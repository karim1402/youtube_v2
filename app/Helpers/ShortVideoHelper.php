<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * ShortVideoHelper - Creates short videos (5-30 minutes) using 1-minute material files
 * Similar to GeminiHelper but optimized for shorter YouTube Shorts/videos
 */
class ShortVideoHelper
{
    private static $targetMinutes;
    
    /**
     * Initialize target duration (5-30 minutes randomly)
     */
    private static function initTargetMinutes()
    {
        if (self::$targetMinutes === null) {
            self::$targetMinutes = rand(5, 30);
        }
        return self::$targetMinutes;
    }

    /**
     * Reset target minutes for new video generation
     */
    public static function resetTargetMinutes()
    {
        self::$targetMinutes = null;
    }

    /**
     * Get the current target minutes
     */
    public static function getTargetMinutes()
    {
        return self::initTargetMinutes();
    }

    /**
     * Main entry point - runs the complete short video creation pipeline
     */
    public static function runShortVideo()
    {
        // Reset for fresh random duration
        self::resetTargetMinutes();
        $targetMinutes = self::initTargetMinutes();
        
        // Clean up previous output
        $finalVideoPath = storage_path('app/outputs/short_video_final.mp4');
        if (file_exists($finalVideoPath)) {
            unlink($finalVideoPath);
        }

        Log::info("Starting short video creation - Target duration: {$targetMinutes} minutes");
        
        date_default_timezone_set('Africa/Cairo');
        Log::info('ShortVideoHelper started at ' . date('Y-m-d H:i:s'));
        
        // Generate audio for target duration
        self::mixAudioForDuration($targetMinutes * 60);
        Log::info("Audio generated for {$targetMinutes} minutes");
        
        // Create video with 1-minute clips
        self::createShortVideoFast();
        Log::info("Short video created successfully.");
        
        // Merge video with audio
        self::mergeShortVideoWithAudio();
        Log::info("Short video merged with audio successfully.");
        
        // Compress the final video
        self::compressShortVideo(50); // 50MB target for short videos
        Log::info("Short video compressed successfully.");
        
        // Copy and merge to target duration
        self::copyAndMergeToTargetDuration();
        Log::info("Short video merged to target duration successfully.");
        
        return $targetMinutes;
    }

    /**
     * Create overlay thumbnail image for short video
     */
    public static function overlayImages()
    {
        $back = rand(1, 35);
        $baby = rand(1, 33);

        $baseImagePath = storage_path("app/background/$back.png");
        $overlayImagePath = storage_path("app/baby/$baby.png");
        $cornerImagePath = storage_path("app/logo/file.png");

        $baseImageOriginal = imagecreatefromstring(file_get_contents($baseImagePath));
        $overlayImageOriginal = imagecreatefromstring(file_get_contents($overlayImagePath));
        $cornerImage = imagecreatefromstring(file_get_contents($cornerImagePath));

        $baseWidth = imagesx($baseImageOriginal);
        $baseHeight = imagesy($baseImageOriginal);
        $overlayWidth = imagesx($overlayImageOriginal);
        $overlayHeight = imagesy($overlayImageOriginal);

        // Resize overlay to 70%
        $newOverlayWidth = (int)($overlayWidth * 0.70);
        $newOverlayHeight = (int)($overlayHeight * 0.70);
        $overlayImage = imagecreatetruecolor($newOverlayWidth, $newOverlayHeight);
        imagealphablending($overlayImage, false);
        imagesavealpha($overlayImage, true);
        imagecopyresampled($overlayImage, $overlayImageOriginal, 0, 0, 0, 0, $newOverlayWidth, $newOverlayHeight, $overlayWidth, $overlayHeight);

        $baseImage = imagecreatetruecolor($baseWidth, $baseHeight);
        imagecopy($baseImage, $baseImageOriginal, 0, 0, 0, 0, $baseWidth, $baseHeight);

        // Center overlay
        $posX = ($baseWidth - $newOverlayWidth) / 2;
        $posY = ($baseHeight - $newOverlayHeight) / 2;
        imagecopy($baseImage, $overlayImage, $posX, $posY, 0, 0, $newOverlayWidth, $newOverlayHeight);

        // Logo in top-right
        $cornerWidth = $baseWidth * 0.12;
        $cornerHeight = $cornerWidth * (imagesy($cornerImage) / imagesx($cornerImage));
        $cornerResized = imagecreatetruecolor($cornerWidth, $cornerHeight);
        imagealphablending($cornerResized, false);
        imagesavealpha($cornerResized, true);
        imagecopyresampled($cornerResized, $cornerImage, 0, 0, 0, 0, $cornerWidth, $cornerHeight, imagesx($cornerImage), imagesy($cornerImage));

        $marginRight = $baseWidth * 0.02;
        $marginTop = $baseHeight * 0.02;
        $positionX = $baseWidth - $cornerWidth - $marginRight;
        $positionY = $marginTop;
        imagecopy($baseImage, $cornerResized, $positionX, $positionY, 0, 0, $cornerWidth, $cornerHeight);

        $outputPath = storage_path('app/public/merged_image_short.png');
        imagepng($baseImage, $outputPath);

        imagedestroy($baseImageOriginal);
        imagedestroy($baseImage);
        imagedestroy($overlayImageOriginal);
        imagedestroy($overlayImage);
        imagedestroy($cornerImage);
        imagedestroy($cornerResized);
    }

    /**
     * Create short video using 1-minute material clips with green screen overlay
     */
    public static function createShortVideoFast()
    {
        $back = rand(1, 11);
        $effectNumber = rand(1, 8);
        $soundBarNumber = rand(1, 8);
        $babyNumber = rand(1, 6);

        // Use 1-minute versions of all assets
        $background = storage_path("app/backgrounds/{$back}_1min.mp4");
        $effect = storage_path("app/effects/{$effectNumber}_1min.mp4");
        $soundbar = storage_path("app/soundbars/{$soundBarNumber}_1min.mp4");
        $baby = storage_path("app/baby_greenscreen/{$babyNumber}_1min.mp4");
        $sleep = storage_path('app/sleep_effects/1_1min.mp4');

        $output = storage_path('app/finals/short_video.mp4');

        // Verify all files exist
        foreach ([$background, $effect, $soundbar, $baby, $sleep] as $file) {
            if (!file_exists($file)) {
                Log::error("Video file not found: $file");
                return false;
            }
        }

        // Build filter_complex for chromakey overlay
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

        Log::info("Short video created: $output");
        return true;
    }

    /**
     * Generate and mix audio for specific duration
     */
    public static function mixAudioForDuration(int $durationSeconds = 300)
    {
        self::generateWhiteNoiseForDuration($durationSeconds);
        self::generatePinkNoiseForDuration($durationSeconds);
        self::generateBrownNoiseForDuration($durationSeconds);

        $whiteNoisePath = storage_path('app/white_audio/white_short.mp3');
        $pinkNoisePath = storage_path('app/white_audio/pink_short.mp3');
        $brownNoisePath = storage_path('app/white_audio/brown_short.mp3');

        $command = [
            'ffmpeg',
            '-y',
            '-i', $pinkNoisePath,
            '-i', $brownNoisePath,
            '-filter_complex',
            '[0:a][1:a]amix=inputs=2:duration=longest[a]',
            '-map', '[a]',
            '-c:a', 'libmp3lame',
            '-q:a', '2',
            '-ar', '44100',
            storage_path('app/white_audio/mixed_short.mp3'),
        ];

        $process = new Process($command);
        $process->setTimeout(600);
        $process->run();

        // Cleanup individual noise files
        if (file_exists($whiteNoisePath)) unlink($whiteNoisePath);
        if (file_exists($pinkNoisePath)) unlink($pinkNoisePath);
        if (file_exists($brownNoisePath)) unlink($brownNoisePath);

        return ['status' => 'success'];
    }

    /**
     * Merge short video with generated audio
     */
    public static function mergeShortVideoWithAudio()
    {
        $videoPath = storage_path('app/finals/short_video.mp4');
        $audioPath = storage_path('app/white_audio/mixed_short.mp3');
        $outputPath = storage_path('app/finals/short_video_with_audio.mp4');

        if (!file_exists($videoPath)) {
            Log::error('Short video file not found');
            return false;
        }
        if (!file_exists($audioPath)) {
            Log::error('Short audio file not found');
            return false;
        }

        $cmd = "ffmpeg -y -i " . escapeshellarg($videoPath)
            . " -i " . escapeshellarg($audioPath)
            . " -c:v copy -c:a aac -shortest " . escapeshellarg($outputPath) . " 2>&1";

        exec($cmd, $output, $resultCode);

        // Cleanup intermediate files
        if (file_exists($outputPath)) {
            unlink($videoPath);
            unlink($audioPath);
        }

        return $resultCode === 0;
    }

    /**
     * Compress short video to target size
     */
    public static function compressShortVideo($targetSizeMB = 50)
    {
        $inputPath = storage_path('app/finals/short_video_with_audio.mp4');
        $outputPath = storage_path('app/finals/short_video_compressed.mp4');

        if (!file_exists($inputPath)) {
            Log::error('Input video not found for compression');
            return false;
        }

        // Get video duration
        $durationCmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($inputPath);
        $duration = floatval(trim(shell_exec($durationCmd)));
        if ($duration <= 0) $duration = 60;

        $targetSizeBytes = $targetSizeMB * 1024 * 1024;
        $bitrate = intval(($targetSizeBytes * 8) / $duration);

        $cmd = "ffmpeg -y -i " . escapeshellarg($inputPath)
            . " -b:v {$bitrate} -maxrate {$bitrate} -bufsize " . intval($bitrate / 2)
            . " -c:v libx264 -c:a aac -preset fast "
            . escapeshellarg($outputPath) . " 2>&1";

        exec($cmd, $output, $resultCode);

        if (file_exists($outputPath)) {
            unlink($inputPath);
        }

        return $resultCode === 0;
    }

    /**
     * Copy 1-minute video multiple times and merge to target duration
     */
    public static function copyAndMergeToTargetDuration()
    {
        $copyCount = self::initTargetMinutes(); // 5-30 copies for 5-30 minutes
        $sourcePath = storage_path('app/finals/short_video_compressed.mp4');
        $copysPath = storage_path('app/copys_short');
        $outputPath = storage_path('app/outputs/short_video_final.mp4');

        if (!file_exists($sourcePath)) {
            Log::error('Compressed short video not found');
            return false;
        }

        // Create copies directory if not exists
        if (!file_exists($copysPath)) {
            mkdir($copysPath, 0777, true);
        }

        // Clear previous copies
        $existingFiles = File::files($copysPath);
        foreach ($existingFiles as $file) {
            File::delete($file);
        }

        // Create copies
        for ($i = 1; $i <= $copyCount; $i++) {
            $copyPath = $copysPath . '/short_video_' . $i . '.mp4';
            copy($sourcePath, $copyPath);
        }

        // Get videos and shuffle
        $videos = collect(File::files($copysPath))
            ->filter(function ($file) {
                return in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'avi']);
            })
            ->shuffle()
            ->take($copyCount)
            ->values();

        if ($videos->isEmpty()) {
            Log::error("No videos found in copys_short folder");
            return false;
        }

        // Create file list for concatenation
        $listFile = storage_path('app/short_videos_list.txt');
        $fileListContent = '';
        foreach ($videos as $video) {
            $fileListContent .= "file '" . $video->getPathname() . "'\n";
        }
        file_put_contents($listFile, $fileListContent);

        // Concatenate all copies
        $ffmpegCmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c copy " . escapeshellarg($outputPath) . " -y";
        exec($ffmpegCmd, $output, $returnVar);

        // Cleanup
        foreach (File::files($copysPath) as $file) {
            File::delete($file);
        }
        if (file_exists($sourcePath)) {
            unlink($sourcePath);
        }

        if ($returnVar === 0) {
            Log::info("Short video merged successfully - {$copyCount} minutes");
            return true;
        } else {
            Log::error("Failed to merge short video. FFmpeg output: " . implode("\n", $output));
            return false;
        }
    }

    /**
     * Generate white noise for specific duration
     */
    public static function generateWhiteNoiseForDuration(int $duration = 300, float $volume = 0.1): array
    {
        $directory = storage_path('app/white_audio');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        $filename = 'white_short.mp3';

        $volume = max(0.1, min(1.0, $volume));
        $seed = mt_rand(0, 999999);
        $bassBoost = mt_rand(0, 5);
        $trebleBoost = mt_rand(-3, 3);
        $midCut = mt_rand(-2, 2);
        $amplitudeVar = 0.95 + (mt_rand(0, 100) / 1000);

        $filePath = $directory . '/' . $filename;

        $audioFilters = [
            "volume={$volume}*{$amplitudeVar}",
            "equalizer=f=100:t=q:w=1:g={$bassBoost}",
            "equalizer=f=1000:t=q:w=1:g={$midCut}",
            "equalizer=f=8000:t=q:w=1:g={$trebleBoost}"
        ];

        $filterComplex = implode(',', $audioFilters);

        $command = [
            'ffmpeg',
            '-y',
            '-f', 'lavfi',
            '-i', "anoisesrc=color=white:duration={$duration}:sample_rate=44100:seed={$seed}",
            '-af', $filterComplex,
            '-c:a', 'libmp3lame',
            '-q:a', '2',
            '-ar', '44100',
            $filePath
        ];

        $process = new Process($command);
        $process->setTimeout($duration + 60);
        $process->run();

        if (!$process->isSuccessful()) {
            return ['status' => 'error', 'message' => 'Failed to generate white noise'];
        }

        return ['status' => 'success'];
    }

    /**
     * Generate pink noise for specific duration
     */
    public static function generatePinkNoiseForDuration(int $duration = 300, float $volume = 0.4): array
    {
        $directory = storage_path('app/white_audio');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        $filename = 'pink_short.mp3';

        $volume = max(0.1, min(1.0, $volume));
        $seed = mt_rand(0, 999999);
        $bassBoost = mt_rand(0, 5);
        $trebleBoost = mt_rand(-3, 3);
        $midCut = mt_rand(-2, 2);
        $amplitudeVar = 0.95 + (mt_rand(0, 100) / 1000);

        $filePath = $directory . '/' . $filename;

        $audioFilters = [
            "volume={$volume}*{$amplitudeVar}",
            "equalizer=f=100:t=q:w=1:g={$bassBoost}",
            "equalizer=f=1000:t=q:w=1:g={$midCut}",
            "equalizer=f=8000:t=q:w=1:g={$trebleBoost}"
        ];

        $filterComplex = implode(',', $audioFilters);

        $command = [
            'ffmpeg',
            '-y',
            '-f', 'lavfi',
            '-i', "anoisesrc=color=pink:duration={$duration}:sample_rate=44100:seed={$seed}",
            '-af', $filterComplex,
            '-c:a', 'libmp3lame',
            '-q:a', '2',
            '-ar', '44100',
            $filePath
        ];

        $process = new Process($command);
        $process->setTimeout($duration + 60);
        $process->run();

        if (!$process->isSuccessful()) {
            return ['status' => 'error', 'message' => 'Failed to generate pink noise'];
        }

        return ['status' => 'success'];
    }

    /**
     * Generate brown noise for specific duration
     */
    public static function generateBrownNoiseForDuration(int $duration = 300, float $volume = 0.4): array
    {
        $directory = storage_path('app/white_audio');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        $filename = 'brown_short.mp3';

        $volume = max(0.1, min(1.0, $volume));
        $seed = mt_rand(0, 999999);
        $bassBoost = mt_rand(0, 5);
        $trebleBoost = mt_rand(-3, 3);
        $midCut = mt_rand(-2, 2);
        $amplitudeVar = 0.95 + (mt_rand(0, 100) / 1000);

        $filePath = $directory . '/' . $filename;

        $audioFilters = [
            "volume={$volume}*{$amplitudeVar}",
            "equalizer=f=100:t=q:w=1:g={$bassBoost}",
            "equalizer=f=1000:t=q:w=1:g={$midCut}",
            "equalizer=f=8000:t=q:w=1:g={$trebleBoost}"
        ];

        $filterComplex = implode(',', $audioFilters);

        $command = [
            'ffmpeg',
            '-y',
            '-f', 'lavfi',
            '-i', "anoisesrc=color=brown:duration={$duration}:sample_rate=44100:seed={$seed}",
            '-af', $filterComplex,
            '-c:a', 'libmp3lame',
            '-q:a', '2',
            '-ar', '44100',
            $filePath
        ];

        $process = new Process($command);
        $process->setTimeout($duration + 60);
        $process->run();

        if (!$process->isSuccessful()) {
            return ['status' => 'error', 'message' => 'Failed to generate brown noise'];
        }

        return ['status' => 'success'];
    }

    /**
     * Call ChatGPT/OpenAI API for title/description generation
     */
    public static function generateText($text)
    {
        $apiKey = env('OPENAI_API_KEY');

        $url = "https://api.openai.com/v1/responses";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $apiKey",
        ])->post($url, [
            'model' => 'gpt-4.1',
            'input' => $text,
        ]);

        return $response->json()["output"][0]['content'][0]['text'];
    }
}
