# Embed Color & Branding Control - Implementation Plan

## Overview
Add a new customization section below existing controls in the iframe generator that allows users to customize:
- **Header Title Text** (e.g., "Podcast Player" → "My Show Player")
- **Header Icon** (Font Awesome icon picker)
- **Primary Color - Dark Mode** (currently #BB86FC purple)
- **Primary Color - Light Mode** (currently #6750A4 purple)
- **Automatic Shading** (buttons/gradients auto-generate based on chosen colors)

## Current State Analysis

### Files Involved
1. **iframe-generator.html** - Add new color/branding control section
2. **iframe-generator.js** - Color picker logic, preview updates, URL parameter generation
3. **iframe-generator.css** - Styling for new controls (color pickers, icon selector)
4. **index.html** - Header text and icon that will be customized
5. **styles.css** - CSS variables that need dynamic override

### Current Purple/Primary Usage in styles.css

#### Dark Mode (Default)
```css
--primary: #BB86FC;
--primary-dark: #9965F4;
```

#### Light Mode
```css
--primary: #6750A4;
--primary-dark: #7F67BE;
```

#### Elements Using Primary Color
Based on images and code review, purple appears in:
- **Header title** (`.app-title` - color: var(--primary))
- **Select label** ("SELECT PODCAST:" - color: var(--primary))
- **Badges** (episode count, dates - use primary color)
- **Buttons** (play/pause, controls - background: var(--primary))
- **Progress bars** (playback progress - background: var(--primary))
- **Links and hover states**
- **Scrollbar thumbs**
- **Active states** (dropdown selections, episode items)
- **Borders and accents**

### Current Header Structure (index.html)
```html
<h1 class="app-title">
    <i class="fa-solid fa-podcast"></i>
    Podcast Player
</h1>
```

## Implementation Plan

### Phase 1: UI Design (iframe-generator.html)

Add new section after existing controls (around line 180+):

```html
<!-- Column 4: Branding & Colors -->
<div class="control-column">
    <div class="control-section">
        <h2 class="section-title">
            <span class="material-icons">palette</span>
            Branding & Colors
        </h2>

        <!-- Header Text -->
        <div class="control-group">
            <div class="input-field">
                <label class="input-label">Header Title</label>
                <input type="text" id="header-title" value="Podcast Player" 
                       class="text-input" placeholder="Podcast Player">
            </div>
        </div>

        <!-- Font Awesome Icon Picker -->
        <div class="control-group">
            <div class="input-field">
                <label class="input-label">Header Icon (Font Awesome)</label>
                <input type="text" id="header-icon" value="fa-podcast" 
                       class="text-input" placeholder="fa-podcast">
                <small class="input-hint">
                    Enter Font Awesome icon name (e.g., fa-music, fa-microphone, fa-radio)
                    <a href="https://fontawesome.com/icons" target="_blank">Browse icons</a>
                </small>
            </div>
            <!-- Icon Preview -->
            <div class="icon-preview">
                <i class="fa-solid fa-podcast" id="icon-preview"></i>
            </div>
        </div>

        <!-- Dark Mode Color -->
        <div class="control-group">
            <div class="input-field">
                <label class="input-label">Primary Color (Dark Mode)</label>
                <div class="color-input-group">
                    <input type="color" id="primary-color-dark" value="#BB86FC" class="color-picker">
                    <input type="text" id="primary-color-dark-hex" value="#BB86FC" 
                           class="hex-input" pattern="^#[0-9A-Fa-f]{6}$">
                </div>
                <small class="input-hint">Default: #BB86FC (Purple)</small>
            </div>
        </div>

        <!-- Light Mode Color -->
        <div class="control-group">
            <div class="input-field">
                <label class="input-label">Primary Color (Light Mode)</label>
                <div class="color-input-group">
                    <input type="color" id="primary-color-light" value="#6750A4" class="color-picker">
                    <input type="text" id="primary-color-light-hex" value="#6750A4" 
                           class="hex-input" pattern="^#[0-9A-Fa-f]{6}$">
                </div>
                <small class="input-hint">Default: #6750A4 (Deep Purple)</small>
            </div>
        </div>

        <!-- Reset Button -->
        <button class="btn-reset" id="reset-branding">
            <span class="material-icons">refresh</span>
            Reset to Defaults
        </button>
    </div>
</div>
```

### Phase 2: CSS Styling (iframe-generator.css)

```css
/* Color Input Group */
.color-input-group {
    display: flex;
    gap: 12px;
    align-items: center;
}

.color-picker {
    width: 60px;
    height: 40px;
    border: 2px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: transform 0.2s;
}

.color-picker:hover {
    transform: scale(1.05);
}

.hex-input {
    flex: 1;
    padding: 10px 14px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-primary);
    font-family: 'Monaco', 'Courier New', monospace;
    font-size: 14px;
}

.hex-input:focus {
    outline: none;
    border-color: var(--primary);
}

/* Icon Preview */
.icon-preview {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 80px;
    height: 80px;
    background: var(--surface-elevated);
    border: 2px solid var(--border);
    border-radius: 12px;
    margin-top: 12px;
}

.icon-preview i {
    font-size: 40px;
    color: var(--primary);
}

/* Input Hint */
.input-hint {
    display: block;
    margin-top: 8px;
    font-size: 12px;
    color: var(--text-tertiary);
}

.input-hint a {
    color: var(--primary);
    text-decoration: none;
}

.input-hint a:hover {
    text-decoration: underline;
}

/* Reset Button */
.btn-reset {
    width: 100%;
    padding: 12px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s;
    margin-top: 20px;
}

.btn-reset:hover {
    background: var(--surface-elevated);
    border-color: var(--primary);
    color: var(--primary);
}
```

### Phase 3: JavaScript Logic (iframe-generator.js)

#### 3.1 Add Variables and Event Listeners

```javascript
// Branding & Color Controls
const headerTitleInput = document.getElementById('header-title');
const headerIconInput = document.getElementById('header-icon');
const iconPreview = document.getElementById('icon-preview');
const primaryColorDark = document.getElementById('primary-color-dark');
const primaryColorDarkHex = document.getElementById('primary-color-dark-hex');
const primaryColorLight = document.getElementById('primary-color-light');
const primaryColorLightHex = document.getElementById('primary-color-light-hex');
const resetBrandingBtn = document.getElementById('reset-branding');

// Event Listeners
headerTitleInput.addEventListener('input', updatePreview);
headerIconInput.addEventListener('input', handleIconChange);
primaryColorDark.addEventListener('input', handleDarkColorChange);
primaryColorDarkHex.addEventListener('input', handleDarkColorHexChange);
primaryColorLight.addEventListener('input', handleLightColorChange);
primaryColorLightHex.addEventListener('input', handleLightColorHexChange);
resetBrandingBtn.addEventListener('click', resetBranding);
```

#### 3.2 Color Handling Functions

```javascript
// Sync color picker with hex input (dark mode)
function handleDarkColorChange(e) {
    const color = e.target.value;
    primaryColorDarkHex.value = color;
    updatePreview();
}

function handleDarkColorHexChange(e) {
    const hex = e.target.value;
    if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
        primaryColorDark.value = hex;
        updatePreview();
    }
}

// Sync color picker with hex input (light mode)
function handleLightColorChange(e) {
    const color = e.target.value;
    primaryColorLightHex.value = color;
    updatePreview();
}

function handleLightColorHexChange(e) {
    const hex = e.target.value;
    if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
        primaryColorLight.value = hex;
        updatePreview();
    }
}

// Generate darker shade for hover states (20% darker)
function generateDarkerShade(hex) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    
    const newR = Math.max(0, Math.floor(r * 0.8));
    const newG = Math.max(0, Math.floor(g * 0.8));
    const newB = Math.max(0, Math.floor(b * 0.8));
    
    return `#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`;
}

// Icon handling
function handleIconChange(e) {
    const iconClass = e.target.value;
    // Update icon preview
    iconPreview.className = `fa-solid ${iconClass}`;
    updatePreview();
}

// Reset to defaults
function resetBranding() {
    headerTitleInput.value = 'Podcast Player';
    headerIconInput.value = 'fa-podcast';
    primaryColorDark.value = '#BB86FC';
    primaryColorDarkHex.value = '#BB86FC';
    primaryColorLight.value = '#6750A4';
    primaryColorLightHex.value = '#6750A4';
    iconPreview.className = 'fa-solid fa-podcast';
    updatePreview();
}
```

#### 3.3 Update Preview Function

```javascript
function updatePreview() {
    const iframe = document.getElementById('preview-iframe');
    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
    
    // Get custom values
    const headerTitle = headerTitleInput.value || 'Podcast Player';
    const headerIcon = headerIconInput.value || 'fa-podcast';
    const darkColor = primaryColorDark.value || '#BB86FC';
    const lightColor = primaryColorLight.value || '#6750A4';
    
    // Generate darker shades
    const darkColorShade = generateDarkerShade(darkColor);
    const lightColorShade = generateDarkerShade(lightColor);
    
    // Update header text and icon in iframe
    const iframeTitle = iframeDoc.querySelector('.app-title');
    if (iframeTitle) {
        const iconElement = iframeTitle.querySelector('i');
        if (iconElement) {
            iconElement.className = `fa-solid ${headerIcon}`;
        }
        const textNode = Array.from(iframeTitle.childNodes).find(node => node.nodeType === Node.TEXT_NODE);
        if (textNode) {
            textNode.textContent = headerTitle;
        }
    }
    
    // Inject CSS variables into iframe
    const styleId = 'custom-branding-styles';
    let styleElement = iframeDoc.getElementById(styleId);
    
    if (!styleElement) {
        styleElement = iframeDoc.createElement('style');
        styleElement.id = styleId;
        iframeDoc.head.appendChild(styleElement);
    }
    
    styleElement.textContent = `
        :root {
            --primary: ${darkColor} !important;
            --primary-dark: ${darkColorShade} !important;
        }
        
        [data-theme="light"] {
            --primary: ${lightColor} !important;
            --primary-dark: ${lightColorShade} !important;
        }
    `;
    
    // Update generated code
    updateGeneratedCode();
}
```

#### 3.4 Update Code Generation

```javascript
function updateGeneratedCode() {
    // ... existing code ...
    
    // Add branding parameters
    const headerTitle = headerTitleInput.value;
    const headerIcon = headerIconInput.value;
    const darkColor = primaryColorDark.value;
    const lightColor = primaryColorLight.value;
    
    if (headerTitle && headerTitle !== 'Podcast Player') {
        params.push(`title=${encodeURIComponent(headerTitle)}`);
    }
    if (headerIcon && headerIcon !== 'fa-podcast') {
        params.push(`icon=${encodeURIComponent(headerIcon)}`);
    }
    if (darkColor && darkColor !== '#BB86FC') {
        params.push(`darkColor=${encodeURIComponent(darkColor)}`);
    }
    if (lightColor && lightColor !== '#6750A4') {
        params.push(`lightColor=${encodeURIComponent(lightColor)}`);
    }
    
    // ... rest of code generation ...
}
```

### Phase 4: URL Parameter Handling (index.html script.js)

Add URL parameter parsing on page load:

```javascript
// Parse branding parameters from URL
function parseBrandingParams() {
    const params = new URLSearchParams(window.location.search);
    
    // Header title
    const title = params.get('title');
    if (title) {
        const titleElement = document.querySelector('.app-title');
        if (titleElement) {
            const textNode = Array.from(titleElement.childNodes).find(node => node.nodeType === Node.TEXT_NODE);
            if (textNode) {
                textNode.textContent = decodeURIComponent(title);
            }
        }
        document.title = decodeURIComponent(title);
    }
    
    // Header icon
    const icon = params.get('icon');
    if (icon) {
        const iconElement = document.querySelector('.app-title i');
        if (iconElement) {
            iconElement.className = `fa-solid ${decodeURIComponent(icon)}`;
        }
    }
    
    // Dark mode color
    const darkColor = params.get('darkColor');
    const lightColor = params.get('lightColor');
    
    if (darkColor || lightColor) {
        const styleId = 'url-branding-styles';
        let styleElement = document.getElementById(styleId);
        
        if (!styleElement) {
            styleElement = document.createElement('style');
            styleElement.id = styleId;
            document.head.appendChild(styleElement);
        }
        
        let css = ':root {';
        if (darkColor) {
            const darkShade = generateDarkerShade(decodeURIComponent(darkColor));
            css += `
                --primary: ${decodeURIComponent(darkColor)} !important;
                --primary-dark: ${darkShade} !important;
            `;
        }
        css += '}';
        
        if (lightColor) {
            const lightShade = generateDarkerShade(decodeURIComponent(lightColor));
            css += `
                [data-theme="light"] {
                    --primary: ${decodeURIComponent(lightColor)} !important;
                    --primary-dark: ${lightShade} !important;
                }
            `;
        }
        
        styleElement.textContent = css;
    }
}

// Helper function (same as in iframe-generator.js)
function generateDarkerShade(hex) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    
    const newR = Math.max(0, Math.floor(r * 0.8));
    const newG = Math.max(0, Math.floor(g * 0.8));
    const newB = Math.max(0, Math.floor(b * 0.8));
    
    return `#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`;
}

// Call on page load (add to existing initialization)
document.addEventListener('DOMContentLoaded', () => {
    parseBrandingParams();
    // ... rest of existing initialization ...
});
```

## Testing Plan

### Manual Testing Checklist

1. **Color Picker Sync**
   - [ ] Dark mode color picker changes hex input
   - [ ] Dark mode hex input changes color picker
   - [ ] Light mode color picker changes hex input
   - [ ] Light mode hex input changes color picker

2. **Preview Updates**
   - [ ] Header title changes in preview
   - [ ] Header icon changes in preview
   - [ ] Dark mode colors update in preview
   - [ ] Light mode colors update in preview (toggle theme)

3. **All Purple Elements Update**
   - [ ] Header title color
   - [ ] "SELECT PODCAST:" label
   - [ ] Episode badges
   - [ ] Play/pause button
   - [ ] Progress bar
   - [ ] Scrollbar thumbs
   - [ ] Active states
   - [ ] Hover states
   - [ ] Button shading/gradients

4. **Generated Code**
   - [ ] URL parameters include custom values
   - [ ] Copy to clipboard works
   - [ ] Pasted iframe loads with custom branding

5. **Reset Functionality**
   - [ ] Reset button restores all defaults
   - [ ] Preview updates after reset

6. **Edge Cases**
   - [ ] Invalid hex codes don't crash
   - [ ] Empty header title falls back to default
   - [ ] Invalid icon class shows default
   - [ ] Very light/dark colors still readable

### Test Scenarios

**Scenario 1: Custom Brand**
- Title: "My Radio Show"
- Icon: "fa-microphone"
- Dark: #FF6B9D (Pink)
- Light: #C2185B (Deep Pink)

**Scenario 2: Corporate Style**
- Title: "Company Podcast Hub"
- Icon: "fa-building"
- Dark: #4CAF50 (Green)
- Light: #2E7D32 (Dark Green)

**Scenario 3: Music Theme**
- Title: "Music Player"
- Icon: "fa-music"
- Dark: #2196F3 (Blue)
- Light: #1565C0 (Dark Blue)

## Implementation Order

1. ✅ **Planning Document** (this file)
2. **iframe-generator.html** - Add UI controls
3. **iframe-generator.css** - Style new controls
4. **iframe-generator.js** - Add logic and event handlers
5. **script.js** (embed/index.html) - Add URL parameter parsing
6. **Testing** - Local server, verify all elements
7. **Documentation** - Update README

## Files to Modify

- `/embed/iframe-generator.html` - Add branding controls section
- `/embed/iframe-generator.css` - Style color pickers and inputs
- `/embed/iframe-generator.js` - Color logic, preview updates, code generation
- `/embed/script.js` - URL parameter parsing on player load
- `/embed/README.md` - Document new customization options

## Estimated Time

- UI Design & HTML: 30 minutes
- CSS Styling: 20 minutes
- JavaScript Logic: 60 minutes
- URL Parameter Handling: 30 minutes
- Testing: 45 minutes
- **Total: ~3 hours**

## Success Criteria

✅ Users can customize header title text
✅ Users can change Font Awesome icon
✅ Users can set primary color for dark mode
✅ Users can set primary color for light mode
✅ Button shading auto-generates (20% darker)
✅ All purple elements update in real-time preview
✅ Generated iframe code includes custom parameters
✅ Embedded player loads with custom branding
✅ Reset button restores all defaults
✅ Color picker and hex input stay in sync
