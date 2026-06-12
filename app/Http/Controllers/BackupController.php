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
     * Display the backup design page.
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
                $backups[] = [
                    'name' => $file->getFilename(),
                    'size' => number_format($file->getSize() / 1024 / 1024, 2) . ' MB',
                    'date' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }
        }

        // Sort by date descending
        usort($backups, function ($a, $b) {
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

        return view('backup.index', compact('backups', 'logs'));
    }

    /**
     * Download a specific backup file.
     *
     * @param  string  $filename
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
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
     * Run the manual backup process.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'destination' => 'required|in:local,external,network',
        ]);

        $destination = $request->input('destination');

        try {
            // Trigger the Artisan command
            $exitCode = Artisan::call('db:backup-manual', [
                '--destination' => $destination
            ]);

            $output = Artisan::output();
            $status = ($exitCode === 0) ? 'success' : 'error';
            
            // Try to extract filename from output or guess it
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
                return redirect()->route('backup.index')->with('success', __('Backup completed successfully.'));
            } else {
                Log::error("Manual backup failed: " . $output);
                return redirect()->route('backup.index')->with('error', __('Backup failed. Check logs for details.'));
            }
        } catch (\Exception $e) {
            Log::error("Backup Controller error: " . $e->getMessage());
            
            try {
                BackupLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'create',
                    'filename' => 'Error',
                    'destination' => $destination,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ]);
            } catch (\Exception $ex) {
                Log::warning("Could not log backup error: " . $ex->getMessage());
            }

            return redirect()->route('backup.index')->with('error', __('An error occurred while running the backup: ') . $e->getMessage());
        }
    }

    /**
     * Delete a specific backup file.
     *
     * @param  string  $filename
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($filename)
    {
        // Security check: prevent directory traversal
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

            return redirect()->route('backup.index')->with('success', __('Backup deleted successfully.'));
        }

        return redirect()->route('backup.index')->with('error', __('Backup file not found.'));
    }

    /**
     * Upload a backup file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function upload(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file', // Adjust max size if needed
        ]);

        $file = $request->file('backup_file');
        $filename = $file->getClientOriginalName();

        // Ensure it's a .sql file
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
     * Restore a specific backup file.
     *
     * @param  string  $filename
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore($filename)
    {
        // Security check: prevent directory traversal
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            abort(403);
        }

        $path = storage_path('app/backups/' . $filename);

        if (!File::exists($path)) {
            return redirect()->route('backup.index')->with('error', __('Backup file not found.'));
        }

        try {
            // Trigger the Artisan command
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
}
