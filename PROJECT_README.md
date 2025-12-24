# YouTube Video Automation System

## 📋 Project Overview

This is an automated YouTube video creation and upload system built with Laravel, FFmpeg, and AI (ChatGPT/Gemini). The system is designed to run as scheduled cron jobs on a server to automatically generate, edit, and upload baby white noise videos to YouTube.

## 🎯 Main Purpose

- **Automated Video Creation**: Generate unique baby sleep/white noise videos by combining background videos, effects, soundbars, baby animations, and audio tracks
- **AI-Powered Metadata**: Use ChatGPT/Gemini API to generate SEO-optimized titles and descriptions
- **YouTube Upload**: Automatically authenticate with YouTube API and upload videos with custom thumbnails
- **Cron Job Execution**: Designed to run 1-2 times daily via server cron jobs
- **API Testing**: API endpoints available for testing new features before adding to cron automation

## 🏗️ Project Structure

```
├── app/
│   ├── Console/Commands/        # Cron job commands
│   │   ├── uplodeCommand.php           # Main upload command
│   │   ├── uplodepurecommand.php       # Alternative upload command
│   │   └── GenerateShuffledVideo.php   # Video generation command
│   │
│   ├── Jobs/                    # Background queue jobs
│   │   ├── UploadVideoJob.php          # Main video processing job
│   │   ├── UploadVideoPureJob.php      # YouTube upload job
│   │   └── StreamToYouTubeJob.php      # Live streaming job
│   │
│   ├── Services/                # Business logic services
│   │   └── WhiteNoiseService.php       # White/pink/brown noise generator
│   │
│   ├── Helpers/                 # Helper utilities
│   │   └── GeminiHelper.php            # Video editing & AI helpers
│   │
│   └── Http/Controllers/Api/    # API endpoints
│       ├── youtubeController.php       # YouTube OAuth & upload
│       ├── imageController.php         # Thumbnail generation
│       └── WhiteNoiseController.php    # Noise generation API
│
├── storage/app/                 # Media storage
│   ├── audio/                          # Audio files (MP3)
│   ├── baby_greenscreen/               # Baby animations (MP4)
│   ├── backgrounds/                    # Background videos (MP4)
│   ├── effects/                        # Video effects (MP4)
│   ├── soundbars/                      # Audio visualizers (MP4)
│   ├── sleep_effects/                  # Sleep animations (MP4)
│   ├── logo/                           # Channel logo (PNG)
│   ├── finals/                         # Temporary final videos
│   ├── copys/                          # Video copies for repetition
│   ├── outputs/                        # Final output videos
│   └── white_noise/                    # Generated white noise audio
│
├── routes/
│   └── api.php                  # API routes
│
└── database/
    └── migrations/              # Database schema
```

## 🔧 System Requirements

### Software Dependencies
- **PHP**: >= 8.2
- **Laravel**: 12.0
- **FFmpeg**: Latest version with libx264, libmp3lame, aac codecs
- **MySQL**: 5.7+ or MariaDB 10.3+
- **Composer**: Latest version
- **Node.js & NPM**: For asset compilation (optional)

### PHP Extensions
- `php-gd` (for image manipulation)
- `php-curl`
- `php-mbstring`
- `php-xml`
- `php-json`

### External APIs
- **Google YouTube Data API v3** (OAuth 2.0)
- **OpenAI API** (ChatGPT for content generation)
- **Google Gemini API** (Alternative AI service)

## 📦 Installation & Setup

### 1. Clone & Install Dependencies

```bash
cd /path/to/project
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Configure Environment (.env)

```env
APP_NAME="YouTube Video Automation"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=youtube_video
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Queue (for background jobs)
QUEUE_CONNECTION=database

# OpenAI API (for title/description generation)
OPENAI_API_KEY=sk-your-api-key-here

# Gemini API (alternative)
GEMINI_API_KEY=your-gemini-api-key
```

### 3. Database Setup

```bash
php artisan migrate
```

### 4. Storage Directories

Ensure these directories exist with proper permissions:

```bash
mkdir -p storage/app/{audio,baby_greenscreen,backgrounds,effects,soundbars,sleep_effects,logo,finals,copys,outputs,white_noise}
chmod -R 775 storage
chown -R www-data:www-data storage
```

### 5. FFmpeg Installation

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install ffmpeg ffprobe -y
ffmpeg -version
```

**macOS:**
```bash
brew install ffmpeg
```

### 6. Google YouTube API Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable **YouTube Data API v3**
4. Create OAuth 2.0 credentials
5. Download `google_credentials.json` and place in `storage/app/`
6. Add authorized redirect URIs: `https://your-domain.com/api/youtube/callback`

### 7. YouTube OAuth Authentication

```bash
# Get authorization URL
curl -X GET https://your-domain.com/api/youtube/auth-url?channel_id=1

# Visit the URL in browser, authorize, and the callback will save tokens
```

## 🎬 Video Creation Workflow

### Step 1: Random Asset Selection
- Background video (1-11)
- Effect overlay (1-8)
- Soundbar visualizer (1-8)
- Baby animation (1-6)
- Sleep effect (1)
- Audio tracks (2 random MP3s mixed)

### Step 2: Video Composition
Uses FFmpeg to layer videos with chromakey:
1. Background base
2. Effect overlay (green screen)
3. Soundbar (green screen)
4. Baby animation (green screen)
5. Sleep effect (green screen)

### Step 3: Audio Processing
- Mix 2 random audio files
- Merge audio with video
- Compress to target size (150MB)

### Step 4: Video Extension
- Copy final video 120 times
- Shuffle and concatenate to create 10-hour video

### Step 5: AI Metadata Generation
- Generate SEO-optimized title (ChatGPT/Gemini)
- Generate description with keywords
- Add relevant tags

### Step 6: Thumbnail Creation
- Overlay baby image on background
- Add channel logo (top-right corner)

### Step 7: YouTube Upload
- Upload video to YouTube
- Set as unlisted initially
- Upload custom thumbnail
- Set metadata (title, description, tags)

## ⚙️ Running the System

### Manual Execution (Testing)

```bash
# Run complete video creation and upload
php artisan app:uplode-command

# Generate shuffled video only
php artisan video:generate

# Start queue worker
php artisan queue:work --queue=high,default --stop-when-empty
```

### Cron Job Setup (Production)

Add to your server's crontab:

```bash
crontab -e
```

Add these lines:

```cron
# Run video upload job daily at 2 AM
0 2 * * * cd /path/to/project && php artisan app:uplode-command >> /var/log/youtube-upload.log 2>&1

# Alternative: Run twice daily (2 AM and 2 PM)
0 2,14 * * * cd /path/to/project && php artisan app:uplode-command >> /var/log/youtube-upload.log 2>&1

# Queue worker (keep alive)
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### Supervisor Configuration (Recommended)

For reliable queue processing:

```ini
[program:youtube-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/youtube-worker.log
stopwaitsecs=3600
```

## 🌐 API Endpoints (Testing)

### YouTube OAuth
- `GET /api/youtube/auth-url?channel_id={id}` - Get authorization URL
- `GET /api/youtube/callback` - OAuth callback handler
- `POST /api/youtube/refresh_token` - Refresh access token

### Video Processing
- `POST /api/youtube/upload` - Upload video to YouTube
- `POST /api/image` - Generate thumbnail overlay
- `POST /api/video/repeat-to-5min` - Repeat video to 5 minutes

### White Noise Generation
- `POST /api/white-noise/generate` - Generate any noise type
- `POST /api/white-noise/generate/white` - Generate white noise
- `POST /api/white-noise/generate/pink` - Generate pink noise
- `POST /api/white-noise/generate/brown` - Generate brown noise
- `GET /api/white-noise/files` - List generated files
- `DELETE /api/white-noise/files` - Delete noise file
- `GET /api/white-noise/health` - Health check

### Queue Management
- `GET /api/queue-work` - Start queue worker

## 📊 Database Tables

- **users** - User accounts (if needed)
- **access_token** - YouTube OAuth tokens per channel
- **jobs** - Queue jobs table
- **cache** - Cache storage
- **sessions** - Session data

## 🔐 Security Considerations

1. **API Keys**: Store in `.env`, never commit to Git
2. **Google Credentials**: Keep `google_credentials.json` secure
3. **File Permissions**: Ensure storage directories are writable but not publicly accessible
4. **Rate Limiting**: YouTube API has quota limits (10,000 units/day)
5. **Environment**: Set `APP_DEBUG=false` in production

## 📈 Performance Tips

1. **Video Processing**: Each video takes 20-40 minutes to process
2. **Queue Jobs**: Use `timeout=3600` (1 hour) for long-running jobs
3. **Storage**: Each 10-hour video is ~150MB, plan accordingly
4. **Memory**: PHP memory_limit should be at least 512M
5. **FFmpeg**: Use `-preset fast` for faster encoding

## 🐛 Troubleshooting

### FFmpeg Not Found
```bash
which ffmpeg
# Add to PATH if needed
export PATH=$PATH:/usr/local/bin
```

### Queue Jobs Not Running
```bash
php artisan queue:restart
php artisan queue:work --tries=1
```

### YouTube Upload Fails
- Check access token is valid: `POST /api/youtube/refresh_token`
- Verify file size < 256GB (YouTube limit)
- Check video format is compatible (MP4 recommended)

### Out of Memory
```bash
# Increase PHP memory limit
php -d memory_limit=1G artisan app:uplode-command
```

## 📝 Logs

Logs are stored in:
- Application logs: `storage/logs/laravel.log`
- Queue logs: Check supervisor configuration
- Cron logs: `/var/log/youtube-upload.log`

## 🔄 Adding New "Fetchers" (Features)

The system is designed to be extensible. To add new video sources or processors:

1. Create a new Command in `app/Console/Commands/`
2. Add business logic in `app/Services/` or `app/Helpers/`
3. Test via API endpoints in `app/Http/Controllers/Api/`
4. Once tested, add to cron schedule

## 📚 Documentation Files

This project includes comprehensive documentation:

1. **[QUICK_START.md](QUICK_START.md)** - 🚀 Get started in 10 minutes
2. **[PROJECT_README.md](PROJECT_README.md)** - Complete system overview (this file)
3. **[COMMANDS_README.md](COMMANDS_README.md)** - Cron job commands ("fetchers") documentation
4. **[JOBS_README.md](JOBS_README.md)** - Background queue jobs documentation
5. **[SERVICES_README.md](SERVICES_README.md)** - Services & helpers documentation
6. **[API_README.md](API_README.md)** - Complete API endpoints reference

### 📖 Reading Order

**For Quick Setup:**
1. Start with `QUICK_START.md`
2. Follow the 10-minute setup guide
3. Test the system
4. Set up cron jobs

**For Understanding the System:**
1. Read `PROJECT_README.md` (this file) for overview
2. Read `COMMANDS_README.md` to understand cron jobs
3. Read `JOBS_README.md` to understand video processing
4. Read `SERVICES_README.md` for implementation details
5. Read `API_README.md` for testing endpoints

**For Developers:**
- All documentation files contain detailed code examples
- Each file has troubleshooting sections
- API documentation includes cURL and Postman examples

## 📞 Support

For issues or questions:

1. **Check logs first:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Check specific documentation:**
   - Setup issues → `QUICK_START.md`
   - Cron job issues → `COMMANDS_README.md`
   - Video processing issues → `JOBS_README.md`
   - FFmpeg issues → `SERVICES_README.md`
   - API testing issues → `API_README.md`

3. **Common solutions:**
   - FFmpeg not found: Install FFmpeg
   - Permission errors: `chmod -R 775 storage`
   - Queue stuck: `php artisan queue:restart`
   - Out of memory: Increase PHP memory_limit

## 📄 License

MIT License
