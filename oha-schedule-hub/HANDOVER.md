# OHA Schedule Hub - Handover

**Current version:** 1.4.0
**Zip filename:** `schedule-hub-oha-v1.4.0.zip`
**Folder inside zip:** `oha-schedule-hub/`
**Dev/extract folder:** `tmp/schedule-hub-extract/oha-schedule-hub/`

## What It Does

Comprehensive class schedule management plugin for OHA Convention. Supports multiple display views (timeline/cards, grid, compact list, table), day/track/instructor filtering, search, print view, and a frontend admin interface. Classes are a custom post type with time, room, level, and instructor metadata.

## Shortcodes

| Shortcode | Description |
|---|---|
| `[oha_schedule]` | Public schedule display (all views, filters, search) |
| `[oha_schedule view="timeline"]` | Force timeline/cards view |
| `[oha_schedule view="grid"]` | Force grid view |
| `[oha_schedule view="list"]` | Force table view |
| `[oha_schedule view="compact"]` | Force compact list view |
| `[oha_schedule day="Friday"]` | Pre-filter to one day |
| `[oha_schedule instructor="Name"]` | Pre-filter by instructor |
| `[oha_schedule map_url="/map/"]` | Adds Map tab link in class detail modal |
| `[oha_schedule_admin]` | Frontend admin interface (requires edit_posts cap) |

## Custom Post Types

- `oha_class` — Schedule class/session entries

## Taxonomies

- `class_day` (hierarchical) — Day of the convention (e.g. Thursday, Friday, Saturday)
- `class_track` (hierarchical) — Track/category grouping for classes
- `instructor` (flat) — Instructor names; supports headshot term meta (`instructor_headshot_id`)

## Key Post Meta Keys

| Meta Key | Purpose |
|---|---|
| `_oha_start_time` | Class start time (HH:MM format) |
| `_oha_end_time` | Class end time |
| `_oha_room` | Room/location |
| `_oha_capacity` | Seat capacity |
| `_oha_level` | Skill level (beginner/intermediate/advanced/all) |
| `_oha_short_desc` | Short description for schedule cards |
| `_oha_materials` | Materials needed |
| `_oha_notes` | Internal admin notes (not public) |
| `_oha_promoted` | '1' if featured/promoted (gold badge) |

## Important wp_options

| Option | Default | Purpose |
|---|---|---|
| `oha_schedule_time_format` | `12h` | 12h or 24h clock display |
| `oha_schedule_default_duration` | `75` | Default class length in minutes |
| `oha_schedule_setup_time` | `15` | Setup buffer minutes |
| `oha_schedule_teardown_time` | `15` | Teardown buffer minutes |
| `oha_schedule_enable_search` | `1` | Show search bar |
| `oha_schedule_enable_filters` | `1` | Show filter dropdowns |
| `oha_schedule_default_view` | `timeline` | Default view on load |
| `oha_schedule_show_descriptions` | `1` | Show class descriptions |
| `oha_schedule_enable_print` | `1` | Show print button |
| `oha_schedule_day_order` | `` | Comma-separated day display order (e.g. `Thursday, Friday, Saturday`) |

## Admin Pages

- **Schedule > Settings** (`oha-schedule-settings`) — General settings including Day Order field
- **Schedule > Bulk Import** (`oha-schedule-import`) — JSON bulk import
- **Schedule > View Schedule** (`oha-schedule-view`) — Admin preview

## Dependencies

None beyond WordPress core.

## Card / Headshot Layout Notes (IMPORTANT)

On the timeline/cards view, instructor headshots float between the image strip and the time header:

- `.oha-instructor-headshots` is `position: absolute; bottom: -22px` inside `.oha-card-image`
- With a featured image: `.oha-card-image` has `overflow: hidden` -- headshots are clipped at the image boundary (visible at the bottom of the image, not below it). Time header shows cleanly below.
- Without a featured image: `.oha-headshots-only-wrap` is a 60px strip with `overflow: visible`. Headshots protrude 22px past the strip. `.has-headshots-only .oha-timeline-header { margin-top: 22px }` was added (v1.3.6) to push the header down so the headshots float cleanly above it.
- Card item itself has `overflow: visible` so headshots can bleed between cards.

## Known Issues / TODOs

- `rest_promoted_starting_soon` REST endpoint is a stub (returns empty array). Future use: query promoted classes starting in 5-10 min for PWA push notifications.
- `frontend.css` still enqueues on every page unconditionally. Future improvement: only enqueue when `[oha_schedule]` shortcode is present. Not urgent since CSS collisions are scoped.
- No devlog.json existed prior to v1.3.4 (first one created that session).

## Taxonomies

- `class_day` (hierarchical) — Day of the convention (e.g. Thursday, Friday, Saturday)
- `class_track` (hierarchical) — Track/category grouping for classes
- `instructor` (flat) — Instructor names; supports headshot term meta (`instructor_headshot_id`)
- `convention_year` (flat) — Convention year (e.g. 2026, 2027); used for multi-year archiving

## Year Management (v1.4.0+)

- **Shortcode filter**: `[oha_schedule year="2026"]` — shows only that year's classes
- **Default (no year attr)**: shows ALL classes regardless of year — backward compatible
- **WP Editor meta box**: "Convention Year" select appears on the oha_class edit screen
- **Quick Admin form**: Year dropdown in the frontend [oha_schedule_admin] form
- **Settings > Year Management**: Export JSON / Delete All per year; Tag All Untagged tool
- **Export JSON**: Downloads full backup of a year's classes (all fields including notes/materials/promoted)
- **Delete All**: Moves classes to trash (confirm dialog required; export first)
- **Tag Untagged**: Retroactively tags all classes with no year. Run this after upgrading from pre-1.4.0.
- AJAX handlers: `oha_export_year_json`, `oha_delete_year_classes`, `oha_tag_untagged_classes` (manage_options cap)

## Session Summary (most recent first)

### 2026-03-18 - v1.3.6 -> v1.4.0

**Changes:**
1. **convention_year taxonomy** - New flat taxonomy for multi-year class management. Year field in WP editor meta box, frontend quick-admin form, AJAX save/get. Shortcode `year="2026"` filters query server-side. Default shows all (backward compatible).
2. **Year Management UI** - Added to Settings page: per-year Export JSON + Delete All buttons, untagged class counter with Tag All tool.
3. **Export fix** - `ajax_export_schedule` now exports all fields: short_desc, materials, notes, promoted, year (was missing these).
4. **Import fix** - `ajax_bulk_import` now imports all fields: level, capacity, track, short_desc, materials, promoted, year (was only importing title/description/time/room/day/instructor).
5. **CSV export updated** - Column headers and rows now include Year, Short Desc, Materials, Promoted columns.

**Files changed:**
- `oha-schedule-hub.php` - register_taxonomies, meta box, save_class_meta, shortcode atts, AJAX save/get/export/import, 3 new AJAX methods
- `templates/schedule-display.php` - year attr added to tax_query
- `templates/admin-settings.php` - Year Management section with inline JS
- `templates/schedule-admin.php` - year select in quick-admin form
- `assets/js/admin.js` - year in loadClassData/saveClass, updated CSV export

### 2026-03-17 - v1.3.5 -> v1.3.6

**Problem:** On timeline/cards view, when a class had instructor headshots but NO featured image, the headshot circles appeared on top of the start-time header, obscuring the time text. With a featured image, the headshots were correctly clipped by `overflow: hidden` and did not interfere with the header.

**Root cause:** `.oha-headshots-only-wrap` has `overflow: visible` and is only 60px tall. The headshots at `bottom: -22px` extend 22px below the strip. With `z-index: 4`, they rendered above `.oha-timeline-header` which has no z-index. With a featured image, `overflow: hidden` on `.oha-card-image` clipped the headshots so they never reached the header.

**Fix:** Added `.has-headshots-only .oha-timeline-header { margin-top: 22px }` to `assets/css/frontend.css`. The header slides down exactly 22px, clearing the headshot overflow. The headshots now float visually above the header.

**Files changed:**
- `assets/css/frontend.css` - Added .has-headshots-only .oha-timeline-header rule
- `oha-schedule-hub.php` - version bump to 1.3.6
- `devlog.json` - new entry added
- `HANDOVER.md` - added to zip (inside oha-schedule-hub/ folder)

### 2026-03-17 - v1.3.4 -> v1.3.5

**Problem:** Schedule hub's `frontend.css` is enqueued on every page (unconditional `wp_enqueue_scripts`). It contained unscoped global CSS rules for `.oha-card-header`, `.oha-card-body`, `.oha-card-footer`, etc. The vendor hub's `boardhub-admin.php` shortcode uses these same class names, causing dark Halloween backgrounds on the board hub page.

**Fix:** Added `.oha-schedule-container` parent scope to all `.oha-card-*` CSS rules.

### 2026-03-17 - v1.3.3 -> v1.3.4

**Problem:** Day sections appeared in unpredictable order.

**Fix:** Added `oha_schedule_day_order` wp_option. Admin enters comma-separated day names in Schedule > Settings.
