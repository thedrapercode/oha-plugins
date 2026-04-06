# OHA Board Hub Suite — Handover

**Plugin name**: OHA Board Hub Suite
**Current version**: 2.2.13
**Zip filename**: `oha-board-hub-v2.2.13.zip`
**Folder inside zip**: `oha-board-hub/`
**Main plugin file**: `oha-board-hub/oha-board-hub-suite.php`
**Last updated**: 2026-03-26

---

## What This Plugin Does

Unified admin suite for the Ohio Haunters Association. Central hub for:
- OHA and listing-owner membership management (SWPM-backed)
- Stripe webhook handling (single endpoint for all OHA plugins)
- Member card tracking
- Vendor and sponsor management (CPTs)
- Board member dashboard (shortcode-gated)
- Haunt discounts system
- Dev log aggregation across OHA plugins

---

## Version Constants

| Constant | Value | Location |
|---|---|---|
| `OHA_BHS_VERSION` | `2.2.13` | `oha-board-hub-suite.php` |
| `OHA_BHS_OPT` | `oha_bh_suite_settings` | `oha-board-hub-suite.php` |
| `OHA_BHS_MENU` | `oha-board-hub` | `oha-board-hub-suite.php` |

In `oha-board-hub.lib.php` (the core integration class):
| Constant | Value |
|---|---|
| `OPT` (class const) | `oha_board_hub_settings` |
| `CAP` (class const) | `read_oha_board_hub` |

---

## Shortcodes

| Shortcode | File | Description |
|---|---|---|
| `[oha_board_hub]` | `oha-board-hub.lib.php` | Password-gated board member dashboard |
| `[oha_vendor_boardhub]` | `boardhub-admin.php` | Vendor-facing board hub admin UI |
| `[oha_stripe_billing_portal]` | `oha-stripe-minimal.lib.php` | Member self-service Stripe billing portal |
| `[oha_stripe_invoices]` | `oha-stripe-minimal.lib.php` | Member invoice history |
| `[oha_stripe_address]` | `oha-stripe-minimal.lib.php` | Member address update form |
| `[oha_membership_checkout]` | `oha-stripe-minimal.lib.php` | Stripe checkout for new OHA membership |
| `[oha_smart_membership_button]` | `oha-stripe-minimal.lib.php` | Context-aware join/renew/manage button |
| `[oha_haunt_discount]` | `oha-haunt-discounts.lib.php` | Member discount display (per haunt) |
| `[oha_haunt_discounts_admin]` | `oha-haunt-discounts.lib.php` | Admin UI for managing haunt discounts |

---

## Custom Post Types (CPTs)

| CPT slug | Description |
|---|---|
| `oha_vendor` | Convention vendor entries |
| `oha_sponsor` | OHA sponsor entries |
| `oha_guest` | Guest/speaker entries |

---

## WordPress Role Registered

| Role slug | Capability granted |
|---|---|
| `oha_board` | `read_oha_board_hub` |

OHA members use the native `subscriber` role.
Listing owners use the `listing_owner` role (registered by UsersWP or another plugin).

---

## Key User Meta Keys

| Meta key | Purpose |
|---|---|
| `uwp_account_type` | Written by board hub for ~4 users with value `oha_member`; NOT the standard UsersWP field |
| `_uwp_register_form_id` | UsersWP's actual user type field (form ID); form 1 = OHA member |
| `uwp_member_type` | Secondary UsersWP type field; rarely populated on this site |
| `oha_membership_paid` | Boolean/date meta written when membership payment confirmed |
| `oha_paid_member_since` | DateTime when user became a paid OHA member |
| `oha_card_sent` | `yes` if physical membership card has been mailed |
| `oha_card_sent_date` | Datetime when card was marked sent |
| `stripe_customer_id` | Stripe customer ID for the user |
| `oha_stripe_member_from_invoice` | Set when membership confirmed via Stripe invoice webhook |

---

## Custom Database Tables

| Table | Purpose |
|---|---|
| `wp_oha_status` | Card status tracking per member |
| `wp_oha_events` | Membership events log |

---

## Important WP Options

| Option key | Purpose |
|---|---|
| `oha_bh_suite_settings` | Suite-level settings (Stripe keys, SWPM levels, webhook secret) |
| `oha_board_hub_settings` | Board hub core settings (password, board contacts, notes) |
| `oha_bh_card_sent_map` | Map of user IDs to card-sent dates |
| `oha_bh_card_events` | Log of card events |
| `oha_sm_settings` | Simple Membership integration settings (legacy) |
| `oha_sa_settings` | Stripe admin settings (legacy key) |

---

## SWPM (Simple Membership) Integration

SWPM table: `wp_swpm_members_tbl`
Joined to WP users by email (case-insensitive).

**SWPM membership level defaults** (stored in `oha_bh_suite_settings`):

| Level ID | Purpose |
|---|---|
| 10 | OHA Member (current active level) |
| 2 | Listing Owner |
| 4 | Board member / old level (legacy) |
| 5 | Old level (legacy — 1 active record) |
| 6 | Vendors (default setting) |
| 8 | Sponsors (default setting) |

**CRITICAL — Membership count discrepancy (investigated 2026-03-26):**
- Board hub membership table shows 72 OHA members
- WordPress Users shows 82 subscribers
- Root cause: Board hub's 3-part member lookup finds: Part 1 `uwp_account_type=oha_member` (4 users) + Part 2 SWPM level 10 (68 users) + Part 3 `_uwp_register_form_id=1` fallback = 72 total
- 6 active SWPM records exist at old levels 4 (5 records) and 5 (1 record) from before SWPM was reconfigured to use level 10; board hub never counts these
- Remaining ~4 subscribers are likely test/orphan accounts with no payment data
- All 186 SWPM records are "active" (no expired memberships — purely a structural/migration issue)

---

## Board Hub 3-Part Membership Lookup (class-oha-members-table.php)

The membership table uses a UNION of 3 queries:
1. Users where `uwp_account_type = oha_member`
2. Users with email in SWPM at the configured OHA membership level (default: 10)
3. Users where `_uwp_register_form_id = 1` AND `uwp_account_type` is NOT set

Views: `new_paid`, `all_paid`, `all_listing`, `new_listing`, `cards_not_sent`, `all`

---

## Admin Page Structure (class-oha-admin-page.php)

Tabs: dashboard, membership, boardhub, stripe, vendor, webhooks, notifications, shortcodes, tools, debug, discounts

**Known broken tabs:**
- Tools tab calls `oha_sm_tools_page()` — function no longer exists (was from old Simple Membership integration)
- Debug tab calls `oha_sm_debug_page()` — same issue

---

## Dependencies

| Plugin | Purpose |
|---|---|
| Simple Membership (SWPM) | Primary membership backend; `wp_swpm_members_tbl` |
| UsersWP | User registration forms; `_uwp_register_form_id` meta |
| Stripe | Payment processing; webhook handled by this plugin |
| GeoDirectory | Listing count queries use `gd_place` post type |
| OHA Vendor Hub | `vendor_application` CPT used in some board views |

---

## Known Issues / TODOs

1. **SWPM legacy levels 4 and 5**: 6 active members whose level IDs were never updated when SWPM was reconfigured. Board hub never counts them. Fix: add "legacy level mapping" setting so admin can mark old levels as equivalent to current OHA level.

2. **Tools and Debug tabs broken**: Both call functions from old Simple Membership integration that no longer exist. Should be removed or replaced.

3. **3-part member lookup fragility**: The `uwp_account_type` and `_uwp_register_form_id=1` parts are holdovers from UsersWP migration and only cover 4 users. Long-term fix: normalize all members to a single canonical field.

4. **No canonical meta writer**: Multiple code paths write membership meta inconsistently. A single `oha_confirm_member($user_id, $level)` function called from all Stripe/SWPM paths would prevent future drift.

5. **Data Health**: No built-in way to see subscriber/SWPM mismatches in the admin. The `oha-member-audit.php` standalone tool was built for this (see audit tool section below).

---

## Audit Tool (oha-member-audit.php)

A standalone diagnostic plugin was created during the 2026-03-26 session:
- File: `oha-member-audit.php` at root of Stable Plugins
- Zip: `oha-member-audit-v1.3.0.zip`
- Accessible at: WP Admin > Users > OHA Member Audit
- Purpose: Finds subscribers who fail ALL 3 board hub membership tests and diagnoses why
- Diagnosis categories: "Old SWPM level" (yellow), "No SWPM + no payment" (red), "Has payment but no SWPM" (yellow), "SWPM says listing owner" (blue)
- Columns: ID, Name, Email, Registered, Listings, SWPM ID, SWPM Level, SWPM State, Stripe CID, Paid Meta, Diagnosis

---

## Session Summary — 2026-03-26

**Problem investigated**: OHA board hub shows 72 OHA members; WP Users shows 82. Sought to identify and understand root cause.

**Root cause found**:
- 68 SWPM level-10 + 4 `uwp_account_type` users = 72 board hub count
- 82 WP subscribers - 72 board hub = 10 uncounted
- 6 of the 10 are legitimate members at old SWPM levels 4/5 (never updated when SWPM was reconfigured)
- Remaining ~4 are test/orphan accounts with no payment data

**Deliverables**:
- `oha-member-audit.php` v1.3.0 — standalone diagnostic tool (3 iterations: v1.0.0, v1.1.0, v1.2.0, v1.3.0)
- `oha-member-audit-v1.3.0.zip` — installable zip

**Architecture recommendations given to user**:
1. Add legacy SWPM level mapping to board hub settings (immediate fix for 6 stranded members)
2. Create `oha_confirm_member()` canonical meta writer called from all membership paths
3. Add Data Health tab to membership section with normalization button
4. Long-term: migrate to `uwp_account_type` as single source of truth after normalization run
