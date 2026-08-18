<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class RrdBackupManual extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rrd:backup-manual 
        {--destination=local : The destination for the backup (local, external, network)} 
        {--retention= : Days of backups to retain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform a manual RRD files backup to a selected destination';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $destination = $this->option('destination');
        $this->info("Starting manual RRD backup to destination: {$destination}");

        // 1. Resolve retention days
        $retentionDays = $this->option('retention');
        if (empty($retentionDays)) {
            try {
                $retentionDays = (int)\DB::table('config')->where('config_name', 'rrd_backup_purge_days')->value('config_value');
            } catch (\Exception $e) {
                // Ignore DB error fallback
            }
            if (empty($retentionDays)) {
                $retentionDays = \LibreNMS\Config::get('rrd_backup_purge_days', 30);
            }
        }
        $retentionDays = max(1, (int)$retentionDays);

        // 2. Resolve RRD Directory
        $rrdDir = \LibreNMS\Config::get('rrd_dir');
        if (empty($rrdDir) || !File::isDirectory($rrdDir)) {
            $rrdDir = base_path('rrd');
        }

        if (!File::exists($rrdDir)) {
            $this->error("RRD directory does not exist: {$rrdDir}");
            return 1;
        }

        // 3. Flush rrdcached if configured
        $this->flushRrdCached();

        // 4. Resolve Base Target Path
        $filename = "rrd_backup_" . now()->format('Y-m-d_H-i-s') . ".tar.gz";
        $basePath = '';

        switch ($destination) {
            case 'external':
                $basePath = '/mnt/external/rrd';
                break;
            case 'network':
                $basePath = '/mnt/network/rrd';
                break;
            case 'local':
            default:
                $basePath = storage_path('app/backups/rrd');
                break;
        }

        if (!File::exists($basePath)) {
            try {
                File::makeDirectory($basePath, 0755, true);
            } catch (\Exception $e) {
                $this->error("Destination path does not exist and could not be created: {$basePath}");
                $this->error("Error: " . $e->getMessage());
                return 1;
            }
        }

        $backupPath = $basePath . DIRECTORY_SEPARATOR . $filename;
        $this->info("Target path: {$backupPath}");

        // 5. Create tar.gz archive
        $command = "tar -czf " . escapeshellarg($backupPath) . " -C " . escapeshellarg($rrdDir) . " .";

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(1800); // 30 minutes timeout for large RRD datasets
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('RRD Backup failed: ' . $process->getErrorOutput());
            return 1;
        }

        $this->info("RRD Backup completed successfully: {$backupPath}");

        // 6. Cleanup old RRD backup files based on retention policy
        $this->cleanupOldBackups($basePath, $retentionDays);

        return 0;
    }

    /**
     * Flush dirty buffers from rrdcached if running/configured.
     */
    protected function flushRrdCached()
    {
        $rrdcached = \LibreNMS\Config::get('rrdcached');
        if (empty($rrdcached)) {
            return;
        }

        $this->info("Flushing rrdcached buffers before archiving...");

        try {
            if (str_contains($rrdcached, '/')) {
                // Unix socket
                $cmd = "rrdtool flushall --daemon " . escapeshellarg($rrdcached);
                $process = Process::fromShellCommandline($cmd);
                $process->setTimeout(60);
                $process->run();
            } else {
                // Host:Port socket
                $cmd = "rrdtool flushall --daemon " . escapeshellarg($rrdcached);
                $process = Process::fromShellCommandline($cmd);
                $process->setTimeout(60);
                $process->run();
            }
        } catch (\Exception $e) {
            $this->warn("Failed to flush rrdcached: " . $e->getMessage());
        }
    }

    /**
     * Delete backups older than specified retention days.
     */
    protected function cleanupOldBackups(string $basePath, int $retentionDays)
    {
        $this->info("Cleaning up RRD backups older than {$retentionDays} days...");
        $thresholdTime = time() - ($retentionDays * 24 * 60 * 60);

        if (File::exists($basePath) && File::isDirectory($basePath)) {
            $files = File::files($basePath);
            foreach ($files as $file) {
                if (str_starts_with($file->getFilename(), 'rrd_backup_') && str_ends_with($file->getFilename(), '.tar.gz')) {
                    if ($file->getMTime() < $thresholdTime) {
                        try {
                            File::delete($file->getPathname());
                            $this->info("Deleted old RRD backup: " . $file->getFilename());
                        } catch (\Exception $e) {
                            $this->warn("Failed to delete file {$file->getFilename()}: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }
}
