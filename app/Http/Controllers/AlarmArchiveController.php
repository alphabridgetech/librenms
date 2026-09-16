<?php

namespace App\Http\Controllers;

use App\Models\AlarmArchive;
use App\Models\BackupLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class AlarmArchiveController extends Controller
{
    /**
     * Display a listing of historical alarm archive files.
     */
    public function index(Request $request)
    {
        $query = AlarmArchive::latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('filename', 'like', "%{$search}%");
        }

        $archives = $query->paginate(20);

        $purge_days = DB::table('config')->where('config_name', 'alarm_archive_purge_days')->value('config_value') ?: 90;
        $archive_time = DB::table('config')->where('config_name', 'alarm_archive_time')->value('config_value') ?: '03:00';
        $archive_interval_days = DB::table('config')->where('config_name', 'alarm_archive_interval_days')->value('config_value') ?: 1;
        $archive_destination = DB::table('config')->where('config_name', 'alarm_archive_destination')->value('config_value') ?: 'local';
        $last_run = DB::table('config')->where('config_name', 'alarm_archive_last_run')->value('config_value') ?: 'Never';

        return view('alerts.archive', compact(
            'archives',
            'purge_days',
            'archive_time',
            'archive_interval_days',
            'archive_destination',
            'last_run'
        ));
    }

    /**
     * Trigger manual alarm history archival.
     */
    public function store(Request $request)
    {
        $request->validate([
            'destination' => 'nullable|in:local,external,network',
        ]);

        try {
            $params = ['--force' => true];
            if ($request->filled('destination')) {
                $params['--destination'] = $request->input('destination');
            }

            $exitCode = Artisan::call('alarm:archive', $params);
            $output = Artisan::output();

            if ($exitCode === 0) {
                return redirect()->back()->with('success', __('Alarm history archive generated successfully. ') . trim($output));
            }

            return redirect()->back()->with('error', __('Failed to generate alarm history archive: ') . trim($output));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to generate alarm history archive: ') . $e->getMessage());
        }
    }

    /**
     * Upload an alarm archive CSV file.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'archive_file' => 'required|file',
        ]);

        $file = $request->file('archive_file');
        $filename = $file->getClientOriginalName();

        if (strtolower($file->getClientOriginalExtension()) !== 'csv') {
            return redirect()->back()->with('error', __('Only .csv alarm archive files are allowed.'));
        }

        try {
            $targetDir = '/tftpboot/alarms';
            if (!File::exists($targetDir)) {
                File::makeDirectory($targetDir, 0777, true);
            }

            $targetPath = $targetDir . '/' . $filename;
            $file->move($targetDir, $filename);
            @chmod($targetPath, 0777);

            $bytes = File::exists($targetPath) ? File::size($targetPath) : 0;
            $sizeFormatted = number_format($bytes / 1024, 2) . ' KB';
            if ($bytes >= 1048576) {
                $sizeFormatted = number_format($bytes / 1048576, 2) . ' MB';
            }

            $lineCount = 0;
            if (File::exists($targetPath) && ($handle = fopen($targetPath, 'r')) !== false) {
                while (fgets($handle) !== false) {
                    $lineCount++;
                }
                fclose($handle);
            }

            AlarmArchive::create([
                'filename' => $filename,
                'file_path' => $targetPath,
                'file_size' => $sizeFormatted,
                'line_count' => max(0, $lineCount - 1), // exclude header if present
                'start_date' => now(),
                'end_date' => now(),
            ]);

            try {
                BackupLog::create([
                    'user_id' => Auth::id(),
                    'module' => 'alarm',
                    'action' => 'upload',
                    'filename' => $filename,
                    'destination' => 'local',
                    'status' => 'success',
                ]);
            } catch (\Exception $e) {
                Log::warning("Could not log alarm archive upload: " . $e->getMessage());
            }

            return redirect()->back()->with('success', __('Alarm history archive uploaded successfully to /tftpboot/alarms/'));
        } catch (\Exception $e) {
            try {
                BackupLog::create([
                    'user_id' => Auth::id(),
                    'module' => 'alarm',
                    'action' => 'upload',
                    'filename' => $filename,
                    'destination' => 'local',
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ]);
            } catch (\Exception $ex) {}

            return redirect()->back()->with('error', __('An error occurred while uploading alarm archive: ') . $e->getMessage());
        }
    }

    /**
     * Download specified archive file securely.
     */
    public function download($id)
    {
        $archive = AlarmArchive::findOrFail($id);
        $filePath = $archive->file_path;

        if (!File::exists($filePath)) {
            // Fallback checks
            if (File::exists('/tftpboot/alarms/' . $archive->filename)) {
                $filePath = '/tftpboot/alarms/' . $archive->filename;
            } elseif (File::exists(storage_path('app/backups/alarm_archives/' . $archive->filename))) {
                $filePath = storage_path('app/backups/alarm_archives/' . $archive->filename);
            } else {
                return redirect()->back()->with('error', __('File not found on server at ') . $archive->file_path);
            }
        }

        try {
            BackupLog::create([
                'user_id' => Auth::id(),
                'module' => 'alarm',
                'action' => 'download',
                'filename' => $archive->filename,
                'status' => 'success',
            ]);
        } catch (\Exception $e) {
            Log::warning("Could not log alarm archive download: " . $e->getMessage());
        }

        return response()->download($filePath, $archive->filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * View contents of specified archive file (up to 500 lines for RCA).
     */
    public function view($id)
    {
        $archive = AlarmArchive::findOrFail($id);
        $filePath = $archive->file_path;

        if (!File::exists($filePath)) {
            if (File::exists('/tftpboot/alarms/' . $archive->filename)) {
                $filePath = '/tftpboot/alarms/' . $archive->filename;
            } elseif (File::exists(storage_path('app/backups/alarm_archives/' . $archive->filename))) {
                $filePath = storage_path('app/backups/alarm_archives/' . $archive->filename);
            } else {
                return response()->json(['error' => 'Archive file not found on server.'], 404);
            }
        }

        $lines = [];
        $handle = fopen($filePath, 'r');
        if ($handle) {
            $lineCount = 0;
            while (($data = fgetcsv($handle)) !== false && $lineCount < 500) {
                $lines[] = $data;
                $lineCount++;
            }
            fclose($handle);
        }

        return response()->json([
            'filename' => $archive->filename,
            'file_size' => $archive->file_size,
            'total_lines' => $archive->line_count,
            'data' => $lines
        ]);
    }

    /**
     * Delete an archive file.
     */
    public function destroy($id)
    {
        $archive = AlarmArchive::findOrFail($id);
        $filePath = $archive->file_path;

        if (File::exists($filePath)) {
            File::delete($filePath);
        } elseif (File::exists('/tftpboot/alarms/' . $archive->filename)) {
            File::delete('/tftpboot/alarms/' . $archive->filename);
        } elseif (File::exists(storage_path('app/backups/alarm_archives/' . $archive->filename))) {
            File::delete(storage_path('app/backups/alarm_archives/' . $archive->filename));
        }

        $filename = $archive->filename;
        $archive->delete();

        try {
            BackupLog::create([
                'user_id' => Auth::id(),
                'module' => 'alarm',
                'action' => 'delete',
                'filename' => $filename,
                'status' => 'success',
            ]);
        } catch (\Exception $e) {
            Log::warning("Could not log alarm archive deletion: " . $e->getMessage());
        }

        return redirect()->back()->with('success', __("Archive file {$filename} deleted successfully."));
    }

    /**
     * Update archive buffer settings.
     */
    public function saveSettings(Request $request)
    {
        $request->validate([
            'archive_time' => 'required|regex:/^\d{2}:\d{2}$/',
            'archive_interval_days' => 'required|integer|min:1',
            'archive_destination' => 'required|in:local,external,network',
            'purge_days' => 'required|integer|min:1|max:3650',
        ]);

        DB::table('config')->updateOrInsert(
            ['config_name' => 'alarm_archive_time'],
            ['config_value' => $request->input('archive_time')]
        );

        DB::table('config')->updateOrInsert(
            ['config_name' => 'alarm_archive_interval_days'],
            ['config_value' => $request->input('archive_interval_days')]
        );

        DB::table('config')->updateOrInsert(
            ['config_name' => 'alarm_archive_destination'],
            ['config_value' => $request->input('archive_destination')]
        );

        DB::table('config')->updateOrInsert(
            ['config_name' => 'alarm_archive_purge_days'],
            ['config_value' => $request->input('purge_days')]
        );

        return redirect()->back()->with('success', __('Alarm History Archive settings updated successfully.'));
    }
}
