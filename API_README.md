# API Endpoints Documentation

## 📋 Overview

This document describes all API endpoints available for testing video processing, YouTube operations, image manipulation, and white noise generation. These APIs are primarily for testing before adding features to automated cron jobs.

**Base URL**: `https://your-domain.com/api`

---

## 🔐 Authentication

### YouTube OAuth Endpoints

#### Get Authorization URL

Get the YouTube OAuth authorization URL for a specific channel.

**Endpoint:** `GET /youtube/auth-url`

**Parameters:**
- `channel_id` (required): Channel identifier (integer)

**Example Request:**

```bash
curl -X GET "https://your-domain.com/api/youtube/auth-url?channel_id=1"
```

**Example Response:**

```json
{
    "auth_url": "https://accounts.google.com/o/oauth2/auth?client_id=...&redirect_uri=...&scope=..."
}
```

**Usage:**
1. Call this endpoint to get the authorization URL
2. Visit the URL in a browser
3. Sign in with Google account
4. Grant permissions
5. You'll be redirected to the callback URL with authorization code

---

#### OAuth Callback Handler

Handles the OAuth callback from Google and saves access tokens.

**Endpoint:** `GET /youtube/callback`

**Parameters:**
- `code` (required): Authorization code from Google
- `state` (required): Channel ID passed during authorization

**Example:**

```
https://your-domain.com/api/youtube/callback?code=4/0Axxxxxxx&state=1
```

**Response:**

```json
{
    "message": "Access token saved successfully for channel 1"
}
```

**Database Storage:**

Tokens are saved to `access_token` table:
- `channel_id`: Channel identifier
- `access_token`: YouTube access token
- `refresh_token`: Refresh token for renewing access
- `expires_at`: Token expiration time
- `scope`: Granted scopes
- `token_type`: Bearer

---

#### Refresh Access Token

Manually refresh an expired YouTube access token.

**Endpoint:** `POST /youtube/refresh_token`

**Example Request:**

```bash
curl -X POST "https://your-domain.com/api/youtube/refresh_token" \
    -H "Content-Type: application/json"
```

**Example Response:**

```json
{
    "access_token": "ya29.a0AfH6SMxxxxxxxx",
    "expires_in": 3599,
    "scope": "https://www.googleapis.com/auth/youtube.upload",
    "token_type": "Bearer"
}
```

**Error Response:**

```json
{
    "error": "Failed to refresh token",
    "response": "...",
    "http_code": 400
}
```

---

## 🎬 Video Processing Endpoints

### Upload Video to YouTube

Upload a video file to YouTube with metadata.

**Endpoint:** `POST /youtube/upload`

⚠️ **Note:** Currently dispatches job and returns immediately. Check logs for upload status.

**Example Request:**

```bash
curl -X POST "https://your-domain.com/api/youtube/upload" \
    -H "Content-Type: application/json"
```

**Response:**

```json
{
    "message": "Video upload job dispatched"
}
```

**Process:**
1. Dispatches `UploadVideoJob`
2. Job processes video in background
3. Check logs: `tail -f storage/logs/laravel.log`

---

### Repeat Video to 5 Minutes

Upload a video and repeat it to exactly 5 minutes duration.

**Endpoint:** `POST /video/repeat-to-5min`

**Parameters:**
- `video` (required): Video file (max 50MB)
- Supported formats: MP4, AVI, MPEG, QuickTime

**Example Request:**

```bash
curl -X POST "https://your-domain.com/api/video/repeat-to-5min" \
    -F "video=@/path/to/video.mp4"
```

**Example Response:**

```json
{
    "message": "Video repeated to 5 minutes successfully.",
    "path": "/var/www/storage/app/public/repeated_video_5min.mp4"
}
```

**Process:**
1. Uploads video file
2. Gets video duration using FFprobe
3. Calculates how many loops needed for 5 minutes
4. Creates concat file listing video multiple times
5. Uses FFmpeg to concatenate
6. Trims to exactly 300 seconds (5 minutes)

**Error Response:**

```json
{
    "error": "Could not get video duration."
}
```

---

## 🖼️ Image Processing Endpoints

### Create Thumbnail Overlay

Generate a thumbnail by overlaying baby image on background with logo.

**Endpoint:** `POST /image`

**Parameters:** None (uses random selection)

**Example Request:**

```bash
curl -X POST "https://your-domain.com/api/image"
```

**Response:**

```
done kemo (overlay 85%)
```

**Process:**
1. Randomly selects background (1-8)
2. Randomly selects baby image (1-11)
3. Resizes baby to 70% of original
4. Centers baby on background
5. Adds logo to top-right corner (12% width, 2% margin)
6. Saves to `storage/app/public/test/merged_image_keep_overlay_size.png`

**Output Image:**
- **Format**: PNG
- **Size**: Same as background (typically 1280x720)
- **Location**: `storage/app/public/test/merged_image_keep_overlay_size.png`

---

## 🎵 White Noise Generation Endpoints

### Generate Noise (Any Type)

Generate white, pink, or brown noise audio file.

**Endpoint:** `POST /white-noise/generate`

**Parameters:**
- `type` (required): Noise type - `white`, `pink`, or `brown`
- `duration` (optional): Duration in seconds (1-36000, default 600)
- `filename` (optional): Custom filename
- `volume` (optional): Volume level (0.1-1.0, default 0.4)

**Example Request:**

```bash
curl -X POST "https://your-domain.com/api/white-noise/generate" \
    -H "Content-Type: application/json" \
    -d '{
        "type": "white",
        "duration": 3600,
        "filename": "test_white_1hour.mp3",
        "volume": 0.5
    }'
```

**Example Response:**

```json
{
    "status": "success",
    "message": "White noise generated successfully with unique audio signature",
    "data": {
        "type": "white",
        "file_path": "/var/www/storage/app/white_noise/test_white_1hour.mp3",
        "relative_path": "white_noise/test_white_1hour.mp3",
        "filename": "test_white_1hour.mp3",
        "duration": 3600,
        "duration_formatted": "01:00:00",
        "file_size": 62914560,
        "file_size_mb": 60.0,
        "volume": 0.5,
        "seed": 742156
    }
}
```

**Error Response:**

```json
{
    "status": "error",
    "message": "FFmpeg is not installed or not available in PATH"
}
```

---

### Generate White Noise

Generate white noise audio file.

**Endpoint:** `POST /white-noise/generate/white`

**Parameters:**
- `duration` (optional): Duration in seconds (1-36000, default 600)
- `filename` (optional): Custom filename
- `volume` (optional): Volume level (0.1-1.0, default 0.4)

**Example Request:**

```bash
curl -X POST "https://your-domain.com/api/white-noise/generate/white" \
    -H "Content-Type: application/json" \
    -d '{
        "duration": 36000,
        "volume": 0.4
    }'
```

**Example Response:**

```json
{
    "status": "success",
    "message": "White noise generated successfully with unique audio signature",
    "data": {
        "file_path": "/var/www/storage/app/white_noise/white_noise_1710523456_7842.mp3",
        "relative_path": "white_noise/white_noise_1710523456_7842.mp3",
        "filename": "white_noise_1710523456_7842.mp3",
        "duration": 36000,
        "duration_formatted": "10:00:00",
        "file_size": 629145600,
        "file_size_mb": 600.0,
        "volume": 0.4,
        "seed": 523789
    }
}
```

**Characteristics:**
- Equal intensity across all frequencies
- Sounds like static or "shhh"
- Good for masking environmental sounds
- Most common type for baby sleep

---

### Generate Pink Noise

Generate pink noise audio file.

**Endpoint:** `POST /white-noise/generate/pink`

**Parameters:** Same as white noise

**Example Request:**

```bash
curl -X POST "https://your-domain.com/api/white-noise/generate/pink" \
    -H "Content-Type: application/json" \
    -d '{
        "duration": 7200,
        "volume": 0.3
    }'
```

**Example Response:**

```json
{
    "status": "success",
    "message": "Pink noise generated successfully with unique audio signature",
    "data": {
        "file_path": "/var/www/storage/app/white_noise/pink_noise_1710523789_2134.mp3",
        "filename": "pink_noise_1710523789_2134.mp3",
        "duration": 7200,
        "duration_formatted": "02:00:00",
        "file_size_mb": 120.0,
        "volume": 0.3,
        "seed": 891234
    }
}
```

**Characteristics:**
- Equal energy per octave (1/f noise)
- Softer and more natural than white noise
- Sounds like steady rainfall
- Preferred by many for sleep

---

### Generate Brown Noise

Generate brown noise audio file.

**Endpoint:** `POST /white-noise/generate/brown`

**Parameters:** Same as white noise

**Example Request:**

```bash
curl -X POST "https://your-domain.com/api/white-noise/generate/brown" \
    -H "Content-Type: application/json" \
    -d '{
        "duration": 1800,
        "filename": "brown_30min.mp3",
        "volume": 0.5
    }'
```

**Example Response:**

```json
{
    "status": "success",
    "message": "Brown noise generated successfully with unique audio signature",
    "data": {
        "file_path": "/var/www/storage/app/white_noise/brown_30min.mp3",
        "filename": "brown_30min.mp3",
        "duration": 1800,
        "duration_formatted": "00:30:00",
        "file_size_mb": 30.0,
        "volume": 0.5,
        "seed": 456789
    }
}
```

**Characteristics:**
- Energy decreases 6dB per octave
- Deep, rumbling sound
- Sounds like waterfall or thunder
- Best for blocking low-frequency noises

---

### List Generated Files

List all generated white noise files.

**Endpoint:** `GET /white-noise/files`

**Example Request:**

```bash
curl -X GET "https://your-domain.com/api/white-noise/files"
```

**Example Response:**

```json
{
    "status": "success",
    "message": "Files retrieved successfully",
    "data": [
        {
            "filename": "white_noise_1710523456_7842.mp3",
            "path": "/var/www/storage/app/white_noise/white_noise_1710523456_7842.mp3",
            "relative_path": "white_noise/white_noise_1710523456_7842.mp3",
            "size": 629145600,
            "size_mb": 600.0,
            "created_at": "2025-10-11 12:30:56",
            "modified_at": "2025-10-11 12:30:56"
        },
        {
            "filename": "pink_noise_1710523789_2134.mp3",
            "path": "/var/www/storage/app/white_noise/pink_noise_1710523789_2134.mp3",
            "relative_path": "white_noise/pink_noise_1710523789_2134.mp3",
            "size": 125829120,
            "size_mb": 120.0,
            "created_at": "2025-10-11 13:16:29",
            "modified_at": "2025-10-11 13:16:29"
        }
    ],
    "total_count": 2
}
```

---

### Delete Noise File

Delete a specific generated noise file.

**Endpoint:** `DELETE /white-noise/files`

**Parameters:**
- `filename` (required): Filename to delete

**Example Request:**

```bash
curl -X DELETE "https://your-domain.com/api/white-noise/files" \
    -H "Content-Type: application/json" \
    -d '{
        "filename": "white_noise_1710523456_7842.mp3"
    }'
```

**Example Response:**

```json
{
    "status": "success",
    "message": "File deleted successfully",
    "filename": "white_noise_1710523456_7842.mp3"
}
```

**Error Response:**

```json
{
    "status": "error",
    "message": "File not found"
}
```

---

### Get Available Noise Types

Get information about available noise types.

**Endpoint:** `GET /white-noise/types`

**Example Request:**

```bash
curl -X GET "https://your-domain.com/api/white-noise/types"
```

**Example Response:**

```json
{
    "status": "success",
    "message": "Available noise types retrieved",
    "data": {
        "white": "White noise - Equal intensity across all frequencies",
        "pink": "Pink noise - Softer, more balanced sound",
        "brown": "Brown noise - Deeper, rumbling sound"
    }
}
```

---

### Health Check

Check if white noise service is ready (FFmpeg installed, directories writable).

**Endpoint:** `GET /white-noise/health`

**Example Request:**

```bash
curl -X GET "https://your-domain.com/api/white-noise/health"
```

**Example Response (Success):**

```json
{
    "status": "success",
    "message": "White noise service is ready",
    "checks": {
        "ffmpeg_available": true,
        "directory_exists": true,
        "directory_writable": true,
        "directory_path": "/var/www/storage/app/white_noise",
        "php_version": "8.2.15"
    },
    "recommendations": []
}
```

**Example Response (Warning):**

```json
{
    "status": "warning",
    "message": "Some components need attention",
    "checks": {
        "ffmpeg_available": false,
        "directory_exists": true,
        "directory_writable": true,
        "directory_path": "/var/www/storage/app/white_noise",
        "php_version": "8.2.15"
    },
    "recommendations": [
        "Install FFmpeg and add it to PATH"
    ]
}
```

---

## 🔄 Queue Management Endpoint

### Start Queue Worker

Start a queue worker to process pending jobs.

**Endpoint:** `GET /queue-work`

**Example Request:**

```bash
curl -X GET "https://your-domain.com/api/queue-work"
```

**Example Response:**

```json
{
    "message": "Queue worker started"
}
```

**What It Does:**

```php
\Artisan::call('optimize');
\Artisan::call('optimize:clear');
\Artisan::call('queue:restart');
\Artisan::call('queue:work --timeout=3600');
```

⚠️ **Warning:** This starts a blocking queue worker. Use supervisor or cron for production.

---

## 🎥 Live Streaming Endpoint (Experimental)

### Start Live Stream

Create a YouTube live stream from a video file.

**Endpoint:** `GET /start`

⚠️ **Status:** Experimental, may not work correctly

**Example Request:**

```bash
curl -X GET "https://your-domain.com/api/start"
```

**Process:**
1. Creates YouTube live broadcast
2. Creates live stream with RTMP ingestion
3. Binds broadcast to stream
4. Uses FFmpeg to stream video file to YouTube

**Response:**

```json
{
    "message": "Live stream started successfully",
    "broadcast_id": "abc123",
    "stream_id": "xyz789",
    "stream_url": "rtmp://a.rtmp.youtube.com/live2",
    "stream_key": "xxxx-xxxx-xxxx-xxxx"
}
```

---

## 📊 API Testing Examples

### Postman Collection

Import this JSON into Postman:

```json
{
    "info": {
        "name": "YouTube Video Automation API",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "item": [
        {
            "name": "YouTube OAuth",
            "item": [
                {
                    "name": "Get Auth URL",
                    "request": {
                        "method": "GET",
                        "url": "{{base_url}}/youtube/auth-url?channel_id=1"
                    }
                },
                {
                    "name": "Refresh Token",
                    "request": {
                        "method": "POST",
                        "url": "{{base_url}}/youtube/refresh_token"
                    }
                }
            ]
        },
        {
            "name": "White Noise",
            "item": [
                {
                    "name": "Generate White Noise",
                    "request": {
                        "method": "POST",
                        "url": "{{base_url}}/white-noise/generate/white",
                        "body": {
                            "mode": "raw",
                            "raw": "{\"duration\": 600, \"volume\": 0.4}"
                        }
                    }
                },
                {
                    "name": "List Files",
                    "request": {
                        "method": "GET",
                        "url": "{{base_url}}/white-noise/files"
                    }
                },
                {
                    "name": "Health Check",
                    "request": {
                        "method": "GET",
                        "url": "{{base_url}}/white-noise/health"
                    }
                }
            ]
        },
        {
            "name": "Image Processing",
            "item": [
                {
                    "name": "Generate Thumbnail",
                    "request": {
                        "method": "POST",
                        "url": "{{base_url}}/image"
                    }
                }
            ]
        }
    ],
    "variable": [
        {
            "key": "base_url",
            "value": "https://your-domain.com/api"
        }
    ]
}
```

### cURL Testing Script

```bash
#!/bin/bash

BASE_URL="https://your-domain.com/api"

echo "=== Testing White Noise Generation ==="
curl -X POST "$BASE_URL/white-noise/generate/white" \
    -H "Content-Type: application/json" \
    -d '{"duration": 60, "volume": 0.5}'

echo -e "\n\n=== Testing File Listing ==="
curl -X GET "$BASE_URL/white-noise/files"

echo -e "\n\n=== Testing Health Check ==="
curl -X GET "$BASE_URL/white-noise/health"

echo -e "\n\n=== Testing Thumbnail Generation ==="
curl -X POST "$BASE_URL/image"

echo -e "\n\n=== Testing YouTube Auth URL ==="
curl -X GET "$BASE_URL/youtube/auth-url?channel_id=1"
```

### Python Testing Script

```python
import requests
import json

BASE_URL = "https://your-domain.com/api"

# Test white noise generation
print("Testing white noise generation...")
response = requests.post(
    f"{BASE_URL}/white-noise/generate/white",
    json={
        "duration": 600,
        "volume": 0.4,
        "filename": "test_python.mp3"
    }
)
print(f"Status: {response.status_code}")
print(json.dumps(response.json(), indent=2))

# Test file listing
print("\nTesting file listing...")
response = requests.get(f"{BASE_URL}/white-noise/files")
print(json.dumps(response.json(), indent=2))

# Test health check
print("\nTesting health check...")
response = requests.get(f"{BASE_URL}/white-noise/health")
print(json.dumps(response.json(), indent=2))
```

---

## ⚠️ Error Codes & Handling

### HTTP Status Codes

- **200 OK**: Success
- **206 Partial Content**: Warning (e.g., health check with issues)
- **400 Bad Request**: Invalid parameters
- **404 Not Found**: Resource not found
- **422 Unprocessable Entity**: Validation failed
- **500 Internal Server Error**: Server error

### Error Response Format

```json
{
    "status": "error",
    "message": "Error description",
    "errors": {
        "field_name": ["Validation error message"]
    }
}
```

### Common Errors

#### FFmpeg Not Installed

```json
{
    "status": "error",
    "message": "FFmpeg is not installed or not available in PATH"
}
```

**Solution:** Install FFmpeg

```bash
# Ubuntu
sudo apt install ffmpeg

# macOS
brew install ffmpeg
```

#### File Not Found

```json
{
    "status": "error",
    "message": "File not found"
}
```

**Solution:** Verify filename exists using `/white-noise/files` endpoint

#### Validation Error

```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "duration": ["The duration must be between 1 and 36000."],
        "volume": ["The volume must be between 0.1 and 1."]
    }
}
```

**Solution:** Fix parameters according to validation rules

#### Storage Permission Error

```json
{
    "status": "error",
    "message": "White noise file was not created"
}
```

**Solution:** Fix directory permissions

```bash
chmod -R 775 storage/app/white_noise
chown -R www-data:www-data storage/app/white_noise
```

---

## 🔒 Security Considerations

### API Authentication

Currently, APIs are not authenticated. For production:

1. **Add Laravel Sanctum:**

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

2. **Protect routes:**

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/white-noise/generate', [WhiteNoiseController::class, 'generateNoise']);
    // ... other routes
});
```

### Rate Limiting

Add rate limiting to prevent abuse:

```php
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/white-noise/generate/white', [WhiteNoiseController::class, 'generateWhiteNoise']);
});
```

### Input Validation

All endpoints validate input. Never bypass validation:

```php
$validator = Validator::make($request->all(), [
    'duration' => 'required|integer|min:1|max:36000',
    'volume' => 'nullable|numeric|min:0.1|max:1.0',
]);
```

---

## 📚 Related Documentation

- Main README: `PROJECT_README.md`
- Commands Documentation: `COMMANDS_README.md`
- Jobs Documentation: `JOBS_README.md`
- Services Documentation: `SERVICES_README.md`
