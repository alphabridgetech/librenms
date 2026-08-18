<?php

namespace App\Http\Controllers;

use App\Models\BackupLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BackupController extends Controller
{
    /**
     * Display the backup page (Database and RRD backups).
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $backupPath = storage_path('app/backups');
        $backups = [];

        if (File::exists($backupPath)) {
            $files = File::files($backupPath);
            foreach ($files as $file) {
                if ($file->getExtension() === 'sql' || str_starts_with($file->getFilename(), 'backup_')) {
                    $backups[] = [
                        'name' => $file->getFilename(),
                        'size' => number_format($file->getSize() / 1024 / 1024, 2) . ' MB',
                        'date' => date('Y-m-d H:i:s', $file->getMTime()),
                    ];
                }
            }
        }

        // Sort DB backups by date descending
        usort($backups, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        // RRD Backups
        $rrdBackupPath = storage_path('app/backups/rrd');
        $rrdBackups = [];

        if (File::exists($rrdBackupPath)) {
            $rrdFiles = File::files($rrdBackupPath);
            foreach ($rrdFiles as $file) {
                if (str_starts_with($file->getFilename(), 'rrd_backup_') || str_ends_with($file->getFilename(), '.tar.gz')) {
                    $rrdBackups[] = [
                        'name' => $file->getFilename(),
                        'size' => number_format($file->getSize() / 1024 / 1024, 2) . ' MB',
                        'date' => date('Y-m-d H:i:s', $file->getMTime()),
                    ];
                }
            }
        }

        // Sort RRD backups by date descending
        usort($rrdBackups, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        // Get recent logs safely
        $logs = collect();
        try {
            if (Schema::hasTable('backup_logs')) {
                $logs = BackupLog::with('user')->latest()->limit(50)->get();
            }
        } catch (\Exception $e) {
            Log::warning("Could not fetch backup logs: " . $e->getMessage());
        }

        $db_backup_time = \DB::table('config')->where('config_name', 'db_backup_time')->value('config_value') ?: '02:00';
        $db_backup_destination = \DB::table('config')->where('config_name', 'db_backup_destination')->value('config_value') ?: 'local';
        $db_backup_retention_days = \DB::table('config')->where('config_name', 'db_backup_retention_days')->value('config_value') ?: 30;

        $rrd_backup_time = \DB::table('config')->where('config_name', 'rrd_backup_time')->value('config_value') ?: '02:30';
        $rrd_backup_destination = \DB::table('config')->where('config_name', 'rrd_backup_destination')->value('config_value') ?: 'local';
        $rrd_backup_purge_days = \DB::table('config')->where('config_name', 'rrd_backup_purge_days')->value('config_value') ?: 30;

        return view('backup.index', compact(
            'backups', 
            'rrdBackups', 
            'logs', 
            'db_backup_time', 
            'db_backup_destination', 
            'db_backup_retention_days',
            'rrd_backup_time',
            'rrd_backup_destination',
            'rrd_backup_purge_days'
        ));
    }

    /**
     * Save the database backup schedule settings.
     */
    public function saveSchedule(Request $request)
    {
        $request->validate([
            'db_backup_time' => 'required|regex:/^\d{2}:\d{2}$/',
            'db_backup_destination' => 'required|in:local,external,network',
            'db_backup_retention_days' => 'required|integer|min:1',
        ]);

        try {
            \DB::table('config')->updateOrInsert(
                ['config_name' => 'db_backup_time'],
                ['config_value' => $request->db_backup_time]
            );

            \DB::table('config')->updateOrInsert(
                ['config_name' => 'db_backup_destination'],
                ['config_value' => $request->db_backup_destination]
            );

            \DB::table('config')->updateOrInsert(
                ['config_name' => 'db_backup_retention_days'],
                ['config_value' => $request->db_backup_retention_days]
            );

            return redirect()->route('backup.index')->with('success', __('Database backup schedule saved successfully.'));
        } catch (\Exception $e) {
            Log::error("Failed to save DB backup schedule: " . $e->getMessage());
            return redirect()->route('backup.index')->with('error', __('Failed to save DB schedule: ') . $e->getMessage());
        }
    }

    /**
     * Save the RRD backup schedule settings.
     */
    public function saveRrdSchedule(Request $request)
    {
        $request->validate([
            'rrd_backup_time' => 'required|regex:/^\d{2}:\d{2}$/',
            'rrd_backup_destination' => 'required|in:local,external,network',
            'rrd_backup_purge_days' => 'required|integer|min:1',
        ]);

        try {
            \DB::table('config')->updateOrInsert(
                ['config_name' => 'rrd_backup_time'],
                ['config_value' => $request->rrd_backup_time]
            );

            \DB::table('config')->updateOrInsert(
                ['config_name' => 'rrd_backup_destination'],
                ['config_value' => $request->rrd_backup_destination]
            );

            \DB::table('config')->updateOrInsert(
                ['config_name' => 'rrd_backup_purge_days'],
                ['config_value' => $request->rrd_backup_purge_days]
            );

            return redirect()->route('backup.index')->with('success', __('RRD backup schedule saved successfully.'));
        } catch (\Exception $e) {
            Log::error("Failed to save RRD backup schedule: " . $e->getMessage());
            return redirect()->route('backup.index')->with('error', __('Failed to save RRD schedule: ') . $e->getMessage());
        }
    }

    /**
     * Download a specific DB backup file.
     */
    public function download($filename)
    {
        $path = storage_path('app/backups/' . $filename);

        if (!File::exists($path)) {
            abort(404);
        }

        try {
            BackupLog::create([
                'user_id' => Auth::id(),
                'action' => 'download',
                'filename' => $filename,
                'status' => 'success',
            ]);
        } catch (\Exception $e) {
            Log::warning("Could not log backup download: " . $e->getMessage());
        }

        return response()->download($path);
    }

    /**
     * Download a specific RRD backup file.
     */
    public function downloadRrd($filename)
    {
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            abort(403);
        }

        $path = storage_path('app/backups/rrd/' . $filename);

        if (!File::exists($path)) {
            abort(404);
        }

        try {
            BackupLog::create([
                'user_id' => Auth::id(),
                'action' => 'download',
                'filename' => 'RRD: ' . $filename,
                'status' => 'success',
            ]);
        } catch (\Exception $e) {
            Log::warning("Could not log RRD backup download: " . $e->getMessage());
        }

        return response()->download($path);
    }

    /**
     * Run manual DB backup.
     */
    public function store(Request $request)
    {
        $request->validate([
            'destination' => 'required|in:local,external,network',
        ]);

        $destination = $request->input('destination');

        try {
            $exitCode = Artisan::call('db:backup-manual', [
                '--destination' => $destination
            ]);

            $output = Artisan::output();
            $status = ($exitCode === 0) ? 'success' : 'error';
            
            $filename = "Unknown";
            if (preg_match('/Target path: (.*)/', $output, $matches)) {
                $filename = basename($matches[1]);
            }

            try {
                BackupLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'create',
                    'filename' => $filename,
                    'destination' => $destination,
                    'status' => $status,
                    'message' => ($status === 'error') ? $output : null,
                ]);
            } catch (\Exception $e) {
                Log::warning("Could not log backup creation: " . $e->getMessage());
            }

            if ($exitCode === 0) {
                return redirect()->route('backup.index')->with('success', __('Database backup completed successfully.'));
            } else {
                Log::error("Manual backup failed: " . $output);
                return redirect()->route('backup.index')->with('error', __('Database backup failed. Check logs for details.'));
            }
        } catch (\Exception $e) {
            Log::error("Backup Controller error: " . $e->getMessage());
            return redirect()->route('backup.index')->with('error', __('An error occurred while running DB backup: ') . $e->getMessage());
        }
    }

    /**
     * Run manual RRD backup.
     */
    public function storeRrd(Request $request)
    {
        $request->validate([
            'destination' => 'required|in:local,external,network',
        ]);

        $destination = $request->input('destination');

        try {
            $exitCode = Artisan::call('rrd:backup-manual', [
                '--destination' => $destination
            ]);

            $output = Artisan::output();
            $status = ($exitCode === 0) ? 'success' : 'error';
            
            $filename = "Unknown";
            if (preg_match('/Target path: (.*)/', $output, $matches)) {
                $filename = basename($matches[1]);
            }

            try {
                BackupLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'create',
                    'filename' => 'RRD: ' . $filename,
                    'destination' => $destination,
                    'status' => $status,
                    'message' => ($status === 'error') ? $output : null,
                ]);
            } catch (\Exception $e) {
                Log::warning("Could not log RRD backup creation: " . $e->getMessage());
            }

            if ($exitCode === 0) {
                return redirect()->route('backup.index')->with('success', __('RRD files backup completed successfully.'));
            } else {
                Log::error("Manual RRD backup failed: " . $output);
                return redirect()->route('backup.index')->with('error', __('RRD backup failed. Check logs for details.'));
            }
        } catch (\Exception $e) {
            Log::error("RRD Backup Controller error: " . $e->getMessage());
            return redirect()->route('backup.index')->with('error', __('An error occurred while running RRD backup: ') . $e->getMessage());
        }
    }

    /**
     * Delete a specific DB backup file.
     */
    public function destroy($filename)
    {
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            abort(403);
        }

        $path = storage_path('app/backups/' . $filename);

        if (File::exists($path)) {
            File::delete($path);

            try {
                BackupLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'delete',
                    'filename' => $filename,
                    'status' => 'success',
                ]);
            } catch (\Exception $e) {
                Log::warning("Could not log backup deletion: " . $e->getMessage());
            }

            return redirect()->route('backup.index')->with('success', __('Database backup deleted successfully.'));
        }

        return redirect()->route('backup.index')->with('error', __('Database backup file not found.'));
    }

    /**
     * Delete a specific RRD backup file.
     */
    public function destroyRrd($filename)
    {
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            abort(403);
        }

        $path = storage_path('app/backups/rrd/' . $filename);

        if (File::exists($path)) {
            File::delete($path);

            try {
                BackupLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'delete',
                    'filename' => 'RRD: ' . $filename,
                    'status' => 'success',
                ]);
            } catch (\Exception $e) {
                Log::warning("Could not log RRD backup deletion: " . $e->getMessage());
            }

            return redirect()->route('backup.index')->with('success', __('RRD backup deleted successfully.'));
        }

        return redirect()->route('backup.index')->with('error', __('RRD backup file not found.'));
    }

    /**
     * Upload a DB backup file.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file',
        ]);

        $file = $request->file('backup_file');
        $filename = $file->getClientOriginalName();

        if ($file->getClientOriginalExtension() !== 'sql') {
            return redirect()->route('backup.index')->with('error', __('Only .sql files are allowed.'));
        }

        try {
            $path = $file->storeAs('backups', $filename);
            
            try {
                BackupLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'upload',
                    'filename' => $filename,
                    'status' => 'success',
                ]);
            } catch (\Exception $e) {
                Log::warning("Could not log backup upload: " . $e->getMessage());
            }

            if ($request->has('restore_immediately')) {
                return $this->restore($filename);
            }

            return redirect()->route('backup.index')->with('success', __('Backup file uploaded successfully.'));
        } catch (\Exception $e) {
            Log::error("Backup upload error: " . $e->getMessage());
            return redirect()->route('backup.index')->with('error', __('An error occurred while uploading: ') . $e->getMessage());
        }
    }

    /**
     * Restore a specific DB backup file.
     */
    public function restore($filename)
    {
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            abort(403);
        }

        $path = storage_path('app/backups/' . $filename);

        if (!File::exists($path)) {
            return redirect()->route('backup.index')->with('error', __('Backup file not found.'));
        }

        try {
            $exitCode = Artisan::call('db:restore-manual', [
                'filename' => $filename
            ]);

            $output = Artisan::output();
            $status = ($exitCode === 0) ? 'success' : 'error';

            try {
                BackupLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'restore',
                    'filename' => $filename,
                    'status' => $status,
                    'message' => ($status === 'error') ? $output : null,
                ]);
            } catch (\Exception $e) {
                Log::warning("Could not log backup restoration: " . $e->getMessage());
            }

            if ($exitCode === 0) {
                return redirect()->route('backup.index')->with('success', __('Database restored successfully from ' . $filename));
            } else {
                Log::error("Manual restore failed: " . $output);
                return redirect()->route('backup.index')->with('error', __('Restoration failed. Check logs for details.'));
            }
        } catch (\Exception $e) {
            Log::error("Backup Controller restore error: " . $e->getMessage());
            return redirect()->route('backup.index')->with('error', __('An error occurred while restoring: ') . $e->getMessage());
        }
    }

    /**
     * Restore a specific RRD backup file.
     */
    public function restoreRrd($filename)
    {
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            abort(403);
        }

        $path = storage_path('app/backups/rrd/' . $filename);

        if (!File::exists($path)) {
            return redirect()->route('backup.index')->with('error', __('RRD backup file not found.'));
        }

        try {
            $exitCode = Artisan::call('rrd:restore-manual', [
                'filename' => $filename
            ]);

            $output = Artisan::output();
            $status = ($exitCode === 0) ? 'success' : 'error';

            try {
                BackupLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'restore',
                    'filename' => 'RRD: ' . $filename,
                    'status' => $status,
                    'message' => ($status === 'error') ? $output : null,
                ]);
            } catch (\Exception $e) {
                Log::warning("Could not log RRD backup restoration: " . $e->getMessage());
            }

            if ($exitCode === 0) {
                return redirect()->route('backup.index')->with('success', __('RRD files restored successfully from ' . $filename));
            } else {
                Log::error("Manual RRD restore failed: " . $output);
                return redirect()->route('backup.index')->with('error', __('RRD restoration failed. Check logs for details.'));
            }
        } catch (\Exception $e) {
            Log::error("RRD Restore error: " . $e->getMessage());
            return redirect()->route('backup.index')->with('error', __('An error occurred while restoring RRD files: ') . $e->getMessage());
        }
    }
}
