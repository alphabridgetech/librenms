<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class RrdRestoreManual extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rrd:restore-manual {filename : The name of the RRD backup tar.gz file in storage/app/backups/rrd}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore RRD files from a compressed tar.gz backup archive';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $filename = $this->argument('filename');

        // Prevent directory traversal
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            $this->error("Invalid filename specified.");
            return 1;
        }

        $backupPath = '/tftpboot/rrd/' . $filename;
        if (!File::exists($backupPath)) {
            $backupPath = storage_path('app/backups/rrd/' . $filename);
        }

        if (!File::exists($backupPath)) {
            $this->error("Backup file not found in /tftpboot/rrd or storage/app/backups/rrd: {$filename}");
            return 1;
        }

        // Resolve RRD Directory
        $rrdDir = \LibreNMS\Config::get('rrd_dir');
        if (empty($rrdDir)) {
            $rrdDir = base_path('rrd');
        }

        if (!File::exists($rrdDir)) {
            try {
                File::makeDirectory($rrdDir, 0777, true);
            } catch (\Exception $e) {
                $this->error("Could not create target RRD directory: {$rrdDir}");
                return 1;
            }
        }

        $this->info("Starting RRD restoration from {$filename} to {$rrdDir}...");

        // 1. Unlink existing .rrd files prior to extraction to prevent BusyBox tar open() permission errors
        $this->info("Preparing target directory for extraction...");
        $preCleanCmd = "find " . escapeshellarg($rrdDir) . " -type f -name '*.rrd' -exec rm -f {} + 2>/dev/null || true";
        $preCleanProcess = Process::fromShellCommandline($preCleanCmd);
        $preCleanProcess->run();

        // 2. Extract tar.gz file into rrdDir with BusyBox and GNU tar compatible flags (-m prevents utime/mtime errors)
        $command = "tar --overwrite -o -m --no-same-permissions -xzf " . escapeshellarg($backupPath) . " -C " . escapeshellarg($rrdDir);

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(1800); // 30 minutes for large restores
        $process->run();

        if (!$process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput();
            
            // Filter out non-fatal directory metadata warnings from BusyBox tar
            $lines = explode("\n", $errorOutput);
            $criticalErrors = array_filter($lines, function ($line) {
                $line = trim($line);
                if (empty($line)) return false;
                if (str_contains($line, 'Cannot change mode')) return false;
                if (str_contains($line, 'Cannot utime')) return false;
                if (str_contains($line, 'Exiting with failure status')) return false;
                return true;
            });

            if (!empty($criticalErrors)) {
                $this->error('RRD Restoration failed: ' . implode("\n", $criticalErrors));

                if (str_contains($errorOutput, 'Permission denied')) {
                    $this->warn("Note: Ensure the command is executed with write permissions for target directory: {$rrdDir} (e.g. as librenms user or root).");
                }
                return 1;
            }
        }

        // 3. Fix permissions on restored RRD files so both poller and web user can write metrics
        $fixPermCmd = "chmod -R 0777 " . escapeshellarg($rrdDir) . " 2>/dev/null || true";
        $fixPermProc = Process::fromShellCommandline($fixPermCmd);
        $fixPermProc->run();

        $this->info("RRD files restored successfully from {$filename}");
        return 0;
    }
}
