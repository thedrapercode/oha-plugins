<?php
/**
 * OHA Passport Hub — Shortcodes
 *
 * All passport shortcodes. Work on any page, any WordPress site.
 * No PWA or convention dependency required.
 *
 * Shortcodes:
 *   [oha_actor_passport show_qr="true" show_stats="true" compact="false"]
 *   [oha_qr_scanner auto_start="false" show_instructions="true"]
 *   [oha_vendor_scanner event="" show_instructions="true"]
 *   [oha_scavenger_hunt event="" show_leaderboard="false"]
 *   [oha_url_scanner]
 *   [oha_smart_scanner mode="smart" show_instructions="true"]
 *   [oha_my_stamps show_stats="true" show_recent="true" limit="20"]
 *   [oha_stamps_leaderboard limit="20" show_haunts="true" event=""]
 *   [oha_event_summary event="oha-convention-2026" show_haunts="true"]
 *
 * @package OHA_Passport_Hub
 * @version 1.2.0
 */

defined( 'ABSPATH' ) || exit;

class OHA_Passport_Shortcodes {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_shortcode( 'oha_actor_passport',    [ $this, 'display_actor_passport' ] );
        add_shortcode( 'oha_qr_scanner',        [ $this, 'display_qr_scanner' ] );
        add_shortcode( 'oha_vendor_scanner',    [ $this, 'display_vendor_scanner' ] );
        add_shortcode( 'oha_scavenger_hunt',    [ $this, 'display_scavenger_hunt' ] );
        add_shortcode( 'oha_url_scanner',       [ $this, 'display_url_scanner' ] );
        add_shortcode( 'oha_smart_scanner',     [ $this, 'display_smart_scanner' ] );
        add_shortcode( 'oha_my_stamps',         [ $this, 'display_my_stamps' ] );
        add_shortcode( 'oha_stamps_leaderboard',[ $this, 'display_leaderboard' ] );
        add_shortcode( 'oha_event_summary',     [ $this, 'display_event_summary' ] );
        add_shortcode( 'oha_my_badges',         [ $this, 'display_my_badges' ] );
        add_shortcode( 'oha_passport_progress', [ $this, 'display_passport_progress' ] );
        add_shortcode( 'oha_connections',       [ $this, 'display_connections' ] );
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_actor_passport]
    // ─────────────────────────────────────────────────────────────────────
    public function display_actor_passport( $atts = [] ) {
        $atts = shortcode_atts( [
            'show_qr'    => 'true',
            'show_stats' => 'true',
            'compact'    => 'false',
            'event'      => '',
        ], $atts );

        if ( ! is_user_logged_in() ) {
            return '<div class="oha-ph-login-prompt"><p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to create your actor passport.</p></div>';
        }

        ob_start(); ?>
        <div id="oha-actor-passport" class="oha-ph-container oha-passport-container" data-compact="<?php echo esc_attr( $atts['compact'] ); ?>">

            <div class="oha-passport-header">
                <h3>🎭 Actor Passport</h3>
                <p>Your character profile for OHA events</p>
            </div>

            <div id="passport-card" class="oha-passport-card">
                <div class="oha-ph-loading"><div class="oha-ph-spinner"></div><p>Loading passport…</p></div>
            </div>

            <div id="passport-form" class="oha-passport-form" style="display:none;">
                <h4>Character Profile</h4>
                <form id="actor-profile-form">
                    <div class="form-row">
                        <label for="character_name">Character Name *</label>
                        <input type="text" id="character_name" name="character_name" maxlength="100" required>
                        <small>Your character's name — not your real name</small>
                    </div>
                    <div class="form-row">
                        <label for="character_type">Character Type</label>
                        <select id="character_type" name="character_type">
                            <option value="">Select type…</option>
                            <?php foreach ( OHA_Passport_Profiles::get_character_types() as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label for="haunt_name">Haunt I Work At</label>
                        <input type="text" id="haunt_name" name="haunt_name" maxlength="150" placeholder="e.g., Dent Schoolhouse">
                        <small>Optional — free text, any haunt name</small>
                    </div>
                    <div class="form-row">
                        <label for="character_bio">Character Bio</label>
                        <textarea id="character_bio" name="character_bio" maxlength="500" rows="4" placeholder="Tell us about your character…"></textarea>
                        <small><span id="bio-count">0</span>/500 characters</small>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="oha-btn-primary">
                            <span class="btn-text">Save Profile</span>
                            <span class="btn-loading" style="display:none;">Saving…</span>
                        </button>
                        <button type="button" id="cancel-edit" class="oha-btn-secondary">Cancel</button>
                    </div>
                </form>
            </div>

            <?php if ( $atts['show_qr'] === 'true' ) : ?>
            <div id="qr-code-section" class="oha-qr-section" style="display:none;">
                <h4>📱 Your QR Code</h4>
                <div id="qr-display"></div>
                <p><small>Share this QR code to trade badges at OHA events!</small></p>
                <button id="refresh-qr" class="oha-btn-secondary">Refresh QR</button>
            </div>
            <?php endif; ?>

            <?php if ( $atts['show_stats'] === 'true' ) : ?>
            <div id="passport-stats" class="oha-passport-stats" style="display:none;"></div>
            <?php endif; ?>
        </div>
        <div class="oha-ph-disclaimer">
            <p><small>📢 <strong>Note:</strong> Actor passport content is user-controlled. OHA does not monitor or endorse profile content. Users are responsible for their own information.</small></p>
        </div>
        <?php return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_qr_scanner]
    // ─────────────────────────────────────────────────────────────────────
    public function display_qr_scanner( $atts = [] ) {
        $atts = shortcode_atts( [
            'auto_start'        => 'false',
            'show_instructions' => 'true',
            'event'             => '',
            'passport_url'      => get_option( 'oha_pwa_passport_url', rtrim( get_option( 'oha_convention_base_url', '/convention' ), '/' ) . '/passport/' ),
        ], $atts );

        if ( ! is_user_logged_in() ) {
            return '<div class="oha-ph-login-prompt"><p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to scan QR codes.</p></div>';
        }

        ob_start(); ?>
        <div id="oha-qr-scanner" class="oha-ph-container oha-scanner-container">

            <div class="oha-scanner-header">
                <h3>📱 QR Badge Scanner</h3>
                <p>Scan another actor's QR code to trade badges</p>
            </div>

            <div id="event-status" class="oha-event-status"></div>

            <div id="scanner-interface" class="oha-scanner-interface">
                <div id="camera-container" class="camera-container">
                    <video id="camera-preview" autoplay playsinline muted></video>
                    <div id="scan-overlay" class="scan-overlay">
                        <div class="scan-frame"></div>
                        <p>Position QR code within the frame</p>
                    </div>
                </div>
                <div class="scanner-controls">
                    <button id="start-scan" class="oha-btn-primary">Start Scanning</button>
                    <button id="stop-scan" class="oha-btn-secondary" style="display:none;">Stop Scanning</button>
                    <button id="switch-camera" class="oha-btn-secondary" style="display:none;">Switch Camera</button>
                </div>
                <div class="manual-input">
                    <p><strong>Having trouble?</strong> Enter QR code manually:</p>
                    <input type="text" id="manual-qr" placeholder="Paste QR code here…">
                    <button id="process-manual" class="oha-btn-secondary">Process Code</button>
                </div>
            </div>

            <div id="scan-results" class="oha-scan-results"></div>

            <?php if ( ! empty( $atts['passport_url'] ) ) : ?>
            <div class="oha-scanner-passport-link">
                <a href="<?php echo esc_url( $atts['passport_url'] ); ?>">View My Passport</a>
            </div>
            <?php endif; ?>

            <?php if ( $atts['show_instructions'] === 'true' ) : ?>
            <div class="oha-instructions">
                <h4>How to Trade Badges</h4>
                <ol>
                    <li>Ask another actor to show their QR code</li>
                    <li>Tap "Start Scanning" and point your camera at their code</li>
                    <li>Both actors receive a badge stamp automatically!</li>
                    <li>You can only trade once per person per day</li>
                </ol>
            </div>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_my_stamps]
    // ─────────────────────────────────────────────────────────────────────
    public function display_my_stamps( $atts = [] ) {
        $atts = shortcode_atts( [
            'show_stats'  => 'true',
            'show_recent' => 'true',
            'limit'       => '20',
            'event'       => '',
        ], $atts );

        if ( ! is_user_logged_in() ) {
            return '<div class="oha-ph-login-prompt"><p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your stamps.</p></div>';
        }

        ob_start(); ?>
        <div id="oha-my-stamps" class="oha-ph-container oha-stamps-container">
            <div class="oha-stamps-header">
                <h3>🏆 My Badge Collection</h3>
                <p>Your badge trades and networking progress</p>
            </div>
            <?php if ( $atts['show_stats'] === 'true' ) : ?>
            <div id="stamp-stats" class="oha-stamp-stats">
                <div class="oha-ph-loading"><div class="oha-ph-spinner"></div><p>Loading stats…</p></div>
            </div>
            <?php endif; ?>
            <?php if ( $atts['show_recent'] === 'true' ) : ?>
            <div id="recent-stamps" class="oha-recent-stamps">
                <h4>Recent Badge Trades</h4>
                <div id="stamps-list"></div>
            </div>
            <?php endif; ?>
            <div id="empty-stamps" class="oha-ph-empty-state" style="display:none;">
                <div class="empty-icon">🎭</div>
                <h4>No Badge Trades Yet</h4>
                <p>Start networking at OHA events to collect badges!</p>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_stamps_leaderboard]
    // ─────────────────────────────────────────────────────────────────────
    public function display_leaderboard( $atts = [] ) {
        $atts = shortcode_atts( [
            'limit'       => '20',
            'show_haunts' => 'true',
            'title'       => 'Badge Trading Leaderboard',
            'event'       => '',
        ], $atts );

        ob_start(); ?>
        <div id="oha-leaderboard" class="oha-ph-container oha-leaderboard-container">
            <div class="oha-leaderboard-header">
                <h3><?php echo esc_html( $atts['title'] ); ?></h3>
                <p>Most active badge traders — celebrating participation, not competition!</p>
            </div>
            <div id="leaderboard-content">
                <div class="oha-ph-loading"><div class="oha-ph-spinner"></div><p>Loading leaderboard…</p></div>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_event_summary]
    // ─────────────────────────────────────────────────────────────────────
    public function display_event_summary( $atts = [] ) {
        $atts = shortcode_atts( [
            'event'       => '',
            'show_haunts' => 'true',
        ], $atts );

        // Default to active event or most recent
        $event_slug = $atts['event'];
        if ( empty( $event_slug ) ) {
            $active = OHA_Passport_Events::get_instance()->get_active_event();
            $event_slug = $active ? $active['slug'] : 'oha-convention-2026';
        }

        $event_info = OHA_Passport_Events::get_instance()->get_event_by_slug( $event_slug );
        $event_name = $event_info ? $event_info['event_name'] : esc_html( $event_slug );

        ob_start(); ?>
        <div id="oha-event-summary" class="oha-ph-container oha-summary-container"
             data-event="<?php echo esc_attr( $event_slug ); ?>">
            <div class="oha-summary-header">
                <h3>🎉 <?php echo esc_html( $event_name ); ?> — Badge Summary</h3>
                <p>Community networking results</p>
            </div>
            <div id="summary-stats">
                <div class="oha-ph-loading"><div class="oha-ph-spinner"></div><p>Loading results…</p></div>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_vendor_scanner]
    // ─────────────────────────────────────────────────────────────────────
    public function display_vendor_scanner( $atts = [] ) {
        $atts = shortcode_atts( [
            'show_instructions' => 'true',
            'event'             => '',
        ], $atts );

        ob_start(); ?>
        <div id="oha-vendor-scanner"
             class="oha-ph-container oha-scanner-container"
             data-mode="vendor">

            <div class="oha-scanner-header">
                <h3>🏪 Vendor Check-in</h3>
                <p>Scan a vendor booth QR code to check in and earn a stamp</p>
            </div>

            <div id="vendor-scanner-interface" class="oha-scanner-interface">
                <div id="vendor-camera-container" class="camera-container">
                    <video id="vendor-camera-preview" autoplay playsinline muted></video>
                    <div id="vendor-scan-overlay" class="scan-overlay">
                        <div class="scan-frame"></div>
                        <p>Point camera at vendor booth QR</p>
                    </div>
                </div>
                <div class="scanner-controls">
                    <button id="vendor-start-scan" class="oha-btn-primary">Start Scanning</button>
                    <button id="vendor-stop-scan"  class="oha-btn-secondary" style="display:none;">Stop Scanning</button>
                    <button id="vendor-switch-cam" class="oha-btn-secondary" style="display:none;">Switch Camera</button>
                </div>
            </div>

            <div id="vendor-scan-results" class="oha-scan-results"></div>

            <?php if ( $atts['show_instructions'] === 'true' ) : ?>
            <div class="oha-instructions">
                <h4>How to Check In</h4>
                <ol>
                    <li>Find the QR code posted at a vendor's booth</li>
                    <li>Tap "Start Scanning" and point your camera at the code</li>
                    <li>The vendor's profile will appear and a stamp is recorded!</li>
                </ol>
            </div>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_scavenger_hunt]
    // ─────────────────────────────────────────────────────────────────────
    public function display_scavenger_hunt( $atts = [] ) {
        $atts = shortcode_atts( [
            'event'            => '',
            'show_leaderboard' => 'false',
        ], $atts );

        // Resolve event slug
        $event_slug = $atts['event'];
        if ( empty( $event_slug ) ) {
            $active = OHA_Passport_Events::get_instance()->get_active_event();
            $event_slug = $active ? $active['slug'] : '';
        }

        ob_start(); ?>
        <div id="oha-scavenger-hunt"
             class="oha-ph-container oha-scavenger-container"
             data-mode="location"
             data-event="<?php echo esc_attr( $event_slug ); ?>">

            <div class="oha-scavenger-header">
                <h3>🗺️ Scavenger Hunt</h3>
                <p>Find hidden QR codes around the venue to earn stamps!</p>
            </div>

            <!-- Progress bar -->
            <div id="hunt-progress-wrap" class="oha-hunt-progress-wrap">
                <div class="oha-ph-loading"><div class="oha-ph-spinner"></div><p>Loading your progress…</p></div>
            </div>

            <!-- Location list (names revealed only after found, or with hint shown) -->
            <div id="hunt-locations-list" class="oha-hunt-locations"></div>

            <!-- Embedded scanner -->
            <div class="oha-scanner-interface oha-hunt-scanner">
                <h4>📷 Scan a Hidden QR Code</h4>
                <div id="hunt-camera-container" class="camera-container">
                    <video id="hunt-camera-preview" autoplay playsinline muted></video>
                    <div id="hunt-scan-overlay" class="scan-overlay">
                        <div class="scan-frame"></div>
                        <p>Find and scan a hidden marker</p>
                    </div>
                </div>
                <div class="scanner-controls">
                    <button id="hunt-start-scan" class="oha-btn-primary">Start Scanning</button>
                    <button id="hunt-stop-scan"  class="oha-btn-secondary" style="display:none;">Stop Scanning</button>
                    <button id="hunt-switch-cam" class="oha-btn-secondary" style="display:none;">Switch Camera</button>
                </div>
            </div>

            <div id="hunt-scan-results" class="oha-scan-results"></div>

            <?php if ( $atts['show_leaderboard'] === 'true' ) : ?>
            <div id="hunt-leaderboard" class="oha-hunt-leaderboard">
                <h4>🏆 Hunt Leaders</h4>
                <div id="hunt-leaderboard-list">
                    <div class="oha-ph-loading"><div class="oha-ph-spinner"></div><p>Loading leaderboard…</p></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_my_badges limit="50" compact="false"]
    // ─────────────────────────────────────────────────────────────────────
    public function display_my_badges( $atts = [] ) {
        $atts = shortcode_atts( [
            'limit'   => '50',
            'compact' => 'false',
        ], $atts );

        if ( ! is_user_logged_in() ) {
            return '<div class="oha-ph-login-prompt"><p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your badges.</p></div>';
        }

        ob_start(); ?>
        <div id="oha-my-badges-wrap"
             class="oha-ph-container oha-badges-container"
             data-compact="<?php echo esc_attr( $atts['compact'] ); ?>"
             data-limit="<?php echo intval( $atts['limit'] ); ?>">
            <div class="oha-ph-loading"><div class="oha-ph-spinner"></div><p>Loading badges...</p></div>
        </div>
        <?php return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_passport_progress]
    // ─────────────────────────────────────────────────────────────────────
    public function display_passport_progress( $atts = [] ) {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        ob_start(); ?>
        <div id="oha-passport-progress" class="oha-ph-container oha-progress-container">
            <div class="oha-ph-loading"><div class="oha-ph-spinner"></div></div>
        </div>
        <?php return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_connections limit="5"]
    // ─────────────────────────────────────────────────────────────────────
    public function display_connections( $atts = [] ) {
        $atts = shortcode_atts( [ 'limit' => '5' ], $atts );

        if ( ! is_user_logged_in() ) {
            return '<div class="oha-ph-login-prompt"><p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your connections.</p></div>';
        }

        ob_start(); ?>
        <div id="oha-connections-wrap"
             class="oha-ph-container oha-connections-container"
             data-limit="<?php echo intval( $atts['limit'] ); ?>">
            <div class="oha-ph-loading"><div class="oha-ph-spinner"></div><p>Loading connections...</p></div>
        </div>
        <?php return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_url_scanner]
    // ─────────────────────────────────────────────────────────────────────
    public function display_url_scanner( $atts = [] ) {
        ob_start(); ?>
        <div id="oha-url-scanner"
             class="oha-ph-container oha-scanner-container"
             data-mode="url">

            <div class="oha-scanner-header">
                <h3>🔗 QR Code Scanner</h3>
                <p>Scan any QR code to open its link</p>
            </div>

            <div id="url-scanner-interface" class="oha-scanner-interface">
                <div id="url-camera-container" class="camera-container">
                    <video id="url-camera-preview" autoplay playsinline muted></video>
                    <div id="url-scan-overlay" class="scan-overlay">
                        <div class="scan-frame"></div>
                        <p>Point camera at any QR code</p>
                    </div>
                </div>
                <div class="scanner-controls">
                    <button id="url-start-scan" class="oha-btn-primary">Start Scanning</button>
                    <button id="url-stop-scan"  class="oha-btn-secondary" style="display:none;">Stop Scanning</button>
                    <button id="url-switch-cam" class="oha-btn-secondary" style="display:none;">Switch Camera</button>
                </div>
                <div id="url-scan-results" class="oha-scan-results"></div>
                <div class="manual-input">
                    <p><strong>Or paste a URL:</strong></p>
                    <input type="text" id="url-manual-input" placeholder="https://…">
                    <button id="url-manual-go" class="oha-btn-secondary">Open</button>
                </div>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // [oha_smart_scanner]
    // Unified scanner: routes QR content to the right mode automatically.
    //   - VND:{id}      → vendor check-in
    //   - LOC:{hash}    → scavenger location
    //   - http(s)://... → URL opener
    //   - 64-char hex   → actor badge trade
    // ─────────────────────────────────────────────────────────────────────
    public function display_smart_scanner( $atts = [] ) {
        $atts = shortcode_atts( [
            'show_instructions' => 'true',
            'passport_url'      => get_option( 'oha_pwa_passport_url', rtrim( get_option( 'oha_convention_base_url', '/convention' ), '/' ) . '/passport/' ),
        ], $atts );

        if ( ! is_user_logged_in() ) {
            return '<div class="oha-ph-login-prompt"><p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to use the scanner.</p></div>';
        }

        ob_start(); ?>
        <div id="oha-smart-scanner"
             class="oha-ph-container oha-scanner-container"
             data-mode="smart">

            <div class="oha-scanner-header">
                <h3>📱 Smart QR Scanner</h3>
                <p>Scan any OHA QR code — badges, vendors, hunt markers, or links</p>
            </div>

            <div id="smart-scanner-interface" class="oha-scanner-interface">
                <div id="smart-camera-container" class="camera-container">
                    <video id="smart-camera-preview" autoplay playsinline muted></video>
                    <div id="smart-scan-overlay" class="scan-overlay">
                        <div class="scan-frame"></div>
                        <p>Point at any OHA QR code</p>
                    </div>
                </div>
                <div class="scanner-controls">
                    <button id="smart-start-scan" class="oha-btn-primary">Start Scanning</button>
                    <button id="smart-stop-scan"  class="oha-btn-secondary" style="display:none;">Stop Scanning</button>
                    <button id="smart-switch-cam" class="oha-btn-secondary" style="display:none;">Switch Camera</button>
                </div>
                <div class="manual-input">
                    <p><strong>Having trouble?</strong> Enter code manually:</p>
                    <input type="text" id="smart-manual-qr" placeholder="Paste QR code here…">
                    <button id="smart-process-manual" class="oha-btn-secondary">Process Code</button>
                </div>
            </div>

            <div id="smart-scan-results" class="oha-scan-results"></div>

            <?php if ( ! empty( $atts['passport_url'] ) ) : ?>
            <div class="oha-scanner-passport-link">
                <a href="<?php echo esc_url( $atts['passport_url'] ); ?>">View My Passport</a>
            </div>
            <?php endif; ?>

            <?php if ( $atts['show_instructions'] === 'true' ) : ?>
            <div class="oha-instructions">
                <h4>What Can I Scan?</h4>
                <ul>
                    <li>🎭 <strong>Actor badges</strong> — trade badges with other actors</li>
                    <li>🏪 <strong>Vendor booths</strong> — check in at vendor tables</li>
                    <li>📍 <strong>Hunt markers</strong> — collect scavenger hunt stamps</li>
                    <li>🔗 <strong>Any QR code</strong> — opens links posted around the venue</li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }
}
