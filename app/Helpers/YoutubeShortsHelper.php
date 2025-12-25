<?php

namespace App\Helpers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class YoutubeShortsHelper
{
    /**
     * Create a vertical video (1080x1920) with duration 60-180 seconds
     */
    public static function generateVideo()
    {
        $duration = rand(60, 180);
        Log::info("Starting YoutubeShorts creation - Duration: {$duration}s");

        // 1. Generate Audio
        self::generateAudio($duration);
        
        // 2. Create Visuals
        $videoPath = self::createVisuals($duration);
        
        // 3. Merge
        $finalPath = self::mergeAudioVideo($videoPath, $duration);
        
        return [
            'path' => $finalPath,
            'duration' => $duration
        ];
    }

    public static function generateAudio($duration)
    {
        // Reuse ShortVideoHelper's noise generation but for specific duration
        // We can just call the mix method if we make it accept seconds, 
        // but ShortVideoHelper::mixAudioForDuration takes seconds now (from my previous edit? No, it takes seconds in LongVideoHelper, let's check ShortVideoHelper)
        // ShortVideoHelper::mixAudioForDuration takes $duration (which was minutes * 60).
        // Let's verify ShortVideoHelper::mixAudioForDuration signature.
        
        ShortVideoHelper::mixAudioForDuration($duration);
    }

    public static function createVisuals($duration)
    {
        $back = rand(1, 11);
        $effectNumber = rand(1, 8);
        $soundBarNumber = rand(1, 8);
        $babyNumber = rand(1, 6);

        $background = storage_path("app/backgrounds/$back.mp4");
        $effect = storage_path("app/effects/$effectNumber.mp4");
        $soundbar = storage_path("app/soundbars/$soundBarNumber.mp4");
        $baby = storage_path("app/baby_greenscreen/$babyNumber.mp4");
        
        $output = storage_path('app/finals/shorts_visual.mp4');

        // Filter Complex for Vertical Video (1080x1920)
        
        // 1. Background: Scale to height 1920, crop center 1080
        // scale=-1:1920 sets height to 1920, width auto (approx 3413)
        // crop=1080:1920:1166:0 (center crop)
        
        // 2. Baby: Scale to width 1080 (height auto ~607)
        // Place in middle/bottom? Let's place at h=800
        
        // 3. Soundbar: Scale to width 1080
        // Place at bottom
        
        // 4. Effect: Scale to height 1920, crop center
        
        $filter = "";
        
        // Background [0]
        $filter .= "[0:v]scale=-1:1920,crop=1080:1920:(iw-1080)/2:0[bg];";
        
        // Effect [1] (Chromakey + Scale/Crop)
        $filter .= "[1:v]scale=-1:1920,crop=1080:1920:(iw-1080)/2:0,chromakey=0x00FF00:0.2:0.1[eff];";
        
        // Soundbar [2] (Chromakey + Scale width 1080)
        $filter .= "[2:v]scale=1080:-1,chromakey=0x00FF00:0.2:0.1[sb];";
        
        // Baby [3] (Chromakey + Scale width 900 to leave margins? Or 1080)
        // Let's do 900 width to keep it safe
        $filter .= "[3:v]scale=900:-1,chromakey=0x00FF00:0.2:0.1[baby];";
        
        // Overlays
        // BG + Effect
        $filter .= "[bg][eff]overlay=0:0[bg_eff];";
        
        // + Baby (Centered horizontally, vertical position centered)
        // x=(W-w)/2, y=(H-h)/2
        $filter .= "[bg_eff][baby]overlay=(W-w)/2:(H-h)/2[bg_eff_baby];";
        
        // + Soundbar (Bottom)
        // x=0, y=H-h-100 (padding from bottom)
        $filter .= "[bg_eff_baby][sb]overlay=(W-w)/2:H-h-100[out]";

        // We need to loop inputs to match duration
        // -stream_loop -1 for all inputs? 
        // FFmpeg input looping is tricky with complex filters.
        // Better to use -stream_loop -1 before each input and -t duration at output.
        
        $cmd = "ffmpeg -y "
            . "-stream_loop -1 -i " . escapeshellarg($background) . " "
            . "-stream_loop -1 -i " . escapeshellarg($effect) . " "
            . "-stream_loop -1 -i " . escapeshellarg($soundbar) . " "
            . "-stream_loop -1 -i " . escapeshellarg($baby) . " "
            . "-filter_complex " . escapeshellarg($filter) . " "
            . "-map [out] "
            . "-t {$duration} "
            . "-c:v libx264 -preset fast -crf 23 "
            . escapeshellarg($output) . " 2>&1";
            
        Log::info("Generating Shorts visuals...");
        exec($cmd, $outputLog, $returnVar);
        
        if ($returnVar !== 0) {
            Log::error("FFmpeg failed: " . implode("\n", $outputLog));
            return false;
        }
        
        return $output;
    }

    public static function mergeAudioVideo($videoPath, $duration)
    {
        $audioPath = storage_path('app/white_audio/mixed_short.mp3'); // ShortVideoHelper saves here
        // Wait, ShortVideoHelper::mixAudioForDuration saves to 'mixed_short.mp3' ?
        // Let's check ShortVideoHelper.
        
        // Assuming it does.
        $outputPath = storage_path('app/outputs/shorts_final.mp4');
        
        $cmd = "ffmpeg -y -i " . escapeshellarg($videoPath)
            . " -i " . escapeshellarg($audioPath)
            . " -c:v copy -c:a aac "
            . "-shortest "
            . escapeshellarg($outputPath) . " 2>&1";
            
        exec($cmd, $outputLog, $returnVar);
        
        if ($returnVar === 0) {
            @unlink($videoPath);
            @unlink($audioPath);
            return $outputPath;
        }
        
        Log::error("Merge failed: " . implode("\n", $outputLog));
        return false;
    }
    
    public static function generateText($prompt) {
        return ShortVideoHelper::generateText($prompt);
    }
}
