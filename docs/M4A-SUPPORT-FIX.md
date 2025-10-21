# M4A Audio Format Support Fix

**Date:** October 20, 2025  
**Issue:** Podcast cloning failed for feeds containing M4A audio files  
**Status:** ✅ Fixed

## Problem

The podcast cloning feature was failing to import episodes from certain feeds (e.g., Anchor.fm podcasts) with the error:

```
Invalid file format. File must be an MP3 audio file.
```

### Root Cause

The `AudioUploader` class was hardcoded to only accept MP3 files, but many modern podcasts (especially those hosted on Anchor/Spotify) use **M4A** (MPEG-4 Audio) format instead of MP3.

Example feed that failed:
- `https://anchor.fm/s/57b2571c/podcast/rss` (A Third of Your Life podcast)
- All 12 episodes were M4A files with MIME type `audio/x-m4a`

## Solution

Added M4A audio format support to the `AudioUploader` class.

### Changes Made

**File Modified:** `includes/AudioUploader.php`

1. **Allowed Extensions** (Line 14)
   ```php
   private $allowedExtensions = ['mp3', 'm4a'];
   ```

2. **Allowed MIME Types** (Line 13)
   ```php
   private $allowedMimeTypes = [
       'audio/mpeg',           // MP3
       'audio/mp3',            // MP3 (alternative)
       'audio/x-mpeg',         // MP3 (alternative)
       'audio/x-m4a',          // M4A
       'audio/mp4',            // M4A (alternative)
       'audio/m4a',            // M4A (alternative)
       'application/octet-stream', // Generic binary
       'video/mp4'             // M4A sometimes detected as video/mp4
   ];
   ```

3. **Dynamic File Extension Handling** (Lines 53-61)
   - Changed from hardcoded `.mp3` extension to dynamic extension detection
   - Preserves original file format (MP3 or M4A)

4. **M4A Magic Number Validation** (Lines 356-358)
   - Added M4A file header detection (`ftyp` box signature)
   - Validates both MP3 and M4A file formats

5. **Updated Error Messages**
   - Changed "Only MP3 files are allowed" → "Only MP3 and M4A files are allowed"

6. **Updated .htaccess** (Lines 387-398)
   - Added M4A MIME type handling
   - Proper Content-Type headers for both formats

## Impact

### What Now Works
- ✅ Clone podcasts with MP3 episodes
- ✅ Clone podcasts with M4A episodes  
- ✅ Clone podcasts with mixed MP3/M4A episodes
- ✅ Upload M4A files manually through the UI
- ✅ Proper MIME type detection and validation
- ✅ Correct file extension preservation

### Backward Compatibility
- ✅ All existing MP3 functionality unchanged
- ✅ No breaking changes to existing podcasts
- ✅ Existing MP3 files continue to work

## Testing

To test the fix:

1. Navigate to **My Podcasts** → **Clone from RSS Feed**
2. Enter feed URL: `https://anchor.fm/s/57b2571c/podcast/rss`
3. Click "Validate Feed"
4. Click "Start Cloning"
5. All 12 M4A episodes should now import successfully

## Technical Details

### M4A Format
- **Full Name:** MPEG-4 Audio
- **Container:** MP4 container (same as video)
- **Codec:** Usually AAC (Advanced Audio Coding)
- **File Extension:** `.m4a`
- **MIME Types:** `audio/x-m4a`, `audio/mp4`, `video/mp4`
- **Magic Number:** `ftyp` box at offset 4

### Why M4A?
- Better compression than MP3 (smaller files, same quality)
- Native format for Apple platforms
- Default format for Anchor/Spotify podcast hosting
- Widely supported by modern podcast players

## Related Files

- `includes/AudioUploader.php` - Main audio upload handler (MODIFIED)
- `includes/PodcastAudioDownloader.php` - Downloads audio from URLs (unchanged)
- `includes/PodcastFeedCloner.php` - Orchestrates cloning (unchanged)

## Future Enhancements

Potential improvements:
- Add support for other formats (OGG, WAV, FLAC)
- Add audio transcoding (convert M4A → MP3 or vice versa)
- Add bitrate/quality detection
- Add ID3 tag extraction for M4A files

## Notes

- M4A files are stored with `.m4a` extension (not converted to MP3)
- RSS feeds will serve M4A files with correct MIME type
- Browser audio players support both MP3 and M4A natively
- File size limits (500MB) apply to both formats
