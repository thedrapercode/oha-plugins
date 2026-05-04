# OHA Modern Map Plugin

A modern, mobile-first WordPress map plugin designed for haunted attractions using GeoDirectory as the data source.

## Features

### Core Functionality
- **Region-Based Navigation**: Automatically divides Ohio into 5 regions (Northwest, Northeast, Central, Southeast, Southwest) based on lat/lng boundaries
- **Icon Hierarchy**: Visual differentiation for Featured, Professional, Home Haunts, and Free listings
- **Smart Clustering**: Performance-optimized marker clustering that breaks apart naturally as you zoom
- **Mobile-First Design**: Bottom sheet interface, tap controls, and thumb-friendly interactions
- **Dark Themed Maps**: Modern dark map style with muted roads and high-contrast markers
- **Responsive Filtering**: Real-time region and type filtering without page reloads
- **List + Map Views**: Toggle between map exploration and detailed list view

### Technical Features
- Integrates with GeoDirectory plugin (no modifications required)
- Uses Leaflet.js for flexible, themable mapping
- AJAX-powered listing loading
- CSS variable-based theming
- Expandable icon type system
- Admin settings panel for complete control
- Two shortcodes for maximum flexibility

## Requirements

- WordPress 5.8+
- PHP 7.4+
- GeoDirectory plugin (free version works)
- Listings with latitude/longitude coordinates

## Installation

### Quick Install
1. Upload `oha-modern-map.zip` via WordPress admin (Plugins > Add New > Upload)
2. Activate the plugin
3. Go to OHA Map > Settings to configure colors and icons
4. Add shortcode to any page: `[oha_modern_map]`

### Manual Install
1. Extract the plugin folder to `/wp-content/plugins/`
2. Activate via WordPress admin
3. Configure settings
4. Use shortcodes

## Shortcodes

### Main Map: `[oha_modern_map]`

Display an interactive map with filters and controls.

**Basic Usage:**
```
[oha_modern_map]
```

**All Available Parameters:**

| Parameter | Default | Options | Description |
|-----------|---------|---------|-------------|
| `region` | (empty) | northwest, northeast, central, southeast, southwest | Focus on specific region |
| `type` | all | all, featured, professional, home_haunt, free | Filter by listing type |
| `height` | 600px | Any CSS height value | Map container height |
| `show_filters` | true | true, false | Show/hide filter controls |
| `show_list` | true | true, false | Enable list view toggle |
| `clustering` | true | true, false | Enable marker clustering |
| `initial_view` | map | map, list | Starting view |
| `class` | (empty) | Any CSS class | Add custom CSS class |

**Examples:**
```
[oha_modern_map region="central" height="500px"]
```
Shows Central Ohio region, 500px tall

```
[oha_modern_map type="featured" show_filters="false"]
```
Only featured haunts, no filters

```
[oha_modern_map initial_view="list" show_list="true"]
```
Starts in list view

### Region Selector: `[oha_region_selector]`

Display clickable region cards (perfect for homepage).

**Basic Usage:**
```
[oha_region_selector]
```

**All Available Parameters:**

| Parameter | Default | Options | Description |
|-----------|---------|---------|-------------|
| `style` | grid | grid, map | Display style |
| `show_counts` | true | true, false | Show haunt counts |
| `title` | Find Haunts Near You | Any text | Section heading |
| `subtitle` | Select your region... | Any text | Section subheading |

**Examples:**
```
[oha_region_selector show_counts="true"]
```
Grid with haunt counts per region

```
[oha_region_selector title="Explore Ohio Haunts" subtitle="Click a region to begin"]
```
Custom heading and subheading

## Configuration

### GeoDirectory Meta Keys

The plugin automatically detects these GeoDirectory meta keys:

**Required:**
- `latitude` - Listing latitude
- `longitude` - Listing longitude

**Optional (auto-detected):**
- `featured` - Set to '1' for featured listings
- `oha_member` - Set to '1' for OHA members
- `home_haunt` - Set to '1' for home haunts
- `professional_haunt` - Set to '1' for professional haunts
- `haunt_status` - Values: open, seasonal, closed
- `street`, `city`, `region`, `zip` - Address components
- `phone`, `email`, `website` - Contact info

### Listing Type Hierarchy

The plugin determines listing types in this priority order:

1. **Featured** - Any listing with `featured` = '1'
2. **Home Haunt** - Has `home_haunt` = '1' OR category 'home-haunt'
3. **Professional** - Has `oha_member` = '1' OR `professional_haunt` = '1'
4. **Free** - Default for all other listings

### Region Auto-Assignment

Regions are automatically determined by lat/lng boundaries:

- **Northwest Ohio**: 40.7°-41.9°N, -84.9°--83.0°W
- **Northeast Ohio**: 40.8°-42.0°N, -82.5°--80.5°W
- **Central Ohio**: 39.5°-40.7°N, -84.0°--82.0°W
- **Southeast Ohio**: 38.4°-40.0°N, -82.5°--80.5°W
- **Southwest Ohio**: 38.4°-40.0°N, -84.9°--83.5°W

## Admin Settings

Access settings at **OHA Map > Settings**

### Map Theme
- **Map Style**: Dark (recommended), Light, or Haunted
- **Accent Color**: Primary UI color (default: #ff6b35)
- **Featured Haunts Color**: Color for featured markers (default: #ffd700)
- **Home Haunts Color**: Color for home haunt markers (default: #9d4edd)

### Icon Hierarchy
- **Featured Icon Size**: 20-50px (recommended: 32px)
- **Professional Icon Size**: 16-40px (recommended: 24px)
- **Home Haunt Icon Size**: 14-32px (recommended: 20px)
- **Featured Glow Effect**: Add subtle glow to featured listings

### Clustering Settings
- **Enable Clustering**: Group nearby markers (strongly recommended)
- **Cluster Radius**: 30-100px (recommended: 50px)
- **Disable at Zoom Level**: 12-18 (recommended: 16)

## Customization

### CSS Variables

Override theme colors in your theme CSS:

```css
:root {
    --oha-accent: #your-color;
    --oha-featured: #your-color;
    --oha-home-haunt: #your-color;
    --oha-bg-dark: #your-color;
    --oha-bg-card: #your-color;
    --oha-text-primary: #your-color;
    --oha-text-secondary: #your-color;
    --oha-border: #your-color;
}
```

### Custom Regions

Add this to your theme's `functions.php`:

```php
add_filter('oha_map_regions', function($regions) {
    // Modify existing region
    $regions['central']['name'] = 'Columbus Metro';
    
    // Add new region
    $regions['custom'] = [
        'name' => 'Custom Region',
        'slug' => 'custom',
        'bounds' => [
            'north' => 41.0,
            'south' => 40.0,
            'east' => -82.0,
            'west' => -83.0
        ],
        'center' => [40.5, -82.5],
        'zoom' => 10
    ];
    
    return $regions;
});
```

### Custom Listing Data

Filter listing data before rendering:

```php
add_filter('oha_map_listings', function($listings, $region, $type) {
    foreach ($listings as &$listing) {
        // Add custom fields
        $listing['custom_field'] = get_post_meta($listing['id'], 'custom_field', true);
    }
    return $listings;
}, 10, 3);
```

## Performance Optimization

### Recommended Settings
- Enable clustering (handles 100+ markers smoothly)
- Set cluster radius to 50px
- Disable clustering at zoom 16
- Use dark theme (better mobile performance)
- Featured icon size: 32px max

### For Sites with 200+ Listings
- Increase cluster radius to 70px
- Disable clustering at zoom 17
- Consider starting with region-focused pages instead of statewide map

## Mobile Optimization

The plugin is mobile-first by design:

- **Bottom Sheet UI**: Native app-style listing details
- **Tap Controls**: Large, thumb-friendly buttons
- **No Pinch Required**: Zoom buttons always visible
- **Optimized Touch**: Smooth panning and interactions
- **Fast Loading**: Lazy marker loading by viewport

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile Safari iOS 14+
- Chrome Android 90+

## Troubleshooting

### Maps Not Loading
1. Check browser console for JavaScript errors
2. Verify GeoDirectory is active
3. Ensure listings have lat/lng coordinates
4. Clear cache and try again

### Markers Not Appearing
1. Go to OHA Map > Regions to verify listing counts
2. Check that listings have coordinates in GeoDirectory
3. Verify region boundaries match your data
4. Test with all type filters enabled

### Styling Issues
1. Check for theme CSS conflicts
2. Clear browser and plugin cache
3. Verify CSS is loading (check Network tab)
4. Try adding `!important` to custom CSS

### Performance Issues
1. Enable clustering
2. Reduce number of visible markers per view
3. Use region-focused pages
4. Check for JavaScript conflicts

## Development

### File Structure
```
oha-modern-map/
├── oha-modern-map.php          # Main plugin file
├── includes/
│   ├── class-oha-map-regions.php    # Region management
│   ├── class-oha-map-data.php       # GeoDirectory integration
│   ├── class-oha-map-shortcode.php  # Shortcode handlers
│   └── class-oha-map-admin.php      # Admin interface
├── assets/
│   ├── css/
│   │   ├── oha-modern-map.css       # Frontend styles
│   │   └── admin.css                # Admin styles
│   └── js/
│       ├── oha-modern-map.js        # Frontend functionality
│       └── admin.js                 # Admin functionality
└── README.md                        # Documentation
```

### Extending the Plugin

**Add New Listing Type:**

```php
// In class-oha-map-data.php, modify determine_listing_type()
if (get_post_meta($post_id, 'haunted_trail', true) === '1') {
    return 'haunted_trail';
}
```

**Add New Icon Style:**

```php
// In admin settings, add new icon size field
// In JavaScript, add new case to createMarkerIcon()
```

**Add Custom Filter:**

Add filter UI to shortcode template, then handle in JavaScript filter logic.

## Support

- Documentation: See this README
- Issues: Contact plugin developer
- Feature Requests: Submit via admin feedback

## Changelog

### Version 1.0.0
- Initial release
- Region-based mapping
- Icon hierarchy system
- Mobile-first bottom sheet UI
- Dark themed map styles
- Smart clustering
- Dual shortcodes
- Admin settings panel
- GeoDirectory integration

## License

This plugin is created for Ohio Haunters Association. All rights reserved.

## Credits

- **Mapping**: Leaflet.js
- **Clustering**: Leaflet.markercluster
- **Tiles**: CartoDB (Dark theme)
- **Framework**: WordPress
- **Data Source**: GeoDirectory
