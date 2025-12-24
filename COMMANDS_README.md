# Console Commands Documentation (Cron Job "Fetchers")

## 📋 Overview

These Laravel Artisan commands are the "fetchers" that run as scheduled cron jobs on the server. They orchestrate the entire video creation and upload pipeline, from asset selection to YouTube publishing.

## 🎯 Purpose

The commands are designed to:
- Run automatically via server cron (1-2 times daily)
- Process videos in the background using Laravel queues
- Handle all video editing, compression, and upload tasks
- Work independently without manual intervention

---

## 📦 Available Commands

### 1. `app:uplode-command` (Main Upload Command)

**Location**: `app/Console/Commands/uplodeCommand.php`

**Purpose**: The primary command for automated video creation and YouTube upload. This is the main "fetcher" that should run on your cron schedule.

#### What It Does

1. Dispatches `UploadVideoJob` to the queue
2. Starts a queue worker to process the job
3. Waits for the job to complete (stops when empty)
4. Handles the entire video creation pipeline

#### Command Signature

```bash
php artisan app:uplode-command
```

#### Internal Process

```php
public function handle()
{
    // Step 1: Dispatch the video upload job to queue
    \App\Jobs\UploadVideoJob::dispatch();
    
    // Step 2: Start queue worker and process the job
    \Artisan::call('queue:work', [
        '--queue' => 'high,default',
        '--stop-when-empty' => true, // Stops after all jobs done
    ]);
    
    $this->info('UploadVideoJob dispatched successfully.');
}
```

#### Job Workflow (Executed by UploadVideoJob)

1. **Video Composition** (`full_video_fast()`)
   - Randomly selects assets (background, effects, baby, soundbar, sleep effect)
   - Layers videos using FFmpeg chromakey (green screen removal)
   - Creates base video with all overlays
   - Output: `storage/app/finals/final_video.mp4`

2. **Audio Mixing** (`mergeTwoAudioFiles()`)
   - Randomly selects 2 audio files from 6 available
   - Mixes them using FFmpeg `amix` filter
   - Output: `storage/app/finals/merged_audio.mp3`

3. **Audio Merge** (`mergeFinalVideoWithAudio()`)
   - Combines video with mixed audio
   - Uses AAC codec for audio
   - Output: `storage/app/finals/final_video_with_audio.mp4`

4. **Video Compression** (`compressFinalVideoWithAudio(150)`)
   - Calculates bitrate to achieve 150MB target size
   - Compresses using H.264 codec
   - Output: `storage/app/finals/final_video_with_audio_compressed.mp4`

5. **Video Repetition** (`copyVideoMultipleTimes(120)`)
   - Copies compressed video 120 times
   - Creates directory: `storage/app/copys/`
   - Output: 120 identical MP4 files

6. **Final Concatenation** (`mergeSameVideoMultipleTimes()`)
   - Randomly shuffles 120 copies
   - Concatenates them into 10-hour video
   - Cleans up copy directory
   - Output: `storage/app/outputs/finaloutpt123.mp4`

#### Expected Duration

- **Total Runtime**: 30-60 minutes depending on server performance
- **FFmpeg Processing**: 20-40 minutes
- **File Operations**: 5-10 minutes
- **Queue Overhead**: 1-2 minutes

#### Cron Setup Examples

**Run once daily at 2 AM:**
```cron
0 2 * * * cd /var/www/youtube-project && php artisan app:uplode-command >> /var/log/youtube-upload.log 2>&1
```

**Run twice daily (2 AM and 2 PM):**
```cron
0 2,14 * * * cd /var/www/youtube-project && php artisan app:uplode-command >> /var/log/youtube-upload.log 2>&1
```

**Run every 12 hours:**
```cron
0 */12 * * * cd /var/www/youtube-project && php artisan app:uplode-command >> /var/log/youtube-upload.log 2>&1
```

#### Error Handling

The command includes automatic error logging:
```php
date_default_timezone_set('Africa/Cairo');
Log::info('UploadVideoJob started at ' . date('Y-m-d H:i:s'));
```

Check logs:
```bash
tail -f storage/logs/laravel.log
```

---

### 2. `app:uplode-pure-command` (Alternative Upload Command)

**Location**: `app/Console/Commands/uplodepurecommand.php`

**Purpose**: Reserved for future use or alternative upload pipeline. Currently commented out.

#### Command Signature

```bash
php artisan app:uplode-pure-command
```

#### Status

⚠️ **Currently Disabled**: The `handle()` method is commented out. This command is reserved for:
- Testing new upload workflows
- Alternative video processing pipelines
- A/B testing different video creation strategies

#### Code Structure

```php
// public function handle()
// {
//     \App\Jobs\UploadVideoJob::dispatch();
//     \Artisan::call('queue:work', [
//         '--queue' => 'high,default',
//         '--stop-when-empty' => true,
//     ]);
//     $this->info('UploadVideoJob dispatched successfully.');
// }
```

#### Activation

To enable, uncomment the `handle()` method and modify as needed for your use case.

---

### 3. `video:generate` (Shuffle Video Generator)

**Location**: `app/Console/Commands/GenerateShuffledVideo.php`

**Purpose**: Generates a merged video from random clips. This is a standalone utility for testing video concatenation without the full upload pipeline.

#### What It Does

1. Scans `storage/app/clips/` for video files
2. Randomly selects 10 videos
3. Shuffles them
4. Concatenates into one video using FFmpeg
5. Outputs to `storage/app/outputs/merged.mp4`

#### Command Signature

```bash
php artisan video:generate
```

#### Detailed Process

```php
public function handle()
{
    // Step 1: Get all videos from clips folder
    $clipsPath = storage_path('app/clips');
    $videos = collect(File::files($clipsPath))
        ->filter(function ($file) {
            return in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'avi']);
        })
        ->shuffle()
        ->take(10) // Take 10 random videos
        ->values();
    
    // Step 2: Create FFmpeg concat file
    $listFile = storage_path('app/clips/videos.txt');
    $fileListContent = '';
    foreach ($videos as $video) {
        $fileListContent .= "file '" . $video->getPathname() . "'\n";
    }
    file_put_contents($listFile, $fileListContent);
    
    // Step 3: Run FFmpeg concat command
    $ffmpegCmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) 
                 . " -c copy " . escapeshellarg($outputPath) . " -y";
    exec($ffmpegCmd, $output, $returnVar);
    
    if ($returnVar === 0) {
        $this->info('Videos merged successfully: ' . $outputPath);
    } else {
        $this->error('Failed to merge videos.');
    }
}
```

#### Use Cases

- **Testing**: Verify FFmpeg is working correctly
- **Asset Validation**: Ensure clips are properly formatted
- **Quick Preview**: Generate sample merged videos
- **Debugging**: Test concatenation without full pipeline

#### Output

- **File**: `storage/app/outputs/merged.mp4`
- **Duration**: Varies (10 clips × average clip length)
- **Codec**: Same as input (uses `-c copy` for fast processing)

#### Example Usage

```bash
# Generate test video
php artisan video:generate

# Check output
ls -lh storage/app/outputs/merged.mp4

# Play or analyze
ffprobe storage/app/outputs/merged.mp4
```

---

## 🔧 Command Configuration

### Queue Settings

All commands use Laravel's queue system. Configure in `.env`:

```env
QUEUE_CONNECTION=database
```

Or use Redis for better performance:

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Timeout Configuration

For long-running video processing:

```php
public $timeout = 36000; // 10 hours in UploadVideoJob
```

In queue worker:
```bash
php artisan queue:work --timeout=3600
```

---

## 📊 Asset Requirements

### Directory Structure

Ensure these directories exist with media files:

```
storage/app/
├── backgrounds/        # 11 background videos (1.mp4 - 11.mp4)
├── effects/           # 8 effect overlays (1.mp4 - 8.mp4)
├── soundbars/         # 8 audio visualizers (1.mp4 - 8.mp4)
├── baby_greenscreen/  # 6 baby animations (1.mp4 - 6.mp4)
├── sleep_effects/     # 1 sleep effect (1.mp4)
├── audio/             # 6 audio tracks (1.mp3 - 6.mp3)
└── logo/              # Channel logo (file.png)
```

### Video Requirements

- **Format**: MP4 (H.264 video, AAC audio)
- **Resolution**: 1920x1080 (Full HD) recommended
- **Green Screen**: Effects must use #00FF00 green for chromakey
- **Duration**: All videos should be same length (e.g., 30 seconds)

### Audio Requirements

- **Format**: MP3
- **Sample Rate**: 44100 Hz
- **Bitrate**: 128-320 kbps
- **Duration**: 5-10 minutes recommended (will be looped)

---

## 🐛 Troubleshooting

### Command Not Found

```bash
php artisan list | grep uplode
php artisan list | grep video
```

If missing, run:
```bash
composer dump-autoload
php artisan clear-compiled
php artisan optimize
```

### Queue Not Processing

```bash
# Check queue table
php artisan queue:monitor

# Restart queue
php artisan queue:restart

# Process manually
php artisan queue:work --once
```

### FFmpeg Errors

```bash
# Test FFmpeg
ffmpeg -version

# Check paths in commands
which ffmpeg

# Test concat
ffmpeg -f concat -safe 0 -i test.txt -c copy output.mp4
```

### Permission Errors

```bash
chmod -R 775 storage/app
chown -R www-data:www-data storage/app
```

---

## 📈 Performance Optimization

### 1. Use Supervisor (Recommended)

Keep queue worker running permanently:

```ini
[program:youtube-queue]
command=php /path/to/project/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
```

### 2. Multiple Workers

For parallel processing:

```bash
php artisan queue:work --queue=high &
php artisan queue:work --queue=default &
```

### 3. FFmpeg Optimization

Use faster presets in production:

```php
// In GeminiHelper or Jobs, change:
-preset slow  // High quality, slow
-preset fast  // Faster, good quality
-preset ultrafast  // Very fast, lower quality
```

### 4. Cron Overlap Prevention

Prevent multiple instances:

```cron
0 2 * * * flock -n /tmp/youtube-upload.lock php artisan app:uplode-command
```

---

## 📝 Logging & Monitoring

### Enable Detailed Logging

In commands or jobs:

```php
Log::info('Step 1: Starting video composition');
Log::info('Selected background: ' . $back);
Log::info('Selected baby: ' . $baby_number);
```

### Monitor Execution

```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log

# Check cron execution
tail -f /var/log/youtube-upload.log

# Check queue status
php artisan queue:monitor
```

### Send Notifications

Add to commands:

```php
if ($returnVar === 0) {
    $this->info('✅ Video created successfully');
    // Send email or Slack notification
} else {
    $this->error('❌ Video creation failed');
    // Alert admin
}
```

---

## 🚀 Advanced Usage

### Custom Asset Selection

Modify random selection logic:

```php
// Instead of random
$back = rand(1, 11);

// Use specific sequence
static $counter = 0;
$back = ($counter++ % 11) + 1;

// Use timestamp-based
$back = (date('j') % 11) + 1;
```

### Multiple Video Formats

Add support for different outputs:

```php
// Create both HD and SD versions
$this->createVideo('hd', 1920, 1080);
$this->createVideo('sd', 1280, 720);
```

### Scheduled Variety

Different content for different times:

```php
$hour = date('G');
if ($hour >= 0 && $hour < 12) {
    $theme = 'morning'; // Bright backgrounds
} else {
    $theme = 'night'; // Dark backgrounds
}
```

---

## 🎓 Creating New Commands

To add a new "fetcher" command:

```bash
php artisan make:command YourNewCommand
```

Template:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class YourNewCommand extends Command
{
    protected $signature = 'app:your-new-command';
    protected $description = 'Description of what this command does';

    public function handle()
    {
        $this->info('Starting process...');
        
        // Your logic here
        
        $this->info('Process completed!');
        return Command::SUCCESS;
    }
}
```

Add to cron:
```cron
0 3 * * * cd /path/to/project && php artisan app:your-new-command
```

---

## 📞 Support

For command-specific issues:

1. Check command exists: `php artisan list`
2. Check logs: `tail -f storage/logs/laravel.log`
3. Test manually: `php artisan app:uplode-command`
4. Verify queue: `php artisan queue:work --once`
5. Check cron: `grep CRON /var/log/syslog`

---

## 📚 Related Documentation

- Main README: `PROJECT_README.md`
- Jobs Documentation: `JOBS_README.md`
- Services Documentation: `SERVICES_README.md`
- API Documentation: `API_README.md`
