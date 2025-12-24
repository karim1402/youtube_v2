# YouTube Video Automation System

> **Automated video creation and upload system for baby white noise/sleep content**

Built with Laravel 12, FFmpeg, and AI (ChatGPT/Gemini) - Designed to run as scheduled cron jobs on servers.

---

## 🎯 What This System Does

This project automatically:
- ✅ Creates unique 10-hour baby sleep videos by combining backgrounds, effects, animations, and audio
- ✅ Generates SEO-optimized titles and descriptions using AI
- ✅ Creates custom thumbnails with overlays
- ✅ Compresses videos to optimal file sizes
- ✅ Manages YouTube OAuth authentication
- ✅ Runs 1-2 times daily via cron jobs (server automation)

**Main Use Case:** Server cron jobs that run automatically to produce YouTube content without manual intervention.

**Testing Use Case:** API endpoints available for testing new features before adding to automation.

---

## 🚀 Quick Start

### New to This Project? Start Here!

**👉 [QUICK_START.md](QUICK_START.md) - Get running in 10 minutes**

```bash
# 1. Install dependencies
composer install

# 2. Configure database
cp .env.example .env
php artisan migrate

# 3. Test the system
php artisan app:uplode-command

# 4. Set up cron job (runs 1-2 times daily)
0 2 * * * cd /path/to/project && php artisan app:uplode-command
```

---

## 📚 Complete Documentation

This project includes **comprehensive documentation** covering every aspect:

### 📖 Documentation Files

| File | Description | Best For |
|------|-------------|----------|
| **[QUICK_START.md](QUICK_START.md)** | 10-minute setup guide | First-time setup |
| **[DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md)** | Documentation navigator | Finding information |
| **[PROJECT_README.md](PROJECT_README.md)** | Complete system overview | Understanding architecture |
| **[COMMANDS_README.md](COMMANDS_README.md)** | Cron job commands ("fetchers") | Setting up automation |
| **[JOBS_README.md](JOBS_README.md)** | Background processing jobs | Understanding video pipeline |
| **[SERVICES_README.md](SERVICES_README.md)** | Services & helpers | Implementation details |
| **[API_README.md](API_README.md)** | API endpoints reference | Testing features |

### 🎓 Recommended Reading Order

1. **Start:** [QUICK_START.md](QUICK_START.md) - Get set up quickly
2. **Understand:** [PROJECT_README.md](PROJECT_README.md) - Learn the system
3. **Automate:** [COMMANDS_README.md](COMMANDS_README.md) - Set up cron jobs
4. **Deep Dive:** Other docs as needed

---

## 🏗️ System Architecture

```
Cron Job (1-2x daily)
    ↓
Command: app:uplode-command
    ↓
Job: UploadVideoJob (30-60 min)
    ↓
1. Compose video layers (FFmpeg chromakey)
2. Mix audio tracks
3. Merge video + audio
4. Compress to 150MB
5. Create 120 copies
6. Concatenate to 10-hour video
    ↓
Output: storage/app/outputs/finaloutpt123.mp4
```

**Key Technologies:**
- **Laravel 12** - Framework
- **FFmpeg** - Video/audio processing
- **ChatGPT/Gemini** - AI content generation
- **YouTube API** - Video uploads
- **MySQL/Redis** - Database & queues

---

## 🔧 System Requirements

- **PHP:** >= 8.2
- **Laravel:** 12.0
- **FFmpeg:** Latest version
- **MySQL:** 5.7+ or MariaDB 10.3+
- **Server:** For cron job automation

---

## 📊 Project Structure

```
├── app/
│   ├── Console/Commands/      # Cron job commands (fetchers)
│   ├── Jobs/                  # Background video processing
│   ├── Services/              # Business logic (white noise, etc.)
│   ├── Helpers/               # Video editing & AI helpers
│   └── Http/Controllers/Api/  # API endpoints (testing)
│
├── storage/app/               # Media assets & outputs
│   ├── backgrounds/           # Background videos
│   ├── effects/               # Video effects
│   ├── baby_greenscreen/      # Baby animations
│   ├── audio/                 # Audio tracks
│   ├── outputs/               # Final videos
│   └── white_noise/           # Generated audio
│
└── Documentation files (7 comprehensive guides)
```

---

## ⚡ Quick Commands

```bash
# Manual video creation
php artisan app:uplode-command

# Generate shuffled video
php artisan video:generate

# Test white noise generation
curl -X POST "http://localhost:8000/api/white-noise/generate/white" \
    -H "Content-Type: application/json" \
    -d '{"duration": 600, "volume": 0.4}'

# Check system health
curl "http://localhost:8000/api/white-noise/health"

# Monitor logs
tail -f storage/logs/laravel.log

# Check queue status
php artisan queue:monitor
```

---

## 🎬 Features

### Video Processing
- ✅ Multi-layer video compositing with chromakey (green screen)
- ✅ Random asset selection (4,224 unique combinations)
- ✅ Audio mixing and merging
- ✅ Intelligent video compression
- ✅ 10-hour video concatenation

### AI Integration
- ✅ ChatGPT/Gemini for SEO-optimized titles
- ✅ Auto-generated video descriptions
- ✅ Keyword optimization

### YouTube Integration
- ✅ OAuth 2.0 authentication
- ✅ Token refresh automation
- ✅ Chunked video uploads
- ✅ Custom thumbnail uploads

### White Noise Generation
- ✅ White, pink, and brown noise
- ✅ Unique audio signatures (avoid copyright)
- ✅ Configurable duration and volume
- ✅ FFmpeg-powered generation

### Automation
- ✅ Cron job support (1-2 times daily)
- ✅ Laravel queue system
- ✅ Supervisor configuration
- ✅ Comprehensive logging

---

## 🔐 Setup Essentials

### 1. Environment Configuration

```env
# Database
DB_CONNECTION=mysql
DB_DATABASE=youtube_video
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Queue
QUEUE_CONNECTION=database

# OpenAI API
OPENAI_API_KEY=sk-your-key-here
```

### 2. Google YouTube API

1. Create project at [Google Cloud Console](https://console.cloud.google.com/)
2. Enable YouTube Data API v3
3. Create OAuth 2.0 credentials
4. Download `google_credentials.json` → Place in `storage/app/`

### 3. Cron Job Setup

```cron
# Run daily at 2 AM
0 2 * * * cd /path/to/project && php artisan app:uplode-command >> /var/log/youtube-upload.log 2>&1
```

---

## 🧪 Testing Before Production

All features can be tested via API endpoints before adding to cron automation:

```bash
# Test white noise generation
curl -X POST "http://localhost:8000/api/white-noise/generate/white" \
    -d '{"duration": 60, "volume": 0.5}'

# Test thumbnail generation
curl -X POST "http://localhost:8000/api/image"

# Test video concatenation
php artisan video:generate
```

See **[API_README.md](API_README.md)** for complete API documentation.

---

## 📈 Performance

- **Video Creation:** 30-60 minutes per 10-hour video
- **File Size:** ~150MB optimized output
- **Asset Combinations:** 4,224 unique variations
- **Daily Quota:** YouTube API allows 10,000 units/day

---

## 🐛 Troubleshooting

### Quick Fixes

| Issue | Solution |
|-------|----------|
| FFmpeg not found | `sudo apt install ffmpeg` or `brew install ffmpeg` |
| Permission denied | `chmod -R 775 storage` |
| Queue stuck | `php artisan queue:restart` |
| Out of memory | Increase `memory_limit` in php.ini |

### Detailed Help

Every documentation file includes comprehensive troubleshooting sections:
- [QUICK_START.md](QUICK_START.md) - Setup issues
- [COMMANDS_README.md](COMMANDS_README.md) - Cron job issues
- [JOBS_README.md](JOBS_README.md) - Video processing issues
- [SERVICES_README.md](SERVICES_README.md) - FFmpeg issues
- [API_README.md](API_README.md) - API testing issues

---

## 📞 Getting Help

1. **Check logs:** `tail -f storage/logs/laravel.log`
2. **Search documentation:** Use [DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md)
3. **Run health check:** `curl http://localhost:8000/api/white-noise/health`
4. **Check queue:** `php artisan queue:monitor`

---

## 🎯 Next Steps

1. **Read** [QUICK_START.md](QUICK_START.md) for setup
2. **Test** the system manually
3. **Configure** cron jobs for automation
4. **Monitor** logs and outputs
5. **Customize** for your specific needs

---

## 📄 License

MIT License

---

## 🙏 Acknowledgments

Built with:
- **Laravel** - PHP Framework
- **FFmpeg** - Multimedia framework
- **Google YouTube API** - Video platform
- **OpenAI** - AI content generation

---

**📚 Full Documentation:** Start with [DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md) to navigate all guides.

**🚀 Quick Setup:** Jump to [QUICK_START.md](QUICK_START.md) to get running in 10 minutes.

**💡 Questions?** Check the comprehensive documentation - every file includes detailed examples and troubleshooting.
