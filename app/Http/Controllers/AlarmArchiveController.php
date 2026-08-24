<?php

namespace App\Http\Controllers;

use App\Models\AlarmArchive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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

        $max_lines = DB::table('config')->where('config_name', 'alarm_archive_max_lines')->value('config_value') ?: 5000;
        $max_size_mb = DB::table('config')->where('config_name', 'alarm_archive_max_size_mb')->value('config_value') ?: 10;
        $purge_days = DB::table('config')->where('config_name', 'alarm_archive_purge_days')->value('config_value') ?: 90;
        $archive_time = DB::table('config')->where('config_name', 'alarm_archive_time')->value('config_value') ?: '03:00';
        $last_run = DB::table('config')->where('config_name', 'alarm_archive_last_run')->value('config_value') ?: 'Never';

        return view('alerts.archive', compact(
            'archives',
            'max_lines',
            'max_size_mb',
            'purge_days',
            'archive_time',
            'last_run'
        ));
    }

    /**
     * Trigger manual alarm history archival.
     */
    public function store(Request $request)
    {
        try {
            Artisan::call('alarm:archive', ['--force' => true]);
            $output = Artisan::output();
            return redirect()->back()->with('success', __('Alarm history archive generated successfully. ') . trim($output));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to generate alarm history archive: ') . $e->getMessage());
        }
    }

    /**
     * Download specified archive file securely.
     */
    public function download($id)
    {
        $archive = AlarmArchive::findOrFail($id);

        if (!File::exists($archive->file_path)) {
            return redirect()->back()->with('error', __('File not found on server at ') . $archive->file_path);
        }

        return response()->download($archive->file_path, $archive->filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * View contents of specified archive file (up to 500 lines for RCA).
     */
    public function view($id)
    {
        $archive = AlarmArchive::findOrFail($id);

        if (!File::exists($archive->file_path)) {
            return response()->json(['error' => 'Archive file not found on server.'], 404);
        }

        $lines = [];
        $handle = fopen($archive->file_path, 'r');
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

        if (File::exists($archive->file_path)) {
            File::delete($archive->file_path);
        }

        $filename = $archive->filename;
        $archive->delete();

        return redirect()->back()->with('success', __("Archive file {$filename} deleted successfully."));
    }

    /**
     * Update archive buffer settings.
     */
    public function saveSettings(Request $request)
    {
        $request->validate([
            'max_lines' => 'required|integer|min:100|max:50000',
            'max_size_mb' => 'required|numeric|min:1|max:500',
            'purge_days' => 'required|integer|min:1|max:3650',
            'archive_time' => 'required|regex:/^\d{2}:\d{2}$/',
        ]);

        DB::table('config')->updateOrInsert(
            ['config_name' => 'alarm_archive_max_lines'],
            ['config_value' => $request->input('max_lines')]
        );

        DB::table('config')->updateOrInsert(
            ['config_name' => 'alarm_archive_max_size_mb'],
            ['config_value' => $request->input('max_size_mb')]
        );

        DB::table('config')->updateOrInsert(
            ['config_name' => 'alarm_archive_purge_days'],
            ['config_value' => $request->input('purge_days')]
        );

        DB::table('config')->updateOrInsert(
            ['config_name' => 'alarm_archive_time'],
            ['config_value' => $request->input('archive_time')]
        );

        return redirect()->back()->with('success', __('Alarm History Archive settings updated successfully.'));
    }
}
