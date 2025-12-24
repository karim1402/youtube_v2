<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * LongVideoHelper - Creates long videos (1h, 3h, 10h) using full-length material files
 * Optimized for sequential processing to save storage
 */
class LongVideoHelper
{
    /**
     * Main entry point - runs the complete long video creation pipeline
     * @param int $targetHours Target duration in hours
     * @return string|false Path to final video or false on failure
     */
    public static function processVideo($targetHours)
    {
        // Add random minutes (0-15) to make duration natural
        $targetMinutes = ($targetHours * 60) + rand(0, 15);
        $targetSeconds = $targetMinutes * 60;
        
        Log::info("Starting long video creation - Target: {$targetHours}h ({$targetMinutes}m)");
        
        date_default_timezone_set('Africa/Cairo');
        
        // 1. Generate Audio
        Log::info("Generating audio...");
        self::mixAudioForDuration($targetSeconds);
        
        // 2. Generate Base Video (~5 mins)
        Log::info("Generating base video...");
        $baseVideoPath = self::generateBaseVideo();
        if (!$baseVideoPath) return false;
        
        // 3. Loop Base Video to Target Duration
        Log::info("Looping video to target duration...");
        $loopedVideoPath = self::loopVideoToDuration($baseVideoPath, $targetMinutes);
        if (!$loopedVideoPath) return false;
        
        // 4. Add Intro
        Log::info("Adding intro...");
        $videoWithIntroPath = self::addIntro($loopedVideoPath);
        if (!$videoWithIntroPath) return false;
        
        // 5. Merge with Audio
        Log::info("Merging with audio...");
        $finalVideoPath = self::mergeWithAudio($videoWithIntroPath);
        
        // 6. Compress
        Log::info("Compressing final video...");
        $compressedPath = self::compressVideo($finalVideoPath, $targetHours);
        
        // Cleanup intermediate files
        self::cleanup([$baseVideoPath, $loopedVideoPath, $videoWithIntroPath, $finalVideoPath]);
        
        return $compressedPath;
    }

    /**
     * Generate base video using full-length assets (similar to GeminiHelper::full_video_fast)
     */
    public static function generateBaseVideo()
    {
        $back = rand(1, 11);
        $effectNumber = rand(1, 8);
        $soundBarNumber = rand(1, 8);
        $babyNumber = rand(1, 6);

        // Use full-length versions
        $background = storage_path("app/backgrounds/$back.mp4");
        $effect = storage_path("app/effects/$effectNumber.mp4");
        $soundbar = storage_path("app/soundbars/$soundBarNumber.mp4");
        $baby = storage_path("app/baby_greenscreen/$babyNumber.mp4");
        $sleep = storage_path('app/sleep_effects/1.mp4');

        $output = storage_path('app/finals/long_base_video.mp4');

        // Verify files
        foreach ([$background, $effect, $soundbar, $baby, $sleep] as $file) {
            if (!file_exists($file)) {
                Log::error("Base video asset not found: $file");
                // Fallback to 1_1min if full version missing (safety check)
                if (!file_exists($file) && strpos($file, 'backgrounds') !== false) {
                     Log::warning("Trying fallback for background...");
                }
                return false;
            }
        }

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
            . " -map [out] -c:v libx264 -crf 18 -preset fast -y " . escapeshellarg($output) . " 2>&1";

        shell_exec($cmd);

        if (file_exists($output)) {
            return $output;
        }
        return false;
    }

    /**
     * Loop the base video to reach target minutes
     */
    public static function loopVideoToDuration($baseVideoPath, $targetMinutes)
    {
        $outputPath = storage_path('app/finals/long_looped_video.mp4');
        
        // Get base video duration
        $durationCmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($baseVideoPath);
        $baseDuration = floatval(trim(shell_exec($durationCmd)));
        
        if ($baseDuration <= 0) return false;
        
        $loopCount = ceil(($targetMinutes * 60) / $baseDuration);
        
        // Create list file
        $listFile = storage_path('app/long_loop_list.txt');
        $content = "";
        for ($i = 0; $i < $loopCount; $i++) {
            $content .= "file '$baseVideoPath'\n";
        }
        file_put_contents($listFile, $content);
        
        $cmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c copy -y " . escapeshellarg($outputPath) . " 2>&1";
        exec($cmd, $output, $returnVar);
        
        unlink($listFile);
        
        return ($returnVar === 0) ? $outputPath : false;
    }

    /**
     * Add random intro to the start
     */
    public static function addIntro($videoPath)
    {
        $introPath = storage_path('app/intros');
        $outputPath = storage_path('app/finals/long_video_with_intro.mp4');

        if (!file_exists($introPath)) return $videoPath; // Skip if no intros

        $intros = collect(File::files($introPath))
            ->filter(function ($file) {
                return in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'avi']);
            })
            ->values();

        if ($intros->isEmpty()) return $videoPath;

        $randomIntro = $intros->random()->getPathname();
        
        // Re-encode intro to match base video properties to avoid concat issues
        $fixedIntro = storage_path('app/finals/fixed_intro.mp4');
        
        // Get video properties
        $probeCmd = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height,r_frame_rate -of csv=p=0 " . escapeshellarg($videoPath);
        $specs = trim(shell_exec($probeCmd));
        list($width, $height, $fps) = explode(',', $specs);
        
        // Fix intro
        $fixCmd = "ffmpeg -i " . escapeshellarg($randomIntro) 
            . " -vf \"scale=$width:$height:force_original_aspect_ratio=decrease,pad=$width:$height:(ow-iw)/2:(oh-ih)/2\""
            . " -c:v libx264 -preset fast -crf 23 -c:a aac -ar 44100 -y " . escapeshellarg($fixedIntro) . " 2>&1";
        shell_exec($fixCmd);

        // Concat
        $listFile = storage_path('app/long_intro_list.txt');
        file_put_contents($listFile, "file '$fixedIntro'\nfile '$videoPath'\n");
        
        $cmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c copy -y " . escapeshellarg($outputPath) . " 2>&1";
        exec($cmd, $output, $returnVar);
        
        unlink($listFile);
        if (file_exists($fixedIntro)) unlink($fixedIntro);
        
        return ($returnVar === 0) ? $outputPath : false;
    }

    /**
     * Merge video with generated audio
     */
    public static function mergeWithAudio($videoPath)
    {
        $audioPath = storage_path('app/white_audio/mixed_long.mp3');
        $outputPath = storage_path('app/finals/long_video_final_audio.mp4');
        
        if (!file_exists($audioPath)) {
            Log::error("Audio file not found: $audioPath");
            return $videoPath;
        }

        $cmd = "ffmpeg -y -i " . escapeshellarg($videoPath)
            . " -i " . escapeshellarg($audioPath)
            . " -c:v copy -c:a aac -shortest " . escapeshellarg($outputPath) . " 2>&1";
            
        exec($cmd, $output, $returnVar);
        
        // Cleanup audio
        unlink($audioPath);
        
        return ($returnVar === 0) ? $outputPath : false;
    }

    /**
     * Compress video based on duration
     */
    public static function compressVideo($inputPath, $hours)
    {
        $outputPath = storage_path('app/outputs/long_video_final.mp4');
        
        // Calculate bitrate based on target size
        // 1h -> ~300MB
        // 3h -> ~800MB
        // 10h -> ~2GB
        $targetSizeMB = match($hours) {
            1 => 300,
            3 => 800,
            10 => 2000,
            default => 500
        };
        
        $duration = $hours * 3600;
        $targetSizeBytes = $targetSizeMB * 1024 * 1024;
        $bitrate = intval(($targetSizeBytes * 8) / $duration);
        
        $cmd = "ffmpeg -y -i " . escapeshellarg($inputPath)
            . " -b:v {$bitrate} -maxrate {$bitrate} -bufsize " . intval($bitrate / 2)
            . " -c:v libx264 -c:a aac -preset fast "
            . escapeshellarg($outputPath) . " 2>&1";
            
        exec($cmd);
        
        return file_exists($outputPath) ? $outputPath : false;
    }

    /**
     * Generate audio for duration (reusing ShortVideoHelper logic)
     */
    public static function mixAudioForDuration($durationSeconds)
    {
        ShortVideoHelper::generateWhiteNoiseForDuration($durationSeconds);
        ShortVideoHelper::generatePinkNoiseForDuration($durationSeconds);
        ShortVideoHelper::generateBrownNoiseForDuration($durationSeconds);

        $white = storage_path('app/white_audio/white_short.mp3');
        $pink = storage_path('app/white_audio/pink_short.mp3');
        $brown = storage_path('app/white_audio/brown_short.mp3');
        $output = storage_path('app/white_audio/mixed_long.mp3');

        $cmd = "ffmpeg -y -i " . escapeshellarg($pink) . " -i " . escapeshellarg($brown)
            . " -filter_complex \"[0:a][1:a]amix=inputs=2:duration=longest[a]\""
            . " -map \"[a]\" -c:a libmp3lame -q:a 2 -ar 44100 " . escapeshellarg($output) . " 2>&1";
            
        shell_exec($cmd);

        @unlink($white);
        @unlink($pink);
        @unlink($brown);
    }
    
    public static function cleanup($files)
    {
        foreach ($files as $file) {
            if ($file && file_exists($file)) {
                unlink($file);
            }
        }
    }
    
    /**
     * Create overlay thumbnail
     */
    public static function overlayImages()
    {
        // Reuse ShortVideoHelper's logic but save to a different path if needed
        // For now, we can just use ShortVideoHelper::overlayImages() and move the file
        ShortVideoHelper::overlayImages();
        $src = storage_path('app/public/merged_image_short.png');
        $dest = storage_path('app/public/merged_image_long.png');
        if (file_exists($src)) rename($src, $dest);
    }
    
    /**
     * Generate text using AI
     */
    public static function generateText($text)
    {
        return ShortVideoHelper::generateText($text);
    }
}
