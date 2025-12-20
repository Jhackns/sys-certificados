<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CleanTempCertificates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean old temporary certificate images from storage/app/public/temp';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting certificate cleanup...');
        
        $disk = Storage::disk('public');
        $tempPath = 'temp';

        if (!$disk->exists($tempPath)) {
            $this->info('Temp directory does not exist. Nothing to clean.');
            return;
        }

        $files = $disk->files($tempPath);
        $count = 0;
        $deletedSize = 0;
        
        // 1. Delete files older than 24 hours
        $threshold = now()->subHours(24)->getTimestamp();

        foreach ($files as $file) {
            // Check if it's an image (basic check by extension)
            if (!preg_match('/\.(png|jpg|jpeg|pdf)$/i', $file)) {
                continue;
            }

            $lastModified = $disk->lastModified($file);

            if ($lastModified < $threshold) {
                $size = $disk->size($file);
                $disk->delete($file);
                $count++;
                $deletedSize += $size;
                $this->line("Deleted: $file");
            }
        }

        $this->info("Deleted $count old files (" . round($deletedSize / 1024 / 1024, 2) . " MB).");
        Log::info("Certificates cleanup: Deleted $count old files.", ['deleted_size_mb' => round($deletedSize / 1024 / 1024, 2)]);

        // 2. Check total size limit (500MB)
        $limitMB = 500;
        $files = $disk->files($tempPath); // Re-scan
        $totalSize = 0;
        $fileData = [];

        foreach ($files as $file) {
             if (!preg_match('/\.(png|jpg|jpeg|pdf)$/i', $file)) {
                continue;
            }
            $size = $disk->size($file);
            $totalSize += $size;
            $fileData[] = [
                'path' => $file,
                'time' => $disk->lastModified($file),
                'size' => $size
            ];
        }

        $totalSizeMB = $totalSize / 1024 / 1024;
        $this->info("Current temp folder size: " . round($totalSizeMB, 2) . " MB");

        if ($totalSizeMB > $limitMB) {
            $this->info("Size limit ($limitMB MB) exceeded. Deleting oldest files...");
            
            // Sort by time ascending (oldest first)
            usort($fileData, function ($a, $b) {
                return $a['time'] <=> $b['time'];
            });

            $cleanedCount = 0;
            $cleanedSize = 0;

            foreach ($fileData as $file) {
                if ($totalSizeMB <= $limitMB) {
                    break;
                }

                $disk->delete($file['path']);
                $cleanedCount++;
                $cleanedSize += $file['size'];
                $totalSizeMB -= ($file['size'] / 1024 / 1024);
                $this->line("Deleted (Size Limit): " . $file['path']);
            }
             $this->info("Size cleanup: Deleted $cleanedCount files (" . round($cleanedSize / 1024 / 1024, 2) . " MB).");
             Log::info("Certificates size cleanup: Deleted $cleanedCount files.", ['cleaned_size_mb' => round($cleanedSize / 1024 / 1024, 2)]);
        }

        $this->info('Cleanup completed.');
    }
}
