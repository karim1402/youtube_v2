<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Dotenv\Util\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;


class GeminiHelper
{
    private static $length;
    
    private static function initLength()
    {
        if (self::$length === null) {
            self::$length = rand(100, 140);
        }
        return self::$length;
    }
    
    public static function runvideo() {

        //check if this file exists and delete it
        $finalVideoPath = storage_path('app/outputs/finaloutpt123.mp4');
        if (file_exists($finalVideoPath)) {
            unlink($finalVideoPath);
        }

    
       self::mixAudio();
       date_default_timezone_set('Africa/Cairo');
       Log::info('UploadVideoJob started at ' . date('Y-m-d H:i:s'));
       self::full_video_fast();
       Log::info("Video created successfully.");
       self::mergeTwoAudioFiles();
       Log::info("Audio files merged successfully.");
       self::mergeFinalVideoWithAudio();
       Log::info("Final video merged with audio successfully.");
       self::compressFinalVideoWithAudio(90);
       Log::info("Final video compressed successfully.");
       self::copyVideoMultipleTimes(120);
       Log::info("Final video copied multiple times successfully.");
       self::mergeSameVideoMultipleTimes();
       Log::info("Same video merged multiple times successfully.");
    //    self::addRandomIntroToVideo();
    //    Log::info("Random intro added to video successfully.");


    }

    public static function base($text)
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

        // // Replace with your actual API key
        // $apiKey = 'AIzaSyACcDPq0OiAACUfpWZ55cRKjr_NoD5qIWY';

        // // Create a Guzzle HTTP client
        // $client = new Client();

        // // Define the API endpoint
        // $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}";

        // // Prepare the request payload
        // $payload = [
        //     "contents" => [
        //         [
        //             "parts" => [
        //                 ["text" => $text ]
        //             ]
        //         ]
        //     ]
        // ];

        // try {
        //     // Send the POST request
        //     $response = $client->post($url, [
        //         'headers' => [
        //             'Content-Type' => 'application/json',
        //         ],
        //         'json' => $payload,
        //     ]);
        //     // Decode the response
        //     $responseBody = json_decode($response->getBody(), true);

        //     // Extract the title from the response
        //     $title = $responseBody['candidates'][0]['content']['parts'][0]['text'];
        //     return  trim($title);



        // } catch (\Exception $e) {
        //     Log::error($e->getMessage());
        //     return response()->json([
        //         'error' => $e->getMessage(),
        //     ], 500);
        // }
    }


    public static function overlayImages()
    {

        $back = rand(1, 35);
        $baby = rand(1, 33);

        // Define paths
        $baseImagePath = storage_path("app/background/$back.png");
        $overlayImagePath = storage_path("app/baby/$baby.png");
        $cornerImagePath = storage_path("app/logo/file.png");

        // Create image resources
        $baseImageOriginal = imagecreatefromstring(file_get_contents($baseImagePath));
        $overlayImageOriginal = imagecreatefromstring(file_get_contents($overlayImagePath));
        $cornerImage = imagecreatefromstring(file_get_contents($cornerImagePath));

        // Get dimensions
        $baseWidth = imagesx($baseImageOriginal);
        $baseHeight = imagesy($baseImageOriginal);
        $overlayWidth = imagesx($overlayImageOriginal);
        $overlayHeight = imagesy($overlayImageOriginal);

        // Resize overlay image to 85% of its original size
        $newOverlayWidth = (int)($overlayWidth * 0.70);
        $newOverlayHeight = (int)($overlayHeight * 0.70);
        $overlayImage = imagecreatetruecolor($newOverlayWidth, $newOverlayHeight);
        imagealphablending($overlayImage, false);
        imagesavealpha($overlayImage, true);
        imagecopyresampled(
            $overlayImage,
            $overlayImageOriginal,
            0,
            0,
            0,
            0,
            $newOverlayWidth,
            $newOverlayHeight,
            $overlayWidth,
            $overlayHeight
        );

        // Create a true color image for the base (same size as original background)
        $baseImage = imagecreatetruecolor($baseWidth, $baseHeight);
        imagecopy($baseImage, $baseImageOriginal, 0, 0, 0, 0, $baseWidth, $baseHeight);

        // Overlay the resized baby image at the center of the background
        $posX = ($baseWidth - $newOverlayWidth) / 2;
        $posY = ($baseHeight - $newOverlayHeight) / 2;
        imagecopy($baseImage, $overlayImage, $posX, $posY, 0, 0, $newOverlayWidth, $newOverlayHeight);

        // Resize the corner logo (12% of base width)
        $cornerWidth = $baseWidth * 0.12;
        $cornerHeight = $cornerWidth * (imagesy($cornerImage) / imagesx($cornerImage));
        $cornerResized = imagecreatetruecolor($cornerWidth, $cornerHeight);
        imagealphablending($cornerResized, false);
        imagesavealpha($cornerResized, true);
        imagecopyresampled(
            $cornerResized,
            $cornerImage,
            0,
            0,
            0,
            0,
            $cornerWidth,
            $cornerHeight,
            imagesx($cornerImage),
            imagesy($cornerImage)
        );

        // Position the logo in the top-right corner with 2% margin
        $marginRight = $baseWidth * 0.02;
        $marginTop = $baseHeight * 0.02;
        $positionX = $baseWidth - $cornerWidth - $marginRight;
        $positionY = $marginTop;

        // Merge the corner logo onto the image
        imagecopy($baseImage, $cornerResized, $positionX, $positionY, 0, 0, $cornerWidth, $cornerHeight);

        // Save final image
        $outputPath = storage_path('app/public/merged_image.png');
        imagepng($baseImage, $outputPath);
        // Clean up
        imagedestroy($baseImageOriginal);
        imagedestroy($baseImage);
        imagedestroy($overlayImageOriginal);
        imagedestroy($overlayImage);
        imagedestroy($cornerImage);
        imagedestroy($cornerResized);

        // Save the resulting image to storage



    }

    /**
     * Merge up to 10 random video clips from storage/app/clips into storage/app/outputs/merged.mp4
     * Returns true on success, false on failure.
     */
    public static function mergeClips()
    {
        $clipsPath = storage_path('app/clips');
        $outputPath = storage_path('app/outputs/merged.mp4');
        $randomNumber = 10; //rand(8, 12);
        //rand(1, 6);


        // Get all video files in the clips directory (e.g., .mp4, .mov, .avi)
        $videos = collect(File::files($clipsPath))
            ->filter(function ($file) {
                return in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'avi']);
            })
            ->shuffle()
            ->take($randomNumber)
            ->values();

        if ($videos->isEmpty()) {
            return false;
        }

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

        Log::info($randomNumber);

        return $randomNumber;
    }


     public static function mergeWithGreenScreen1($background, $greenScreen, $output)
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

    public static function full_video_fast()
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


    public static function mergeTwoAudioFiles()
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

    public static function mergeFinalVideoWithAudio()
    {
        $videoPath = storage_path('app/finals/final_video.mp4');
        $audioPath = storage_path('app/white_audio/mixed.mp3');
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

        // delete the video without audio and the merged audio
        if (file_exists($outputPath)) {
            unlink($videoPath);
            unlink($audioPath);
        }



        return response()->json([
            'success' => $resultCode === 0,
            'output' => $outputPath,
            'ffmpeg_output' => implode("\n", $output)
        ]);
    }

    public static function copyVideoMultipleTimes($count = 120)
    {
        $outputPath = storage_path('app/finals/final_video_with_audio_compressed.mp4');
        $videoPath = storage_path('app/copys');

        if (!file_exists($outputPath)) {
            return false;
        }

        if (!file_exists($videoPath)) {
            mkdir($videoPath, 0777, true);
        }

        $count = self::initLength();
        
        for ($i = 1; $i <= $count; $i++) {
            $copyPath = $videoPath . '/final_video_with_audio_' . $i . '.mp4';
            copy($outputPath, $copyPath);
        }

        //delete the compressed final video after copying
        if (file_exists($outputPath)) {
            unlink($outputPath);
        }

       
    }

   public static function mergeSameVideoMultipleTimes()
    {

        $videoPath =  storage_path('app/copys');
        $outputPath = storage_path('app/outputs/finaloutpt123.mp4');
        $videos = collect(File::files($videoPath))
            ->filter(function ($file) {
                return in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'avi']);
            })
            ->shuffle()
            ->take(self::initLength())
            ->values();

        if ($videos->isEmpty()) {
            Log::error("No videos found in copys folder");
            return false;
        }

        // Get a sample video to extract specifications
        $sampleVideo = $videos->first()->getPathname();

        // Extract video specifications from the sample
        $probeCmd = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height,r_frame_rate -of csv=p=0 " . escapeshellarg($sampleVideo);
        $specs = trim(shell_exec($probeCmd));
        list($width, $height, $frameRate) = explode(',', $specs);

        // Convert frame rate fraction to decimal (e.g., "30/1" to "30")
        if (strpos($frameRate, '/') !== false) {
            $parts = explode('/', $frameRate);
            $frameRate = intval($parts[0]) / intval($parts[1]);
        }

        // Extract audio specifications
        $audioProbeCmd = "ffprobe -v error -select_streams a:0 -show_entries stream=sample_rate,channels -of csv=p=0 " . escapeshellarg($sampleVideo);
        $audioSpecs = trim(shell_exec($audioProbeCmd));
        list($sampleRate, $channels) = explode(',', $audioSpecs);

        Log::info("Video specs - Width: {$width}, Height: {$height}, FPS: {$frameRate}, Sample Rate: {$sampleRate}, Channels: {$channels}");

        $listFile = storage_path('app/videos_repeat.txt');
        $fileListContent = '';

        //add random intro video in the top of the list from app/intros folder
        $introPath = storage_path('app/intros');
        
        if (!file_exists($introPath) || !is_dir($introPath)) {
            Log::warning("Intros folder not found: {$introPath}");
        } else {
            $introFiles = collect(File::files($introPath))
                ->filter(function ($file) {
                    return in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'avi']);
                })
                ->shuffle()
                ->take(1)
                ->values();

            if ($introFiles->isEmpty()) {
                Log::warning("No intro videos found in: {$introPath}");
            } else {
                $originalIntro = $introFiles->first()->getPathname();
                $fixedIntroPath = storage_path('app/intros/fixed_intro_temp.mp4');

                Log::info("Re-encoding intro: {$originalIntro}");

                // Re-encode intro to match main videos' specifications (simplified, no pixel format)
                $filterComplex = "scale={$width}:{$height}:force_original_aspect_ratio=decrease,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2,fps={$frameRate}";
                
                $reencodeCmd = "ffmpeg -y -i " . escapeshellarg($originalIntro)
                    . " -c:v libx264 -preset fast -crf 23"
                    . " -vf " . escapeshellarg($filterComplex)
                    . " -c:a aac -b:a 192k -ar {$sampleRate} -ac {$channels}"
                    . " " . escapeshellarg($fixedIntroPath) . " 2>&1";

                Log::info("Re-encode command: {$reencodeCmd}");
                
                exec($reencodeCmd, $reencodeOutput, $reencodeReturn);

                if ($reencodeReturn === 0 && file_exists($fixedIntroPath)) {
                    // Use the fixed intro
                    $fileListContent .= "file '" . $fixedIntroPath . "'\n";
                    Log::info("✓ Intro video re-encoded successfully and added to merge list");
                } else {
                    Log::error("✗ Failed to re-encode intro (return code: {$reencodeReturn}). Error: " . implode("\n", $reencodeOutput));
                }
            }
        }

        foreach ($videos as $video) {
            $fileListContent .= "file '" . $video->getPathname() . "'\n";
        }
        file_put_contents($listFile, $fileListContent);

        $ffmpegCmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c copy " . escapeshellarg($outputPath) . " -y";

        exec($ffmpegCmd, $output, $returnVar);

        // Clean up temporary fixed intro file
        $fixedIntroPath = storage_path('app/intros/fixed_intro_temp.mp4');
        if (file_exists($fixedIntroPath)) {
            unlink($fixedIntroPath);
        }

       //delete all files in the copys folder
        $files = File::files($videoPath);
        foreach ($files as $file) {
            File::delete($file);
        }

        if ($returnVar === 0) {
            Log::info("Videos merged successfully with intro");
            return true;
        } else {
            Log::error("Failed to merge videos. FFmpeg output: " . implode("\n", $output));
            return false;
        }
      
    }

    


    public static function compressFinalVideoWithAudio($targetSizeMB = 150)
    {
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

        //delete the uncompressed final video and keep only the compressed one
        if (file_exists($outputPath)) {
            unlink($inputPath);
       }
    }

    public static function generateWhiteNoise(int $duration = 300, ?string $filename = null, float $volume = 0.1): array
    {
        // try {
            // Create directory if it doesn't exist
            $directory = storage_path('app/white_audio');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $filename = 'white.mp3';

           

            // Ensure volume is within valid range
            $volume = max(0.1, min(1.0, $volume));
            
            // Generate STRONG unique variations for each audio
            $seed = mt_rand(0, 999999);
            
            // Add random frequency variations for MORE difference
            $bassBoost = mt_rand(0, 5);           // Random bass: 0-5 dB
            $trebleBoost = mt_rand(-3, 3);        // Random treble: -3 to +3 dB
            $midCut = mt_rand(-2, 2);             // Random mid: -2 to +2 dB
            
            // Add random amplitude variation
            $amplitudeVar = 0.95 + (mt_rand(0, 100) / 1000); // 0.95 to 1.05

            $filePath = $directory . '/' . $filename;

            

            // Build FFmpeg command with clean EQ-only randomization
            // Creates variation without degrading audio quality
            $audioFilters = [
                "volume={$volume}*{$amplitudeVar}",                    // Random volume variation
                "equalizer=f=100:t=q:w=1:g={$bassBoost}",              // Random bass boost
                "equalizer=f=1000:t=q:w=1:g={$midCut}",                // Random mid adjustment
                "equalizer=f=8000:t=q:w=1:g={$trebleBoost}"            // Random treble boost
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

            // Execute FFmpeg command
            $process = new Process($command);
            $process->setTimeout($duration + 60); // Add buffer time
            $process->run();

            // Check if process was successful
            if (!$process->isSuccessful()) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to generate white noise: ' . $process->getErrorOutput(),
                    'file_path' => null,
                ];
            }

            // Verify file was created
            if (!file_exists($filePath)) {
                return [
                    'status' => 'error',
                    'message' => 'White noise file was not created',
                    'file_path' => null,
                ];
            }

            $fileSize = filesize($filePath);
            $relativePath = 'white_noise/' . $filename;

            return [
                'status' => 'success',
            ];

            // return [
            //     'status' => 'success',
            //     'message' => 'White noise generated successfully with unique audio signature',
            //     'file_path' => $filePath,
            //     'relative_path' => $relativePath,
            //     'filename' => $filename,
            //     'duration' => $duration,
            //     'file_size' => $fileSize,
            //     'file_size_mb' => round($fileSize / 1024 / 1024, 2),
            //     'volume' => $volume,
            //     'seed' => $seed,
            //     'audio_signature' => [
            //         'bass_boost' => $bassBoost . ' dB',
            //         'treble_boost' => $trebleBoost . ' dB',
            //         'mid_adjustment' => $midCut . ' dB',
            //         'amplitude_variation' => round($amplitudeVar, 3),
            //     ],
            // ];

        // } catch (\Exception $e) {
        //     return [
        //         'status' => 'error',
        //         'message' => 'Exception occurred: ' . $e->getMessage(),
        //         'file_path' => null,
        //     ];
        // }
    }

    public static function generatePinkNoise(int $duration = 300, ?string $filename = null, float $volume = 0.4): array
    {
        // try {
            $directory = storage_path('app/white_audio');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            $filename = 'pink.mp3';

            

            // Ensure volume is within valid range
            $volume = max(0.1, min(1.0, $volume));
            
            // Generate STRONG unique variations for each audio
            $seed = mt_rand(0, 999999);
            
            // Add random frequency variations for MORE difference
            $bassBoost = mt_rand(0, 5);           // Random bass: 0-5 dB
            $trebleBoost = mt_rand(-3, 3);        // Random treble: -3 to +3 dB
            $midCut = mt_rand(-2, 2);             // Random mid: -2 to +2 dB
            
            // Add random amplitude variation
            $amplitudeVar = 0.95 + (mt_rand(0, 100) / 1000); // 0.95 to 1.05

            $filePath = $directory . '/' . $filename;

           

            // Build FFmpeg command with clean EQ-only randomization
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
                return [
                    'status' => 'error',
                    'message' => 'Failed to generate pink noise: ' . $process->getErrorOutput(),
                    'file_path' => null,
                ];
            }

            if (!file_exists($filePath)) {
                return [
                    'status' => 'error',
                    'message' => 'Pink noise file was not created',
                    'file_path' => null,
                ];
            }

            $fileSize = filesize($filePath);
            $relativePath = 'white_noise/' . $filename;

            return [
                'status' => 'success',
                'message' => 'Pink noise generated successfully with unique audio signature',
                'file_path' => $filePath,
                'relative_path' => $relativePath,
                'filename' => $filename,
                'duration' => $duration,
                'file_size' => $fileSize,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2),
                'volume' => $volume,
                'seed' => $seed,
                'audio_signature' => [
                    'bass_boost' => $bassBoost . ' dB',
                    'treble_boost' => $trebleBoost . ' dB',
                    'mid_adjustment' => $midCut . ' dB',
                    'amplitude_variation' => round($amplitudeVar, 3),
                ],
            ];

        // } catch (\Exception $e) {
        //     return [
        //         'status' => 'error',
        //         'message' => 'Exception occurred: ' . $e->getMessage(),
        //         'file_path' => null,
        //     ];
        // }
    }

    public static function generateBrownNoise(int $duration = 300, ?string $filename = null, float $volume = 0.4): array
    {
        
            $directory = storage_path('app/white_audio');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $filename = 'brown.mp3';

            // Ensure volume is within valid range
            $volume = max(0.1, min(1.0, $volume));
            
            // Generate STRONG unique variations for each audio
            $seed = mt_rand(0, 999999);
            
            // Add random frequency variations for MORE difference
            $bassBoost = mt_rand(0, 5);           // Random bass: 0-5 dB
            $trebleBoost = mt_rand(-3, 3);        // Random treble: -3 to +3 dB
            $midCut = mt_rand(-2, 2);             // Random mid: -2 to +2 dB
            
            // Add random amplitude variation
            $amplitudeVar = 0.95 + (mt_rand(0, 100) / 1000); // 0.95 to 1.05

            $filePath = $directory . '/' . $filename;

            

            // Build FFmpeg command with clean EQ-only randomization
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
                return [
                    'status' => 'error',
                    'message' => 'Failed to generate brown noise: ' . $process->getErrorOutput(),
                    'file_path' => null,
                ];
            }

            if (!file_exists($filePath)) {
                return [
                    'status' => 'error',
                    'message' => 'Brown noise file was not created',
                    'file_path' => null,
                ];
            }

            $fileSize = filesize($filePath);
            $relativePath = 'white_noise/' . $filename;

            return [
                'status' => 'success',
                'message' => 'Brown noise generated successfully with unique audio signature',
                'file_path' => $filePath,
                'relative_path' => $relativePath,
                'filename' => $filename,
                'duration' => $duration,
                'file_size' => $fileSize,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2),
                'volume' => $volume,
                'seed' => $seed,
                'audio_signature' => [
                    'bass_boost' => $bassBoost . ' dB',
                    'treble_boost' => $trebleBoost . ' dB',
                    'mid_adjustment' => $midCut . ' dB',
                    'amplitude_variation' => round($amplitudeVar, 3),
                ],
            ];

        
    }

    public static function mixAudio(){
        self::generateWhiteNoise();
        self::generatePinkNoise();
        self::generateBrownNoise();

        $whiteNoisePath = storage_path('app/white_audio/white.mp3');
        $pinkNoisePath = storage_path('app/white_audio/pink.mp3');
        $brownNoisePath = storage_path('app/white_audio/brown.mp3');

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
            storage_path('app/white_audio/mixed.mp3'),
        ];

        $process = new Process($command);
        $process->setTimeout(600);
        $process->run();

        //delete the 3 audio files
        unlink($whiteNoisePath);
        unlink($pinkNoisePath);
        unlink($brownNoisePath);

        return [
            'status' => 'success',
          
        ];
        
    }

    /**
     * Add a random intro video to the final output video
     * Takes the output from mergeSameVideoMultipleTimes() and prepends a random intro
     * 
     * @return bool True on success, false on failure
     */
    public static function addRandomIntroToVideo()
    {
        $introPath = storage_path('app/intros');
        $mainVideoPath = storage_path('app/outputs/finaloutpt123.mp4');
        $outputPath = storage_path('app/outputs/final_with_intro.mp4');

        // Check if main video exists
        if (!file_exists($mainVideoPath)) {
            Log::error("Main video not found: $mainVideoPath");
            return false;
        }

        // Check if intros directory exists
        if (!file_exists($introPath) || !is_dir($introPath)) {
            Log::error("Intros directory not found: $introPath");
            return false;
        }

        // Get all video files from intros directory
        $intros = collect(File::files($introPath))
            ->filter(function ($file) {
                return in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'avi']);
            })
            ->values();

        if ($intros->isEmpty()) {
            Log::error("No intro videos found in: $introPath");
            return false;
        }

        // Select a random intro
        $randomIntro = $intros->random();
        $introVideoPath = $randomIntro->getPathname();

        // Create a temporary file list for FFmpeg concat
        $listFile = storage_path('app/intro_concat_list.txt');
        $fileListContent = "file '" . $introVideoPath . "'\n";
        $fileListContent .= "file '" . $mainVideoPath . "'\n";
        file_put_contents($listFile, $fileListContent);

        // Build FFmpeg command to concatenate intro + main video with proper audio re-encoding
        // Using re-encode to ensure audio compatibility between intro and main video
        $ffmpegCmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) 
            . " -c:v libx264 -preset fast -crf 23 -c:a aac -b:a 192k -ar 44100 " 
            . escapeshellarg($outputPath) . " -y 2>&1";

        exec($ffmpegCmd, $output, $returnVar);

        // Clean up the list file
        // if (file_exists($listFile)) {
        //     unlink($listFile);
        // }

        if ($returnVar === 0 && file_exists($outputPath)) {
            Log::info("Successfully added intro to video. Output: $outputPath");
            return true;
        } else {
            Log::error("Failed to add intro to video. FFmpeg output: " . implode("\n", $output));
            return false;
        }
    }


  
   
}
