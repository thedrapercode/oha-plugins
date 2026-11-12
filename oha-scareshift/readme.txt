=== ScareShift - Haunter RPG System ===
Contributors: ScareShift Team
Tags: haunt, halloween, rpg, character creator, game
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.3.0
Requires PHP: 7.4
License: GPLv2 or later

Create haunt characters, play cooperative shifts, and master the art of fear through team-based gameplay.

== Description ==

ScareShift is a comprehensive RPG system designed specifically for haunted attraction actors, haunt owners, and Halloween enthusiasts. It combines character creation, skill development, and cooperative gameplay to create an engaging off-season training and entertainment platform.

= Features =

**Character Creator**
* Create unique scare characters with 5 core stats
* Answer questions about your scare style
* Export characters as PNG or PDF
* Save up to 20 characters per user

**Shift Mode (NEW in v1.3.0!)**
* Cooperative gameplay - your crew vs challenging guests
* 3-wave encounters (15-20 minute sessions)
* Different NPC guest types (Green, Yellow, Orange difficulty)
* Characters grow stronger through gameplay
* Stats increase based on performance
* Team-based decision making

**Daily Scenario Challenge**
* Face a new haunt situation every day
* Make choices that teach real-world haunt skills
* Earn XP for completing scenarios

**Progression System**
* Earn XP through character creation, scenarios, and shifts
* Level up based on your total XP
* Character stats improve over time

== Installation ==

1. Upload the `scareshift` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to ScareShift → Settings to configure the plugin
4. Create pages and add the shortcodes (see Shortcodes section)

== Shortcodes ==

**[scareshift_creator]**
Displays the character creation studio

**[scareshift_library]**
Shows logged-in users their saved characters

**[scareshift_daily_challenge]**
Displays today's scenario challenge (requires login)

**[scareshift_shift_mode]** (NEW!)
Play cooperative shift mode (requires login)

**[scareshift_scenario_history]**
Shows a user's completed scenario history

**[scareshift_tutorial]**
Displays the tutorial/help page

== Changelog ==

= 1.3.0 =
* Added Shift Mode - Cooperative gameplay system!
* Your characters work together against NPC guests
* 3-wave encounters with different difficulty levels
* Characters grow stronger - stats increase based on performance
* 5 encounter types teaching real haunt situations
* Post-shift results with star ratings
* Character stat growth tracking
* Solo mode using your saved characters
* 15-20 minute engaging gameplay sessions

= 1.2.0 =
* Added Daily Scenario Challenge system
* Implemented XP and leveling system
* Added tutorial/walkthrough for new users
* Completely redesigned UI with modern dark theme
* Added admin stats dashboard
* Added scenario management system
* Improved code organization
* Enhanced mobile responsiveness

= 1.1.1 =
* Added Safety & Culture Charter modal
* Added admin settings page
* Made configurations editable via admin

== Upgrade Notice ==

= 1.3.0 =
Major update! Adds cooperative Shift Mode gameplay. Your characters now grow stronger over time!


== Description ==

ScareShift is a comprehensive RPG system designed specifically for haunted attraction actors, haunt owners, and Halloween enthusiasts. It combines character creation, skill development, and cooperative gameplay to create an engaging off-season training and entertainment platform.

= Features =

**Character Creator**
* Create unique scare characters with 5 core stats: Fear, Agility, Endurance, Voice, and Presence
* Answer questions about your scare style to generate realistic character stats
* Export characters as PNG or PDF for sharing
* Save up to 20 characters per user
* Load sample characters for inspiration

**Daily Scenario Challenge**
* Face a new haunt situation every day
* Make choices that teach real-world haunt skills
* Earn XP for completing scenarios
* Learn from realistic outcomes based on professional haunt practices
* Track your scenario history

**Progression System**
* Earn XP through character creation and daily challenges
* Level up based on your total XP
* Track your progress over time
* View player leaderboards (admins only)

**Safety-First Design**
* Teaches proper safety protocols through gameplay
* Penalizes unsafe choices (grabbing guests, blocking exits)
* Rewards vocal care, physical conditioning, and professional behavior
* Includes comprehensive Safety & Culture Charter

**Customizable**
* Edit scoring configurations via admin panel
* Add or modify daily scenarios
* Control XP rewards
* Enable/disable features as needed

== Installation ==

1. Upload the `scareshift` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to ScareShift → Settings to configure the plugin
4. Create pages and add the shortcodes (see Shortcodes section)

== Shortcodes ==

**[scareshift_creator]**
Displays the character creation studio where users can build and save scare characters.

**[scareshift_library]**
Shows logged-in users their saved characters with download and delete options.

**[scareshift_daily_challenge]**
Displays today's scenario challenge (requires login).

**[scareshift_scenario_history]**
Shows a user's completed scenario history (requires login).

**[scareshift_tutorial]**
Displays the tutorial/help page for new users.

== Frequently Asked Questions ==

= Do users need to be logged in? =

Users can view and create characters without logging in, but they cannot save characters or play daily scenarios. We recommend requiring login for the full experience.

= How does the XP system work? =

Users earn XP by:
* Creating their first character (50 XP)
* Completing daily scenarios (10 XP default, configurable)

XP determines player level: Level = floor(sqrt(XP / 100))

= Can I add my own scenarios? =

Yes! Go to ScareShift → Scenarios in your admin panel. You can add, edit, or remove scenarios in JSON format. A template is provided.

= How are daily scenarios chosen? =

The plugin uses the current date as a seed to randomly select from your scenario pool. This means everyone sees the same scenario on the same day, but it changes daily.

= Can I customize the stats/scoring? =

Yes! Go to ScareShift → Settings and edit the scoring configuration JSON. The system is highly customizable.

= Is this competitive or cooperative? =

ScareShift is designed to be cooperative. There are no leaderboards exposed to users, and the focus is on learning and improvement, not winning.

== Screenshots ==

1. Character Creator - Build unique scare personas
2. Character Sheet - View stats and export options
3. Daily Challenge - Face realistic haunt scenarios
4. Scenario Outcome - Learn from your choices
5. Admin Settings - Customize every aspect

== Changelog ==

= 1.2.0 =
* Added Daily Scenario Challenge system
* Implemented XP and leveling system
* Added tutorial/walkthrough for new users
* Completely redesigned UI with modern dark theme
* Added admin stats dashboard
* Added scenario management system
* Improved code organization with class-based structure
* Enhanced mobile responsiveness
* Added comprehensive safety features
* Included 10 default scenarios teaching real haunt skills

= 1.1.1 =
* Added Safety & Culture Charter modal
* Added admin settings page
* Made configurations editable via admin
* Added library shortcode for saved characters

= 1.1.0 =
* Initial release
* Character creator with stat calculation
* Basic save/load functionality

== Upgrade Notice ==

= 1.2.0 =
Major update! Adds gameplay mechanics, XP system, daily challenges, and improved UI. Backup your site before upgrading.

== Configuration ==

After installation, configure these settings in ScareShift → Settings:

* **Enable Charter Gate** - Show safety charter before first use
* **Enable Tutorial** - Show tutorial for new users
* **Enable Daily Challenge** - Allow daily scenario gameplay
* **XP Per Scenario** - How much XP to award per scenario (default: 10)
* **Scenarios Per Day** - How many scenarios users can complete daily (default: 1)

== For Developers ==

ScareShift uses a modern class-based architecture:

* `ScareShift` - Main plugin class
* `ScareShift_Character_Creator` - Character creation and management
* `ScareShift_Daily_Challenge` - Daily scenario system
* `ScareShift_Tutorial` - Tutorial/help system
* `ScareShift_Admin` - Admin panel and settings

REST API endpoints:
* `/scareshift/v1/character/save` - Save character
* `/scareshift/v1/character/list` - List user's characters
* `/scareshift/v1/character/delete` - Delete character
* `/scareshift/v1/challenge/today` - Get today's scenario
* `/scareshift/v1/challenge/submit` - Submit scenario choice

== Support ==

For support, feature requests, or bug reports, please contact the plugin author or visit the support forums.

== Credits ==

Built for the haunt community with love and fear. Special thanks to all the haunt actors who tested and provided feedback.
