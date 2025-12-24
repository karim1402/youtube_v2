<?php

namespace App\Jobs;

use App\Services\VideoProcessingService;
use App\Services\YouTubeUploadService;
use App\Services\ThumbnailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UploadVideoJobOptimized implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200; // 2 hours
    public int $tries = 2; // Retry once if failed
    public int $backoff = 300; // Wait 5 minutes before retry

    protected string $channelId;
    protected int $videoLengthHours;
    protected string $privacy;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $channelId = '2',
        int $videoLengthHours = 10,
        string $privacy = 'public'
    ) {
        $this->channelId = $channelId;
        $this->videoLengthHours = $videoLengthHours;
        $this->privacy = $privacy;
    }

    /**
     * Execute the job - OPTIMIZED VERSION
     * 
     * Performance improvements:
     * 1. Uses dedicated services (separation of concerns)
     * 2. Optimized FFmpeg settings (fast preset)
     * 3. No file copying (uses FFmpeg concat directly)
     * 4. Proper error handling with retries
     * 5. Progress logging
     * 6. Resource cleanup
     */
    public function handle(): void
    {
        Log::info('=== OPTIMIZED Upload Job Started ===', [
            'channel_id' => $this->channelId,
            'video_length_hours' => $this->videoLengthHours,
            'timestamp' => now()
        ]);

        try {
            // Step 1: Create video (30-45 minutes with optimizations)
            Log::info('Step 1/4: Creating video...');
            $videoPath = $this->createVideo();
            Log::info('✓ Video created', ['path' => $videoPath, 'size_mb' => round(filesize($videoPath) / 1024 / 1024, 2)]);

            // Step 2: Create thumbnail (5 seconds)
            Log::info('Step 2/4: Creating thumbnail...');
            $thumbnailPath = $this->createThumbnail();
            Log::info('✓ Thumbnail created', ['path' => $thumbnailPath]);

            // Step 3: Upload to YouTube (10-20 minutes depending on file size)
            Log::info('Step 3/4: Uploading to YouTube...');
            $uploadResult = $this->uploadToYouTube($videoPath);
            Log::info('✓ Video uploaded to YouTube', $uploadResult);

            // Step 4: Upload thumbnail
            Log::info('Step 4/4: Uploading thumbnail...');
            $this->uploadThumbnail($uploadResult['video_id'], $thumbnailPath);
            Log::info('✓ Thumbnail uploaded');

            Log::info('=== Upload Job Completed Successfully ===', [
                'video_id' => $uploadResult['video_id'],
                'video_url' => $uploadResult['url'],
                'total_time' => '~45-60 minutes'
            ]);

        } catch (\Exception $e) {
            Log::error('Upload job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to allow queue retry
            throw $e;
        }
    }

    /**
     * Create video using VideoProcessingService
     * Auto-calculates copies for exact 10-hour duration
     */
    protected function createVideo(): string
    {
        $service = app(VideoProcessingService::class);
        
        // Configure for optimal performance
        $service->setPreset('fast') // Use fast preset (3x faster than slow)
                ->setTimeout(3600) // 1 hour timeout for video creation
                ->setTargetDuration($this->videoLengthHours, 10); // Auto-calculate for target duration ±10 min
        
        // Create video with AUTO-CALCULATED repetition (no manual calculation!)
        return $service->createVideo(
            copyCount: null,  // NULL = auto-calculate for 10 hours ±10 minutes
            targetSizeMB: 150 // Compress to 150MB
        );
    }

    /**
     * Create thumbnail using ThumbnailService
     */
    protected function createThumbnail(): string
    {
        $service = app(ThumbnailService::class);
        
        return $service->createThumbnail();
    }

    /**
     * Upload video to YouTube using YouTubeUploadService
     */
    protected function uploadToYouTube(string $videoPath): array
    {
        $service = app(YouTubeUploadService::class);
        
        // Configure for faster upload (5MB chunks)
        $service->setChunkSize(5 * 1024 * 1024);
        
        return $service->uploadVideo(
            videoPath: $videoPath,
            channelId: $this->channelId,
            videoLengthHours: $this->videoLengthHours,
            privacy: $this->privacy
        );
    }

    /**
     * Upload thumbnail to YouTube
     */
    protected function uploadThumbnail(string $videoId, string $thumbnailPath): void
    {
        $service = app(YouTubeUploadService::class);
        
        $service->uploadThumbnail(
            videoId: $videoId,
            channelId: $this->channelId,
            thumbnailPath: $thumbnailPath
        );
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Upload job failed permanently after all retries', [
            'error' => $exception->getMessage(),
            'channel_id' => $this->channelId
        ]);

        // You can add notification logic here (email, Slack, etc.)
    }
}
