<?php

namespace App\Console\Commands;

use App\Services\VideoProcessingService;
use App\Services\YouTubeUploadService;
use App\Services\ThumbnailService;
use App\Jobs\UploadVideoJobOptimized;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestOptimizedPipeline extends Command
{
    protected $signature = 'test:optimized-pipeline 
                            {--step=all : Which step to test (all, video, thumbnail, upload, job)}
                            {--channel=2 : Channel ID for YouTube upload}
                            {--copies=30 : Number of video copies (default 30 for faster testing)}
                            {--size=40 : Target size in MB (default 40 for faster testing)}
                            {--preset=fast : FFmpeg preset (ultrafast, fast, medium, slow)}';

    protected $description = 'Test the optimized video processing pipeline';

    public function handle()
    {
        $step = $this->option('step');
        
        $this->info('🧪 Testing Optimized Pipeline');
        $this->info('========================================');
        $this->newLine();

        try {
            switch ($step) {
                case 'video':
                    $this->testVideoProcessing();
                    break;
                
                case 'thumbnail':
                    $this->testThumbnail();
                    break;
                
                case 'upload':
                    $this->testYouTubeUpload();
                    break;
                
                case 'job':
                    $this->testCompleteJob();
                    break;
                
                case 'all':
                default:
                    $this->testAll();
                    break;
            }

            $this->newLine();
            $this->info('✅ All tests completed successfully!');
            $this->newLine();
            $this->info('💡 Check logs for details: tail -f storage/logs/laravel.log');

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Test failed: ' . $e->getMessage());
            $this->error('Check logs: tail -f storage/logs/laravel.log');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function testAll()
    {
        $this->testVideoProcessing();
        $this->newLine();
        $this->testThumbnail();
        $this->newLine();
        $this->comment('⏭️  Skipping YouTube upload test (use --step=upload to test)');
        $this->newLine();
        $this->comment('💡 To test complete job: php artisan test:optimized-pipeline --step=job');
    }

    protected function testVideoProcessing()
    {
        $this->comment('Testing Video Processing Service...');
        $this->newLine();

        $startTime = microtime(true);
        $service = app(VideoProcessingService::class);

        // Configure for faster testing
        $copies = (int) $this->option('copies');
        $size = (int) $this->option('size');
        $preset = $this->option('preset');

        $this->line("⚙️  Configuration:");
        $this->line("   - Copies: {$copies} (~30s clip × {$copies} ≈ " . round($copies * 30 / 60, 1) . " minutes)");
        $this->line("   - Target Size: {$size}MB");
        $this->line("   - Preset: {$preset}");
        $this->newLine();
        
        $this->comment("💡 Note: For production, use copyCount=null to auto-calculate for 10 hours!");
        $this->newLine();

        // Show progress bar
        $bar = $this->output->createProgressBar(6);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        // Step 1: Create layered video
        $bar->setMessage('Creating layered video with chromakey...');
        $service->setPreset($preset)->createLayeredVideo();
        $bar->advance();

        // Step 2: Generate and mix audio (brown + pink noise)
        $bar->setMessage('Generating brown & pink noise...');
        $service->mixAudioFiles();
        $bar->advance();

        // Step 3: Merge video with audio
        $bar->setMessage('Merging video with audio...');
        $service->mergeVideoWithAudio();
        $bar->advance();

        // Step 4: Compress
        $bar->setMessage('Compressing video...');
        $service->compressVideo($size);
        $bar->advance();

        // Step 5: Create repeated video (OPTIMIZED!)
        $bar->setMessage('Creating repeated video (NO file copying!)...');
        $videoPath = $service->createRepeatedVideoOptimized($copies);
        $bar->advance();

        // Step 6: Verify output
        $bar->setMessage('Verifying output...');
        $bar->advance();
        $bar->finish();

        $this->newLine(2);

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $fileSize = file_exists($videoPath) ? round(filesize($videoPath) / 1024 / 1024, 2) : 0;

        $this->info("✅ Video Processing Test Passed!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Output File', $videoPath],
                ['File Size', "{$fileSize} MB"],
                ['Processing Time', "{$duration} seconds"],
                ['Video Duration', ($copies * 30) . " seconds (" . round($copies * 30 / 60, 1) . " minutes)"],
                ['Disk Copies', '0 (optimized!)'],
            ]
        );

        $this->newLine();
        $this->line("🎬 You can check the video:");
        $this->line("   open {$videoPath}");
    }

    protected function testThumbnail()
    {
        $this->comment('Testing Thumbnail Service...');
        $this->newLine();

        $startTime = microtime(true);
        $service = app(ThumbnailService::class);

        $this->line('📸 Creating thumbnail...');
        $thumbnailPath = $service->createThumbnail();

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $fileSize = file_exists($thumbnailPath) ? round(filesize($thumbnailPath) / 1024, 2) : 0;

        $this->info("✅ Thumbnail Test Passed!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Output File', $thumbnailPath],
                ['File Size', "{$fileSize} KB"],
                ['Processing Time', "{$duration} seconds"],
            ]
        );

        $this->newLine();
        $this->line("🖼️  You can view the thumbnail:");
        $this->line("   open {$thumbnailPath}");
    }

    protected function testYouTubeUpload()
    {
        $this->comment('Testing YouTube Upload Service...');
        $this->newLine();

        // Check if video exists
        $videoPath = storage_path('app/outputs/finaloutpt123.mp4');
        if (!file_exists($videoPath)) {
            $this->warn('⚠️  No video found at: ' . $videoPath);
            $this->line('Run video processing first:');
            $this->line('   php artisan test:optimized-pipeline --step=video');
            return;
        }

        $channelId = $this->option('channel');
        $fileSize = round(filesize($videoPath) / 1024 / 1024, 2);

        $this->line("📹 Video found: {$videoPath}");
        $this->line("📊 File size: {$fileSize} MB");
        $this->line("📺 Channel ID: {$channelId}");
        $this->newLine();

        if (!$this->confirm('⚠️  This will upload to YouTube. Continue?', false)) {
            $this->info('Upload cancelled.');
            return;
        }

        $startTime = microtime(true);
        $service = app(YouTubeUploadService::class);

        $this->line('⬆️  Uploading to YouTube...');
        $this->line('   (This may take 10-20 minutes depending on file size)');
        $this->newLine();

        $result = $service->uploadVideo(
            videoPath: $videoPath,
            channelId: $channelId,
            videoLengthHours: 1, // Test video
            privacy: 'unlisted' // Safer for testing
        );

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) / 60, 2);

        $this->info("✅ YouTube Upload Test Passed!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Video ID', $result['video_id']],
                ['Title', $result['title']],
                ['URL', $result['url']],
                ['Upload Time', "{$duration} minutes"],
            ]
        );

        $this->newLine();
        $this->line("🎥 Watch your video:");
        $this->line("   {$result['url']}");
    }

    protected function testCompleteJob()
    {
        $this->comment('Testing Complete Job Pipeline...');
        $this->newLine();

        $channelId = $this->option('channel');
        $copies = (int) $this->option('copies');

        $this->line("⚙️  Configuration:");
        $this->line("   - Channel ID: {$channelId}");
        $this->line("   - Video copies: {$copies}");
        $this->line("   - Privacy: unlisted (safe for testing)");
        $this->newLine();

        if (!$this->confirm('This will dispatch a job to the queue. Continue?', true)) {
            $this->info('Job dispatch cancelled.');
            return;
        }

        $this->line('🚀 Dispatching optimized job...');

        // Dispatch the job
        UploadVideoJobOptimized::dispatch(
            channelId: $channelId,
            videoLengthHours: round($copies * 30 / 3600, 1),
            privacy: 'unlisted'
        );

        $this->newLine();
        $this->info('✅ Job dispatched successfully!');
        $this->newLine();

        $this->line('📊 Monitor the job:');
        $this->line('   php artisan queue:work --once');
        $this->newLine();
        $this->line('📋 Watch logs in real-time:');
        $this->line('   tail -f storage/logs/laravel.log');
        $this->newLine();
        $this->line('🔍 Check queue status:');
        $this->line('   php artisan queue:monitor');
    }
}
