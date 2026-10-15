<?php
if (!defined('ABSPATH')) exit;

class OHA_Awards_Landing {
    public static function init() {
        add_shortcode('oha_awards_landing', [__CLASS__, 'render']);
    }

    public static function render($atts = []) {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (function_exists('nocache_headers')) nocache_headers();

        $award    = sanitize_key($_GET['award'] ?? '');
        $year     = OHA_Awards_Settings::get_year();
        $opts     = get_option('oha_awards_settings', []);
        $page_url = (string) get_permalink();

        if ($award === 'actor') {
            return self::render_form_view('actor', $opts, $year, $page_url);
        }
        if ($award === 'campsite') {
            return self::render_form_view('campsite', $opts, $year, $page_url);
        }
        return self::render_choice($opts, $year, $page_url);
    }

    private static function render_choice($opts, $year, $page_url) {
        $actor_open    = OHA_Awards_Settings::is_submissions_open('', 'actor');
        $campsite_open = OHA_Awards_Settings::is_submissions_open('', 'campsite');
        $actor_url     = esc_url(add_query_arg('award', 'actor', $page_url));
        $campsite_url  = esc_url(add_query_arg('award', 'campsite', $page_url));
        ob_start();
        ?>
        <style>
        .oha-awards-choice { display:grid; grid-template-columns:1fr; gap:24px; max-width:900px; }
        @media(min-width:640px){ .oha-awards-choice { grid-template-columns:1fr 1fr; } }
        .oha-awards-choice-card { background:var(--global-palette9,#000); border:1px solid rgba(173,49,25,0.45); border-radius:10px; padding:28px 24px; text-align:center; transition:border-color 0.15s, box-shadow 0.15s; }
        .oha-awards-choice-card:hover { border-color:var(--global-palette1,#ad3119); box-shadow:0 6px 24px rgba(173,49,25,0.3); }
        .oha-awards-choice-card h3 { color:var(--global-palette3,#fff); margin-top:0; margin-bottom:10px; font-size:1.2rem; }
        .oha-awards-choice-card p { color:var(--global-palette4,#f7fafc); margin-bottom:22px; line-height:1.6; }
        .oha-awards-choice-btn { display:inline-block; padding:11px 26px; border-radius:6px; background:var(--global-palette1,#ad3119); color:#fff; text-decoration:none; font-weight:600; transition:background 0.15s; }
        .oha-awards-choice-btn:hover { background:var(--global-palette2,#ff0000); color:#fff; text-decoration:none; }
        .oha-awards-choice-closed { display:inline-block; padding:10px 20px; border-radius:6px; background:rgba(255,255,255,0.08); color:var(--global-palette6,#cbd2d9); font-size:0.9rem; }
        </style>
        <div class="oha-awards-choice">
            <div class="oha-awards-choice-card">
                <h3>Haunt Actor Award</h3>
                <p>Recognize a member of your haunt staff who went above and beyond this season. Submitted by haunt owners on behalf of their performers.</p>
                <?php if ($actor_open): ?>
                    <a href="<?php echo $actor_url; ?>" class="oha-awards-choice-btn">Submit Actor Nomination</a>
                <?php else: ?>
                    <span class="oha-awards-choice-closed">Submissions Closed</span>
                <?php endif; ?>
            </div>
            <div class="oha-awards-choice-card">
                <h3>Campsite Award</h3>
                <p>Submit your haunted campsite competition for recognition. Open to all campground operators - no OHA account required.</p>
                <?php if ($campsite_open): ?>
                    <a href="<?php echo $campsite_url; ?>" class="oha-awards-choice-btn">Submit Campsite Nomination</a>
                <?php else: ?>
                    <span class="oha-awards-choice-closed">Submissions Closed</span>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_form_view($type, $opts, $year, $page_url) {
        $back = esc_url(remove_query_arg('award', $page_url));

        if ($type === 'campsite') {
            $raw      = !empty($opts['campsite_landing_text']) ? $opts['campsite_landing_text'] : self::default_campsite_text();
            $form_tag = '[oha_campsite_award_submit]';
        } else {
            $raw      = !empty($opts['actor_landing_text']) ? $opts['actor_landing_text'] : self::default_actor_text();
            $form_tag = '[oha_awards_submit]';
        }

        $text = str_replace('{year}', esc_html($year), $raw);

        ob_start();
        echo '<p style="margin-bottom:20px;"><a href="' . $back . '" style="color:var(--global-palette7,#e76653);">&larr; Back to Awards</a></p>';
        echo '<div class="oha-awards-landing-text" style="max-width:820px;margin-bottom:28px;">' . wpautop(wp_kses_post($text)) . '</div>';
        echo do_shortcode($form_tag);
        return ob_get_clean();
    }

    private static function default_actor_text() {
        return "{year} Submissions\n\nThe Ohio Haunters Association will be offering haunts the opportunity for your performers to receive recognition for their performance. The Outstanding Performance Award for {year} is meant for whichever staff member you felt went above and beyond this season.\n\nHere are the steps to submit your entries:\n\nSubmit Your Nominee - Use the form below to submit information about the actor or actress you wish to acknowledge and choose \"Submit Award Nomination\"\n\nCertificates - Once verified by the OHA Board, we will announce the winners on our Website and Social Media Platforms. Each haunt gets a free digital certificate that will be made available to the owner who submitted the nomination. If you wish to have a printed certificate made they can be purchased for $10 via the payment link that will be sent with your digital certificate.";
    }

    private static function default_campsite_text() {
        return "{year} Submissions\n\nThe Ohio Haunters Association is proud to recognize haunted campsite competitions that delivered an outstanding experience for participants. The Most Outstanding Haunted Campsite Award for {year} is open to any campground that hosted a haunted camping competition this season.\n\nHere are the steps to submit your entry:\n\nSubmit Your Campground - Use the form below to provide information about your haunted campsite competition and choose \"Submit Campsite Award Nomination\"\n\nCertificates - Once verified by the OHA Board, we will announce the winners on our Website and Social Media Platforms. Each recognized campground receives a free digital certificate that will be made available to the contact who submitted the nomination. If you wish to have a printed certificate made they can be purchased for $10 via the payment link that will be sent with your digital certificate.";
    }
}
