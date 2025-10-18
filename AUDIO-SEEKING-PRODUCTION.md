# Audio Seeking - Production Deployment

## The Issue
Audio seeking (scrubber) requires HTTP Range request support.

## Local Development
- PHP built-in server does NOT support range requests
- **Solution:** Use `stream.php` which handles range requests
- Files are served via: `stream.php?file=uploads/audio/[path]`

## Production (Apache/Nginx)
- Apache and Nginx BOTH support range requests by default
- Audio files can be served directly
- `.htaccess` includes explicit `Accept-Ranges: bytes` header for safety

## Files to Deploy
✅ `stream.php` - Range request handler (works in both dev and production)
✅ `.htaccess` - Updated with range request headers
✅ `self-hosted-episodes.php` - Uses stream.php for local files
✅ `assets/js/custom-audio-player.js` - Working scrubber code
✅ `assets/css/custom-audio-player.css` - Invisible scrubber overlay

## Testing in Production
1. Upload an episode with audio file
2. Play the episode
3. Click/drag the progress bar
4. Audio should seek to that position (not restart)

## If Seeking Doesn't Work in Production
Check server response headers:
```bash
curl -I -H "Range: bytes=0-1000" https://your-domain.com/uploads/audio/file.mp3
```

Should return:
```
HTTP/1.1 206 Partial Content
Accept-Ranges: bytes
Content-Range: bytes 0-1000/[filesize]
```

If it returns `200 OK` instead of `206 Partial Content`, the server doesn't support range requests.

## Fallback
If production server doesn't support range requests, all audio will go through `stream.php` which handles it properly.

---

**Status:** ✅ FIXED - Ready for production
**Tested:** Local development with stream.php
**Production:** Apache/Nginx will work natively, stream.php as fallback
