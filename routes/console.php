<?php

use App\Console\Commands\MaintenanceCleanupNetworks;
use App\Console\Commands\MaintenanceFetchOuis;
use App\Jobs\PingCheck;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Process\Process;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('device:rename
    {old hostname : ' . __('The existing hostname, IP, or device id') . '}
    {new hostname : ' . __('The new hostname or IP') . '}
', function () {
    /** @var Illuminate\Console\Command $this */
    (new Process([
        base_path('renamehost.php'),
        $this->argument('old hostname'),
        $this->argument('new hostname'),
    ]))->setTimeout(null)->setIdleTimeout(null)->setTty(true)->run();
})->purpose(__('Rename a device, this can be used to change the hostname or IP of a device'));

Artisan::command('update', function () {
    (new Process([base_path('daily.sh')]))->setTimeout(null)->setIdleTimeout(null)->setTty(true)->run();
})->purpose(__('Update LibreNMS and run maintenance routines'));

Artisan::command('poller:ping
    {groups?* : ' . __('Optional List of distributed poller groups to poll') . '}
', function () {
    PingCheck::dispatch($this->argument('groups'));
})->purpose(__('Check if devices are up or down via icmp'));

Artisan::command('poller:discovery
    {device spec : ' . __('Device spec to discover: device_id, hostname, wildcard, odd, even, all, new') . '}
    {--o|os= : ' . __('Only devices with the specified operating system') . '}
    {--t|type= : ' . __('Only devices with the specified type') . '}
    {--m|modules= : ' . __('Specify single module to be run. Comma separate modules, submodules may be added with /') . '}
', function () {
    $command = [base_path('discovery.php'), '-h', $this->argument('device spec')];
    if ($this->option('os')) {
        $command[] = '-o';
        $command[] = $this->option('os');
    }
    if ($this->option('type')) {
        $command[] = '-t';
        $command[] = $this->option('type');
    }
    if ($this->option('modules')) {
        $command[] = '-m';
        $command[] = $this->option('modules');
    }
    if (($verbosity = $this->getOutput()->getVerbosity()) >= 128) {
        $command[] = '-d';
        if ($verbosity >= 256) {
            $command[] = '-v';
        }
    }
    (new Process($command))->setTimeout(null)->setIdleTimeout(null)->setTty(true)->run();
})->purpose(__('Discover information about existing devices, defines what will be polled'));

Artisan::command('poller:alerts', function () {
    $command = [base_path('alerts.php')];
    if (($verbosity = $this->getOutput()->getVerbosity()) >= 128) {
        $command[] = '-d';
        if ($verbosity >= 256) {
            $command[] = '-v';
        }
    }

    (new Process($command))->setTimeout(null)->setIdleTimeout(null)->setTty(true)->run();
})->purpose(__('Check for any pending alerts and deliver them via defined transports'));

Artisan::command('poller:billing
    {bill id? : ' . __('The bill id to poll') . '}
', function () {
    /** @var Illuminate\Console\Command $this */
    $command = [base_path('poll-billing.php')];
    if ($this->argument('bill id')) {
        $command[] = '-b';
        $command[] = $this->argument('bill id');
    }

    if (($verbosity = $this->getOutput()->getVerbosity()) >= 128) {
        $command[] = '-d';
        if ($verbosity >= 256) {
            $command[] = '-v';
        }
    }
    (new Process($command))->setTimeout(null)->setIdleTimeout(null)->setTty(true)->run();
})->purpose(__('Collect billing data'));

Artisan::command('poller:services
    {device spec : ' . __('Device spec to poll: device_id, hostname, wildcard, all') . '}
    {--x|no-data : ' . __('Do not update datastores (RRD, InfluxDB, etc)') . '}
', function () {
    /** @var Illuminate\Console\Command $this */
    $command = [base_path('check-services.php')];
    if ($this->option('no-data')) {
        array_push($command, '-r', '-f', '-p');
    }
    if ($this->argument('device spec') !== 'all') {
        $command[] = '-h';
        $command[] = $this->argument('device spec');
    }

    if (($verbosity = $this->getOutput()->getVerbosity()) >= 128) {
        $command[] = '-d';
        if ($verbosity >= 256) {
            $command[] = '-v';
        }
    }
    (new Process($command))->setTimeout(null)->setIdleTimeout(null)->setTty(true)->run();
})->purpose(__('Update LibreNMS and run maintenance routines'));

Artisan::command('poller:billing-calculate
    {--c|clear-history : ' . __('Delete all billing history') . '}
', function () {
    /** @var Illuminate\Console\Command $this */
    $command = [base_path('billing-calculate.php')];
    if ($this->option('clear-history')) {
        $command[] = '-r';
    }

    (new Process($command))->setTimeout(null)->setIdleTimeout(null)->setTty(true)->run();
})->purpose(__('Run billing calculations'));

Artisan::command('scan
    {network?* : ' . __('CIDR notation network(s) to scan, can be ommited if \'nets\' config is set') . '}
    {--P|ping-only : ' . __('Add the device as a ping only device if it replies to ping but not SNMP') . '}
    {--o|dns-only : ' . __('Only DNS resolved Devices') . '}
    {--t|threads=32 : ' . __('How many IPs to scan at a time, more will increase the scan speed, but could overload your system') . '}
    {--l|legend : ' . __('Print the legend') . '}
', function () {
    /** @var Illuminate\Console\Command $this */
    $command = [base_path('snmp-scan.py')];

    if (empty($this->argument('network')) && ! \App\Facades\LibrenmsConfig::has('nets')) {
        $this->error(__('Network is required if \'nets\' is not set in the config'));

        return 1;
    }

    if ($this->option('dns-only')) {
        $command[] = '-o';
    }

    if ($this->option('ping-only')) {
        $command[] = '-P';
    }

    $command[] = '-t';
    $command[] = $this->option('threads');

    if ($this->option('legend')) {
        $command[] = '-l';
    }

    $verbosity = $this->getOutput()->getVerbosity();
    if ($verbosity >= 64) {
        $command[] = '-v';
        if ($verbosity >= 128) {
            $command[] = '-v';
            if ($verbosity >= 256) {
                $command[] = '-v';
            }
        }
    }

    $command = array_merge($command, $this->argument('network'));

    $scan_process = (new Process($command))
        ->setTimeout(null)
        ->setIdleTimeout(null)
        ->setTty(Process::isTtySupported() && ! $this->option('quiet'));
    $scan_process->run();

    if (! Process::isTtySupported() && ! $this->option('quiet')) {
        // just dump the output after we are done if we couldn't use tty
        $this->line($scan_process->getOutput());
    }

    return $scan_process->getExitCode();
})->purpose(__('Scan the network for hosts and try to add them to LibreNMS'));

// mark schedule working
Schedule::call(function () {
    Cache::put('scheduler_working', now(), now()->addMinutes(6));
})->everyMinute();

// schedule maintenance, should be after all others
$maintenance_log_file = Config::get('log_dir') . '/maintenance.log';
Schedule::command(MaintenanceFetchOuis::class, ['--wait'])
    ->weeklyOn(0, '1:00')
    ->onOneServer()
    ->appendOutputTo($maintenance_log_file);

Schedule::command(MaintenanceCleanupNetworks::class, [])
    ->weeklyOn(0, '2:00')
    ->onOneServer()
    ->appendOutputTo($maintenance_log_file);

Artisan::command('backup:startup-configs', function () {
    /** @var Illuminate\Console\Command $this */
    $tftpServer = \DB::table('config')->where('config_name', 'tftp_server_ip')->value('config_value');
    
    if (empty($tftpServer) || $tftpServer === 'localhost' || $tftpServer === '127.0.0.1') {
        $tftpServer = parse_url(config('app.url'), PHP_URL_HOST);
    }
    
    if (empty($tftpServer) || $tftpServer === 'localhost' || $tftpServer === '127.0.0.1') {
        $tftpServer = '192.168.200.128'; // Fallback IP
    }

    $devices = \App\Models\Device::where('disabled', 0)->get();
    $this->info("Starting daily startup-config backups for " . $devices->count() . " devices using TFTP server: " . $tftpServer);

    $pluginPath = base_path('librenms-ansible-inventory-plugin');
    $playbook = "{$pluginPath}/playbooks/tftpexport.yml";

    foreach ($devices as $device) {
        $hostname = $device->hostname;
        $hostsFile = "{$pluginPath}/hosts/{$hostname}.yml";

        if (!file_exists($hostsFile)) {
            $this->warn("Skipping device {$hostname}: hosts definition file not found.");
            continue;
        }

        $date = date('Y-m-d');
        $destination_file = "{$hostname}_{$date}_startup-config";
        
        $this->info("Exporting startup-config for {$hostname} to {$destination_file}...");

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

        $process = new Process($cmd);
        $process->setTimeout(12000);

        try {
            $process->run();

            if ($process->isSuccessful()) {
                $this->info("Successfully backed up {$hostname}.");
                
                // Sync file locally if it was uploaded to a remote TFTP server
                $localPath = "/tftpboot/{$destination_file}";
                if (!file_exists($localPath)) {
                    $downloadCmd = "tftp -g -r " . escapeshellarg($destination_file) . " -l " . escapeshellarg($localPath) . " " . escapeshellarg($tftpServer);
                    shell_exec($downloadCmd);
                }

                try {
                    \App\Models\ConfigBackupLog::create([
                        'device_id' => $device->device_id,
                        'user_id' => null, // Automated background task
                        'filename' => $destination_file,
                        'tftp_server' => $tftpServer,
                        'status' => 'success',
                        'message' => "Daily automated backup completed successfully.",
                    ]);
                } catch (\Exception $e) {
                    $this->warn("Could not log automated backup to DB: " . $e->getMessage());
                }
            } else {
                $this->error("Failed to back up {$hostname}: " . $process->getErrorOutput());
                try {
                    \App\Models\ConfigBackupLog::create([
                        'device_id' => $device->device_id,
                        'user_id' => null,
                        'filename' => $destination_file,
                        'tftp_server' => $tftpServer,
                        'status' => 'error',
                        'message' => "Daily automated backup failed: " . $process->getErrorOutput(),
                    ]);
                } catch (\Exception $e) {
                    $this->warn("Could not log automated backup error to DB: " . $e->getMessage());
                }
            }
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            $this->error("Failed to back up {$hostname} due to timeout (120s).");
            try {
                \App\Models\ConfigBackupLog::create([
                    'device_id' => $device->device_id,
                    'user_id' => null,
                    'filename' => $destination_file,
                    'tftp_server' => $tftpServer,
                    'status' => 'error',
                    'message' => "Daily automated backup failed: Process timed out after 120 seconds.",
                ]);
            } catch (\Exception $dbEx) {
                $this->warn("Could not log automated backup timeout to DB: " . $dbEx->getMessage());
            }
        } catch (\Exception $e) {
            $this->error("Failed to back up {$hostname} due to exception: " . $e->getMessage());
            try {
                \App\Models\ConfigBackupLog::create([
                    'device_id' => $device->device_id,
                    'user_id' => null,
                    'filename' => $destination_file,
                    'tftp_server' => $tftpServer,
                    'status' => 'error',
                    'message' => "Daily automated backup failed with exception: " . $e->getMessage(),
                ]);
            } catch (\Exception $dbEx) {
                $this->warn("Could not log automated backup exception to DB: " . $dbEx->getMessage());
            }
        }
    }

    // Cleanup old backups based on retention settings
    $retentionDays = 30;
    try {
        $retentionDays = (int)\DB::table('config')->where('config_name', 'backup_retention_days')->value('config_value') ?: 30;
    } catch (\Exception $e) {
        // Fallback to default
    }

    $this->info("Cleaning up backups older than {$retentionDays} days...");
    $thresholdTime = time() - ($retentionDays * 24 * 60 * 60);

    // 1. Delete files in /tftpboot older than threshold
    $tftpPath = '/tftpboot';
    if (file_exists($tftpPath) && is_dir($tftpPath)) {
        $files = scandir($tftpPath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filePath = "{$tftpPath}/{$file}";
            if (is_file($filePath) && str_contains($file, 'startup-config')) {
                if (filemtime($filePath) < $thresholdTime) {
                    unlink($filePath);
                    $this->info("Deleted old local backup file: {$file}");
                }
            }
        }
    }

    // 2. Delete database log entries older than threshold
    try {
        $thresholdDate = date('Y-m-d H:i:s', $thresholdTime);
        $deletedLogsCount = \App\Models\ConfigBackupLog::where('created_at', '<', $thresholdDate)->delete();
        $this->info("Deleted {$deletedLogsCount} old backup log records from database.");
    } catch (\Exception $e) {
        $this->error("Failed to delete old log records: " . $e->getMessage());
    }
})->purpose('Backup startup-configs daily for all active network devices');

$backupTime = '01:30';
$tftpServer = '192.168.200.128';
$dbBackupTime = '02:00';
try {
    $backupTime = \DB::table('config')->where('config_name', 'backup_time')->value('config_value') ?: '01:30';
    $tftpServer = \DB::table('config')->where('config_name', 'tftp_server_ip')->value('config_value') ?: '192.168.200.128';
    $dbBackupTime = \DB::table('config')->where('config_name', 'db_backup_time')->value('config_value') ?: '02:00';
} catch (\Exception $e) {
    // Fallback to default if DB is not reachable during bootstrap (e.g. CLI operations)
}

Schedule::command('backup:startup-configs')
    ->dailyAt($backupTime)
    ->onOneServer()
    ->appendOutputTo(config('log_dir', storage_path('logs')) . '/startup_backups.log');

Artisan::command('backup:database', function () {
    /** @var Illuminate\Console\Command $this */
    $destination = 'local';
    $retentionDays = 30;

    try {
        $destination = \DB::table('config')->where('config_name', 'db_backup_destination')->value('config_value') ?: 'local';
        $retentionDays = (int)\DB::table('config')->where('config_name', 'db_backup_retention_days')->value('config_value') ?: 30;
    } catch (\Exception $e) {
        $this->error("Failed to read DB backup config: " . $e->getMessage());
    }

    $this->info("Starting daily automated database backup to destination: {$destination}");

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
        \App\Models\BackupLog::create([
            'user_id' => null, // null means automated/system
            'action' => 'create',
            'filename' => $filename,
            'destination' => $destination,
            'status' => $status,
            'message' => ($status === 'error') ? $output : 'Daily automated backup completed successfully.',
        ]);
    } catch (\Exception $e) {
        $this->warn("Could not log automated backup: " . $e->getMessage());
    }

    if ($exitCode === 0) {
        $this->info("Daily automated backup completed successfully.");
    } else {
        $this->error("Daily automated backup failed: " . $output);
    }

    // Retention / Cleanup
    $this->info("Cleaning up database backups older than {$retentionDays} days...");
    $thresholdTime = time() - ($retentionDays * 24 * 60 * 60);
    $basePath = '';

    switch ($destination) {
        case 'external':
            $basePath = '/mnt/external';
            break;
        case 'network':
            $basePath = '/mnt/network';
            break;
        case 'local':
        default:
            $basePath = storage_path('app/backups');
            break;
    }

    if (file_exists($basePath) && is_dir($basePath)) {
        $files = scandir($basePath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filePath = "{$basePath}/{$file}";
            if (is_file($filePath) && str_starts_with($file, 'backup_') && str_ends_with($file, '.sql')) {
                if (filemtime($filePath) < $thresholdTime) {
                    unlink($filePath);
                    $this->info("Deleted old database backup file: {$file}");
                }
            }
        }
    }
})->purpose('Backup the database daily and cleanup old backup files');

Schedule::command('backup:database')
    ->dailyAt($dbBackupTime)
    ->onOneServer()
    ->appendOutputTo(config('log_dir', storage_path('logs')) . '/db_backups.log');
