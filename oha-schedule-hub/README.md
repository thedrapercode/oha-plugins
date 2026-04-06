# OHA Convention Schedule Hub

A comprehensive WordPress plugin for managing and displaying class schedules for the Ohio Haunters Association Convention. Built specifically for easy updates, multiple viewing options, and PWA offline support.

## Features

### Core Functionality
- ✅ **Custom Post Type** for classes with full meta data management
- ✅ **Easy Admin Interface** with quick-add capabilities
- ✅ **Multiple View Modes**: Timeline, Grid, List, and Compact
- ✅ **Advanced Filtering**: By day, instructor, track/category
- ✅ **Real-time Search**: Instant class lookup
- ✅ **Bulk Import**: CSV and Excel import capabilities
- ✅ **Export Functionality**: Download schedule as CSV
- ✅ **PWA Compatible**: Works offline in your PWA
- ✅ **Print-Friendly**: Optimized print layout
- ✅ **Responsive Design**: Mobile, tablet, and desktop support

### Track Management
- Color-coded tracks (Acting, Technical, Business, Makeup, etc.)
- Custom track creation
- Track-based filtering

### Class Details
- Start and end times
- Instructor information
- Room/location assignments
- Capacity limits
- Skill level (Beginner, Intermediate, Advanced, All Levels)
- Materials needed
- Internal notes (admin only)
- Full descriptions

### Display Options
- **Timeline View**: Classic chronological layout with times on left
- **Grid View**: Card-based responsive grid
- **List View**: Table format for detailed information
- **Compact View**: Minimal, print-optimized layout

## Installation

1. Upload the `oha-schedule-hub` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Schedule > Settings** to configure options
4. Start adding classes!

## Quick Start

### Adding Your First Class

**Method 1: WordPress Admin**
1. Go to **Schedule > Add Class**
2. Fill in class details
3. Set day, instructor, and track
4. Click **Publish**

**Method 2: Bulk Import (Recommended for initial setup)**
1. Go to **Schedule > Bulk Import**
2. Click **"Import 2026 Schedule"** to load your uploaded schedule
3. Or paste CSV data / upload Excel file
4. Click **Import**

**Method 3: Frontend Quick Admin**
1. Create a page with `[oha_schedule_admin]` shortcode
2. Click **"+ Add New Class"** button
3. Fill in the modal form
4. Click **Save**

### Displaying the Schedule

#### Basic Display
```
[oha_schedule]
```

#### Timeline View
```
[oha_schedule view="timeline"]
```

#### Grid View
```
[oha_schedule view="grid"]
```

#### Saturday Only
```
[oha_schedule day="Saturday"]
```

#### Specific Instructor
```
[oha_schedule instructor="Allen Redmon"]
```

#### Hide Filters
```
[oha_schedule show_filters="0"]
```

## Shortcodes

### Main Display Shortcode
```
[oha_schedule]
```

**Available Attributes:**
- `view` - Timeline, grid, list, or compact (default: timeline)
- `day` - Filter by specific day
- `track` - Filter by track/category
- `instructor` - Filter by instructor name
- `show_filters` - Show/hide filter controls (1 or 0)
- `show_search` - Show/hide search box (1 or 0)

**Examples:**
```
[oha_schedule view="grid" day="Saturday"]
[oha_schedule view="list" show_filters="0"]
[oha_schedule view="compact" track="Acting"]
```

### Admin Shortcode
```
[oha_schedule_admin]
```

Displays a frontend management interface for users with `edit_posts` capability. Perfect for board members to quickly update the schedule without accessing the WordPress admin.

## Settings

Navigate to **Schedule > Settings** to configure:

- **Time Format**: 12-hour or 24-hour display
- **Default Duration**: Default class length (75 minutes)
- **Setup/Teardown Times**: Buffer times between classes
- **Default View**: Which view loads initially
- **Display Options**: Toggle search, filters, descriptions, print button

## Track Colors

The plugin includes pre-defined color schemes for different tracks:

- **Acting** - Red
- **Technical** - Blue
- **Business** - Green
- **Makeup** - Orange
- **Default** - Gray

You can customize track colors by adding CSS to your theme:

```css
.track-yourtrack { border-left-color: #your-color; }
.track-yourtrack .oha-class-track { background: #your-bg; color: #your-text; }
```

## Import Format

### CSV Format
```
Title, Instructor, Day, Start Time, End Time, Room, Description
Lighting Controllers, Allen Redmon, Saturday, 10:00, 11:15, Room A, Learn about lighting
```

### Excel Format
Create an Excel file with these columns:
- Title
- Instructor
- Day (Saturday/Sunday)
- Start Time (HH:MM format)
- End Time (HH:MM format)
- Room/Location
- Description (optional)
- Track/Category (optional)
- Level (optional)
- Capacity (optional)

## PWA Integration

The plugin is fully compatible with your OHA Convention PWA:

1. **Offline Access**: Schedule data is cached in localStorage
2. **Auto-refresh**: Updates every 5 minutes when online
3. **Offline Indicator**: Shows banner when viewing cached data
4. **Service Worker Compatible**: Works with your existing PWA setup

## For Developers

### Custom Track Colors

Add to your theme's `functions.php`:

```php
add_filter('oha_schedule_track_colors', function($colors) {
    $colors['special-effects'] = '#9333ea'; // Purple
    return $colors;
});
```

### Custom View Template

Create a custom view in your theme:
`your-theme/oha-schedule/views/custom-view.php`

### Hooks Available

**Actions:**
- `oha_before_schedule_display` - Before schedule renders
- `oha_after_schedule_display` - After schedule renders
- `oha_before_class_item` - Before each class item
- `oha_after_class_item` - After each class item

**Filters:**
- `oha_schedule_class_query` - Modify class query args
- `oha_schedule_time_format` - Customize time display
- `oha_schedule_track_colors` - Add custom track colors

## Troubleshooting

### Classes Not Showing
1. Check that classes are published (not draft)
2. Verify day/instructor taxonomies are assigned
3. Clear cache if using caching plugin

### Filters Not Working
1. Ensure JavaScript is not blocked
2. Check browser console for errors
3. Verify jQuery is loaded

### Import Fails
1. Check CSV format (comma-separated)
2. Ensure time format is HH:MM (e.g., 10:00, not 10am)
3. Remove special characters from class titles

### PWA Not Caching
1. Check localStorage is enabled in browser
2. Verify service worker is registered
3. Check browser console for cache errors

## Support

For issues, questions, or feature requests:
- Email: justin@ohiohaunt.com
- Visit: https://ohiohaunt.com

## Changelog

### Version 1.0.0 (December 2025)
- Initial release
- Custom post type for classes
- Multiple view modes (Timeline, Grid, List, Compact)
- Search and filter functionality
- Bulk import from CSV/Excel
- Export to CSV
- Frontend admin interface
- PWA offline support
- Print-friendly layouts
- Track color coding
- Instructor and day taxonomies
- Capacity and level management

## Credits

Developed for the Ohio Haunters Association by Justin
Convention Management Plugin Suite

## License

GPL v2 or later
