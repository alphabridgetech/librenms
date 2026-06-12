<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DatabaseRestoreManual extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:restore-manual {filename : The name of the backup file in storage/app/backups}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore the database from a backup file';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $filename = $this->argument('filename');
        $backupPath = storage_path('app/backups/' . $filename);

        if (!File::exists($backupPath)) {
            $this->error("Backup file not found: {$backupPath}");
            return 1;
        }

        $this->info("Starting restoration from: {$filename}");

        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');

        // Using mysql client for restoration
        $command = "mysql --user='{$username}' --password='{$password}' --host='{$host}' '{$database}' < '{$backupPath}'";

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(1200); // 20 minutes for large restores
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Restoration failed: ' . $process->getErrorOutput());
            return 1;
        }

        $this->info("Database restored successfully from {$filename}");
        return 0;
    }
}
