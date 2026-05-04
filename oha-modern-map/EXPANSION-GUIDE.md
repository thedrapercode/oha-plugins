# OHA Modern Map - Expansion Guide

This guide explains how to extend the plugin for future needs.

## Common Expansions

### 1. Add New Listing Type

**Example: Add "Haunted Trail" type**

**Step 1 - Modify Type Detection**

Edit `includes/class-oha-map-data.php`, in the `determine_listing_type()` method:

```php
private static function determine_listing_type($post_id) {
    // Check if featured (highest priority)
    if (get_post_meta($post_id, 'featured', true) === '1') {
        return 'featured';
    }
    
    // ADD NEW TYPE CHECK HERE
    if (get_post_meta($post_id, 'haunted_trail', true) === '1') {
        return 'haunted_trail';
    }
    
    // Check for home haunt
    $is_home_haunt = self::is_home_haunt($post_id);
    if ($is_home_haunt) {
        return 'home_haunt';
    }
    
    // ... rest of existing code
}
```

**Step 2 - Add Icon Settings**

Edit `includes/class-oha-map-admin.php`, add to settings page:

```php
<tr>
    <th scope="row">
        <label for="haunted_trail_size"><?php _e('Haunted Trail Icon Size', 'oha-modern-map'); ?></label>
    </th>
    <td>
        <input type="number" name="icons[haunted_trail_size]" id="haunted_trail_size" value="<?php echo esc_attr($icons['haunted_trail_size'] ?? 22); ?>" min="14" max="32" step="2">
        <span>px</span>
    </td>
</tr>

<tr>
    <th scope="row">
        <label for="haunted_trail_color"><?php _e('Haunted Trail Color', 'oha-modern-map'); ?></label>
    </th>
    <td>
        <input type="text" name="theme[haunted_trail_color]" id="haunted_trail_color" value="<?php echo esc_attr($theme['haunted_trail_color'] ?? '#2ecc71'); ?>" class="oha-color-picker">
    </td>
</tr>
```

**Step 3 - Add Filter Toggle**

Edit `includes/class-oha-map-shortcode.php`, add to filter section:

```php
<label class="oha-toggle">
    <input type="checkbox" value="haunted_trail" checked>
    <span class="oha-toggle-label">
        <span class="oha-icon oha-icon-trail"></span>
        <?php _e('Haunted Trails', 'oha-modern-map'); ?>
    </span>
</label>
```

**Step 4 - Add Icon Style**

Edit `assets/css/oha-modern-map.css`:

```css
.oha-icon-trail {
    background: #2ecc71;
}

.oha-listing-badge.haunted-trail {
    background: #2ecc71;
    color: white;
}
```

**Step 5 - Add JS Icon Handling**

Edit `assets/js/oha-modern-map.js`, in `createMarkerIcon()`:

```javascript
case 'haunted_trail':
    size = iconSettings.haunted_trail_size || 22;
    color = theme.haunted_trail_color || '#2ecc71';
    glow = false;
    break;
```

And in `getListingBadges()`:

```javascript
case 'haunted_trail':
    badges += '<span class="oha-listing-badge haunted-trail">Haunted Trail</span>';
    break;
```

### 2. Add Custom Region

**Option A - Via Filter (Recommended)**

Add to your theme's `functions.php`:

```php
add_filter('oha_map_regions', function($regions) {
    $regions['cincinnati'] = [
        'name' => 'Greater Cincinnati',
        'slug' => 'cincinnati',
        'bounds' => [
            'north' => 39.3,
            'south' => 38.9,
            'east' => -84.2,
            'west' => -84.8
        ],
        'center' => [39.1, -84.5],
        'zoom' => 11
    ];
    
    return $regions;
});
```

**Option B - Modify Default Regions**

Edit `includes/class-oha-map-regions.php`, in `get_default_regions()`:

```php
'cincinnati' => [
    'name' => 'Greater Cincinnati',
    'slug' => 'cincinnati',
    'bounds' => [
        'north' => 39.3,
        'south' => 38.9,
        'east' => -84.2,
        'west' => -84.8
    ],
    'center' => [39.1, -84.5],
    'zoom' => 11
],
```

### 3. Add Event Mode

**Example: Show only haunts open tonight**

**Step 1 - Add Status Filter**

Edit `includes/class-oha-map-shortcode.php`, add toggle:

```php
<label class="oha-toggle">
    <input type="checkbox" value="open_now" id="oha-open-now">
    <span class="oha-toggle-label">
        <span class="dashicons dashicons-clock"></span>
        <?php _e('Open Now', 'oha-modern-map'); ?>
    </span>
</label>
```

**Step 2 - Add JS Filter Logic**

Edit `assets/js/oha-modern-map.js`:

```javascript
// In initControls()
$wrapper.on('change', '#oha-open-now', function() {
    OHAMap.activeFilters.openNow = $(this).is(':checked');
    OHAMap.filterListings($wrapper);
});

// In shouldShowListing()
if (OHAMap.activeFilters.openNow) {
    if (listing.status !== 'open') {
        return false;
    }
}
```

### 4. Add Distance Search

**Step 1 - Add Location Input**

Edit `includes/class-oha-map-shortcode.php`:

```php
<div class="oha-filter-group">
    <label><?php _e('Near You:', 'oha-modern-map'); ?></label>
    <input type="text" id="oha-location" placeholder="Enter ZIP or City">
    <button id="oha-search-location" class="oha-btn oha-btn-primary">Search</button>
</div>
```

**Step 2 - Add Distance Calculation**

Edit `assets/js/oha-modern-map.js`:

```javascript
calculateDistance: function(lat1, lng1, lat2, lng2) {
    // Haversine formula
    const R = 3959; // Earth radius in miles
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLng/2) * Math.sin(dLng/2);
    
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
},

searchByLocation: function($wrapper, userLat, userLng) {
    const mapId = $wrapper.attr('id');
    const listings = OHAMap.listings[mapId] || [];
    
    // Add distance to each listing
    listings.forEach(function(listing) {
        listing.distance = OHAMap.calculateDistance(
            userLat, userLng,
            listing.lat, listing.lng
        );
    });
    
    // Sort by distance
    listings.sort((a, b) => a.distance - b.distance);
    
    // Re-render
    OHAMap.renderMarkers($wrapper);
    OHAMap.renderListView($wrapper);
}
```

### 5. Add Review/Rating System

**Step 1 - Store Ratings in Post Meta**

Use a plugin like "GeoDirectory Reviews" or add custom meta:

```php
update_post_meta($listing_id, 'rating', 4.5);
update_post_meta($listing_id, 'review_count', 23);
```

**Step 2 - Include in Listing Data**

Edit `includes/class-oha-map-data.php`:

```php
$listings[] = [
    'id' => $post_id,
    'title' => get_the_title(),
    // ... existing fields
    'rating' => floatval(get_post_meta($post_id, 'rating', true)),
    'review_count' => intval(get_post_meta($post_id, 'review_count', true))
];
```

**Step 3 - Display in UI**

Edit `assets/js/oha-modern-map.js`, in `buildListingHTML()`:

```javascript
${listing.rating ? `
    <div class="oha-rating">
        <span class="oha-stars">${'★'.repeat(Math.floor(listing.rating))}${'☆'.repeat(5 - Math.floor(listing.rating))}</span>
        <span class="oha-review-count">(${listing.review_count} reviews)</span>
    </div>
` : ''}
```

### 6. Add Export to Calendar

**Step 1 - Add Button**

Edit `assets/js/oha-modern-map.js`, in `buildListingHTML()`:

```javascript
<button class="oha-btn oha-btn-secondary oha-add-calendar" data-listing-id="${listing.id}">
    Add to Calendar
</button>
```

**Step 2 - Handle Click**

```javascript
$wrapper.on('click', '.oha-add-calendar', function() {
    const listingId = $(this).data('listing-id');
    // Generate .ics file or open Google Calendar URL
    window.open(`https://www.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(listing.title)}&dates=...`);
});
```

### 7. Add Offline Mode (PWA)

**Step 1 - Create Service Worker**

Create `assets/js/sw.js`:

```javascript
const CACHE_NAME = 'oha-map-v1';
const urlsToCache = [
    '/wp-content/plugins/oha-modern-map/assets/css/oha-modern-map.css',
    '/wp-content/plugins/oha-modern-map/assets/js/oha-modern-map.js'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(urlsToCache))
    );
});
```

**Step 2 - Register Service Worker**

Edit `oha-modern-map.php`:

```php
add_action('wp_footer', function() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('<?php echo OHA_MODERN_MAP_URL; ?>assets/js/sw.js');
    }
});
```

### 8. Add Analytics Tracking

**Track Map Interactions**

Add to `assets/js/oha-modern-map.js`:

```javascript
trackEvent: function(category, action, label) {
    if (typeof gtag !== 'undefined') {
        gtag('event', action, {
            'event_category': category,
            'event_label': label
        });
    }
},

// In marker click handler
OHAMap.trackEvent('Map', 'marker_click', listing.title);

// In filter change
OHAMap.trackEvent('Map', 'filter_change', 'region: ' + region);
```

## Plugin Architecture

### Data Flow

```
GeoDirectory Posts
    ↓
class-oha-map-data.php (fetches & processes)
    ↓
AJAX Response → JavaScript
    ↓
Leaflet Map Display
```

### Extension Points (Filters)

```php
// Modify regions
apply_filters('oha_map_regions', $regions);

// Modify listing data
apply_filters('oha_map_listings', $listings, $region, $type);
```

### Best Practices

1. **Always test** new features on staging first
2. **Keep backward compatibility** - don't break existing shortcodes
3. **Use CSS variables** for new colors
4. **Follow naming conventions** - `oha-*` for classes
5. **Add admin settings** for user-configurable options
6. **Document shortcode params** in README
7. **Mobile-first** - test on phone before desktop

## Need Help?

For complex customizations:
1. Review existing code patterns
2. Check WordPress and Leaflet documentation
3. Test in browser console first
4. Use version control (Git) before major changes

---

The plugin is built to be expandable. Follow these patterns and you can add almost any feature while maintaining the core UX principles. 🎃
