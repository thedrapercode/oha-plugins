# OHA Modern Map - Quick Setup Guide

## Step-by-Step Installation

### 1. Install the Plugin

**Option A - Upload via WordPress Admin (Recommended)**
1. Go to WordPress admin: **Plugins > Add New**
2. Click **Upload Plugin** button
3. Choose `oha-modern-map.zip`
4. Click **Install Now**
5. Click **Activate Plugin**

**Option B - Manual FTP Upload**
1. Extract `oha-modern-map.zip`
2. Upload the `oha-modern-map` folder to `/wp-content/plugins/`
3. Go to **Plugins** in WordPress admin
4. Find "OHA Modern Map" and click **Activate**

### 2. Configure Basic Settings

1. Go to **OHA Map > Settings** in WordPress admin
2. Configure these recommended settings:
   - **Map Style**: Dark (best for haunted theme)
   - **Accent Color**: #ff6b35 (or your brand color)
   - **Featured Icon Size**: 32px
   - **Professional Icon Size**: 24px
   - **Home Haunt Icon Size**: 20px
   - **Enable Clustering**: ✓ Checked
   - **Cluster Radius**: 50px
   - **Disable at Zoom Level**: 16
3. Click **Save Settings**

### 3. Add Map to Homepage

**Create Region Selector (Recommended for Homepage)**

1. Edit your homepage or create a new page
2. Add this shortcode:
   ```
   [oha_region_selector show_counts="true"]
   ```
3. This creates clickable region cards with haunt counts

**Or Add Full Map Directly**

1. Edit your page
2. Add this shortcode:
   ```
   [oha_modern_map height="600px"]
   ```

### 4. Create Region Pages (Optional but Recommended)

Create 5 pages for better UX:

**Northwest Ohio Haunts**
```
[oha_modern_map region="northwest" height="600px"]
```

**Northeast Ohio Haunts**
```
[oha_modern_map region="northeast" height="600px"]
```

**Central Ohio Haunts**
```
[oha_modern_map region="central" height="600px"]
```

**Southeast Ohio Haunts**
```
[oha_modern_map region="southeast" height="600px"]
```

**Southwest Ohio Haunts**
```
[oha_modern_map region="southwest" height="600px"]
```

### 5. Configure GeoDirectory Listings

For the plugin to work, your GeoDirectory listings need:

**Required Fields:**
- ✓ Latitude (automatically set by GeoDirectory)
- ✓ Longitude (automatically set by GeoDirectory)

**Optional Fields for Better Experience:**

Add these as GeoDirectory custom fields if not present:

1. Go to **GeoDirectory > Custom Fields**
2. Add these fields:

**Featured Status:**
- Field Type: Checkbox
- Meta Key: `featured`
- Label: "Featured Listing"

**OHA Member:**
- Field Type: Checkbox
- Meta Key: `oha_member`
- Label: "OHA Member"

**Home Haunt:**
- Field Type: Checkbox
- Meta Key: `home_haunt`
- Label: "Home Haunt"

**Haunt Status:**
- Field Type: Select
- Meta Key: `haunt_status`
- Options: open, seasonal, closed

### 6. Test the Map

1. Visit your homepage or map page
2. You should see:
   - Dark themed map
   - Region filters
   - Type toggles
   - Markers for your listings
3. On mobile:
   - Tap a marker
   - Bottom sheet should slide up
   - Tap handle to close
4. Try filtering by region and type

### 7. Troubleshooting

**No markers showing?**
1. Go to **OHA Map > Regions** to check listing counts
2. Verify listings have lat/lng in GeoDirectory
3. Check that filters aren't hiding everything

**Map not loading?**
1. Open browser console (F12)
2. Look for JavaScript errors
3. Verify GeoDirectory plugin is active
4. Clear cache

**Wrong colors/styling?**
1. Go to **OHA Map > Settings**
2. Adjust theme colors
3. Clear browser cache

## Recommended Page Setup

### Homepage
```
<h2>Find Haunts Near You</h2>
<p>Select your region to explore Ohio's best haunted attractions</p>

[oha_region_selector show_counts="true"]
```

### All Haunts Page
```
<h1>All Ohio Haunts</h1>

[oha_modern_map height="600px" show_filters="true" show_list="true"]
```

### Featured Haunts Page
```
<h1>Featured Haunted Attractions</h1>
<p>Our top-rated and most popular haunts</p>

[oha_modern_map type="featured" height="500px"]
```

### Central Ohio Example
```
<h1>Central Ohio Haunted Attractions</h1>
<p>Explore haunts in and around Columbus</p>

[oha_modern_map region="central" height="600px"]
```

## Mobile Testing Checklist

- ✓ Map loads on mobile
- ✓ Zoom controls work
- ✓ Markers are tap-able
- ✓ Bottom sheet opens on marker tap
- ✓ Bottom sheet closes on handle tap
- ✓ Filters are thumb-friendly
- ✓ List view is readable
- ✓ No horizontal scrolling

## Performance Tips

1. **Use region pages** instead of one statewide map
2. **Keep clustering enabled** for sites with 50+ listings
3. **Start with region selector** on homepage (faster load)
4. **Use featured-only maps** for promotional pages
5. **Optimize images** in listing content

## Next Steps

1. ✓ Configure icon hierarchy in settings
2. ✓ Add custom CSS for your brand colors (optional)
3. ✓ Create menu links to region pages
4. ✓ Test on mobile devices
5. ✓ Share with board members for feedback

## Common Customizations

### Change Map Height
```
[oha_modern_map height="800px"]
```

### Hide Filters
```
[oha_modern_map show_filters="false"]
```

### Start in List View
```
[oha_modern_map initial_view="list"]
```

### Featured Only, No Clustering
```
[oha_modern_map type="featured" clustering="false"]
```

### Custom Region Selector Title
```
[oha_region_selector title="Choose Your Area" subtitle="Tap a region to explore"]
```

## Support & Questions

If you run into issues:

1. Check the main README.md for detailed documentation
2. Verify GeoDirectory listings have coordinates
3. Clear all caches (plugin, theme, browser)
4. Test in incognito/private browsing mode
5. Check browser console for errors

---

**You're all set!** The map should now be working on your site with a modern, mobile-first experience that makes the board happy and users productive. 🎃
