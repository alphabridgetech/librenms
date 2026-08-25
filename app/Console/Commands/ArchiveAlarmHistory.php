<?php

namespace App\Console\Commands;

use App\Models\AlarmArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ArchiveAlarmHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alarm:archive {--lines=} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive alert history log entries into downloadable CSV files based on line count and size thresholds.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting Alarm History Archive operation...");

        // Load configuration thresholds or set defaults
        $maxLinesConfig = (int) (DB::table('config')->where('config_name', 'alarm_archive_max_lines')->value('config_value') ?: 5000);
        $maxLines = $this->option('lines') ? (int) $this->option('lines') : $maxLinesConfig;
        $maxSizeMb = (float) (DB::table('config')->where('config_name', 'alarm_archive_max_size_mb')->value('config_value') ?: 10);
        $maxSizeBytes = $maxSizeMb * 1024 * 1024;
        $purgeDays = (int) (DB::table('config')->where('config_name', 'alarm_archive_purge_days')->value('config_value') ?: 90);

        $archiveDir = '/tftpboot/alarms';
        if (!File::exists($archiveDir)) {
            File::makeDirectory($archiveDir, 0777, true);
        }

        // Fetch logs from alert_log table
        $query = DB::table('alert_log as E')
            ->leftJoin('devices as D', 'E.device_id', '=', 'D.device_id')
            ->leftJoin('alert_rules as R', 'E.rule_id', '=', 'R.id')
            ->select(
                'E.id',
                'E.time_logged',
                'D.hostname',
                'E.state',
                'R.severity',
                'R.name as alert_name',
                'E.details'
            )
            ->orderBy('E.id', 'asc');

        $totalLogs = $query->count();
        if ($totalLogs === 0) {
            $this->info("No alert history logs found to archive.");
            return 0;
        }

        $this->info("Processing {$totalLogs} total alert history entries (Max lines per file: {$maxLines}, Max size: {$maxSizeMb} MB)...");

        $chunkCount = 0;
        $currentLines = 0;
        $currentBytes = 0;
        $fileHandle = null;
        $currentFilename = '';
        $currentFilePath = '';
        $firstTimestamp = null;
        $lastTimestamp = null;

        $stateMap = [
            0 => 'Ok (recovered)',
            1 => 'Alert',
            3 => 'Worse',
            4 => 'Better',
            5 => 'Changed',
        ];

        $closeCurrentFile = function() use (&$fileHandle, &$currentFilename, &$currentFilePath, &$currentLines, &$firstTimestamp, &$lastTimestamp) {
            if ($fileHandle) {
                fclose($fileHandle);
                $fileHandle = null;

                if (File::exists($currentFilePath)) {
                    $bytes = File::size($currentFilePath);
                    $sizeFormatted = number_format($bytes / 1024, 2) . ' KB';
                    if ($bytes >= 1048576) {
                        $sizeFormatted = number_format($bytes / 1048576, 2) . ' MB';
                    }

                    AlarmArchive::create([
                        'filename' => $currentFilename,
                        'file_path' => $currentFilePath,
                        'file_size' => $sizeFormatted,
                        'line_count' => $currentLines,
                        'start_date' => $firstTimestamp,
                        'end_date' => $lastTimestamp,
                    ]);
                }
            }
        };

        $openNewFile = function($timestampSample) use ($archiveDir, &$fileHandle, &$currentFilename, &$currentFilePath, &$currentLines, &$currentBytes, &$firstTimestamp, &$lastTimestamp, &$chunkCount) {
            $chunkCount++;
            $dateStr = date('Ymd_His');
            $currentFilename = "alarm_history_{$dateStr}_part{$chunkCount}.csv";
            $currentFilePath = "{$archiveDir}/{$currentFilename}";

            $fileHandle = fopen($currentFilePath, 'w');
            // Write CSV Header
            fputcsv($fileHandle, ['ID', 'Timestamp', 'Device', 'Alert Name', 'State', 'Severity', 'Details']);
            
            $currentLines = 0;
            $currentBytes = filesize($currentFilePath);
            $firstTimestamp = $timestampSample;
            $lastTimestamp = $timestampSample;
        };

        $query->chunk(1000, function ($logs) use (
            $maxLines,
            $maxSizeBytes,
            $stateMap,
            &$fileHandle,
            &$currentFilename,
            &$currentFilePath,
            &$currentLines,
            &$currentBytes,
            &$firstTimestamp,
            &$lastTimestamp,
            $closeCurrentFile,
            $openNewFile
        ) {
            foreach ($logs as $log) {
                if (!$fileHandle) {
                    $openNewFile($log->time_logged);
                }

                $stateStr = $stateMap[$log->state] ?? "State ({$log->state})";
                $severityStr = $log->severity ?: 'N/A';

                $detailsRaw = $log->details;
                $detailsClean = '';
                if (!empty($detailsRaw)) {
                    if (is_string($detailsRaw) && @gzuncompress($detailsRaw) !== false) {
                        $uncompressed = @gzuncompress($detailsRaw);
                        $json = json_decode($uncompressed, true);
                        if (is_array($json)) {
                            $detailsClean = json_encode($json);
                        } else {
                            $detailsClean = $uncompressed;
                        }
                    } else {
                        $detailsClean = (string) $detailsRaw;
                    }
                }
                $detailsClean = trim(strip_tags($detailsClean));

                $csvRow = [
                    $log->id,
                    $log->time_logged,
                    $log->hostname ?: 'Unknown Device',
                    $log->alert_name ?: 'Unnamed Alert',
                    $stateStr,
                    $severityStr,
                    $detailsClean
                ];

                fputcsv($fileHandle, $csvRow);
                $currentLines++;
                $lastTimestamp = $log->time_logged;

                if (File::exists($currentFilePath)) {
                    $currentBytes = File::size($currentFilePath);
                }
                if ($currentLines >= $maxLines || $currentBytes >= $maxSizeBytes) {
                    $closeCurrentFile();
                }
            }
        });

        if ($fileHandle) {
            $closeCurrentFile();
        }

        // Purge old archives if purge days configured
        if ($purgeDays > 0) {
            $cutoff = now()->subDays($purgeDays);
            $oldArchives = AlarmArchive::where('created_at', '<', $cutoff)->get();
            foreach ($oldArchives as $archive) {
                if (File::exists($archive->file_path)) {
                    File::delete($archive->file_path);
                }
                $archive->delete();
            }
            if ($oldArchives->count() > 0) {
                $this->info("Purged {$oldArchives->count()} historical archive file(s) older than {$purgeDays} days.");
            }
        }

        DB::table('config')->updateOrInsert(
            ['config_name' => 'alarm_archive_last_run'],
            ['config_value' => now()->toDateTimeString()]
        );

        $this->info("Alarm History Archive process completed successfully.");
        return 0;
    }
}
