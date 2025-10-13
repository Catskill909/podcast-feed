# View Feed Button - Sort Integration Fixed

## The Real Problem (Now I Understand!)

You were right - I was misunderstanding!

**The Issue:**
- Admin panel sort dropdown: Works (client-side)
- "View Feed" button: Ignored the sort selection, always showed default

**Example:**
1. You select "Oldest Episodes" in admin panel
2. Table shows AFGE first (correct!)
3. Click "View Feed" button
4. Feed modal shows Labor Radio first (wrong!)

## The Fix

Updated `showFeedModal()` to read the current sort selection and pass it to feed.php:

```javascript
async showFeedModal() {
    // Get current sort from SortManager
    let feedUrl = window.location.origin + '/feed.php';
    
    if (window.sortManager && window.sortManager.currentSort) {
        const sortKey = window.sortManager.currentSort;
        const sortMap = {
            'date-newest': { sort: 'episodes', order: 'desc' },
            'date-oldest': { sort: 'episodes', order: 'asc' },
            'title-az': { sort: 'title', order: 'asc' },
            'title-za': { sort: 'title', order: 'desc' },
            'status-active': { sort: 'status', order: 'desc' },
            'status-inactive': { sort: 'status', order: 'asc' }
        };
        
        const sortParams = sortMap[sortKey];
        feedUrl += `?sort=${sortParams.sort}&order=${sortParams.order}`;
    }
    
    // Load feed with correct sort parameters
    await this.loadFeedContent(feedUrl);
}
```

## How It Works Now

1. **Select "Oldest Episodes"** in dropdown
2. **Admin table** sorts to show AFGE first
3. **Click "View Feed"**
4. **Feed URL** becomes: `feed.php?sort=episodes&order=asc`
5. **Feed modal** shows AFGE first (correct!)

## Test It

1. **Refresh browser** (Cmd+Shift+R)
2. **Select "Oldest Episodes"** from sort dropdown
3. **Click "View Feed"** button
4. **Feed should show** AFGE first now!

## Sort Mappings

| Admin Panel Selection | Feed Parameters |
|----------------------|-----------------|
| Newest Episodes | `?sort=episodes&order=desc` |
| Oldest Episodes | `?sort=episodes&order=asc` |
| A-Z | `?sort=title&order=asc` |
| Z-A | `?sort=title&order=desc` |
| Active First | `?sort=status&order=desc` |
| Inactive First | `?sort=status&order=asc` |

## Result

✅ Admin panel sort and feed modal now stay in sync!
✅ "View Feed" button respects your sort selection
✅ Feed URL includes correct sort parameters
✅ Both visual (admin) and actual (feed) sorting work together

Sorry for the confusion - you were absolutely right!
