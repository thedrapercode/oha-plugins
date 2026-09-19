=== OHA Board Hub — Awards Addon ===
Contributors: oha
Requires at least: 5.8
Tested up to: 6.x
Requires PHP: 7.4
Stable tag: 6.5.12
License: GPLv2 or later

Shortcode-only add-on that captures Kadence (free) form submissions for Haunt Awards into a CPT, adds Board Hub-style admin listing with status changes and CSV export, supports bulk certificate uploads (ZIP), and provides an owner shortcode to download low-res certificates and initiate a $10 print order via Stripe Checkout (stub).

== Setup ==
1. Install and activate.
2. Go to **OHA Awards → Settings** and map Kadence field keys:
   - Haunt ID
   - Actor Name
   - Actor Email
   - Actor Photo
   Optionally enable “Enforce one submission per haunt”.
3. Place your Kadence form on a page for logged-in owners.
   (The add-on includes a bridge so free Kadence Blocks triggers this capture.)
4. Board members: **OHA Awards → Submissions** to review, set status, and export CSV.
5. Bulk certificates: upload a ZIP with files named like 1234.pdf (entry ID). Low-res previews generated when Imagick is available.
6. Owner page: use **[oha_awards]** (or **[oha_my_award]**) shortcode.

Replace the Stripe stub with your real Stripe Checkout integration + webhook.
