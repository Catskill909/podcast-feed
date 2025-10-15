# RSS Validation UI Mockups

## 🎨 Visual Design Mockups

### **Scenario 1: Perfect Feed (All Checks Pass)**

```
┌─────────────────────────────────────────────────────────────┐
│  RSS Feed Validation                                    [×] │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📡 Validating Feed...                                      │
│  ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 100%            │
│                                                             │
│  ✅ Feed Validated Successfully!                            │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  📻 My Awesome Podcast                              │   │
│  │  🔗 https://example.com/feed.xml                    │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ✅ Critical Checks (4/4 passed)                            │
│  ├─ ✓ Valid RSS 2.0 structure                              │
│  ├─ ✓ Feed accessible (HTTP 200, 1.2s)                     │
│  ├─ ✓ Cover image valid (1600×1600px, JPG)                 │
│  └─ ✓ 25 episodes found                                    │
│                                                             │
│  ℹ️  Feed Information                                       │
│  ├─ Type: RSS 2.0 with iTunes namespace                    │
│  ├─ Latest Episode: Today (Oct 15, 2025)                   │
│  ├─ Language: English (en-US)                              │
│  └─ Categories: Technology, Business                       │
│                                                             │
│                                    [Cancel]  [✓ Continue]  │
└─────────────────────────────────────────────────────────────┘
```

---

### **Scenario 2: Feed with Warnings (Can Import)**

```
┌─────────────────────────────────────────────────────────────┐
│  RSS Feed Validation                                    [×] │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ⚠️  Feed Has Warnings                                      │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  📻 Decent Podcast                                  │   │
│  │  🔗 https://example.com/podcast.xml                 │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ✅ Critical Checks (4/4 passed)                            │
│  ├─ ✓ Valid RSS 2.0 structure                              │
│  ├─ ✓ Feed accessible (HTTP 200, 3.5s)                     │
│  ├─ ✓ Cover image valid (1200×1200px, PNG)                 │
│  └─ ✓ 15 episodes found                                    │
│                                                             │
│  ⚠️  Warnings (3 issues)                                    │
│  ├─ ⚠ Missing iTunes namespace                             │
│  │   → Feed may not work properly in Apple Podcasts        │
│  │   → Add: xmlns:itunes="http://www.itunes.com/..."       │
│  │                                                          │
│  ├─ ⚠ Cover image is small (1200×1200px)                   │
│  │   → Recommended: 1400×1400px minimum                    │
│  │   → Ideal: 3000×3000px for best quality                 │
│  │                                                          │
│  └─ ⚠ Slow response time (3.5 seconds)                     │
│      → May cause timeouts during updates                   │
│      → Consider contacting podcast host                    │
│                                                             │
│  ℹ️  You can still import this feed, but it may have       │
│     compatibility issues with some podcast apps.           │
│                                                             │
│              [Cancel]  [Import Anyway]  [Fix Issues First] │
└─────────────────────────────────────────────────────────────┘
```

---

### **Scenario 3: Critical Errors (Cannot Import)**

```
┌─────────────────────────────────────────────────────────────┐
│  ❌ Cannot Import Feed                                  [×] │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  📻 Broken Podcast                                  │   │
│  │  🔗 https://badsite.com/broken.xml                  │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ❌ Critical Issues (3 found)                               │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  ✗ Cover image not found                            │   │
│  │                                                      │   │
│  │  Details:                                            │   │
│  │  • <image> tag is missing from feed                 │   │
│  │  • <itunes:image> tag is also missing               │   │
│  │                                                      │   │
│  │  How to fix:                                         │   │
│  │  1. Add <image><url>...</url></image> to feed       │   │
│  │  2. Ensure image URL is publicly accessible         │   │
│  │  3. Image must be 1400×1400px minimum               │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  ✗ Invalid XML structure                            │   │
│  │                                                      │   │
│  │  Details:                                            │   │
│  │  • Parse error at line 45, column 12                │   │
│  │  • Unclosed <item> tag                              │   │
│  │                                                      │   │
│  │  How to fix:                                         │   │
│  │  1. Validate feed at feedvalidator.org              │   │
│  │  2. Fix XML syntax errors                           │   │
│  │  3. Ensure all tags are properly closed             │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  ✗ No episodes found                                │   │
│  │                                                      │   │
│  │  Details:                                            │   │
│  │  • Feed must have at least one <item> element       │   │
│  │                                                      │   │
│  │  How to fix:                                         │   │
│  │  1. Publish at least one episode                    │   │
│  │  2. Ensure <item> tags are present in feed          │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  🔗 Helpful Resources:                                      │
│  • Feed Validator: https://feedvalidator.org               │
│  • iTunes Validator: https://podcastsconnect.apple.com     │
│  • RSS 2.0 Spec: https://cyber.harvard.edu/rss/rss.html    │
│                                                             │
│                      [Close]  [Try Different URL]          │
└─────────────────────────────────────────────────────────────┘
```

---

### **Scenario 4: In-Progress Validation**

```
┌─────────────────────────────────────────────────────────────┐
│  RSS Feed Validation                                    [×] │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│                                                             │
│                      🔍 Validating Feed...                  │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  https://example.com/feed.xml                       │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Progress:                                                  │
│  ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░░░░░░░░░░░░░░░░░░░░ 45%          │
│                                                             │
│  ✓ Fetching feed...                                         │
│  ✓ Parsing XML structure...                                 │
│  ⏳ Validating cover image...                               │
│  ⏺ Checking episodes...                                     │
│  ⏺ Verifying iTunes compatibility...                        │
│                                                             │
│                                              [Cancel]       │
└─────────────────────────────────────────────────────────────┘
```

---

### **Scenario 5: Quick Validation Summary (Inline)**

After validation, show compact summary in import modal:

```
┌─────────────────────────────────────────────────────────────┐
│  Import from RSS Feed                                   [×] │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Feed URL:                                                  │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ https://example.com/feed.xml                        │   │
│  └─────────────────────────────────────────────────────┘   │
│  [Validate Feed]                                            │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  ✅ Validation Passed                                │   │
│  │  • RSS 2.0 structure valid                          │   │
│  │  • Cover image: 1600×1600px ✓                       │   │
│  │  • 25 episodes found                                │   │
│  │  ⚠️  2 warnings (click to view)                      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Podcast Title:                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ My Awesome Podcast                                  │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Description:                                               │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ A great podcast about...                            │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  Cover Image: [Preview]                                     │
│  ┌─────────┐                                                │
│  │  🖼️     │  1600×1600px, 245 KB                          │
│  │ Image  │  ✓ Valid JPG                                   │
│  └─────────┘                                                │
│                                                             │
│                                    [Cancel]  [✓ Import]     │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎨 Color Scheme

### **Status Colors:**
- ✅ **Success/Pass:** `#10b981` (green)
- ⚠️ **Warning:** `#f59e0b` (amber)
- ❌ **Error/Fail:** `#ef4444` (red)
- ℹ️ **Info:** `#3b82f6` (blue)
- ⏳ **In Progress:** `#8b5cf6` (purple)

### **UI Elements:**
- **Background:** `#1a1a1a` (dark)
- **Card Background:** `#2a2a2a`
- **Border:** `#3a3a3a`
- **Text Primary:** `#ffffff`
- **Text Secondary:** `#9ca3af`

---

## 📱 Responsive Design

### **Desktop (>768px):**
- Modal width: 600px
- Full validation details visible
- Side-by-side buttons

### **Mobile (<768px):**
- Modal width: 95vw
- Stacked layout
- Full-width buttons
- Collapsible sections for warnings/errors

---

## ♿ Accessibility

- **Keyboard Navigation:** Tab through all elements
- **Screen Reader:** Proper ARIA labels
- **Focus Indicators:** Clear visual focus states
- **Color Contrast:** WCAG AA compliant
- **Error Messages:** Clear, actionable text

---

## 🎬 Animation & Transitions

### **Validation Progress:**
```css
.validation-progress {
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
```

### **Success State:**
```css
.validation-success {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
```

### **Error Shake:**
```css
.validation-error {
    animation: shake 0.5s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}
```

---

## 🔘 Button States

### **Primary Button (Continue/Import):**
```
Normal:   [✓ Continue]  (green background)
Hover:    [✓ Continue]  (brighter green)
Disabled: [✓ Continue]  (gray, not clickable)
Loading:  [⏳ Importing...] (spinner)
```

### **Secondary Button (Import Anyway):**
```
Normal:   [Import Anyway]  (amber background)
Hover:    [Import Anyway]  (brighter amber)
```

### **Tertiary Button (Cancel):**
```
Normal:   [Cancel]  (transparent, border only)
Hover:    [Cancel]  (light background)
```

---

**This provides a complete visual reference for implementing the validation UI!**
