<?php
/**
 * PWA App Download / Mobile App Marketing Page Shortcode
 *
 * [oha_pwa_download]
 *
 * App-store-style landing page for the OHA Convention PWA.
 * - Hero with screenshot or CSS phone mockup
 * - Desktop: QR code (auto-generated) + Chrome "Send to your devices" guide
 * - Mobile: banner directing straight to install steps
 * - Optional promo video (YouTube / Vimeo / direct MP4)
 * - Feature cards with optional screenshot thumbnails -> lightbox
 * - OS-tabbed install instructions (auto-detects platform)
 * - Notification setup walkthrough
 * - Version display
 * All content configurable via PWA Settings > App Download Page.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Extract a clean embed URL from a YouTube / Vimeo link.
 * Returns '' for unrecognised URLs (caller should check for direct video extension).
 */
function oha_pwa_dl_get_embed_url( $url ) {
    if ( preg_match( '#(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([a-zA-Z0-9_-]{11})#', $url, $m ) ) {
        return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0&modestbranding=1&color=white';
    }
    if ( preg_match( '#vimeo\.com/(\d+)#', $url, $m ) ) {
        return 'https://player.vimeo.com/video/' . $m[1] . '?color=AD3119&title=0&byline=0';
    }
    return '';
}

function oha_pwa_download_shortcode( $atts ) {

    // ── Existing options ─────────────────────────────────────────────────────
    $app_name      = esc_html( get_option( 'oha_pwa_dl_app_name',      'OHC Convention App' ) );
    $tagline       = esc_html( get_option( 'oha_pwa_dl_tagline',       'Your Official Convention Companion' ) );
    $description   = esc_html( get_option( 'oha_pwa_dl_description',   '' ) );
    $conv_url      = esc_url(  get_option( 'oha_pwa_dl_convention_url', home_url( '/convention/' ) ) );
    $show_passport = (bool)    get_option( 'oha_pwa_dl_show_passport',  true );

    // ── Screenshot options ───────────────────────────────────────────────────
    $sc_hero    = esc_url( get_option( 'oha_pwa_dl_screenshot_hero',          '' ) );
    $sc_sched   = esc_url( get_option( 'oha_pwa_dl_screenshot_schedule',      '' ) );
    $sc_map     = esc_url( get_option( 'oha_pwa_dl_screenshot_map',           '' ) );
    $sc_vendors = esc_url( get_option( 'oha_pwa_dl_screenshot_vendors',       '' ) );
    $sc_pass    = esc_url( get_option( 'oha_pwa_dl_screenshot_passport',      '' ) );
    $sc_notif   = esc_url( get_option( 'oha_pwa_dl_screenshot_notifications', '' ) );
    $sc_offline = esc_url( get_option( 'oha_pwa_dl_screenshot_offline',       '' ) );

    // ── Video options ────────────────────────────────────────────────────────
    $video_url    = get_option( 'oha_pwa_dl_video_url',   '' );
    $video_title  = esc_html( get_option( 'oha_pwa_dl_video_title', 'See It In Action' ) );
    $embed_url    = $video_url ? oha_pwa_dl_get_embed_url( $video_url ) : '';
    $direct_video = ( $video_url && ! $embed_url && preg_match( '#\.(mp4|webm|ogg)$#i', $video_url ) )
                    ? esc_url( $video_url ) : '';

    // ── Version ──────────────────────────────────────────────────────────────
    $show_version = (bool) get_option( 'oha_pwa_dl_show_version', true );

    // ── QR code (auto-generated from current page URL) ───────────────────────
    $qr_page_url = home_url( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) ?: '/' );
    $qr_src      = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='
                   . urlencode( $qr_page_url )
                   . '&color=ffffff&bgcolor=1a1a1a&margin=10&format=svg';

    // ── Build screenshot list for lightbox ───────────────────────────────────
    $sc_map_data = [
        'hero'          => [ 'title' => 'App Preview',        'url' => $sc_hero    ],
        'schedule'      => [ 'title' => 'Full Event Schedule','url' => $sc_sched   ],
        'map'           => [ 'title' => 'Interactive Map',    'url' => $sc_map     ],
        'vendors'       => [ 'title' => 'Vendor Favorites',   'url' => $sc_vendors ],
        'passport'      => [ 'title' => 'Haunt Passport',     'url' => $sc_pass    ],
        'notifications' => [ 'title' => 'Push Notifications', 'url' => $sc_notif   ],
        'offline'       => [ 'title' => 'Works Offline',      'url' => $sc_offline ],
    ];
    $lb_data = [];
    foreach ( $sc_map_data as $key => $sc ) {
        if ( ! empty( $sc['url'] ) ) {
            $lb_data[] = [ 'key' => $key, 'title' => $sc['title'], 'url' => $sc['url'] ];
        }
    }
    $lb_json      = wp_json_encode( $lb_data );
    $has_lb       = count( $lb_data ) > 0;
    $hero_trigger = $has_lb && $sc_hero;

    ob_start();
    ?>
<div class="oha-dl-page">

<!-- ── MOBILE INSTALL BANNER (JS shows on mobile only) ─────────────────── -->
<div class="oha-dl-mobile-banner" id="oha-dl-mobile-banner" style="display:none;">
    <span id="oha-dl-mob-label">&#128242; You&#8217;re on mobile</span>. Install this as a free app in seconds. No App Store needed!
    <a href="#oha-dl-how-to-install" class="oha-dl-mob-cta">Show Me How &#8595;</a>
</div>

<!-- ── HERO ─────────────────────────────────────────────────────────────── -->
<div class="oha-dl-hero">
    <div class="oha-dl-hero-inner">
        <div class="oha-dl-hero-text">
            <div class="oha-dl-hero-eyebrow">&#127881; Free Mobile App</div>
            <h1 class="oha-dl-app-name"><?php echo $app_name; ?></h1>
            <p class="oha-dl-tagline"><?php echo $tagline; ?></p>
            <?php if ( $description ) : ?>
            <p class="oha-dl-desc"><?php echo $description; ?></p>
            <?php endif; ?>
            <div class="oha-dl-hero-btns">
                <button id="oha-dl-hero-install-btn" class="oha-dl-btn oha-dl-btn-primary" style="display:none;">&#128242; Install the App</button>
                <a href="#oha-dl-how-to-install" class="oha-dl-btn oha-dl-btn-secondary">How to Install &#8595;</a>
                <a href="<?php echo $conv_url; ?>" class="oha-dl-btn oha-dl-btn-ghost">Open Convention &#8594;</a>
            </div>
            <div class="oha-dl-badges">
                <span>&#9989; Free</span>
                <span>&#9989; No App Store</span>
                <span>&#9989; Works Offline</span>
                <span>&#9989; Android &amp; iPhone</span>
            </div>
            <?php if ( $show_version ) : ?>
            <div class="oha-dl-ver">App v<?php echo esc_html( OHA_PWA_VERSION ); ?></div>
            <?php endif; ?>
        </div>
        <div class="oha-dl-hero-visual" aria-hidden="true">
            <?php if ( $sc_hero ) : ?>
            <div class="oha-dl-hero-screenshot<?php echo $hero_trigger ? ' oha-lb-trigger' : ''; ?>"
                 <?php if ( $hero_trigger ) echo 'data-lb-key="hero"'; ?>>
                <img src="<?php echo $sc_hero; ?>" alt="<?php echo esc_attr( $app_name ); ?> screenshot" class="oha-dl-hero-img">
                <?php if ( $hero_trigger ) : ?><div class="oha-dl-expand-hint">&#128269; Preview</div><?php endif; ?>
            </div>
            <?php else : ?>
            <div class="oha-dl-phone">
                <div class="oha-dl-phone-screen">
                    <div class="oha-dl-phone-status">9:41</div>
                    <div class="oha-dl-phone-app-icon">&#127875;</div>
                    <div class="oha-dl-phone-app-label"><?php echo $app_name; ?></div>
                    <div class="oha-dl-phone-nav">
                        <span>&#128197;</span><span>&#128506;&#65039;</span><span>&#11088;</span><span>&#129259;</span><span>&#8505;&#65039;</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── DESKTOP: GET IT ON YOUR PHONE (hidden on mobile by CSS + JS) ─────── -->
<div class="oha-dl-section oha-dl-desktop-only">
    <h2 class="oha-dl-section-title">&#128242; Get It On Your Phone</h2>
    <p class="oha-dl-section-sub">You&#8217;re on a desktop. Send this page to your phone and install in seconds.</p>
    <div class="oha-dl-bridge">
        <div class="oha-dl-bridge-qr">
            <img src="<?php echo esc_attr( $qr_src ); ?>" width="160" height="160"
                 alt="QR code — scan to open on your phone" class="oha-dl-qr-img" loading="lazy">
            <p class="oha-dl-qr-label">Scan with your<br>phone&#8217;s camera</p>
        </div>
        <div class="oha-dl-bridge-or">or</div>
        <div class="oha-dl-bridge-share">
            <strong>Use Chrome: Send to Your Devices</strong>
            <ol class="oha-dl-steps oha-dl-steps-sm" style="margin-top:10px;">
                <li><span class="oha-dl-step-n">1</span><div class="oha-dl-step-body">Click the <strong>&#8942; three-dot menu</strong> in Chrome</div></li>
                <li><span class="oha-dl-step-n">2</span><div class="oha-dl-step-body">Choose <strong>&#8220;Cast, save, and share&#8221;</strong></div></li>
                <li><span class="oha-dl-step-n">3</span><div class="oha-dl-step-body">Tap <strong>&#8220;Send to your devices&#8221;</strong> and select your phone</div></li>
            </ol>
            <button id="oha-dl-share-btn" class="oha-dl-btn oha-dl-btn-secondary" style="display:none;margin-top:10px;">&#128228; Share This Page</button>
        </div>
    </div>
</div>

<!-- ── VIDEO (shown only if URL is configured) ───────────────────────────── -->
<?php if ( $embed_url || $direct_video ) : ?>
<div class="oha-dl-section">
    <h2 class="oha-dl-section-title">&#127916; <?php echo $video_title; ?></h2>
    <div class="oha-dl-video-wrap">
        <?php if ( $embed_url ) : ?>
        <iframe src="<?php echo esc_url( $embed_url ); ?>" frameborder="0"
                allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture"
                allowfullscreen class="oha-dl-video-frame" loading="lazy"></iframe>
        <?php else : ?>
        <video controls class="oha-dl-video-frame" preload="metadata">
            <source src="<?php echo $direct_video; ?>" type="video/mp4">
        </video>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── FEATURES ─────────────────────────────────────────────────────────── -->
<div class="oha-dl-section">
    <h2 class="oha-dl-section-title">Everything You Need at the Convention</h2>
    <p class="oha-dl-section-sub">One free app. All the tools that make convention day easier.</p>

    <div class="oha-dl-features-grid">

        <div class="oha-dl-feature-card<?php echo $sc_sched ? ' oha-lb-trigger' : ''; ?>"
             <?php if ( $sc_sched ) echo 'data-lb-key="schedule"'; ?>>
            <?php if ( $sc_sched ) : ?>
            <div class="oha-dl-card-thumb" style="background-image:url('<?php echo $sc_sched; ?>')">
                <div class="oha-dl-card-thumb-hint">&#128269; View Screenshot</div>
            </div>
            <?php endif; ?>
            <div class="oha-dl-feature-icon">&#128197;</div>
            <h3>Full Event Schedule</h3>
            <p>Browse every session, class, and event. Save your favorites and get automatic 5-minute reminders so you never miss a thing.</p>
            <div class="oha-dl-feature-tags">
                <span>&#11088; Favorites</span><span>&#9200; Reminders</span><span>&#128266; Offline</span>
            </div>
        </div>

        <div class="oha-dl-feature-card<?php echo $sc_map ? ' oha-lb-trigger' : ''; ?>"
             <?php if ( $sc_map ) echo 'data-lb-key="map"'; ?>>
            <?php if ( $sc_map ) : ?>
            <div class="oha-dl-card-thumb" style="background-image:url('<?php echo $sc_map; ?>')">
                <div class="oha-dl-card-thumb-hint">&#128269; View Screenshot</div>
            </div>
            <?php endif; ?>
            <div class="oha-dl-feature-icon">&#128506;&#65039;</div>
            <h3>Interactive Map</h3>
            <p>Navigate the venue with a live interactive map. Find vendor booths, session rooms, and key areas in seconds.</p>
            <div class="oha-dl-feature-tags">
                <span>&#128205; Live Pins</span><span>&#128269; Search</span><span>&#127981; Regions</span>
            </div>
        </div>

        <div class="oha-dl-feature-card<?php echo $sc_vendors ? ' oha-lb-trigger' : ''; ?>"
             <?php if ( $sc_vendors ) echo 'data-lb-key="vendors"'; ?>>
            <?php if ( $sc_vendors ) : ?>
            <div class="oha-dl-card-thumb" style="background-image:url('<?php echo $sc_vendors; ?>')">
                <div class="oha-dl-card-thumb-hint">&#128269; View Screenshot</div>
            </div>
            <?php endif; ?>
            <div class="oha-dl-feature-icon">&#128717;</div>
            <h3>Vendor Favorites</h3>
            <p>Browse all vendors and heart the ones you must visit. Your must-see list is always one tap away, even offline.</p>
            <div class="oha-dl-feature-tags">
                <span>&#10084;&#65039; Save Vendors</span><span>&#128506; Find on Map</span>
            </div>
        </div>

        <?php if ( $show_passport ) : ?>
        <div class="oha-dl-feature-card<?php echo $sc_pass ? ' oha-lb-trigger' : ''; ?>"
             <?php if ( $sc_pass ) echo 'data-lb-key="passport"'; ?>>
            <?php if ( $sc_pass ) : ?>
            <div class="oha-dl-card-thumb" style="background-image:url('<?php echo $sc_pass; ?>')">
                <div class="oha-dl-card-thumb-hint">&#128269; View Screenshot</div>
            </div>
            <?php endif; ?>
            <div class="oha-dl-feature-icon">&#129259;</div>
            <h3>Haunt Passport</h3>
            <p>Collect stamps from haunts by scanning QR codes. Build your passport, track your collection, and show it off!</p>
            <div class="oha-dl-feature-tags">
                <span>&#128247; QR Scanner</span><span>&#127991;&#65039; Stamps</span><span>&#129351; Achievements</span>
            </div>
        </div>
        <?php endif; ?>

        <div class="oha-dl-feature-card<?php echo $sc_notif ? ' oha-lb-trigger' : ''; ?>"
             <?php if ( $sc_notif ) echo 'data-lb-key="notifications"'; ?>>
            <?php if ( $sc_notif ) : ?>
            <div class="oha-dl-card-thumb" style="background-image:url('<?php echo $sc_notif; ?>')">
                <div class="oha-dl-card-thumb-hint">&#128269; View Screenshot</div>
            </div>
            <?php endif; ?>
            <div class="oha-dl-feature-icon">&#128276;</div>
            <h3>Push Notifications</h3>
            <p>Get real-time alerts from convention organizers: schedule changes, special announcements, and reminders for saved sessions.</p>
            <div class="oha-dl-feature-tags">
                <span>&#128226; Announcements</span><span>&#9200; Reminders</span><span>&#128680; Alerts</span>
            </div>
        </div>

        <div class="oha-dl-feature-card<?php echo $sc_offline ? ' oha-lb-trigger' : ''; ?>"
             <?php if ( $sc_offline ) echo 'data-lb-key="offline"'; ?>>
            <?php if ( $sc_offline ) : ?>
            <div class="oha-dl-card-thumb" style="background-image:url('<?php echo $sc_offline; ?>')">
                <div class="oha-dl-card-thumb-hint">&#128269; View Screenshot</div>
            </div>
            <?php endif; ?>
            <div class="oha-dl-feature-icon">&#128266;</div>
            <h3>Works Offline</h3>
            <p>Cell service spotty at the venue? No problem. Schedule, map, and favorites all work without a connection.</p>
            <div class="oha-dl-feature-tags">
                <span>&#128266; No Wi-Fi Needed</span><span>&#9889; Instant Load</span>
            </div>
        </div>

    </div>
</div>

<!-- ── LIGHTBOX ──────────────────────────────────────────────────────────── -->
<?php if ( $has_lb ) : ?>
<div id="oha-lb" class="oha-lb" role="dialog" aria-modal="true" aria-label="Screenshot viewer" style="display:none;" tabindex="-1">
    <div class="oha-lb-overlay" id="oha-lb-overlay"></div>
    <div class="oha-lb-box">
        <button class="oha-lb-arrow oha-lb-prev" id="oha-lb-prev" aria-label="Previous">&#8249;</button>
        <img class="oha-lb-img" id="oha-lb-img" src="" alt="">
        <button class="oha-lb-arrow oha-lb-next" id="oha-lb-next" aria-label="Next">&#8250;</button>
    </div>
    <button class="oha-lb-close" id="oha-lb-close" aria-label="Close">&#10005;</button>
    <div class="oha-lb-caption" id="oha-lb-caption"></div>
</div>
<?php endif; ?>

<!-- ── INSTALL INSTRUCTIONS ─────────────────────────────────────────────── -->
<div class="oha-dl-section" id="oha-dl-how-to-install">
    <h2 class="oha-dl-section-title">&#128242; How to Install the App</h2>
    <p class="oha-dl-section-sub">It&#8217;s free, takes about 30 seconds, and no App Store is needed.</p>

    <div class="oha-dl-os-tabs" role="tablist">
        <button class="oha-dl-os-tab" data-os="android" role="tab">&#129302; Android</button>
        <button class="oha-dl-os-tab" data-os="ios"     role="tab">&#127822; iPhone / iPad</button>
        <button class="oha-dl-os-tab" data-os="desktop" role="tab">&#128187; Desktop</button>
    </div>

    <div class="oha-dl-os-panel" id="oha-dl-os-android" style="display:none;">
        <div class="oha-dl-native-cta" id="oha-dl-android-native" style="display:none;">
            <p>&#127881; <strong>Your browser supports one-tap install!</strong></p>
            <button id="oha-dl-android-native-btn" class="oha-dl-btn oha-dl-btn-primary oha-dl-btn-block">&#128242; Tap Here to Install Now</button>
        </div>
        <ol class="oha-dl-steps">
            <li><span class="oha-dl-step-n">1</span><div class="oha-dl-step-body"><strong>Open this page in Chrome</strong><span>Make sure you&#8217;re in Google Chrome, not a browser inside Gmail, Facebook, or another app.</span></div></li>
            <li><span class="oha-dl-step-n">2</span><div class="oha-dl-step-body"><strong>Tap the three-dot menu &#8942; in the top-right</strong><span>It&#8217;s the vertical dots in the upper-right corner of Chrome.</span></div></li>
            <li><span class="oha-dl-step-n">3</span><div class="oha-dl-step-body"><strong>Tap &#8220;Add to Home Screen&#8221; or &#8220;Install App&#8221;</strong><span>You may also see an install banner pop up at the bottom. Tap that instead.</span></div></li>
            <li><span class="oha-dl-step-n">4</span><div class="oha-dl-step-body"><strong>Tap &#8220;Install&#8221;</strong><span>The app will be added to your home screen and app drawer.</span></div></li>
            <li><span class="oha-dl-step-n">5</span><div class="oha-dl-step-body"><strong>Open it from your home screen &#127881;</strong><span>Tap the new icon for the full-screen app experience!</span></div></li>
        </ol>
        <div class="oha-dl-tip">&#128161; <strong>Tip:</strong> If the three-dot menu doesn&#8217;t show the option, look for an install banner at the bottom of the screen instead.</div>
    </div>

    <div class="oha-dl-os-panel" id="oha-dl-os-ios" style="display:none;">
        <div class="oha-dl-warning">&#9888;&#65039; <strong>Safari is required on iPhone and iPad.</strong> If you opened this page from Gmail, Facebook, or another app, tap the &#8942; menu and choose &#8220;Open in Safari&#8221; before continuing.</div>
        <ol class="oha-dl-steps">
            <li><span class="oha-dl-step-n">1</span><div class="oha-dl-step-body"><strong>Open this page in Safari</strong><span>Copy the link and paste it into Safari if you&#8217;re currently in another browser.</span></div></li>
            <li><span class="oha-dl-step-n">2</span><div class="oha-dl-step-body"><strong>Tap the Share button &#9096;&#65039;</strong><span>The Share button (a box with an upward arrow) is at the bottom on iPhone, top-right on iPad.</span></div></li>
            <li><span class="oha-dl-step-n">3</span><div class="oha-dl-step-body"><strong>Scroll down and tap &#8220;Add to Home Screen&#8221;</strong><span>Scroll down in the share sheet if you don&#8217;t see it right away.</span></div></li>
            <li><span class="oha-dl-step-n">4</span><div class="oha-dl-step-body"><strong>Name it and tap &#8220;Add&#8221;</strong><span>We suggest &#8220;OHC 2026&#8221;. Tap Add in the top-right corner to confirm.</span></div></li>
            <li><span class="oha-dl-step-n">5</span><div class="oha-dl-step-body"><strong>Open it from your Home Screen &#127881;</strong><span>Find the new icon and tap it for the full-screen, native app experience!</span></div></li>
        </ol>
        <div class="oha-dl-tip">&#128161; <strong>Push notifications on iPhone require iOS 16.4 or later.</strong> Go to Settings &#8594; General &#8594; Software Update to check. Notifications require opening the app from your Home Screen (not Safari).</div>
    </div>

    <div class="oha-dl-os-panel" id="oha-dl-os-desktop" style="display:none;">
        <ol class="oha-dl-steps">
            <li><span class="oha-dl-step-n">1</span><div class="oha-dl-step-body"><strong>Use Chrome, Edge, or Brave</strong><span>Desktop install works in any Chromium-based browser.</span></div></li>
            <li><span class="oha-dl-step-n">2</span><div class="oha-dl-step-body"><strong>Look for the install icon in the address bar</strong><span>A small &#10010; or computer-with-arrow icon appears on the right side of the address bar.</span></div></li>
            <li><span class="oha-dl-step-n">3</span><div class="oha-dl-step-body"><strong>Click it, then click &#8220;Install&#8221;</strong><span>A small confirmation dialog will appear.</span></div></li>
            <li><span class="oha-dl-step-n">4</span><div class="oha-dl-step-body"><strong>Done! The app opens in its own window &#127881;</strong><span>A desktop shortcut will be created. Great for checking the schedule on a bigger screen!</span></div></li>
        </ol>
        <div class="oha-dl-tip">&#128161; <strong>Don&#8217;t see the install icon?</strong> The app may already be installed &#8212; check your desktop, taskbar, or Start menu.</div>
    </div>
</div>

<!-- ── NOTIFICATIONS ─────────────────────────────────────────────────────── -->
<div class="oha-dl-section">
    <h2 class="oha-dl-section-title">&#128276; Turn On Notifications</h2>
    <p class="oha-dl-section-sub">Notifications make the app dramatically more useful at the convention.</p>
    <div class="oha-dl-notif-reasons">
        <div class="oha-dl-notif-reason"><span class="oha-dl-notif-ico">&#9200;</span><div><strong>5-Minute Session Reminders</strong><p>Save sessions to favorites and get an automatic heads-up 5 minutes before they start. Never walk in late.</p></div></div>
        <div class="oha-dl-notif-reason"><span class="oha-dl-notif-ico">&#128226;</span><div><strong>Live Convention Announcements</strong><p>Real-time messages from OHA organizers: schedule changes, room moves, special events, and more.</p></div></div>
        <div class="oha-dl-notif-reason"><span class="oha-dl-notif-ico">&#128680;</span><div><strong>Emergency and Time-Sensitive Alerts</strong><p>Stay informed if something important happens at the venue. No spam &#8212; only what matters.</p></div></div>
    </div>
    <h3 class="oha-dl-sub-title">How to Enable Notifications</h3>
    <div class="oha-dl-notif-tabs" role="tablist">
        <button class="oha-dl-notif-tab" data-notif="android" role="tab">&#129302; Android</button>
        <button class="oha-dl-notif-tab" data-notif="ios"     role="tab">&#127822; iPhone</button>
    </div>
    <div class="oha-dl-notif-panel" id="oha-dl-notif-android" style="display:none;">
        <ol class="oha-dl-steps oha-dl-steps-sm">
            <li><span class="oha-dl-step-n">1</span><div class="oha-dl-step-body"><strong>Install and open the app</strong> from your home screen (steps above)</div></li>
            <li><span class="oha-dl-step-n">2</span><div class="oha-dl-step-body"><strong>Tap &#8220;Enable Notifications&#8221;</strong> anywhere you see it in the app</div></li>
            <li><span class="oha-dl-step-n">3</span><div class="oha-dl-step-body">When Chrome asks &#8220;Allow notifications?&#8221;, tap <strong>Allow</strong></div></li>
            <li><span class="oha-dl-step-n">4</span><div class="oha-dl-step-body">If still not working: <strong>Settings &#8594; Apps &#8594; Chrome &#8594; Notifications &#8594; turn ON</strong></div></li>
        </ol>
    </div>
    <div class="oha-dl-notif-panel" id="oha-dl-notif-ios" style="display:none;">
        <div class="oha-dl-warning" style="margin-bottom:14px;">&#9888;&#65039; <strong>Requires iOS 16.4 or later</strong> and the app must be opened from your Home Screen (not Safari).</div>
        <ol class="oha-dl-steps oha-dl-steps-sm">
            <li><span class="oha-dl-step-n">1</span><div class="oha-dl-step-body"><strong>Install from Safari</strong> and open the app from your Home Screen</div></li>
            <li><span class="oha-dl-step-n">2</span><div class="oha-dl-step-body"><strong>Tap &#8220;Enable Notifications&#8221;</strong> in the app</div></li>
            <li><span class="oha-dl-step-n">3</span><div class="oha-dl-step-body">When iOS asks &#8220;Allow Notifications?&#8221;, tap <strong>Allow</strong></div></li>
            <li><span class="oha-dl-step-n">4</span><div class="oha-dl-step-body">Missed it? <strong>Settings &#8594; Notifications &#8594; scroll to the app &#8594; Allow &#8594; ON</strong></div></li>
        </ol>
    </div>
</div>

<!-- ── ALREADY INSTALLED FOOTER ──────────────────────────────────────────── -->
<div class="oha-dl-already">
    <p>Already have the app?
       <a href="<?php echo $conv_url; ?>" class="oha-dl-already-link">Open the Convention Hub &#8594;</a>
       <?php if ( $show_version ) : ?><span class="oha-dl-ver-foot">v<?php echo esc_html( OHA_PWA_VERSION ); ?></span><?php endif; ?>
    </p>
</div>

</div><!-- .oha-dl-page -->

<style>
/* ── OHA App Download Page ────────────────────────────────────────────────── */
.oha-dl-page{max-width:840px;margin:0 auto;padding:4px 0 32px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#f0f0f0;}

/* Mobile install banner */
.oha-dl-mobile-banner{background:#1a0a00;border:1px solid rgba(173,49,25,.55);border-radius:12px;padding:14px 18px;margin-bottom:14px;font-size:13px;color:#ccc;line-height:1.6;text-align:center;}
.oha-dl-mob-cta{display:inline-block;margin-top:10px;background:#AD3119;color:#fff;border-radius:8px;padding:8px 18px;font-weight:700;font-size:13px;text-decoration:none;}
.oha-dl-mob-cta:hover{text-decoration:none;opacity:.9;}

/* Hero */
.oha-dl-hero{background:linear-gradient(135deg,#1a0808 0%,#0d0d0d 55%,#1a0d00 100%);border:1px solid rgba(173,49,25,.5);border-radius:16px;padding:36px 24px 28px;margin-bottom:14px;}
.oha-dl-hero-inner{display:flex;gap:32px;align-items:center;}
.oha-dl-hero-text{flex:1;min-width:0;}
.oha-dl-hero-eyebrow{font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#AD3119;margin-bottom:10px;}
.oha-dl-app-name{font-size:clamp(20px,5vw,32px);font-weight:800;color:#fff;margin:0 0 8px;line-height:1.2;}
.oha-dl-tagline{font-size:clamp(13px,3vw,17px);color:#e07060;font-weight:600;margin:0 0 12px;}
.oha-dl-desc{font-size:14px;color:#999;line-height:1.6;margin:0 0 18px;}
.oha-dl-hero-btns{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;}
.oha-dl-badges{display:flex;gap:10px;flex-wrap:wrap;font-size:12px;color:#666;margin:0;}
.oha-dl-badges span{white-space:nowrap;}
.oha-dl-ver{font-size:11px;color:#444;margin-top:10px;}
.oha-dl-installed-badge{background:#0d2010;border:1px solid #46b450;color:#7dc97d;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;margin-bottom:18px;display:block;text-align:center;}

/* Hero visual — screenshot or phone mockup */
.oha-dl-hero-visual{flex-shrink:0;}
.oha-dl-hero-screenshot{position:relative;border-radius:14px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.6);}
.oha-dl-hero-screenshot.oha-lb-trigger{cursor:pointer;}
.oha-dl-hero-img{display:block;width:180px;max-width:180px;height:auto;}
.oha-dl-expand-hint{position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,.75));color:#fff;font-size:12px;font-weight:600;text-align:center;padding:24px 8px 10px;opacity:0;transition:opacity .2s;}
.oha-dl-hero-screenshot:hover .oha-dl-expand-hint{opacity:1;}
.oha-dl-phone{width:90px;height:162px;background:#1a1a1a;border:2px solid #333;border-radius:18px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.6),0 0 0 1px rgba(255,255,255,.06);}
.oha-dl-phone-screen{flex:1;display:flex;flex-direction:column;align-items:center;padding:8px 4px 4px;background:#111;}
.oha-dl-phone-status{font-size:9px;color:#888;align-self:flex-start;padding-left:4px;margin-bottom:8px;}
.oha-dl-phone-app-icon{font-size:32px;margin-bottom:4px;}
.oha-dl-phone-app-label{font-size:8px;color:#aaa;text-align:center;margin-bottom:auto;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.oha-dl-phone-nav{display:flex;justify-content:space-around;width:100%;background:#0d0d0d;border-top:1px solid #222;padding:4px 0;font-size:12px;}
@media(max-width:520px){.oha-dl-hero-visual{display:none;}}

/* Buttons */
.oha-dl-btn{display:inline-flex;align-items:center;gap:7px;padding:13px 22px;border-radius:10px;font-size:15px;font-weight:700;text-decoration:none;cursor:pointer;border:none;transition:transform .15s,opacity .15s;line-height:1;}
.oha-dl-btn:hover{transform:translateY(-2px);opacity:.92;text-decoration:none;}
.oha-dl-btn-primary{background:#AD3119;color:#fff;}
.oha-dl-btn-secondary{background:rgba(173,49,25,.15);color:#e07060;border:1px solid rgba(173,49,25,.4);}
.oha-dl-btn-ghost{background:rgba(255,255,255,.07);color:#bbb;border:1px solid rgba(255,255,255,.15);}
.oha-dl-btn-block{width:100%;justify-content:center;font-size:17px;padding:16px;}

/* Sections */
.oha-dl-section{background:#111;border:1px solid #222;border-radius:16px;padding:28px 24px;margin-bottom:14px;}
.oha-dl-section-title{font-size:clamp(16px,4vw,21px);font-weight:700;color:#fff;margin:0 0 6px;}
.oha-dl-section-sub{color:#777;font-size:14px;margin:0 0 22px;}
.oha-dl-sub-title{font-size:15px;font-weight:600;color:#ccc;margin:26px 0 12px;}

/* Desktop only section */
.oha-dl-desktop-only{display:block;}
@media(max-width:767px){.oha-dl-desktop-only{display:none;}}

/* QR bridge */
.oha-dl-bridge{display:flex;align-items:flex-start;gap:24px;flex-wrap:wrap;}
.oha-dl-bridge-qr{text-align:center;flex-shrink:0;}
.oha-dl-qr-img{border-radius:10px;border:2px solid #333;display:block;}
.oha-dl-qr-label{font-size:13px;color:#888;margin-top:8px;line-height:1.4;}
.oha-dl-bridge-or{font-size:18px;font-weight:700;color:#444;align-self:center;flex-shrink:0;padding:0 4px;}
.oha-dl-bridge-share{flex:1;min-width:220px;}
.oha-dl-bridge-share strong{font-size:15px;color:#ddd;display:block;margin-bottom:4px;}

/* Video */
.oha-dl-video-wrap{position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:12px;background:#000;}
.oha-dl-video-frame{position:absolute;top:0;left:0;width:100%;height:100%;border:none;border-radius:12px;}

/* Feature cards */
.oha-dl-features-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:500px){.oha-dl-features-grid{grid-template-columns:1fr;}}
.oha-dl-feature-card{display:block;background:#1a1a1a;border:1px solid #2c2c2c;border-radius:12px;padding:20px 16px;color:#f0f0f0;overflow:hidden;transition:border-color .2s,transform .15s,box-shadow .15s;}
.oha-dl-feature-card.oha-lb-trigger{cursor:pointer;}
.oha-dl-feature-card.oha-lb-trigger:hover{border-color:#AD3119;transform:translateY(-2px);box-shadow:0 4px 20px rgba(173,49,25,.15);}
.oha-dl-feature-icon{font-size:30px;margin-bottom:10px;display:block;}
.oha-dl-feature-card h3{font-size:15px;font-weight:700;color:#fff;margin:0 0 7px;}
.oha-dl-feature-card p{font-size:13px;color:#999;line-height:1.55;margin:0 0 12px;}
.oha-dl-feature-tags{display:flex;gap:5px;flex-wrap:wrap;}
.oha-dl-feature-tags span{background:rgba(173,49,25,.18);border:1px solid rgba(173,49,25,.35);color:#e07060;border-radius:20px;padding:2px 9px;font-size:11px;}

/* Screenshot thumbnail on cards */
.oha-dl-card-thumb{width:calc(100% + 32px);margin:-20px -16px 14px;height:160px;background-size:cover;background-position:top center;position:relative;overflow:hidden;}
.oha-dl-card-thumb-hint{position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,.75));color:#fff;font-size:12px;text-align:center;padding:18px 8px 8px;opacity:0;transition:opacity .2s;}
.oha-lb-trigger:hover .oha-dl-card-thumb-hint{opacity:1;}

/* Lightbox */
.oha-lb{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;}
.oha-lb-overlay{position:absolute;inset:0;background:rgba(0,0,0,.93);}
.oha-lb-box{position:relative;z-index:1;display:flex;align-items:center;gap:10px;max-width:96vw;max-height:92vh;}
.oha-lb-img{max-width:min(600px,82vw);max-height:85vh;object-fit:contain;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,.6);display:block;}
.oha-lb-close{position:fixed;top:14px;right:18px;z-index:2;width:40px;height:40px;background:rgba(255,255,255,.15);border:none;border-radius:50%;color:#fff;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s;}
.oha-lb-close:hover{background:rgba(255,255,255,.3);}
.oha-lb-arrow{background:rgba(255,255,255,.12);border:none;color:#fff;font-size:40px;width:46px;height:46px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .2s;line-height:1;}
.oha-lb-arrow:hover:not(:disabled){background:rgba(173,49,25,.6);}
.oha-lb-arrow:disabled{opacity:.2;cursor:default;}
.oha-lb-caption{position:fixed;bottom:18px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.75);color:#eee;font-size:13px;padding:5px 18px;border-radius:20px;white-space:nowrap;z-index:2;}
@media(max-width:500px){.oha-lb-img{max-width:96vw;max-height:76vh;}.oha-lb-box{gap:4px;}.oha-lb-arrow{width:36px;height:36px;font-size:28px;}}

/* OS / notification tabs */
.oha-dl-os-tabs,.oha-dl-notif-tabs{display:flex;gap:4px;margin-bottom:18px;background:#0d0d0d;border-radius:10px;padding:4px;}
.oha-dl-os-tab,.oha-dl-notif-tab{flex:1;padding:10px 6px;background:transparent;border:none;border-radius:8px;color:#777;font-size:13px;font-weight:600;cursor:pointer;transition:background .2s,color .2s;white-space:nowrap;}
.oha-dl-os-tab.active,.oha-dl-notif-tab.active{background:#AD3119;color:#fff;}
.oha-dl-os-tab:hover:not(.active),.oha-dl-notif-tab:hover:not(.active){background:#1e1e1e;color:#ccc;}
@media(max-width:380px){.oha-dl-os-tab,.oha-dl-notif-tab{font-size:11px;padding:8px 3px;}}

/* Steps */
.oha-dl-steps{list-style:none;padding:0;margin:0 0 16px;}
.oha-dl-steps li{display:flex;gap:14px;align-items:flex-start;padding:13px 0;border-bottom:1px solid #1c1c1c;}
.oha-dl-steps li:last-child{border-bottom:none;}
.oha-dl-step-n{flex-shrink:0;width:28px;height:28px;border-radius:50%;background:#AD3119;color:#fff;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center;margin-top:1px;}
.oha-dl-step-body{font-size:14px;color:#bbb;line-height:1.5;}
.oha-dl-step-body strong{color:#fff;display:block;margin-bottom:2px;font-size:14px;}
.oha-dl-step-body span{font-size:13px;color:#888;}
.oha-dl-steps-sm li{padding:8px 0;}
.oha-dl-steps-sm .oha-dl-step-body{font-size:13px;}

/* Native install CTA */
.oha-dl-native-cta{background:#0a1a0a;border:1px solid rgba(70,180,80,.4);border-radius:12px;padding:16px 20px;margin-bottom:20px;text-align:center;}
.oha-dl-native-cta p{color:#7dc97d;font-weight:600;font-size:15px;margin:0 0 12px;}

/* Tip / warning */
.oha-dl-tip{background:#181400;border:1px solid #3a2e00;color:#c8a84a;border-radius:8px;padding:10px 14px;font-size:13px;line-height:1.5;margin-top:8px;}
.oha-dl-warning{background:#1a0d00;border:1px solid #5a3200;color:#e0a060;border-radius:8px;padding:10px 14px;font-size:13px;line-height:1.5;margin-bottom:18px;}

/* Notification reasons */
.oha-dl-notif-reasons{display:flex;flex-direction:column;gap:12px;margin-bottom:26px;}
.oha-dl-notif-reason{display:flex;gap:16px;align-items:flex-start;background:#1a1a1a;border-radius:10px;padding:14px 16px;}
.oha-dl-notif-ico{font-size:22px;flex-shrink:0;margin-top:1px;}
.oha-dl-notif-reason strong{display:block;color:#fff;font-size:14px;margin-bottom:4px;}
.oha-dl-notif-reason p{color:#888;font-size:13px;margin:0;line-height:1.5;}

/* Already installed footer */
.oha-dl-already{text-align:center;padding:18px 8px 6px;color:#555;font-size:14px;}
.oha-dl-already-link{color:#AD3119;text-decoration:none;font-weight:600;}
.oha-dl-already-link:hover{text-decoration:underline;}
.oha-dl-ver-foot{font-size:11px;color:#444;margin-left:8px;}
</style>

<script>
(function () {
    'use strict';

    // ── Platform detection ───────────────────────────────────────────────────
    function detectOS() {
        var ua = navigator.userAgent;
        if (/iPad|iPhone|iPod/.test(ua)) { return 'ios'; }
        if (/[Aa]ndroid/.test(ua))       { return 'android'; }
        return 'desktop';
    }

    var os   = detectOS();
    var page = document.querySelector('.oha-dl-page');
    if (page) { page.classList.add('oha-dl-os-' + os); }

    // ── Mobile banner ────────────────────────────────────────────────────────
    if (os !== 'desktop') {
        var mob = document.getElementById('oha-dl-mobile-banner');
        var lbl = document.getElementById('oha-dl-mob-label');
        if (mob) { mob.style.display = 'block'; }
        if (lbl) {
            lbl.textContent = (os === 'ios')
                ? '\uD83D\uDCF1 You\u2019re on iPhone / iPad'
                : '\uD83D\uDCF1 You\u2019re on Android';
        }
        // Force-hide desktop section on mobile OS regardless of screen width
        document.querySelectorAll('.oha-dl-desktop-only').forEach(function (s) {
            s.style.display = 'none';
        });
    }

    // ── Share button (navigator.share) ───────────────────────────────────────
    var shareBtn = document.getElementById('oha-dl-share-btn');
    if (shareBtn && navigator.share) {
        shareBtn.style.display = 'inline-flex';
        shareBtn.addEventListener('click', function () {
            navigator.share({ title: document.title, url: window.location.href }).catch(function () {});
        });
    }

    // ── OS install tab activation ────────────────────────────────────────────
    function activateOsTab(os) {
        document.querySelectorAll('.oha-dl-os-tab').forEach(function (b) {
            var active = b.dataset.os === os;
            b.classList.toggle('active', active);
            b.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('.oha-dl-os-panel').forEach(function (p) { p.style.display = 'none'; });
        var panel = document.getElementById('oha-dl-os-' + os);
        if (panel) { panel.style.display = 'block'; }
    }
    document.querySelectorAll('.oha-dl-os-tab').forEach(function (btn) {
        btn.addEventListener('click', function () { activateOsTab(btn.dataset.os); });
    });

    // ── Notification tab activation ──────────────────────────────────────────
    function activateNotifTab(notif) {
        document.querySelectorAll('.oha-dl-notif-tab').forEach(function (b) {
            b.classList.toggle('active', b.dataset.notif === notif);
        });
        document.querySelectorAll('.oha-dl-notif-panel').forEach(function (p) { p.style.display = 'none'; });
        var panel = document.getElementById('oha-dl-notif-' + notif);
        if (panel) { panel.style.display = 'block'; }
    }
    document.querySelectorAll('.oha-dl-notif-tab').forEach(function (btn) {
        btn.addEventListener('click', function () { activateNotifTab(btn.dataset.notif); });
    });

    // Auto-select tabs
    activateOsTab(os);
    activateNotifTab(os === 'ios' ? 'ios' : 'android');

    // ── Lightbox ─────────────────────────────────────────────────────────────
    var ohaLbData = <?php echo $lb_json; ?>;

    if (ohaLbData.length) {
        var lbKeyMap = {};
        ohaLbData.forEach(function (s, i) { lbKeyMap[s.key] = i; });

        var lbEl   = document.getElementById('oha-lb');
        var lbImg  = document.getElementById('oha-lb-img');
        var lbCap  = document.getElementById('oha-lb-caption');
        var lbPrev = document.getElementById('oha-lb-prev');
        var lbNext = document.getElementById('oha-lb-next');
        var lbCur  = 0;

        // Hide arrows if only one screenshot
        if (ohaLbData.length <= 1) {
            if (lbPrev) { lbPrev.style.display = 'none'; }
            if (lbNext) { lbNext.style.display = 'none'; }
        }

        function lbOpen(idx) {
            if (!lbEl) { return; }
            lbCur = Math.max(0, Math.min(idx, ohaLbData.length - 1));
            if (lbImg) { lbImg.src = ohaLbData[lbCur].url; lbImg.alt = ohaLbData[lbCur].title; }
            if (lbCap) { lbCap.textContent = ohaLbData[lbCur].title + ' (' + (lbCur + 1) + '/' + ohaLbData.length + ')'; }
            if (lbPrev) { lbPrev.disabled = (lbCur === 0); }
            if (lbNext) { lbNext.disabled = (lbCur === ohaLbData.length - 1); }
            lbEl.style.display = 'flex';
            lbEl.focus();
        }

        function lbClose() {
            if (lbEl) { lbEl.style.display = 'none'; }
            if (lbImg) { lbImg.src = ''; }
        }

        document.querySelectorAll('.oha-lb-trigger').forEach(function (el) {
            el.addEventListener('click', function () {
                var key = el.getAttribute('data-lb-key');
                var idx = (key && lbKeyMap[key] !== undefined) ? lbKeyMap[key] : 0;
                lbOpen(idx);
            });
        });

        if (lbPrev) { lbPrev.addEventListener('click', function () { lbOpen(lbCur - 1); }); }
        if (lbNext) { lbNext.addEventListener('click', function () { lbOpen(lbCur + 1); }); }

        var lbCloseBtn = document.getElementById('oha-lb-close');
        var lbOverlay  = document.getElementById('oha-lb-overlay');
        if (lbCloseBtn) { lbCloseBtn.addEventListener('click', lbClose); }
        if (lbOverlay)  { lbOverlay.addEventListener('click', lbClose); }

        document.addEventListener('keydown', function (e) {
            if (!lbEl || lbEl.style.display === 'none') { return; }
            if (e.key === 'Escape')     { lbClose(); }
            if (e.key === 'ArrowLeft')  { lbOpen(lbCur - 1); }
            if (e.key === 'ArrowRight') { lbOpen(lbCur + 1); }
        });
    }

    // ── Native beforeinstallprompt (Chrome Android / Chrome Desktop) ─────────
    var deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        var heroBtn   = document.getElementById('oha-dl-hero-install-btn');
        var nativeSec = document.getElementById('oha-dl-android-native');
        var nativeBtn = document.getElementById('oha-dl-android-native-btn');
        if (heroBtn)   { heroBtn.style.display   = 'inline-flex'; }
        if (nativeSec) { nativeSec.style.display = 'block'; }
        function triggerInstall() {
            if (!deferredPrompt) { return; }
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function (result) {
                if (result.outcome === 'accepted') {
                    if (heroBtn)   { heroBtn.textContent   = '\u2705 App Installed!'; }
                    if (nativeBtn) { nativeBtn.textContent = '\u2705 Installed! Open it from your home screen.'; }
                }
                deferredPrompt = null;
            });
        }
        if (heroBtn)   { heroBtn.addEventListener('click',   triggerInstall); }
        if (nativeBtn) { nativeBtn.addEventListener('click', triggerInstall); }
    });

    // ── Already installed banner ─────────────────────────────────────────────
    var standalone = window.matchMedia('(display-mode: standalone)').matches ||
                     (typeof window.navigator.standalone === 'boolean' && window.navigator.standalone);
    if (standalone) {
        var hero = document.querySelector('.oha-dl-hero');
        if (hero) {
            var badge = document.createElement('div');
            badge.className = 'oha-dl-installed-badge';
            badge.textContent = '\u2705 App is already installed on this device!';
            hero.insertBefore(badge, hero.firstChild);
        }
    }
}());
</script>
    <?php
    return ob_get_clean();
}

add_shortcode( 'oha_pwa_download', 'oha_pwa_download_shortcode' );
