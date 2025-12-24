# Services & Helpers Documentation

## 📋 Overview

Services and Helpers contain reusable business logic for video processing, audio generation, image manipulation, and AI integration. These classes are called by Jobs, Commands, and Controllers.

---

## 📦 Services

### WhiteNoiseService

**Location**: `app/Services/WhiteNoiseService.php`

**Purpose**: Generate unique white, pink, and brown noise audio files using FFmpeg for baby sleep videos.

#### Features

- Generate white noise (equal intensity across frequencies)
- Generate pink noise (softer, balanced sound)
- Generate brown noise (deeper, rumbling sound)
- Unique audio signatures (no copyright detection issues)
- Configurable duration, volume, and filename
- Audio variation through EQ randomization
- File management (list, delete)

#### Class Methods

##### `generateWhiteNoise($duration, $filename, $volume)`

Generates white noise audio file.

**Parameters:**
- `$duration` (int): Duration in seconds (1-36000, default 600 = 10 minutes)
- `$filename` (string|null): Custom filename (optional, auto-generated if null)
- `$volume` (float): Volume level (0.1-1.0, default 0.4 for soft sound)

**Returns:** Array with status, file info, and audio signature

**Example:**

```php
use App\Services\WhiteNoiseService;

$service = new WhiteNoiseService();

// Generate 10-hour white noise at 40% volume
$result = $service->generateWhiteNoise(
    duration: 36000,  // 10 hours
    filename: 'baby_sleep_white_noise.mp3',
    volume: 0.4
);

if ($result['status'] === 'success') {
    echo "File created: " . $result['file_path'];
    echo "File size: " . $result['file_size_mb'] . " MB";
    echo "Audio seed: " . $result['seed'];
}
```

**FFmpeg Command:**

```bash
ffmpeg -y -f lavfi \
    -i "anoisesrc=color=white:duration=36000:sample_rate=44100:seed=123456" \
    -af "volume=0.4*1.02,equalizer=f=100:t=q:w=1:g=3,equalizer=f=1000:t=q:w=1:g=-1,equalizer=f=8000:t=q:w=1:g=2" \
    -c:a libmp3lame \
    -q:a 2 \
    -ar 44100 \
    output.mp3
```

**Audio Randomization:**

Each generated file has unique characteristics:

```php
$seed = mt_rand(0, 999999);              // Random noise seed
$bassBoost = mt_rand(0, 5);              // 0-5 dB bass boost
$trebleBoost = mt_rand(-3, 3);           // -3 to +3 dB treble
$midCut = mt_rand(-2, 2);                // -2 to +2 dB mid adjustment
$amplitudeVar = 0.95 + (mt_rand(0, 100) / 1000);  // 0.95-1.05x amplitude
```

**Output Format:**
- **Codec**: MP3 (libmp3lame)
- **Quality**: VBR quality 2 (high quality)
- **Sample Rate**: 44100 Hz
- **Channels**: Mono
- **Typical Size**: ~1MB per minute

##### `generatePinkNoise($duration, $filename, $volume)`

Generates pink noise (1/f noise, gentler than white noise).

**Parameters:** Same as `generateWhiteNoise()`

**Example:**

```php
$result = $service->generatePinkNoise(3600, 'pink_1hour.mp3', 0.3);
```

**Characteristics:**
- Equal energy per octave (not per frequency)
- Sounds softer and more natural than white noise
- Better for masking low-frequency sounds
- Often preferred for baby sleep

##### `generateBrownNoise($duration, $filename, $volume)`

Generates brown noise (Brownian noise, deepest frequency).

**Parameters:** Same as `generateWhiteNoise()`

**Example:**

```php
$result = $service->generateBrownNoise(7200, 'brown_2hour.mp3', 0.5);
```

**Characteristics:**
- Energy decreases 6dB per octave
- Deep, rumbling sound like waterfall
- Best for blocking low-frequency noises
- Very soothing for some babies

##### `listNoiseFiles()`

Lists all generated noise files with metadata.

**Returns:** Array with file list and count

**Example:**

```php
$result = $service->listNoiseFiles();

foreach ($result['files'] as $file) {
    echo $file['filename'] . " - " . $file['size_mb'] . " MB\n";
    echo "Created: " . $file['created_at'] . "\n";
}
```

**Sample Output:**

```php
[
    'status' => 'success',
    'message' => 'Files retrieved successfully',
    'files' => [
        [
            'filename' => 'white_noise_1234567890_5678.mp3',
            'path' => '/path/to/storage/app/white_noise/white_noise_1234567890_5678.mp3',
            'relative_path' => 'white_noise/white_noise_1234567890_5678.mp3',
            'size' => 62914560,
            'size_mb' => 60.0,
            'created_at' => '2025-10-11 15:30:00',
            'modified_at' => '2025-10-11 15:30:00',
        ],
        // ... more files
    ],
    'total_count' => 15
]
```

##### `deleteNoiseFile($filename)`

Deletes a specific noise file.

**Parameters:**
- `$filename` (string): Filename to delete

**Returns:** Array with status and message

**Example:**

```php
$result = $service->deleteNoiseFile('white_noise_1234567890_5678.mp3');

if ($result['status'] === 'success') {
    echo "File deleted: " . $result['filename'];
}
```

##### `getAvailableNoiseColors()`

Returns available noise types and descriptions.

**Returns:** Array of noise types

**Example:**

```php
$types = $service->getAvailableNoiseColors();

// Output:
// [
//     'white' => 'White noise - Equal intensity across all frequencies',
//     'pink' => 'Pink noise - Softer, more balanced sound',
//     'brown' => 'Brown noise - Deeper, rumbling sound',
// ]
```

##### `checkFFmpeg()` (Protected)

Checks if FFmpeg is available on the system.

**Returns:** Boolean

**Example:**

```php
if ($service->checkFFmpeg()) {
    echo "FFmpeg is installed and available";
} else {
    echo "FFmpeg not found. Please install it.";
}
```

#### Audio Signature Explanation

Each generated audio has a unique "fingerprint" to avoid YouTube copyright detection:

```php
'audio_signature' => [
    'bass_boost' => '3 dB',        // Random bass boost
    'treble_boost' => '2 dB',      // Random treble adjustment
    'mid_adjustment' => '-1 dB',   // Random mid-range adjustment
    'amplitude_variation' => 1.02, // Random volume variation
]
```

These variations ensure:
- Every generated file is acoustically unique
- No two files have identical waveforms
- YouTube's Content ID won't flag as duplicate
- Audio quality remains high

#### Usage in Project

**In Controller:**

```php
// Generate white noise via API
public function generateWhiteNoise(Request $request)
{
    $service = new WhiteNoiseService();
    $result = $service->generateWhiteNoise(
        $request->input('duration', 600),
        $request->input('filename'),
        $request->input('volume', 0.4)
    );
    
    return response()->json($result);
}
```

**In Job:**

```php
// Generate audio for video
$service = new WhiteNoiseService();
$audio = $service->generateWhiteNoise(36000, null, 0.4);
$audioPath = $audio['file_path'];
```

#### Error Handling

```php
$result = $service->generateWhiteNoise(600);

if ($result['status'] === 'error') {
    // Handle errors
    switch ($result['message']) {
        case 'FFmpeg is not installed or not available in PATH':
            // Install FFmpeg
            break;
        case 'Failed to generate white noise':
            // Check FFmpeg output
            break;
        case 'White noise file was not created':
            // Check disk space and permissions
            break;
    }
}
```

---

## 📦 Helpers

### GeminiHelper

**Location**: `app/Helpers/GeminiHelper.php`

**Purpose**: Video editing operations, AI content generation, and YouTube upload helpers.

#### Features

- Complete video creation pipeline
- FFmpeg video compositing with chromakey
- Audio mixing and merging
- Video compression and optimization
- Image overlay and thumbnail creation
- AI-powered content generation (ChatGPT/Gemini)
- Video repetition and concatenation

#### Class Methods

##### `runvideo()`

Complete video creation pipeline (static method).

**Usage:**

```php
use App\Helpers\GeminiHelper;

GeminiHelper::runvideo();
```

**Process:**

```
1. full_video_fast()           → Create layered video
2. mergeTwoAudioFiles()        → Mix audio tracks
3. mergeFinalVideoWithAudio()  → Combine video + audio
4. compressFinalVideoWithAudio(90) → Compress to 90MB
5. copyVideoMultipleTimes(120) → Create 120 copies
6. mergeSameVideoMultipleTimes() → Concatenate to 10-hour video
```

**Output:** `storage/app/outputs/finaloutpt123.mp4`

##### `base($text)`

AI-powered text generation using OpenAI API.

**Parameters:**
- `$text` (string): Prompt for AI

**Returns:** String (AI-generated response)

**Example:**

```php
$title = GeminiHelper::base(
    "Write a YouTube title for baby white noise video under 100 characters"
);

// Output: "White Noise for Babies | 10 Hours of Peaceful Sleep | Soothe Crying Infant"
```

**API Configuration:**

```php
$apiKey = 'sk-proj-...'; // OpenAI API key
$url = "https://api.openai.com/v1/responses";

$response = Http::withHeaders([
    'Content-Type' => 'application/json',
    'Authorization' => "Bearer $apiKey",
])->post($url, [
    'model' => 'gpt-4.1',
    'input' => $text,
]);

return $response->json()["output"][0]['content'][0]['text'];
```

**Use Cases:**
- Generate video titles
- Generate video descriptions
- Create SEO keywords
- Write channel descriptions

##### `overlayImages()`

Creates thumbnail by overlaying baby image on background with logo.

**Process:**

```
1. Random select: background (1-35), baby (1-33)
2. Load images: background, baby, logo
3. Resize baby to 70% of original
4. Center baby on background
5. Add logo to top-right corner (12% width, 2% margin)
6. Save to storage/app/public/merged_image.png
```

**Example:**

```php
GeminiHelper::overlayImages();

// Output: storage/app/public/merged_image.png
```

**Image Specifications:**
- **Output Size**: Same as background (typically 1280x720)
- **Baby Size**: 70% of original
- **Logo Size**: 12% of background width
- **Format**: PNG with transparency support

##### `mergeClips()`

Merges random video clips into one video.

**Returns:** Integer (number of clips merged)

**Process:**

```php
$clipsPath = storage_path('app/clips');
$randomNumber = 10; // Number of clips to merge

$videos = collect(File::files($clipsPath))
    ->filter(function ($file) {
        return in_array(strtolower($file->getExtension()), ['mp4', 'mov', 'avi']);
    })
    ->shuffle()
    ->take($randomNumber)
    ->values();

// Create concat list
$listFile = storage_path('app/clips/videos.txt');
$fileListContent = '';
foreach ($videos as $video) {
    $fileListContent .= "file '" . $video->getPathname() . "'\n";
}
file_put_contents($listFile, $fileListContent);

// Merge with FFmpeg
$ffmpegCmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) 
           . " -c copy " . escapeshellarg($outputPath) . " -y";
exec($ffmpegCmd);
```

**Output:** `storage/app/outputs/merged.mp4`

##### `mergeWithGreenScreen1($background, $greenScreen, $output)`

Overlays green screen video on background using chromakey.

**Parameters:**
- `$background` (string): Path to background video
- `$greenScreen` (string): Path to green screen video
- `$output` (string): Output file path

**Returns:** String (FFmpeg log output)

**Example:**

```php
$outputLog = GeminiHelper::mergeWithGreenScreen1(
    storage_path('app/backgrounds/1.mp4'),
    storage_path('app/baby_greenscreen/3.mp4'),
    storage_path('app/outputs/result.mp4')
);
```

**FFmpeg Command:**

```bash
ffmpeg -i greenscreen.mp4 -i background.mp4 \
    -filter_complex "[0:v]chromakey=0x00FF00:0.2:0.1[ckout];[1:v][ckout]overlay[out]" \
    -map [out] -c:v libx264 -crf 18 -preset slow -y output.mp4
```

**Chromakey Parameters:**
- `0x00FF00`: Green color to remove (hex)
- `0.2`: Similarity threshold (how close to green)
- `0.1`: Blend value (edge smoothing)

##### `full_video_fast()`

Creates complete video by layering 5 videos with chromakey.

**Layers (bottom to top):**

1. Background (base layer)
2. Effect (green screen overlay)
3. Soundbar (audio visualizer, green screen)
4. Baby (baby animation, green screen)
5. Sleep effect (sleep animation, green screen)

**Example:**

```php
GeminiHelper::full_video_fast();

// Output: storage/app/finals/final_video.mp4
```

**FFmpeg Filter Chain:**

```
[1:v]chromakey=0x00FF00:0.2:0.1[eff];        → Remove green from effect
[0:v][eff]overlay[bg_eff];                   → Overlay effect on background
[2:v]chromakey=0x00FF00:0.2:0.1[sb];         → Remove green from soundbar
[bg_eff][sb]overlay[sb_eff];                 → Overlay soundbar
[3:v]chromakey=0x00FF00:0.2:0.1[baby];       → Remove green from baby
[sb_eff][baby]overlay[baby_eff];             → Overlay baby
[4:v]chromakey=0x00FF00:0.2:0.1[sleep];      → Remove green from sleep
[baby_eff][sleep]overlay[out]                → Final output
```

**Asset Selection:**

```php
$back = rand(1, 11);           // Random background (11 options)
$effict_number = rand(1, 8);   // Random effect (8 options)
$sound_bar_number = rand(1, 8); // Random soundbar (8 options)
$baby_number = rand(1, 6);      // Random baby (6 options)
$sleep = 1;                     // Fixed sleep effect
```

**Total Combinations:** 11 × 8 × 8 × 6 = 4,224 unique video variations

##### `mergeTwoAudioFiles()`

Mixes two random audio files into one.

**Process:**

```php
$random = rand(1, 6);
$random2 = rand(1, 6);
while ($random2 == $random) {
    $random2 = rand(1, 6); // Ensure different files
}

$audio1 = storage_path("app/audio/$random.mp3");
$audio2 = storage_path("app/audio/$random2.mp3");
$outputPath = storage_path('app/finals/merged_audio.mp3');

$ffmpegCmd = "ffmpeg -y -i \"$audio1\" -i \"$audio2\" "
           . "-filter_complex \"[0:0][1:0]amix=inputs=2:duration=longest:dropout_transition=2\" "
           . "-c:a libmp3lame \"$outputPath\"";
exec($ffmpegCmd);
```

**Output:** `storage/app/finals/merged_audio.mp3`

**Audio Mix Settings:**
- `inputs=2`: Mix 2 audio streams
- `duration=longest`: Use longest audio duration
- `dropout_transition=2`: 2-second crossfade when one audio ends

##### `mergeFinalVideoWithAudio()`

Combines video with audio track.

**Input:**
- Video: `storage/app/finals/final_video.mp4`
- Audio: `storage/app/finals/merged_audio.mp3`

**Output:** `storage/app/finals/final_video_with_audio.mp4`

**FFmpeg Command:**

```bash
ffmpeg -y -i video.mp4 -i audio.mp3 \
    -c:v copy \              # Copy video stream (no re-encode)
    -c:a aac \               # Encode audio to AAC
    -shortest \              # Match shortest stream
    output.mp4
```

**Cleanup:** Deletes temporary files (video without audio, merged audio)

##### `copyVideoMultipleTimes($count = 120)`

Creates multiple copies of compressed video.

**Parameters:**
- `$count` (int): Number of copies (default 120)

**Process:**

```php
$outputPath = storage_path('app/finals/final_video_with_audio_compressed.mp4');
$videoPath = storage_path('app/copys');

for ($i = 1; $i <= $count; $i++) {
    $copyPath = $videoPath . '/final_video_with_audio_' . $i . '.mp4';
    copy($outputPath, $copyPath);
}
```

**Output:** 120 files in `storage/app/copys/`

**Purpose:**
- Create material for 10-hour video
- Enable random shuffling
- Avoid repetitive patterns

##### `mergeSameVideoMultipleTimes()`

Concatenates shuffled video copies into long video.

**Process:**

```php
// Get all copies
$videos = collect(File::files($videoPath))
    ->shuffle()
    ->take(120)
    ->values();

// Create concat file
$listFile = storage_path('app/videos_repeat.txt');
foreach ($videos as $video) {
    $fileListContent .= "file '" . $video->getPathname() . "'\n";
}
file_put_contents($listFile, $fileListContent);

// Concatenate
$ffmpegCmd = "ffmpeg -f concat -safe 0 -i \"$listFile\" -c copy \"$outputPath\" -y";
exec($ffmpegCmd);

// Clean up copies
$files = File::files($videoPath);
foreach ($files as $file) {
    File::delete($file);
}
```

**Output:** `storage/app/outputs/finaloutpt123.mp4` (10-hour video)

##### `compressFinalVideoWithAudio($targetSizeMB = 150)`

Compresses video to target file size.

**Parameters:**
- `$targetSizeMB` (int): Target size in megabytes (default 150MB)

**Process:**

```php
// Get video duration
$durationCmd = "ffprobe -v error -show_entries format=duration "
             . "-of default=noprint_wrappers=1:nokey=1 \"$inputPath\"";
$duration = floatval(trim(shell_exec($durationCmd)));

// Calculate bitrate
$targetSizeBytes = $targetSizeMB * 1024 * 1024;
$bitrate = intval(($targetSizeBytes * 8) / $duration);

// Compress
$cmd = "ffmpeg -y -i \"$inputPath\" "
     . "-b:v {$bitrate} -maxrate {$bitrate} -bufsize " . intval($bitrate / 2)
     . " -c:v libx264 -c:a aac -preset fast \"$outputPath\"";
exec($cmd);
```

**Output:** `storage/app/finals/final_video_with_audio_compressed.mp4`

**Bitrate Calculation:**

```
bitrate (bits/sec) = (target_size_MB × 1024 × 1024 × 8) ÷ duration_seconds

Example for 150MB, 1-hour video:
bitrate = (150 × 1024 × 1024 × 8) ÷ 3600
bitrate = 335,544 bps ≈ 335 kbps
```

---

## 🧪 Testing Services

### WhiteNoiseService Testing

```php
// Test white noise generation
$service = new WhiteNoiseService();

// Test 1: Generate white noise
$result = $service->generateWhiteNoise(60, 'test_white.mp3', 0.5);
assert($result['status'] === 'success');
assert(file_exists($result['file_path']));

// Test 2: Generate pink noise
$result = $service->generatePinkNoise(60, 'test_pink.mp3', 0.5);
assert($result['status'] === 'success');

// Test 3: List files
$result = $service->listNoiseFiles();
assert($result['total_count'] >= 2);

// Test 4: Delete file
$result = $service->deleteNoiseFile('test_white.mp3');
assert($result['status'] === 'success');

// Test 5: Check FFmpeg
assert($service->checkFFmpeg() === true);
```

### GeminiHelper Testing

```php
// Test video creation
GeminiHelper::overlayImages();
assert(file_exists(storage_path('app/public/merged_image.png')));

// Test clip merging
$count = GeminiHelper::mergeClips();
assert($count === 10);
assert(file_exists(storage_path('app/outputs/merged.mp4')));

// Test AI generation (requires API key)
$title = GeminiHelper::base("Generate a test title");
assert(strlen($title) > 0);
```

---

## 🐛 Troubleshooting

### FFmpeg Not Found

```bash
# Check FFmpeg installation
which ffmpeg
ffmpeg -version

# Install if missing
# Ubuntu/Debian
sudo apt install ffmpeg

# macOS
brew install ffmpeg
```

### Audio Generation Fails

```php
// Check error details
$result = $service->generateWhiteNoise(600);
if ($result['status'] === 'error') {
    echo "Error: " . $result['message'];
}

// Common issues:
// 1. FFmpeg not in PATH
// 2. Storage directory not writable
// 3. Disk space full
```

### Video Processing Errors

```bash
# Test FFmpeg chromakey manually
ffmpeg -i greenscreen.mp4 -i background.mp4 \
    -filter_complex "[0:v]chromakey=0x00FF00:0.2:0.1[ck];[1:v][ck]overlay" \
    -c:v libx264 -crf 18 output.mp4

# Check input files
ffprobe greenscreen.mp4
ffprobe background.mp4
```

### Memory Issues

```php
// Increase PHP memory limit
ini_set('memory_limit', '1024M');

// Or in php.ini
memory_limit = 1024M

// Or via command line
php -d memory_limit=1G artisan command
```

---

## 📚 Related Documentation

- Main README: `PROJECT_README.md`
- Commands Documentation: `COMMANDS_README.md`
- Jobs Documentation: `JOBS_README.md`
- API Documentation: `API_README.md`
