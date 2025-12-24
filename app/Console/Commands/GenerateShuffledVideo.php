<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateShuffledVideo extends Command
{
    protected $signature = 'video:generate';
    protected $description = 'Concatenate 10 videos from the clips folder into one video using FFmpeg';

    public function handle()
    {
        $clipsPath = storage_path('app/clips');
        $outputPath = storage_path('app/outputs/merged.mp4');

        // Get all video files in the clips directory (e.g., .mp4)
        $videos = collect(File::files($clipsPath))
            ->filter(function ($file) {
                return in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'avi']);
            })
            ->shuffle()
            ->take(10)
            ->values();

       
        // Create a temporary file list for FFmpeg
      $listFile = storage_path('app/clips/videos.txt');
$fileListContent = '';
foreach ($videos as $video) {
    $fileListContent .= "file '" . $video->getPathname() . "'\n";
}
file_put_contents($listFile, $fileListContent);

// Build FFmpeg command
$ffmpegCmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c copy " . escapeshellarg($outputPath) . " -y";


exec($ffmpegCmd, $output, $returnVar);

        if ($returnVar === 0) {
            $this->info('Videos merged successfully: ' . $outputPath);
        } else {
            $this->error('Failed to merge videos.');
        }
    }
}