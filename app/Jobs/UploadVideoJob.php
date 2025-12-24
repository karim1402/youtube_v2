<?php

namespace App\Jobs;

use App\Helpers\GeminiHelper;
use Google\Client;
use Google\Service\YouTube;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Models\access_token;
use Illuminate\Support\Facades\Http;


class UploadVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $videoPath;
    private $title;
    private $description;

    private $client;
    public $timeout = 36000;

    /**
     * Create a new job instance.
     *
     * @param string $videoPath
     * @param string $title
     * @param string $description
     */
    public function __construct() {}
    /**
     * Execute the job.
     */
    public function handle()
    {
        //log info time useing the egypt timezone
        date_default_timezone_set('Africa/Cairo');
        Log::info('UploadVideoJob started at ' . date('Y-m-d H:i:s'));
        $this->full_video_fast();
        $this->mergeTwoAudioFiles();
        $final_video_with_audio = $this->mergeFinalVideoWithAudio();
        $this->compressFinalVideoWithAudio(150);
        $this->copyVideoMultipleTimes(120);
        $final_repeated_video = $this->mergeSameVideoMultipleTimes();
    }

    public function mergeWithGreenScreen1($background, $greenScreen, $output)
    {

        // $greenScreen =   storage_path('app/final_compress/green_screen.mp4');
        // $background =   storage_path('app/final_compress/background.mp4');
        // $output =   storage_path('app/final_compress/output.mp4');

        if (!file_exists($greenScreen)) {
            Log::error("الفيديو غير موجود: $greenScreen");
        }
        if (!file_exists($background)) {
            Log::error("الفيديو غير موجود: $background");
        }

        $filter = "[0:v]chromakey=0x00FF00:0.2:0.1[ckout];[1:v][ckout]overlay[out]";
        $cmd = "ffmpeg -i " . escapeshellarg($greenScreen)
            . " -i " . escapeshellarg($background)
            . " -filter_complex " . escapeshellarg($filter)
            . " -map [out] -c:v libx264 -crf 18 -preset slow -y " . escapeshellarg($output) . " 2>&1";

        $outputLog = shell_exec($cmd);

        return $outputLog;
    }

    public function full_video_fast()
    {

        $back = rand(1, 11);
        $effict_number = rand(1, 8);
        $sound_bar_number = rand(1, 8);
        $baby_number = rand(1, 6);
        $background = storage_path("app/backgrounds/$back.mp4");
        $effect = storage_path("app/effects/$effict_number.mp4");
        $soundbar = storage_path("app/soundbars/$sound_bar_number.mp4");
        $baby = storage_path("app/baby_greenscreen/$baby_number.mp4");
        $sleep = storage_path('app/sleep_effects/1.mp4');

        $output = storage_path('app/finals/final_video.mp4');

        // Check files exist
        foreach ([$background, $effect, $soundbar, $baby, $sleep] as $file) {
            if (!file_exists($file)) {
                FacadesLog::error("الفيديو غير موجود: $file");
            }
        }

        // Build filter_complex
        $filter = "[1:v]chromakey=0x00FF00:0.2:0.1[eff];"
            . "[0:v][eff]overlay[bg_eff];"
            . "[2:v]chromakey=0x00FF00:0.2:0.1[sb];"
            . "[bg_eff][sb]overlay[sb_eff];"
            . "[3:v]chromakey=0x00FF00:0.2:0.1[baby];"
            . "[sb_eff][baby]overlay[baby_eff];"
            . "[4:v]chromakey=0x00FF00:0.2:0.1[sleep];"
            . "[baby_eff][sleep]overlay[out]";

        $cmd = "ffmpeg -i " . escapeshellarg($background)
            . " -i " . escapeshellarg($effect)
            . " -i " . escapeshellarg($soundbar)
            . " -i " . escapeshellarg($baby)
            . " -i " . escapeshellarg($sleep)
            . " -filter_complex " . escapeshellarg($filter)
            . " -map [out] -c:v libx264 -crf 18 -preset slow -y " . escapeshellarg($output) . " 2>&1";

        $outputLog = shell_exec($cmd);

        return response()->json(['message' => 'تم إنشاء الفيديو بنجاح', 'video_path' => $output, 'ffmpeg_log' => $outputLog]);
    }


    public function mergeTwoAudioFiles()
    {
        $audioDir = storage_path('app/audio');
        $outputDir = storage_path('app/finals');
        $random = rand(1, 6);
        $random2 = rand(1, 6);
        while ($random2 == $random) {
            $random2 = rand(1, 6);
        }

        $audio1 = $audioDir . "/$random.mp3";
        $audio2 = $audioDir . "/$random2.mp3";
        $outputPath = $outputDir . '/merged_audio.mp3';

        // Ensure output directory exists
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        if (!file_exists($audio1)) {
            return response()->json(['success' => false, 'error' => 'First audio file not found']);
        }
        if (!file_exists($audio2)) {
            return response()->json(['success' => false, 'error' => 'Second audio file not found']);
        }

        // FFmpeg command to mix two audio files together
        $ffmpegCmd = "ffmpeg -y -i \"$audio1\" -i \"$audio2\" -filter_complex \"[0:0][1:0]amix=inputs=2:duration=longest:dropout_transition=2\" -c:a libmp3lame \"$outputPath\" 2>&1";
        exec($ffmpegCmd, $output, $resultCode);

        return response()->json([
            'success' => $resultCode === 0,
            'output' => $outputPath,
            'ffmpeg_output' => implode("\n", $output)
        ]);
    }

    public function mergeFinalVideoWithAudio()
    {
        $videoPath = storage_path('app/finals/final_video.mp4');
        $audioPath = storage_path('app/finals/merged_audio.mp3');
        $outputPath = storage_path('app/finals/final_video_with_audio.mp4');

        if (!file_exists($videoPath)) {
            return response()->json(['success' => false, 'error' => 'Video file not found']);
        }
        if (!file_exists($audioPath)) {
            return response()->json(['success' => false, 'error' => 'Audio file not found']);
        }

        // FFmpeg command to merge audio and video
        $cmd = "ffmpeg -y -i " . escapeshellarg($videoPath)
            . " -i " . escapeshellarg($audioPath)
            . " -c:v copy -c:a aac -shortest " . escapeshellarg($outputPath) . " 2>&1";

        exec($cmd, $output, $resultCode);
        //copy the final video to $outputPath folder 50 times



        return response()->json([
            'success' => $resultCode === 0,
            'output' => $outputPath,
            'ffmpeg_output' => implode("\n", $output)
        ]);
    }

    public function copyVideoMultipleTimes($count = 120)
    {
        $outputPath = storage_path('app/finals/final_video_with_audio.mp4');
        $videoPath = storage_path('app/copys');

        if (!file_exists($outputPath)) {
            return false;
        }

        if (!file_exists($videoPath)) {
            mkdir($videoPath, 0777, true);
        }

        for ($i = 1; $i <= $count; $i++) {
            $copyPath = $videoPath . '/final_video_with_audio_' . $i . '.mp4';
            copy($outputPath, $copyPath);
        }

        return true;
    }

    public function mergeSameVideoMultipleTimes()
    {

        $videoPath =  storage_path('app/copys');
        $outputPath = storage_path('app/outputs/finaloutpt123.mp4');
        $videos = collect(File::files($videoPath))
            ->filter(function ($file) {
                return in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'avi']);
            })
            ->shuffle()
            ->take(120)
            ->values();

        $listFile = storage_path('app/videos_repeat.txt');
        $fileListContent = '';

        foreach ($videos as $video) {
            $fileListContent .= "file '" . $video->getPathname() . "'\n";
        }
        file_put_contents($listFile, $fileListContent);

        $ffmpegCmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c copy " . escapeshellarg($outputPath) . " -y";

        exec($ffmpegCmd, $output, $returnVar);


        //        $repeatCount = 12;
        //        $videoPath =  storage_path('app/outputs/final_video_with_audio.mp4');
        //        if (!file_exists($videoPath)) {
        //            return false;
        //        }
        //        $outputPath = storage_path('app/outputs/merged_repeated10.mp4');
        //
        //        $fileListContent = '';
        //        for ($i = 0; $i < $repeatCount; $i++) {
        //            $fileListContent .= "file '" . $videoPath . "'\n";
        //        }
        //        file_put_contents($listFile, $fileListContent);
        //
        //        // Use re-encoding for compatibility
        //        $ffmpegCmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile)
        //            . " -c:v libx264 -c:a aac -b:a 128k -preset fast -y " . escapeshellarg($outputPath);
        //
        //        exec($ffmpegCmd, $output, $returnVar);
        //
        //        return $returnVar === 0 ? $outputPath : false;
    }

    public function compressFinalVideoWithAudio($targetSizeMB = 150)
    {


    //     $inputPath = storage_path('app/finals/final_video_with_audio.mp4');
    // $outputPath = storage_path('app/finals/final_video_with_audio_compressed.mp4');

    // if (!file_exists($inputPath)) {
    //     return response()->json(['success' => false, 'error' => 'Input video not found']);
    // }

    // $inputSizeMB = filesize($inputPath) / (1024 * 1024);
    // if ($inputSizeMB <= $targetSizeMB) {
    //     // Already small enough, just copy
    //     copy($inputPath, $outputPath);
    //     return response()->json([
    //         'success' => true,
    //         'output' => $outputPath,
    //         'note' => 'Input already under target size, copied without compression.'
    //     ]);
    // }

    // // Use CRF for more reliable compression
    // $crf = 30; // Increase for smaller size, decrease for better quality
    // $cmd = "ffmpeg -y -i " . escapeshellarg($inputPath)
    //     . " -c:v libx264 -crf {$crf} -preset veryfast -c:a aac -b:a 96k "
    //     . escapeshellarg($outputPath) . " 2>&1";

    // exec($cmd, $output, $resultCode);

    // $outputSizeMB = file_exists($outputPath) ? filesize($outputPath) / (1024 * 1024) : 0;

    // return response()->json([
    //     'success' => $resultCode === 0 && $outputSizeMB <= $targetSizeMB,
    //     'output' => $outputPath,
    //     'output_size_mb' => round($outputSizeMB, 2),
    //     'ffmpeg_output' => implode("\n", $output)
    // ]);





        $inputPath = storage_path('app/finals/final_video_with_audio.mp4');
        $outputPath = storage_path('app/finals/final_video_with_audio_compressed.mp4');

        if (!file_exists($inputPath)) {
            return response()->json(['success' => false, 'error' => 'Input video not found']);
        }

        // Estimate bitrate for target size (in bits per second)
        $durationCmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($inputPath);
        $duration = floatval(trim(shell_exec($durationCmd)));
        if ($duration <= 0) $duration = 60; // fallback

        $targetSizeBytes = $targetSizeMB * 1024 * 1024;
        $bitrate = intval(($targetSizeBytes * 8) / $duration); // in bits/sec

        // FFmpeg command to compress video
        $cmd = "ffmpeg -y -i " . escapeshellarg($inputPath)
            . " -b:v {$bitrate} -maxrate {$bitrate} -bufsize " . intval($bitrate / 2)
            . " -c:v libx264 -c:a aac -preset fast "
            . escapeshellarg($outputPath) . " 2>&1";

        exec($cmd, $output, $resultCode);

        return response()->json([
            'success' => $resultCode === 0,
            'output' => $outputPath,
            'ffmpeg_output' => implode("\n", $output)
        ]);
    }
    
}
