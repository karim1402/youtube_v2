# 🎬 Intros Setup Guide - Numbered Files (1.mp4 to 15.mp4)

## ✅ Updated Intro System!

The system now looks for **numbered intro files** from `1.mp4` to `15.mp4` in `storage/app/intros/`.

---

## 📁 File Structure

### Required Format

```
storage/app/intros/
├── 1.mp4      ← Intro #1
├── 2.mp4      ← Intro #2
├── 3.mp4      ← Intro #3
├── 4.mp4      ← Intro #4
├── 5.mp4      ← Intro #5
├── 6.mp4      ← Intro #6
├── 7.mp4      ← Intro #7
├── 8.mp4      ← Intro #8
├── 9.mp4      ← Intro #9
├── 10.mp4     ← Intro #10
├── 11.mp4     ← Intro #11
├── 12.mp4     ← Intro #12
├── 13.mp4     ← Intro #13
├── 14.mp4     ← Intro #14
└── 15.mp4     ← Intro #15
```

**You can have anywhere from 1 to 15 intro files!**

---

## 🚀 Quick Setup

### Step 1: Create Directory

```bash
mkdir -p storage/app/intros
```

### Step 2: Add Your Intro Videos

```bash
# Copy your intros with numbered names
cp your_intro_1.mp4 storage/app/intros/1.mp4
cp your_intro_2.mp4 storage/app/intros/2.mp4
cp your_intro_3.mp4 storage/app/intros/3.mp4
# ... up to 15.mp4
```

### Step 3: Test

```bash
# Test video creation
php artisan test:optimized-pipeline --step=video --copies=10 --preset=fast

# Check logs for which intro was selected
tail -f storage/logs/laravel.log | grep "Selected random intro"
```

### Step 4: Verify

```bash
# Watch the video - should start with one of your intros
open storage/app/outputs/finaloutpt123.mp4
```

---

## 🎲 How Random Selection Works

### Example with 5 Intros

```
storage/app/intros/
├── 1.mp4   ← 20% chance
├── 2.mp4   ← 20% chance
├── 3.mp4   ← 20% chance
├── 4.mp4   ← 20% chance
└── 5.mp4   ← 20% chance

System randomly picks one each time!
```

### Example with 15 Intros (Maximum)

```
storage/app/intros/
├── 1.mp4   ← 6.7% chance each
├── 2.mp4
├── 3.mp4
├── ...
└── 15.mp4

Better variety with more intros!
```

---

## 📊 What Gets Logged

### Log Output Example

```
[2025-01-15 14:30:27] Selected random intro:
    - intro: 7.mp4
    - total_intros: 15
    - available_range: 1.mp4 to 15.mp4

[2025-01-15 14:30:27] Concatenating intro with main video...
[2025-01-15 14:30:30] Intro successfully added to video:
    - intro: 7.mp4
    - output: finaloutpt123.mp4
    - size_mb: 152.3 MB
```

**You'll know exactly which intro was selected!**

---

## ⚙️ Technical Specifications

### Recommended Intro Settings

```
Filename: 1.mp4 to 15.mp4 (numbered)
Resolution: 1920x1080 (match main video)
FPS: 25 (match main video)
Codec: H.264 (libx264)
Audio: AAC 128k (optional, or silent)
Duration: 5-15 seconds (recommended)
File Size: < 10 MB per intro
```

### Why These Settings?

- **Numbered names** → Easy to manage and identify
- **1920x1080** → Matches main video resolution
- **25 FPS** → Matches main video frame rate
- **H.264** → Compatible with stream copy (fast!)
- **Short duration** → Keeps viewers engaged

---

## 🔧 Flexible Setup Options

### Option 1: Just One Intro
```
storage/app/intros/
└── 1.mp4

Result: Same intro every time (100% chance)
```

### Option 2: Few Intros (3-5)
```
storage/app/intros/
├── 1.mp4
├── 2.mp4
└── 3.mp4

Result: Good variety with 33% chance each
```

### Option 3: Many Intros (10-15)
```
storage/app/intros/
├── 1.mp4
├── 2.mp4
├── ...
└── 15.mp4

Result: Maximum variety with ~6-7% chance each
```

### Option 4: Skipped Numbers (OK!)
```
storage/app/intros/
├── 1.mp4
├── 3.mp4   (2.mp4 missing - that's OK!)
├── 5.mp4   (4.mp4 missing - that's OK!)
└── 10.mp4  (6-9.mp4 missing - that's OK!)

Result: System only uses files that exist (1, 3, 5, 10)
```

---

## 🎨 Creating Intros

### Template Structure

```
[0-3 seconds]   → Logo/branding
[3-7 seconds]   → Title ("White Noise for Babies")
[7-10 seconds]  → Smooth transition/fade

Total: 10 seconds
```

### Example FFmpeg Command

```bash
# Create 10-second intro with text
ffmpeg -f lavfi -i color=c=black:s=1920x1080:d=10:r=25 \
  -vf "drawtext=text='White Noise for Babies':fontsize=80:fontcolor=white:x=(w-text_w)/2:y=(h-text_h)/2" \
  -c:v libx264 -crf 18 -pix_fmt yuv420p \
  storage/app/intros/1.mp4

# Create more variants with different text
ffmpeg -f lavfi -i color=c=#1a1a2e:s=1920x1080:d=10:r=25 \
  -vf "drawtext=text='Soothing Baby Sleep':fontsize=80:fontcolor=#eaeaea:x=(w-text_w)/2:y=(h-text_h)/2" \
  -c:v libx264 -crf 18 -pix_fmt yuv420p \
  storage/app/intros/2.mp4
```

---

## 🚨 Troubleshooting

### Issue: "No intro videos found"

**Check files exist:**
```bash
ls -la storage/app/intros/
```

**Solution:**
```bash
# Make sure files are named 1.mp4, 2.mp4, etc.
mv intro_one.mp4 storage/app/intros/1.mp4
mv intro_two.mp4 storage/app/intros/2.mp4
```

### Issue: Intro doesn't play / black screen

**Cause:** Format mismatch

**Solution:** Re-encode to match main video:
```bash
ffmpeg -i your_intro.mp4 \
  -c:v libx264 -crf 18 \
  -s 1920x1080 -r 25 \
  -c:a aac -b:a 128k \
  -pix_fmt yuv420p \
  storage/app/intros/1.mp4
```

### Issue: Concatenation fails

**Cause:** Invalid data in concat file

**Solution:** This is now fixed! The system automatically cleans up old concat files before creating new ones.

### Issue: Want to use different intro each day

**Solution:** You already have this! The system randomly selects from available intros automatically.

---

## 📊 Examples

### Example 1: Day/Night Themes

```
storage/app/intros/
├── 1.mp4   → Morning theme (sun)
├── 2.mp4   → Afternoon theme (bright)
├── 3.mp4   → Evening theme (sunset)
├── 4.mp4   → Night theme (moon)
└── 5.mp4   → Midnight theme (stars)

Result: Natural variety throughout the day
```

### Example 2: Seasonal Themes

```
storage/app/intros/
├── 1.mp4   → Spring theme
├── 2.mp4   → Summer theme
├── 3.mp4   → Autumn theme
└── 4.mp4   → Winter theme

Result: Seasonal variety (update intros per season)
```

### Example 3: Minimal Setup

```
storage/app/intros/
└── 1.mp4   → Simple logo intro

Result: Professional look with minimal effort
```

---

## ✅ Checklist

Before running production:

- [ ] Created `storage/app/intros/` directory
- [ ] Added at least one intro (1.mp4)
- [ ] Intros are 1920x1080, 25 FPS, H.264
- [ ] Tested with: `php artisan test:optimized-pipeline --step=video --copies=10 --preset=fast`
- [ ] Verified intro plays at start of video
- [ ] Checked logs show intro selection
- [ ] All intro files < 10 MB each
- [ ] Intros are 5-15 seconds long

---

## 🎯 Best Practices

### ✅ Do

- **Number files sequentially** (1.mp4, 2.mp4, 3.mp4...)
- **Keep intros short** (5-15 seconds)
- **Match video specs** (1920x1080, 25 FPS, H.264)
- **Test each intro** before production
- **Have 3-5 variants** for good variety
- **Update seasonally** for fresh content

### ❌ Don't

- Use random filenames (must be numbered!)
- Make intros too long (>30 seconds)
- Use different resolutions/FPS
- Forget to test concatenation
- Use very large files (>50 MB)
- Mix different codecs

---

## 🎬 Quick Commands Reference

```bash
# Check what intros you have
ls -lh storage/app/intros/

# Count intros
ls storage/app/intros/*.mp4 | wc -l

# Test video creation
php artisan test:optimized-pipeline --step=video --copies=10 --preset=fast

# Watch logs for intro selection
tail -f storage/logs/laravel.log | grep "intro"

# Check which intro was used in last run
grep "Selected random intro" storage/logs/laravel.log | tail -1

# Verify final video
open storage/app/outputs/finaloutpt123.mp4

# Production run
php artisan app:uplode-command
```

---

## 💡 Pro Tip

**Create intro variants for A/B testing:**

```
1.mp4 → Simple logo (control)
2.mp4 → Logo + "Subscribe" text
3.mp4 → Logo + "10 Hours" emphasis
4.mp4 → Logo + "Baby Sleep" emphasis
5.mp4 → Animated logo

Track which videos perform better on YouTube!
```

---

## ✅ Summary

**Numbered Intro System:**

✅ Files named: **1.mp4 to 15.mp4**
✅ Location: **storage/app/intros/**
✅ Selection: **Random each time**
✅ Range: **1 to 15 intros maximum**
✅ Flexible: **Can have any number from 1-15**
✅ Logging: **Shows which intro selected**
✅ Performance: **Fast stream copy (~2-3 seconds)**

**Just add your numbered intros and run:**

```bash
php artisan app:uplode-command
```

---

**Your videos will now have professional random intros! 🎬✨**
