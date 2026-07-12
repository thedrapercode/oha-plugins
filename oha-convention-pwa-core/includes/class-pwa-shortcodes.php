<?php
/**
 * OHA PWA Shortcodes Class
 *
 * Convention PWA shortcodes: schedule, my-schedule, and convention nav tabs.
 * Actor Passport shortcodes ([oha_actor_passport] etc.) are provided by the
 * OHA Passport Hub plugin. This class integrates Passport Hub into the
 * convention schedule tabs when it is active, and shows a graceful notice
 * when it is not.
 *
 * @package OHA_PWA_Core
 * @version 3.5.0
 */

defined( 'ABSPATH' ) || exit;

class OHA_PWA_Shortcodes {

    private static $instance = null;

    public static function getInstance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_shortcode( 'oha_convention_schedule',    [ $this, 'display_convention_schedule' ] );
        add_shortcode( 'oha_my_schedule',            [ $this, 'display_my_schedule' ] );
        add_shortcode( 'oha_pwa_event_card',         [ $this, 'pwa_event_card' ] );
        add_shortcode( 'oha_happening_now',          [ $this, 'happening_now' ] );
        add_shortcode( 'oha_notification_subscribe', [ $this, 'notification_subscribe' ] );
        add_shortcode( 'oha_faq',                    [ $this, 'faq' ] );
        add_shortcode( 'oha_volunteer_area',         [ $this, 'volunteer_area' ] );
        add_shortcode( 'oha_favorite_vendors',       [ $this, 'favorite_vendors' ] );
        add_shortcode( 'oha_pwa_onboarding',         [ $this, 'pwa_onboarding' ] );
        add_shortcode( 'oha_board_notifications',    [ $this, 'board_notifications' ] );
        add_shortcode( 'oha_local_notification_test', [ $this, 'local_notification_test' ] );
        add_shortcode( 'oha_convention_vendors',      [ $this, 'convention_vendors' ] );
        add_shortcode( 'oha_board_dashboard',         [ $this, 'board_dashboard' ] );
        add_shortcode( 'oha_convention_home',         [ $this, 'convention_home' ] );
        add_shortcode( 'oha_convention_passport',     [ $this, 'convention_passport' ] );
        add_shortcode( 'oha_pwa_settings',            [ $this, 'pwa_settings' ] );
        add_shortcode( 'oha_lost_found',              [ $this, 'lost_found' ] );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helper — is Passport Hub installed and active?
    // ─────────────────────────────────────────────────────────────────────
    private function passport_hub_active() {
        return function_exists( 'oha_passport_hub_active' );
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_convention_schedule]
    // Five-tab view: Schedule | My Classes | Passport | Scanner | Stamps
    // Passport tabs delegate to Passport Hub shortcodes when available.
    // ─────────────────────────────────────────────────────────────────────
    public function display_convention_schedule( $atts ) {
        $atts = shortcode_atts( [
            'view'         => 'full',
            'show_filters' => 'true',
            'show_export'  => 'true',
        ], $atts );

        ob_start(); ?>
        <div id="oha-convention-schedule" class="oha-pwa-container">

            <div class="oha-schedule-header">
                <h2>OHA Convention 2026 Schedule</h2>
                <p>May 9–10, 2026 &bull; Ohio State Reformatory, Mansfield</p>
            </div>

            <div class="oha-pwa-nav">
                <button class="oha-nav-btn active" data-tab="schedule">📅 Schedule</button>
                <button class="oha-nav-btn" data-tab="my-classes">⭐ My Classes</button>
                <?php if ( $this->passport_hub_active() ) : ?>
                <button class="oha-nav-btn" data-tab="passport">🎭 My Passport</button>
                <button class="oha-nav-btn" data-tab="scanner">📱 QR Scanner</button>
                <button class="oha-nav-btn" data-tab="stamps">🏆 My Stamps</button>
                <?php endif; ?>
            </div>

            <!-- Schedule tab -->
            <div id="schedule-tab" class="oha-tab-content active">
                <div id="oha-schedule-container">
                    <div class="oha-loading">
                        <div class="oha-spinner"></div>
                        <p>Loading schedule…</p>
                    </div>
                </div>
            </div>

            <!-- My Classes tab -->
            <div id="my-classes-tab" class="oha-tab-content">
                <div id="oha-my-classes-container"></div>
            </div>

            <?php if ( $this->passport_hub_active() ) : ?>

            <!-- Actor Passport tab — rendered by Passport Hub -->
            <div id="passport-tab" class="oha-tab-content">
                <?php echo do_shortcode( '[oha_actor_passport show_qr="true" show_stats="true"]' ); ?>
            </div>

            <!-- QR Scanner tab — rendered by Passport Hub -->
            <div id="scanner-tab" class="oha-tab-content">
                <?php echo do_shortcode( '[oha_qr_scanner show_instructions="true"]' ); ?>
            </div>

            <!-- My Stamps tab — rendered by Passport Hub -->
            <div id="stamps-tab" class="oha-tab-content">
                <?php echo do_shortcode( '[oha_my_stamps show_stats="true" show_recent="true"]' ); ?>
            </div>

            <?php else : ?>

            <!-- Passport Hub not active — show install notice in admin only -->
            <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <div class="oha-admin-notice" style="
                margin:16px 0;padding:16px;
                background:#fff3cd;border:1px solid #ffc107;border-radius:6px;">
                <strong>🎭 Actor Passport tabs are hidden.</strong><br>
                Install and activate the <strong>OHA Passport Hub</strong> plugin to enable
                badge trading, QR scanning, and stamp collection for convention attendees.
            </div>
            <?php endif; ?>

            <?php endif; ?>

        </div>

        <div class="oha-disclaimer">
            <p><small>📢 <strong>Important:</strong> Actor passport content is user-controlled.
            OHA does not monitor or endorse profile content.</small></p>
        </div>
        <?php
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_my_schedule]
    // ─────────────────────────────────────────────────────────────────────
    public function display_my_schedule( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="oha-login-prompt"><p>Please <a href="' .
                esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your schedule.</p></div>';
        }

        ob_start(); ?>
        <div id="oha-my-schedule" class="oha-pwa-container">
            <h3>My OHC Schedule</h3>
            <div id="my-schedule-content">
                <div class="oha-loading">
                    <div class="oha-spinner"></div>
                    <p>Loading your schedule…</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    // ─────────────────────────────────────────────────────────────────────
    // [oha_pwa_event_card id="" title="" time="" room="" url=""]
    // Renders a favoritable event card for events not in the Schedule Hub
    // (e.g. Costume Ball, Opening Ceremony on a standalone page).
    // pwa-favorites.js auto-discovers .event-item and injects the ★ button.
    // Usage: [oha_pwa_event_card id="costume-ball" title="Costume Ball"
    //         time="Saturday 8:00 PM - 11:00 PM" room="Grand Ballroom"
    //         url="/costume-ball/"]
    // ─────────────────────────────────────────────────────────────────────
    public function pwa_event_card( $atts ) {
        $atts = shortcode_atts( [
            'id'    => '',
            'title' => '',
            'time'  => '',
            'room'  => '',
            'url'   => '',
        ], $atts );

        if ( empty( $atts['id'] ) || empty( $atts['title'] ) ) {
            return '';
        }

        $id    = esc_attr( $atts['id'] );
        $title = esc_html( $atts['title'] );
        $time  = esc_attr( $atts['time'] );
        $room  = esc_attr( $atts['room'] );
        $url   = esc_url( $atts['url'] ?: get_permalink() );

        ob_start(); ?>
        <div class="oha-pwa-standalone-event event-item oha-promoted-event"
             data-event-id="<?php echo $id; ?>"
             data-event-title="<?php echo esc_attr( $atts['title'] ); ?>"
             data-event-time="<?php echo $time; ?>"
             data-event-room="<?php echo $room; ?>"
             data-event-url="<?php echo $url; ?>"
             data-event-promoted="1">
            <div class="oha-promoted-badge">&#9733; Featured Event</div>
            <h3 class="oha-standalone-title"><?php echo $title; ?></h3>
            <?php if ( $atts['time'] ) : ?>
            <p class="oha-standalone-time">&#128336; <?php echo esc_html( $atts['time'] ); ?></p>
            <?php endif; ?>
            <?php if ( $atts['room'] ) : ?>
            <p class="oha-standalone-room">&#128205; <?php echo esc_html( $atts['room'] ); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    // ─────────────────────────────────────────────────────────────────────
    // [oha_happening_now]
    // Renders the "What's Happening Now" container; pwa-phase3a-features.js populates it.
    // ─────────────────────────────────────────────────────────────────────
    public function happening_now( $atts ) {
        return '<div id="oha-happening-now"></div>';
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_lost_found]
    // Lost and Found info card — items and map URL configured in PWA Settings.
    // ─────────────────────────────────────────────────────────────────────
    public function lost_found( $atts ) {
        $items_raw = get_option( 'oha_pwa_lost_found_items', '' );
        $map_url   = get_option( 'oha_pwa_lost_found_map_url', '' );

        $lines = array_filter( array_map( 'trim', explode( "\n", $items_raw ) ) );

        ob_start(); ?>
<div class="oha-pwa-container oha-lost-found-wrap">
<style>
.oha-lost-found-card {
    background: #1a1a1a;
    border: 1px solid #2a2a2a;
    border-left: 4px solid #AD3119;
    border-radius: 12px;
    padding: 20px 18px;
    margin: 0 0 16px;
    color: #eee;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.oha-lost-found-heading {
    margin: 0 0 6px;
    font-size: 1.15em;
    font-weight: 700;
    color: #fff;
}
.oha-lost-found-sub {
    margin: 0 0 14px;
    font-size: 13px;
    color: rgba(255,255,255,.5);
    line-height: 1.5;
}
.oha-lost-found-list {
    list-style: none;
    margin: 0 0 14px;
    padding: 0;
}
.oha-lost-found-list li {
    padding: 8px 0;
    border-bottom: 1px solid #2a2a2a;
    font-size: 14px;
    color: #ddd;
}
.oha-lost-found-list li:last-child { border-bottom: none; }
.oha-lost-found-empty {
    font-size: 14px;
    color: rgba(255,255,255,.4);
    margin: 0 0 14px;
    font-style: italic;
}
.oha-lost-found-map-link {
    display: inline-block;
    padding: 9px 18px;
    background: #AD3119;
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    -webkit-tap-highlight-color: transparent;
}
.oha-lost-found-map-link:hover { background: #8b2714; color: #fff; }
</style>
<div class="oha-lost-found-card">
    <h2 class="oha-lost-found-heading">Lost and Found</h2>
    <p class="oha-lost-found-sub">Items turned in to volunteers are listed below. Visit the Lost and Found table near the main entrance if you are missing something.</p>
    <?php if ( ! empty( $lines ) ) : ?>
    <ul class="oha-lost-found-list">
        <?php foreach ( $lines as $line ) : ?>
        <li><?php echo esc_html( $line ); ?></li>
        <?php endforeach; ?>
    </ul>
    <?php else : ?>
    <p class="oha-lost-found-empty">No items have been turned in yet.</p>
    <?php endif; ?>
    <?php if ( $map_url ) : ?>
    <a class="oha-lost-found-map-link" href="<?php echo esc_url( $map_url ); ?>">View Location on Map</a>
    <?php endif; ?>
</div>
</div>
        <?php
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_faq]
    // FAQ chatbot — questions managed in PWA Settings → FAQ Chatbot tab.
    // ─────────────────────────────────────────────────────────────────────
    public function faq( $atts ) {
        $enabled  = get_option( 'oha_pwa_faq_enabled', true );
        if ( ! $enabled ) {
            return '<p>FAQ is currently disabled.</p>';
        }
        $faq_data = get_option( 'oha_pwa_faq_questions', '' );
        wp_localize_script( 'oha-pwa-phase3a-features', 'ohaFAQData', $faq_data );
        return '<div id="oha-faq-container"></div>';
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_volunteer_area]
    // Password-protected volunteer info — configured in PWA Settings → Volunteer Area tab.
    // ─────────────────────────────────────────────────────────────────────
    public function volunteer_area( $atts ) {
        $password = get_option( 'oha_pwa_volunteer_password', '' );
        if ( empty( $password ) ) {
            return '<p>Volunteer area not configured. Please set a password in PWA Settings.</p>';
        }
        $auth_url = esc_js( rest_url( 'oha/v1/volunteer/auth' ) );
        ob_start(); ?>
<div id="oha-volunteer-area">
<style>
#oha-volunteer-area { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #eee; }
#oha-vol-locked { max-width: 400px; margin: 40px auto; background: #1a1a2e; border-radius: 14px; padding: 32px 24px; text-align: center; box-shadow: 0 4px 24px rgba(0,0,0,.5); }
#oha-vol-locked h3 { margin: 0 0 8px; font-size: 1.4em; }
#oha-vol-locked p { opacity: .75; margin-bottom: 20px; }
#oha-vol-pw { width: 100%; box-sizing: border-box; padding: 11px 13px; font-size: 16px; border-radius: 8px; border: 2px solid #333; background: #111; color: #fff; margin-bottom: 10px; }
#oha-vol-pw:focus { outline: none; border-color: #4CAF50; }
#oha-vol-submit { width: 100%; padding: 11px; background: #4CAF50; border: none; color: #fff; font-size: 15px; font-weight: 600; border-radius: 8px; cursor: pointer; }
#oha-vol-submit:hover { background: #388E3C; }
#oha-vol-error { color: #f44336; font-size: 14px; margin-top: 8px; display: none; }
/* Portal sections */
.oha-vol-portal { padding-bottom: 60px; }
.oha-vol-section { background: #111; border: 1px solid #2a2a2a; border-radius: 12px; padding: 18px 16px; margin-bottom: 14px; }
.oha-vol-section-emergency { background: #1a0a0a; border-color: #8B0000; }
.oha-vol-section-title { margin: 0 0 14px; font-size: 1.05em; font-weight: 700; color: #fff; letter-spacing: .02em; }
.oha-vol-title-emergency { color: #ff6b6b; }
/* Event times */
.oha-vol-time-item { display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px 10px; padding: 8px 0; border-bottom: 1px solid #222; }
.oha-vol-time-item:last-child { border-bottom: none; }
.oha-vol-time-label { font-weight: 600; font-size: 14px; flex: 1 1 140px; }
.oha-vol-time-value { background: #2a2a2a; color: #fff; font-size: 13px; padding: 2px 8px; border-radius: 6px; }
.oha-vol-time-notes { font-size: 12px; color: #888; flex: 1 1 100%; margin-top: 2px; }
/* Emergency */
.oha-vol-emerg-item { display: flex; flex-wrap: wrap; gap: 4px 12px; padding: 8px 0; border-bottom: 1px solid #2a1010; }
.oha-vol-emerg-item:last-child { border-bottom: none; }
.oha-vol-emerg-label { font-weight: 700; font-size: 13px; color: #ff6b6b; min-width: 140px; }
.oha-vol-emerg-detail { font-size: 14px; color: #eee; }
/* Radio */
.oha-vol-radio-row { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid #222; }
.oha-vol-radio-row:last-child { border-bottom: none; }
.oha-vol-radio-team { font-size: 14px; font-weight: 600; }
.oha-vol-radio-ch { background: #AD3119; color: #fff; font-size: 13px; font-weight: 700; padding: 3px 12px; border-radius: 20px; }
/* Venue text */
.oha-vol-venue-text { font-size: 14px; line-height: 1.7; color: #ccc; }
/* Contacts */
.oha-vol-contacts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; }
.oha-vol-contact-card { background: #1a1a2e; border: 1px solid #333; border-radius: 10px; padding: 14px; }
.oha-vol-contact-name { font-size: 15px; font-weight: 700; margin: 0 0 4px; }
.oha-vol-contact-role { display: inline-block; background: #2a4a2a; color: #6fcf6f; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; margin-bottom: 8px; }
.oha-vol-contact-call { display: block; text-align: center; background: #4CAF50; color: #fff; text-decoration: none; padding: 7px; border-radius: 7px; font-weight: 600; font-size: 14px; margin-top: 8px; }
/* Extra / logout */
.oha-vol-extra { font-size: 14px; line-height: 1.7; }
.oha-volunteer-logout { display: block; width: 100%; margin-top: 16px; padding: 10px; background: transparent; border: 1px solid #444; color: #aaa; border-radius: 8px; font-size: 14px; cursor: pointer; }
.oha-volunteer-logout:hover { border-color: #f44336; color: #f44336; }
/* Refresh button */
#oha-vol-refresh { background: #1a1a2e; border: 1px solid #444; color: #aaa; padding: 7px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; margin-bottom: 14px; display: block; }
#oha-vol-refresh:hover { border-color: #4CAF50; color: #fff; }
/* Volunteer schedule section */
.oha-vol-sched-sub { font-size: 12px; color: #888; margin: -6px 0 12px; }
.oha-vol-sched-date-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
.oha-vol-sched-date-btn { background: #222; border: 1px solid #444; color: #aaa; padding: 7px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; cursor: pointer; }
.oha-vol-sched-date-btn.active { background: #AD3119; border-color: #AD3119; color: #fff; }
.oha-vol-sched-slot { margin-bottom: 16px; }
.oha-vol-sched-time { font-size: 13px; font-weight: 700; color: #fff; padding: 4px 0 6px; border-bottom: 1px solid #2a2a2a; margin-bottom: 8px; }
.oha-vol-sched-areas { display: flex; flex-direction: column; gap: 6px; }
.oha-vol-sched-area-block { border-left: 3px solid #555; padding: 7px 10px; background: #1a1a1a; border-radius: 0 8px 8px 0; }
.oha-vol-sched-area-name { font-size: 13px; font-weight: 600; color: #ddd; }
.oha-vol-sched-area-icon { font-size: 14px; line-height: 1; }
.oha-vol-sched-vols { font-size: 12px; color: #888; margin-top: 4px; line-height: 1.5; }
/* Handbook collapsible */
.oha-vol-handbook-outer { padding: 0 !important; overflow: hidden; }
.oha-vol-handbook-toggle { width: 100%; background: none; border: none; color: inherit; text-align: left; cursor: pointer; display: flex; align-items: center; justify-content: space-between; padding: 18px 16px; gap: 12px; }
.oha-vol-handbook-toggle .oha-vol-section-title { margin: 0; }
.oha-vol-hb-arrow { font-size: 22px; color: #AD3119; font-weight: 700; flex-shrink: 0; line-height: 1; }
.oha-vol-handbook-body { padding: 0 16px 16px; border-top: 1px solid #2a2a2a; }
.oha-vol-handbook-body .handbook-download { display: none !important; }
.oha-vol-handbook-body .oha-vh-handbook { color: #ddd; }
.oha-vol-handbook-body h1, .oha-vol-handbook-body h2, .oha-vol-handbook-body h3 { color: #e07060 !important; border-color: #e07060 !important; }
.oha-vol-handbook-body .handbook-header { border-color: #e07060 !important; }
.oha-vol-handbook-body p, .oha-vol-handbook-body li { color: #ccc !important; }
.oha-vol-handbook-body h4 { color: #ddd !important; background: transparent !important; }
.oha-vol-handbook-body section { background: transparent !important; border-color: #333 !important; border-radius: 8px; }
.oha-vol-handbook-body .handbook-content { padding: 0; }
</style>
    <div id="oha-vol-locked">
        <h3>Volunteer Access</h3>
        <p>Enter the volunteer password to access shift info and venue details.</p>
        <input type="password" id="oha-vol-pw" placeholder="Enter password" autocomplete="current-password">
        <button id="oha-vol-submit">Access Volunteer Portal</button>
        <p id="oha-vol-error">Incorrect password.</p>
    </div>
    <div id="oha-vol-content" style="display:none;">
        <button id="oha-vol-refresh">Refresh Content</button>
    </div>
</div>
<script>
(function(){
    var AUTH_URL    = '<?php echo $auth_url; ?>';
    var STORAGE_KEY = 'oha_volunteer_access';
    var CONTENT_KEY = 'oha_volunteer_content';

    function show(html) {
        document.getElementById('oha-vol-locked').style.display = 'none';
        var contentEl = document.getElementById('oha-vol-content');
        contentEl.style.display = '';
        var refreshBtn = document.getElementById('oha-vol-refresh');
        // Insert new HTML after the refresh button
        var existing = contentEl.querySelector('.oha-vol-portal');
        if (existing) { existing.remove(); }
        var div = document.createElement('div');
        div.innerHTML = html;
        contentEl.appendChild(div);
    }

    function fetchContent() {
        var pw = null;
        // Re-auth is needed — we only have a granted flag, not a token.
        // Show a spinner then re-fetch.
        var contentEl = document.getElementById('oha-vol-content');
        contentEl.innerHTML = '<button id="oha-vol-refresh">Refresh Content</button><p style="color:#aaa;font-size:14px;padding:12px 0;">Loading...</p>';
        attachRefresh();
        // We need the password — require re-login on explicit refresh
        localStorage.removeItem(STORAGE_KEY);
        localStorage.removeItem(CONTENT_KEY);
        contentEl.style.display = 'none';
        document.getElementById('oha-vol-locked').style.display = '';
    }

    function attachRefresh() {
        var btn = document.getElementById('oha-vol-refresh');
        if (btn) { btn.addEventListener('click', fetchContent); }
    }

    if (localStorage.getItem(STORAGE_KEY) === 'granted') {
        show(localStorage.getItem(CONTENT_KEY) || '');
        attachRefresh();
    }

    document.getElementById('oha-vol-submit').addEventListener('click', function(){
        var pw = document.getElementById('oha-vol-pw').value.trim();
        if (!pw) return;
        var btn = document.getElementById('oha-vol-submit');
        btn.textContent = 'Checking...';
        btn.disabled = true;
        fetch(AUTH_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({password: pw})
        })
        .then(function(r){ return r.ok ? r.json() : r.json().then(function(e){ throw e; }); })
        .then(function(data){
            localStorage.setItem(STORAGE_KEY, 'granted');
            localStorage.setItem(CONTENT_KEY, data.content || '');
            show(data.content || '');
            attachRefresh();
        })
        .catch(function(e){
            var errEl = document.getElementById('oha-vol-error');
            errEl.textContent = e.message || 'Incorrect password.';
            errEl.style.display = 'block';
            btn.textContent = 'Access Volunteer Portal';
            btn.disabled = false;
        });
    });
    document.getElementById('oha-vol-pw').addEventListener('keydown', function(e){
        if (e.key === 'Enter') { document.getElementById('oha-vol-submit').click(); }
    });

    document.getElementById('oha-vol-content').addEventListener('click', function(e){
        if (e.target.classList.contains('oha-volunteer-logout')) {
            localStorage.removeItem(STORAGE_KEY);
            localStorage.removeItem(CONTENT_KEY);
            document.getElementById('oha-vol-locked').style.display = '';
            document.getElementById('oha-vol-content').style.display = 'none';
            document.getElementById('oha-vol-submit').textContent = 'Access Volunteer Portal';
            document.getElementById('oha-vol-submit').disabled = false;
        }
    });
})();
function ohaVolSchedSwitch(btn, date) {
    var wrap = btn.closest('.oha-vol-sched-wrap');
    if (!wrap) return;
    wrap.querySelectorAll('.oha-vol-sched-date-btn').forEach(function(b) {
        b.classList.toggle('active', b.dataset.date === date);
    });
    wrap.querySelectorAll('.oha-vol-sched-panel').forEach(function(p) {
        p.style.display = p.dataset.date === date ? '' : 'none';
    });
}
function ohaVolHandbookToggle(btn) {
    var body = btn.nextElementSibling;
    var arrow = btn.querySelector('.oha-vol-hb-arrow');
    var open = body.style.display !== 'none';
    body.style.display = open ? 'none' : '';
    if (arrow) arrow.textContent = open ? '+' : '-';
}
</script>
        <?php
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_favorite_vendors]
    // "My Must-See Vendors" — localStorage-backed list with thumbnail,
    // map deep-link (tap card → map tab at vendor booth), remove button.
    // ─────────────────────────────────────────────────────────────────────
    public function favorite_vendors( $atts ) {
        $_conv_base = rtrim( get_option( 'oha_convention_base_url', '/convention' ), '/' );
        $convention_map_url = get_option( 'oha_pwa_convention_map_url', $_conv_base . '/map/' );

        // Build a server-side lookup of all paid vendors so the renderer
        // always shows fresh name/logo/website regardless of what was saved
        // in localStorage (handles stale data from previous plugin versions).
        $vendor_lookup = [];
        $paid_vendor_ids = get_posts( [
            'post_type'      => 'vendor_application',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [ [
                'key'     => '_oha_status',
                'value'   => [ 'Paid', 'paid', 'PAID' ],
                'compare' => 'IN',
            ] ],
        ] );
        foreach ( $paid_vendor_ids as $vid ) {
            $cname   = get_post_meta( $vid, 'company_name', true );
            if ( ! $cname ) $cname = get_the_title( $vid );
            $website = get_post_meta( $vid, 'website', true );
            $logo    = '';
            $logo_id = get_post_meta( $vid, '_company_logo_id', true );
            if ( $logo_id ) {
                $img = wp_get_attachment_image_src( (int) $logo_id, [ 120, 120 ] );
                if ( $img ) $logo = $img[0];
            }
            if ( ! $logo ) $logo = get_post_meta( $vid, '_company_logo_url', true );
            $vendor_lookup[ (string) $vid ] = [
                'name'    => $cname,
                'logo'    => $logo,
                'website' => $website,
            ];
        }

        ob_start(); ?>
        <script>window.ohaVendorLookup = <?php echo wp_json_encode( $vendor_lookup ); ?>;</script>
        <style>
        .oha-msv-wrap { padding: 8px 0; }
        .oha-msv-heading { font-size: 1.3em; font-weight: 700; color: #fff; margin-bottom: 6px; }
        .oha-msv-subhead { font-size: 12px; color: rgba(255,255,255,.45); margin-bottom: 18px; }
        .oha-msv-empty {
            text-align: center;
            padding: 36px 16px;
            color: rgba(255,255,255,.5);
            font-size: 14px;
            line-height: 1.6;
            background: rgba(255,255,255,.03);
            border-radius: 10px;
        }
        .oha-msv-grid { display: flex; flex-direction: column; gap: 10px; }
        .oha-msv-card {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 10px;
            padding: 10px 38px 10px 10px;
            position: relative;
            -webkit-tap-highlight-color: transparent;
            transition: background .18s;
        }
        .oha-msv-thumb {
            width: 58px; height: 58px;
            border-radius: 8px;
            background: rgba(255,255,255,.06);
            flex-shrink: 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        .oha-msv-thumb img {
            width: 100%; height: 100%;
            object-fit: contain;
            padding: 4px;
            box-sizing: border-box;
        }
        .oha-msv-info { flex: 1; min-width: 0; }
        .oha-msv-name {
            display: block;
            font-size: 14px; font-weight: 700; color: #fff;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .oha-msv-website {
            display: block;
            font-size: 11px; color: rgba(255,255,255,.45);
            margin-top: 2px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .oha-msv-booth {
            display: inline-block;
            margin-top: 5px;
            font-size: 11px; font-weight: 600; color: #AD3119;
            background: rgba(173,49,25,.12);
            border-radius: 4px;
            padding: 2px 7px;
        }
        .oha-msv-locate-btn {
            display: inline-block;
            margin-top: 6px;
            padding: 4px 10px;
            font-size: 11px; font-weight: 600;
            color: #fff;
            background: #AD3119;
            border-radius: 5px;
            text-decoration: none;
            -webkit-tap-highlight-color: transparent;
        }
        .oha-msv-locate-btn:hover { background: #c73a1f; color: #fff; }
        .oha-msv-remove {
            position: absolute;
            top: 8px; right: 8px;
            width: 24px; height: 24px;
            border-radius: 50%;
            background: rgba(255,255,255,.1);
            border: none; color: rgba(255,255,255,.45);
            font-size: 11px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            -webkit-tap-highlight-color: transparent;
            line-height: 1; padding: 0;
            transition: background .15s;
        }
        .oha-msv-remove:active { background: rgba(220,50,50,.6); color: #fff; }
        </style>
        <div class="oha-msv-wrap">
            <h2 class="oha-msv-heading">My Must-See Vendors</h2>
            <p class="oha-msv-subhead">Browse vendors and tap the skull icon to save them here. Tap any saved card to find them on the map.</p>
            <div id="oha-favorite-vendors-list"></div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            var MAP_BASE = <?php echo wp_json_encode( rtrim( $convention_map_url, '/' ) . '/' ); ?>;
            var lookup   = window.ohaVendorLookup || {};

            function esc(str) {
                return $('<div>').text(str || '').html();
            }

            function renderFavoriteVendors() {
                var favorites = JSON.parse(localStorage.getItem('oha_favorite_vendors') || '{}');
                var vendors   = Object.values(favorites);
                var container = $('#oha-favorite-vendors-list');

                if (vendors.length === 0) {
                    container.html('<div class="oha-msv-empty">No must-see vendors yet.<br>Browse vendors and tap the skull icon to save them here.</div>');
                    return;
                }

                var html = '<div class="oha-msv-grid">';
                vendors.forEach(function(v) {
                    var sid      = String(v.id);
                    var fresh    = lookup[sid] || {};
                    // Always prefer fresh server data over potentially stale localStorage
                    var name     = fresh.name    || v.name    || '';
                    var logo     = fresh.logo    || v.logo    || '';
                    var website  = fresh.website || v.website || '';
                    var hasBooth = v.booth && v.booth.length > 0;
                    var mapUrl   = hasBooth ? MAP_BASE + '?oha_vendor=' + encodeURIComponent(sid) : '';
                    var siteClean = website ? website.replace(/^https?:\/\//, '').replace(/\/$/, '') : '';

                    // Skip if this vendor ID is no longer in the paid vendor list
                    // (i.e. they lost Paid status) — still show with whatever we have
                    html += '<div class="oha-msv-card">';

                    // Thumbnail
                    html += '<div class="oha-msv-thumb">';
                    if (logo) {
                        html += '<img src="' + esc(logo) + '" alt="' + esc(name) + '">';
                    } else {
                        html += '&#127962;';
                    }
                    html += '</div>';

                    // Info
                    html += '<div class="oha-msv-info">';
                    html += '<span class="oha-msv-name">' + esc(name || 'Vendor') + '</span>';
                    if (siteClean) {
                        html += '<span class="oha-msv-website">&#127760; ' + esc(siteClean) + '</span>';
                    }
                    if (hasBooth) {
                        html += '<span class="oha-msv-booth">&#128205; Booth ' + esc(v.booth) + '</span>';
                        html += '<a class="oha-msv-locate-btn" href="' + esc(mapUrl) + '">Locate Booth on Map</a>';
                    }
                    html += '</div>';

                    // Remove
                    html += '<button class="oha-msv-remove" data-vendor-id="' + esc(sid) + '" aria-label="Remove">&#x2715;</button>';
                    html += '</div>';
                });
                html += '</div>';
                container.html(html);

                container.on('click', '.oha-msv-remove', function(e) {
                    e.stopPropagation();
                    var id = String($(this).data('vendor-id'));
                    var f  = JSON.parse(localStorage.getItem('oha_favorite_vendors') || '{}');
                    var removedName = f[id] ? (f[id].name || id) : id;
                    delete f[id];
                    localStorage.setItem('oha_favorite_vendors', JSON.stringify(f));
                    if (window.OHAAnalytics) { OHAAnalytics.trackVendorFavorite('remove', id, removedName); }
                    renderFavoriteVendors();
                });

                container.on('click', '.oha-msv-locate-btn', function() {
                    var card = $(this).closest('.oha-msv-card');
                    var nameEl = card.find('.oha-msv-name').text();
                    var boothEl = card.find('.oha-msv-booth').text().replace(/Booth\s*/i, '').trim();
                    if (window.OHAAnalytics) { OHAAnalytics.trackVendorMapClick('', nameEl, boothEl); }
                });
            }

            renderFavoriteVendors();
        });
        </script>
        <?php
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_pwa_onboarding]
    // Step-by-step PWA install + notification setup wizard.
    // Atts: show_install="true|false"  show_notifications="true|false"
    // Completed steps auto-collapse to a tappable summary row.
    // When all steps are done the whole widget collapses to a "Setup Complete"
    // banner with a "See all steps" toggle. State persists in localStorage.
    // ─────────────────────────────────────────────────────────────────────
    public function pwa_onboarding( $atts ) {
        $atts = shortcode_atts( [
            'show_install'        => 'true',
            'show_notifications'  => 'true',
        ], $atts );
        $show_install        = filter_var( $atts['show_install'],       FILTER_VALIDATE_BOOLEAN );
        $show_notifications  = filter_var( $atts['show_notifications'], FILTER_VALIDATE_BOOLEAN );
        $total_steps = ( $show_install ? 1 : 0 ) + ( $show_notifications ? 1 : 0 );

        ob_start(); ?>
        <div class="oha-pwa-onboarding" id="oha-pwa-onboarding">
        <style>
        .oha-onboarding-step{background:rgba(255,255,255,0.05);border-radius:12px;padding:24px;margin-bottom:20px;border-left:4px solid #666;transition:border-color .3s,background .3s,padding .2s}
        .oha-onboarding-step.active{border-left-color:#AD3119;background:rgba(173,49,25,.1)}
        .oha-onboarding-step.completed{border-left-color:#4CAF50;background:rgba(76,175,80,.1)}
        .oha-onboarding-step.collapsed{padding:12px 16px;cursor:pointer}
        .oha-onboarding-step h3{margin-top:0;display:flex;align-items:center;gap:12px}
        .oha-step-number{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;background:#666;color:#fff;border-radius:50%;font-weight:600}
        .oha-onboarding-step.active .oha-step-number{background:#AD3119}
        .oha-onboarding-step.completed .oha-step-number{background:#4CAF50}
        .oha-onboarding-step.completed .oha-step-number::before{content:'\2713'}
        .oha-onboarding-actions{margin-top:16px;display:flex;gap:12px;flex-wrap:wrap}
        .oha-onboarding-button{padding:12px 24px;background:#AD3119;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;transition:all .2s}
        .oha-onboarding-button:hover{background:#8B0000;transform:translateY(-2px)}
        .oha-onboarding-button:disabled{background:#666;cursor:not-allowed;transform:none}
        .oha-skip-button{background:transparent;border:2px solid #666;color:#fff}
        .oha-skip-button:hover{border-color:#AD3119;color:#AD3119;background:transparent}
        .oha-platform-instructions{background:rgba(0,0,0,.2);padding:16px;border-radius:8px;margin-top:12px}
        .oha-progress-bar{height:8px;background:rgba(255,255,255,.1);border-radius:4px;margin-bottom:24px;overflow:hidden}
        .oha-progress-fill{height:100%;background:linear-gradient(90deg,#AD3119 0%,#4CAF50 100%);transition:width .3s}
        /* Collapsed step summary row */
        .oha-step-summary{display:none;align-items:center;gap:12px}
        .oha-onboarding-step.collapsed .oha-step-summary{display:flex}
        .oha-onboarding-step.collapsed .oha-step-body{display:none}
        .oha-summary-check{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;background:#4CAF50;color:#fff;border-radius:50%;font-weight:700;font-size:13px;flex-shrink:0}
        .oha-summary-label{flex:1;font-weight:600;font-size:.95rem}
        .oha-summary-expand{font-size:.8rem;color:#888;white-space:nowrap}
        /* All-done banner */
        #oha-onboarding-done{background:rgba(76,175,80,.12);border:1px solid rgba(76,175,80,.5);border-radius:12px;padding:14px 16px;margin-bottom:8px}
        .oha-done-row{display:flex;align-items:center;gap:12px}
        .oha-done-icon{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;background:#4CAF50;color:#fff;border-radius:50%;font-weight:700;font-size:16px;flex-shrink:0}
        .oha-done-label{flex:1;font-weight:600;font-size:1rem}
        .oha-see-steps-btn{background:none;border:1px solid rgba(76,175,80,.6);color:#81C784;border-radius:6px;padding:6px 12px;font-size:.85rem;cursor:pointer;white-space:nowrap}
        .oha-see-steps-btn:hover{background:rgba(76,175,80,.15)}
        </style>

        <!-- All-done banner (shown when every step is complete) -->
        <div id="oha-onboarding-done" style="display:none;">
            <div class="oha-done-row">
                <span class="oha-done-icon">&#10003;</span>
                <span class="oha-done-label">Setup Complete</span>
                <button class="oha-see-steps-btn" id="oha-see-steps-btn" type="button">See all steps</button>
            </div>
        </div>

        <!-- Steps container -->
        <div id="oha-onboarding-steps">
        <h2>&#127914; Setup Your OHA Convention App</h2>
        <p>Get the full experience with our Progressive Web App! Follow these simple steps:</p>
        <div class="oha-progress-bar"><div class="oha-progress-fill" id="oha-onboarding-progress" style="width:0%;"></div></div>

        <?php if ( $show_install ) : ?>
        <div class="oha-onboarding-step" id="step-install" data-step="1">
            <div class="oha-step-summary" id="step-install-summary">
                <span class="oha-summary-check">&#10003;</span>
                <span class="oha-summary-label">App Installed</span>
                <span class="oha-summary-expand">tap to review &#8250;</span>
            </div>
            <div class="oha-step-body" id="step-install-body">
                <h3><span class="oha-step-number">1</span> Install the Convention App</h3>
                <p>Install our app to access it quickly from your home screen and use it offline at the convention!</p>
                <div id="install-status">
                    <p id="install-checking">&#9203; Checking installation status...</p>
                    <p id="install-available" style="display:none;">&#128242; Ready to install!</p>
                    <p id="install-already" style="display:none;">&#9989; App already installed!</p>
                    <p id="install-platform-help" style="display:none;"></p>
                </div>
                <div class="oha-onboarding-actions">
                    <button id="install-pwa-btn" class="oha-onboarding-button" style="display:none;">&#128242; Install App</button>
                    <button id="skip-install-btn" class="oha-skip-button oha-onboarding-button" style="display:none;">Skip for Now</button>
                </div>
                <div id="platform-instructions" class="oha-platform-instructions" style="display:none;"></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $show_notifications ) : ?>
        <div class="oha-onboarding-step" id="step-notifications" data-step="2">
            <div class="oha-step-summary" id="step-notifications-summary">
                <span class="oha-summary-check">&#10003;</span>
                <span class="oha-summary-label">Notifications Enabled</span>
                <span class="oha-summary-expand">tap to review &#8250;</span>
            </div>
            <div class="oha-step-body" id="step-notifications-body">
                <h3><span class="oha-step-number"><?php echo $show_install ? '2' : '1'; ?></span> Enable Push Notifications</h3>
                <p>Get instant updates about schedule changes, announcements, and important alerts!</p>
                <div id="notification-status">
                    <p id="notification-checking">&#9203; Checking notification status...</p>
                    <p id="notification-available" style="display:none;">&#128276; Ready to enable!</p>
                    <p id="notification-already" style="display:none;">&#9989; Notifications already enabled!</p>
                    <p id="notification-error" style="display:none;">&#10060; <span id="notification-error-text"></span></p>
                </div>
                <div class="oha-onboarding-actions">
                    <button id="enable-notifications-btn" class="oha-onboarding-button" style="display:none;">&#128276; Enable Notifications</button>
                    <button id="skip-notifications-btn" class="oha-skip-button oha-onboarding-button" style="display:none;">Skip for Now</button>
                </div>
                <div id="ios-notification-help" class="oha-platform-instructions" style="display:none;">
                    <strong>&#128242; iOS Users:</strong>
                    <ol style="margin:8px 0;padding-left:20px;">
                        <li>Tap "Allow" when the notification popup appears</li>
                        <li>If you don't see a popup, make sure the app is installed first</li>
                        <li>Open the app from your home screen (not Safari)</li>
                    </ol>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="oha-onboarding-step" id="step-complete" data-step="3" style="display:none;">
            <h3><span class="oha-step-number">&#10003;</span> You're All Set!</h3>
            <p><strong>&#127881; Great! You're ready for the convention!</strong></p>
            <ul style="margin:12px 0;padding-left:20px;">
                <li id="complete-install" style="display:none;">&#9989; App installed on your device</li>
                <li id="complete-notifications" style="display:none;">&#9989; Notifications enabled</li>
                <li>&#9989; Ready to use offline at the venue</li>
                <li>&#9989; Quick access from home screen</li>
            </ul>
            <div class="oha-onboarding-actions">
                <a href="/convention/schedule/" class="oha-onboarding-button">&#128197; View Schedule</a>
                <a href="/convention/my-ohc/" class="oha-onboarding-button" style="background:#4CAF50;">&#11088; My OHC</a>
            </div>
        </div>
        </div><!-- /#oha-onboarding-steps -->
        </div><!-- /.oha-pwa-onboarding -->

        <script>
        (function() {
            var LS_INSTALL = 'oha_ob_install_done';
            var LS_NOTIF   = 'oha_ob_notif_done';
            var installCompleted = false, notificationsCompleted = false;
            var totalSteps = <?php echo (int) $total_steps; ?>;
            var stepsVisible = true;

            function collapseStep(stepId) {
                var step = document.getElementById(stepId);
                if (!step) return;
                step.classList.add('completed', 'collapsed');
                step.classList.remove('active');
            }

            function expandStep(stepId) {
                var step = document.getElementById(stepId);
                if (!step) return;
                step.classList.remove('collapsed');
            }

            function checkAllDone() {
                var done = (installCompleted ? 1 : 0) + (notificationsCompleted ? 1 : 0);
                if (done < totalSteps) return;
                // Reveal the "You're All Set!" section inside the steps container
                var sc = document.getElementById('step-complete');
                if (sc) {
                    sc.style.display = 'block';
                    if (installCompleted) { var ci = document.getElementById('complete-install'); if (ci) ci.style.display = 'list-item'; }
                    if (notificationsCompleted) { var cn = document.getElementById('complete-notifications'); if (cn) cn.style.display = 'list-item'; }
                }
                // Persist completion
                if (installCompleted)      localStorage.setItem(LS_INSTALL, '1');
                if (notificationsCompleted) localStorage.setItem(LS_NOTIF, '1');
                // Show done banner, hide steps container
                var doneEl  = document.getElementById('oha-onboarding-done');
                var stepsEl = document.getElementById('oha-onboarding-steps');
                if (doneEl)  doneEl.style.display  = 'block';
                if (stepsEl) stepsEl.style.display = 'none';
                stepsVisible = false;
            }

            function updateProgress() {
                var done = (installCompleted ? 1 : 0) + (notificationsCompleted ? 1 : 0);
                var prog = document.getElementById('oha-onboarding-progress');
                if (prog) prog.style.width = ((done / totalSteps) * 100) + '%';
                if (installCompleted)      collapseStep('step-install');
                if (notificationsCompleted) collapseStep('step-notifications');
                checkAllDone();
            }

            // Tap collapsed summary row to expand and review
            document.getElementById('step-install-summary')?.addEventListener('click', function() {
                expandStep('step-install');
            });
            document.getElementById('step-notifications-summary')?.addEventListener('click', function() {
                expandStep('step-notifications');
            });

            // "See all steps" / "Hide steps" toggle in the done banner
            document.getElementById('oha-see-steps-btn')?.addEventListener('click', function() {
                var stepsEl = document.getElementById('oha-onboarding-steps');
                if (!stepsVisible) {
                    if (stepsEl) stepsEl.style.display = 'block';
                    stepsVisible = true;
                    this.textContent = 'Hide steps';
                } else {
                    if (stepsEl) stepsEl.style.display = 'none';
                    stepsVisible = false;
                    this.textContent = 'See all steps';
                }
            });

            // ── Perfecty helpers (self-contained for this onboarding widget) ──
            function urlB64ToUint8Array(b64) {
                var padding = '='.repeat((4 - b64.length % 4) % 4);
                var base64  = (b64 + padding).replace(/-/g, '+').replace(/_/g, '/');
                var rawData = window.atob(base64);
                var arr     = new Uint8Array(rawData.length);
                for (var i = 0; i < rawData.length; ++i) { arr[i] = rawData.charCodeAt(i); }
                return arr;
            }

            function subscribeViaPerfecty(onSuccess, onError) {
                var popts    = window.PerfectyPushOptions || {};
                var vapidKey = popts.vapidPublicKey || '';
                var scope    = popts.serviceWorkerScope || '/';
                var base     = (popts.serverUrl || '/wp-json/perfecty-push').replace(/\/$/, '');
                var nonce    = popts.token || '';
                if (!vapidKey) { onError('Notification service not configured.'); return; }

                // Ensure proxy SW is registered first
                navigator.serviceWorker.getRegistration(scope).then(function (reg) {
                    if (!reg) {
                        return navigator.serviceWorker.register('/perfecty-push-sw.js', { scope: scope, updateViaCache: 'none' });
                    }
                }).then(function () {
                    // Poll until active (up to 10 s)
                    return new Promise(function (resolve, reject) {
                        var attempts = 0;
                        var poll = function () {
                            attempts++;
                            navigator.serviceWorker.getRegistration(scope).then(function (reg) {
                                if (reg && reg.active) { resolve(reg); }
                                else if (attempts >= 20) { reject(new Error('Service worker not ready. Please reload and try again.')); }
                                else { setTimeout(poll, 500); }
                            }).catch(reject);
                        };
                        poll();
                    });
                }).then(function (reg) {
                    return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlB64ToUint8Array(vapidKey) });
                }).then(function (sub) {
                    var subJson = sub.toJSON();
                    return fetch(base + '/v1/push/users', {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                        body:    JSON.stringify({ user: { endpoint: subJson.endpoint, keys: subJson.keys }, first_time: true }),
                    }).then(function (r) { return r.json(); }).then(function (data) {
                        if (data && data.uuid) { localStorage.setItem('oha_perfecty_uuid', data.uuid); }
                        onSuccess();
                    });
                }).catch(function (err) {
                    onError(err && err.message ? err.message : 'Subscribe failed. Please try again.');
                });
            }

            function showNotifEnableBtn() {
                document.getElementById('notification-available').style.display = 'block';
                document.getElementById('enable-notifications-btn').style.display = 'inline-block';
                document.getElementById('skip-notifications-btn').style.display = 'inline-block';
                if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                    document.getElementById('ios-notification-help').style.display = 'block';
                }
            }

            function checkNotificationStatus() {
                document.getElementById('step-notifications')?.classList.add('active');
                var checkEl = document.getElementById('notification-checking');
                if (checkEl) { checkEl.style.display = 'none'; }

                if (typeof Notification === 'undefined' || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                    document.getElementById('notification-error').style.display = 'block';
                    document.getElementById('notification-error-text').textContent = 'Push notifications are not supported on this browser.';
                    return;
                }
                if (Notification.permission === 'denied') {
                    document.getElementById('notification-error').style.display = 'block';
                    document.getElementById('notification-error-text').textContent =
                        'Notifications blocked in Chrome. Tap the \uD83D\uDD12 lock icon in Chrome\'s address bar \u2192 Site settings \u2192 Notifications \u2192 Allow, then retry.';
                    document.getElementById('skip-notifications-btn').style.display = 'inline-block';
                    return;
                }
                // Check for existing Perfecty subscription
                var scope = (window.PerfectyPushOptions || {}).serviceWorkerScope || '/';
                navigator.serviceWorker.getRegistration(scope).then(function (reg) {
                    if (reg && reg.pushManager) {
                        reg.pushManager.getSubscription().then(function (sub) {
                            if (sub) {
                                document.getElementById('notification-already').style.display = 'block';
                                notificationsCompleted = true; updateProgress();
                            } else {
                                showNotifEnableBtn();
                            }
                        });
                    } else {
                        showNotifEnableBtn();
                    }
                }).catch(function () { showNotifEnableBtn(); });
            }

            function checkInstallStatus() {
                var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
                if (isStandalone) {
                    document.getElementById('install-checking').style.display = 'none';
                    document.getElementById('install-already').style.display = 'block';
                    installCompleted = true; updateProgress(); checkNotificationStatus();
                } else {
                    window.addEventListener('beforeinstallprompt', function(e) {
                        e.preventDefault(); window.deferredInstallPrompt = e;
                        document.getElementById('install-checking').style.display = 'none';
                        document.getElementById('install-available').style.display = 'block';
                        document.getElementById('install-pwa-btn').style.display = 'inline-block';
                        document.getElementById('skip-install-btn').style.display = 'inline-block';
                        document.getElementById('step-install')?.classList.add('active');
                    });
                    setTimeout(function() {
                        if (!window.deferredInstallPrompt && !window.matchMedia('(display-mode: standalone)').matches) {
                            var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
                            var instrDiv = document.getElementById('platform-instructions');
                            instrDiv.innerHTML = isIOS
                                ? '<strong>&#128242; iOS:</strong><ol style="margin:8px 0;padding-left:20px;"><li>Tap the Share button</li><li>Scroll and tap "Add to Home Screen"</li><li>Tap "Add"</li></ol>'
                                : '<strong>&#127760; Browser:</strong><p>Look for an install icon in the address bar or check browser menu for "Install".</p>';
                            instrDiv.style.display = 'block';
                            document.getElementById('install-checking').style.display = 'none';
                            document.getElementById('install-platform-help').style.display = 'block';
                            document.getElementById('install-platform-help').textContent = isIOS ? '&#128242; See instructions below.' : '&#128187; Install from browser menu.';
                            document.getElementById('skip-install-btn').style.display = 'inline-block';
                            document.getElementById('step-install')?.classList.add('active');
                        }
                    }, 2000);
                }
            }

            document.getElementById('install-pwa-btn')?.addEventListener('click', function() {
                if (window.deferredInstallPrompt) {
                    window.deferredInstallPrompt.prompt();
                    window.deferredInstallPrompt.userChoice.then(function(r) {
                        if (r.outcome === 'accepted') {
                            installCompleted = true;
                            document.getElementById('install-available').style.display = 'none';
                            document.getElementById('install-already').style.display = 'block';
                            document.getElementById('install-pwa-btn').style.display = 'none';
                            document.getElementById('skip-install-btn').style.display = 'none';
                            updateProgress(); checkNotificationStatus();
                        }
                    });
                }
            });
            document.getElementById('skip-install-btn')?.addEventListener('click', function() { installCompleted = true; updateProgress(); checkNotificationStatus(); });
            document.getElementById('enable-notifications-btn')?.addEventListener('click', function() {
                var btn = document.getElementById('enable-notifications-btn');
                btn.disabled    = true;
                btn.textContent = 'Working\u2026';
                Notification.requestPermission().then(function (perm) {
                    if (perm !== 'granted') {
                        btn.disabled    = false;
                        btn.textContent = '\uD83D\uDD14 Enable Notifications';
                        document.getElementById('notification-available').style.display = 'none';
                        document.getElementById('notification-error').style.display = 'block';
                        document.getElementById('notification-error-text').textContent =
                            'Permission denied. In Chrome: tap the \uD83D\uDD12 lock icon \u2192 Site settings \u2192 Notifications \u2192 Allow, then tap Retry.';
                        document.getElementById('skip-notifications-btn').style.display = 'inline-block';
                        return;
                    }
                    subscribeViaPerfecty(
                        function () {   // success
                            document.getElementById('notification-available').style.display = 'none';
                            document.getElementById('enable-notifications-btn').style.display = 'none';
                            document.getElementById('skip-notifications-btn').style.display = 'none';
                            document.getElementById('notification-already').style.display = 'block';
                            notificationsCompleted = true; updateProgress();
                        },
                        function (errMsg) {   // error
                            btn.disabled    = false;
                            btn.textContent = '\uD83D\uDD14 Enable Notifications';
                            document.getElementById('notification-error').style.display = 'block';
                            document.getElementById('notification-error-text').textContent = errMsg;
                        }
                    );
                });
            });
            document.getElementById('skip-notifications-btn')?.addEventListener('click', function() { notificationsCompleted = true; updateProgress(); });

            // ── Init: restore completed state from localStorage ──
            var lsInstall = <?php echo $show_install ? 'true' : 'false'; ?> && localStorage.getItem(LS_INSTALL) === '1';
            var lsNotif   = <?php echo $show_notifications ? 'true' : 'false'; ?> && localStorage.getItem(LS_NOTIF) === '1';
            if (lsInstall) installCompleted = true;
            if (lsNotif)   notificationsCompleted = true;
            var doneAtStart = (installCompleted ? 1 : 0) + (notificationsCompleted ? 1 : 0);

            if (doneAtStart === totalSteps && totalSteps > 0) {
                // All steps already done — show done banner immediately, collapse all
                var prog = document.getElementById('oha-onboarding-progress');
                if (prog) prog.style.width = '100%';
                if (installCompleted)      collapseStep('step-install');
                if (notificationsCompleted) collapseStep('step-notifications');
                var sc = document.getElementById('step-complete');
                if (sc) {
                    sc.style.display = 'block';
                    if (installCompleted) { var ci = document.getElementById('complete-install'); if (ci) ci.style.display = 'list-item'; }
                    if (notificationsCompleted) { var cn = document.getElementById('complete-notifications'); if (cn) cn.style.display = 'list-item'; }
                }
                var doneEl  = document.getElementById('oha-onboarding-done');
                var stepsEl = document.getElementById('oha-onboarding-steps');
                if (doneEl)  doneEl.style.display  = 'block';
                if (stepsEl) stepsEl.style.display = 'none';
                stepsVisible = false;
            } else if (doneAtStart > 0) {
                // Some steps done — collapse those, check the remaining
                var prog2 = document.getElementById('oha-onboarding-progress');
                if (prog2) prog2.style.width = ((doneAtStart / totalSteps) * 100) + '%';
                if (installCompleted)      collapseStep('step-install');
                if (notificationsCompleted) collapseStep('step-notifications');
                <?php if ( $show_install ) : ?>
                if (!installCompleted) { checkInstallStatus(); }
                else { <?php if ( $show_notifications ) : ?>checkNotificationStatus();<?php endif; ?> }
                <?php elseif ( $show_notifications ) : ?>
                if (!notificationsCompleted) { checkNotificationStatus(); }
                <?php endif; ?>
            } else {
                // Nothing done yet — normal first-run flow
                <?php if ( $show_install ) : ?>
                checkInstallStatus();
                <?php else : ?>
                checkNotificationStatus();
                <?php endif; ?>
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_convention_home]
    // Welcome / home page content for the /convention/ page.
    // Outputs the hero card, info grid, and happenings section.
    // Place [oha_pwa_onboarding] above this shortcode on the WP page.
    // Happenings items managed at PWA Settings > General > Home Page Happenings
    // (one item per line: Title|Description).  Falls back to hardcoded defaults.
    // Nav hint (bottom): shows once per browser session, dismissed via sessionStorage.
    // Happenings section: "Don't show" hides it via localStorage (persists across sessions).
    // ─────────────────────────────────────────────────────────────────────
    public function convention_home( $atts ) {
        // Parse happenings from WP option
        $raw   = get_option( 'oha_pwa_happenings', '' );
        $items = [];
        if ( $raw ) {
            foreach ( explode( "\n", $raw ) as $line ) {
                $line = trim( $line );
                if ( ! $line ) continue;
                $parts   = explode( '|', $line, 2 );
                $items[] = [
                    'title' => esc_html( trim( $parts[0] ) ),
                    'desc'  => esc_html( isset( $parts[1] ) ? trim( $parts[1] ) : '' ),
                ];
            }
        }
        if ( empty( $items ) ) {
            $items = [
                [ 'title' => 'Vendor registration is open',       'desc' => 'Want a booth? Registration for Vendor Booths is underway!' ],
                [ 'title' => 'Schedule updates will appear here',  'desc' => 'As seminars and rooms are finalized, this section will stay current.' ],
                [ 'title' => 'Pro-tip for attendees',              'desc' => 'Bookmark vendors and sessions so you\'re not panic-scrolling in the hallway.' ],
            ];
        }

        ob_start(); ?>
        <div class="oha-conv-home" id="oha-conv-home">
        <style>
        .oha-conv-home{--ohc-bg:#0f0f0f;--ohc-card:#1a1a1a;--ohc-text:#f5f5f5;--ohc-muted:#b5b5b5;--ohc-muted2:#9a9a9a;--ohc-accent:#c62828;--ohc-border:#2a2a2a;color:var(--ohc-text);font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        .ohc-hero{background:linear-gradient(180deg,rgba(255,255,255,.06),rgba(255,255,255,.02));border:1px solid var(--ohc-border);border-radius:16px;padding:1.25rem;box-shadow:0 10px 25px rgba(0,0,0,.45);margin-top:.5rem}
        .ohc-badge{display:inline-block;font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:#ffd6d6;background:rgba(198,40,40,.25);border:1px solid rgba(198,40,40,.45);padding:.35rem .6rem;border-radius:999px;margin-bottom:.75rem}
        .ohc-hero h2{margin:.25rem 0 .5rem;font-size:1.5rem;line-height:1.2;color:var(--ohc-text)}
        .ohc-subtitle{margin:0;color:var(--ohc-muted);line-height:1.5;font-size:1rem}
        .ohc-quick-actions{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem}
        .ohc-btn{display:inline-block;padding:.85rem 1.1rem;border-radius:999px;border:1px solid var(--ohc-border);background:rgba(255,255,255,.03);color:var(--ohc-text);text-decoration:none;font-weight:600;font-size:.9rem;cursor:pointer}
        .ohc-btn.ohc-primary{background:var(--ohc-accent);border-color:rgba(255,255,255,.08)}
        .ohc-btn:active{transform:translateY(1px)}
        .ohc-chips{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem}
        .ohc-chip{font-size:.85rem;color:var(--ohc-muted);background:rgba(255,255,255,.03);border:1px solid var(--ohc-border);padding:.45rem .7rem;border-radius:999px}
        .ohc-grid{display:grid;grid-template-columns:1fr;gap:1rem;margin-top:1rem}
        @media(min-width:720px){.ohc-grid{grid-template-columns:1fr 1fr}}
        .ohc-card{background:var(--ohc-card);border:1px solid var(--ohc-border);border-radius:14px;padding:1rem}
        .ohc-card h3{margin:0 0 .5rem;font-size:1.05rem;color:var(--ohc-text)}
        .ohc-card p{margin:0;color:var(--ohc-muted);line-height:1.5;font-size:.95rem}
        .ohc-news{margin-top:1rem}
        .ohc-news-item{display:flex;gap:.75rem;padding:.85rem;border-radius:12px;border:1px solid var(--ohc-border);background:rgba(255,255,255,.02);margin-top:.75rem}
        .ohc-dot{width:10px;height:10px;border-radius:50%;background:var(--ohc-accent);margin-top:.35rem;flex:0 0 auto}
        .ohc-news-title{margin:0;font-weight:700;font-size:.95rem;color:var(--ohc-text)}
        .ohc-news-meta{margin:.2rem 0 0;color:var(--ohc-muted2);font-size:.85rem;line-height:1.35}
        /* Nav hint — fixed bottom, fades out on dismiss */
        #ohc-nav-hint{position:fixed;left:0;right:0;bottom:0;padding:.9rem 1.25rem 1.1rem;background:linear-gradient(180deg,transparent,rgba(0,0,0,.75) 35%,rgba(0,0,0,.92));z-index:200;pointer-events:none}
        .ohc-nav-hint-inner{max-width:760px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;gap:1rem;pointer-events:auto}
        .ohc-nav-hint-text{color:var(--ohc-muted);font-size:.9rem;line-height:1.35}
        .ohc-small-btn{background:rgba(255,255,255,.06);border:1px solid var(--ohc-border);color:var(--ohc-text);border-radius:999px;padding:.6rem .9rem;font-weight:700;cursor:pointer;font-size:.85rem;white-space:nowrap}
        </style>

        <div class="ohc-hero">
            <div class="ohc-badge">Welcome</div>
            <h2>Welcome to the Ohio Haunters Convention App</h2>
            <p class="ohc-subtitle">
                Your pocket guide for the event — schedule, map, vendors, and saved favorites.
                Use the navigation at the bottom to jump around fast.
            </p>
            <div class="ohc-quick-actions">
                <a class="ohc-btn ohc-primary" href="/convention/schedule/">Open Schedule</a>
                <a class="ohc-btn" href="/convention/map/">View Map</a>
                <a class="ohc-btn" href="/convention/vendors/">Browse Vendors</a>
            </div>
            <div class="ohc-chips">
                <div class="ohc-chip">Tip: Save sessions to "My OHC"</div>
                <div class="ohc-chip">Tip: Tap a room name to open the map</div>
                <div class="ohc-chip">Tip: Check Happenings for live updates</div>
            </div>
        </div>

        <div class="ohc-grid">
            <div class="ohc-card">
                <h3>What is this event?</h3>
                <p>A weekend built for haunters, actors, builders, artists, and Halloween people. Training, shopping, networking, and conversations that only make sense here.</p>
            </div>
            <div class="ohc-card">
                <h3>First time here?</h3>
                <p>Start with <strong>Schedule</strong> to plan your day, then hit <strong>Map</strong> when you arrive. <strong>Vendors</strong> is your shopping list. <strong>My OHC</strong> keeps your saved stuff in one place.</p>
            </div>
        </div>

        <div class="ohc-news ohc-card" id="ohc-news" style="margin-top:1rem;">
            <h3>Happenings</h3>
            <p style="color:var(--ohc-muted);font-size:.95rem;">Quick updates and important notes.</p>
            <?php foreach ( $items as $item ) : ?>
            <div class="ohc-news-item">
                <div class="ohc-dot"></div>
                <div>
                    <p class="ohc-news-title"><?php echo $item['title']; ?></p>
                    <?php if ( $item['desc'] ) : ?><p class="ohc-news-meta"><?php echo $item['desc']; ?></p><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="ohc-quick-actions" style="margin-top:1rem;">
                <a class="ohc-btn" href="/convention-registration/">Become a Vendor</a>
                <button class="ohc-btn" id="ohc-dismiss-happenings" type="button">Don't show this again</button>
            </div>
        </div>

        <!-- Bottom nav hint — shown once per session -->
        <div id="ohc-nav-hint">
            <div class="ohc-nav-hint-inner">
                <div class="ohc-nav-hint-text">Use the bottom nav to jump to <strong>Schedule</strong>, <strong>Map</strong>, <strong>Vendors</strong>, and <strong>My Con</strong>.</div>
                <button class="ohc-small-btn" id="ohc-got-it-btn" type="button">Got it</button>
            </div>
        </div>

        <script>
        (function(){
            // Nav hint: show once per browser session
            var NAV_KEY  = 'oha_nav_hint_v1';
            var hint     = document.getElementById('ohc-nav-hint');
            if (sessionStorage.getItem(NAV_KEY) === '1' && hint) {
                hint.style.display = 'none';
            }
            document.getElementById('ohc-got-it-btn')?.addEventListener('click', function() {
                sessionStorage.setItem(NAV_KEY, '1');
                if (hint) hint.style.display = 'none';
            });

            // Happenings section: "Don't show this again" via localStorage
            var HAP_KEY  = 'oha_happenings_hidden_v1';
            var newsEl   = document.getElementById('ohc-news');
            if (localStorage.getItem(HAP_KEY) === '1' && newsEl) {
                newsEl.style.display = 'none';
            }
            document.getElementById('ohc-dismiss-happenings')?.addEventListener('click', function() {
                localStorage.setItem(HAP_KEY, '1');
                if (newsEl) newsEl.style.display = 'none';
            });
        })();
        </script>
        </div><!-- /.oha-conv-home -->
        <?php
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_board_notifications]
    // Frontend notification send form for board members (edit_pages cap).
    // Note: The admin dashboard version at Settings → Push Notifications is
    // preferred. This shortcode is for embedding on a frontend page.
    // ─────────────────────────────────────────────────────────────────────
    public function board_notifications( $atts ) {
        if ( ! current_user_can( 'edit_pages' ) && ! current_user_can( 'manage_options' ) ) {
            return '<p>Access denied. Board members only.</p>';
        }
        if ( ! class_exists( 'Perfecty_Push_Lib_Push_Server' ) ) {
            return '<p style="color:#dc3232;">⚠️ Perfecty Push plugin is not active. Push notifications are unavailable.</p>';
        }

        global $wpdb;
        $subscriber_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}perfecty_push_users"
        );

        ob_start(); ?>
        <div class="oha-board-notifications" style="max-width:600px;">
            <h2>📢 Send Push Notification</h2>
            <p style="color:#fff;">Sends instantly to all <?php echo $subscriber_count; ?> subscribed devices.</p>
            <form id="send-notification-form" style="background:#f5f5f5;padding:20px;border-radius:8px;">
                <div style="margin-bottom:16px;">
                    <label for="notification-title" style="display:block;margin-bottom:8px;font-weight:600;color:#111;">Notification Title *</label>
                    <input type="text" id="notification-title" class="regular-text" placeholder="e.g., Schedule Change" required style="width:100%;padding:8px;box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label for="notification-message" style="display:block;margin-bottom:8px;font-weight:600;color:#111;">Message *</label>
                    <textarea id="notification-message" rows="3" class="large-text" placeholder="e.g., Room A has moved to the second floor" required style="width:100%;padding:8px;box-sizing:border-box;"></textarea>
                </div>
                <div style="margin-bottom:16px;">
                    <label for="notification-image" style="display:block;margin-bottom:8px;font-weight:600;color:#111;">Image URL <span style="font-weight:400;color:#666;">(optional — shown in notification on supported devices)</span></label>
                    <input type="url" id="notification-image" class="regular-text" placeholder="https://example.com/image.jpg"
                        style="width:100%;padding:8px;box-sizing:border-box;">
                    <p style="font-size:12px;color:#777;margin-top:4px;">Paste a direct image URL. Get one from <strong>Media Library → click image → Copy URL</strong>. Recommended: square, at least 192×192.</p>
                </div>
                <div style="margin-bottom:20px;">
                    <label for="notification-url" style="display:block;margin-bottom:8px;font-weight:600;color:#111;">Tap opens URL <span style="font-weight:400;color:#666;">(optional)</span></label>
                    <div style="margin-bottom:8px;display:flex;flex-wrap:wrap;gap:6px;">
                        <?php
                        $_conv_base = rtrim( get_option( 'oha_convention_base_url', '/convention' ), '/' );
                        $convention_pages = [
                            '⭐ My OHC'       => home_url( $_conv_base . '/my-ohc/' ),
                            '📅 Schedule'     => home_url( $_conv_base . '/schedule/' ),
                            '🗓 My Schedule'  => home_url( $_conv_base . '/my-schedule/' ),
                            '🛒 Vendors'      => home_url( $_conv_base . '/vendors/' ),
                            '🗺 Map'          => home_url( $_conv_base . '/map/' ),
                            '🏠 Home'         => home_url( '/convention/' ),
                        ];
                        foreach ( $convention_pages as $chip_label => $chip_url ) : ?>
                        <button type="button" class="button oha-page-chip"
                            data-url="<?php echo esc_attr( $chip_url ); ?>"
                            style="font-size:12px;padding:3px 10px;">
                            <?php echo esc_html( $chip_label ); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="url" id="notification-url" class="regular-text"
                        value="<?php echo esc_attr( home_url( rtrim( get_option( 'oha_convention_base_url', '/convention' ), '/' ) . '/my-ohc/' ) ); ?>"
                        style="width:100%;padding:8px;box-sizing:border-box;">
                    <p style="font-size:12px;color:#777;margin-top:4px;">Tap a page above to pre-fill, or type a custom URL.</p>
                </div>
                <button type="submit" id="send-notif-btn" style="background:#AD3119;border:none;color:#fff;padding:12px 24px;font-size:16px;font-weight:600;border-radius:6px;cursor:pointer;">
                    📤 Send to All Devices
                </button>
                <div id="notification-result" style="margin-top:16px;font-size:15px;"></div>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($) {
            function escHtml(s) {
                return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }
            // Quick-select page chips
            $('.oha-page-chip').on('click', function() {
                $('#notification-url').val($(this).data('url'));
                $('.oha-page-chip').removeClass('button-primary');
                $(this).addClass('button-primary');
            });

            $('#send-notification-form').on('submit', function(e) {
                e.preventDefault();
                var title   = $('#notification-title').val().trim();
                var message = $('#notification-message').val().trim();
                var url     = $('#notification-url').val().trim();
                var image   = $('#notification-image').val().trim();
                if (!title || !message) {
                    $('#notification-result').html('<p style="color:#dc3232;">Please fill in both title and message.</p>');
                    return;
                }
                var btn    = $('#send-notif-btn');
                var result = $('#notification-result');
                btn.prop('disabled', true).text('Sending…');
                result.html('<p style="color:#0073aa;">📤 Sending…</p>');
                $.ajax({
                    url: '<?php echo esc_js( rest_url( 'oha/v1/notifications/send' ) ); ?>',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>');
                    },
                    contentType: 'application/json',
                    data: JSON.stringify({ title: title, message: message, url: url, image: image }),
                    success: function(data) {
                        btn.prop('disabled', false).text('📤 Send to All Devices');
                        if (data.id) {
                            result.html('<p style="color:#46b450;font-weight:600;">✅ Sent to ' + data.recipients + ' device' + (data.recipients !== 1 ? 's' : '') + '!</p>');
                            $('#notification-title, #notification-message, #notification-image, #notification-url').val('');
                        } else {
                            result.html('<p style="color:#dc3232;">❌ ' + escHtml(data.message || JSON.stringify(data)) + '</p>');
                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).text('📤 Send to All Devices');
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Request failed';
                        result.html('<p style="color:#dc3232;">❌ Error: ' + escHtml(msg) + '</p>');
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_notification_subscribe]
    // Shows subscription status widget and enable/disable toggle.
    // Provider: Perfecty Push (self-hosted VAPID — no CDN dependency)
    // ─────────────────────────────────────────────────────────────────────
    public function notification_subscribe( $atts ) {
        $perfecty_opts = get_option( 'perfecty_push', [] );
        if ( empty( $perfecty_opts['vapid_public_key'] ) ) {
            return '';
        }

        ob_start(); ?>
        <div class="oha-notification-subscribe-section">

            <div id="oha-notification-loading" style="background:rgba(255,255,255,0.05);padding:20px;border-radius:12px;text-align:center;">
                <p>⏳ Checking notification status…</p>
            </div>

            <div id="oha-notification-not-subscribed" style="display:none;background:rgba(255,255,255,0.05);padding:20px;border-radius:12px;text-align:center;">
                <h3>🔔 Stay Updated</h3>
                <p>Get instant notifications for schedule changes, announcements, and important updates.</p>
                <button id="oha-subscribe-notifications"
                        style="background:#AD3119;border:none;color:#fff;padding:12px 24px;font-size:16px;font-weight:600;border-radius:8px;cursor:pointer;margin-top:12px;">
                    🔔 Enable Notifications
                </button>
            </div>

            <div id="oha-notification-subscribed" style="display:none;background:rgba(76,175,80,0.1);padding:20px;border-radius:12px;text-align:center;border:2px solid #4CAF50;">
                <h3>✅ Notifications Enabled!</h3>
                <p>You'll receive convention updates on this device.</p>
                <p style="font-size:14px;opacity:0.8;margin-top:12px;">
                    Device ID: <code id="oha-subscription-id-display" style="font-size:12px;"></code>
                </p>
                <button id="oha-unsubscribe-notifications"
                        style="background:#b32d2e;border:none;color:#fff;padding:8px 18px;border-radius:6px;cursor:pointer;margin-top:10px;">
                    🔕 Unsubscribe
                </button>
            </div>

            <div id="oha-notification-error" style="display:none;background:rgba(244,67,54,0.1);padding:20px;border-radius:12px;text-align:center;border:2px solid #f44336;">
                <h3>❌ Notification Setup Issue</h3>
                <p id="oha-notification-error-message"></p>
                <button id="oha-retry-notification"
                        style="background:#666;border:none;color:#fff;padding:8px 18px;border-radius:6px;cursor:pointer;margin-top:10px;">
                    🔄 Retry
                </button>
            </div>
        </div>

        <script>
        (function () {
            // ── Helpers ───────────────────────────────────────────────
            function show(id) {
                ['oha-notification-loading','oha-notification-not-subscribed',
                 'oha-notification-subscribed','oha-notification-error'].forEach(function(i) {
                    var el = document.getElementById(i);
                    if (el) { el.style.display = (i === id) ? '' : 'none'; }
                });
            }

            function setError(msg) {
                show('oha-notification-error');
                var el = document.getElementById('oha-notification-error-message');
                if (el) { el.textContent = msg; }
            }

            function urlBase64ToUint8Array(base64String) {
                var padding = '='.repeat((4 - base64String.length % 4) % 4);
                var base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                var rawData = window.atob(base64);
                var arr     = new Uint8Array(rawData.length);
                for (var i = 0; i < rawData.length; ++i) { arr[i] = rawData.charCodeAt(i); }
                return arr;
            }

            // Perfecty config — injected by Perfecty Push on all pages
            var popts    = window.PerfectyPushOptions || {};
            var serverUrl = (popts.serverUrl || '/wp-json/perfecty-push').replace(/\/$/, '');
            var vapidKey  = popts.vapidPublicKey || '';
            var nonce     = popts.token || '';
            var scope     = popts.serviceWorkerScope || '/';
            var LS_UUID   = 'oha_perfecty_uuid';

            // Ensure the proxy SW is registered on every page — needed when the
            // shortcode appears on non-convention pages (e.g. FAQ) where
            // pwa-register.js returns early and never registers it.
            if (scope && 'serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistration(scope).then(function (reg) {
                    if (!reg) {
                        navigator.serviceWorker.register('/perfecty-push-sw.js', {
                            scope:          scope,
                            updateViaCache: 'none',
                        }).catch(function (err) {
                            console.warn('[OHA] Perfecty proxy SW registration failed:', err.message);
                        });
                    }
                });
            }

            // ── Status check ──────────────────────────────────────────
            function checkStatus() {
                show('oha-notification-loading');
                if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                    setError('Push notifications are not supported on this browser.');
                    return;
                }

                navigator.serviceWorker.getRegistration(scope).then(function (reg) {
                    if (!reg || !reg.pushManager) {
                        show('oha-notification-not-subscribed');
                        return;
                    }
                    return reg.pushManager.getSubscription().then(function (sub) {
                        if (sub) {
                            show('oha-notification-subscribed');
                            var uuid = localStorage.getItem(LS_UUID) || '';
                            var disp = document.getElementById('oha-subscription-id-display');
                            if (disp) { disp.textContent = uuid ? uuid.substring(0, 16) + '…' : '(active)'; }
                        } else {
                            localStorage.removeItem(LS_UUID);
                            show('oha-notification-not-subscribed');
                        }
                    });
                }).catch(function (e) {
                    setError(e.message || 'Could not check notification status.');
                });
            }

            // ── Subscribe ─────────────────────────────────────────────
            var subBtn = document.getElementById('oha-subscribe-notifications');
            if (subBtn) {
                subBtn.addEventListener('click', function () {
                    if (!vapidKey) { setError('Notification service not configured.'); return; }
                    subBtn.disabled    = true;
                    subBtn.textContent = 'Working…';

                    Notification.requestPermission().then(function (permission) {
                        if (permission !== 'granted') {
                            subBtn.disabled    = false;
                            subBtn.textContent = '🔔 Enable Notifications';
                            setError('Permission denied. Please allow notifications in your browser settings.');
                            return;
                        }

                        // Must use Perfecty's SW (by scope), NOT navigator.serviceWorker.ready
                        // which returns the OHA convention SW on convention pages.
                        // Poll up to 10 s for Perfecty's SW to become active.
                        new Promise(function (resolve, reject) {
                            var attempts = 0;
                            var check = function () {
                                attempts++;
                                navigator.serviceWorker.getRegistration(scope).then(function (reg) {
                                    if (reg && reg.active) { resolve(reg); }
                                    else if (attempts >= 20) { reject(new Error('Perfecty SW not ready at: ' + scope)); }
                                    else { setTimeout(check, 500); }
                                }).catch(reject);
                            };
                            check();
                        }).then(function (reg) {
                            return reg.pushManager.subscribe({
                                userVisibleOnly:      true,
                                applicationServerKey: urlBase64ToUint8Array(vapidKey),
                            });
                        }).then(function (sub) {
                            var subJson = sub.toJSON();
                            return fetch(serverUrl + '/v1/push/users', {
                                method:  'POST',
                                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                                body:    JSON.stringify({
                                    user:       { endpoint: subJson.endpoint, keys: subJson.keys },
                                    first_time: true,
                                }),
                            }).then(function (r) {
                                if (!r.ok) { throw new Error('Server error ' + r.status); }
                                return r.json();
                            }).then(function (data) {
                                if (data && data.uuid) {
                                    localStorage.setItem(LS_UUID, data.uuid);
                                }
                                checkStatus();
                            });
                        }).catch(function (err) {
                            subBtn.disabled    = false;
                            subBtn.textContent = '🔔 Enable Notifications';
                            setError(err && err.message ? err.message : 'Subscribe failed. Please try again.');
                        });
                    });
                });
            }

            // ── Unsubscribe ───────────────────────────────────────────
            var unsubBtn = document.getElementById('oha-unsubscribe-notifications');
            if (unsubBtn) {
                unsubBtn.addEventListener('click', function () {
                    unsubBtn.disabled    = true;
                    unsubBtn.textContent = 'Removing…';

                    var uuid = localStorage.getItem(LS_UUID);
                    var step1 = uuid
                        ? fetch(serverUrl + '/v1/push/users/' + encodeURIComponent(uuid) + '/unregister', {
                              method:  'POST',
                              headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                          })
                        : Promise.resolve();

                    step1.then(function () {
                        return navigator.serviceWorker.getRegistration(scope);
                    }).then(function (reg) {
                        if (!reg || !reg.pushManager) { return; }
                        return reg.pushManager.getSubscription().then(function (sub) {
                            if (sub) { return sub.unsubscribe(); }
                        });
                    }).then(function () {
                        localStorage.removeItem(LS_UUID);
                        checkStatus();
                    }).catch(function (err) {
                        unsubBtn.disabled    = false;
                        unsubBtn.textContent = '🔕 Unsubscribe';
                        setError(err && err.message ? err.message : 'Unsubscribe failed. Please try again.');
                    });
                });
            }

            // ── Retry ─────────────────────────────────────────────────
            var retryBtn = document.getElementById('oha-retry-notification');
            if (retryBtn) { retryBtn.addEventListener('click', checkStatus); }

            // ── Initial check ─────────────────────────────────────────
            checkStatus();
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_local_notification_test]
    // Admin-only tool: fires a LOCAL notification via navigator.serviceWorker.ready
    // — the exact same code path as pwa-reminders.js. If this works, 5-minute
    // schedule reminders will work. No server, no Perfecty, no network call.
    // Usage: add [oha_local_notification_test] to any page, log in as admin.
    // ─────────────────────────────────────────────────────────────────────
    public function local_notification_test( $atts ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return ''; // Invisible to non-admins — no error message.
        }

        $icon = plugins_url( 'assets/icons/icon-192.png', dirname( __FILE__ ) );

        ob_start(); ?>
        <div id="oha-notif-test-wrap" style="
            background:#1a1a2e;color:#eee;padding:24px;border-radius:12px;
            max-width:480px;margin:24px auto;font-family:sans-serif;
            border:2px dashed #AD3119;">

            <h3 style="margin:0 0 6px;color:#fff;">🔔 Local Notification Test</h3>
            <p style="margin:0 0 18px;font-size:13px;opacity:.7;">Admin only &mdash; tests the same code path as 5-minute reminders.</p>

            <label style="display:block;font-size:13px;margin-bottom:4px;">Title</label>
            <input id="oha-nt-title" type="text" value="OHA Test"
                style="width:100%;box-sizing:border-box;padding:8px 10px;border-radius:6px;
                       border:1px solid #555;background:#2a2a3e;color:#fff;margin-bottom:12px;">

            <label style="display:block;font-size:13px;margin-bottom:4px;">Message</label>
            <input id="oha-nt-body" type="text" value="This is a test — 5-minute reminders are working!"
                style="width:100%;box-sizing:border-box;padding:8px 10px;border-radius:6px;
                       border:1px solid #555;background:#2a2a3e;color:#fff;margin-bottom:16px;">

            <button id="oha-nt-send"
                style="background:#AD3119;border:none;color:#fff;padding:10px 22px;
                       font-size:15px;font-weight:600;border-radius:8px;cursor:pointer;
                       width:100%;">
                Send Test Notification
            </button>

            <div id="oha-nt-status" style="margin-top:14px;font-size:13px;line-height:1.6;"></div>
        </div>

        <script>
        (function () {
            var btn    = document.getElementById('oha-nt-send');
            var status = document.getElementById('oha-nt-status');
            var ICON   = '<?php echo esc_js( $icon ); ?>';

            function setStatus(html, color) {
                status.style.color = color || '#ccc';
                status.innerHTML   = html;
            }

            btn.addEventListener('click', function () {
                var title = document.getElementById('oha-nt-title').value.trim() || 'OHA Test';
                var body  = document.getElementById('oha-nt-body').value.trim()  || 'Test notification';

                // ── Preflight checks ────────────────────────────────────
                if (!('serviceWorker' in navigator)) {
                    setStatus('&#10060; Service Worker not supported in this browser.', '#f44336');
                    return;
                }
                if (!('Notification' in window)) {
                    setStatus('&#10060; Notification API not supported in this browser.', '#f44336');
                    return;
                }

                var perm = Notification.permission;
                if (perm === 'denied') {
                    setStatus('&#10060; Notifications are <strong>blocked</strong> in browser settings. '
                        + 'Fix in Chrome \u2192 Site Settings, then retry.', '#f44336');
                    return;
                }
                if (perm !== 'granted') {
                    setStatus('&#9203; Permission is <strong>' + perm + '</strong>. '
                        + 'Subscribe via the notification bell first, then retry.', '#ff9800');
                    return;
                }

                // ── Fire via serviceWorker.ready (same as pwa-reminders.js) ──
                btn.disabled     = true;
                btn.textContent  = 'Sending\u2026';
                setStatus('&#9203; Waiting for service worker\u2026', '#ccc');

                navigator.serviceWorker.ready.then(function (reg) {
                    return reg.showNotification(title, {
                        body:               body,
                        icon:               ICON,
                        badge:              ICON,
                        tag:                'oha-test-' + Date.now(),
                        requireInteraction: false,
                        vibrate:            [200, 100, 200],
                        data:               { url: window.location.href, type: 'test' },
                        actions: [
                            { action: 'view',    title: 'View' },
                            { action: 'dismiss', title: 'Dismiss' }
                        ]
                    });
                }).then(function () {
                    setStatus('&#9989; Notification sent! Check your notification tray.<br>'
                        + '<small style="opacity:.7">SW scope: ' + (navigator.serviceWorker.controller
                            ? navigator.serviceWorker.controller.scriptURL : 'none (no controller)') + '</small>',
                        '#4CAF50');
                    btn.disabled    = false;
                    btn.textContent = 'Send Test Notification';
                }).catch(function (err) {
                    setStatus('&#10060; Error: ' + err.message, '#f44336');
                    btn.disabled    = false;
                    btn.textContent = 'Send Test Notification';
                });
            });

            // ── Diagnostic readout on load ───────────────────────────────
            var diagLines = [
                'SW API: '           + ('serviceWorker' in navigator ? 'supported' : 'MISSING'),
                'Notification API: ' + ('Notification' in window     ? 'supported' : 'MISSING'),
                'Permission: '       + (typeof Notification !== 'undefined' ? Notification.permission : 'n/a'),
            ];
            navigator.serviceWorker.getRegistrations().then(function (regs) {
                diagLines.push('SW registrations: ' + (regs.length ? regs.map(function(r){ return r.scope; }).join(', ') : 'none'));
                setStatus('<small style="opacity:.6">' + diagLines.join(' &bull; ') + '</small>');
            }).catch(function () {
                setStatus('<small style="opacity:.6">' + diagLines.join(' &bull; ') + '</small>');
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
    // ─────────────────────────────────────────────────────────────────────
    // [oha_convention_vendors]
    // Displays a card grid of confirmed convention vendors (status = Paid).
    // Atts: title="Our Convention Vendors"
    // ─────────────────────────────────────────────────────────────────────
    public function convention_vendors( $atts ) {
        $atts  = shortcode_atts( [ 'title' => 'Convention Vendor Hall' ], $atts );
        $title = esc_html( $atts['title'] );

        $card_fields    = get_option( 'oha_pwa_vendor_card_fields', [
            'website'  => true,
            'products' => false,
            'social'   => false,
        ] );
        $convention_map_url = get_option( 'oha_pwa_convention_map_url', '' );

        $vendors = get_posts( [
            'post_type'      => 'vendor_application',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation'    => 'AND',
                'status'      => [
                    'key'     => '_oha_status',
                    'value'   => [ 'Paid', 'paid', 'PAID' ],
                    'compare' => 'IN',
                ],
                'name_clause' => [
                    'key'     => 'company_name',
                    'compare' => 'EXISTS',
                ],
            ],
            'orderby'        => 'name_clause',
            'order'          => 'ASC',
        ] );

        // Build booth lookup map (single query instead of N+1)
        $booth_map = [];
        if ( post_type_exists( 'oha_booth' ) ) {
            $all_booths = get_posts( [
                'post_type'      => 'oha_booth',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => [ [ 'key' => '_booth_vendor_hub_id', 'compare' => 'EXISTS' ] ],
            ] );
            foreach ( $all_booths as $bid ) {
                $vid  = get_post_meta( $bid, '_booth_vendor_hub_id', true );
                $bnum = get_post_meta( $bid, '_booth_number', true );
                if ( $vid ) $booth_map[ (string) $vid ] = $bnum;
            }
        }

        ob_start(); ?>
        <style>
        .oha-vendors-wrap { padding: 16px 0; }
        .oha-vendors-heading { font-size: 1.5em; font-weight: 700; margin-bottom: 4px; color: #fff; }
        .oha-vendors-count { font-size: 13px; opacity: .65; margin-bottom: 20px; color: #fff; }
        .oha-vendors-grid { display: flex; flex-direction: column; gap: 14px; }
        .oha-vendor-card {
            background: #111;
            border: 1px solid rgba(173, 49, 25, .3);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0,0,0,.55);
            position: relative;
            -webkit-tap-highlight-color: transparent;
        }
        .oha-vendor-img-wrap {
            position: relative;
            width: 100%;
            aspect-ratio: 5/3;
            background: rgba(255,255,255,.04);
            overflow: hidden;
        }
        .oha-vendor-logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            padding: 20px;
            box-sizing: border-box;
        }
        .oha-vendor-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
        }
        .oha-vendor-img-overlay {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            padding: 28px 12px 10px;
            background: linear-gradient(to top, rgba(0,0,0,.88) 0%, rgba(0,0,0,.45) 55%, transparent 100%);
        }
        .oha-vendor-name {
            display: block;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
        }
        .oha-vendor-site {
            display: block;
            font-size: 11px;
            color: rgba(255,255,255,.65);
            margin-top: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .oha-vendor-favorite-btn {
            position: absolute;
            top: 8px; right: 8px;
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(0,0,0,.55);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border: 1.5px solid rgba(255,255,255,.3);
            font-size: 20px;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            -webkit-tap-highlight-color: transparent;
            transition: background .2s, transform .15s;
            line-height: 1;
        }
        .oha-vendor-favorite-btn:active { transform: scale(.86); }
        .oha-vendor-favorite-btn.is-favorite {
            background: rgba(173,49,25,.9);
            border-color: rgba(255,255,255,.4);
        }
        .oha-vendor-extra {
            padding: 8px 12px 10px;
            border-top: 1px solid rgba(255,255,255,.06);
        }
        .oha-vendor-products { font-size:.75rem; color:#aaa; margin:0 0 4px; line-height:1.4; }
        .oha-vendor-social   { font-size:.75rem; color:#aaa; margin:0; }
        .oha-vendors-empty {
            text-align: center;
            padding: 48px 16px;
            font-size: 18px;
            color: rgba(255,255,255,.6);
        }
        .oha-vendor-locate-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            padding: 9px 12px;
            background: rgba(173,49,25,.15);
            border-top: 1px solid rgba(173,49,25,.35);
            color: #ff8a70;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            letter-spacing: .02em;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
            transition: background .15s;
        }
        .oha-vendor-locate-btn:hover,
        .oha-vendor-locate-btn:active { background: rgba(173,49,25,.32); color: #ff8a70; text-decoration: none; }
        .oha-vendors-subtext { margin: 0 0 14px; font-size: 13px; color: rgba(255,255,255,.5); text-align: center; }
        .oha-vendors-search-wrap { margin: 0 0 16px; }
        .oha-vendors-search-input {
            width: 100%; box-sizing: border-box;
            padding: 10px 14px;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.13);
            border-radius: 10px;
            color: #eee;
            font-size: 14px;
            outline: none;
            -webkit-appearance: none;
        }
        .oha-vendors-search-input::placeholder { color: rgba(255,255,255,.35); }
        .oha-vendors-search-input:focus { border-color: rgba(173,49,25,.7); background: rgba(255,255,255,.1); }
        </style>
        <div class="oha-vendors-wrap">
            <h2 class="oha-vendors-heading"><?php echo $title; ?></h2>

            <?php if ( empty( $vendors ) ) : ?>
            <div class="oha-vendors-empty">Vendor announcements coming soon.</div>
            <?php else : ?>

            <p class="oha-vendors-subtext">Find them on the map. Save favorites to plan your visit.</p>
            <div class="oha-vendors-search-wrap">
                <input type="search" class="oha-vendors-search-input" placeholder="Search vendors...">
            </div>
            <div class="oha-vendors-grid">
            <?php foreach ( $vendors as $vendor ) :
                $company_name = get_post_meta( $vendor->ID, 'company_name', true );
                if ( empty( $company_name ) ) {
                    $company_name = get_the_title( $vendor->ID );
                }

                // Logo — resolve URL for both display and localStorage storage
                $logo_src = '';
                $logo_id  = get_post_meta( $vendor->ID, '_company_logo_id', true );
                if ( $logo_id ) {
                    $img = wp_get_attachment_image_src( (int) $logo_id, [ 400, 300 ] );
                    if ( $img ) $logo_src = $img[0];
                }
                if ( ! $logo_src ) {
                    $logo_src = get_post_meta( $vendor->ID, '_company_logo_url', true );
                }

                // Optional meta fields
                $website = get_post_meta( $vendor->ID, 'website', true );
                $website_display = $website ? preg_replace( '#^https?://#', '', rtrim( $website, '/' ) ) : '';
                $products = ! empty( $card_fields['products'] ) ? get_post_meta( $vendor->ID, 'product_type', true ) : '';
                $social   = ! empty( $card_fields['social'] )   ? get_post_meta( $vendor->ID, 'company_social', true ) : '';

                // Booth number from lookup map (may be empty string if no number assigned)
                $has_booth    = array_key_exists( (string) $vendor->ID, $booth_map );
                $booth_number = $has_booth ? (string) $booth_map[ (string) $vendor->ID ] : '';
            ?>
            <div class="oha-vendor-card"
                 data-vendor-id="<?php echo esc_attr( $vendor->ID ); ?>"
                 data-name="<?php echo esc_attr( $company_name ); ?>"
                 data-logo="<?php echo esc_attr( $logo_src ); ?>"
                 data-website="<?php echo esc_attr( $website ); ?>"
                 data-booth="<?php echo esc_attr( $booth_number ); ?>">
                <div class="oha-vendor-img-wrap">
                    <?php if ( $logo_src ) : ?>
                        <img class="oha-vendor-logo" src="<?php echo esc_url( $logo_src ); ?>" alt="<?php echo esc_attr( $company_name ); ?>">
                    <?php else : ?>
                        <div class="oha-vendor-placeholder">&#127962;</div>
                    <?php endif; ?>
                    <button class="oha-vendor-favorite-btn" data-vendor-id="<?php echo esc_attr( $vendor->ID ); ?>" data-booth="<?php echo esc_attr( $booth_number ); ?>" aria-label="Add to Must-See Vendors"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 3C6.69 3 4 5.69 4 9c0 2.08 1.06 3.9 2.67 5H7v2h6v-2h.33C14.94 12.9 16 11.08 16 9c0-3.31-2.69-6-6-6z"/><circle cx="8" cy="9" r="1.2" fill="currentColor" stroke="none"/><circle cx="12" cy="9" r="1.2" fill="currentColor" stroke="none"/></svg></button>
                    <div class="oha-vendor-img-overlay">
                        <span class="oha-vendor-name"><?php echo esc_html( $company_name ); ?></span>
                        <?php if ( $website_display ) : ?>
                        <span class="oha-vendor-site">&#127760; <?php echo esc_html( $website_display ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ( $products || $social ) : ?>
                <div class="oha-vendor-extra">
                    <?php if ( $products ) : ?>
                    <p class="oha-vendor-products"><?php echo esc_html( $products ); ?></p>
                    <?php endif; ?>
                    <?php if ( $social ) : ?>
                    <p class="oha-vendor-social">&#128241; <?php echo esc_html( $social ); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ( $has_booth && $convention_map_url ) : ?>
                <a class="oha-vendor-locate-btn"
                   href="<?php echo esc_url( rtrim( $convention_map_url, '/' ) . '/?oha_vendor=' . $vendor->ID ); ?>">
                    &#128205; <?php echo $booth_number ? 'Find Booth ' . esc_html( $booth_number ) . ' on Map' : 'Find on Map'; ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </div>

            <?php endif; ?>
        </div>
        <?php if ( ! empty( $vendors ) ) : ?>
        <script>
        (function () {
            var wrap  = document.querySelector('.oha-vendors-wrap');
            var input = wrap && wrap.querySelector('.oha-vendors-search-input');
            if (!input) return;
            input.addEventListener('input', function () {
                var q = this.value.toLowerCase().trim();
                wrap.querySelectorAll('.oha-vendor-card').forEach(function (card) {
                    var name = (card.getAttribute('data-name') || '').toLowerCase();
                    card.style.display = (!q || name.includes(q)) ? '' : 'none';
                });
            });

            // Track locate-on-map clicks
            if (wrap) {
                wrap.addEventListener('click', function (e) {
                    var btn = e.target.closest('.oha-vendor-locate-btn');
                    if (!btn) return;
                    var card = btn.closest('.oha-vendor-card');
                    var name = card ? (card.getAttribute('data-name') || '') : '';
                    var booth = card ? (card.getAttribute('data-booth') || '') : '';
                    var vid  = card ? (card.getAttribute('data-vendor-id') || '') : '';
                    if (window.OHAAnalytics) {
                        OHAAnalytics.trackVendorMapClick(vid, name, booth);
                    }
                });
            }
        })();
        </script>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }
    // ─────────────────────────────────────────────────────────────────────
    // [oha_board_dashboard]
    // Full password-protected board dashboard (5 tabs):
    //   Notifications | Live Stats | Board Notes | Emergency Contacts | Quick Links
    // Password and content are configured in PWA Settings → Board Hub tab.
    // Auth pattern: shared password checked client-side, persisted in sessionStorage.
    // ─────────────────────────────────────────────────────────────────────
    public function board_dashboard( $atts ) {
        $password = get_option( 'oha_pwa_board_password', '' );

        if ( empty( $password ) ) {
            if ( current_user_can( 'manage_options' ) ) {
                return '<div style="padding:12px;border-left:4px solid #ffb900;background:#fffbeb;margin:16px 0;">'
                    . '<p>⚠️ Board Hub password not configured. <a href="'
                    . esc_url( admin_url( 'options-general.php?page=oha-pwa-settings' ) )
                    . '">PWA Settings → Board Hub</a></p></div>';
            }
            return '';
        }

        // Subscriber count
        global $wpdb;
        $pt          = $wpdb->prefix . 'perfecty_push_users';
        $sub_count   = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pt ) ) === $pt )
                       ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM $pt" ) : 0;
        $perfecty_ok = class_exists( 'Perfecty_Push_Lib_Push_Server' );

        // Convention page chips
        $_conv_base = rtrim( get_option( 'oha_convention_base_url', '/convention' ), '/' );
        $convention_pages = [
            '⭐ My OHC'      => home_url( $_conv_base . '/my-ohc/' ),
            '📅 Schedule'    => home_url( $_conv_base . '/schedule/' ),
            '🗓 My Schedule' => home_url( $_conv_base . '/my-schedule/' ),
            '🛒 Vendors'     => home_url( $_conv_base . '/vendors/' ),
            '🗺 Map'         => home_url( $_conv_base . '/map/' ),
            '🏠 Home'        => home_url( '/convention/' ),
        ];

        $rest_nonce   = wp_create_nonce( 'wp_rest' );
        $send_url     = esc_js( rest_url( 'oha/v1/notifications/send' ) );
        $stats_url    = esc_js( rest_url( 'oha/v1/notifications/stats' ) );
        $auth_url     = esc_js( rest_url( 'oha/v1/board/auth' ) );
        $content_url  = esc_js( rest_url( 'oha/v1/board/content' ) );
        $inc_save_url = esc_js( rest_url( 'oha/v1/board/incident/save' ) );
        $inc_list_url = esc_js( rest_url( 'oha/v1/board/incident/list' ) );

        ob_start(); ?>
<div id="oha-board-dashboard">
<style>
#oha-board-dashboard { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #eee; }

/* Lock screen */
#oha-bd-locked { max-width: 420px; margin: 40px auto; background: #1a1a2e; border-radius: 16px; padding: 36px 28px; text-align: center; box-shadow: 0 4px 32px rgba(0,0,0,.5); }
#oha-bd-locked h2 { margin: 0 0 10px; font-size: 1.6em; }
#oha-bd-locked p { opacity: .75; margin-bottom: 24px; }
#oha-bd-pw-input { width: 100%; box-sizing: border-box; padding: 12px 14px; font-size: 16px; border-radius: 8px; border: 2px solid #333; background: #111; color: #fff; margin-bottom: 12px; }
#oha-bd-pw-input:focus { outline: none; border-color: #AD3119; }
#oha-bd-pw-submit { width: 100%; padding: 12px; background: #AD3119; border: none; color: #fff; font-size: 16px; font-weight: 600; border-radius: 8px; cursor: pointer; }
#oha-bd-pw-submit:hover { background: #8B0000; }
#oha-bd-pw-error { color: #f44336; font-size: 14px; margin-top: 10px; display: none; }

/* Unlocked header */
#oha-bd-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 0 12px; border-bottom: 2px solid #AD3119; margin-bottom: 16px; }
#oha-bd-header h2 { margin: 0; font-size: 1.4em; }
#oha-board-logout { background: transparent; border: 1px solid #666; color: #ccc; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; }
#oha-board-logout:hover { border-color: #AD3119; color: #fff; }

/* Tab nav */
.oha-bd-tab-nav { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
.oha-bd-tab-btn { background: #222; border: 1px solid #444; color: #ccc; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all .2s; }
.oha-bd-tab-btn:hover { border-color: #AD3119; color: #fff; }
.oha-bd-tab-btn.active { background: #AD3119; border-color: #AD3119; color: #fff; }

/* Tab content */
.oha-bd-tab-content { background: #111; border: 1px solid #333; border-radius: 10px; padding: 20px; }

/* Notification form */
.oha-bd-form-group { margin-bottom: 16px; }
.oha-bd-form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #ccc; }
.oha-bd-form-group input[type="text"],
.oha-bd-form-group input[type="url"],
.oha-bd-form-group textarea { width: 100%; box-sizing: border-box; padding: 9px 11px; background: #1a1a2e; border: 1px solid #444; border-radius: 6px; color: #fff; font-size: 14px; }
.oha-bd-form-group input:focus,
.oha-bd-form-group textarea:focus { outline: none; border-color: #AD3119; }
.oha-bd-hint { font-size: 11px; color: #888; margin-top: 4px; }
.oha-bd-chips { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 8px; }
.oha-bd-chip { background: #222; border: 1px solid #555; color: #ccc; padding: 3px 10px; border-radius: 20px; cursor: pointer; font-size: 12px; }
.oha-bd-chip:hover, .oha-bd-chip.active { background: #AD3119; border-color: #AD3119; color: #fff; }
.oha-bd-send-btn { width: 100%; padding: 12px; background: #AD3119; border: none; color: #fff; font-size: 15px; font-weight: 600; border-radius: 8px; cursor: pointer; margin-top: 4px; }
.oha-bd-send-btn:hover { background: #8B0000; }
.oha-bd-send-btn:disabled { background: #555; cursor: not-allowed; }
#oha-bd-notif-result { margin-top: 12px; font-size: 14px; }

/* Stats */
.oha-bd-stats-table { width: 100%; border-collapse: collapse; }
.oha-bd-stats-table td { padding: 10px 12px; border-bottom: 1px solid #222; font-size: 14px; }
.oha-bd-stats-table td:first-child { color: #aaa; }
.oha-bd-stats-table td:last-child { text-align: right; font-size: 1.1em; }
.oha-bd-refresh-btn { background: #222; border: 1px solid #555; color: #ccc; padding: 7px 16px; border-radius: 6px; cursor: pointer; margin-bottom: 16px; font-size: 13px; }
.oha-bd-refresh-btn:hover { border-color: #AD3119; color: #fff; }

/* Board Notes */
.oha-bd-notes-content { line-height: 1.7; }
.oha-bd-notes-content h1,.oha-bd-notes-content h2,.oha-bd-notes-content h3 { color: #fff; }
.oha-bd-notes-content a { color: #AD3119; }
.oha-bd-notes-empty { color: #888; font-style: italic; }

/* Emergency Contacts */
.oha-bd-contacts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; }
.oha-bd-contact-card { background: #1a1a2e; border: 1px solid #333; border-radius: 10px; padding: 16px; }
.oha-bd-contact-name { font-size: 16px; font-weight: 700; margin: 0 0 4px; }
.oha-bd-contact-role { display: inline-block; background: #AD3119; color: #fff; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; margin-bottom: 10px; }
.oha-bd-contact-call { display: block; text-align: center; background: #4CAF50; color: #fff; text-decoration: none; padding: 8px; border-radius: 7px; font-weight: 600; font-size: 14px; margin-top: 10px; }
.oha-bd-contact-call:hover { background: #388E3C; color: #fff; }
.oha-bd-contacts-empty { color: #888; font-style: italic; }

/* Quick Links */
.oha-bd-links-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; }
.oha-bd-link-card { display: block; background: #1a1a2e; border: 1px solid #333; border-radius: 10px; padding: 16px 12px; text-align: center; text-decoration: none; color: #eee; transition: all .2s; }
.oha-bd-link-card:hover { border-color: #AD3119; color: #fff; transform: translateY(-2px); }
.oha-bd-link-icon { font-size: 28px; display: block; margin-bottom: 8px; }
.oha-bd-link-label { font-size: 13px; font-weight: 600; }
.oha-bd-cache-btn { background: #1a1a2e; border: 1px solid #333; border-radius: 10px; padding: 16px 12px; text-align: center; cursor: pointer; color: #eee; width: 100%; transition: all .2s; }
.oha-bd-cache-btn:hover { border-color: #AD3119; color: #fff; }

/* Incident Reports */
.oha-bd-report-section { margin-bottom: 18px; }
.oha-bd-report-section h4 { margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #aaa; text-transform: uppercase; letter-spacing: .04em; }
.oha-bd-report-submit { width: 100%; padding: 12px; background: #AD3119; border: none; color: #fff; font-size: 15px; font-weight: 600; border-radius: 8px; cursor: pointer; margin-top: 4px; }
.oha-bd-report-submit:hover { background: #8B0000; }
.oha-bd-report-submit:disabled { background: #555; cursor: not-allowed; }
.oha-bd-report-result { margin-top: 10px; font-size: 14px; }
.oha-bd-photo-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.oha-bd-photo-thumb { width: 72px; height: 72px; object-fit: cover; border-radius: 6px; border: 1px solid #444; }
.oha-bd-photo-remove { display: block; font-size: 11px; color: #f44336; cursor: pointer; text-align: center; margin-top: 2px; }
.oha-bd-report-list { margin-top: 24px; }
.oha-bd-report-item { background: #1a1a2e; border: 1px solid #333; border-radius: 10px; padding: 14px; margin-bottom: 10px; cursor: pointer; }
.oha-bd-report-item-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
.oha-bd-report-type-badge { background: #AD3119; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; white-space: nowrap; }
.oha-bd-report-meta { font-size: 12px; color: #888; margin-top: 4px; }
.oha-bd-report-detail { display: none; margin-top: 12px; border-top: 1px solid #2a2a2a; padding-top: 12px; font-size: 13px; line-height: 1.6; }
.oha-bd-report-detail.open { display: block; }
.oha-bd-report-field { margin-bottom: 8px; }
.oha-bd-report-field strong { color: #aaa; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 2px; }
.oha-bd-report-photos { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.oha-bd-report-photos a img { width: 80px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #444; }
.oha-bd-draft-notice { font-size: 12px; color: #888; font-style: italic; margin-bottom: 8px; }
</style>

<!-- ── Password gate ─────────────────────────────────────────── -->
<div id="oha-bd-locked">
    <h2>🏛️ Board Hub</h2>
    <p>Enter the board password to access the on-site dashboard.</p>
    <input type="password" id="oha-bd-pw-input" placeholder="Board password" autocomplete="current-password">
    <button id="oha-bd-pw-submit">🔓 Unlock Dashboard</button>
    <p id="oha-bd-pw-error">Incorrect password. Please try again.</p>
</div>

<!-- ── Dashboard (shown after auth) ────────────────────────── -->
<div id="oha-bd-unlocked" style="display:none;">

    <div id="oha-bd-header">
        <h2>🏛️ Board Hub</h2>
        <button id="oha-board-logout">🚪 Log Out</button>
    </div>

    <nav class="oha-bd-tab-nav">
        <button class="oha-bd-tab-btn active" data-tab="notify">📢 Notifications</button>
        <button class="oha-bd-tab-btn" data-tab="stats">📊 Stats</button>
        <button class="oha-bd-tab-btn" data-tab="notes">📋 Notes</button>
        <button class="oha-bd-tab-btn" data-tab="contacts">🚨 Contacts</button>
        <button class="oha-bd-tab-btn" data-tab="reports">📝 Reports</button>
        <button class="oha-bd-tab-btn" data-tab="links">⚡ Links</button>
    </nav>

    <!-- Tab 1: Notifications -->
    <div id="oha-bd-tab-notify" class="oha-bd-tab-content">
        <h3 style="margin-top:0;">📢 Send Push Notification</h3>
        <?php if ( ! $perfecty_ok ) : ?>
        <p style="color:#f44336;">⚠️ Perfecty Push plugin is not active. Push notifications are unavailable.</p>
        <?php else : ?>
        <p style="color:#aaa;font-size:14px;">Sends instantly to all <strong style="color:#fff;"><?php echo $sub_count; ?></strong> subscribed device<?php echo $sub_count !== 1 ? 's' : ''; ?>.</p>
        <form id="oha-bd-notif-form">
            <div class="oha-bd-form-group">
                <label>Notification Title *</label>
                <input type="text" id="oha-bd-notif-title" placeholder="e.g., Schedule Change" required>
            </div>
            <div class="oha-bd-form-group">
                <label>Message *</label>
                <textarea id="oha-bd-notif-message" rows="3" placeholder="e.g., Room A has moved to the second floor" required></textarea>
            </div>
            <div class="oha-bd-form-group">
                <label>Image URL <span style="font-weight:400;color:#888;">(optional)</span></label>
                <input type="url" id="oha-bd-notif-image" placeholder="https://example.com/image.jpg">
                <p class="oha-bd-hint">Paste a direct image URL. Recommended: square, at least 192×192.</p>
            </div>
            <div class="oha-bd-form-group">
                <label>Tap opens URL <span style="font-weight:400;color:#888;">(optional)</span></label>
                <div class="oha-bd-chips">
                    <?php foreach ( $convention_pages as $chip_label => $chip_url ) : ?>
                    <button type="button" class="oha-bd-chip" data-url="<?php echo esc_attr( $chip_url ); ?>">
                        <?php echo esc_html( $chip_label ); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="url" id="oha-bd-notif-url" value="<?php echo esc_attr( home_url( rtrim( get_option( 'oha_convention_base_url', '/convention' ), '/' ) . '/my-ohc/' ) ); ?>">
                <p class="oha-bd-hint">Tap a page above to pre-fill, or type a custom URL.</p>
            </div>
            <button type="submit" id="oha-bd-notif-btn" class="oha-bd-send-btn">📤 Send to All Devices</button>
            <div id="oha-bd-notif-result"></div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Tab 2: Live Stats -->
    <div id="oha-bd-tab-stats" class="oha-bd-tab-content" style="display:none;">
        <h3 style="margin-top:0;">📊 Live Stats</h3>
        <button class="oha-bd-refresh-btn" id="oha-bd-stats-refresh">🔄 Refresh</button>
        <div id="oha-bd-stats-content"><p style="color:#888;">Click the Refresh button or switch to this tab to load stats.</p></div>
    </div>

    <!-- Tab 3: Board Notes -->
    <div id="oha-bd-tab-notes" class="oha-bd-tab-content" style="display:none;">
        <h3 style="margin-top:0;">📋 Board Notes</h3>
        <div id="oha-bd-notes-content"><p style="opacity:.6">Loading...</p></div>
    </div>

    <!-- Tab 4: Emergency Contacts -->
    <div id="oha-bd-tab-contacts" class="oha-bd-tab-content" style="display:none;">
        <h3 style="margin-top:0;">🚨 Emergency Contacts</h3>
        <div id="oha-bd-contacts-content"><p style="opacity:.6">Loading...</p></div>
    </div>

    <!-- Tab 5: Incident Reports -->
    <div id="oha-bd-tab-reports" class="oha-bd-tab-content" style="display:none;">
        <h3 style="margin-top:0;">📝 Incident Report</h3>
        <p id="oha-bd-rep-draft-notice" class="oha-bd-draft-notice" style="display:none;">Draft restored. Review and submit or clear.</p>
        <form id="oha-bd-report-form" autocomplete="off">
            <div class="oha-bd-report-section">
                <div class="oha-bd-form-group">
                    <label>Reported By *</label>
                    <input type="text" id="oha-bd-rep-by" placeholder="Your name">
                </div>
            </div>
            <div class="oha-bd-form-group">
                <label>Date and Time of Incident *</label>
                <input type="datetime-local" id="oha-bd-rep-time">
            </div>
            <div class="oha-bd-form-group">
                <label>Location *</label>
                <input type="text" id="oha-bd-rep-location" placeholder="e.g. Main Hall, Registration, Parking Lot">
            </div>
            <div class="oha-bd-form-group">
                <label>Incident Type *</label>
                <select id="oha-bd-rep-type" style="width:100%;padding:9px 11px;background:#1a1a2e;border:1px solid #444;border-radius:6px;color:#fff;font-size:14px;">
                    <option value="">Select type...</option>
                    <option value="Injury">Injury</option>
                    <option value="Medical Emergency">Medical Emergency</option>
                    <option value="Altercation / Fight">Altercation / Fight</option>
                    <option value="Property Damage">Property Damage</option>
                    <option value="Theft">Theft</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="oha-bd-form-group">
                <label>People Involved</label>
                <textarea id="oha-bd-rep-people" rows="2" placeholder="Names or descriptions of anyone involved"></textarea>
            </div>
            <div class="oha-bd-form-group">
                <label>Description of Incident *</label>
                <textarea id="oha-bd-rep-desc" rows="4" placeholder="Describe what happened in detail"></textarea>
            </div>
            <div class="oha-bd-form-group">
                <label>Action Taken</label>
                <textarea id="oha-bd-rep-action" rows="3" placeholder="What steps were taken in response"></textarea>
            </div>
            <div class="oha-bd-form-group">
                <label>Witnesses</label>
                <input type="text" id="oha-bd-rep-witnesses" placeholder="Names or descriptions of any witnesses">
            </div>
            <div class="oha-bd-form-group">
                <label>Photos <span style="font-weight:400;color:#888;">(up to 4)</span></label>
                <input type="file" id="oha-bd-rep-photos" accept="image/*" multiple style="color:#ccc;font-size:13px;">
                <div id="oha-bd-rep-photo-preview" class="oha-bd-photo-row"></div>
                <p class="oha-bd-hint">Photos are compressed automatically before upload.</p>
            </div>
            <button type="submit" id="oha-bd-rep-submit" class="oha-bd-report-submit">Submit Incident Report</button>
            <div id="oha-bd-rep-result" class="oha-bd-report-result"></div>
        </form>

        <div class="oha-bd-report-list">
            <h3 style="margin-bottom:12px;">Previous Reports</h3>
            <button class="oha-bd-refresh-btn" id="oha-bd-rep-load">Load Reports</button>
            <div id="oha-bd-rep-list-content"></div>
        </div>
    </div>

    <!-- Tab 6: Quick Links -->
    <div id="oha-bd-tab-links" class="oha-bd-tab-content" style="display:none;">
        <h3 style="margin-top:0;">⚡ Quick Links</h3>
        <div class="oha-bd-links-grid">
            <?php $_conv_base = rtrim( get_option( 'oha_convention_base_url', '/convention' ), '/' ); ?>
            <a class="oha-bd-link-card" href="<?php echo esc_url( home_url( $_conv_base . '/my-ohc/' ) ); ?>">
                <span class="oha-bd-link-icon">⭐</span>
                <span class="oha-bd-link-label">My OHC</span>
            </a>
            <a class="oha-bd-link-card" href="<?php echo esc_url( home_url( $_conv_base . '/schedule/' ) ); ?>">
                <span class="oha-bd-link-icon">📅</span>
                <span class="oha-bd-link-label">Schedule</span>
            </a>
            <a class="oha-bd-link-card" href="<?php echo esc_url( home_url( $_conv_base . '/vendors/' ) ); ?>">
                <span class="oha-bd-link-icon">🛒</span>
                <span class="oha-bd-link-label">Vendors</span>
            </a>
            <a class="oha-bd-link-card" href="<?php echo esc_url( admin_url() ); ?>" target="_blank">
                <span class="oha-bd-link-icon">🖥️</span>
                <span class="oha-bd-link-label">WP Admin</span>
            </a>
            <a class="oha-bd-link-card" href="<?php echo esc_url( admin_url( 'admin.php?page=perfecty-push-notifications' ) ); ?>" target="_blank">
                <span class="oha-bd-link-icon">🔔</span>
                <span class="oha-bd-link-label">Perfecty Push</span>
            </a>
            <button class="oha-bd-cache-btn" id="oha-bd-cache-clear">
                <span class="oha-bd-link-icon">🗑️</span>
                <span class="oha-bd-link-label">Clear My Cache</span>
            </button>
        </div>
    </div>

</div><!-- #oha-bd-unlocked -->
</div><!-- #oha-board-dashboard -->

<script>
(function () {
    var AUTH_URL    = '<?php echo $auth_url; ?>';
    var CONTENT_URL = '<?php echo $content_url; ?>';
    var SEND_URL    = '<?php echo $send_url; ?>';
    var STAT_URL    = '<?php echo $stats_url; ?>';
    var INC_SAVE    = '<?php echo $inc_save_url; ?>';
    var INC_LIST    = '<?php echo $inc_list_url; ?>';
    var NONCE       = '<?php echo esc_js( $rest_nonce ); ?>';

    var elLocked   = document.getElementById('oha-bd-locked');
    var elUnlocked = document.getElementById('oha-bd-unlocked');
    var elPwInput  = document.getElementById('oha-bd-pw-input');
    var elPwError  = document.getElementById('oha-bd-pw-error');

    function unlock() {
        elLocked.style.display   = 'none';
        elUnlocked.style.display = '';
    }
    function lock() {
        elUnlocked.style.display = 'none';
        elLocked.style.display   = '';
        elPwInput.value          = '';
        elPwError.style.display  = 'none';
    }
    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function loadBoardContent(token, doUnlock) {
        fetch(CONTENT_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-WP-Nonce': NONCE},
            body: JSON.stringify({token: token})
        })
        .then(function(r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function(data) {
            if (doUnlock) { unlock(); }
            var notesEl = document.getElementById('oha-bd-notes-content');
            if (notesEl) { notesEl.innerHTML = data.notes || '<p class="oha-bd-notes-empty">No board notes set yet.</p>'; }
            var cEl = document.getElementById('oha-bd-contacts-content');
            if (cEl && data.contacts && data.contacts.length) {
                var html = '<div class="oha-bd-contacts-grid">';
                data.contacts.forEach(function(c) {
                    var ph = c.phone.replace(/[^0-9+]/g,'');
                    html += '<div class="oha-bd-contact-card">';
                    html += '<p class="oha-bd-contact-name">' + escHtml(c.name) + '</p>';
                    if (c.role) { html += '<span class="oha-bd-contact-role">' + escHtml(c.role) + '</span>'; }
                    if (ph) { html += '<a class="oha-bd-contact-call" href="tel:' + ph + '">📞 ' + escHtml(c.phone) + '</a>'; }
                    else { html += '<p style="color:#888;font-size:13px;margin:8px 0 0;">No phone listed</p>'; }
                    html += '</div>';
                });
                html += '</div>';
                cEl.innerHTML = html;
            } else if (cEl) {
                cEl.innerHTML = '<p class="oha-bd-contacts-empty">No emergency contacts configured yet.</p>';
            }
        })
        .catch(function() {
            localStorage.removeItem('oha_board_token');
            lock();
        });
    }

    // Restore session
    var savedToken = localStorage.getItem('oha_board_token');
    if (savedToken) { loadBoardContent(savedToken, true); }

    // Password submit
    document.getElementById('oha-bd-pw-submit').addEventListener('click', function () {
        var pw = elPwInput.value.trim();
        if (!pw) return;
        fetch(AUTH_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({password: pw})
        })
        .then(function(r) { return r.ok ? r.json() : r.json().then(function(e){ throw e; }); })
        .then(function(data) {
            localStorage.setItem('oha_board_token', data.token);
            loadBoardContent(data.token, true);
        })
        .catch(function(e) {
            elPwError.textContent = e.message || 'Incorrect password.';
            elPwError.style.display = 'block';
            elPwInput.select();
        });
    });
    elPwInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { document.getElementById('oha-bd-pw-submit').click(); }
    });

    // Logout
    document.getElementById('oha-board-logout').addEventListener('click', function () {
        localStorage.removeItem('oha_board_token');
        lock();
    });

    // Tab switching
    var statsLoaded = false;
    document.querySelectorAll('.oha-bd-tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.oha-bd-tab-btn').forEach(function (b) { b.classList.remove('active'); });
            document.querySelectorAll('.oha-bd-tab-content').forEach(function (c) { c.style.display = 'none'; });
            this.classList.add('active');
            var tab = this.getAttribute('data-tab');
            document.getElementById('oha-bd-tab-' + tab).style.display = '';
            if (tab === 'stats' && !statsLoaded) { loadStats(); }
        });
    });

    // Stats
    function loadStats() {
        var container = document.getElementById('oha-bd-stats-content');
        container.innerHTML = '<p style="color:#aaa;">⏳ Loading stats…</p>';
        fetch(STAT_URL, { headers: { 'X-WP-Nonce': NONCE } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                statsLoaded = true;
                var p = d.platforms || {};
                container.innerHTML =
                    '<table class="oha-bd-stats-table">' +
                    '<tr><td>📡 Push Subscribers</td><td><strong>' + (d.subscribers || 0) + '</strong></td></tr>' +
                    '<tr><td>📱 Total PWA Installs</td><td><strong>' + (d.installs || 0) + '</strong></td></tr>' +
                    '<tr><td>🟢 Active (last 30d)</td><td><strong>' + (d.active_installs || 0) + '</strong></td></tr>' +
                    '<tr><td>🍎 iOS</td><td><strong>' + (p.ios || 0) + '</strong></td></tr>' +
                    '<tr><td>🤖 Android</td><td><strong>' + (p.android || 0) + '</strong></td></tr>' +
                    '<tr><td>💻 Other</td><td><strong>' + (p.other || 0) + '</strong></td></tr>' +
                    '</table>';
            })
            .catch(function (err) {
                container.innerHTML = '<p style="color:#f44336;">❌ Failed to load stats: ' + err.message + '</p>';
            });
    }
    document.getElementById('oha-bd-stats-refresh').addEventListener('click', function () {
        statsLoaded = false;
        loadStats();
    });

    // Page chips → fill URL input
    document.querySelectorAll('.oha-bd-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            document.getElementById('oha-bd-notif-url').value = this.getAttribute('data-url');
            document.querySelectorAll('.oha-bd-chip').forEach(function (c) { c.classList.remove('active'); });
            this.classList.add('active');
        });
    });

    // Notification form
    var notifForm = document.getElementById('oha-bd-notif-form');
    if (notifForm) {
        notifForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var title   = document.getElementById('oha-bd-notif-title').value.trim();
            var message = document.getElementById('oha-bd-notif-message').value.trim();
            var url     = document.getElementById('oha-bd-notif-url').value.trim();
            var image   = document.getElementById('oha-bd-notif-image').value.trim();
            var btn     = document.getElementById('oha-bd-notif-btn');
            var result  = document.getElementById('oha-bd-notif-result');

            if (!title || !message) {
                result.innerHTML = '<p style="color:#f44336;">Please fill in both title and message.</p>';
                return;
            }
            btn.disabled    = true;
            btn.textContent = 'Sending…';
            result.innerHTML = '<p style="color:#0073aa;">📤 Sending…</p>';

            fetch(SEND_URL, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE },
                body:    JSON.stringify({ title: title, message: message, url: url, image: image }),
            }).then(function (r) { return r.json(); }).then(function (data) {
                btn.disabled    = false;
                btn.textContent = '📤 Send to All Devices';
                if (data.id) {
                    result.innerHTML = '<p style="color:#46b450;font-weight:600;">✅ Sent to ' +
                        data.recipients + ' device' + (data.recipients !== 1 ? 's' : '') + '!</p>';
                    ['oha-bd-notif-title','oha-bd-notif-message','oha-bd-notif-image','oha-bd-notif-url']
                        .forEach(function (id) { var el = document.getElementById(id); if (el) el.value = ''; });
                    document.querySelectorAll('.oha-bd-chip').forEach(function (c) { c.classList.remove('active'); });
                } else {
                    result.innerHTML = '<p style="color:#f44336;">❌ ' + escHtml(data.message || JSON.stringify(data)) + '</p>';
                }
            }).catch(function (err) {
                btn.disabled    = false;
                btn.textContent = '📤 Send to All Devices';
                result.innerHTML = '<p style="color:#f44336;">❌ Error: ' + escHtml(err.message) + '</p>';
            });
        });
    }

    // Clear Cache button
    var cacheClearBtn = document.getElementById('oha-bd-cache-clear');
    if (cacheClearBtn) {
        cacheClearBtn.addEventListener('click', function () {
            var label = cacheClearBtn.querySelector('.oha-bd-link-label');
            if (typeof window.ohaPWAClearCache === 'function') {
                window.ohaPWAClearCache();
                if (label) { label.textContent = '✅ Cleared!'; setTimeout(function () { label.textContent = 'Clear My Cache'; }, 3000); }
            } else if ('caches' in window) {
                caches.keys().then(function (names) {
                    return Promise.all(names.map(function (n) { return caches.delete(n); }));
                }).then(function () {
                    if (label) { label.textContent = '✅ Cleared!'; setTimeout(function () { label.textContent = 'Clear My Cache'; }, 3000); }
                });
            }
        });
    }

    // ── Incident Reports ─────────────────────────────────────────────────────
    var DRAFT_KEY = 'oha_incident_draft';
    var repPhotos = []; // Array of {file, dataUrl} for photos selected this session

    // Auto-fill incident time to now when tab is first opened
    function setDefaultIncidentTime() {
        var el = document.getElementById('oha-bd-rep-time');
        if (el && !el.value) {
            var now = new Date();
            now.setSeconds(0, 0);
            el.value = now.toISOString().slice(0, 16);
        }
    }

    // Draft: save all text fields to localStorage on any input change
    function saveDraft() {
        var draft = {
            by:       document.getElementById('oha-bd-rep-by').value,
            time:     document.getElementById('oha-bd-rep-time').value,
            location: document.getElementById('oha-bd-rep-location').value,
            type:     document.getElementById('oha-bd-rep-type').value,
            people:   document.getElementById('oha-bd-rep-people').value,
            desc:     document.getElementById('oha-bd-rep-desc').value,
            action:   document.getElementById('oha-bd-rep-action').value,
            witnesses:document.getElementById('oha-bd-rep-witnesses').value,
        };
        localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
    }

    function restoreDraft() {
        var raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return;
        try {
            var d = JSON.parse(raw);
            var map = {by:'oha-bd-rep-by', time:'oha-bd-rep-time', location:'oha-bd-rep-location',
                       type:'oha-bd-rep-type', people:'oha-bd-rep-people', desc:'oha-bd-rep-desc',
                       action:'oha-bd-rep-action', witnesses:'oha-bd-rep-witnesses'};
            var hasData = false;
            Object.keys(map).forEach(function(k) {
                var el = document.getElementById(map[k]);
                if (el && d[k]) { el.value = d[k]; hasData = true; }
            });
            if (hasData) {
                var notice = document.getElementById('oha-bd-rep-draft-notice');
                if (notice) notice.style.display = '';
            }
        } catch(e) {}
    }

    function clearDraft() {
        localStorage.removeItem(DRAFT_KEY);
        var notice = document.getElementById('oha-bd-rep-draft-notice');
        if (notice) notice.style.display = 'none';
    }

    // Wire up draft autosave on all report form fields
    ['oha-bd-rep-by','oha-bd-rep-time','oha-bd-rep-location','oha-bd-rep-type',
     'oha-bd-rep-people','oha-bd-rep-desc','oha-bd-rep-action','oha-bd-rep-witnesses']
    .forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', saveDraft);
    });

    // Photo compression via Canvas
    function compressPhoto(file, cb) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = new Image();
            img.onload = function() {
                var MAX = 1200;
                var w = img.width, h = img.height;
                if (w > MAX) { h = Math.round(h * MAX / w); w = MAX; }
                var canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                cb(canvas.toDataURL('image/jpeg', 0.80));
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    function renderPhotoPreviews() {
        var container = document.getElementById('oha-bd-rep-photo-preview');
        if (!container) return;
        container.innerHTML = '';
        repPhotos.forEach(function(p, i) {
            var wrap = document.createElement('div');
            wrap.style.textAlign = 'center';
            var img = document.createElement('img');
            img.src = p.dataUrl; img.className = 'oha-bd-photo-thumb';
            var rm = document.createElement('span');
            rm.className = 'oha-bd-photo-remove'; rm.textContent = 'Remove';
            rm.addEventListener('click', function() { repPhotos.splice(i, 1); renderPhotoPreviews(); });
            wrap.appendChild(img); wrap.appendChild(rm);
            container.appendChild(wrap);
        });
    }

    var photoInput = document.getElementById('oha-bd-rep-photos');
    if (photoInput) {
        photoInput.addEventListener('change', function() {
            var files = Array.from(this.files).slice(0, 4 - repPhotos.length);
            var pending = files.length;
            if (!pending) return;
            files.forEach(function(file) {
                compressPhoto(file, function(dataUrl) {
                    repPhotos.push({dataUrl: dataUrl, mime: 'image/jpeg'});
                    pending--;
                    if (pending === 0) renderPhotoPreviews();
                });
            });
            photoInput.value = '';
        });
    }

    // Report form submit
    var repForm = document.getElementById('oha-bd-report-form');
    if (repForm) {
        repForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var token = localStorage.getItem('oha_board_token');
            if (!token) { return; }

            var by       = document.getElementById('oha-bd-rep-by').value.trim();
            var time     = document.getElementById('oha-bd-rep-time').value;
            var location = document.getElementById('oha-bd-rep-location').value.trim();
            var type     = document.getElementById('oha-bd-rep-type').value;
            var desc     = document.getElementById('oha-bd-rep-desc').value.trim();

            if (!by || !time || !location || !type || !desc) {
                document.getElementById('oha-bd-rep-result').innerHTML =
                    '<p style="color:#f44336;">Please fill in all required fields (marked *).</p>';
                return;
            }

            var btn = document.getElementById('oha-bd-rep-submit');
            btn.disabled = true; btn.textContent = 'Submitting...';
            document.getElementById('oha-bd-rep-result').innerHTML =
                '<p style="color:#0073aa;">Saving report...</p>';

            var photos = repPhotos.map(function(p) {
                return { data: p.dataUrl.split(',')[1], mime: p.mime };
            });

            fetch(INC_SAVE, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-WP-Nonce': NONCE},
                body: JSON.stringify({
                    token: token,
                    reported_by:      by,
                    incident_time:    time,
                    location:         location,
                    incident_type:    type,
                    people_involved:  document.getElementById('oha-bd-rep-people').value.trim(),
                    description:      desc,
                    action_taken:     document.getElementById('oha-bd-rep-action').value.trim(),
                    witnesses:        document.getElementById('oha-bd-rep-witnesses').value.trim(),
                    photos:           photos,
                })
            })
            .then(function(r) { return r.ok ? r.json() : r.json().then(function(e){ throw e; }); })
            .then(function(data) {
                btn.disabled = false; btn.textContent = 'Submit Incident Report';
                document.getElementById('oha-bd-rep-result').innerHTML =
                    '<p style="color:#46b450;font-weight:600;">Report #' + data.id + ' saved successfully.</p>';
                clearDraft();
                repPhotos = [];
                renderPhotoPreviews();
                repForm.reset();
                setDefaultIncidentTime();
                // Refresh list
                loadReportsList();
            })
            .catch(function(err) {
                btn.disabled = false; btn.textContent = 'Submit Incident Report';
                document.getElementById('oha-bd-rep-result').innerHTML =
                    '<p style="color:#f44336;">Error: ' + escHtml(err.message || 'Could not save report.') + '</p>';
            });
        });
    }

    // Load reports list
    function loadReportsList() {
        var token = localStorage.getItem('oha_board_token');
        if (!token) return;
        var container = document.getElementById('oha-bd-rep-list-content');
        if (!container) return;
        container.innerHTML = '<p style="color:#aaa;">Loading...</p>';
        fetch(INC_LIST, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-WP-Nonce': NONCE},
            body: JSON.stringify({token: token})
        })
        .then(function(r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function(data) {
            var reports = data.reports || [];
            if (!reports.length) {
                container.innerHTML = '<p style="color:#888;">No reports filed yet.</p>';
                return;
            }
            var html = '';
            reports.forEach(function(r) {
                var dt = r.incident_time ? r.incident_time.replace('T', ' ') : '';
                html += '<div class="oha-bd-report-item" data-id="' + r.id + '">';
                html += '<div class="oha-bd-report-item-header">';
                html += '<div>';
                html += '<span class="oha-bd-report-type-badge">' + escHtml(r.incident_type) + '</span>';
                html += '<div class="oha-bd-report-meta">Report #' + r.id + ' &bull; ' + escHtml(dt) + ' &bull; ' + escHtml(r.location) + '</div>';
                html += '<div class="oha-bd-report-meta" style="margin-top:2px;">Filed by: ' + escHtml(r.reported_by) + '</div>';
                html += '</div>';
                html += '<span style="color:#555;font-size:18px;">&#9660;</span>';
                html += '</div>';
                html += '<div class="oha-bd-report-detail">';
                if (r.people_involved) {
                    html += '<div class="oha-bd-report-field"><strong>People Involved</strong>' + escHtml(r.people_involved) + '</div>';
                }
                html += '<div class="oha-bd-report-field"><strong>Description</strong>' + escHtml(r.description) + '</div>';
                if (r.action_taken) {
                    html += '<div class="oha-bd-report-field"><strong>Action Taken</strong>' + escHtml(r.action_taken) + '</div>';
                }
                if (r.witnesses) {
                    html += '<div class="oha-bd-report-field"><strong>Witnesses</strong>' + escHtml(r.witnesses) + '</div>';
                }
                if (r.photo_urls && r.photo_urls.length) {
                    html += '<div class="oha-bd-report-field"><strong>Photos</strong>';
                    html += '<div class="oha-bd-report-photos">';
                    r.photo_urls.forEach(function(url) {
                        html += '<a href="' + url + '" target="_blank"><img src="' + url + '" alt="incident photo"></a>';
                    });
                    html += '</div></div>';
                }
                html += '</div></div>';
            });
            container.innerHTML = html;
            // Expand/collapse on click
            container.querySelectorAll('.oha-bd-report-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    var detail = item.querySelector('.oha-bd-report-detail');
                    if (detail) detail.classList.toggle('open');
                });
            });
        })
        .catch(function() {
            container.innerHTML = '<p style="color:#f44336;">Could not load reports.</p>';
        });
    }

    var repLoadBtn = document.getElementById('oha-bd-rep-load');
    if (repLoadBtn) { repLoadBtn.addEventListener('click', loadReportsList); }

    // Restore draft and set time when Reports tab becomes active
    document.querySelectorAll('.oha-bd-tab-btn').forEach(function(btn) {
        if (btn.getAttribute('data-tab') === 'reports') {
            btn.addEventListener('click', function() {
                setDefaultIncidentTime();
                restoreDraft();
            });
        }
    });
})();
</script>
        <?php
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_convention_passport]
    // Pre-event passport hub page: profile setup, demo stamp, how-it-works.
    // Requires OHA Passport Hub plugin for full functionality.
    // ─────────────────────────────────────────────────────────────────────
    public function convention_passport( $atts ) {
        $passport_active = $this->passport_hub_active();

        // Countdown to convention
        $convention_start = get_option( 'oha_pwa_convention_start', '2026-05-09 08:00' );
        try {
            $now  = new DateTime( 'now', new DateTimeZone( 'America/New_York' ) );
            $conv = new DateTime( $convention_start, new DateTimeZone( 'America/New_York' ) );
        } catch ( Exception $e ) {
            $now  = new DateTime();
            $conv = new DateTime( '2026-05-09 08:00' );
        }
        $diff            = $now->diff( $conv );
        $days_until      = (int) $diff->days;
        $already_started = ( $now >= $conv );

        // Server-side profile + demo stamp state
        $is_logged_in   = is_user_logged_in();
        $has_profile    = false;
        $has_demo_stamp = false;
        $profile_id     = 0;

        if ( $passport_active && $is_logged_in && class_exists( 'OHA_Passport_Profiles' ) ) {
            $user_id = get_current_user_id();
            $profile = OHA_Passport_Profiles::get_instance()->get_profile_by_user_id( $user_id );
            if ( $profile ) {
                $has_profile = true;
                $profile_id  = (int) $profile['id'];
                global $wpdb;
                $st         = $wpdb->prefix . 'oha_event_stamps';
                $demo_count = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$st} WHERE actor_one_id = %d AND stamp_type = 'demo' AND event_slug = 'pre-event'",
                    $profile_id
                ) );
                $has_demo_stamp = ( $demo_count > 0 );
            }
        }

        $nonce      = $passport_active ? wp_create_nonce( 'oha_passport_hub_nonce' ) : '';
        $ajax_url   = admin_url( 'admin-ajax.php' );
        $_conv_base  = rtrim( get_option( 'oha_convention_base_url', '/convention' ), '/' );
        $scanner_url = get_option( 'oha_pwa_scanner_url', $_conv_base . '/passport/' );

        ob_start();
        ?>
<div class="oha-conv-passport">
<style>
.oha-conv-passport{max-width:640px;margin:0 auto;padding:0 0 80px;color:#e0e0e0;font-family:inherit}
.oha-cp-hero{background:linear-gradient(135deg,#1a1a1a 0%,#2d1a10 100%);border:1px solid #3a2a20;border-radius:14px;padding:28px 24px 22px;margin-bottom:20px;text-align:center}
.oha-cp-hero-icon{font-size:48px;line-height:1;display:block;margin-bottom:10px}
.oha-cp-hero h2{margin:0 0 6px;font-size:1.5rem;color:#fff}
.oha-cp-tagline{color:#aaa;font-size:.9rem;margin:0 0 14px}
.oha-cp-countdown{display:inline-flex;align-items:center;gap:8px;background:rgba(173,49,25,.2);border:1px solid rgba(173,49,25,.4);border-radius:20px;padding:6px 16px;font-size:.95rem;color:#ff7043;font-weight:600}
.oha-cp-notice{background:#2a2a2a;border:1px solid #444;border-radius:12px;padding:20px;margin-bottom:20px;text-align:center;color:#aaa}
.oha-cp-checklist{background:#2a2a2a;border:1px solid #3a3a3a;border-radius:12px;padding:16px 20px;margin-bottom:20px}
.oha-cp-checklist h3{margin:0 0 12px;font-size:.85rem;text-transform:uppercase;letter-spacing:.08em;color:#888}
.oha-cp-check-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #333}
.oha-cp-check-item:last-child{border-bottom:none}
.oha-cp-check-icon{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.9rem}
.oha-cp-check-icon.done{background:#1b5e20;color:#66bb6a}
.oha-cp-check-icon.todo{background:#333;color:#888}
.oha-cp-check-text{flex:1}
.oha-cp-check-text strong{display:block;color:#e0e0e0;font-size:.95rem}
.oha-cp-check-text span{color:#888;font-size:.82rem}
.oha-cp-section{margin-bottom:20px}
.oha-cp-section-title{font-size:.8rem;text-transform:uppercase;letter-spacing:.1em;color:#666;margin-bottom:10px;padding-left:4px}
.oha-cp-demo-card{background:linear-gradient(135deg,#1e1208 0%,#2d1a00 100%);border:2px solid rgba(255,112,0,.4);border-radius:14px;padding:24px 20px;text-align:center;margin-bottom:20px}
.oha-cp-demo-card h3{margin:0 0 6px;font-size:1.1rem;color:#fff}
.oha-cp-demo-card p{color:#aaa;font-size:.88rem;margin:0 0 18px}
.oha-cp-demo-btn{display:inline-flex;align-items:center;gap:8px;background:#AD3119;color:#fff;border:none;border-radius:10px;padding:12px 24px;font-size:1rem;font-weight:700;cursor:pointer;transition:background .2s}
.oha-cp-demo-btn:hover{background:#c94020}
.oha-cp-demo-btn:disabled{background:#555;cursor:not-allowed}
.oha-cp-stamp-reveal{display:none}
.oha-cp-stamp-reveal.visible{display:block;animation:oha-stamp-pop .5s cubic-bezier(.175,.885,.32,1.275)}
@keyframes oha-stamp-pop{0%{transform:scale(.3) rotate(-15deg);opacity:0}80%{transform:scale(1.1) rotate(3deg)}100%{transform:scale(1) rotate(0);opacity:1}}
.oha-cp-stamp-badge{background:linear-gradient(135deg,#1b5e20,#2e7d32);border:3px solid #66bb6a;border-radius:50%;width:90px;height:90px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:2.2rem}
.oha-cp-stamp-label{color:#66bb6a;font-weight:700;font-size:1rem}
.oha-cp-stamp-sub{color:#888;font-size:.82rem;margin-top:4px}
.oha-cp-demo-result{margin-top:12px;font-size:.88rem}
.oha-cp-how{background:#222;border:1px solid #333;border-radius:12px;padding:20px;margin-bottom:20px}
.oha-cp-how h3{margin:0 0 14px;font-size:1rem;color:#fff}
.oha-cp-how-steps{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px}
.oha-cp-how-steps li{display:flex;align-items:flex-start;gap:12px}
.oha-cp-how-num{background:#AD3119;color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0;margin-top:1px}
.oha-cp-how-steps p{margin:0;color:#ccc;font-size:.88rem}
.oha-cp-how-steps strong{color:#fff}
.oha-cp-login-prompt{background:#2a2a2a;border:1px solid #3a3a3a;border-radius:12px;padding:20px;margin-bottom:20px;text-align:center}
.oha-cp-login-prompt p{color:#aaa;margin:0 0 12px}
.oha-cp-login-btn{display:inline-block;background:#AD3119;color:#fff;border-radius:8px;padding:10px 24px;font-weight:700;text-decoration:none}
.oha-cp-scan-btn{display:inline-flex;align-items:center;gap:8px;background:#e65c00;color:#fff;border-radius:10px;padding:12px 24px;font-size:1rem;font-weight:700;text-decoration:none;margin-top:14px;transition:background .2s}
.oha-cp-scan-btn:hover{background:#c94e00;color:#fff;text-decoration:none}
</style>

<?php /* Hero ────────────────────────────────────────────── */ ?>
<div class="oha-cp-hero">
    <span class="oha-cp-hero-icon">&#128203;</span>
    <h2>Haunt Passport</h2>
    <p class="oha-cp-tagline">Trade badges, collect stamps, meet fellow haunters</p>
    <?php if ( ! $already_started ) : ?>
    <div class="oha-cp-countdown">
        &#128197; <?php echo esc_html( $days_until ); ?> day<?php echo 1 !== $days_until ? 's' : ''; ?> until the convention
    </div>
    <?php else : ?>
    <div class="oha-cp-countdown">Convention is happening now!</div>
    <?php endif; ?>
    <?php if ( $is_logged_in && $has_profile ) : ?>
    <a href="<?php echo esc_url( $scanner_url ); ?>" class="oha-cp-scan-btn">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true"><path d="M3 11V3h8v8H3zm2-2h4V5H5v4zM3 21v-8h8v8H3zm2-2h4v-4H5v4zm8-8V3h8v8h-8zm2-2h4V5h-4v4zM19 19v-2h2v2h-2zm-6-6v-2h2v2h-2zm2 2v-2h2v2h-2zm-2 2v-2h2v2h-2zm2 2v-2h2v2h-2zm2-2v-2h2v2h-2zM17 17v-2h2v2h-2zm2-4v-2h2v2h-2z"/></svg>
        Scan QR Codes
    </a>
    <?php endif; ?>
</div>

<?php if ( ! $passport_active ) : ?>
<div class="oha-cp-notice">
    <p><strong>The Passport Hub plugin is not currently active.</strong></p>
    <p>Contact your site administrator to enable the OHA Passport Hub plugin.</p>
</div>

<?php elseif ( ! $is_logged_in ) : ?>
<div class="oha-cp-login-prompt">
    <p>&#128274; Log in to create your Haunt Passport and start collecting stamps!</p>
    <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="oha-cp-login-btn">Log In to OHA</a>
</div>
<div class="oha-cp-how">
    <h3>&#129300; How Passport Trading Works</h3>
    <ul class="oha-cp-how-steps">
        <li><span class="oha-cp-how-num">1</span><p><strong>Create your profile</strong> &#8212; choose your character name and haunt</p></li>
        <li><span class="oha-cp-how-num">2</span><p><strong>Get your QR badge</strong> &#8212; every actor gets a unique scannable code</p></li>
        <li><span class="oha-cp-how-num">3</span><p><strong>Scan someone at the event</strong> &#8212; use the QR Scanner to trade stamps</p></li>
        <li><span class="oha-cp-how-num">4</span><p><strong>Collect all weekend</strong> &#8212; climb the leaderboard and win recognition</p></li>
    </ul>
</div>

<?php else : ?>
<?php /* Logged in + Passport Hub active ─────────────────── */ ?>
<div class="oha-cp-checklist">
    <h3>Pre-Event Setup</h3>
    <div class="oha-cp-check-item">
        <div class="oha-cp-check-icon <?php echo $has_profile ? 'done' : 'todo'; ?>">
            <?php echo $has_profile ? '&#10003;' : '1'; ?>
        </div>
        <div class="oha-cp-check-text">
            <strong>Create Your Passport Profile</strong>
            <span><?php echo $has_profile ? 'Done! Your passport is ready.' : 'Set your character name, haunt, and photo.'; ?></span>
        </div>
    </div>
    <div class="oha-cp-check-item" id="oha-cp-check-demo">
        <div class="oha-cp-check-icon <?php echo $has_demo_stamp ? 'done' : 'todo'; ?>" id="oha-cp-demo-icon">
            <?php echo $has_demo_stamp ? '&#10003;' : '2'; ?>
        </div>
        <div class="oha-cp-check-text">
            <strong>Claim Your OHA Beginner Stamp</strong>
            <span id="oha-cp-demo-check-text"><?php echo $has_demo_stamp ? 'You earned your first stamp!' : 'Try a demo trade &#8212; learn how badge trading works.'; ?></span>
        </div>
    </div>
</div>

<div class="oha-cp-section">
    <div class="oha-cp-section-title">Your Passport</div>
    <?php echo do_shortcode( '[oha_actor_passport]' ); ?>
</div>

<?php if ( $has_profile && ! $has_demo_stamp ) : ?>
<div class="oha-cp-demo-card" id="oha-cp-demo-card">
    <h3>&#127942; Claim Your OHA Beginner Stamp</h3>
    <p>Experience a stamp trade right now &#8212; no other attendee needed!<br>
    This earns you a real stamp on your passport.</p>
    <button class="oha-cp-demo-btn" id="oha-cp-demo-btn">&#127942; Simulate a Stamp Trade</button>
    <div class="oha-cp-stamp-reveal" id="oha-cp-stamp-reveal">
        <div class="oha-cp-stamp-badge">&#127942;</div>
        <div class="oha-cp-stamp-label">OHA Beginner</div>
        <div class="oha-cp-stamp-sub">Your first stamp! Keep collecting at the convention.</div>
    </div>
    <div class="oha-cp-demo-result" id="oha-cp-demo-result"></div>
</div>
<?php elseif ( $has_demo_stamp ) : ?>
<div class="oha-cp-demo-card" style="border-color:rgba(102,187,106,.4);">
    <div class="oha-cp-stamp-badge">&#127942;</div>
    <div class="oha-cp-stamp-label">OHA Beginner stamp collected!</div>
    <div class="oha-cp-stamp-sub" style="margin-top:6px;">Ready to collect more at the convention.</div>
</div>
<?php endif; ?>

<div class="oha-cp-how">
    <h3>&#129300; How Passport Trading Works</h3>
    <ul class="oha-cp-how-steps">
        <li><span class="oha-cp-how-num">1</span><p><strong>Show your badge</strong> &#8212; your QR code passport is always in this app</p></li>
        <li><span class="oha-cp-how-num">2</span><p><strong>Find someone at the event</strong> &#8212; a haunter, a vendor, a staff member</p></li>
        <li><span class="oha-cp-how-num">3</span><p><strong>Scan their badge</strong> - use the Scan button in the app nav to open the QR scanner</p></li>
        <li><span class="oha-cp-how-num">4</span><p><strong>Both actors get a stamp</strong> &#8212; both passports record the trade instantly</p></li>
    </ul>
</div>

<div class="oha-cp-section">
    <div class="oha-cp-section-title">My Stamp Collection</div>
    <?php echo do_shortcode( '[oha_my_stamps]' ); ?>
</div>

<script>
(function() {
    var btn = document.getElementById('oha-cp-demo-btn');
    if (!btn) return;
    var AJAX  = <?php echo json_encode( esc_url_raw( $ajax_url ) ); ?>;
    var NONCE = <?php echo json_encode( $nonce ); ?>;
    btn.addEventListener('click', function() {
        btn.disabled = true;
        btn.innerHTML = '&#9203; Claiming&hellip;';
        var result = document.getElementById('oha-cp-demo-result');
        var fd = new FormData();
        fd.append('action', 'oha_award_demo_stamp');
        fd.append('nonce', NONCE);
        fetch(AJAX, {method:'POST', body:fd})
            .then(function(r){return r.json();})
            .then(function(d) {
                if (d.success) {
                    btn.style.display = 'none';
                    var reveal = document.getElementById('oha-cp-stamp-reveal');
                    if (reveal) reveal.classList.add('visible');
                    var icon = document.getElementById('oha-cp-demo-icon');
                    if (icon) { icon.className = 'oha-cp-check-icon done'; icon.innerHTML = '&#10003;'; }
                    var txt = document.getElementById('oha-cp-demo-check-text');
                    if (txt) txt.textContent = 'You earned your first stamp!';
                } else {
                    var msg = d.data || 'Something went wrong.';
                    if (msg === 'demo_already_claimed') {
                        result.innerHTML = '<span style="color:#66bb6a;">&#10003; You already have this stamp!</span>';
                        btn.style.display = 'none';
                    } else if (msg === 'Create your Passport profile first') {
                        result.innerHTML = '<span style="color:#f44336;">Please create your Passport profile first, then come back.</span>';
                        btn.disabled = false;
                        btn.innerHTML = '&#127942; Simulate a Stamp Trade';
                    } else {
                        result.innerHTML = '<span style="color:#f44336;">Error: ' + msg + '</span>';
                        btn.disabled = false;
                        btn.innerHTML = '&#127942; Simulate a Stamp Trade';
                    }
                }
            })
            .catch(function(err) {
                result.innerHTML = '<span style="color:#f44336;">Network error: ' + err.message + '</span>';
                btn.disabled = false;
                btn.innerHTML = '&#127942; Simulate a Stamp Trade';
            });
    });
})();
</script>
<?php endif; // end logged-in check ?>
</div>
        <?php
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_pwa_settings]
    // User-facing settings page: restore dismissed content, clear saved data,
    // view app info. All actions operate on localStorage — no AJAX needed.
    // Usage: add [oha_pwa_settings] to any page (e.g. the Info page).
    // ─────────────────────────────────────────────────────────────────────
    public function pwa_settings( $atts ) {
        $version = defined( 'OHA_PWA_VERSION' ) ? OHA_PWA_VERSION : '';

        ob_start(); ?>
        <div id="oha-pwa-settings" class="oha-pwa-settings-wrap">
        <style>
        .oha-pwa-settings-wrap{max-width:560px;margin:0 auto;padding:8px 0 32px;font-family:inherit;color:#eee}
        .oha-pwa-settings-section{background:#111;border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:20px;margin-bottom:16px}
        .oha-pwa-settings-section h3{margin:0 0 4px;font-size:1rem;font-weight:700;color:#fff;letter-spacing:.3px}
        .oha-pwa-settings-section p.oha-settings-desc{margin:0 0 16px;font-size:13px;color:rgba(255,255,255,.55);line-height:1.5}
        .oha-settings-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-top:1px solid rgba(255,255,255,.07)}
        .oha-settings-row:first-of-type{border-top:none;padding-top:0}
        .oha-settings-label{font-size:14px;font-weight:600;color:#ddd;flex:1}
        .oha-settings-label small{display:block;font-weight:400;font-size:12px;color:rgba(255,255,255,.45);margin-top:2px}
        .oha-settings-btn{background:#1e1e2e;border:1px solid rgba(173,49,25,.5);color:#f9a8a8;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s,border-color .15s;white-space:nowrap;flex-shrink:0}
        .oha-settings-btn:hover{background:#2a1010;border-color:#AD3119}
        .oha-settings-btn:disabled{opacity:.4;cursor:not-allowed}
        .oha-settings-btn.done{background:#0d1f0d;border-color:#4caf50;color:#81c784}
        .oha-settings-btn-danger{border-color:rgba(244,67,54,.5);color:#ff8a80}
        .oha-settings-btn-danger:hover{background:#1a0505;border-color:#f44336}
        .oha-settings-info-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-top:1px solid rgba(255,255,255,.07);font-size:13px}
        .oha-settings-info-row:first-child{border-top:none;padding-top:0}
        .oha-settings-info-key{color:rgba(255,255,255,.5)}
        .oha-settings-info-val{font-weight:600;color:#eee}
        #oha-settings-connection-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#4caf50;margin-right:6px;flex-shrink:0}
        #oha-settings-connection-dot.offline{background:#f44336}
        .oha-settings-notif-hint{margin-top:10px;padding:10px 12px;background:#1a0e0e;border:1px solid rgba(244,67,54,.3);border-radius:8px;font-size:12px;color:rgba(255,255,255,.65);line-height:1.5;display:none}
        </style>

        <div class="oha-pwa-settings-section">
            <h3>App Information</h3>
            <div class="oha-settings-info-row">
                <span class="oha-settings-info-key">App Version</span>
                <span class="oha-settings-info-val"><?php echo esc_html( $version ); ?></span>
            </div>
            <div class="oha-settings-info-row">
                <span class="oha-settings-info-key">Connection</span>
                <span class="oha-settings-info-val" id="oha-settings-connection">
                    <span id="oha-settings-connection-dot"></span>
                    <span id="oha-settings-connection-text">Checking...</span>
                </span>
            </div>
            <div class="oha-settings-info-row">
                <span class="oha-settings-info-key">Mode</span>
                <span class="oha-settings-info-val" id="oha-settings-pwa-mode">Checking...</span>
            </div>
            <div class="oha-settings-info-row">
                <span class="oha-settings-info-key">Notifications</span>
                <span class="oha-settings-info-val" id="oha-settings-notif-status">Checking...</span>
            </div>
            <div class="oha-settings-notif-hint" id="oha-settings-notif-hint">
                Notifications are blocked. To fix this: open your browser or phone settings, find this website, and change the notification permission to Allow. Then close and reopen the app.
            </div>
        </div>

        <div class="oha-pwa-settings-section">
            <h3>Restore Dismissed Content</h3>
            <p class="oha-settings-desc">Restore sections you previously dismissed from the app.</p>
            <div class="oha-settings-row">
                <div class="oha-settings-label">
                    Home Page Setup Guide
                    <small id="oha-onboarding-status">Checking...</small>
                </div>
                <button class="oha-settings-btn" id="oha-restore-onboarding" type="button">Restore</button>
            </div>
            <div class="oha-settings-row">
                <div class="oha-settings-label">
                    Happenings Feed
                    <small id="oha-happenings-status">Checking...</small>
                </div>
                <button class="oha-settings-btn" id="oha-restore-happenings" type="button">Restore</button>
            </div>
            <div class="oha-settings-row">
                <div class="oha-settings-label">
                    Convention Map Tutorial
                    <small id="oha-tutorial-status">Checking...</small>
                </div>
                <button class="oha-settings-btn" id="oha-restore-tutorial" type="button">Restore</button>
            </div>
        </div>

        <div class="oha-pwa-settings-section">
            <h3>Clear Saved Data</h3>
            <p class="oha-settings-desc">Remove locally saved preferences. Individual clears cannot be undone.</p>
            <div class="oha-settings-row">
                <div class="oha-settings-label">
                    Saved Schedule Items
                    <small>Clears your starred classes from My OHC</small>
                </div>
                <button class="oha-settings-btn" id="oha-clear-favorites" type="button">Clear</button>
            </div>
            <div class="oha-settings-row">
                <div class="oha-settings-label">
                    Saved Vendors
                    <small>Clears your saved vendor list</small>
                </div>
                <button class="oha-settings-btn" id="oha-clear-vendor-favorites" type="button">Clear</button>
            </div>
            <div class="oha-settings-row">
                <div class="oha-settings-label">
                    Reset All App Data
                    <small>Clears all saved data and signs out of volunteer and board areas</small>
                </div>
                <button class="oha-settings-btn oha-settings-btn-danger" id="oha-clear-all" type="button">Reset All</button>
            </div>
        </div>

        <div class="oha-pwa-settings-section">
            <h3>Text Size</h3>
            <p class="oha-settings-desc">Increase text size across the app for easier reading.</p>
            <div class="oha-settings-row" style="flex-wrap:wrap;gap:8px;">
                <div class="oha-settings-label" style="flex-basis:100%;margin-bottom:2px;">
                    Size
                    <small id="oha-text-size-label">Normal</small>
                </div>
                <div style="display:flex;gap:8px;">
                    <button class="oha-settings-btn oha-text-size-btn" data-size="normal"  type="button" style="min-width:64px;">Normal</button>
                    <button class="oha-settings-btn oha-text-size-btn" data-size="large"   type="button" style="min-width:64px;">Large</button>
                    <button class="oha-settings-btn oha-text-size-btn" data-size="largest" type="button" style="min-width:64px;">Largest</button>
                </div>
            </div>
        </div>

        <div class="oha-pwa-settings-section">
            <h3>App Updates</h3>
            <div class="oha-settings-row">
                <div class="oha-settings-label">
                    Refresh Convention Data
                    <small id="oha-refresh-cache-status">Re-download map booths, schedule, and app pages</small>
                </div>
                <button class="oha-settings-btn" id="oha-refresh-cache" type="button">Refresh</button>
            </div>
            <div class="oha-settings-row">
                <div class="oha-settings-label">
                    Check for Updates
                    <small id="oha-update-status">Tap to check for a newer version</small>
                </div>
                <button class="oha-settings-btn" id="oha-check-updates" type="button">Check</button>
            </div>
        </div>

        </div><!-- .oha-pwa-settings-wrap -->

        <script>
        (function () {
            // ── Connection status ────────────────────────────────────────
            var dot  = document.getElementById('oha-settings-connection-dot');
            var txt  = document.getElementById('oha-settings-connection-text');
            var mode = document.getElementById('oha-settings-pwa-mode');

            function updateConnection() {
                var online = navigator.onLine;
                if (dot) { dot.className = online ? '' : 'offline'; }
                if (txt) { txt.textContent = online ? 'Online' : 'Offline'; }
            }
            updateConnection();
            window.addEventListener('online',  updateConnection);
            window.addEventListener('offline', updateConnection);

            if (mode) {
                var isPWA = window.matchMedia('(display-mode: standalone)').matches ||
                            window.navigator.standalone === true ||
                            window.matchMedia('(display-mode: fullscreen)').matches;
                mode.textContent = isPWA ? 'Installed App (PWA)' : 'Browser';
            }

            // ── Notification permission status ────────────────────────────
            var notifEl   = document.getElementById('oha-settings-notif-status');
            var notifHint = document.getElementById('oha-settings-notif-hint');
            if (notifEl) {
                if (!('Notification' in window)) {
                    notifEl.textContent = 'Not supported';
                    notifEl.style.color = 'rgba(255,255,255,0.4)';
                } else {
                    var perm = Notification.permission;
                    if (perm === 'granted') {
                        notifEl.textContent = 'Allowed';
                        notifEl.style.color = '#81c784';
                    } else if (perm === 'denied') {
                        notifEl.textContent = 'Blocked';
                        notifEl.style.color = '#f44336';
                        if (notifHint) { notifHint.style.display = 'block'; }
                    } else {
                        notifEl.textContent = 'Not set up';
                        notifEl.style.color = '#ffb74d';
                    }
                }
            }

            // ── Restore indicators ────────────────────────────────────────
            function updateRestoreIndicators() {
                var obDismissed = localStorage.getItem('oha_ob_install_done') || localStorage.getItem('oha_ob_notif_done');
                var obStatus = document.getElementById('oha-onboarding-status');
                var btnOb    = document.getElementById('oha-restore-onboarding');
                if (obStatus) { obStatus.textContent = obDismissed ? 'Currently hidden' : 'Currently showing'; }
                if (btnOb)    { btnOb.disabled = !obDismissed; }

                var hapDismissed = localStorage.getItem('oha_happenings_hidden_v1') === '1';
                var hapStatus = document.getElementById('oha-happenings-status');
                var btnHap    = document.getElementById('oha-restore-happenings');
                if (hapStatus) { hapStatus.textContent = hapDismissed ? 'Currently hidden' : 'Currently showing'; }
                if (btnHap)    { btnHap.disabled = !hapDismissed; }

                var tutSeen   = !!localStorage.getItem('oha_conv_map_tutorial_v1');
                var tutStatus = document.getElementById('oha-tutorial-status');
                var btnTut    = document.getElementById('oha-restore-tutorial');
                if (tutStatus) { tutStatus.textContent = tutSeen ? 'Already completed' : 'Not yet shown'; }
                if (btnTut)    { btnTut.disabled = !tutSeen; }
            }
            updateRestoreIndicators();

            // ── Helper: flash button done then reset ──────────────────────
            function flashDone(btn, resetLabel, callback) {
                btn.disabled    = true;
                btn.className   = 'oha-settings-btn done';
                btn.textContent = 'Done';
                setTimeout(function () {
                    btn.disabled    = false;
                    btn.className   = 'oha-settings-btn';
                    btn.textContent = resetLabel;
                    if (callback) { callback(); }
                }, 2000);
            }

            // ── Restore onboarding ────────────────────────────────────────
            var btnOnboarding = document.getElementById('oha-restore-onboarding');
            if (btnOnboarding) {
                btnOnboarding.addEventListener('click', function () {
                    localStorage.removeItem('oha_ob_install_done');
                    localStorage.removeItem('oha_ob_notif_done');
                    flashDone(btnOnboarding, 'Restore', updateRestoreIndicators);
                });
            }

            // ── Restore happenings ────────────────────────────────────────
            var btnHappenings = document.getElementById('oha-restore-happenings');
            if (btnHappenings) {
                btnHappenings.addEventListener('click', function () {
                    localStorage.removeItem('oha_happenings_hidden_v1');
                    flashDone(btnHappenings, 'Restore', updateRestoreIndicators);
                });
            }

            // ── Restore convention map tutorial ───────────────────────────
            var btnTutorial = document.getElementById('oha-restore-tutorial');
            if (btnTutorial) {
                btnTutorial.addEventListener('click', function () {
                    localStorage.removeItem('oha_conv_map_tutorial_v1');
                    flashDone(btnTutorial, 'Restore', updateRestoreIndicators);
                });
            }

            // ── Clear schedule favorites ──────────────────────────────────
            var btnFav = document.getElementById('oha-clear-favorites');
            if (btnFav) {
                btnFav.addEventListener('click', function () {
                    if (!confirm('Clear all saved schedule items?')) { return; }
                    localStorage.removeItem('oha_favorites');
                    flashDone(btnFav, 'Clear');
                });
            }

            // ── Clear vendor favorites ────────────────────────────────────
            var btnVendorFav = document.getElementById('oha-clear-vendor-favorites');
            if (btnVendorFav) {
                btnVendorFav.addEventListener('click', function () {
                    if (!confirm('Clear all saved vendors?')) { return; }
                    localStorage.removeItem('oha_favorite_vendors');
                    flashDone(btnVendorFav, 'Clear');
                });
            }

            // ── Reset all app data ────────────────────────────────────────
            var btnClearAll = document.getElementById('oha-clear-all');
            if (btnClearAll) {
                btnClearAll.addEventListener('click', function () {
                    if (!confirm('This will clear ALL saved data including your schedule, saved vendors, volunteer access, and board login. The page will reload. Continue?')) { return; }
                    Object.keys(localStorage).filter(function (k) {
                        return k.indexOf('oha_') === 0;
                    }).forEach(function (k) { localStorage.removeItem(k); });
                    window.location.reload();
                });
            }

            // ── Text size ─────────────────────────────────────────────────
            var textSizeBtns  = document.querySelectorAll('.oha-text-size-btn');
            var textSizeLabel = document.getElementById('oha-text-size-label');
            var sizeNames     = { normal: 'Normal', large: 'Large', largest: 'Largest' };

            function applyTextSize(size) {
                document.body.classList.remove('oha-text-large', 'oha-text-largest');
                if (size === 'large')   { document.body.classList.add('oha-text-large'); }
                if (size === 'largest') { document.body.classList.add('oha-text-largest'); }
                if (textSizeLabel) { textSizeLabel.textContent = sizeNames[size] || 'Normal'; }
                textSizeBtns.forEach(function (btn) {
                    var active = btn.getAttribute('data-size') === size;
                    btn.style.background     = active ? 'rgba(173,49,25,.75)' : '';
                    btn.style.borderColor    = active ? '#AD3119' : '';
                    btn.style.color          = active ? '#fff' : '';
                });
            }

            var currentTextSize = 'normal';
            try { currentTextSize = localStorage.getItem('oha_text_size') || 'normal'; } catch (e) {}
            applyTextSize(currentTextSize);

            textSizeBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var size = this.getAttribute('data-size');
                    try { localStorage.setItem('oha_text_size', size); } catch (e) {}
                    applyTextSize(size);
                });
            });

            // ── Refresh convention data (clear SW caches + reload) ────────
            var btnRefreshCache  = document.getElementById('oha-refresh-cache');
            var refreshCacheStatus = document.getElementById('oha-refresh-cache-status');
            if (btnRefreshCache) {
                btnRefreshCache.addEventListener('click', function () {
                    if (!navigator.serviceWorker || !navigator.serviceWorker.controller) {
                        if (refreshCacheStatus) { refreshCacheStatus.textContent = 'App is not running as PWA - open from your home screen.'; }
                        return;
                    }
                    if (!confirm('This will clear cached convention data so it can be re-downloaded fresh. You will need a connection after reload. Continue?')) { return; }
                    btnRefreshCache.disabled    = true;
                    btnRefreshCache.textContent = 'Clearing...';
                    navigator.serviceWorker.controller.postMessage({ action: 'clearCache' });
                    // Small delay lets the SW finish deleting before the reload triggers new fetches
                    setTimeout(function () { window.location.reload(); }, 1200);
                });
            }

            // ── Check for updates ─────────────────────────────────────────
            var btnUpdate   = document.getElementById('oha-check-updates');
            var updateStatus = document.getElementById('oha-update-status');
            if (btnUpdate) {
                btnUpdate.addEventListener('click', function () {
                    if (!('serviceWorker' in navigator)) {
                        if (updateStatus) { updateStatus.textContent = 'Not available in this browser.'; }
                        return;
                    }
                    btnUpdate.disabled    = true;
                    btnUpdate.textContent = 'Checking...';
                    if (updateStatus) { updateStatus.textContent = 'Looking for updates...'; }

                    navigator.serviceWorker.getRegistration().then(function (reg) {
                        if (!reg) {
                            if (updateStatus) { updateStatus.textContent = 'App not installed as PWA.'; }
                            btnUpdate.disabled    = false;
                            btnUpdate.textContent = 'Check';
                            return;
                        }
                        var updateFound = false;
                        reg.addEventListener('updatefound', function () {
                            updateFound = true;
                            var newSW = reg.installing;
                            if (!newSW) { return; }
                            newSW.addEventListener('statechange', function () {
                                if (newSW.state === 'installed' && navigator.serviceWorker.controller) {
                                    if (updateStatus) { updateStatus.textContent = 'Update ready - tap Reload to apply.'; }
                                    btnUpdate.disabled    = false;
                                    btnUpdate.textContent = 'Reload';
                                    btnUpdate.addEventListener('click', function () { window.location.reload(); }, { once: true });
                                }
                            });
                        });
                        reg.update().catch(function () {});
                        setTimeout(function () {
                            if (!updateFound) {
                                if (updateStatus) { updateStatus.textContent = 'You are on the latest version.'; }
                                btnUpdate.disabled    = false;
                                btnUpdate.textContent = 'Check';
                            }
                        }, 4000);
                    });
                });
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

}

function oha_get_shortcodes() {
    return OHA_PWA_Shortcodes::getInstance();
}
