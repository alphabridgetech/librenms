<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DatabaseBackupManual extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup-manual {--destination=local : The destination for the backup (local, external, network)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform a manual database backup to a selected destination';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $destination = $this->option('destination');
        $this->info("Starting manual backup to destination: {$destination}");

        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');

        $filename = "backup_" . now()->format('Y-m-d_H-i-s') . ".sql";
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

        // Using mysqldump. Note: We use shell redirection.
        // For security, we should ideally use a config file or env vars, 
        // but here we follow the user's specific request for a manual script run.
        $command = "mysqldump --user='{$username}' --password='{$password}' --host='{$host}' '{$database}' > '{$backupPath}'";

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(600); // 10 minutes timeout for large databases
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Backup failed: ' . $process->getErrorOutput());
            return 1;
        }

        $this->info("Backup completed successfully: {$backupPath}");
        return 0;
    }
}
