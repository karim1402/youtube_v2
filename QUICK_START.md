# Quick Start Guide

## 🚀 Getting Started in 10 Minutes

This guide will get your YouTube video automation system up and running quickly.

---

## 📋 Prerequisites Checklist

Before starting, ensure you have:

- ✅ **PHP 8.2+** installed
- ✅ **MySQL/MariaDB** running
- ✅ **Composer** installed
- ✅ **FFmpeg** installed (`ffmpeg -version`)
- ✅ **Google Cloud Project** with YouTube Data API enabled
- ✅ **OpenAI API Key** (for ChatGPT)
- ✅ Server access for cron jobs

---

## 🔧 Installation Steps

### 1. Install Dependencies (2 minutes)

```bash
cd "/Users/kemomac/Desktop/last youtube /archive (2)"
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Configure Database (1 minute)

Edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=youtube_video
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Run migrations:

```bash
php artisan migrate
```

### 3. Configure APIs (2 minutes)

Add to `.env`:

```env
# OpenAI API (for title/description generation)
OPENAI_API_KEY=sk-your-api-key-here

# Queue configuration
QUEUE_CONNECTION=database
```

### 4. Setup Storage Directories (1 minute)

```bash
chmod -R 775 storage
mkdir -p storage/app/{audio,baby_greenscreen,backgrounds,effects,soundbars,sleep_effects,logo,finals,copys,outputs,white_noise}
```

### 5. Setup YouTube OAuth (3 minutes)

1. Place `google_credentials.json` in `storage/app/`
2. Get authorization URL:

```bash
curl "http://localhost:8000/api/youtube/auth-url?channel_id=1"
```

3. Visit the URL in browser and authorize
4. Tokens will be saved automatically

### 6. Add Media Assets (1 minute)

Place your video/audio files in:

```
storage/app/
├── backgrounds/     → 11 background videos (1.mp4 - 11.mp4)
├── effects/         → 8 effect overlays (1.mp4 - 8.mp4)
├── soundbars/       → 8 audio visualizers (1.mp4 - 8.mp4)
├── baby_greenscreen/→ 6 baby animations (1.mp4 - 6.mp4)
├── sleep_effects/   → 1 sleep effect (1.mp4)
├── audio/           → 6 audio tracks (1.mp3 - 6.mp3)
└── logo/            → Channel logo (file.png)
```

---

## 🧪 Testing (Before Cron Setup)

### Test 1: Generate White Noise

```bash
curl -X POST "http://localhost:8000/api/white-noise/generate/white" \
    -H "Content-Type: application/json" \
    -d '{"duration": 60, "volume": 0.5}'
```

**Expected Result:** Returns success with file path

### Test 2: Generate Thumbnail

```bash
curl -X POST "http://localhost:8000/api/image"
```

**Expected Result:** Creates thumbnail in `storage/app/public/test/`

### Test 3: Check Health

```bash
curl "http://localhost:8000/api/white-noise/health"
```

**Expected Result:** All checks pass (FFmpeg available, directory writable)

### Test 4: Run Video Creation Manually

```bash
php artisan app:uplode-command
```

**Expected Result:** 
- Video created in `storage/app/outputs/finaloutpt123.mp4`
- Check logs: `tail -f storage/logs/laravel.log`
- Process takes 30-60 minutes

---

## ⏰ Setup Cron Jobs (Production)

### Option 1: Simple Cron (Recommended for Testing)

```bash
crontab -e
```

Add:

```cron
# Run daily at 2 AM
0 2 * * * cd "/Users/kemomac/Desktop/last youtube /archive (2)" && php artisan app:uplode-command >> /var/log/youtube-upload.log 2>&1
```

### Option 2: Twice Daily

```cron
# Run at 2 AM and 2 PM
0 2,14 * * * cd "/Users/kemomac/Desktop/last youtube /archive (2)" && php artisan app:uplode-command >> /var/log/youtube-upload.log 2>&1
```

### Option 3: Supervisor (Recommended for Production)

Create `/etc/supervisor/conf.d/youtube-worker.conf`:

```ini
[program:youtube-worker]
process_name=%(program_name)s_%(process_num)02d
command=php "/Users/kemomac/Desktop/last youtube /archive (2)/artisan" queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/youtube-worker.log
stopwaitsecs=3600
```

Start supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start youtube-worker:*
```

---

## 📊 Monitoring

### Check Logs

```bash
# Application logs
tail -f storage/logs/laravel.log

# Cron logs
tail -f /var/log/youtube-upload.log

# Queue status
php artisan queue:monitor
```

### Check Failed Jobs

```bash
# List failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all
```

### Check Queue Status

```bash
# MySQL
mysql -u root -p
USE youtube_video;
SELECT COUNT(*) FROM jobs;
SELECT COUNT(*) FROM failed_jobs;
```

---

## 🎯 Workflow Overview

```
Cron Trigger (2 AM)
        ↓
Command: app:uplode-command
        ↓
Job: UploadVideoJob
        ↓
1. Generate layered video (5-10 min)
   - Background + Effects + Soundbar + Baby + Sleep
        ↓
2. Mix audio tracks (1 min)
   - Select 2 random audio files
   - Mix with FFmpeg
        ↓
3. Merge video + audio (1 min)
        ↓
4. Compress to 150MB (5-10 min)
        ↓
5. Copy 120 times (2-5 min)
        ↓
6. Concatenate into 10-hour video (10-20 min)
        ↓
Output: storage/app/outputs/finaloutpt123.mp4
```

**Total Time:** 30-60 minutes per video

---

## 🔍 Troubleshooting

### FFmpeg Not Found

```bash
# Check installation
which ffmpeg
ffmpeg -version

# Install if missing (Ubuntu)
sudo apt install ffmpeg

# Install if missing (macOS)
brew install ffmpeg
```

### Permission Denied

```bash
chmod -R 775 storage
chown -R www-data:www-data storage
```

### Queue Not Processing

```bash
# Restart queue
php artisan queue:restart

# Process manually
php artisan queue:work --once

# Check queue table
php artisan tinker
>>> DB::table('jobs')->count();
```

### Out of Memory

```bash
# Increase PHP memory
php -d memory_limit=1G artisan app:uplode-command

# Or edit php.ini
memory_limit = 1024M
```

### YouTube Upload Fails

```bash
# Refresh access token
curl -X POST "http://localhost:8000/api/youtube/refresh_token"

# Check token in database
php artisan tinker
>>> DB::table('access_token')->first();
```

---

## 📱 Quick Commands Reference

```bash
# Manual video creation
php artisan app:uplode-command

# Generate shuffled video only
php artisan video:generate

# Start queue worker
php artisan queue:work

# Clear all caches
php artisan optimize:clear

# Check routes
php artisan route:list | grep api

# View logs
tail -f storage/logs/laravel.log

# Check database
php artisan tinker
```

---

## 🌐 API Testing Endpoints

```bash
# Test white noise generation
curl -X POST "http://localhost:8000/api/white-noise/generate/white" \
    -H "Content-Type: application/json" \
    -d '{"duration": 600, "volume": 0.4}'

# Test thumbnail generation
curl -X POST "http://localhost:8000/api/image"

# Check health
curl "http://localhost:8000/api/white-noise/health"

# List generated files
curl "http://localhost:8000/api/white-noise/files"

# Get YouTube auth URL
curl "http://localhost:8000/api/youtube/auth-url?channel_id=1"

# Refresh YouTube token
curl -X POST "http://localhost:8000/api/youtube/refresh_token"
```

---

## 📈 Performance Tips

### 1. Use Redis Queue (Faster)

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 2. Optimize FFmpeg

Use faster preset in `app/Helpers/GeminiHelper.php`:

```php
// Change from:
-preset slow

// To:
-preset fast
```

### 3. Reduce Video Copies

For faster testing, reduce copies in `app/Jobs/UploadVideoJob.php`:

```php
// Change from:
$this->copyVideoMultipleTimes(120);

// To:
$this->copyVideoMultipleTimes(30); // 2.5 hours instead of 10
```

### 4. Enable Opcache

In `php.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
```

---

## 🎓 Next Steps

1. **Read Full Documentation**
   - `PROJECT_README.md` - Complete system overview
   - `COMMANDS_README.md` - Detailed command documentation
   - `JOBS_README.md` - Background jobs explained
   - `SERVICES_README.md` - Services and helpers
   - `API_README.md` - Complete API reference

2. **Test All Features**
   - Generate different noise types
   - Create custom thumbnails
   - Test video processing pipeline
   - Upload to YouTube

3. **Customize for Your Needs**
   - Modify AI prompts for better titles
   - Adjust video compression settings
   - Add more asset variations
   - Implement notifications

4. **Production Deployment**
   - Set up proper server (not localhost)
   - Configure SSL certificates
   - Set up monitoring (Sentry, New Relic)
   - Add backups for storage directory

5. **Add New Fetchers**
   - Create new commands for different content types
   - Test via API endpoints first
   - Add to cron schedule when stable

---

## 📞 Support & Resources

### Check System Status

```bash
# FFmpeg
ffmpeg -version

# PHP
php -v

# Database
php artisan tinker
>>> DB::connection()->getPdo();

# Queue
php artisan queue:monitor

# Storage permissions
ls -la storage/app
```

### Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| FFmpeg not found | `sudo apt install ffmpeg` or `brew install ffmpeg` |
| Permission denied | `chmod -R 775 storage && chown -R www-data:www-data storage` |
| Queue stuck | `php artisan queue:restart` |
| Out of memory | Increase `memory_limit` in php.ini |
| YouTube upload fails | Refresh token via API |
| Video not created | Check `storage/logs/laravel.log` |

### Log Locations

- Application logs: `storage/logs/laravel.log`
- Cron logs: `/var/log/youtube-upload.log`
- Supervisor logs: `/var/log/youtube-worker.log`
- System logs: `/var/log/syslog`

---

## ✅ Final Checklist

Before going to production:

- [ ] All dependencies installed
- [ ] Database migrated
- [ ] `.env` configured correctly
- [ ] Storage directories created with proper permissions
- [ ] Media assets uploaded to storage directories
- [ ] YouTube OAuth configured and tokens saved
- [ ] OpenAI API key added
- [ ] FFmpeg installed and accessible
- [ ] Manual test run completed successfully
- [ ] Cron job configured
- [ ] Supervisor configured (recommended)
- [ ] Logs monitored and working
- [ ] Backup strategy in place

---

## 🎉 You're Ready!

Your YouTube video automation system is now set up. The cron job will run automatically and create/upload videos on schedule.

**What happens next:**

1. At scheduled time (e.g., 2 AM), cron triggers the command
2. System creates a unique 10-hour video with random assets
3. AI generates SEO-optimized title and description
4. Video is saved to `storage/app/outputs/finaloutpt123.mp4`
5. Check logs to monitor progress
6. Manually upload to YouTube or integrate upload job

**Manual trigger anytime:**

```bash
php artisan app:uplode-command
```

**Monitor in real-time:**

```bash
tail -f storage/logs/laravel.log
```

Happy automating! 🚀
