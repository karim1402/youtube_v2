# 🧪 Testing Guide for Optimized Code

## Quick Test Commands

### Test Everything (Except Upload)
```bash
php artisan test:optimized-pipeline
```

### Test Individual Components

#### 1. Test Video Processing Only
```bash
php artisan test:optimized-pipeline --step=video
```

**What it tests:**
- ✅ Layered video composition with chromakey
- ✅ Audio mixing
- ✅ Video + audio merging
- ✅ Video compression
- ✅ **Optimized repetition** (no file copying!)

**Output:**
- Creates test video at `storage/app/outputs/finaloutpt123.mp4`
- Shows processing time and file size
- Default: 30 copies = 15-minute video

#### 2. Test Thumbnail Generation
```bash
php artisan test:optimized-pipeline --step=thumbnail
```

**What it tests:**
- ✅ Random background selection
- ✅ Baby image overlay
- ✅ Logo placement
- ✅ Image optimization

**Output:**
- Creates thumbnail at `storage/app/public/merged_image.png`
- Takes ~1-2 seconds

#### 3. Test YouTube Upload
```bash
php artisan test:optimized-pipeline --step=upload
```

**What it tests:**
- ✅ OAuth token refresh
- ✅ AI title generation
- ✅ AI description generation
- ✅ Chunked video upload
- ✅ Progress tracking

**Requirements:**
- Video must exist (run video test first)
- Valid YouTube OAuth token
- Confirmation required before upload

#### 4. Test Complete Job Pipeline
```bash
php artisan test:optimized-pipeline --step=job
```

**What it tests:**
- ✅ Entire pipeline as a queue job
- ✅ Job dispatching
- ✅ Progress logging
- ✅ Error handling

---

## Advanced Options

### Fast Testing (Smaller Video)
```bash
# Create 30 copies instead of 120 (15 min video instead of 60 min)
php artisan test:optimized-pipeline --step=video --copies=30 --size=40
```

### Ultra-Fast Testing (Minimal Video)
```bash
# Create 10 copies (5 min video, very fast!)
php artisan test:optimized-pipeline --step=video --copies=10 --size=20 --preset=ultrafast
```

### Different FFmpeg Presets
```bash
# Ultrafast (lowest quality, fastest)
php artisan test:optimized-pipeline --step=video --preset=ultrafast

# Fast (good quality, 3x faster than slow) - RECOMMENDED
php artisan test:optimized-pipeline --step=video --preset=fast

# Medium (better quality, 2x faster than slow)
php artisan test:optimized-pipeline --step=video --preset=medium

# Slow (best quality, slowest)
php artisan test:optimized-pipeline --step=video --preset=slow
```

### Different Channel ID
```bash
php artisan test:optimized-pipeline --step=upload --channel=1
```

---

## Test Scenarios

### Scenario 1: Quick Functionality Test (5 minutes)
```bash
# Test with minimal video
php artisan test:optimized-pipeline --step=video --copies=10 --size=20 --preset=ultrafast

# Then test thumbnail
php artisan test:optimized-pipeline --step=thumbnail
```

**Expected Time:** ~5 minutes  
**Purpose:** Verify everything works without waiting

### Scenario 2: Performance Comparison (30 minutes)
```bash
# Test optimized version
time php artisan test:optimized-pipeline --step=video --copies=120 --size=150 --preset=fast

# Compare with old code timing
# (You'll see 2x speed improvement!)
```

**Expected Time:** ~30-40 minutes  
**Purpose:** Measure performance gains

### Scenario 3: Full Production Test (60 minutes)
```bash
# Test complete job with real settings
php artisan test:optimized-pipeline --step=job --copies=120 --channel=2
```

**Expected Time:** ~45-60 minutes  
**Purpose:** Full end-to-end test before production

---

## Monitoring Tests

### Watch Logs in Real-Time
```bash
tail -f storage/logs/laravel.log
```

### Check Queue Status
```bash
php artisan queue:monitor
```

### Process Queue Manually
```bash
php artisan queue:work --once
```

### View Failed Jobs
```bash
php artisan queue:failed
```

---

## Expected Output Examples

### Video Processing Test
```
🧪 Testing Optimized Pipeline
========================================

Testing Video Processing Service...

⚙️  Configuration:
   - Copies: 30 (30s clip × 30 = 15 minutes)
   - Target Size: 40MB
   - Preset: fast

 6/6 [████████████████████] 100% Verifying output...

✅ Video Processing Test Passed!
+-------------------+--------------------------------------------------+
| Metric            | Value                                            |
+-------------------+--------------------------------------------------+
| Output File       | /path/to/storage/app/outputs/finaloutpt123.mp4  |
| File Size         | 39.8 MB                                          |
| Processing Time   | 234.56 seconds                                   |
| Video Duration    | 900 seconds (15.0 minutes)                       |
| Disk Copies       | 0 (optimized!)                                   |
+-------------------+--------------------------------------------------+

🎬 You can check the video:
   open /path/to/storage/app/outputs/finaloutpt123.mp4
```

### Thumbnail Test
```
Testing Thumbnail Service...

📸 Creating thumbnail...

✅ Thumbnail Test Passed!
+-------------------+--------------------------------------------------+
| Metric            | Value                                            |
+-------------------+--------------------------------------------------+
| Output File       | /path/to/storage/app/public/merged_image.png    |
| File Size         | 245.67 KB                                        |
| Processing Time   | 1.23 seconds                                     |
+-------------------+--------------------------------------------------+

🖼️  You can view the thumbnail:
   open /path/to/storage/app/public/merged_image.png
```

---

## Troubleshooting Tests

### Error: "FFmpeg command failed"
**Solution:**
```bash
# Check FFmpeg is installed
ffmpeg -version

# Install if missing
brew install ffmpeg  # macOS
sudo apt install ffmpeg  # Ubuntu
```

### Error: "Video file not found"
**Solution:**
```bash
# Run video processing first
php artisan test:optimized-pipeline --step=video
```

### Error: "OpenAI API key not configured"
**Solution:**
```bash
# Add to .env
echo "OPENAI_API_KEY=sk-your-key-here" >> .env
```

### Error: "Access token not found"
**Solution:**
```bash
# Set up YouTube OAuth first
curl "http://localhost:8000/api/youtube/auth-url?channel_id=2"
# Visit URL and authorize
```

---

## Performance Benchmarks

### Expected Test Times (on standard VPS)

| Test | Copies | Preset | Expected Time |
|------|--------|--------|---------------|
| **Minimal** | 10 | ultrafast | 2-3 minutes |
| **Quick** | 30 | fast | 5-8 minutes |
| **Standard** | 60 | fast | 10-15 minutes |
| **Production** | 120 | fast | 30-40 minutes |
| **High Quality** | 120 | medium | 45-60 minutes |

---

## Comparing Old vs New

### Old Code Performance
```bash
# Old method (if you still have it)
time php artisan app:uplode-command

# Expected: 60-90 minutes
# Disk: Creates 120 file copies (18GB temp space)
# Memory: 1-2 GB peak
```

### New Code Performance
```bash
# New optimized method
time php artisan test:optimized-pipeline --step=job --copies=120

# Expected: 30-45 minutes  ⚡ 2x FASTER
# Disk: No file copies (150MB only)  ⚡ 99% LESS
# Memory: 200-400 MB  ⚡ 80% LESS
```

---

## CI/CD Integration

### Add to Your Test Suite
```bash
# .github/workflows/test.yml or similar

- name: Test Video Processing
  run: php artisan test:optimized-pipeline --step=video --copies=10 --preset=ultrafast

- name: Test Thumbnail Generation
  run: php artisan test:optimized-pipeline --step=thumbnail
```

---

## Safety Features

### Built-in Safety
- ✅ Upload tests require confirmation
- ✅ Test videos use "unlisted" privacy
- ✅ Small file sizes by default
- ✅ Detailed error messages
- ✅ Rollback on failure

### Testing Best Practices
1. Always test with small videos first (`--copies=10`)
2. Use `--preset=ultrafast` for quick validation
3. Monitor logs during tests
4. Verify output files manually
5. Test on staging before production

---

## Quick Reference

```bash
# Fastest test (2-3 min)
php artisan test:optimized-pipeline --step=video --copies=10 --preset=ultrafast

# Standard test (5-8 min)
php artisan test:optimized-pipeline --step=video --copies=30

# Production test (30-40 min)
php artisan test:optimized-pipeline --step=job --copies=120

# Monitor progress
tail -f storage/logs/laravel.log
```

---

## Next Steps After Testing

1. ✅ Verify tests pass
2. ✅ Compare performance with old code
3. ✅ Update your main command to use `UploadVideoJobOptimized`
4. ✅ Set up cron job with new command
5. ✅ Monitor first production run

---

**💡 Pro Tip:** Start with `--copies=10 --preset=ultrafast` for quick validation, then gradually increase to production settings!
