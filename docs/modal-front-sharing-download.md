# Modal Front-End: Sharing & Download Feature Research

**Date:** January 10, 2025  
**Status:** Research & Brainstorm  
**Scope:** Player modal download button improvements + new share functionality

---

## Current Implementation

### Download Button (player-modal.js lines 636-664)
```javascript
downloadEpisode(audioUrl, title) {
    const link = document.createElement('a');
    link.href = audioUrl;
    link.download = `${title}.mp3`;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
```

### Current Behavior
- Uses HTML5 `download` attribute on dynamically created `<a>` element
- **Problem:** `download` attribute only works for **same-origin URLs**
- Cross-origin audio files (most podcast episodes) will **open in new tab** instead of downloading
- Browser behavior varies: Safari often opens, Chrome/Firefox may download

---

## Part 1: Force Download Research

### The Core Problem
The HTML5 `download` attribute is **ignored for cross-origin URLs** due to browser security policies. Since podcast audio files are hosted on external CDNs (Libsyn, Podbean, Anchor, etc.), the download attribute has no effect.

### Solution Options

#### Option A: Fetch + Blob (Client-Side) ⭐ RECOMMENDED
Download the file via JavaScript, convert to blob, then trigger download.

```javascript
async function forceDownload(url, filename) {
    try {
        const response = await fetch(url, {
            method: 'GET',
            mode: 'cors'  // Requires CORS headers from server
        });
        const blob = await response.blob();
        const blobUrl = URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = filename;
        a.click();
        
        URL.revokeObjectURL(blobUrl);
    } catch (error) {
        // Fallback to current behavior
        window.open(url, '_blank');
    }
}
```

**Pros:**
- Works for cross-origin files (if CORS allows)
- True force download behavior
- No server-side changes needed

**Cons:**
- Requires CORS headers on audio host (most podcast CDNs have this)
- Downloads entire file to memory first (could be slow for large files)
- May fail silently if CORS blocked

#### Option B: Server-Side Proxy (PHP)
Create a PHP endpoint that fetches the file and serves it with `Content-Disposition: attachment`.

```php
// api/download-audio.php
<?php
$url = $_GET['url'] ?? '';
$filename = $_GET['filename'] ?? 'episode.mp3';

// Validate URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    exit('Invalid URL');
}

// Stream the file with download headers
header('Content-Type: audio/mpeg');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');

readfile($url);
```

**Pros:**
- Guaranteed download behavior
- Works regardless of CORS
- Can add analytics tracking

**Cons:**
- Server bandwidth usage (proxies all downloads)
- Potential timeout issues for large files
- More complex implementation

#### Option C: Hybrid Approach ⭐ BEST
Try client-side first, fall back to server proxy if CORS fails.

```javascript
async function downloadEpisode(url, filename) {
    try {
        // Try client-side download first
        const response = await fetch(url, { mode: 'cors' });
        if (!response.ok) throw new Error('Fetch failed');
        
        const blob = await response.blob();
        const blobUrl = URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = blobUrl;
        a.download = filename;
        a.click();
        
        URL.revokeObjectURL(blobUrl);
    } catch (error) {
        // Fallback to server proxy
        window.location.href = `/api/download-audio.php?url=${encodeURIComponent(url)}&filename=${encodeURIComponent(filename)}`;
    }
}
```

### Recommendation
**Start with Option A (Fetch + Blob)** - it works for most podcast CDNs and requires no server changes. Add Option B as fallback only if needed.

### Known CORS-Friendly Podcast Hosts
- Libsyn ✅
- Podbean ✅
- Anchor/Spotify ✅
- Buzzsprout ✅
- Transistor ✅
- Self-hosted (our own) ✅

---

## Part 2: Share Button Research

### Native Web Share API ⭐ RECOMMENDED FOR MOBILE

The **Web Share API** is a browser-native sharing mechanism that invokes the OS share sheet.

```javascript
async function shareEpisode(episode, podcast) {
    const shareData = {
        title: episode.title,
        text: `Listen to "${episode.title}" from ${podcast.title}`,
        url: episode.audio_url  // or a web page URL
    };
    
    if (navigator.share && navigator.canShare(shareData)) {
        try {
            await navigator.share(shareData);
            console.log('Shared successfully');
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.error('Share failed:', err);
            }
        }
    } else {
        // Fallback for unsupported browsers
        showShareFallback(shareData);
    }
}
```

#### Browser Support (as of 2024)
| Browser | Support |
|---------|---------|
| Chrome (Android) | ✅ Full |
| Safari (iOS) | ✅ Full |
| Safari (macOS) | ✅ Full (Sonoma+) |
| Chrome (Desktop) | ✅ Full (Windows/ChromeOS) |
| Firefox (Desktop) | ❌ No |
| Firefox (Android) | ✅ Full |
| Edge | ✅ Full |

**Key Points:**
- Requires HTTPS (or localhost)
- Must be triggered by user gesture (click)
- Can share text, URL, and files (files support varies)

### Fallback Options for Unsupported Browsers

#### Option 1: Custom Share Modal (No Dependencies)
Build a simple modal with direct share links:

```javascript
function showShareFallback(data) {
    const encodedUrl = encodeURIComponent(data.url);
    const encodedText = encodeURIComponent(data.text);
    const encodedTitle = encodeURIComponent(data.title);
    
    const shareLinks = {
        twitter: `https://twitter.com/intent/tweet?text=${encodedText}&url=${encodedUrl}`,
        facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`,
        linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
        email: `mailto:?subject=${encodedTitle}&body=${encodedText}%0A%0A${encodedUrl}`,
        whatsapp: `https://wa.me/?text=${encodedText}%20${encodedUrl}`,
        telegram: `https://t.me/share/url?url=${encodedUrl}&text=${encodedText}`,
        copy: 'clipboard'  // Copy to clipboard
    };
    
    // Show modal with these options
}
```

**Pros:**
- Zero dependencies
- Full control over UI
- Matches existing dark theme

**Cons:**
- More code to maintain
- Need to keep share URLs updated

#### Option 2: Sharer.js Library
Lightweight vanilla JS library for social sharing.

**GitHub:** https://github.com/ellisonleao/sharer.js  
**Size:** ~2KB minified  
**Dependencies:** None (vanilla JS)

```html
<script src="https://cdn.jsdelivr.net/npm/sharer.js@latest/sharer.min.js"></script>

<button data-sharer="twitter" 
        data-title="Check out this episode!" 
        data-url="https://example.com/episode">
    Share on Twitter
</button>
```

**Supported Platforms:**
- Twitter/X, Facebook, LinkedIn, Pinterest
- WhatsApp, Telegram, Email
- Reddit, Tumblr, Pocket
- And 20+ more

**Pros:**
- Tiny footprint
- Data-attribute driven (easy to use)
- Well maintained

**Cons:**
- External dependency
- Less control over popup behavior

#### Option 3: ShareThis / AddToAny (Third-Party Services)
Full-featured sharing widgets with analytics.

**Not Recommended** for this project because:
- Adds external tracking/cookies
- Heavier weight
- Less control over appearance
- Privacy concerns

### Recommendation
**Use Web Share API with custom fallback modal:**

1. **Primary:** Web Share API (mobile + modern desktop)
2. **Fallback:** Custom modal with direct share links (Firefox desktop, older browsers)
3. **Always include:** Copy to clipboard option

---

## Part 3: Implementation Plan

### Phase 1: Improve Download Button
1. Implement Fetch + Blob download method
2. Add loading indicator during download
3. Fallback to current behavior if fetch fails
4. Test with various podcast CDNs

### Phase 2: Add Share Button
1. Add share icon next to download button in episode card
2. Implement Web Share API for supported browsers
3. Build fallback share modal with:
   - Twitter/X
   - Facebook
   - WhatsApp
   - Email
   - Copy Link
4. Style to match existing dark theme

### Phase 3: Share Modal UI
```
┌─────────────────────────────────────┐
│  Share Episode                    ✕ │
├─────────────────────────────────────┤
│                                     │
│  [🐦 Twitter]  [📘 Facebook]        │
│                                     │
│  [💬 WhatsApp] [📧 Email]           │
│                                     │
│  [📋 Copy Link]                     │
│                                     │
│  ─────────────────────────────────  │
│  🔗 https://podcast.example.com/... │
│                                     │
└─────────────────────────────────────┘
```

---

## Files to Modify

### Download Improvements
- `assets/js/player-modal.js` - Update `downloadEpisode()` method
- `embed/script.js` - Update download handler (lines 880-892)
- (Optional) `api/download-audio.php` - Server-side proxy fallback

### Share Feature
- `assets/js/player-modal.js` - Add `shareEpisode()` method
- `assets/css/player-modal.css` - Share modal styles
- `embed/script.js` - Add share functionality to embed player
- `embed/styles.css` - Share modal styles for embed

---

## Questions to Resolve

1. **What should be shared?**
   - Episode audio URL directly?
   - Link to podcast website/page?
   - RSS feed URL?
   
2. **Share at podcast level or episode level?**
   - Currently `sharePodcast()` exists (copies RSS feed URL)
   - Add episode-level sharing?

3. **Include share button in embed player too?**
   - Yes - consistent experience
   - Consider iframe restrictions

4. **Analytics tracking for shares?**
   - Track share button clicks?
   - Which platforms are used?

---

## References

- [MDN Web Share API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Share_API)
- [Sharer.js Documentation](https://ellisonleao.github.io/sharer.js/)
- [Can I Use: Web Share API](https://caniuse.com/web-share)
- [Force Download with Fetch + Blob](https://plainenglish.io/blog/how-to-download-files-with-javascript)
