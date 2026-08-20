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
                $logs = BackupLog::with('user')->latest()->limit(500)->get();
            }
        } catch (\Exception $e) {
            Log::warning("Could not fetch backup logs: " . $e->getMessage());
        }

        // Node / Device Startup-Config Backups
        $nodeBackupPath = '/tftpboot';
        $nodeBackups = [];

        if (File::exists($nodeBackupPath)) {
            $nodeFiles = File::files($nodeBackupPath);
            foreach ($nodeFiles as $file) {
                if ($file->isFile()) {
                    $nodeBackups[] = [
                        'name' => $file->getFilename(),
                        'size' => number_format($file->getSize() / 1024, 2) . ' KB',
                        'date' => date('Y-m-d H:i:s', $file->getMTime()),
                    ];
                }
            }
        }

        usort($nodeBackups, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        // Active Devices
        $devices = collect();
        try {
            $devices = \App\Models\Device::where('disabled', 0)->orderBy('hostname')->get();
        } catch (\Exception $e) {
            Log::warning("Could not fetch devices: " . $e->getMessage());
        }

        // Node / Config Backup Logs
        $nodeLogs = collect();
        try {
            if (Schema::hasTable('config_backup_logs')) {
                $nodeLogs = \App\Models\ConfigBackupLog::with('user', 'device')->latest()->limit(500)->get();
            }
        } catch (\Exception $e) {
            Log::warning("Could not fetch config backup logs: " . $e->getMessage());
        }

        $db_backup_time = \DB::table('config')->where('config_name', 'db_backup_time')->value('config_value') ?: '02:00';
        $db_backup_destination = \DB::table('config')->where('config_name', 'db_backup_destination')->value('config_value') ?: 'local';
        $db_backup_retention_days = \DB::table('config')->where('config_name', 'db_backup_retention_days')->value('config_value') ?: 30;
        $db_backup_interval_days = \DB::table('config')->where('config_name', 'db_backup_interval_days')->value('config_value') ?: 1;

        $rrd_backup_time = \DB::table('config')->where('config_name', 'rrd_backup_time')->value('config_value') ?: '02:30';
        $rrd_backup_destination = \DB::table('config')->where('config_name', 'rrd_backup_destination')->value('config_value') ?: 'local';
        $rrd_backup_purge_days = \DB::table('config')->where('config_name', 'rrd_backup_purge_days')->value('config_value') ?: 30;
        $rrd_backup_interval_days = \DB::table('config')->where('config_name', 'rrd_backup_interval_days')->value('config_value') ?: 1;

        $node_backup_time = \DB::table('config')->where('config_name', 'backup_time')->value('config_value') ?: '01:30';
        $node_tftp_server_ip = \DB::table('config')->where('config_name', 'tftp_server_ip')->value('config_value') ?: (request()->getHost() ?: '192.168.200.128');
        $node_backup_retention_days = \DB::table('config')->where('config_name', 'backup_retention_days')->value('config_value') ?: 30;
        $node_backup_interval_days = \DB::table('config')->where('config_name', 'node_backup_interval_days')->value('config_value') ?: 1;

        return view('backup.index', compact(
            'backups', 
            'rrdBackups', 
            'nodeBackups',
            'devices',
            'logs', 
            'nodeLogs',
            'db_backup_time', 
            'db_backup_destination', 
            'db_backup_retention_days',
            'db_backup_interval_days',
            'rrd_backup_time',
            'rrd_backup_destination',
            'rrd_backup_purge_days',
            'rrd_backup_interval_days',
            'node_backup_time',
            'node_tftp_server_ip',
            'node_backup_retention_days',
            'node_backup_interval_days'
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
            'db_backup_interval_days' => 'required|integer|min:1',
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

            \DB::table('config')->updateOrInsert(
                ['config_name' => 'db_backup_interval_days'],
                ['config_value' => $request->db_backup_interval_days]
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
            'rrd_backup_interval_days' => 'required|integer|min:1',
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

            \DB::table('config')->updateOrInsert(
                ['config_name' => 'rrd_backup_interval_days'],
                ['config_value' => $request->rrd_backup_interval_days]
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
                    'message' => ($status === 'error') ? trim($output) : 'Manual database backup completed successfully.',
                ]);
            } catch (\Exception $e) {
                Log::warning("Could not log backup creation: " . $e->getMessage());
            }

            if ($exitCode === 0) {
                try {
                    \DB::table('config')->updateOrInsert(
                        ['config_name' => 'db_backup_last_run'],
                        ['config_value' => now()->toDateTimeString()]
                    );
                } catch (\Exception $e) {}

                return redirect()->route('backup.index')->with('success', __('Database backup completed successfully.'));
            } else {
                Log::error("Manual backup failed: " . $output);
                return redirect()->route('backup.index')->with('error', __('Database backup failed: ') . trim($output));
            }
        } catch (\Exception $e) {
            Log::error("Backup Controller error: " . $e->getMessage());
            try {
                BackupLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'create',
                    'filename' => 'Database Backup',
                    'destination' => $destination,
                    'status' => 'error',
                    'message' => 'Manual database backup failed with exception: ' . $e->getMessage(),
                ]);
            } catch (\Exception $ex) {}
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
                    'message' => ($status === 'error') ? trim($output) : 'Manual RRD backup completed successfully.',
                ]);
            } catch (\Exception $e) {
                Log::warning("Could not log RRD backup creation: " . $e->getMessage());
            }

            if ($exitCode === 0) {
                try {
                    \DB::table('config')->updateOrInsert(
                        ['config_name' => 'rrd_backup_last_run'],
                        ['config_value' => now()->toDateTimeString()]
                    );
                } catch (\Exception $e) {}

                return redirect()->route('backup.index')->with('success', __('RRD files backup completed successfully.'));
            } else {
                Log::error("Manual RRD backup failed: " . $output);
                return redirect()->route('backup.index')->with('error', __('RRD backup failed: ') . trim($output));
            }
        } catch (\Exception $e) {
            Log::error("RRD Backup Controller error: " . $e->getMessage());
            try {
                BackupLog::create([
                    'user_id' => Auth::id(),
                    'action' => 'create',
                    'filename' => 'RRD Files',
                    'destination' => $destination,
                    'status' => 'error',
                    'message' => 'Manual RRD backup failed with exception: ' . $e->getMessage(),
                ]);
            } catch (\Exception $ex) {}
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

    /**
     * Save the Node / Startup-Config backup schedule settings.
     */
    public function saveNodeSchedule(Request $request)
    {
        $request->validate([
            'node_backup_time' => 'required|regex:/^\d{2}:\d{2}$/',
            'node_tftp_server_ip' => 'required|string',
            'node_backup_retention_days' => 'required|integer|min:1',
            'node_backup_interval_days' => 'required|integer|min:1',
        ]);

        try {
            \DB::table('config')->updateOrInsert(
                ['config_name' => 'backup_time'],
                ['config_value' => $request->node_backup_time]
            );

            \DB::table('config')->updateOrInsert(
                ['config_name' => 'tftp_server_ip'],
                ['config_value' => $request->node_tftp_server_ip]
            );

            \DB::table('config')->updateOrInsert(
                ['config_name' => 'backup_retention_days'],
                ['config_value' => $request->node_backup_retention_days]
            );

            \DB::table('config')->updateOrInsert(
                ['config_name' => 'node_backup_interval_days'],
                ['config_value' => $request->node_backup_interval_days]
            );

            return redirect()->route('backup.index')->with('success', __('Node backup schedule saved successfully.'));
        } catch (\Exception $e) {
            Log::error("Failed to save Node backup schedule: " . $e->getMessage());
            return redirect()->route('backup.index')->with('error', __('Failed to save Node schedule: ') . $e->getMessage());
        }
    }

    /**
     * Trigger manual Node / Startup-Config backup.
     */
    public function storeNode(Request $request)
    {
        $request->validate([
            'device_id' => 'nullable|string',
            'tftp_server_ip' => 'required|string',
        ]);

        $deviceId = $request->input('device_id');
        $tftpServer = $request->input('tftp_server_ip');

        try {
            if (empty($deviceId) || $deviceId === 'all') {
                $exitCode = Artisan::call('backup:startup-configs');
                $output = Artisan::output();

                if ($exitCode === 0) {
                    \DB::table('config')->updateOrInsert(
                        ['config_name' => 'node_backup_last_run'],
                        ['config_value' => now()->toDateTimeString()]
                    );
                    return redirect()->route('backup.index')->with('success', __('Startup-configs backup for all active nodes completed successfully.'));
                } else {
                    return redirect()->route('backup.index')->with('error', __('Node startup-config backup failed: ') . trim($output));
                }
            } else {
                $device = \App\Models\Device::find($deviceId);
                if (!$device) {
                    return redirect()->route('backup.index')->with('error', __('Selected device not found.'));
                }

                $pluginPath = base_path('librenms-ansible-inventory-plugin');
                $playbook = "{$pluginPath}/playbooks/tftpexport.yml";
                $hostname = $device->hostname;
                $hostsFile = "{$pluginPath}/hosts/{$hostname}.yml";

                if (!file_exists($hostsFile)) {
                    return redirect()->route('backup.index')->with('error', __("Ansible hosts file for {$hostname} not found."));
                }

                $date = date('Y-m-d_His');
                $destination_file = "{$hostname}_{$date}_startup-config";
                $cmd = [
                    'ansible-playbook',
                    '-i', $hostsFile,
                    $playbook,
                    '-e', json_encode([
                        'tftp_server' => $tftpServer,
                        'filename' => 'startup-config',
                        'destination_file' => $destination_file
                    ])
                ];

                $process = new \Symfony\Component\Process\Process($cmd);
                $process->setTimeout(600);
                $process->run();

                if ($process->isSuccessful()) {
                    $localPath = "/tftpboot/{$destination_file}";
                    if (!file_exists($localPath)) {
                        $downloadCmd = "tftp -g -r " . escapeshellarg($destination_file) . " -l " . escapeshellarg($localPath) . " " . escapeshellarg($tftpServer);
                        shell_exec($downloadCmd);
                    }

                    try {
                        \App\Models\ConfigBackupLog::create([
                            'device_id' => $device->device_id,
                            'user_id' => Auth::id(),
                            'filename' => $destination_file,
                            'tftp_server' => $tftpServer,
                            'status' => 'success',
                            'message' => 'Manual startup-config export completed successfully.',
                        ]);
                    } catch (\Exception $e) {}

                    \DB::table('config')->updateOrInsert(
                        ['config_name' => 'node_backup_last_run'],
                        ['config_value' => now()->toDateTimeString()]
                    );

                    return redirect()->route('backup.index')->with('success', __("Startup-config for {$hostname} exported successfully."));
                } else {
                    $errorOut = $process->getErrorOutput() ?: $process->getOutput();
                    try {
                        \App\Models\ConfigBackupLog::create([
                            'device_id' => $device->device_id,
                            'user_id' => Auth::id(),
                            'filename' => $destination_file,
                            'tftp_server' => $tftpServer,
                            'status' => 'error',
                            'message' => 'Manual startup-config export failed: ' . trim($errorOut),
                        ]);
                    } catch (\Exception $e) {}

                    return redirect()->route('backup.index')->with('error', __("Export failed for {$hostname}: ") . trim($errorOut));
                }
            }
        } catch (\Exception $e) {
            Log::error("Node backup error: " . $e->getMessage());
            return redirect()->route('backup.index')->with('error', __('An error occurred while running node backup: ') . $e->getMessage());
        }
    }

    /**
     * Download Node backup file from /tftpboot.
     */
    public function downloadNode($filename)
    {
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            abort(403);
        }

        $filePath = "/tftpboot/{$filename}";

        if (File::exists($filePath)) {
            return response()->download($filePath);
        }

        return redirect()->route('backup.index')->with('error', __('File not found on TFTP server.'));
    }

    /**
     * Delete Node backup file from /tftpboot.
     */
    public function destroyNode($filename)
    {
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            abort(403);
        }

        $filePath = "/tftpboot/{$filename}";

        if (File::exists($filePath)) {
            File::delete($filePath);
            return redirect()->route('backup.index')->with('success', __('Node backup file deleted successfully.'));
        }

        return redirect()->route('backup.index')->with('error', __('Backup file not found.'));
    }
}
