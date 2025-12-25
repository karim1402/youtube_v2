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
        
        // 1. Generate Base Video (~5 mins)
        Log::info("Generating base video...");
        $baseVideoPath = self::generateBaseVideo();
        if (!$baseVideoPath) return false;

        // 2. Generate Audio for Base Video
        Log::info("Generating audio for base video...");
        $durationCmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($baseVideoPath);
        $baseDuration = floatval(trim(shell_exec($durationCmd)));
        if ($baseDuration <= 0) $baseDuration = 300;
        
        self::mixAudioForDuration($baseDuration);
        
        // 3. Merge Audio to Base Video
        Log::info("Merging audio to base video...");
        $audioPath = storage_path('app/white_audio/mixed_long.mp3');
        $videoWithAudioPath = storage_path('app/finals/long_base_video_audio.mp4');
        
        $cmd = "ffmpeg -y -i " . escapeshellarg($baseVideoPath)
            . " -i " . escapeshellarg($audioPath)
            . " -c:v copy -c:a aac -shortest " . escapeshellarg($videoWithAudioPath) . " 2>&1";
        exec($cmd);
        
        if (file_exists($videoWithAudioPath)) {
            unlink($baseVideoPath);
            unlink($audioPath);
            $baseVideoPath = $videoWithAudioPath;
        }
        
        // 4. Loop Video to Target Duration (includes Intro)
        Log::info("Looping video to target duration...");
        $loopedVideoPath = self::loopVideoToDuration($baseVideoPath, $targetMinutes);
        if (!$loopedVideoPath) return false;
        
        // 5. Compress
        Log::info("Compressing final video...");
        $compressedPath = self::compressVideo($loopedVideoPath, $targetHours);
        
        // Cleanup intermediate files
        self::cleanup([$baseVideoPath, $loopedVideoPath]);
        
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

        // --- INTRO LOGIC ---
        $introPath = storage_path('app/intros');
        if (file_exists($introPath) && is_dir($introPath)) {
            $intros = collect(File::files($introPath))
                ->filter(function ($file) {
                    return in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'avi']);
                });

            if ($intros->isNotEmpty()) {
                $randomIntro = $intros->random()->getPathname();
                $fixedIntro = storage_path('app/finals/fixed_intro_long.mp4');

                // Get specs from base video
                $probeCmd = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height,r_frame_rate -of csv=p=0 " . escapeshellarg($baseVideoPath);
                $specs = trim(shell_exec($probeCmd));
                list($width, $height, $frameRate) = explode(',', $specs);

                if (strpos($frameRate, '/') !== false) {
                    $parts = explode('/', $frameRate);
                    $frameRate = intval($parts[0]) / intval($parts[1]);
                }

                // Get audio specs
                $audioProbeCmd = "ffprobe -v error -select_streams a:0 -show_entries stream=sample_rate,channels -of csv=p=0 " . escapeshellarg($baseVideoPath);
                $audioSpecs = trim(shell_exec($audioProbeCmd));
                $sampleRate = 44100;
                $channels = 2;
                if (!empty($audioSpecs) && strpos($audioSpecs, ',') !== false) {
                    list($sampleRate, $channels) = explode(',', $audioSpecs);
                }

                // Re-encode intro
                $filterComplex = "scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2,fps={$frameRate}";
                $fixCmd = "ffmpeg -y -i " . escapeshellarg($randomIntro) 
                    . " -c:v libx264 -preset fast -crf 23"
                    . " -vf " . escapeshellarg($filterComplex)
                    . " -c:a aac -b:a 192k -ar {$sampleRate} -ac {$channels}"
                    . " " . escapeshellarg($fixedIntro) . " 2>&1";
                
                exec($fixCmd, $reencodeOutput, $reencodeReturn);

                if ($reencodeReturn === 0 && file_exists($fixedIntro)) {
                    $content .= "file '" . $fixedIntro . "'\n";
                    Log::info("Intro added to loop list");
                } else {
                    Log::error("Failed to re-encode intro: " . implode("\n", $reencodeOutput));
                }
            }
        }

        for ($i = 0; $i < $loopCount; $i++) {
            $content .= "file '$baseVideoPath'\n";
        }
        file_put_contents($listFile, $content);
        
        $cmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c copy -y " . escapeshellarg($outputPath) . " 2>&1";
        exec($cmd, $output, $returnVar);
        
        unlink($listFile);
        if (isset($fixedIntro) && file_exists($fixedIntro)) unlink($fixedIntro);
        
        return ($returnVar === 0) ? $outputPath : false;
    }

    /**
     * Add random intro to the start
     */


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
        Log::info("LongVideoHelper: Generating audio for {$durationSeconds} seconds");
        
        // Create directory if it doesn't exist
        $directory = storage_path('app/white_audio');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        ShortVideoHelper::generateWhiteNoiseForDuration($durationSeconds);
        Log::info("White noise generated for long video");
        
        ShortVideoHelper::generatePinkNoiseForDuration($durationSeconds);
        Log::info("Pink noise generated for long video");
        
        ShortVideoHelper::generateBrownNoiseForDuration($durationSeconds);
        Log::info("Brown noise generated for long video");

        $white = storage_path('app/white_audio/white_short.mp3');
        $pink = storage_path('app/white_audio/pink_short.mp3');
        $brown = storage_path('app/white_audio/brown_short.mp3');
        $output = storage_path('app/white_audio/mixed_long.mp3');

        // Check if noise files were created
        if (!file_exists($pink)) {
            Log::error("Pink noise file not created: {$pink}");
        }
        if (!file_exists($brown)) {
            Log::error("Brown noise file not created: {$brown}");
        }

        $cmd = "ffmpeg -y -i " . escapeshellarg($pink) . " -i " . escapeshellarg($brown)
            . " -filter_complex \"[0:a][1:a]amix=inputs=2:duration=longest[a]\""
            . " -map \"[a]\" -c:a libmp3lame -q:a 2 -ar 44100 " . escapeshellarg($output) . " 2>&1";
            
        $result = shell_exec($cmd);
        Log::info("Mix audio result: " . substr($result ?? '', -200));

        @unlink($white);
        @unlink($pink);
        @unlink($brown);

        if (file_exists($output)) {
            Log::info("Long video audio created successfully: " . filesize($output) . " bytes");
        } else {
            Log::error("Failed to create long video audio");
        }
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
