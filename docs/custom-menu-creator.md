# Custom Menu Creator - Feature Planning Document

**Created:** October 29, 2025  
**Status:** Planning Phase  
**Purpose:** Add customizable menu system to podcast app with icon/image support and dynamic menu items

---

## 1. OVERVIEW

### What We're Building
A custom menu management system that allows admins to:
- **Customize site branding** (title, icon/image, header text)
- **Add/remove/reorder menu items** dynamically
- **Configure menu item properties** (label, URL, icon, target)
- **Match existing design** seamlessly with Browse button style

### Where It Fits
- **Admin Panel:** New "Menu" link in admin navigation (between "Ads Manager" and "View Feed")
- **Public Site:** Menu items appear in header navigation next to "Browse" button
- **Integration:** Minimal changes to existing code, self-contained module

---

## 2. USER INTERFACE DESIGN

### Admin Interface (menu-manager.php)

#### Page Layout
```
┌─────────────────────────────────────────────────────────────┐
│  [← Back to Admin]                                          │
│                                                              │
│  🎨 Menu Manager                                            │
│  Customize your site branding and navigation menu           │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SITE BRANDING                                        │   │
│  │                                                       │   │
│  │ Site Title:  [Podcast Browser        ]              │   │
│  │                                                       │   │
│  │ Logo Icon:   ( ) Font Awesome Icon                   │   │
│  │              (•) Custom Image                        │   │
│  │                                                       │   │
│  │              [Upload Image] or [fa-podcast    ]      │   │
│  │              Preview: [🎧 Podcast Browser]           │   │
│  │                                                       │   │
│  │              [Save Branding]                         │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ MENU ITEMS                                           │   │
│  │                                                       │   │
│  │  [+ Add Menu Item]                                   │   │
│  │                                                       │   │
│  │  ┌─────────────────────────────────────────────┐    │   │
│  │  │ ⋮⋮ Browse                                    │    │   │
│  │  │    URL: index.php                            │    │   │
│  │  │    Icon: (none)                              │    │   │
│  │  │    [Edit] [Delete]                           │    │   │
│  │  └─────────────────────────────────────────────┘    │   │
│  │                                                       │   │
│  │  ┌─────────────────────────────────────────────┐    │   │
│  │  │ ⋮⋮ Admin                                     │    │   │
│  │  │    URL: admin.php                            │    │   │
│  │  │    Icon: fa-lock                             │    │   │
│  │  │    [Edit] [Delete]                           │    │   │
│  │  └─────────────────────────────────────────────┘    │   │
│  │                                                       │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

#### Add/Edit Menu Item Modal
```
┌─────────────────────────────────────────┐
│  Add Menu Item                    [×]   │
├─────────────────────────────────────────┤
│                                          │
│  Label: [About Us           ]           │
│                                          │
│  URL:   [/about.php         ]           │
│                                          │
│  Icon (optional):                        │
│  ( ) None                                │
│  (•) Font Awesome  [fa-info-circle]     │
│  ( ) Custom Image  [Upload]             │
│                                          │
│  Open in:                                │
│  (•) Same window                         │
│  ( ) New tab                             │
│                                          │
│  Preview: [ℹ️ About Us]                  │
│                                          │
│  [Cancel]  [Save Menu Item]             │
└─────────────────────────────────────────┘
```

### Public Site Integration (index.php header)

#### Current Header
```html
<nav>
  <ul class="nav-links">
    <li><a href="index.php" class="active">Browse</a></li>
    <li><a href="admin.php"><i class="fa-solid fa-lock"></i> Admin</a></li>
  </ul>
</nav>
```

#### Enhanced Header (with custom menu)
```html
<nav>
  <ul class="nav-links">
    <!-- Dynamic menu items from XML -->
    <li><a href="index.php" class="active">Browse</a></li>
    <li><a href="/about.php"><i class="fa-solid fa-info-circle"></i> About Us</a></li>
    <li><a href="/contact.php">Contact</a></li>
    <li><a href="admin.php"><i class="fa-solid fa-lock"></i> Admin</a></li>
  </ul>
</nav>
```

---

## 3. TECHNICAL ARCHITECTURE

### File Structure

#### New Files to Create
```
/menu-manager.php                    # Admin interface
/includes/MenuManager.php            # Business logic
/includes/MenuXMLHandler.php         # XML data storage
/assets/css/menu-manager.css         # Styling
/assets/js/menu-manager.js           # Frontend logic
/api/save-menu-branding.php          # Save branding settings
/api/save-menu-item.php              # Add/edit menu item
/api/delete-menu-item.php            # Delete menu item
/api/reorder-menu-items.php          # Drag-to-reorder
/data/menu-config.xml                # Data storage
/uploads/menu/                       # Logo/icon uploads
```

#### Files to Modify
```
/admin.php                           # Add "Menu" link to nav
/index.php                           # Load menu items in header
/assets/css/style.css                # Ensure nav-links styles work
```

### Data Structure (menu-config.xml)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<menu>
  <branding>
    <site_title>Podcast Browser</site_title>
    <logo_type>icon</logo_type><!-- icon|image -->
    <logo_icon>fa-podcast</logo_icon>
    <logo_image></logo_image><!-- path to uploaded image -->
  </branding>
  
  <items>
    <item>
      <id>1</id>
      <label>Browse</label>
      <url>index.php</url>
      <icon_type>none</icon_type><!-- none|fa|image -->
      <icon_value></icon_value>
      <target>_self</target><!-- _self|_blank -->
      <order>1</order>
      <active>1</active>
    </item>
    <item>
      <id>2</id>
      <label>Admin</label>
      <url>admin.php</url>
      <icon_type>fa</icon_type>
      <icon_value>fa-lock</icon_value>
      <target>_self</target>
      <order>2</order>
      <active>1</active>
    </item>
  </items>
</menu>
```

### PHP Class Structure

#### MenuManager.php
```php
class MenuManager {
    private $xmlHandler;
    
    // Branding methods
    public function getBranding()
    public function saveBranding($data, $logoFile = null)
    
    // Menu item methods
    public function getMenuItems($activeOnly = false)
    public function getMenuItem($id)
    public function addMenuItem($data, $iconFile = null)
    public function updateMenuItem($id, $data, $iconFile = null)
    public function deleteMenuItem($id)
    public function reorderMenuItems($order)
    public function toggleMenuItem($id, $active)
    
    // Utility methods
    public function getNextOrder()
    public function validateMenuItem($data)
}
```

#### MenuXMLHandler.php
```php
class MenuXMLHandler {
    private $xmlFile;
    
    public function __construct()
    public function getBranding()
    public function saveBranding($data)
    public function getItems()
    public function getItem($id)
    public function addItem($data)
    public function updateItem($id, $data)
    public function deleteItem($id)
    public function reorderItems($order)
    public function ensureXMLExists()
}
```

---

## 4. FEATURE ENHANCEMENTS

### Core Features (MVP)
- ✅ Custom site title and logo
- ✅ Add/edit/delete menu items
- ✅ Font Awesome icon support
- ✅ Custom image upload for icons
- ✅ Drag-to-reorder menu items
- ✅ URL validation
- ✅ Live preview in admin
- ✅ Match existing design system

### Enhanced Features (Nice-to-Have)
- 🔄 **Active state detection** - Auto-highlight current page
- 🔄 **Menu item visibility toggle** - Show/hide without deleting
- 🔄 **Dropdown/submenu support** - Nested menu items
- 🔄 **Mobile hamburger menu** - Responsive design
- 🔄 **Icon picker modal** - Browse Font Awesome icons visually
- 🔄 **External link indicator** - Show icon for external URLs
- 🔄 **Menu templates** - Pre-built menu configurations
- 🔄 **Import/Export** - Backup and restore menu config
- 🔄 **Analytics** - Track menu item clicks
- 🔄 **A/B Testing** - Test different menu configurations

### User Experience Improvements
- **Drag-and-drop reordering** with Sortable.js (like ads-manager)
- **Live preview** showing menu as it will appear
- **Validation feedback** for URLs and required fields
- **Confirmation modals** for delete actions
- **Toast notifications** for save/delete success
- **Keyboard shortcuts** for power users
- **Undo/Redo** for menu changes

---

## 5. DESIGN SPECIFICATIONS

### Styling Guidelines

#### Match Existing Design System
```css
/* Use existing CSS variables from style.css */
--bg-primary: #0d1117;
--bg-secondary: #161b22;
--bg-tertiary: #21262d;
--text-primary: #f0f6fc;
--accent-primary: #238636;
--border-primary: #30363d;
--font-heading: 'Oswald', sans-serif;
--font-family: 'Inter', sans-serif;
```

#### Menu Item Card Design
```css
.menu-item-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-primary);
  border-radius: var(--border-radius);
  padding: var(--spacing-md);
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
  cursor: grab;
  transition: var(--transition-base);
}

.menu-item-card:hover {
  background: var(--bg-hover);
  border-color: var(--accent-primary);
}
```

#### Public Site Menu Styling
```css
/* Match existing nav-links style */
.nav-links li a {
  color: var(--text-secondary);
  text-decoration: none;
  font-weight: 500;
  padding: var(--spacing-sm) var(--spacing-md);
  border-radius: var(--border-radius);
  transition: var(--transition-base);
  display: flex;
  align-items: center;
  gap: var(--spacing-xs);
}

.nav-links li a:hover,
.nav-links li a.active {
  color: var(--text-primary);
  background-color: var(--bg-hover);
}
```

---

## 6. INTEGRATION PLAN

### Phase 1: Backend Setup
1. Create `MenuXMLHandler.php` with XML operations
2. Create `MenuManager.php` with business logic
3. Create default `menu-config.xml` with current menu items
4. Create upload directory `/uploads/menu/`
5. Test XML read/write operations

### Phase 2: Admin Interface
1. Create `menu-manager.php` page structure
2. Add branding section (title, logo)
3. Add menu items list with drag-to-reorder
4. Create add/edit modal
5. Implement delete confirmation
6. Add live preview section
7. Create `menu-manager.css` matching design system
8. Create `menu-manager.js` for interactions

### Phase 3: API Endpoints
1. `/api/save-menu-branding.php`
2. `/api/save-menu-item.php`
3. `/api/delete-menu-item.php`
4. `/api/reorder-menu-items.php`
5. Add error handling and validation

### Phase 4: Public Site Integration
1. Modify `index.php` to load menu items
2. Modify `admin.php` to load menu items
3. Update header logo/title dynamically
4. Test active state detection
5. Test responsive behavior

### Phase 5: Testing & Polish
1. Test all CRUD operations
2. Test drag-to-reorder
3. Test image uploads
4. Test URL validation
5. Test on mobile devices
6. Add documentation
7. Create migration script for existing menus

---

## 7. BACKWARDS COMPATIBILITY

### Ensuring No Breaking Changes

#### Default Menu Items
If `menu-config.xml` doesn't exist, create it with current menu:
```php
// In MenuXMLHandler::ensureXMLExists()
$defaultMenu = [
    'branding' => [
        'site_title' => 'Podcast Browser',
        'logo_type' => 'icon',
        'logo_icon' => 'fa-podcast',
        'logo_image' => ''
    ],
    'items' => [
        [
            'id' => 1,
            'label' => 'Browse',
            'url' => 'index.php',
            'icon_type' => 'none',
            'icon_value' => '',
            'target' => '_self',
            'order' => 1,
            'active' => 1
        ],
        [
            'id' => 2,
            'label' => 'Admin',
            'url' => 'admin.php',
            'icon_type' => 'fa',
            'icon_value' => 'fa-lock',
            'target' => '_self',
            'order' => 2,
            'active' => 1
        ]
    ]
];
```

#### Fallback Rendering
If MenuManager fails, fall back to hardcoded menu:
```php
// In index.php
try {
    $menuManager = new MenuManager();
    $menuItems = $menuManager->getMenuItems(true);
} catch (Exception $e) {
    // Fallback to default menu
    $menuItems = [
        ['label' => 'Browse', 'url' => 'index.php', 'icon_type' => 'none'],
        ['label' => 'Admin', 'url' => 'admin.php', 'icon_type' => 'fa', 'icon_value' => 'fa-lock']
    ];
}
```

---

## 8. SECURITY CONSIDERATIONS

### Input Validation
- **URL validation:** Only allow relative URLs or whitelisted domains
- **XSS prevention:** Sanitize all user inputs with `htmlspecialchars()`
- **File upload security:** Validate image types, dimensions, file size
- **SQL injection:** N/A (using XML, but validate all data)

### Access Control
- **Admin-only access:** Require authentication for menu-manager.php
- **API protection:** Check session/auth on all API endpoints
- **File permissions:** Ensure uploads directory has correct permissions

### Best Practices
```php
// Sanitize user input
$label = htmlspecialchars($_POST['label'], ENT_QUOTES, 'UTF-8');

// Validate URL
function isValidUrl($url) {
    // Allow relative URLs
    if (strpos($url, '/') === 0) return true;
    
    // Allow whitelisted domains
    $parsed = parse_url($url);
    $allowedDomains = ['localhost', 'podcast.example.com'];
    return in_array($parsed['host'] ?? '', $allowedDomains);
}

// Validate icon class
function isValidFontAwesomeIcon($icon) {
    return preg_match('/^fa-[a-z0-9-]+$/', $icon);
}
```

---

## 9. TESTING CHECKLIST

### Functional Testing
- [ ] Create new menu item
- [ ] Edit existing menu item
- [ ] Delete menu item (with confirmation)
- [ ] Reorder menu items (drag-and-drop)
- [ ] Upload logo image
- [ ] Upload menu item icon image
- [ ] Change site title
- [ ] Toggle menu item visibility
- [ ] Validate URL formats
- [ ] Test Font Awesome icons
- [ ] Test external links (new tab)
- [ ] Test internal links (same window)

### Integration Testing
- [ ] Menu items appear on index.php
- [ ] Menu items appear on admin.php
- [ ] Active state highlights correctly
- [ ] Logo/title updates across all pages
- [ ] Menu survives page refresh
- [ ] Menu survives server restart

### UI/UX Testing
- [ ] Drag-and-drop is smooth
- [ ] Modals open/close correctly
- [ ] Forms validate properly
- [ ] Error messages are clear
- [ ] Success notifications appear
- [ ] Live preview updates in real-time
- [ ] Responsive on mobile
- [ ] Keyboard navigation works

### Edge Cases
- [ ] Empty menu (no items)
- [ ] Very long menu item labels
- [ ] Special characters in labels
- [ ] Invalid URLs
- [ ] Corrupted XML file
- [ ] Missing uploads directory
- [ ] Duplicate menu item IDs
- [ ] Circular URL references

---

## 10. DEPLOYMENT PLAN

### Local Development
1. Create all new files
2. Test thoroughly on localhost
3. Verify no conflicts with existing features
4. Document any manual steps needed

### Coolify Production
1. **Backup current state**
   - Export podcasts.xml
   - Backup uploads directory
   - Document current menu structure

2. **Deploy new files**
   - Upload new PHP files
   - Upload new CSS/JS files
   - Create menu-config.xml with defaults
   - Create uploads/menu directory

3. **Verify permissions**
   ```bash
   chmod 755 /data/uploads/menu
   chmod 644 /data/menu-config.xml
   ```

4. **Test on production**
   - Access menu-manager.php
   - Create test menu item
   - Verify it appears on public site
   - Delete test menu item

5. **Monitor for issues**
   - Check error logs
   - Test all existing features
   - Verify no performance impact

### Rollback Plan
If issues occur:
1. Remove "Menu" link from admin.php
2. Revert index.php header to hardcoded menu
3. Keep menu-manager.php accessible but hidden
4. Fix issues and redeploy

---

## 11. DOCUMENTATION

### User Guide (to be created)
- How to access Menu Manager
- How to change site branding
- How to add a menu item
- How to reorder menu items
- How to use Font Awesome icons
- How to upload custom icons
- Best practices for menu design

### Developer Guide (to be created)
- MenuManager API reference
- MenuXMLHandler methods
- Adding new menu item types
- Extending menu functionality
- Troubleshooting common issues

---

## 12. FUTURE ENHANCEMENTS

### Short-term (Next Release)
- Mobile hamburger menu for responsive design
- Icon picker modal (visual Font Awesome browser)
- Menu item visibility toggle (show/hide) ✅ **COMPLETED** (Oct 29, 2025)
- Active state auto-detection ✅ **COMPLETED** (Oct 29, 2025)
- **Menu Item Styling Customization:**
  - Custom font family selection
  - Custom font colors (default, hover, active states)
  - Custom button background colors
  - Border style options (solid, none, custom)
  - Padding/spacing adjustments
  - Font size options (small, medium, large)

### Medium-term
- Dropdown/submenu support (nested items)
- Menu templates (pre-built configurations)
- Import/Export menu config
- Menu item analytics (click tracking)
- **Advanced Styling:**
  - CSS class injection for menu items
  - Custom hover animations
  - Icon position (left, right, top, bottom)
  - Badge/label support (e.g., "New", "Beta")

### Long-term
- Multi-language menu support
- User role-based menu visibility
- Dynamic menu items (from database)
- Menu A/B testing
- Menu item scheduling (show/hide by date)

---

## 13. RISKS & MITIGATION

### Potential Risks

#### Risk 1: Breaking Existing Navigation
- **Impact:** High
- **Probability:** Medium
- **Mitigation:** 
  - Fallback to hardcoded menu if MenuManager fails
  - Extensive testing before deployment
  - Keep existing menu structure as default

#### Risk 2: XML Corruption
- **Impact:** Medium
- **Probability:** Low
- **Mitigation:**
  - Validate XML before saving
  - Create backup before each write
  - Implement XML repair function

#### Risk 3: Performance Impact
- **Impact:** Low
- **Probability:** Low
- **Mitigation:**
  - Cache menu items in session
  - Optimize XML parsing
  - Monitor page load times

#### Risk 4: Security Vulnerabilities
- **Impact:** High
- **Probability:** Low
- **Mitigation:**
  - Strict input validation
  - XSS prevention
  - File upload restrictions
  - Admin-only access control

---

## 14. SUCCESS METRICS

### How We'll Know It's Working
- ✅ Admin can customize site branding in < 2 minutes
- ✅ Admin can add new menu item in < 1 minute
- ✅ Menu items appear correctly on all pages
- ✅ No performance degradation (< 50ms overhead)
- ✅ Zero breaking changes to existing features
- ✅ Mobile-responsive menu works on all devices
- ✅ 100% of functional tests pass

---

## 15. IMPLEMENTATION TIMELINE

### Estimated Time: 6-8 hours

#### Day 1 (3-4 hours)
- [x] Create planning document (this file)
- [ ] Create MenuXMLHandler.php (1 hour)
- [ ] Create MenuManager.php (1 hour)
- [ ] Create default menu-config.xml (30 min)
- [ ] Create API endpoints (1 hour)

#### Day 2 (3-4 hours)
- [ ] Create menu-manager.php UI (2 hours)
- [ ] Create menu-manager.css (1 hour)
- [ ] Create menu-manager.js (1 hour)

#### Day 3 (Testing & Integration)
- [ ] Integrate with index.php (30 min)
- [ ] Integrate with admin.php (30 min)
- [ ] Testing and bug fixes (2 hours)
- [ ] Documentation (1 hour)

---

## 16. QUESTIONS TO RESOLVE

### Before Implementation
1. ✅ Should we support dropdown/nested menus in MVP? **Decision: No, add later**
2. ✅ Should we allow external URLs? **Decision: Yes, with validation**
3. ✅ Should we track menu item clicks? **Decision: No, add later**
4. ✅ Should we support menu item scheduling? **Decision: No, add later**
5. ✅ Should we add mobile hamburger menu now? **Decision: No, add later**

### During Implementation
- How should we handle very long menu item labels?
- Should we limit the number of menu items?
- Should we add menu item descriptions/tooltips?
- Should we support custom CSS classes per menu item?

---

## 17. REVIEW & APPROVAL

### Review Checklist
- [x] Planning document complete
- [ ] Technical architecture reviewed
- [ ] Security considerations addressed
- [ ] Integration plan validated
- [ ] Testing strategy defined
- [ ] Deployment plan ready
- [ ] Documentation outlined

### Next Steps
1. **Review this document** with stakeholder
2. **Get approval** to proceed with implementation
3. **Create GitHub issue** or task tracking
4. **Begin Phase 1** (Backend Setup)
5. **Iterate and improve** based on feedback

---

## APPENDIX A: Code Snippets

### Example: Loading Menu in Header
```php
// In index.php (around line 46)
<?php
require_once __DIR__ . '/includes/MenuManager.php';

try {
    $menuManager = new MenuManager();
    $branding = $menuManager->getBranding();
    $menuItems = $menuManager->getMenuItems(true); // Active only
} catch (Exception $e) {
    // Fallback to defaults
    $branding = [
        'site_title' => 'Podcast Browser',
        'logo_type' => 'icon',
        'logo_icon' => 'fa-podcast'
    ];
    $menuItems = [
        ['label' => 'Browse', 'url' => 'index.php', 'icon_type' => 'none'],
        ['label' => 'Admin', 'url' => 'admin.php', 'icon_type' => 'fa', 'icon_value' => 'fa-lock']
    ];
}
?>

<!-- Header -->
<header class="header">
    <div class="container">
        <div class="header-content">
            <a href="index.php" class="logo">
                <?php if ($branding['logo_type'] === 'image' && !empty($branding['logo_image'])): ?>
                    <img src="<?php echo htmlspecialchars($branding['logo_image']); ?>" 
                         alt="Logo" class="logo-image">
                <?php else: ?>
                    <i class="fa-solid <?php echo htmlspecialchars($branding['logo_icon']); ?> logo-icon"></i>
                <?php endif; ?>
                <span><?php echo htmlspecialchars($branding['site_title']); ?></span>
            </a>
            <nav>
                <ul class="nav-links">
                    <?php foreach ($menuItems as $item): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($item['url']); ?>" 
                               <?php echo $item['target'] === '_blank' ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                               <?php echo basename($_SERVER['PHP_SELF']) === basename($item['url']) ? 'class="active"' : ''; ?>>
                                <?php if ($item['icon_type'] === 'fa' && !empty($item['icon_value'])): ?>
                                    <i class="fa-solid <?php echo htmlspecialchars($item['icon_value']); ?>"></i>
                                <?php elseif ($item['icon_type'] === 'image' && !empty($item['icon_value'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['icon_value']); ?>" 
                                         alt="" class="menu-icon-image">
                                <?php endif; ?>
                                <?php echo htmlspecialchars($item['label']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    </div>
</header>
```

---

## APPENDIX B: Similar Features Reference

### Ads Manager Pattern
The Menu Manager will follow the same pattern as the Ads Manager:
- Drag-and-drop reordering with Sortable.js
- Material design toggle switches
- Modal-based add/edit interface
- Live preview section
- Consistent styling with dark theme
- XML-based data storage
- Modular PHP classes

### Files to Reference
- `/ads-manager.php` - UI structure
- `/includes/AdsManager.php` - Business logic pattern
- `/includes/AdsXMLHandler.php` - XML operations pattern
- `/assets/css/ads-manager.css` - Styling patterns
- `/assets/js/ads-manager.js` - JavaScript patterns

---

**END OF PLANNING DOCUMENT**

*This document will be updated as implementation progresses and new requirements emerge.*
