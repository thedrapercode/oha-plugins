# OHA Haunt Listing Display — Place Fields Setup V2

This replaces `PLACE-FIELDS-SETUP.md` from Session 1.
Create all fields at: **WordPress Admin → GeoDirectory → Place Fields → gd_place**

---

## How to Create a Place Field

1. Go to **GeoDirectory → Place Fields**
2. Select **gd_place** from the post type dropdown
3. Click **Add New Field**
4. Fill in: **Field Type**, **Admin Label** (your label), **Field Key** (the meta key — copy exactly)
5. For **Select / Multiselect** types: enter each option on its own line in the Options box
6. Click **Save Field**

> **CRITICAL:** The Field Key must match exactly what is in the template code. Double-check spelling.

---

## Priority 1 Fields — Add These First

### 1. Haunt Type
| Setting | Value |
|---------|-------|
| Field Type | Select |
| Admin Label | Haunt Type |
| **Field Key** | `haunt_type` |
| Options (one per line) | Home Haunt<br>Professional Haunt<br>Charity Haunt<br>Yard Display<br>Haunted Trail<br>Walkthrough |

---

### 2. Fear Level
| Setting | Value |
|---------|-------|
| Field Type | Select |
| Admin Label | Fear Level |
| **Field Key** | `fear_level` |
| Options (one per line) | Family Friendly<br>Mild<br>Moderate<br>Intense<br>Extreme |

---

### 3. Open This Season
| Setting | Value |
|---------|-------|
| Field Type | Checkbox (single boolean) |
| Admin Label | Open This Season |
| **Field Key** | `open_this_season` |
| Checkbox Label | Open for 2026 Season |

> Template checks: if value is non-empty (checked), the "Open This Season" badge appears on the hero.

---

### 4. Admission Type
| Setting | Value |
|---------|-------|
| Field Type | Select |
| Admin Label | Admission Type |
| **Field Key** | `admission_type` |
| Options | Free<br>Donation<br>Paid Admission |

---

### 5. Admission Price
| Setting | Value |
|---------|-------|
| Field Type | Text |
| Admin Label | Admission Price |
| **Field Key** | `admission_price` |
| Placeholder | e.g. $15 general / $12 kids |

> Only shown in template when Admission Type = "Paid Admission" and this field has a value.

---

### 6. Haunt Season
| Setting | Value |
|---------|-------|
| Field Type | Text |
| Admin Label | Haunt Season |
| **Field Key** | `haunt_season` |
| Placeholder | e.g. October only, or Year-round |

---

### 7. Typical Hours
| Setting | Value |
|---------|-------|
| Field Type | Text |
| Admin Label | Typical Hours |
| **Field Key** | `typical_hours` |
| Placeholder | e.g. 7PM – 11PM select nights |

---

### 8. Indoor / Outdoor
| Setting | Value |
|---------|-------|
| Field Type | Select |
| Admin Label | Indoor / Outdoor |
| **Field Key** | `indoor_outdoor` |
| Options | Indoor<br>Outdoor<br>Mixed |

---

## Priority 2 Fields — Add After Priority 1 is Tested

### 9. Haunt Features
| Setting | Value |
|---------|-------|
| Field Type | **Multiselect** |
| Admin Label | Haunt Features |
| **Field Key** | `haunt_features` |
| Options | Outdoor Event<br>No Touch Policy<br>Free Parking<br>Walk-Through<br>Drive-Through<br>Strobe Lights<br>Fog Effects<br>Loud Sounds |

> GD Multiselect stores values as comma-separated. Template uses `explode(',', ...)`.

---

### 10. Recommended Audience
| Setting | Value |
|---------|-------|
| Field Type | Multiselect |
| Admin Label | Recommended Audience |
| **Field Key** | `recommended_audience` |
| Options | Kids<br>Family<br>Teens<br>Adults |

---

### 11. Accessibility & Warnings
| Setting | Value |
|---------|-------|
| Field Type | Multiselect |
| Admin Label | Accessibility & Warnings |
| **Field Key** | `accessibility` |
| Options | Wheelchair Accessible<br>Kid Friendly<br>Flashing/Strobe Lights<br>Loud Audio<br>Fog Effects<br>Physical Contact |

---

### 12. Parking Type
| Setting | Value |
|---------|-------|
| Field Type | Select |
| Admin Label | Parking Type |
| **Field Key** | `parking_type` |
| Options | Free Street Parking<br>Free Lot<br>Paid Parking |

---

### 13. Typical Wait Time
| Setting | Value |
|---------|-------|
| Field Type | Select |
| Admin Label | Typical Wait Time |
| **Field Key** | `wait_time` |
| Options | Under 10 min<br>10–30 min<br>30–60 min<br>1 hour+ |

---

### 14. Experience Length
| Setting | Value |
|---------|-------|
| Field Type | Select |
| Admin Label | Experience Length |
| **Field Key** | `experience_length` |
| Options | Under 5 min<br>5–10 min<br>10–20 min<br>20–30 min<br>30 min+ |

---

### 15. Actor Interaction
| Setting | Value |
|---------|-------|
| Field Type | Select |
| Admin Label | Actor Interaction |
| **Field Key** | `actor_interaction` |
| Options | No Touching<br>Light Contact<br>Extreme Contact |

---

### 16. Age Restriction
| Setting | Value |
|---------|-------|
| Field Type | Text |
| Admin Label | Age Restriction |
| **Field Key** | `age_restriction` |
| Placeholder | e.g. All ages, or 13+ |

---

## Priority 3 Fields — Add After Testing Priorities 1 & 2

| Admin Label | Field Key | Type |
|-------------|-----------|------|
| Haunt Theme | `haunt_theme` | Text |
| Haunt Storyline | `haunt_storyline` | Textarea |
| Years Running | `years_running` | Text |
| Scene Count | `scene_count` | Text |
| Actor Count | `actor_count` | Text |
| Animatronic Count | `animatronic_count` | Text |
| Visitor Tips | `visitor_tips` | Textarea |
| Recruiting Actors | `recruiting_actors` | Select (Yes / No) |
| Recruitment Link | `recruitment_link` | URL |
| Special Effects | `special_effects` | Multiselect |
| Instagram | `instagram` | URL |
| TikTok | `tiktok` | URL |
| YouTube Channel | `youtube_channel` | URL |

**Special Effects options:** Animatronics, Projection Mapping, Escape Room Element, Laser Effects, Fog Machines, Bubble Machines

---

## Already Created (from Session 1 Setup)

These were created in Session 1. Verify they exist with exact keys:

| Field Key | Type |
|-----------|------|
| `haunt_features` | Multiselect |
| `admission_type` | Select |
| `admission_price` | Text |
| `haunt_season` | Text |
| `typical_hours` | Text |
| `age_restriction` | Text |

---

## Notes

- **Never change a Field Key after haunt owners have submitted data** — it will break all existing entries.
- All multiselect fields use **comma** as separator (confirmed from GeoDirectory source).
- Fields that are empty or non-existent render as hidden — no blank sections will appear.
- The discount bar (below hero) is controlled by the **OHA Discounts Frontend** plugin, not these Place Fields.
