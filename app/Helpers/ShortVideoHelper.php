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
     * Flow: Create base video -> Compress -> Concatenate -> Add Intro -> Generate Audio -> Merge Audio
     */
    public static function runShortVideo()
    {
        self::resetTargetMinutes();
        $targetMinutes = self::initTargetMinutes();
        
        // Cleanup
        $finalVideoPath = storage_path('app/outputs/short_video_final.mp4');
        if (file_exists($finalVideoPath)) unlink($finalVideoPath);

        Log::info("Starting short video creation - Target duration: {$targetMinutes} minutes");
        date_default_timezone_set('Africa/Cairo');

        // 1. Generate Audio (1 min)
        self::mixAudioForDuration(60);
        Log::info("Audio generated for 1 minute");

        // 2. Create Base Video (1 min)
        self::createShortVideoFast();
        Log::info("Base short video created");

        // 3. Merge Audio
        self::mergeShortVideoWithAudio();
        Log::info("Audio merged with base video");

        // 4. Compress
        self::compressShortVideo(50);
        Log::info("Base video compressed");

        // 5. Concat (with Intro)
        self::copyAndMergeToTargetDuration();
        Log::info("Video concatenated with intro");

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
        Log::info("Starting audio generation for {$durationSeconds} seconds");
        
        // Create directory if it doesn't exist
        $directory = storage_path('app/white_audio');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        self::generateWhiteNoiseForDuration($durationSeconds);
        Log::info("White noise generated");
        
        self::generatePinkNoiseForDuration($durationSeconds);
        Log::info("Pink noise generated");
        
        self::generateBrownNoiseForDuration($durationSeconds);
        Log::info("Brown noise generated");

        $whiteNoisePath = storage_path('app/white_audio/white_short.mp3');
        $pinkNoisePath = storage_path('app/white_audio/pink_short.mp3');
        $brownNoisePath = storage_path('app/white_audio/brown_short.mp3');
        $outputPath = storage_path('app/white_audio/mixed_short.mp3');

        // Check if noise files were created
        if (!file_exists($pinkNoisePath)) {
            Log::error("Pink noise file not created: {$pinkNoisePath}");
        }
        if (!file_exists($brownNoisePath)) {
            Log::error("Brown noise file not created: {$brownNoisePath}");
        }

        // Mix the audio files using shell_exec for reliability
        $cmd = "ffmpeg -y -i " . escapeshellarg($pinkNoisePath) 
            . " -i " . escapeshellarg($brownNoisePath)
            . " -filter_complex \"[0:a][1:a]amix=inputs=2:duration=longest[a]\""
            . " -map \"[a]\" -c:a libmp3lame -q:a 2 -ar 44100 "
            . escapeshellarg($outputPath) . " 2>&1";
        
        $result = shell_exec($cmd);
        Log::info("Mix audio result: " . substr($result, -200));

        // Cleanup individual noise files
        if (file_exists($whiteNoisePath)) @unlink($whiteNoisePath);
        if (file_exists($pinkNoisePath)) @unlink($pinkNoisePath);
        if (file_exists($brownNoisePath)) @unlink($brownNoisePath);

        if (file_exists($outputPath)) {
            Log::info("Mixed audio created successfully: " . filesize($outputPath) . " bytes");
            return ['status' => 'success'];
        }
        
        Log::error("Failed to create mixed audio");
        return ['status' => 'error'];
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
     * Compress base video (no audio) before concatenation
     */


    /**
     * Copy 1-minute video multiple times and merge to target duration
     */
    public static function copyAndMergeToTargetDuration()
    {
        $copyCount = self::initTargetMinutes();
        $sourcePath = storage_path('app/finals/short_video_compressed.mp4');
        $copysPath = storage_path('app/copys_short');
        $outputPath = storage_path('app/outputs/short_video_final.mp4');

        if (!file_exists($sourcePath)) {
            Log::error('Compressed short video not found');
            return false;
        }

        if (!file_exists($copysPath)) {
            mkdir($copysPath, 0777, true);
        }

        // Clear previous copies
        foreach (File::files($copysPath) as $file) {
            File::delete($file);
        }

        // Create copies
        for ($i = 1; $i <= $copyCount; $i++) {
            copy($sourcePath, $copysPath . '/short_video_' . $i . '.mp4');
        }

        // Get videos
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

        $listFile = storage_path('app/short_videos_list.txt');
        $fileListContent = '';

        // --- INTRO LOGIC ---
        $introPath = storage_path('app/intros');
        if (file_exists($introPath) && is_dir($introPath)) {
            $intros = collect(File::files($introPath))
                ->filter(function ($file) {
                    return in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'avi']);
                });

            if ($intros->isNotEmpty()) {
                $randomIntro = $intros->random()->getPathname();
                $fixedIntroPath = storage_path('app/finals/fixed_intro_short.mp4');

                // Get specs from first video copy
                $sampleVideo = $videos->first()->getPathname();
                $probeCmd = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height,r_frame_rate -of csv=p=0 " . escapeshellarg($sampleVideo);
                $specs = trim(shell_exec($probeCmd));
                list($width, $height, $frameRate) = explode(',', $specs);

                if (strpos($frameRate, '/') !== false) {
                    $parts = explode('/', $frameRate);
                    $frameRate = intval($parts[0]) / intval($parts[1]);
                }

                // Get audio specs
                $audioProbeCmd = "ffprobe -v error -select_streams a:0 -show_entries stream=sample_rate,channels -of csv=p=0 " . escapeshellarg($sampleVideo);
                $audioSpecs = trim(shell_exec($audioProbeCmd));
                $sampleRate = 44100;
                $channels = 2;
                if (!empty($audioSpecs) && strpos($audioSpecs, ',') !== false) {
                    list($sampleRate, $channels) = explode(',', $audioSpecs);
                }

                // Re-encode intro
                $filterComplex = "scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2,fps={$frameRate}";
                $reencodeCmd = "ffmpeg -y -i " . escapeshellarg($randomIntro)
                    . " -c:v libx264 -preset fast -crf 23"
                    . " -vf " . escapeshellarg($filterComplex)
                    . " -c:a aac -b:a 192k -ar {$sampleRate} -ac {$channels}"
                    . " " . escapeshellarg($fixedIntroPath) . " 2>&1";

                exec($reencodeCmd, $reencodeOutput, $reencodeReturn);

                if ($reencodeReturn === 0 && file_exists($fixedIntroPath)) {
                    $fileListContent .= "file '" . $fixedIntroPath . "'\n";
                    Log::info("Intro added to merge list");
                } else {
                    Log::error("Failed to re-encode intro: " . implode("\n", $reencodeOutput));
                }
            }
        }

        foreach ($videos as $video) {
            $fileListContent .= "file '" . $video->getPathname() . "'\n";
        }
        file_put_contents($listFile, $fileListContent);

        $ffmpegCmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c copy " . escapeshellarg($outputPath) . " -y";
        exec($ffmpegCmd, $output, $returnVar);

        // Cleanup
        foreach (File::files($copysPath) as $file) File::delete($file);
        if (file_exists($sourcePath)) unlink($sourcePath);
        if (file_exists($listFile)) unlink($listFile);
        if (isset($fixedIntroPath) && file_exists($fixedIntroPath)) unlink($fixedIntroPath);

        if ($returnVar === 0) {
            Log::info("Short video merged successfully");
            return true;
        } else {
            Log::error("Failed to merge short video: " . implode("\n", $output));
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

        $audioFilters = "volume={$volume}*{$amplitudeVar},equalizer=f=100:t=q:w=1:g={$bassBoost},equalizer=f=1000:t=q:w=1:g={$midCut},equalizer=f=8000:t=q:w=1:g={$trebleBoost}";

        // Use shell_exec for reliability with long-running processes
        $cmd = "ffmpeg -y -f lavfi -i \"anoisesrc=color=white:duration={$duration}:sample_rate=44100:seed={$seed}\" "
            . "-af \"" . $audioFilters . "\" "
            . "-c:a libmp3lame -q:a 2 -ar 44100 "
            . escapeshellarg($filePath) . " 2>&1";
        
        shell_exec($cmd);

        if (file_exists($filePath)) {
            return ['status' => 'success'];
        }
        
        Log::error("Failed to generate white noise");
        return ['status' => 'error', 'message' => 'Failed to generate white noise'];
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

        $audioFilters = "volume={$volume}*{$amplitudeVar},equalizer=f=100:t=q:w=1:g={$bassBoost},equalizer=f=1000:t=q:w=1:g={$midCut},equalizer=f=8000:t=q:w=1:g={$trebleBoost}";

        $cmd = "ffmpeg -y -f lavfi -i \"anoisesrc=color=pink:duration={$duration}:sample_rate=44100:seed={$seed}\" "
            . "-af \"" . $audioFilters . "\" "
            . "-c:a libmp3lame -q:a 2 -ar 44100 "
            . escapeshellarg($filePath) . " 2>&1";
        
        shell_exec($cmd);

        if (file_exists($filePath)) {
            return ['status' => 'success'];
        }
        
        Log::error("Failed to generate pink noise");
        return ['status' => 'error', 'message' => 'Failed to generate pink noise'];
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

        $audioFilters = "volume={$volume}*{$amplitudeVar},equalizer=f=100:t=q:w=1:g={$bassBoost},equalizer=f=1000:t=q:w=1:g={$midCut},equalizer=f=8000:t=q:w=1:g={$trebleBoost}";

        $cmd = "ffmpeg -y -f lavfi -i \"anoisesrc=color=brown:duration={$duration}:sample_rate=44100:seed={$seed}\" "
            . "-af \"" . $audioFilters . "\" "
            . "-c:a libmp3lame -q:a 2 -ar 44100 "
            . escapeshellarg($filePath) . " 2>&1";
        
        shell_exec($cmd);

        if (file_exists($filePath)) {
            return ['status' => 'success'];
        }
        
        Log::error("Failed to generate brown noise");
        return ['status' => 'error', 'message' => 'Failed to generate brown noise'];
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
        Log::info(['response'=> $response->json()]);

        return $response->json()["output"][0]['content'][0]['text'];
    }
}
