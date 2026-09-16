# GeoDirectory Place Fields Setup Guide
## OHA Haunt Listing Display v1.0.0

Go to **GeoDirectory > Place Fields** in the WordPress admin to add each field below.

---

## IMPORTANT NOTES

1. GeoDirectory free has ONE shared form for all listing types. Every field you add here will appear on all `gd_place` submission forms.
2. The **meta key** (sometimes labelled "Field key" or "Custom field name") must match exactly what is used in the plugin template code. Set these precisely as documented below.
3. GeoDirectory Place Fields are stored as columns in the `wp_geodir_gd_place_detail` table — NOT in `wp_postmeta`. The plugin template accesses them via `geodir_get_post_info()`.

---

## Fields to Create

### 1. Haunt Features (multi-select)

> **IMPORTANT**: Use field type `Multiselect` (NOT `Checkbox`). In GeoDirectory, a plain Checkbox is a single yes/no field (TINYINT). To support multiple selectable options, you must use Multiselect. Values are stored comma-separated in a TEXT column.

| Setting | Value |
|---|---|
| **Field Type** | Multiselect |
| **Field Label** | Haunt Features |
| **Meta Key / Field Key** | `haunt_features` |
| **Option Values** | Outdoor Event, No Touch Policy, Free Parking, Walk-Through, Drive-Through, Strobe / Flashing Lights Warning |
| **Required** | No |
| **Show in search** | Optional |

**How to set Option Values:** Enter each option on its own line, or as a comma-separated list, depending on the GeoDirectory version. The option text is exactly what will be stored and displayed.

---

### 2. Admission Type

| Setting | Value |
|---|---|
| **Field Type** | Select |
| **Field Label** | Admission Type |
| **Meta Key / Field Key** | `admission_type` |
| **Option Values** | Free, Paid, Donation |
| **Required** | No |

---

### 3. Admission Price

| Setting | Value |
|---|---|
| **Field Type** | Text |
| **Field Label** | Admission Price |
| **Meta Key / Field Key** | `admission_price` |
| **Placeholder** | e.g. $15 general / $12 kids |
| **Required** | No |

---

### 4. Haunt Season

| Setting | Value |
|---|---|
| **Field Type** | Text |
| **Field Label** | Haunt Season |
| **Meta Key / Field Key** | `haunt_season` |
| **Placeholder** | e.g. October or Year-round |
| **Required** | No |

---

### 5. Typical Hours

| Setting | Value |
|---|---|
| **Field Type** | Text |
| **Field Label** | Typical Hours |
| **Meta Key / Field Key** | `typical_hours` |
| **Placeholder** | e.g. 7PM – 11PM select nights |
| **Required** | No |

---

### 6. Age Restriction

| Setting | Value |
|---|---|
| **Field Type** | Text |
| **Field Label** | Age Restriction |
| **Meta Key / Field Key** | `age_restriction` |
| **Placeholder** | e.g. All ages or 13+ |
| **Required** | No |

---

## After Adding Fields

1. Save each field in GeoDirectory > Place Fields.
2. Open a test listing in edit mode and verify each field appears on the form.
3. Save the test listing with values in each field.
4. Visit the single listing page — the plugin template reads these fields from `$gd->fieldname` via `geodir_get_post_info()`.
5. To debug raw data, temporarily add this to `templates/content-single.php` after `$gd = geodir_get_post_info( $listing_id );`:

```php
echo '<pre style="background:#111;color:#0f0;padding:16px;font-size:12px;">';
print_r( $gd );
echo '</pre>';
```

This will dump the full GeoDirectory post object so you can confirm field names and values.

---

## Standard Fields Already in GeoDirectory

These fields are built-in to GeoDirectory free and do NOT need to be created:

| Field | Meta Key on $gd | Notes |
|---|---|---|
| Street address | `$gd->street` | Core field |
| City | `$gd->city` | Core field |
| State/Region | `$gd->region` | Core field |
| ZIP/Postcode | `$gd->zip` | Core field |
| Country | `$gd->country` | Core field |
| Latitude | `$gd->latitude` | Core field |
| Longitude | `$gd->longitude` | Core field |
| Phone | `$gd->phone` | Standard GD field |
| Website | `$gd->website` | Standard GD field |
| Facebook | `$gd->facebook` | Standard GD field |
| Featured | `$gd->featured` | Core field (1 = featured) |
