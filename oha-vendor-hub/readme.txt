=== OHA Vendor Hub ===
Contributors: OHA Dev Team
Tags: vendor, application, e-signature, stripe, events
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.6.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete vendor management system for event organizers with applications, e-signatures, Stripe payments, and admin dashboard.

== Description ==

OHA Vendor Hub is a comprehensive vendor management solution designed for event organizers, specifically built for the Ohio Haunters Convention 2026.

= Features =

* **Vendor Application Forms** - Customizable application forms with duplicate prevention
* **E-Signature System** - Built-in HTML5 canvas signature pad (100% free, no third-party services)
* **Stripe Integration** - Accept payments via Stripe Payment Links
* **Board Hub Dashboard** - Admin interface for managing applications
* **Email Automation** - Automated emails for each application status
* **Pre-Population** - Send vendors pre-filled application links
* **Mobile Friendly** - Fully responsive design for all devices

= Two-Step Application Process =

1. **Initial Application** - Basic vendor information
2. **Final Application** - Venue details, e-signature, and payment

= E-Signature Features =

* Draw signature with mouse or touch
* Legally binding under ESIGN Act
* Captures signature, name, date, and IP address
* Complete terms & conditions display
* No monthly fees or third-party services required

= Stripe Payment =

* Test mode for practice
* Live mode for real payments
* Multiple booth selection
* Automatic price calculation
* Secure payment processing

= Shortcodes =

* `[oha_vendor_form]` - Initial application form
* `[oha_vendor_final_form]` - Final application with e-signature
* `[oha_vendor_board_hub]` - Admin dashboard
* `[oha_vendor_user_dashboard]` - User dashboard

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Vendor Applications → Settings
4. Configure your Stripe Payment Links
5. Create pages with the shortcodes

= Stripe Setup =

1. Create a Stripe account (free)
2. Create Payment Links in Stripe Dashboard
3. Configure one for test mode, one for live mode
4. Add the URLs to plugin settings
5. Test with test cards before going live

== Frequently Asked Questions ==

= Is the e-signature system legally valid? =

Yes, the e-signature system is compliant with the ESIGN Act (2000) and UETA standards. It captures signature, printed name, date, timestamp, and IP address.

= Do I need to pay for e-signatures? =

No! The e-signature system is built-in and completely free with no limits.

= What does Stripe charge? =

Stripe charges 2.9% + $0.30 per transaction. The plugin itself is free.

= Can vendors pay for multiple booths? =

Yes, Stripe Payment Links support quantity selection for multiple booth spaces.

= How do I send final application links to vendors? =

Use the Board Hub admin interface to send pre-populated links via email. The plugin includes a one-click send function.

= Does this work on mobile devices? =

Yes, the plugin is fully responsive and the signature pad works with touch input on phones and tablets.

== Screenshots ==

1. Initial vendor application form
2. Final application with e-signature pad
3. Board Hub admin dashboard
4. Vendor application detail view
5. Email automation settings
6. Stripe configuration

== Changelog ==

= 1.6.0 =
* Complete WordPress plugin package
* Integrated e-signature system
* Duplicate submission prevention
* Stripe payment integration
* Email automation
* Board Hub improvements
* Mobile optimization
* Security enhancements

= 1.5.1 =
* Added final application form
* Added e-signature functionality
* Added Stripe integration
* Improved validation

= 1.5.0 =
* Fixed duplicate submissions
* Added email tracking
* Improved error handling

= 1.4.2 =
* Initial public release
* Basic vendor application form
* Board Hub dashboard
* CSV export

== Upgrade Notice ==

= 1.6.0 =
Major update with e-signature system, Stripe integration, and many improvements. Backup before upgrading.

== Additional Info ==

For detailed documentation, visit the plugin's documentation folder after installation.

Support: This plugin is developed for the Ohio Haunters Association. For custom implementations, contact the development team.

Stripe is a trademark of Stripe, Inc. This plugin is not affiliated with or endorsed by Stripe.
