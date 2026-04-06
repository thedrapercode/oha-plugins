# OHA Schedule Hub - Changelog

## Version 1.1.0 (December 15, 2025)

### 🎉 Major Feature: PWA Favorites Integration

**NEW: Full integration with OHA Convention PWA favorites system!**

Classes can now be added to favorites in the PWA and synced to "My OHC" page:

- **Added PWA-compatible data attributes** to all schedule views
  - `data-event-id` - Unique identifier for each class
  - `data-event-title` - Class name
  - `data-event-time` - Formatted as "Day HH:MM AM/PM - HH:MM AM/PM"
  - `data-event-room` - Room/location
  - `data-event-speaker` - Instructor name
  - `class="event-item"` - Required class for PWA to detect events

- **Automatic favorite button injection**
  - PWA's `pwa-favorites.js` automatically finds classes with `data-event-id`
  - Adds "⭐ Add to Favorites" button to each class
  - No additional code needed - works out of the box!

- **Full offline support**
  - Favorites stored in localStorage
  - Works completely offline in PWA
  - Persists across app sessions
  - No backend or login required

- **Calendar export**
  - PWA adds "📅 Add to Calendar" buttons
  - Exports .ics files for calendar apps
  - Includes reminders and notifications

- **Styled favorite buttons**
  - Added CSS for favorite and calendar buttons
  - Responsive positioning for all view modes
  - Matches OHA Convention branding

### How It Works

1. **Schedule Plugin** (this plugin) outputs classes with PWA data attributes
2. **PWA Core Plugin** detects elements with `data-event-id`
3. **PWA automatically adds** favorite and calendar buttons
4. **Users can favorite** classes in any view (Timeline, Grid, List, Compact)
5. **Favorites appear** on "My OHC" page (`<div id="oha-my-schedule"></div>`)
6. **Users can export** favorites to their calendar app

### Requirements

- OHA Convention PWA Core plugin v3.1.2 or higher
- "My OHC" page with `<div id="oha-my-schedule"></div>`
- Both plugins active simultaneously

### Browser Support

Works in all modern browsers and as installed PWA on:
- iOS (Safari)
- Android (Chrome)
- Desktop (all major browsers)

---

## Version 1.0.4 (December 15, 2025)

### Fixed
- **List View Text Visibility**: Fixed white text on white background in list/table view
  - Added dark text color (#1f2937) to all table cells
  - Added darker color (#111827) to class names (strong text)
  - Added text colors to `.oha-list-class`, `.oha-list-instructor`, `.oha-list-room`, `.oha-list-track`
  - Added default color to all `<td>` elements
  - Class names, instructors, locations, and tracks now fully readable in list view
  - Hover state darkens text slightly for better visibility

### Technical Changes
- Added comprehensive text color rules for all list view elements
- Ensured no element inherits white text color from theme

## Version 1.0.3 (December 15, 2025)

### Fixed
- **View Switching Actually Works Now**: Complete rewrite of view rendering system
  - Changed template to render ALL views (timeline, grid, list, compact) in HTML
  - JavaScript now shows/hides views instead of trying to switch non-existent elements
  - Added console logging for easier debugging
  - Added window.load event handler as backup
  - All four view modes now work reliably

- **Admin Table Text Visibility**: Fixed white text on white background
  - Added dark text colors (#1f2937) to table cells
  - Added darker color (#111827) to bold text
  - Added text color to empty state message
  - Table is now fully readable

### Technical Changes
- Views are now pre-rendered and toggled via CSS display property
- Added default `display: none` to all views in CSS
- JavaScript activates the selected view on page load
- More robust event handling with both ready and load events

## Version 1.0.2 (December 15, 2025)

### Fixed
- **View Switching**: Fixed grid, list, and compact views not displaying properly
  - Updated JavaScript to properly show/hide view containers
  - Added initial view detection and display
  - Fixed view switcher to work across all day sections

- **Filter Dropdowns**: Fixed white text on white background issue
  - Added proper color styling for select elements
  - Added option text color for visibility
  - Improved dropdown contrast

- **Day Section Titles**: Changed from black to white text
  - Added dark background (#1f2937)
  - Added white text color (#ffffff)
  - Added padding and rounded corners
  - Added blue accent border on left

- **Admin Modal Issues**: Fixed "Add New Class" and "Edit" buttons not working
  - Properly enqueued admin scripts for frontend shortcode use
  - Fixed modal display using class toggle method
  - Added better z-index and positioning
  - Added event delegation for dynamic buttons
  - Added console logging for debugging
  - Fixed modal show/hide reliability

- **JavaScript Improvements**
  - Added element existence checks
  - Added better error handling
  - Added Escape key support for modal closing
  - Improved AJAX success/error handling

## Version 1.0.1 (December 15, 2025)

### Fixed
- **Critical Error Fix**: Resolved "function already defined" fatal error
  - Moved helper function `oha_format_time()` into main class as static method
  - Updated all template files to use `OHA_Schedule_Hub::format_time()`
  - Prevents function redeclaration when shortcode appears multiple times

- **Query Improvements**
  - Fixed tax_query initialization to prevent array errors
  - Added proper empty tax_query handling

### Improved
- Better code organization and structure
- Improved error handling throughout
- Added comprehensive troubleshooting documentation

## Version 1.0.0 (December 15, 2025)

### Initial Release
- Custom post type for classes
- Multiple view modes (Timeline, Grid, List, Compact)
- Search and filter functionality
- Bulk CSV/Excel import
- CSV export
- Frontend admin interface via shortcode
- WordPress admin interface
- Settings management page
- PWA offline support with localStorage caching
- Print-friendly layouts
- Mobile responsive design
- Track color coding
- Instructor and day taxonomies
- Capacity and skill level management
- Room/location assignments
- Internal admin notes
- Materials needed tracking

---

## Upgrade Notes

### Upgrading from 1.0.1 to 1.0.2
- No database changes required
- Safe to upgrade directly
- Clear site cache after upgrade
- Test view switching and admin modal after upgrade

### Upgrading from 1.0.0 to 1.0.1
- Deactivate plugin
- Delete old version
- Upload new version
- Activate plugin
- Clear all caches
- Data is preserved (no database changes)
