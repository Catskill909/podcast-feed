# M4A Support - Deployment Checklist

## Issue
Podcast cloning fails for feeds with M4A audio files (e.g., Anchor.fm/Spotify podcasts).

## Root Cause
Three separate validation checks were blocking M4A files:
1. AudioUploader only accepted MP3
2. PodcastAudioDownloader only validated MP3 URLs
3. **SelfHostedPodcastManager was re-validating already-uploaded local files**

## Files Modified

### 1. includes/AudioUploader.php
**Changes:**
- Line 13-14: Added M4A to allowed MIME types and extensions
- Line 25: Added logging for supported formats
- Lines 53-61: Dynamic extension detection (preserves .m4a)
- Lines 88: Pass extension to getAudioInfo
- Lines 110-126: Updated deleteAudio to handle both extensions
- Lines 158-190: Updated getAudioInfo to handle both extensions
- Lines 310-315: Updated error messages to mention M4A
- Lines 324-343: Added M4A MIME types and validation
- Lines 345-367: Added M4A magic number validation
- Lines 387-398: Updated .htaccess for M4A
- Line 453: Updated storage stats to count M4A files

### 2. includes/PodcastAudioDownloader.php
**Changes:**
- Lines 63-65: Dynamic extension detection from URL
- Lines 176-180: Updated URL validation regex to accept .m4a

### 3. includes/SelfHostedPodcastManager.php ⭐ CRITICAL
**Changes:**
- Lines 525-540: **Skip URL validation for local uploaded files**
  - This is the KEY fix that makes cloning work
  - Detects if audio_url points to local uploads directory
  - Skips external URL validation for already-uploaded files

## Deployment Steps

1. **Commit all changes:**
   ```bash
   git add includes/AudioUploader.php
   git add includes/PodcastAudioDownloader.php
   git add includes/SelfHostedPodcastManager.php
   git commit -m "Add M4A audio format support for podcast cloning"
   ```

2. **Push to repository:**
   ```bash
   git push origin main
   ```

3. **Deploy via Coolify:**
   - Coolify will auto-deploy on push (if configured)
   - OR manually trigger deployment in Coolify dashboard

4. **Verify deployment:**
   - Check Coolify build logs for success
   - SSH into server and verify file timestamps
   - Clear PHP OPcache if needed:
     ```bash
     php -r "opcache_reset();"
     ```

5. **Test on production:**
   - Navigate to: https://podcast.supersoul.top/self-hosted-podcasts.php
   - Click "Clone from RSS Feed"
   - Test URL: `https://anchor.fm/s/57b2571c/podcast/rss`
   - Should successfully clone all 12 M4A episodes

## Testing Checklist

- [ ] MP3 podcasts still clone successfully
- [ ] M4A podcasts clone successfully (Anchor.fm feed)
- [ ] Mixed MP3/M4A podcasts work
- [ ] Manual M4A upload works
- [ ] Existing podcasts still play correctly
- [ ] RSS feeds serve M4A files with correct MIME type

## Rollback Plan

If issues occur:
1. Revert commit: `git revert HEAD`
2. Push: `git push origin main`
3. Coolify will auto-deploy previous version

## Documentation

- Created: `docs/M4A-SUPPORT-FIX.md`
- Updated: This deployment checklist

## Success Criteria

✅ Anchor.fm podcast clones with 12/12 episodes (0 failed)
✅ M4A files play in browser
✅ RSS feed validates in podcast players
✅ No regression in MP3 functionality
