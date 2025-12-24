# Background Jobs Documentation

## 📋 Overview

Laravel Queue Jobs handle the heavy video processing and YouTube upload tasks in the background. These jobs are dispatched by commands and processed asynchronously to avoid blocking the main application.

## 🎯 Purpose

Jobs are designed to:
- Process long-running video editing tasks (20-60 minutes)
- Handle YouTube API uploads with chunked file transfer
- Manage OAuth token refresh automatically
- Work with Laravel's queue system for reliability
- Log detailed execution information

---

## 📦 Available Jobs

### 1. `UploadVideoJob` (Complete Video Pipeline)

**Location**: `app/Jobs/UploadVideoJob.php`

**Purpose**: Handles the complete video creation pipeline from asset composition to final concatenation. This is the main processing job dispatched by `uplodeCommand`.

#### Job Properties

```php
class UploadVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $timeout = 36000; // 10 hours maximum execution time
}
```

#### Execution Flow

```
handle() 
  ↓
full_video_fast()                    → Create layered video with effects
  ↓
mergeTwoAudioFiles()                 → Mix 2 random audio tracks
  ↓
mergeFinalVideoWithAudio()           → Combine video + audio
  ↓
compressFinalVideoWithAudio(150)     → Compress to 150MB target
  ↓
copyVideoMultipleTimes(120)          → Create 120 copies
  ↓
mergeSameVideoMultipleTimes()        → Concatenate into 10-hour video
```

#### Method Details

##### `handle()`

Main entry point, sets timezone and orchestrates the pipeline:

```php
public function handle()
{
    date_default_timezone_set('Africa/Cairo');
    Log::info('UploadVideoJob started at ' . date('Y-m-d H:i:s'));
    
    $this->full_video_fast();
    $this->mergeTwoAudioFiles();
    $final_video_with_audio = $this->mergeFinalVideoWithAudio();
    $this->compressFinalVideoWithAudio(150);
    $this->copyVideoMultipleTimes(120);
    $final_repeated_video = $this->mergeSameVideoMultipleTimes();
}
```

##### `full_video_fast()`

Creates the base video by layering multiple videos with chromakey:

```php
public function full_video_fast()
{
    // Randomly select assets
    $back = rand(1, 11);           // Background video
    $effict_number = rand(1, 8);   // Effect overlay
    $sound_bar_number = rand(1, 8); // Audio visualizer
    $baby_number = rand(1, 6);      // Baby animation
    $sleep = 1;                     // Sleep effect (fixed)
    
    // Build file paths
    $background = storage_path("app/backgrounds/$back.mp4");
    $effect = storage_path("app/effects/$effict_number.mp4");
    $soundbar = storage_path("app/soundbars/$sound_bar_number.mp4");
    $baby = storage_path("app/baby_greenscreen/$baby_number.mp4");
    $sleep = storage_path('app/sleep_effects/1.mp4');
    
    // FFmpeg chromakey filter chain
    $filter = "[1:v]chromakey=0x00FF00:0.2:0.1[eff];"
            . "[0:v][eff]overlay[bg_eff];"
            . "[2:v]chromakey=0x00FF00:0.2:0.1[sb];"
            . "[bg_eff][sb]overlay[sb_eff];"
            . "[3:v]chromakey=0x00FF00:0.2:0.1[baby];"
            . "[sb_eff][baby]overlay[baby_eff];"
            . "[4:v]chromakey=0x00FF00:0.2:0.1[sleep];"
            . "[baby_eff][sleep]overlay[out]";
    
    // Execute FFmpeg
    $cmd = "ffmpeg -i $background -i $effect -i $soundbar -i $baby -i $sleep "
         . "-filter_complex '$filter' "
         . "-map [out] -c:v libx264 -crf 18 -preset slow -y $output";
    
    $outputLog = shell_exec($cmd);
}
```

**Output**: `storage/app/finals/final_video.mp4` (30-60 seconds, ~50MB)

**Chromakey Explanation**:
- `0x00FF00`: Pure green color (#00FF00)
- `0.2`: Similarity threshold (0.0-1.0)
- `0.1`: Blend value for edge smoothing

##### `mergeTwoAudioFiles()`

Mixes two random audio tracks:

```php
public function mergeTwoAudioFiles()
{
    // Select 2 different random audio files
    $random = rand(1, 6);
    $random2 = rand(1, 6);
    while ($random2 == $random) {
        $random2 = rand(1, 6);
    }
    
    $audio1 = storage_path("app/audio/$random.mp3");
    $audio2 = storage_path("app/audio/$random2.mp3");
    
    // Mix using amix filter
    $ffmpegCmd = "ffmpeg -y -i \"$audio1\" -i \"$audio2\" "
               . "-filter_complex \"[0:0][1:0]amix=inputs=2:duration=longest:dropout_transition=2\" "
               . "-c:a libmp3lame \"$outputPath\"";
    
    exec($ffmpegCmd, $output, $resultCode);
}
```

**Output**: `storage/app/finals/merged_audio.mp3` (~10MB)

**Audio Mix Parameters**:
- `inputs=2`: Mix 2 audio streams
- `duration=longest`: Use longest audio duration
- `dropout_transition=2`: 2-second fade when audio ends

##### `mergeFinalVideoWithAudio()`

Combines video with mixed audio:

```php
public function mergeFinalVideoWithAudio()
{
    $videoPath = storage_path('app/finals/final_video.mp4');
    $audioPath = storage_path('app/finals/merged_audio.mp3');
    $outputPath = storage_path('app/finals/final_video_with_audio.mp4');
    
    $cmd = "ffmpeg -y -i \"$videoPath\" -i \"$audioPath\" "
         . "-c:v copy -c:a aac -shortest \"$outputPath\"";
    
    exec($cmd, $output, $resultCode);
}
```

**Output**: `storage/app/finals/final_video_with_audio.mp4` (~60MB)

**Parameters**:
- `-c:v copy`: Copy video stream (no re-encoding)
- `-c:a aac`: Encode audio to AAC
- `-shortest`: Match shortest stream duration

##### `compressFinalVideoWithAudio($targetSizeMB = 150)`

Compresses video to target file size:

```php
public function compressFinalVideoWithAudio($targetSizeMB = 150)
{
    // Get video duration
    $durationCmd = "ffprobe -v error -show_entries format=duration "
                 . "-of default=noprint_wrappers=1:nokey=1 \"$inputPath\"";
    $duration = floatval(trim(shell_exec($durationCmd)));
    
    // Calculate bitrate for target size
    $targetSizeBytes = $targetSizeMB * 1024 * 1024;
    $bitrate = intval(($targetSizeBytes * 8) / $duration);
    
    // Compress with calculated bitrate
    $cmd = "ffmpeg -y -i \"$inputPath\" "
         . "-b:v {$bitrate} -maxrate {$bitrate} -bufsize " . intval($bitrate / 2)
         . " -c:v libx264 -c:a aac -preset fast \"$outputPath\"";
    
    exec($cmd, $output, $resultCode);
}
```

**Output**: `storage/app/finals/final_video_with_audio_compressed.mp4` (~150MB)

**Bitrate Calculation**:
```
bitrate (bps) = (target_size_bytes × 8) ÷ duration_seconds
```

##### `copyVideoMultipleTimes($count = 120)`

Creates multiple copies for concatenation:

```php
public function copyVideoMultipleTimes($count = 120)
{
    $outputPath = storage_path('app/finals/final_video_with_audio_compressed.mp4');
    $videoPath = storage_path('app/copys');
    
    for ($i = 1; $i <= $count; $i++) {
        $copyPath = $videoPath . '/final_video_with_audio_' . $i . '.mp4';
        copy($outputPath, $copyPath);
    }
}
```

**Output**: `storage/app/copys/` containing 120 identical MP4 files

**Why 120 copies?**
- 30-second video × 120 = 3600 seconds = 1 hour
- For 10-hour video: 30s × 1200 copies
- 120 provides flexibility for shuffling

##### `mergeSameVideoMultipleTimes()`

Concatenates shuffled copies into final 10-hour video:

```php
public function mergeSameVideoMultipleTimes()
{
    $videoPath = storage_path('app/copys');
    $outputPath = storage_path('app/outputs/finaloutpt123.mp4');
    
    // Get all copies and shuffle
    $videos = collect(File::files($videoPath))
        ->filter(function ($file) {
            return in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'avi']);
        })
        ->shuffle()
        ->take(120)
        ->values();
    
    // Create concat list file
    $listFile = storage_path('app/videos_repeat.txt');
    $fileListContent = '';
    foreach ($videos as $video) {
        $fileListContent .= "file '" . $video->getPathname() . "'\n";
    }
    file_put_contents($listFile, $fileListContent);
    
    // Concatenate
    $ffmpegCmd = "ffmpeg -f concat -safe 0 -i \"$listFile\" -c copy \"$outputPath\" -y";
    exec($ffmpegCmd, $output, $returnVar);
    
    // Clean up copies
    $files = File::files($videoPath);
    foreach ($files as $file) {
        File::delete($file);
    }
}
```

**Output**: `storage/app/outputs/finaloutpt123.mp4` (~150MB for 10 hours)

**Shuffling Benefits**:
- Prevents repetitive patterns
- Each 10-hour video has different sequence
- More engaging for viewers

---

### 2. `UploadVideoPureJob` (YouTube Upload Job)

**Location**: `app/Jobs/UploadVideoPureJob.php`

**Purpose**: Handles YouTube video upload with AI-generated metadata and thumbnail. This job focuses on the upload pipeline rather than video creation.

#### Job Properties

```php
class UploadVideoPureJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $timeout = 3600; // 1 hour maximum execution time
}
```

#### Execution Flow

```
handle()
  ↓
GeminiHelper::mergeClips()           → Generate video from clips
  ↓
GeminiHelper::overlayImages()        → Create thumbnail
  ↓
refresh_access_token()               → Ensure valid YouTube token
  ↓
GeminiHelper::base()                 → Generate title (AI)
  ↓
GeminiHelper::base()                 → Generate description (AI)
  ↓
YouTube API upload                   → Chunked video upload
  ↓
uploadThumbnail()                    → Upload custom thumbnail
```

#### Method Details

##### `handle()`

Main entry point for YouTube upload:

```php
public function handle()
{
    Log::info('job start');
    
    // Generate video from clips
    $video_hours_length = GeminiHelper::mergeClips();
    
    // Create thumbnail
    GeminiHelper::overlayImages();
    
    // Setup YouTube client
    $channelId = '2';
    $this->client = new Client();
    $this->refresh_access_token($channelId);
    
    $this->client->setAuthConfig(storage_path('app/google_credentials.json'));
    $this->client->addScope(YouTube::YOUTUBE_UPLOAD);
    
    // Load access token from database
    $accessTokenModel = access_token::where('channel_id', $channelId)->first();
    $this->client->setAccessToken([
        'access_token' => $accessTokenModel->access_token,
        'refresh_token' => $accessTokenModel->refresh_token,
        'expires_in' => $accessTokenModel->expires_at,
    ]);
    
    $youtube = new YouTube($this->client);
    
    // Generate AI title
    $title = GeminiHelper::base($titlePrompt);
    
    // Generate AI description
    $description = GeminiHelper::base($descriptionPrompt);
    
    // Create video metadata
    $snippet = new YouTube\VideoSnippet();
    $snippet->setTitle($title);
    $snippet->setDescription($description);
    $snippet->setTags([...]);
    $snippet->setCategoryId(24); // Entertainment
    
    // Set privacy status
    $status = new YouTube\VideoStatus();
    $status->setPrivacyStatus('unlisted');
    
    // Create video object
    $video = new YouTube\Video();
    $video->setSnippet($snippet);
    $video->setStatus($status);
    
    // Upload with chunked transfer
    $chunkSizeBytes = 1 * 1024 * 1024; // 1MB chunks
    $this->client->setDefer(true);
    $insertRequest = $youtube->videos->insert('snippet,status', $video);
    
    $media = new \Google\Http\MediaFileUpload(
        $this->client,
        $insertRequest,
        'video/*',
        null,
        true,
        $chunkSizeBytes
    );
    $media->setFileSize(filesize($videoPath));
    
    // Upload in chunks
    $status = false;
    $handle = fopen($videoPath, 'rb');
    while (!$status && !feof($handle)) {
        $chunk = fread($handle, $chunkSizeBytes);
        $status = $media->nextChunk($chunk);
    }
    fclose($handle);
    
    $this->client->setDefer(false);
    
    // Upload thumbnail
    $this->uploadThumbnail($status['id']);
}
```

##### AI Prompts

**Title Generation**:

```php
$titlePrompt = "Write one unique YouTube video title for a white noise video designed to help babies sleep. "
             . "The title must be under 100 characters, fully optimized for YouTube SEO. "
             . "Use emotionally driven language (e.g., soothe, calm, peaceful, magic sound). "
             . "The video length is $video_hours_length hours. "
             . "Return only the title — no commentary or explanation.";
```

**Description Generation**:

```php
$descriptionPrompt = "Write a full YouTube video description for a white noise video made for babies. "
                   . "The description should be 150–300 words long, written in natural English. "
                   . "Include keywords: white noise for babies, baby sleep sounds, colic relief, etc. "
                   . "Explain benefits of white noise, how to use during naps/nighttime. "
                   . "Highlight 10 hours continuous playback without interruptions. "
                   . "Do not include emojis, timestamps, hashtags, or links.";
```

##### `uploadThumbnail($videoId)`

Uploads custom thumbnail to YouTube:

```php
public function uploadThumbnail($id)
{
    $accessTokenModel = access_token::where('channel_id', '2')->first();
    $this->client->setAccessToken([...]);
    
    $youtube = new YouTube($this->client);
    $thumbnailPath = storage_path('app/public/merged_image.png');
    
    $response = $youtube->thumbnails->set($id, [
        'data' => file_get_contents($thumbnailPath),
        'mimeType' => 'image/jpeg',
        'uploadType' => 'multipart',
    ]);
    
    return response()->json([
        'message' => 'Thumbnail uploaded successfully',
        'response' => $response
    ]);
}
```

##### `refresh_access_token($channelId)`

Refreshes YouTube OAuth token if expired:

```php
public function refresh_access_token($channelId)
{
    $accessTokenModel = access_token::where('channel_id', $channelId)->first();
    
    $this->client->setAccessToken([
        'access_token' => $accessTokenModel->access_token,
        'refresh_token' => $accessTokenModel->refresh_token,
    ]);
    
    if ($this->client->isAccessTokenExpired()) {
        // Refresh the token
        $this->client->fetchAccessTokenWithRefreshToken($accessTokenModel->refresh_token);
        $newAccessToken = $this->client->getAccessToken();
        
        // Update database
        $accessTokenModel->access_token = $newAccessToken['access_token'];
        $accessTokenModel->refresh_token = $newAccessToken['refresh_token'];
        $accessTokenModel->expires_at = $newAccessToken['expires_in'];
        $accessTokenModel->save();
    }
}
```

---

### 3. `StreamToYouTubeJob` (Live Streaming Job)

**Location**: `app/Jobs/StreamToYouTubeJob.php`

**Purpose**: Reserved for future YouTube Live streaming functionality. Currently not fully implemented.

**Status**: ⚠️ **In Development**

---

## ⚙️ Queue Configuration

### Database Queue Driver

Configure in `.env`:

```env
QUEUE_CONNECTION=database
```

Run migrations:

```bash
php artisan queue:table
php artisan migrate
```

### Redis Queue Driver (Recommended for Production)

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
```

Install Redis:

```bash
# Ubuntu
sudo apt install redis-server

# macOS
brew install redis
brew services start redis
```

### Queue Worker Commands

```bash
# Process jobs once
php artisan queue:work --once

# Process until empty
php artisan queue:work --stop-when-empty

# Process specific queue
php artisan queue:work --queue=high,default

# With timeout
php artisan queue:work --timeout=3600

# With memory limit
php artisan queue:work --memory=512

# With retries
php artisan queue:work --tries=3
```

### Supervisor Configuration

Keep queue worker running 24/7:

```ini
[program:youtube-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/youtube-project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/youtube-queue.log
stopwaitsecs=3600
```

Start supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start youtube-queue-worker:*
```

---

## 📊 Job Monitoring

### Queue Status

```bash
# Monitor queues
php artisan queue:monitor

# Check failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all

# Forget failed job
php artisan queue:forget <job-id>

# Flush all failed jobs
php artisan queue:flush
```

### Job Metrics

```bash
# Check queue size
php artisan queue:monitor database:default,database:high

# View jobs table
mysql -u user -p
USE youtube_video;
SELECT * FROM jobs;
SELECT * FROM failed_jobs;
```

### Horizon (Advanced Monitoring)

Install Laravel Horizon for Redis queues:

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

Access dashboard: `http://your-domain.com/horizon`

---

## 🐛 Troubleshooting

### Job Timeout

If job exceeds timeout:

```php
// Increase in job class
public $timeout = 7200; // 2 hours

// Or in queue worker
php artisan queue:work --timeout=7200
```

### Memory Exhaustion

```php
// Increase PHP memory
php -d memory_limit=1G artisan queue:work

// Or in php.ini
memory_limit = 1024M
```

### Job Stuck

```bash
# Kill stuck jobs
php artisan queue:restart

# Clear queue
php artisan queue:clear

# Check process
ps aux | grep queue:work
kill -9 <pid>
```

### Failed Jobs

Check `failed_jobs` table:

```sql
SELECT * FROM failed_jobs ORDER BY failed_at DESC LIMIT 10;
```

View exception details:

```php
$failedJob = DB::table('failed_jobs')->first();
echo $failedJob->exception;
```

---

## 📈 Performance Optimization

### 1. Chunk Size Optimization

```php
// Smaller chunks for slow connections
$chunkSizeBytes = 256 * 1024; // 256KB

// Larger chunks for fast connections
$chunkSizeBytes = 5 * 1024 * 1024; // 5MB
```

### 2. FFmpeg Optimization

```php
// Use hardware acceleration
-hwaccel auto

// Use faster preset
-preset ultrafast

// Reduce CRF for faster encoding
-crf 28  // Lower quality but faster
```

### 3. Parallel Processing

Dispatch multiple jobs:

```php
// Process different channels in parallel
UploadVideoPureJob::dispatch()->onQueue('channel-1');
UploadVideoPureJob::dispatch()->onQueue('channel-2');

// Run multiple workers
php artisan queue:work --queue=channel-1 &
php artisan queue:work --queue=channel-2 &
```

### 4. Job Batching

```php
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

Bus::batch([
    new UploadVideoJob(),
    new UploadVideoPureJob(),
])->dispatch();
```

---

## 📝 Best Practices

### 1. Logging

```php
use Illuminate\Support\Facades\Log;

Log::info('Job started', ['job_id' => $this->job->getJobId()]);
Log::error('Job failed', ['error' => $e->getMessage()]);
```

### 2. Exception Handling

```php
public function handle()
{
    try {
        // Job logic
    } catch (\Exception $e) {
        Log::error('Job exception: ' . $e->getMessage());
        $this->fail($e);
    }
}
```

### 3. Progress Tracking

```php
public function handle()
{
    $steps = 6;
    $current = 0;
    
    Log::info("Progress: " . (++$current) . "/$steps - Video composition");
    $this->full_video_fast();
    
    Log::info("Progress: " . (++$current) . "/$steps - Audio mixing");
    $this->mergeTwoAudioFiles();
    
    // ... continue
}
```

### 4. Cleanup

```php
public function handle()
{
    // Main logic
    
    // Cleanup temporary files
    $this->cleanup();
}

private function cleanup()
{
    @unlink(storage_path('app/finals/final_video.mp4'));
    @unlink(storage_path('app/finals/merged_audio.mp3'));
}
```

---

## 📚 Related Documentation

- Main README: `PROJECT_README.md`
- Commands Documentation: `COMMANDS_README.md`
- Services Documentation: `SERVICES_README.md`
- API Documentation: `API_README.md`
