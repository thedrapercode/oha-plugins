<?php
/**
 * Survey Selector Template
 * Displays the survey selection page with trust-building information
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if a specific survey is requested via URL parameter
$requested_survey = isset($_GET['survey']) ? sanitize_text_field($_GET['survey']) : '';

// Process a retake token if present
$retake_token = isset($_GET['oha_retake']) ? sanitize_text_field($_GET['oha_retake']) : '';
if ($retake_token) {
    $retake_data = get_transient('oha_retake_' . $retake_token);
    if ($retake_data && is_array($retake_data) && !empty($retake_data['email']) && !empty($retake_data['survey_type'])) {
        delete_transient('oha_retake_' . $retake_token);
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'oha_survey_responses',
            ['email' => $retake_data['email'], 'survey_type' => $retake_data['survey_type']],
            ['%s', '%s']
        );
        // Map any year's survey type back to URL param; legacy 2026 retake links remain valid
        $conv_year = OHA_Convention_Survey::get_year();
        $type_to_param = [
            'convention_' . $conv_year              => 'convention_attendee',
            'convention_' . $conv_year . '_vendor'  => 'convention_vendor',
            'convention_' . $conv_year . '_volunteer' => 'convention_volunteer',
            'convention_2026'           => 'convention_attendee',
            'convention_2026_vendor'    => 'convention_vendor',
            'convention_2026_volunteer' => 'convention_volunteer',
        ];
        $requested_survey = $type_to_param[$retake_data['survey_type']] ?? '';
        $GLOBALS['oha_survey_retake_email'] = $retake_data['email'];
    } else {
        ?>
        <div class="oha-survey-selector">
            <div class="oha-selector-header">
                <h2 class="oha-selector-title">Retake Link Expired</h2>
                <p class="oha-selector-description">This retake link has already been used or has expired. Please contact the OHA board for a new link.</p>
            </div>
        </div>
        <?php
        return;
    }
}

// Map survey types to shortcodes
$survey_shortcodes = array(
    'member'                 => '[oha_survey_member direct_link="true"]',
    'scare_actor'            => '[oha_survey_scare_actor direct_link="true"]',
    'behind_scenes'          => '[oha_survey_behind_scenes direct_link="true"]',
    'home_haunt'             => '[oha_survey_home_haunt direct_link="true"]',
    'industry_deep_dive'     => '[oha_survey_industry_deep_dive direct_link="true"]',
    'convention_planning'    => '[oha_survey_convention_planning direct_link="true"]',
    'convention_attendee'    => '[oha_convention_survey context="attendee"]',
    'convention_vendor'      => '[oha_convention_survey context="vendor"]',
    'convention_volunteer'   => '[oha_convention_survey context="volunteer"]',
);

// If a specific survey is requested, load it directly
if ($requested_survey && isset($survey_shortcodes[$requested_survey])) {
    echo do_shortcode($survey_shortcodes[$requested_survey]);
    return;
}

// Base URL for this selector page — used to build direct links for each survey
$page_url = get_permalink();

// Helper to build and escape a direct link for a given survey type
if (!function_exists('oha_survey_direct_link')) {
    function oha_survey_direct_link($page_url, $type) {
        return esc_url(add_query_arg('survey', $type, $page_url));
    }
}
?>

<div class="oha-survey-selector">
    <div class="oha-selector-header">
        <h2 class="oha-selector-title"><?php echo esc_html($atts['title']); ?></h2>
        <p class="oha-selector-description"><?php echo esc_html($atts['description']); ?></p>
    </div>

    <div class="oha-trust-building">
        <h3><?php _e('Your Privacy and Trust Matter', 'oha-survey-hub'); ?></h3>
        <div class="oha-trust-grid">
            <div class="oha-trust-item">
                <h4><?php _e('Completely Anonymous', 'oha-survey-hub'); ?></h4>
                <p><?php _e('We only collect your email to prevent duplicate responses and send confirmation. Your answers are never linked to your identity.', 'oha-survey-hub'); ?></p>
            </div>
            <div class="oha-trust-item">
                <h4><?php _e('Save Your Progress', 'oha-survey-hub'); ?></h4>
                <p><?php _e('Take breaks and return later. Your progress is automatically saved so you can complete the survey at your own pace.', 'oha-survey-hub'); ?></p>
            </div>
            <div class="oha-trust-item">
                <h4><?php _e('Help Our Community', 'oha-survey-hub'); ?></h4>
                <p><?php _e('Your insights help OHA better serve the Ohio haunt community through improved programs, resources, and advocacy.', 'oha-survey-hub'); ?></p>
            </div>
            <div class="oha-trust-item">
                <h4><?php _e('Smart Structure', 'oha-survey-hub'); ?></h4>
                <p><?php _e('Questions are organized into short pages so even partial responses provide value.', 'oha-survey-hub'); ?></p>
            </div>
        </div>
    </div>

    <?php $planning_year = (int) get_option('oha_convention_survey_year', 2027); ?>
    <div class="oha-survey-options oha-survey-options-convention">
        <h3><?php echo esc_html(sprintf(__('OHA Convention %d Planning', 'oha-survey-hub'), $planning_year)); ?></h3>
        <p class="oha-convention-surveys-intro"><?php echo esc_html(sprintf(__('Help us plan OHA Convention %d. Tell us what you want to attend, what food and logistics options matter to you, and what would make it worth the trip.', 'oha-survey-hub'), $planning_year)); ?></p>
        <div class="oha-survey-grid">

            <div class="oha-survey-card oha-survey-card-convention" data-survey-type="convention_planning">
                <h4><?php echo esc_html(sprintf(__('Convention %d Planning Survey', 'oha-survey-hub'), $planning_year)); ?></h4>
                <p><?php echo esc_html(sprintf(__('For anyone considering attending OHA Convention %d. Share your preferences so we can plan the best event possible.', 'oha-survey-hub'), $planning_year)); ?></p>
                <div class="oha-survey-meta">
                    <span class="oha-survey-time">10 to 15 minutes</span>
                    <span class="oha-survey-pages">10 pages</span>
                </div>
                <a href="<?php echo oha_survey_direct_link($page_url, 'convention_planning'); ?>" class="oha-survey-btn">
                    <?php _e('Start Survey', 'oha-survey-hub'); ?>
                </a>
                <div class="oha-card-direct-link">
                    <span class="oha-direct-link-label">Direct link:</span>
                    <button class="oha-copy-link-btn" data-url="<?php echo oha_survey_direct_link($page_url, 'convention_planning'); ?>">
                        Copy Link
                    </button>
                </div>
            </div>

        </div>
    </div>

    <div class="oha-survey-options oha-survey-options-convention">
        <?php $conv_year = OHA_Convention_Survey::get_year(); ?>
        <h3><?php echo esc_html(sprintf(__('OHA Convention %d Feedback', 'oha-survey-hub'), $conv_year)); ?></h3>
        <p class="oha-convention-surveys-intro"><?php echo esc_html(sprintf(__('Share your experience from OHA Convention %d. Select the survey that matches your role at the event.', 'oha-survey-hub'), $conv_year)); ?></p>
        <div class="oha-survey-grid">

            <div class="oha-survey-card oha-survey-card-convention" data-survey-type="convention_attendee">
                <h4><?php _e('Attendee Feedback', 'oha-survey-hub'); ?></h4>
                <p><?php echo esc_html(sprintf(__('For general admission and VIP attendees of OHA Convention %d.', 'oha-survey-hub'), $conv_year)); ?></p>
                <div class="oha-survey-meta">
                    <span class="oha-survey-time">5 to 10 minutes</span>
                    <span class="oha-survey-pages">Up to 8 pages</span>
                </div>
                <a href="<?php echo oha_survey_direct_link($page_url, 'convention_attendee'); ?>" class="oha-survey-btn">
                    <?php _e('Start Survey', 'oha-survey-hub'); ?>
                </a>
                <div class="oha-card-direct-link">
                    <span class="oha-direct-link-label">Direct link:</span>
                    <button class="oha-copy-link-btn" data-url="<?php echo oha_survey_direct_link($page_url, 'convention_attendee'); ?>">
                        Copy Link
                    </button>
                </div>
            </div>

            <div class="oha-survey-card oha-survey-card-convention" data-survey-type="convention_vendor">
                <h4><?php _e('Vendor Feedback', 'oha-survey-hub'); ?></h4>
                <p><?php echo esc_html(sprintf(__('For vendors and exhibitors who participated in the OHA Convention %d vendor floor.', 'oha-survey-hub'), $conv_year)); ?></p>
                <div class="oha-survey-meta">
                    <span class="oha-survey-time">5 to 10 minutes</span>
                    <span class="oha-survey-pages">Varies</span>
                </div>
                <a href="<?php echo oha_survey_direct_link($page_url, 'convention_vendor'); ?>" class="oha-survey-btn">
                    <?php _e('Start Survey', 'oha-survey-hub'); ?>
                </a>
                <div class="oha-card-direct-link">
                    <span class="oha-direct-link-label">Direct link:</span>
                    <button class="oha-copy-link-btn" data-url="<?php echo oha_survey_direct_link($page_url, 'convention_vendor'); ?>">
                        Copy Link
                    </button>
                </div>
            </div>

            <div class="oha-survey-card oha-survey-card-convention" data-survey-type="convention_volunteer">
                <h4><?php _e('Volunteer Feedback', 'oha-survey-hub'); ?></h4>
                <p><?php echo esc_html(sprintf(__('For volunteers who helped run OHA Convention %d.', 'oha-survey-hub'), $conv_year)); ?></p>
                <div class="oha-survey-meta">
                    <span class="oha-survey-time">5 to 10 minutes</span>
                    <span class="oha-survey-pages">Varies</span>
                </div>
                <a href="<?php echo oha_survey_direct_link($page_url, 'convention_volunteer'); ?>" class="oha-survey-btn">
                    <?php _e('Start Survey', 'oha-survey-hub'); ?>
                </a>
                <div class="oha-card-direct-link">
                    <span class="oha-direct-link-label">Direct link:</span>
                    <button class="oha-copy-link-btn" data-url="<?php echo oha_survey_direct_link($page_url, 'convention_volunteer'); ?>">
                        Copy Link
                    </button>
                </div>
            </div>

        </div>
    </div>

    <div class="oha-survey-options">
        <h3><?php _e('Choose Your Survey', 'oha-survey-hub'); ?></h3>
        <div class="oha-survey-grid">

            <div class="oha-survey-card" data-survey-type="member">
                <h4><?php _e('OHA Member Survey', 'oha-survey-hub'); ?></h4>
                <p><?php _e('For current and potential OHA members across all roles in the haunt industry.', 'oha-survey-hub'); ?></p>
                <div class="oha-survey-meta">
                    <span class="oha-survey-time">10 to 15 minutes</span>
                    <span class="oha-survey-pages">7 pages</span>
                </div>
                <a href="<?php echo oha_survey_direct_link($page_url, 'member'); ?>" class="oha-survey-btn">
                    <?php _e('Start Survey', 'oha-survey-hub'); ?>
                </a>
                <div class="oha-card-direct-link">
                    <span class="oha-direct-link-label">Direct link:</span>
                    <button class="oha-copy-link-btn" data-url="<?php echo oha_survey_direct_link($page_url, 'member'); ?>">
                        Copy Link
                    </button>
                </div>
            </div>

            <div class="oha-survey-card" data-survey-type="scare_actor">
                <h4><?php _e('Scare Actor and Crew Survey', 'oha-survey-hub'); ?></h4>
                <p><?php _e('For scare actors, performers, makeup artists, and costume crew members.', 'oha-survey-hub'); ?></p>
                <div class="oha-survey-meta">
                    <span class="oha-survey-time">12 to 18 minutes</span>
                    <span class="oha-survey-pages">8 pages</span>
                </div>
                <a href="<?php echo oha_survey_direct_link($page_url, 'scare_actor'); ?>" class="oha-survey-btn">
                    <?php _e('Start Survey', 'oha-survey-hub'); ?>
                </a>
                <div class="oha-card-direct-link">
                    <span class="oha-direct-link-label">Direct link:</span>
                    <button class="oha-copy-link-btn" data-url="<?php echo oha_survey_direct_link($page_url, 'scare_actor'); ?>">
                        Copy Link
                    </button>
                </div>
            </div>

            <div class="oha-survey-card" data-survey-type="behind_scenes">
                <h4><?php _e('Behind-the-Scenes and Technical Survey', 'oha-survey-hub'); ?></h4>
                <p><?php _e('For builders, technicians, designers, and behind-the-scenes professionals.', 'oha-survey-hub'); ?></p>
                <div class="oha-survey-meta">
                    <span class="oha-survey-time">15 to 20 minutes</span>
                    <span class="oha-survey-pages">7 pages</span>
                </div>
                <a href="<?php echo oha_survey_direct_link($page_url, 'behind_scenes'); ?>" class="oha-survey-btn">
                    <?php _e('Start Survey', 'oha-survey-hub'); ?>
                </a>
                <div class="oha-card-direct-link">
                    <span class="oha-direct-link-label">Direct link:</span>
                    <button class="oha-copy-link-btn" data-url="<?php echo oha_survey_direct_link($page_url, 'behind_scenes'); ?>">
                        Copy Link
                    </button>
                </div>
            </div>

            <div class="oha-survey-card" data-survey-type="home_haunt">
                <h4><?php _e('Home Haunt Survey', 'oha-survey-hub'); ?></h4>
                <p><?php _e('For home haunt operators, yard display creators, and neighborhood haunt organizers.', 'oha-survey-hub'); ?></p>
                <div class="oha-survey-meta">
                    <span class="oha-survey-time">12 to 16 minutes</span>
                    <span class="oha-survey-pages">8 pages</span>
                </div>
                <a href="<?php echo oha_survey_direct_link($page_url, 'home_haunt'); ?>" class="oha-survey-btn">
                    <?php _e('Start Survey', 'oha-survey-hub'); ?>
                </a>
                <div class="oha-card-direct-link">
                    <span class="oha-direct-link-label">Direct link:</span>
                    <button class="oha-copy-link-btn" data-url="<?php echo oha_survey_direct_link($page_url, 'home_haunt'); ?>">
                        Copy Link
                    </button>
                </div>
            </div>

            <?php $hy = OHA_Survey_Definitions::haunt_season_years(); ?>
            <div class="oha-survey-card" data-survey-type="industry_deep_dive">
                <h4><?php echo esc_html(sprintf(__('Industry Deep-Dive Survey (%1$d to %2$d)', 'oha-survey-hub'), $hy['past'], $hy['next'])); ?></h4>
                <p><?php _e('For haunt owners and operators looking at industry trends, financial health, and future planning.', 'oha-survey-hub'); ?></p>
                <div class="oha-survey-meta">
                    <span class="oha-survey-time">20 to 25 minutes</span>
                    <span class="oha-survey-pages">10 pages</span>
                </div>
                <a href="<?php echo oha_survey_direct_link($page_url, 'industry_deep_dive'); ?>" class="oha-survey-btn">
                    <?php _e('Start Survey', 'oha-survey-hub'); ?>
                </a>
                <div class="oha-card-direct-link">
                    <span class="oha-direct-link-label">Direct link:</span>
                    <button class="oha-copy-link-btn" data-url="<?php echo oha_survey_direct_link($page_url, 'industry_deep_dive'); ?>">
                        Copy Link
                    </button>
                </div>
            </div>

        </div>
    </div>

    <div class="oha-survey-footer">
        <div class="oha-footer-info">
            <h4><?php _e('Need Help?', 'oha-survey-hub'); ?></h4>
            <p><?php
                $support_email = get_option('oha_survey_hub_support_email', 'info@ohiohaunt.com');
                printf(
                    __('If you have questions about these surveys or need assistance, please contact the OHA board at %s', 'oha-survey-hub'),
                    '<a href="mailto:' . esc_attr($support_email) . '">' . esc_html($support_email) . '</a>'
                );
            ?></p>
        </div>

        <div class="oha-footer-links">
            <h4><?php _e('Direct Links', 'oha-survey-hub'); ?></h4>
            <p><?php _e('Share these links to send someone directly to a specific survey:', 'oha-survey-hub'); ?></p>
            <ul class="oha-direct-links">
                <li><a href="<?php echo oha_survey_direct_link($page_url, 'convention_planning'); ?>"><?php echo esc_html(sprintf(__('Convention %d Planning Survey', 'oha-survey-hub'), $planning_year)); ?></a></li>
                <li><a href="<?php echo oha_survey_direct_link($page_url, 'member'); ?>"><?php _e('Member Survey', 'oha-survey-hub'); ?></a></li>
                <li><a href="<?php echo oha_survey_direct_link($page_url, 'scare_actor'); ?>"><?php _e('Scare Actor Survey', 'oha-survey-hub'); ?></a></li>
                <li><a href="<?php echo oha_survey_direct_link($page_url, 'behind_scenes'); ?>"><?php _e('Behind-the-Scenes Survey', 'oha-survey-hub'); ?></a></li>
                <li><a href="<?php echo oha_survey_direct_link($page_url, 'home_haunt'); ?>"><?php _e('Home Haunt Survey', 'oha-survey-hub'); ?></a></li>
                <li><a href="<?php echo oha_survey_direct_link($page_url, 'industry_deep_dive'); ?>"><?php _e('Industry Deep-Dive Survey', 'oha-survey-hub'); ?></a></li>
                <li><a href="<?php echo oha_survey_direct_link($page_url, 'convention_attendee'); ?>"><?php echo esc_html(sprintf(__('Convention %d - Attendee', 'oha-survey-hub'), $conv_year)); ?></a></li>
                <li><a href="<?php echo oha_survey_direct_link($page_url, 'convention_vendor'); ?>"><?php echo esc_html(sprintf(__('Convention %d - Vendor', 'oha-survey-hub'), $conv_year)); ?></a></li>
                <li><a href="<?php echo oha_survey_direct_link($page_url, 'convention_volunteer'); ?>"><?php echo esc_html(sprintf(__('Convention %d - Volunteer', 'oha-survey-hub'), $conv_year)); ?></a></li>
            </ul>
        </div>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.oha-copy-link-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-url');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(function () {
                    btn.textContent = 'Copied';
                    setTimeout(function () { btn.textContent = 'Copy Link'; }, 2000);
                });
            } else {
                // Fallback for older browsers
                var temp = document.createElement('textarea');
                temp.value = url;
                temp.style.position = 'fixed';
                temp.style.opacity = '0';
                document.body.appendChild(temp);
                temp.select();
                try {
                    document.execCommand('copy');
                    btn.textContent = 'Copied';
                    setTimeout(function () { btn.textContent = 'Copy Link'; }, 2000);
                } catch (e) {}
                document.body.removeChild(temp);
            }
        });
    });
}());
</script>
