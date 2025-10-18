# Edit Functionality - Development Plan

## Overview
Add edit capabilities to the self-hosted podcast system for both podcast metadata and individual episodes.

## Current State
✅ Create new podcasts
✅ Add episodes to podcasts
✅ Delete episodes
✅ View episodes with audio player
❌ Edit podcast metadata
❌ Edit episode metadata

## Requirements

### 1. Edit Podcast Metadata
**Location:** `self-hosted-episodes.php` (podcast header section)

**Editable Fields:**
- Title
- Description
- Author
- Email
- Website URL
- Cover Image (replace existing)
- Category
- Subcategory
- Language
- Explicit content flag
- Copyright
- Podcast type (episodic/serial)
- Owner name
- Owner email
- Subtitle
- Keywords
- Complete flag
- Status (active/inactive)

**UI Approach:**
- Add "Edit Podcast" button next to podcast title
- Use collapsible form (same pattern as "Add Episode")
- Pre-populate all fields with current values
- Allow image replacement with preview
- Save updates to XML

### 2. Edit Episode Metadata
**Location:** `self-hosted-episodes.php` (episode cards)

**Editable Fields:**
- Title
- Description
- Audio file (replace existing)
- Audio URL (if using external)
- Episode cover image (replace existing)
- Publication date
- Episode number
- Season number
- Episode type (full/trailer/bonus)
- Explicit content flag
- Status (published/draft/scheduled)

**UI Approach:**
- Add "Edit" button to each episode card (next to Delete)
- Use collapsible inline form within the card OR
- Use modal/slide-out panel for editing
- Pre-populate all fields with current values
- Show current audio file info
- Allow audio file replacement
- Allow image replacement with preview

## Technical Architecture

### Backend (PHP)

#### New Files Needed:
- None (add to existing files)

#### Updates Needed:

**`self-hosted-episodes.php`:**
```php
// Add new POST actions:
case 'update_podcast':
    // Update podcast metadata
    $result = $manager->updatePodcast($podcastId, $data, $imageFile);
    break;

case 'update_episode':
    // Update episode metadata
    $result = $manager->updateEpisode($podcastId, $episodeId, $data, $imageFile, $audioFile);
    break;
```

**`includes/SelfHostedPodcastManager.php`:**
```php
// Already has updatePodcast() method - verify it works
// Already has updateEpisode() method - verify it works
// May need to add audio file replacement logic
```

**`includes/SelfHostedXMLHandler.php`:**
```php
// Already has updatePodcast() method - verify it works
// Already has updateEpisode() method - verify it works
```

### Frontend (HTML/JS)

#### Podcast Edit Form
**Pattern:** Collapsible form (same as "Add Episode")

**HTML Structure:**
```html
<div id="editPodcastForm" style="display: none;">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_podcast">
        <!-- All podcast fields pre-populated -->
        <!-- Current cover image preview -->
        <!-- Option to replace cover image -->
        <button type="submit">Save Changes</button>
        <button type="button" onclick="toggleEditPodcastForm()">Cancel</button>
    </form>
</div>
```

**JavaScript:**
```javascript
function toggleEditPodcastForm() {
    // Show/hide form
    // Scroll into view
}
```

#### Episode Edit Form
**Pattern:** Inline collapsible form within episode card

**HTML Structure:**
```html
<!-- In each episode card -->
<div class="episode-edit-form" id="editForm_<?php echo $episode['id']; ?>" style="display: none;">
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_episode">
        <input type="hidden" name="episode_id" value="<?php echo $episode['id']; ?>">
        <!-- All episode fields pre-populated -->
        <!-- Current audio file info -->
        <!-- Option to replace audio file -->
        <!-- Current cover image preview -->
        <!-- Option to replace cover image -->
        <button type="submit">Save Changes</button>
        <button type="button" onclick="toggleEditEpisodeForm('<?php echo $episode['id']; ?>')">Cancel</button>
    </form>
</div>
```

**JavaScript:**
```javascript
function toggleEditEpisodeForm(episodeId) {
    // Show/hide form for specific episode
    // Hide episode display, show form
    // Scroll into view
}
```

## UI/UX Considerations

### Podcast Edit
1. **Button Placement:** Next to "Add New Episode" button
2. **Form Style:** Match "Add Episode" form styling
3. **Image Preview:** Show current cover, allow replacement
4. **Validation:** Same as create form
5. **Feedback:** Success/error messages

### Episode Edit
1. **Button Placement:** In episode actions (next to Download/Delete)
2. **Form Style:** Inline within episode card OR modal overlay
3. **Audio Preview:** Show current audio info, allow replacement
4. **Image Preview:** Show current cover, allow replacement
5. **Validation:** Same as add episode form
6. **Feedback:** Success/error messages

## File Replacement Strategy

### Audio File Replacement
**Options:**
1. **Keep old file, upload new:** Rename old file with timestamp backup
2. **Delete old, upload new:** Remove old file from server
3. **Keep both:** Store multiple versions

**Recommended:** Delete old, upload new (saves disk space)

**Implementation:**
```php
if ($newAudioFile) {
    // Delete old audio file
    if (!empty($episode['audio_url']) && strpos($episode['audio_url'], 'uploads/audio/') !== false) {
        $oldPath = extractPathFromUrl($episode['audio_url']);
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
    }
    
    // Upload new file
    $audioResult = $audioUploader->uploadAudio($newAudioFile, $podcastId, $episodeId);
    $episodeData['audio_url'] = $audioResult['url'];
}
```

### Image File Replacement
**Same strategy as audio:** Delete old, upload new

## Validation

### Podcast Edit
- Title required
- Email format validation
- URL format validation
- Image dimensions (1400x1400 to 3000x3000)
- Image size (max 2MB)

### Episode Edit
- Title required
- Audio file OR URL required (if replacing)
- Image dimensions (if replacing)
- Date format validation

## Error Handling

### Scenarios:
1. **File upload fails:** Show error, keep old file
2. **Validation fails:** Show errors, don't save
3. **XML save fails:** Rollback file changes
4. **Image too large:** Show error, don't save
5. **Audio format invalid:** Show error, don't save

### User Feedback:
- Success: Green flash message "Podcast updated successfully"
- Error: Red flash message with specific error
- Keep form open on error (don't lose data)

## Implementation Steps

### Phase 1: Podcast Edit (Simpler)
1. Add "Edit Podcast" button to header
2. Create collapsible edit form (copy create form structure)
3. Pre-populate form fields with current values
4. Add `update_podcast` POST handler
5. Test update without file changes
6. Add cover image replacement
7. Test complete flow

### Phase 2: Episode Edit (More Complex)
1. Add "Edit" button to each episode card
2. Create inline edit form (copy add episode structure)
3. Pre-populate form fields with current values
4. Add `update_episode` POST handler
5. Test update without file changes
6. Add audio file replacement logic
7. Add cover image replacement
8. Test complete flow

### Phase 3: Polish
1. Add loading states
2. Add confirmation dialogs for file replacement
3. Improve error messages
4. Add keyboard shortcuts (ESC to cancel)
5. Mobile responsive testing

## Testing Checklist

### Podcast Edit
- [ ] Edit title only
- [ ] Edit description only
- [ ] Replace cover image
- [ ] Edit multiple fields at once
- [ ] Cancel without saving
- [ ] Validation errors display correctly
- [ ] Success message shows
- [ ] Changes persist after refresh

### Episode Edit
- [ ] Edit title only
- [ ] Edit description only
- [ ] Replace audio file
- [ ] Replace cover image
- [ ] Edit metadata (episode number, etc.)
- [ ] Cancel without saving
- [ ] Validation errors display correctly
- [ ] Success message shows
- [ ] Changes persist after refresh
- [ ] Audio player uses new file
- [ ] Old files deleted from server

## Security Considerations

1. **Authentication:** Verify user is logged in
2. **Authorization:** Verify user owns the podcast
3. **File Upload:** Validate file types and sizes
4. **Path Traversal:** Sanitize file paths
5. **XSS:** Escape all output
6. **CSRF:** Use tokens (already in place)

## Database/Storage Impact

### XML Updates:
- Podcast: Update `<podcast>` node
- Episode: Update `<episode>` node within podcast
- Timestamp: Update `updated_date` field

### File System:
- Old audio files: Deleted
- Old images: Deleted
- New files: Uploaded to same directory structure

## Rollback Strategy

If update fails:
1. Keep old files
2. Don't update XML
3. Show error to user
4. Allow retry

## Future Enhancements

1. **Bulk Edit:** Edit multiple episodes at once
2. **History:** Track edit history
3. **Undo:** Revert to previous version
4. **Draft Mode:** Save without publishing
5. **Preview:** Preview changes before saving

## Estimated Time

- **Podcast Edit:** 1-2 hours
- **Episode Edit:** 2-3 hours
- **Testing & Polish:** 1 hour
- **Total:** 4-6 hours

## Dependencies

- ✅ Working create podcast form
- ✅ Working add episode form
- ✅ Working file upload system
- ✅ Working XML handler
- ✅ Working image uploader
- ✅ Working audio uploader

## Success Criteria

✅ User can edit podcast metadata
✅ User can replace podcast cover image
✅ User can edit episode metadata
✅ User can replace episode audio file
✅ User can replace episode cover image
✅ Old files are cleaned up
✅ Changes persist correctly
✅ Error handling works properly
✅ UI is intuitive and matches existing design

---

**Status:** 📋 PLANNED - Ready to implement
**Priority:** HIGH - Core functionality
**Complexity:** MEDIUM - Uses existing patterns
**Start:** New thread recommended (this one is 160k+ tokens)
