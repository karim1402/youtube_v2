<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class VideoProcessingService
{
    protected int $ffmpegTimeout = 3600;
    protected string $ffmpegPreset = 'fast'; // fast, medium, slow
    protected int $targetDurationHours = 10; // Default: 10 hours
    protected int $allowedVarianceMinutes = 10; // ±10 minutes is acceptable

    /**
     * Main video creation pipeline
     * Auto-calculates copies needed for target duration
     */
    public function createVideo(int $copyCount = null, int $targetSizeMB = 150): string
    {
        Log::info('Video processing started', ['timestamp' => now()]);

        try {
            // Ensure required directories exist
            $this->ensureDirectoriesExist();
            
            // Cleanup previous output if exists
            $this->cleanupPreviousOutput();
            
            // Cleanup any leftover concat files (prevents FFmpeg errors)
            $this->cleanupConcatFiles();

            // Step 1: Create layered video
            $this->createLayeredVideo();
            Log::info('✓ Layered video created');

            // Step 2: Mix audio files
            $this->mixAudioFiles();
            Log::info('✓ Audio files mixed');

            // Step 3: Merge video with audio
            $this->mergeVideoWithAudio();
            Log::info('✓ Video merged with audio');

            // Step 4: Compress video
            $this->compressVideo($targetSizeMB);
            Log::info('✓ Video compressed');

            // Step 5: Calculate copies needed for target duration (if not specified)
            if ($copyCount === null) {
                $copyCount = $this->calculateCopiesForTargetDuration();
            }

            // Step 6: Create repeated video (optimized)
            $this->createRepeatedVideoOptimized($copyCount);
            Log::info('✓ Repeated video created');

            $finalPath = storage_path('app/outputs/finaloutpt123.mp4');
            Log::info('Video processing completed successfully', ['output' => $finalPath]);

            return $finalPath;

        } catch (\Exception $e) {
            Log::error('Video processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Ensure all required directories exist
     */
    protected function ensureDirectoriesExist(): void
    {
        $directories = [
            storage_path('app/outputs'),
            storage_path('app/finals'),
            storage_path('app/copys'),
            storage_path('app/public'),
        ];

        foreach ($directories as $directory) {
            if (!file_exists($directory)) {
                mkdir($directory, 0775, true);
                Log::info("Created directory: {$directory}");
            }
        }
    }

    /**
     * Cleanup previous output file
     */
    protected function cleanupPreviousOutput(): void
    {
        $finalVideoPath = storage_path('app/outputs/finaloutpt123.mp4');
        if (file_exists($finalVideoPath)) {
            @unlink($finalVideoPath);
        }
    }

    /**
     * Cleanup any leftover concat files from previous runs
     * Prevents "Invalid data" FFmpeg errors
     */
    protected function cleanupConcatFiles(): void
    {
        $concatFiles = [
            storage_path('app/videos_repeat.txt'),
        ];

        foreach ($concatFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
                Log::info('Cleaned up leftover concat file', ['file' => basename($file)]);
            }
        }
    }

    /**
     * Create layered video with chromakey (green screen removal)
     * Performance: Uses configurable preset for faster encoding
     */
    public function createLayeredVideo(): void
    {
        // Random asset selection
        $assets = [
            'background' => storage_path('app/backgrounds/' . rand(1, 11) . '.mp4'),
            'effect' => storage_path('app/effects/' . rand(1, 8) . '.mp4'),
            'soundbar' => storage_path('app/soundbars/' . rand(1, 8) . '.mp4'),
            'baby' => storage_path('app/baby_greenscreen/' . rand(1, 6) . '.mp4'),
            'sleep' => storage_path('app/sleep_effects/1.mp4'),
        ];

        // Validate all files exist
        foreach ($assets as $name => $path) {
            if (!file_exists($path)) {
                throw new \RuntimeException("Missing asset: {$name} at {$path}");
            }
        }

        $output = storage_path('app/finals/final_video.mp4');

        // Build optimized filter chain
        $filter = $this->buildFilterChain();

        // Build FFmpeg command with HIGH QUALITY settings
        $command = [
            'ffmpeg', '-y',
            '-i', $assets['background'],
            '-i', $assets['effect'],
            '-i', $assets['soundbar'],
            '-i', $assets['baby'],
            '-i', $assets['sleep'],
            '-filter_complex', $filter,
            '-map', '[out]',
            '-c:v', 'libx264',
            '-crf', '18',                      // High quality (18 = visually lossless)
            '-preset', $this->ffmpegPreset,    // Configurable preset
            '-profile:v', 'high',              // High profile for better quality
            '-level', '4.1',                   // Compatibility level
            '-pix_fmt', 'yuv420p',             // Standard pixel format
            '-movflags', '+faststart',         // Enable streaming
            $output
        ];

        $this->executeFFmpegCommand($command);
    }

    /**
     * Build FFmpeg filter chain for chromakey compositing
     */
    protected function buildFilterChain(): string
    {
        return "[1:v]chromakey=0x00FF00:0.2:0.1[eff];" .
               "[0:v][eff]overlay[bg_eff];" .
               "[2:v]chromakey=0x00FF00:0.2:0.1[sb];" .
               "[bg_eff][sb]overlay[sb_eff];" .
               "[3:v]chromakey=0x00FF00:0.2:0.1[baby];" .
               "[sb_eff][baby]overlay[baby_eff];" .
               "[4:v]chromakey=0x00FF00:0.2:0.1[sleep];" .
               "[baby_eff][sleep]overlay[out]";
    }

    /**
     * Generate and mix brown and pink noise
     * Performance: Creates unique audio programmatically, auto-deletes temp files
     */
    public function mixAudioFiles(): void
    {
        Log::info('Generating brown and pink noise audio...');
        
        $whiteNoiseService = app(WhiteNoiseService::class);
        $outputPath = storage_path('app/finals/merged_audio.mp3');

        // Get base video duration to match audio length
        $baseDuration = 30; // Default 30 seconds for base video
        
        // Generate brown noise
        Log::info('Generating brown noise...');
        $brownResult = $whiteNoiseService->generateBrownNoise(
            duration: $baseDuration,
            filename: 'temp_brown_' . time() . '.mp3',
            volume: 0.5 // 50% volume for better mixing
        );

        if ($brownResult['status'] !== 'success') {
            throw new \RuntimeException("Failed to generate brown noise: " . $brownResult['message']);
        }

        // Generate pink noise
        Log::info('Generating pink noise...');
        $pinkResult = $whiteNoiseService->generatePinkNoise(
            duration: $baseDuration,
            filename: 'temp_pink_' . time() . '.mp3',
            volume: 0.5 // 50% volume for better mixing
        );

        if ($pinkResult['status'] !== 'success') {
            // Cleanup brown noise if pink fails
            @unlink($brownResult['file_path']);
            throw new \RuntimeException("Failed to generate pink noise: " . $pinkResult['message']);
        }

        Log::info('Mixing brown and pink noise together...');

        // Mix brown and pink noise together
        $command = [
            'ffmpeg', '-y',
            '-i', $brownResult['file_path'],
            '-i', $pinkResult['file_path'],
            '-filter_complex', '[0:0][1:0]amix=inputs=2:duration=longest:dropout_transition=2,volume=1.2', // Boost final volume
            '-c:a', 'libmp3lame',
            '-q:a', '2', // High quality
            '-ar', '44100',
            $outputPath
        ];

        try {
            $this->executeFFmpegCommand($command);
            
            Log::info('Audio mixing complete', [
                'output' => $outputPath,
                'size_mb' => round(filesize($outputPath) / 1024 / 1024, 2)
            ]);

        } finally {
            // Always delete temporary noise files after mixing
            Log::info('Cleaning up temporary noise files...');
            
            if (isset($brownResult['file_path']) && file_exists($brownResult['file_path'])) {
                @unlink($brownResult['file_path']);
                Log::info('Deleted temporary brown noise file');
            }
            
            if (isset($pinkResult['file_path']) && file_exists($pinkResult['file_path'])) {
                @unlink($pinkResult['file_path']);
                Log::info('Deleted temporary pink noise file');
            }
        }
    }

    /**
     * Merge video with audio
     * Performance: Uses stream copy for video (no re-encoding)
     * Auto-cleanup: Deletes temporary video and audio files
     */
    public function mergeVideoWithAudio(): void
    {
        $videoPath = storage_path('app/finals/final_video.mp4');
        $audioPath = storage_path('app/finals/merged_audio.mp3');
        $outputPath = storage_path('app/finals/final_video_with_audio.mp4');

        // Validate inputs
        if (!file_exists($videoPath) || !file_exists($audioPath)) {
            throw new \RuntimeException("Video or audio file not found for merging");
        }

        Log::info('Merging video with generated audio...');

        // Build FFmpeg command - use stream copy for video (much faster)
        $command = [
            'ffmpeg', '-y',
            '-i', $videoPath,
            '-i', $audioPath,
            '-c:v', 'copy', // No re-encoding (FAST!)
            '-c:a', 'aac',
            '-b:a', '128k',
            '-ar', '44100',
            '-shortest',
            $outputPath
        ];

        $this->executeFFmpegCommand($command);

        Log::info('Video and audio merged successfully');

        // Cleanup temporary files
        Log::info('Cleaning up temporary video and audio files...');
        @unlink($videoPath);
        @unlink($audioPath);
        Log::info('Temporary files deleted');
    }

    /**
     * Compress video to target size
     * IMPROVED: Better quality compression with CRF + maxrate approach
     */
    public function compressVideo(int $targetSizeMB): void
    {
        $inputPath = storage_path('app/finals/final_video_with_audio.mp4');
        $outputPath = storage_path('app/finals/final_video_with_audio_compressed.mp4');

        if (!file_exists($inputPath)) {
            throw new \RuntimeException("Input video not found for compression");
        }

        // Get video duration and current size
        $duration = $this->getVideoDuration($inputPath);
        $currentSizeMB = filesize($inputPath) / 1024 / 1024;

        // If already smaller than target, just copy
        if ($currentSizeMB <= $targetSizeMB) {
            Log::info("Video already smaller than target, copying", [
                'current_size' => round($currentSizeMB, 2) . 'MB',
                'target_size' => $targetSizeMB . 'MB'
            ]);
            copy($inputPath, $outputPath);
            @unlink($inputPath);
            return;
        }

        // Calculate bitrate with 10% buffer for safety
        $targetSizeBytes = $targetSizeMB * 1024 * 1024 * 0.9; // 90% of target
        $audioBitrate = 128; // 128k for better audio quality
        $audioSize = ($audioBitrate * 1000 * $duration) / 8;
        $videoSize = $targetSizeBytes - $audioSize;
        $videoBitrate = intval(($videoSize * 8) / $duration);

        // Use CRF mode with maxrate for better quality
        // CRF 20-23 = excellent quality, 24-28 = good quality
        $crf = 22; // Excellent quality

        Log::info("Compressing video", [
            'current_size' => round($currentSizeMB, 2) . 'MB',
            'target_size' => $targetSizeMB . 'MB',
            'video_bitrate' => round($videoBitrate / 1000) . 'k',
            'audio_bitrate' => $audioBitrate . 'k',
            'crf' => $crf
        ]);

        // Build FFmpeg command with CRF + maxrate (best quality/size balance)
        $command = [
            'ffmpeg', '-y',
            '-i', $inputPath,
            '-c:v', 'libx264',
            '-crf', (string)$crf,              // Quality-based encoding
            '-maxrate', "{$videoBitrate}",     // Don't exceed this bitrate
            '-bufsize', intval($videoBitrate * 2), // Buffer size
            '-preset', $this->ffmpegPreset,
            '-profile:v', 'high',              // High profile for better quality
            '-level', '4.1',                   // Compatibility level
            '-pix_fmt', 'yuv420p',             // Ensure compatibility
            '-movflags', '+faststart',         // Enable streaming
            '-c:a', 'aac',
            '-b:a', "{$audioBitrate}k",
            '-ar', '44100',                    // Audio sample rate
            $outputPath
        ];

        $this->executeFFmpegCommand($command);

        // Log final size
        $finalSizeMB = filesize($outputPath) / 1024 / 1024;
        Log::info("Compression complete", [
            'original_size' => round($currentSizeMB, 2) . 'MB',
            'final_size' => round($finalSizeMB, 2) . 'MB',
            'compression_ratio' => round(($currentSizeMB / $finalSizeMB), 2) . 'x'
        ]);

        // Cleanup uncompressed version
        @unlink($inputPath);
    }

    /**
     * Create repeated video using OPTIMIZED method
     * Performance: 10x faster than copying 120 files!
     * 
     * Instead of:
     * - Creating 120 physical copies (slow, disk intensive)
     * 
     * We:
     * - Create concat file with 120 references to ONE file
     * - Let FFmpeg handle repetition internally
     */
    public function createRepeatedVideoOptimized(int $count = 120): void
    {
        $sourcePath = storage_path('app/finals/final_video_with_audio_compressed.mp4');
        $outputPath = storage_path('app/outputs/finaloutpt123.mp4');

        if (!file_exists($sourcePath)) {
            throw new \RuntimeException("Source video not found for repetition");
        }

        // Create concat file that references the SAME file multiple times
        $listFile = storage_path('app/videos_repeat.txt');
        $fileContent = str_repeat("file '{$sourcePath}'\n", $count);
        file_put_contents($listFile, $fileContent);

        // Build FFmpeg command - concat with stream copy (VERY FAST!)
        $command = [
            'ffmpeg', '-y',
            '-f', 'concat',
            '-safe', '0',
            '-i', $listFile,
            '-c', 'copy', // Stream copy = no re-encoding!
            $outputPath
        ];

        $this->executeFFmpegCommand($command);

        // Cleanup
        @unlink($listFile);
        @unlink($sourcePath);
    }

    /**
     * OLD METHOD (kept for reference - DO NOT USE)
     * This is 10x slower! Creates 120 physical file copies
     */
    public function createRepeatedVideoSlow(int $count = 120): void
    {
        $sourcePath = storage_path('app/finals/final_video_with_audio_compressed.mp4');
        $copysDir = storage_path('app/copys');

        if (!file_exists($copysDir)) {
            mkdir($copysDir, 0777, true);
        }

        // SLOW: Copy file 120 times (disk intensive!)
        for ($i = 1; $i <= $count; $i++) {
            copy($sourcePath, "{$copysDir}/video_{$i}.mp4");
        }

        // Then concat all copies
        $videos = collect(File::files($copysDir))->shuffle()->values();
        $listFile = storage_path('app/videos_repeat.txt');
        $fileContent = $videos->map(fn($v) => "file '{$v->getPathname()}'\n")->implode('');
        file_put_contents($listFile, $fileContent);

        $command = [
            'ffmpeg', '-y',
            '-f', 'concat',
            '-safe', '0',
            '-i', $listFile,
            '-c', 'copy',
            storage_path('app/outputs/finaloutpt123.mp4')
        ];

        $this->executeFFmpegCommand($command);

        // Cleanup
        File::deleteDirectory($copysDir);
        @unlink($listFile);
    }

    /**
     * Get video duration using ffprobe
     */
    protected function getVideoDuration(string $videoPath): float
    {
        $command = [
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $videoPath
        ];

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::warning('Failed to get video duration, using fallback');
            return 60; // Fallback to 60 seconds
        }

        return floatval(trim($process->getOutput())) ?: 60;
    }

    /**
     * Execute FFmpeg command with proper error handling
     */
    protected function executeFFmpegCommand(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout($this->ffmpegTimeout);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            Log::error('FFmpeg command failed', [
                'command' => $process->getCommandLine(),
                'error' => $e->getMessage(),
                'output' => $process->getErrorOutput()
            ]);
            throw new \RuntimeException("FFmpeg processing failed: " . $e->getMessage());
        }
    }

    /**
     * Set FFmpeg preset (fast, medium, slow)
     */
    public function setPreset(string $preset): self
    {
        $this->ffmpegPreset = $preset;
        return $this;
    }

    /**
     * Set FFmpeg timeout
     */
    public function setTimeout(int $seconds): self
    {
        $this->ffmpegTimeout = $seconds;
        return $this;
    }

    /**
     * Set target video duration in hours
     */
    public function setTargetDuration(int $hours, int $varianceMinutes = 10): self
    {
        $this->targetDurationHours = $hours;
        $this->allowedVarianceMinutes = $varianceMinutes;
        return $this;
    }

    /**
     * Calculate number of copies needed to reach target duration
     * Target: 10 hours ±10 minutes
     */
    protected function calculateCopiesForTargetDuration(): int
    {
        $compressedVideoPath = storage_path('app/finals/final_video_with_audio_compressed.mp4');
        
        if (!file_exists($compressedVideoPath)) {
            Log::warning('Compressed video not found, using default 1200 copies for 10 hours');
            return 1200; // Default for 10 hours if base is ~30 seconds
        }

        // Get base video duration
        $baseDuration = $this->getVideoDuration($compressedVideoPath);
        
        // Calculate target duration in seconds
        $targetSeconds = $this->targetDurationHours * 3600; // 10 hours = 36,000 seconds
        
        // Calculate exact copies needed
        $exactCopies = $targetSeconds / $baseDuration;
        
        // Round to nearest integer
        $copies = (int) round($exactCopies);
        
        // Calculate actual duration
        $actualDurationSeconds = $copies * $baseDuration;
        $actualDurationHours = $actualDurationSeconds / 3600;
        $varianceMinutes = abs(($actualDurationSeconds - $targetSeconds) / 60);
        
        Log::info('Calculated video repetition', [
            'base_duration' => round($baseDuration, 2) . ' seconds',
            'target_duration' => $this->targetDurationHours . ' hours',
            'copies_needed' => $copies,
            'actual_duration' => round($actualDurationHours, 2) . ' hours',
            'variance' => round($varianceMinutes, 1) . ' minutes',
            'within_target' => $varianceMinutes <= $this->allowedVarianceMinutes ? 'YES ✓' : 'NO'
        ]);

        // Warn if variance is too high
        if ($varianceMinutes > $this->allowedVarianceMinutes) {
            Log::warning('Video duration variance exceeds allowed limit', [
                'allowed_variance' => $this->allowedVarianceMinutes . ' minutes',
                'actual_variance' => round($varianceMinutes, 1) . ' minutes'
            ]);
        }

        return $copies;
    }

}
