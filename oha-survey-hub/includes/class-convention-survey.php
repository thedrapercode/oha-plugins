<?php
/**
 * OHA Convention Survey
 * DB-backed question management with conditional routing, admin edit/hide/delete,
 * and multi-context support (attendee, vendor, volunteer).
 */

if (!defined('ABSPATH')) {
    exit;
}

class OHA_Convention_Survey {

    const SURVEY_TYPE_ATTENDEE  = 'convention_2026';
    const SURVEY_TYPE_VENDOR    = 'convention_2026_vendor';
    const SURVEY_TYPE_VOLUNTEER = 'convention_2026_volunteer';

    // Kept for backward-compat with main plugin file references
    const SURVEY_TYPE = 'convention_2026';

    private static $page_titles = [
        'attendee' => [
            1 => 'Ticketing and Pricing',
            2 => 'Ticket Type and Venue',
            3 => 'VIP Experience',
            4 => 'Vendor Floor',
            5 => 'Education Sessions',
            6 => 'Costume Ball',
            7 => 'Blood Prison',
            8 => '2027 Planning and Overall Feedback',
        ],
        'vendor' => [
            1 => 'Setup and Operations',
            2 => 'Attendee Engagement',
            3 => 'Education Sessions',
            4 => 'Costume Ball',
            5 => 'Communication and Support',
            6 => 'Looking Ahead to 2027',
        ],
        'volunteer' => [],
    ];

    private static function get_page_title($pg, $context = 'attendee') {
        $map = self::$page_titles[$context] ?? [];
        return $map[$pg] ?? 'Page ' . $pg;
    }

    private static $context_labels = [
        'attendee'  => 'Attendees',
        'vendor'    => 'Vendors',
        'volunteer' => 'Volunteers',
    ];

    // -------------------------------------------------------------------------
    // Table / seed
    // -------------------------------------------------------------------------

    public static function create_table() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table           = $wpdb->prefix . 'oha_survey_convention_questions';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id int(11) NOT NULL AUTO_INCREMENT,
            survey_context varchar(20) NOT NULL DEFAULT 'attendee',
            sort_order int(11) DEFAULT 0,
            page_group int(11) DEFAULT 1,
            question_text text NOT NULL,
            question_type varchar(20) NOT NULL DEFAULT 'radio',
            options longtext DEFAULT NULL,
            scale_min int(11) DEFAULT 1,
            scale_max int(11) DEFAULT 5,
            scale_label_min varchar(100) DEFAULT '',
            scale_label_max varchar(100) DEFAULT '',
            required tinyint(1) DEFAULT 1,
            hidden tinyint(1) DEFAULT 0,
            gate_for_group int(11) DEFAULT 0,
            gate_show_value varchar(100) DEFAULT '',
            PRIMARY KEY (id),
            KEY survey_context (survey_context),
            KEY sort_order (sort_order),
            KEY hidden (hidden)
        ) $charset_collate;";
        dbDelta($sql);
    }

    public static function seed_questions() {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_survey_convention_questions';
        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM $table") > 0) {
            return;
        }
        foreach (self::default_questions() as $q) {
            $wpdb->insert($table, $q);
        }
    }

    private static function q($sort, $group, $text, $type, $opts, $required, $gate_for = 0, $gate_val = '', $smin = 1, $smax = 5, $slmin = '', $slmax = '') {
        return [
            'survey_context'  => 'attendee',
            'sort_order'      => $sort,
            'page_group'      => $group,
            'question_text'   => $text,
            'question_type'   => $type,
            'options'         => $opts ? json_encode($opts) : null,
            'scale_min'       => $smin,
            'scale_max'       => $smax,
            'scale_label_min' => $slmin,
            'scale_label_max' => $slmax,
            'required'        => $required ? 1 : 0,
            'hidden'          => 0,
            'gate_for_group'  => $gate_for,
            'gate_show_value' => $gate_val,
        ];
    }

    private static function default_questions() {
        return [
            // ---- Page 1: Ticketing and Pricing (everyone) ----
            self::q(5, 1, 'Which day(s) did you attend OHA Convention 2026?',
                'radio', ['Saturday only (May 10)', 'Sunday only (May 11)', 'Both days'], true),

            self::q(10, 1, 'How did you purchase your convention tickets?',
                'radio', ['Online pre-sale', 'At the door', 'Both online and at the door'], true),

            self::q(20, 1, 'If you purchased online, how easy was the ticket purchase process?',
                'scale', null, false, 0, '', 1, 5, 'Very difficult', 'Very easy'),

            self::q(30, 1, 'Were you aware that Costume Ball, Education, and Blood Prison admission required separate tickets that needed to be added during your initial online checkout?',
                'radio', ['Yes, I was aware', 'No, I did not know this', 'I did not purchase online'], true),

            self::q(40, 1, 'Did you need to add Costume Ball, Education, or Blood Prison tickets after your initial online order was placed?',
                'radio', [
                    'Yes - I contacted the venue to add them',
                    'Yes - I bought them at the door instead',
                    'No - I added everything I needed during checkout',
                    'Not applicable - I did not want those events',
                ], true),

            // ---- Page 2: Ticket Type and Venue (everyone) ----
            // Gate question: VIP ($80) gates page 3
            self::q(50, 2, 'Which ticket type did you purchase?',
                'radio', ['General Admission ($30)', 'VIP ($80)', 'I attended without purchasing a ticket'], true,
                3, 'VIP ($80)'),

            self::q(60, 2, 'How would you rate the overall value of your ticket?',
                'scale', null, true, 0, '', 1, 5, 'Poor value', 'Excellent value'),

            self::q(70, 2, 'How would you rate your self-guided tour experience of the Ohio Reformatory, including the museum and filming locations?',
                'scale', null, true, 0, '', 1, 5, 'Disappointing', 'Outstanding'),

            self::q(80, 2, 'The historic cell areas of the building do not have heating or cooling. How did the temperature affect your experience?',
                'radio', [
                    'Not an issue for me',
                    'Slightly uncomfortable but manageable',
                    'It moderately impacted my enjoyment',
                    'It significantly impacted my enjoyment',
                    'I was not aware of this before arriving',
                ], true),

            self::q(85, 2, 'How would you rate the entry and check-in process when you arrived?',
                'scale', null, true, 0, '', 1, 5, 'Very slow or confusing', 'Quick and smooth'),

            self::q(87, 2, 'How would you rate the signage and wayfinding inside the venue?',
                'scale', null, true, 0, '', 1, 5, 'Confusing', 'Very clear'),

            // ---- Page 3: VIP Experience (conditional - shown only if VIP purchased) ----
            self::q(90, 3, 'The VIP ticket included vendor floor access, all education classes, and costume ball admission. Did the VIP inclusions meet your expectations?',
                'radio', ['Exceeded expectations', 'Met expectations', 'Fell slightly short', 'Did not meet expectations'], true),

            self::q(100, 3, 'How would you rate the overall value of the VIP upgrade compared to General Admission?',
                'scale', null, true, 0, '', 1, 5, 'Not worth it', 'Excellent value'),

            self::q(110, 3, 'Would you purchase VIP again for a future OHA Convention?',
                'radio', ['Definitely yes', 'Probably yes', 'Unsure', 'Probably not', 'Definitely not'], true),

            // ---- Page 4: Vendor Floor + Branch Gates (everyone) ----
            self::q(120, 4, 'How would you rate your overall experience on the vendor floor?',
                'scale', null, true, 0, '', 1, 5, 'Disappointing', 'Excellent'),

            self::q(130, 4, 'With 37 vendors this year, how would you rate the variety of vendor offerings?',
                'scale', null, true, 0, '', 1, 5, 'Needs more variety', 'Excellent variety'),

            self::q(140, 4, 'How would you rate the food options available outside the building?',
                'scale', null, true, 0, '', 1, 5, 'Poor', 'Excellent'),

            self::q(145, 4, 'How would you rate the restroom facilities available at the event?',
                'scale', null, true, 0, '', 1, 5, 'Very poor', 'Excellent'),

            // Gate: Education gates page 5
            self::q(150, 4, 'Did you attend any Education sessions?',
                'radio', ['Yes', 'No'], true, 5, 'Yes'),

            // Gate: Costume Ball gates page 6
            self::q(160, 4, 'Did you attend the Costume Ball?',
                'radio', ['Yes', 'No'], true, 6, 'Yes'),

            // Gate: Blood Prison gates page 7
            self::q(170, 4, 'Did you attend Blood Prison?',
                'radio', ['Yes', 'No'], true, 7, 'Yes'),

            // ---- Page 5: Education Sessions (conditional) ----
            self::q(180, 5, 'How would you rate the Education sessions you attended overall?',
                'scale', null, true, 0, '', 1, 5, 'Disappointing', 'Excellent'),

            self::q(190, 5, 'How many Education sessions did you attend?',
                'radio', ['1', '2', '3 or more'], true),

            self::q(200, 5, 'How relevant were the session topics to your interests and needs?',
                'scale', null, true, 0, '', 1, 5, 'Not relevant', 'Very relevant'),

            self::q(210, 5, 'Do you have suggestions for Education topics you would like to see at future conventions?',
                'textarea', null, false),

            // ---- Page 6: Costume Ball (conditional) ----
            self::q(220, 6, 'How would you rate the Costume Ball overall?',
                'scale', null, true, 0, '', 1, 5, 'Disappointing', 'Excellent'),

            self::q(230, 6, 'How would you rate the refreshments at the Costume Ball (pulled pork, meatballs, pretzels, and cheese)?',
                'scale', null, false, 0, '', 1, 5, 'Poor', 'Excellent'),

            self::q(240, 6, 'How would you rate the costume contest format? (Contestants formed a line and presented their costume to judges for a few minutes.)',
                'radio', ['Very good', 'Good', 'Neutral', 'Needs improvement', 'I did not watch or participate'], false),

            self::q(250, 6, 'How would you rate the circus-style entertainment performance?',
                'radio', ['Excellent', 'Good', 'Fair', 'Poor', 'I did not see the performance'], false),

            // ---- Page 7: Blood Prison (conditional) ----
            self::q(260, 7, 'How would you rate the Blood Prison experience overall?',
                'scale', null, true, 0, '', 1, 5, 'Disappointing', 'Excellent'),

            self::q(270, 7, 'Was the Blood Prison add-on worth the additional ticket cost?',
                'radio', ['Definitely yes', 'Yes', 'Neutral', 'No', 'Definitely not'], true),

            // ---- Page 8: 2027 Planning and Overall Feedback (everyone) ----
            self::q(275, 8, 'Did you use the OHA Convention mobile app during the event?',
                'radio', ['Yes', 'No'], true),

            self::q(278, 8, 'If you used the mobile app, how would you rate your experience with it?',
                'radio', ['Excellent', 'Good', 'Fair', 'Poor - it had issues', 'I did not use the mobile app'], false),

            self::q(280, 8, "The convention was held on Mother's Day weekend (Sunday, May 11). Did this affect your attendance?",
                'radio', [
                    'No impact on my attendance',
                    'I had to adjust my schedule to attend',
                    'I could only attend one day because of it',
                    'It made it difficult but I still came',
                    'Not applicable',
                ], true),

            self::q(290, 8, 'OHA Convention 2027 is planned for May 22-23, 2027 at the same venue. Do you plan to attend?',
                'radio', ['Definitely yes', 'Probably yes', 'Unsure', 'Probably not', 'Definitely not'], true),

            self::q(300, 8, 'What would most increase your likelihood of attending in 2027? (Select all that apply)',
                'checkbox', [
                    'More vendors',
                    'More Education session topics',
                    'Better food options',
                    'Changes to the Costume Ball',
                    'Additional events or activities',
                    'Lower ticket price',
                    'Nothing - I am already planning to attend',
                    'Other',
                ], true),

            self::q(310, 8, 'What from this year should we keep exactly the same for 2027?',
                'textarea', null, false),

            self::q(320, 8, 'What should we change or add for 2027?',
                'textarea', null, false),

            self::q(335, 8, 'Overall, how would you rate OHA Convention 2026?',
                'scale', null, true, 0, '', 1, 5, 'Disappointing', 'Excellent'),

            self::q(330, 8, 'How did you hear about OHA Convention 2026? (Select all that apply)',
                'checkbox', [
                    'OHA website',
                    'Facebook',
                    'Instagram',
                    'Other social media',
                    'Friend or family referral',
                    'OHA email newsletter',
                    'I attended in a previous year',
                    'Other',
                ], true),

            self::q(340, 8, 'Would you recommend OHA Convention to a friend or colleague?',
                'radio', ['Definitely yes', 'Probably yes', 'Unsure', 'Probably not', 'Definitely not'], true),

            self::q(350, 8, 'Any additional comments or feedback?',
                'textarea', null, false),
        ];
    }

    // -------------------------------------------------------------------------
    // Vendor question seed
    // -------------------------------------------------------------------------

    public static function seed_vendor_questions() {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_survey_convention_questions';
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE survey_context = %s", 'vendor'
        ));
        if ($exists > 0) return;
        foreach (self::default_vendor_questions() as $q) {
            $wpdb->insert($table, $q);
        }
    }

    public static function dedup_vendor_questions() {
        global $wpdb;
        $table = $wpdb->prefix . 'oha_survey_convention_questions';
        // Keep only the lowest id per (survey_context, sort_order) — removes duplicates from concurrent seeding
        $wpdb->query("
            DELETE q1 FROM {$table} q1
            INNER JOIN {$table} q2
                ON q1.survey_context = q2.survey_context
                AND q1.sort_order = q2.sort_order
                AND q1.id > q2.id
            WHERE q1.survey_context = 'vendor'
        ");
    }

    private static function qv($sort, $group, $text, $type, $opts, $required, $gate_for = 0, $gate_val = '', $smin = 1, $smax = 5, $slmin = '', $slmax = '') {
        return [
            'survey_context'  => 'vendor',
            'sort_order'      => $sort,
            'page_group'      => $group,
            'question_text'   => $text,
            'question_type'   => $type,
            'options'         => $opts ? json_encode($opts) : null,
            'scale_min'       => $smin,
            'scale_max'       => $smax,
            'scale_label_min' => $slmin,
            'scale_label_max' => $slmax,
            'required'        => $required ? 1 : 0,
            'hidden'          => 0,
            'gate_for_group'  => $gate_for,
            'gate_show_value' => $gate_val,
        ];
    }

    private static function default_vendor_questions() {
        return [

            // ---- Page 1: Setup and Operations (everyone) ----
            self::qv(10, 1, 'How would you rate the pre-event communication and setup instructions provided by OHA?',
                'scale', null, true, 0, '', 1, 5, 'Very unclear', 'Very clear'),

            self::qv(20, 1, 'How would you rate the booth setup and load-in process on the day of the event?',
                'scale', null, true, 0, '', 1, 5, 'Very difficult', 'Very smooth'),

            self::qv(30, 1, 'How would you rate the physical booth space and your assigned location on the vendor floor?',
                'scale', null, true, 0, '', 1, 5, 'Poor', 'Excellent'),

            self::qv(40, 1, 'Did you experience any issues with your booth setup or assigned location?',
                'radio', [
                    'No issues at all',
                    'Minor issues that were resolved quickly',
                    'Minor issues that were not fully resolved',
                    'Significant issues that impacted my participation',
                ], true),

            self::qv(50, 1, 'How would you rate the load-out process at the end of the event?',
                'scale', null, true, 0, '', 1, 5, 'Very difficult', 'Very smooth'),

            // ---- Page 2: Attendee Engagement (everyone, contains gate questions) ----
            self::qv(60, 2, 'How would you rate the attendee foot traffic at your booth throughout the event?',
                'scale', null, true, 0, '', 1, 5, 'Very low', 'Very high'),

            self::qv(70, 2, 'Compared to your expectations coming in, how did your sales or engagement perform?',
                'radio', [
                    'Far exceeded expectations',
                    'Exceeded expectations',
                    'Met expectations',
                    'Fell short of expectations',
                    'Significantly fell short of expectations',
                ], true),

            self::qv(80, 2, 'With 37 vendors on the floor this year, how would you rate the overall vendor mix and diversity?',
                'scale', null, true, 0, '', 1, 5, 'Too similar, needs more variety', 'Excellent variety'),

            self::qv(90, 2, 'How would you rate the overall professionalism and friendliness of the attendees you interacted with?',
                'scale', null, true, 0, '', 1, 5, 'Poor', 'Excellent'),

            // Gate: Education gates page 3
            self::qv(100, 2, 'Did you attend any of the Education sessions at the convention?',
                'radio', ['Yes', 'No'], true, 3, 'Yes'),

            // Gate: Costume Ball gates page 4
            self::qv(110, 2, 'Did you attend the Costume Ball?',
                'radio', ['Yes', 'No'], true, 4, 'Yes'),

            // ---- Page 3: Education Sessions (conditional) ----
            self::qv(120, 3, 'How would you rate the Education sessions you attended overall?',
                'scale', null, true, 0, '', 1, 5, 'Disappointing', 'Excellent'),

            self::qv(130, 3, 'How relevant were the session topics to your business or craft as a vendor?',
                'scale', null, true, 0, '', 1, 5, 'Not relevant', 'Very relevant'),

            self::qv(140, 3, 'Did you present or teach an Education session yourself?',
                'radio', ['Yes', 'No'], true),

            self::qv(150, 3, 'If you presented a session, how would you rate the setup and support provided to presenters?',
                'scale', null, false, 0, '', 1, 5, 'Poor support', 'Excellent support'),

            self::qv(160, 3, 'Do you have suggestions for Education topics relevant to vendors for future conventions?',
                'textarea', null, false),

            // ---- Page 4: Costume Ball (conditional) ----
            self::qv(170, 4, 'How would you rate the Costume Ball overall?',
                'scale', null, true, 0, '', 1, 5, 'Disappointing', 'Excellent'),

            self::qv(180, 4, 'Would you like Costume Ball access included as a standard benefit for vendors at future conventions?',
                'radio', ['Yes, definitely', 'Yes, it would be a nice perk', 'Neutral', 'No preference', 'No'], true),

            // ---- Page 5: Communication and Overall Support (everyone) ----
            self::qv(190, 5, 'How responsive was the OHA team when you had questions or issues before or during the event?',
                'scale', null, true, 0, '', 1, 5, 'Very unresponsive', 'Very responsive'),

            self::qv(200, 5, 'Were all logistics (booth location, power, access times, rules) clearly communicated before the event?',
                'radio', [
                    'Yes, fully communicated with no gaps',
                    'Mostly yes, with a few minor gaps',
                    'Somewhat - I had to ask several follow-up questions',
                    'No, there were significant communication gaps',
                ], true),

            self::qv(210, 5, 'What was the biggest challenge you faced as a vendor during the convention?',
                'textarea', null, true),

            self::qv(220, 5, 'Is there anything OHA could have done before or during the event to better support you as a vendor?',
                'textarea', null, false),

            // ---- Page 6: Looking Ahead to 2027 (everyone) ----
            self::qv(230, 6, 'Would you apply to be a vendor at OHA Convention 2027?',
                'radio', ['Definitely yes', 'Probably yes', 'Unsure', 'Probably not', 'Definitely not'], true),

            self::qv(240, 6, 'How likely are you to recommend vending at OHA Convention to other vendors or businesses?',
                'scale', null, true, 0, '', 1, 5, 'Would not recommend', 'Would strongly recommend'),

            self::qv(250, 6, 'What changes would most improve the vendor experience in 2027? (Select all that apply)',
                'checkbox', [
                    'Better foot traffic or event layout',
                    'Clearer pre-event communication',
                    'More flexibility in booth size options',
                    'Earlier setup access',
                    'Better signage directing attendees to the vendor floor',
                    'More marketing or promotion of the vendor floor',
                    'Lower vendor fees',
                    'More attendees overall',
                    'Nothing - keep it the same',
                    'Other',
                ], true),

            self::qv(260, 6, 'What from this year should we keep exactly the same for 2027?',
                'textarea', null, false),

            self::qv(270, 6, 'Overall, how would you rate your vendor experience at OHA Convention 2026?',
                'scale', null, true, 0, '', 1, 5, 'Disappointing', 'Excellent'),

            self::qv(280, 6, 'Any additional comments or feedback for the OHA team?',
                'textarea', null, false),
        ];
    }

    // -------------------------------------------------------------------------
    // Survey definition helper (used by main plugin's ajax_get_response)
    // -------------------------------------------------------------------------

    public static function get_as_definition($survey_type = self::SURVEY_TYPE) {
        global $wpdb;
        $table   = $wpdb->prefix . 'oha_survey_convention_questions';
        $context = self::type_to_context($survey_type);

        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE survey_context = %s ORDER BY sort_order ASC",
            $context
        ));

        $pages_map = [];
        foreach ($questions as $q) {
            $pg = (int) $q->page_group;
            if (!isset($pages_map[$pg])) {
                $pages_map[$pg] = [];
            }
            $pages_map[$pg][] = [
                'id'       => 'q_' . $q->id,
                'question' => $q->question_text,
                'type'     => $q->question_type,
                'required' => (bool) $q->required,
            ];
        }

        $pages = [];
        foreach ($pages_map as $pg => $qs) {
            $pages[] = [
                'title'     => self::get_page_title($pg, $context),
                'questions' => $qs,
            ];
        }

        return [
            'title'       => self::survey_title($survey_type),
            'description' => '',
            'pages'       => $pages,
        ];
    }

    private static function type_to_context($survey_type) {
        $map = [
            self::SURVEY_TYPE_ATTENDEE  => 'attendee',
            self::SURVEY_TYPE_VENDOR    => 'vendor',
            self::SURVEY_TYPE_VOLUNTEER => 'volunteer',
        ];
        return $map[$survey_type] ?? 'attendee';
    }

    private static function survey_title($survey_type) {
        $map = [
            self::SURVEY_TYPE_ATTENDEE  => 'OHA Convention 2026 - Attendee Feedback',
            self::SURVEY_TYPE_VENDOR    => 'OHA Convention 2026 - Vendor Feedback',
            self::SURVEY_TYPE_VOLUNTEER => 'OHA Convention 2026 - Volunteer Feedback',
        ];
        return $map[$survey_type] ?? 'OHA Convention 2026 Feedback';
    }

    // -------------------------------------------------------------------------
    // Shortcode
    // -------------------------------------------------------------------------

    public function shortcode($atts) {
        $atts        = shortcode_atts(['context' => 'attendee'], $atts);
        $context     = sanitize_key($atts['context']);
        $survey_type = self::context_to_type($context);
        $title       = self::survey_title($survey_type);

        // Enqueue here directly so scripts load even when has_shortcode() misses this page
        wp_enqueue_style(
            'oha-survey-hub-style',
            OHA_SURVEY_HUB_PLUGIN_URL . 'assets/css/survey-style.css',
            [],
            OHA_SURVEY_HUB_VERSION
        );
        wp_enqueue_script(
            'oha-convention-survey-script',
            OHA_SURVEY_HUB_PLUGIN_URL . 'assets/js/convention-survey.js',
            ['jquery'],
            OHA_SURVEY_HUB_VERSION,
            true
        );
        wp_localize_script('oha-convention-survey-script', 'oha_conv_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('oha_survey_nonce'),
        ]);

        global $wpdb;
        $table     = $wpdb->prefix . 'oha_survey_convention_questions';
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE survey_context = %s AND hidden = 0 ORDER BY sort_order ASC",
            $context
        ));

        // Group by page_group; track gate config for JS
        $pages      = [];
        $gate_config = []; // [question_db_id => {for: page_group, value: gate_show_value}]
        foreach ($questions as $q) {
            $pg = (int) $q->page_group;
            if (!isset($pages[$pg])) {
                $pages[$pg] = [];
            }
            $pages[$pg][] = $q;
            if ((int) $q->gate_for_group > 0) {
                $gate_config['q_' . $q->id] = [
                    'for'   => (int) $q->gate_for_group,
                    'value' => $q->gate_show_value,
                ];
            }
        }
        ksort($pages);

        // Total pages = email page (index 0) + one per page_group
        $total_pages = count($pages) + 1;

        ob_start();
        ?>
        <div id="oha-convention-survey" class="oha-survey-container"
             data-survey-type="<?php echo esc_attr($survey_type); ?>"
             data-total-pages="<?php echo $total_pages; ?>">

            <div class="oha-survey-header">
                <h2 class="oha-survey-title"><?php echo esc_html($title); ?></h2>
                <p class="oha-survey-description">Thank you for attending OHA Convention 2026 at the Ohio State Reformatory. Your feedback helps us improve the experience. This survey takes approximately 5 to 7 minutes to complete.</p>
                <div class="oha-progress-container">
                    <div class="oha-progress-bar">
                        <div class="oha-conv-progress-fill" style="width: 0%;"></div>
                    </div>
                    <div class="oha-progress-text">
                        Page <span class="oha-conv-current-page">1</span> of <span class="oha-conv-total-pages"><?php echo $total_pages; ?></span>
                    </div>
                </div>
            </div>

            <div class="oha-survey-content">

                <?php /* Page index 0: Email */ ?>
                <div class="oha-conv-page" data-cpage="0" data-page-group="0">
                    <div class="oha-email-section">
                        <div class="oha-field-group oha-field-required">
                            <label for="oha-conv-email" class="oha-field-label">
                                Email Address <span class="oha-required-indicator">*</span>
                            </label>
                            <input type="email" id="oha-conv-email" name="survey_email" class="oha-field-input" required>
                            <div class="oha-field-help">Required to prevent duplicate responses. Your email will not be linked to your individual answers.</div>
                        </div>
                    </div>
                </div>

                <?php
                $page_index = 1;
                foreach ($pages as $pg => $group_questions):
                    $page_title = self::get_page_title($pg, $context);
                    // Determine if this page group is a conditional one (gated by another question)
                    $is_gated = false;
                    foreach ($gate_config as $gate) {
                        if ($gate['for'] === $pg) { $is_gated = true; break; }
                    }
                ?>
                <div class="oha-conv-page" data-cpage="<?php echo $page_index; ?>"
                     data-page-group="<?php echo $pg; ?>"
                     <?php echo $is_gated ? 'data-conditional="1"' : ''; ?>
                     style="display:none;">
                    <h3 class="oha-page-title"><?php echo esc_html($page_title); ?></h3>
                    <div class="oha-questions-container">
                        <?php foreach ($group_questions as $q): ?>
                            <?php $this->render_question($q, $gate_config); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php $page_index++; endforeach; ?>

            </div>

            <div class="oha-survey-navigation">
                <button type="button" class="oha-btn oha-btn-secondary oha-conv-prev" style="display:none;">
                    <span class="oha-btn-icon">&#8592;</span> Previous
                </button>
                <button type="button" class="oha-btn oha-btn-primary oha-conv-next">
                    Next <span class="oha-btn-icon">&#8594;</span>
                </button>
                <button type="button" class="oha-btn oha-btn-accent oha-conv-submit" style="display:none;">
                    <span class="oha-btn-icon">&#10003;</span> Submit Survey
                </button>
            </div>

            <div class="oha-conv-messages"></div>

            <div class="oha-survey-success" style="display:none;">
                <div class="oha-success-icon">&#10003;</div>
                <h3>Thank You for Your Feedback</h3>
                <p>Your responses have been recorded. We appreciate you taking the time to help us improve OHA Convention.</p>
            </div>

        </div>

        <div class="oha-survey-trust-info">
            <h4>Your Privacy Matters</h4>
            <ul>
                <li>Your email is used only to prevent duplicate submissions</li>
                <li>Your individual responses will not be shared or published</li>
            </ul>
        </div>

        <script>
        var oha_conv_gate_config = <?php echo wp_json_encode($gate_config); ?>;
        </script>
        <?php
        return ob_get_clean();
    }

    private static function context_to_type($context) {
        $map = [
            'attendee'  => self::SURVEY_TYPE_ATTENDEE,
            'vendor'    => self::SURVEY_TYPE_VENDOR,
            'volunteer' => self::SURVEY_TYPE_VOLUNTEER,
        ];
        return $map[$context] ?? self::SURVEY_TYPE_ATTENDEE;
    }

    private function render_question($q, $gate_config) {
        $required  = (bool) $q->required;
        $field_id  = 'q_' . $q->id;
        $classes   = 'oha-field-group' . ($required ? ' oha-field-required' : '');
        $gate_attr = '';
        if ((int) $q->gate_for_group > 0) {
            $gate_attr = ' data-gate-for="' . (int) $q->gate_for_group . '" data-gate-value="' . esc_attr($q->gate_show_value) . '"';
        }
        ?>
        <div class="<?php echo $classes; ?>" data-question-id="<?php echo esc_attr($field_id); ?>"<?php echo $gate_attr; ?>>
            <label class="oha-field-label">
                <?php echo esc_html($q->question_text); ?>
                <?php if ($required): ?><span class="oha-required-indicator">*</span><?php endif; ?>
            </label>
            <?php $this->render_input($q, $field_id, $required); ?>
        </div>
        <?php
    }

    private function render_input($q, $field_id, $required) {
        $req_attr = $required ? 'required' : '';
        $options  = $q->options ? json_decode($q->options, true) : [];

        switch ($q->question_type) {
            case 'text':
                echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" class="oha-field-input" ' . $req_attr . '>';
                break;

            case 'textarea':
                echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_id) . '" class="oha-field-textarea" rows="4" ' . $req_attr . '></textarea>';
                break;

            case 'radio':
                echo '<div class="oha-radio-group">';
                foreach ($options as $opt) {
                    $val = esc_attr($opt);
                    $lbl = esc_html($opt);
                    echo '<label class="oha-radio-label">'
                       . '<input type="radio" name="' . esc_attr($field_id) . '" value="' . $val . '" class="oha-radio-input" ' . $req_attr . '>'
                       . '<span class="oha-radio-custom"></span>'
                       . '<span class="oha-radio-text">' . $lbl . '</span>'
                       . '</label>';
                }
                echo '</div>';
                break;

            case 'checkbox':
                echo '<div class="oha-checkbox-group">';
                $idx = 0;
                foreach ($options as $opt) {
                    $idx++;
                    $val    = esc_attr($opt);
                    $lbl    = esc_html($opt);
                    $req_cb = ($required && $idx === 1) ? 'required' : '';
                    echo '<label class="oha-checkbox-label">'
                       . '<input type="checkbox" name="' . esc_attr($field_id) . '[]" value="' . $val . '" class="oha-checkbox-input" ' . $req_cb . '>'
                       . '<span class="oha-checkbox-custom"></span>'
                       . '<span class="oha-checkbox-text">' . $lbl . '</span>'
                       . '</label>';
                }
                echo '</div>';
                break;

            case 'scale':
                $min     = (int) $q->scale_min;
                $max     = (int) $q->scale_max;
                $lbl_min = esc_html($q->scale_label_min);
                $lbl_max = esc_html($q->scale_label_max);
                echo '<div class="oha-scale-group">'
                   . '<div class="oha-scale-labels">'
                   . ($lbl_min ? '<span class="oha-scale-label-start">' . $lbl_min . '</span>' : '')
                   . ($lbl_max ? '<span class="oha-scale-label-end">' . $lbl_max . '</span>' : '')
                   . '</div><div class="oha-scale-options">';
                for ($i = $min; $i <= $max; $i++) {
                    echo '<label class="oha-scale-option">'
                       . '<input type="radio" name="' . esc_attr($field_id) . '" value="' . $i . '" class="oha-scale-input" ' . $req_attr . '>'
                       . '<span class="oha-scale-number">' . $i . '</span>'
                       . '</label>';
                }
                echo '</div></div>';
                break;
        }
    }

    // -------------------------------------------------------------------------
    // Frontend AJAX: submit
    // -------------------------------------------------------------------------

    public function ajax_submit() {
        check_ajax_referer('oha_survey_nonce', 'nonce');

        $email       = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $survey_type = isset($_POST['survey_type']) ? sanitize_key($_POST['survey_type']) : self::SURVEY_TYPE;

        $valid_types = [self::SURVEY_TYPE_ATTENDEE, self::SURVEY_TYPE_VENDOR, self::SURVEY_TYPE_VOLUNTEER];
        if (!in_array($survey_type, $valid_types, true)) {
            $survey_type = self::SURVEY_TYPE_ATTENDEE;
        }

        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Please enter a valid email address.']);
        }

        global $wpdb;
        $responses_table = $wpdb->prefix . 'oha_survey_responses';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $responses_table WHERE email = %s AND survey_type = %s AND is_completed = 1",
            $email, $survey_type
        ));
        if ($existing) {
            wp_send_json_error(['message' => 'This email has already submitted a response for this survey.']);
        }

        // Sanitize answers
        $raw_responses = isset($_POST['responses']) ? $_POST['responses'] : [];
        $answers       = [];
        if (is_array($raw_responses)) {
            foreach ($raw_responses as $key => $val) {
                $clean_key = sanitize_key($key);
                if (is_array($val)) {
                    $answers[$clean_key] = array_map('sanitize_text_field', $val);
                } else {
                    $answers[$clean_key] = sanitize_text_field($val);
                }
            }
        }

        // Validate required visible questions
        // Only validate questions whose page group was not skipped (sent via skipped_groups)
        $skipped_groups = [];
        if (!empty($_POST['skipped_groups'])) {
            $raw_skipped = $_POST['skipped_groups'];
            if (is_array($raw_skipped)) {
                foreach ($raw_skipped as $g) {
                    $skipped_groups[] = (int) $g;
                }
            }
        }

        $context         = self::type_to_context($survey_type);
        $questions_table = $wpdb->prefix . 'oha_survey_convention_questions';
        $required_qs     = $wpdb->get_results($wpdb->prepare(
            "SELECT id, page_group FROM $questions_table WHERE survey_context = %s AND required = 1 AND hidden = 0",
            $context
        ));

        foreach ($required_qs as $rq) {
            if (in_array((int) $rq->page_group, $skipped_groups, true)) {
                continue; // user skipped this conditional section
            }
            $key = 'q_' . $rq->id;
            $val = $answers[$key] ?? null;
            if ($val === null || $val === '' || $val === []) {
                wp_send_json_error(['message' => 'Please answer all required questions before submitting.']);
            }
        }

        $context_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT page_group) FROM $questions_table WHERE survey_context = %s AND hidden = 0",
            $context
        )) + 1;

        $wpdb->insert($responses_table, [
            'survey_type'         => $survey_type,
            'email'               => $email,
            'session_id'          => wp_generate_uuid4(),
            'response_data'       => wp_json_encode($answers),
            'current_page'        => $context_count,
            'total_pages'         => $context_count,
            'is_completed'        => 1,
            'progress_percentage' => 100,
            'started_at'          => current_time('mysql'),
            'completed_at'        => current_time('mysql'),
            'ip_address'          => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent'          => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ]);

        wp_schedule_single_event(time(), 'oha_survey_convention_send_emails', [$email, $survey_type]);

        wp_send_json_success(['message' => 'Survey submitted. Thank you for your feedback.']);
    }

    // -------------------------------------------------------------------------
    // Emails
    // -------------------------------------------------------------------------

    public function send_emails($submitter_email, $survey_type) {
        $from_name   = get_bloginfo('name') ?: 'Ohio Haunters Association';
        $from_email  = get_option('oha_survey_hub_support_email', get_option('admin_email'));
        $headers     = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        ];
        $survey_title = self::survey_title($survey_type);
        $site_url     = home_url();
        $results_url  = admin_url('admin.php?page=oha-survey-results&survey_type=' . $survey_type);

        // --- Confirmation to submitter ---
        if (get_option('oha_survey_hub_email_notifications', true)) {
            $subject = $survey_title . ' - Submission Confirmed';
            $body    = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#222;max-width:600px;margin:0 auto;padding:20px;">'
                     . '<h2 style="color:#1a1a1a;">Thank You for Your Feedback</h2>'
                     . '<p>Your response to the <strong>' . esc_html($survey_title) . '</strong> has been recorded.</p>'
                     . '<p>Your feedback will help us improve the convention experience for future attendees. We appreciate you taking the time to share your thoughts.</p>'
                     . '<p>If you have additional comments or questions, please contact us at <a href="mailto:' . esc_attr($from_email) . '">' . esc_html($from_email) . '</a>.</p>'
                     . '<hr style="border:none;border-top:1px solid #ddd;margin:24px 0;">'
                     . '<p style="font-size:12px;color:#666;">Ohio Haunters Association &mdash; <a href="' . esc_url($site_url) . '">' . esc_url($site_url) . '</a></p>'
                     . '</body></html>';
            wp_mail($submitter_email, $subject, $body, $headers);
        }

        // --- Notification to board ---
        if (get_option('oha_survey_hub_admin_notifications', true)) {
            $board_email = get_option('oha_convention_board_email', '');
            if (!$board_email) {
                $board_email = get_option('admin_email');
            }
            $submitted_at = current_time('F j, Y \a\t g:i A');
            $subject      = 'New Survey Response: ' . $survey_title;
            $body         = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#222;max-width:600px;margin:0 auto;padding:20px;">'
                          . '<h2 style="color:#1a1a1a;">New Survey Response Received</h2>'
                          . '<table style="width:100%;border-collapse:collapse;margin:16px 0;">'
                          . '<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;width:120px;">Survey</td><td style="padding:8px;border:1px solid #ddd;">' . esc_html($survey_title) . '</td></tr>'
                          . '<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Submitted</td><td style="padding:8px;border:1px solid #ddd;">' . esc_html($submitted_at) . '</td></tr>'
                          . '</table>'
                          . '<p><a href="' . esc_url($results_url) . '" style="display:inline-block;background:#1a1a1a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:3px;">View All Responses</a></p>'
                          . '<hr style="border:none;border-top:1px solid #ddd;margin:24px 0;">'
                          . '<p style="font-size:12px;color:#666;">This is an automated notification from the OHA Survey Hub plugin.</p>'
                          . '</body></html>';
            wp_mail($board_email, $subject, $body, $headers);
        }
    }

    // -------------------------------------------------------------------------
    // Admin page
    // -------------------------------------------------------------------------

    public function render_admin_page() {
        $active_context = isset($_GET['context']) ? sanitize_key($_GET['context']) : 'attendee';
        if (!array_key_exists($active_context, self::$context_labels)) {
            $active_context = 'attendee';
        }

        global $wpdb;
        $table      = $wpdb->prefix . 'oha_survey_convention_questions';
        $resp_table = $wpdb->prefix . 'oha_survey_responses';
        $questions  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE survey_context = %s ORDER BY sort_order ASC",
            $active_context
        ));

        $survey_type    = self::context_to_type($active_context);
        $response_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $resp_table WHERE survey_type = %s AND is_completed = 1",
            $survey_type
        ));

        $type_labels = [
            'radio'    => 'Radio',
            'checkbox' => 'Checkbox',
            'scale'    => 'Scale',
            'textarea' => 'Text Area',
            'text'     => 'Text',
        ];

        $base_url = admin_url('admin.php?page=oha-convention-survey');
        ?>
        <div class="wrap oha-survey-admin">
            <h1>Convention 2026 Survey Questions</h1>

            <?php /* Context tabs */ ?>
            <nav class="nav-tab-wrapper" style="margin-bottom:16px;">
                <?php foreach (self::$context_labels as $ctx => $lbl): ?>
                    <a href="<?php echo esc_url($base_url . '&context=' . $ctx); ?>"
                       class="nav-tab<?php echo $active_context === $ctx ? ' nav-tab-active' : ''; ?>">
                        <?php echo esc_html($lbl); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <p>
                Shortcode: <code>[oha_convention_survey<?php echo $active_context !== 'attendee' ? ' context="' . esc_html($active_context) . '"' : ''; ?>]</code>
                &nbsp;|&nbsp;
                <strong><?php echo $response_count; ?></strong> completed response<?php echo $response_count !== 1 ? 's' : ''; ?>.
                <?php if ($response_count > 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=oha-survey-results&survey_type=' . $survey_type); ?>" class="button button-small">View Responses</a>
                <?php endif; ?>
            </p>

            <button type="button" class="button button-primary" id="oha-conv-add-btn"
                data-context="<?php echo esc_attr($active_context); ?>">Add Question</button>

            <table class="widefat striped oha-conv-questions-table" style="margin-top:16px;">
                <thead>
                    <tr>
                        <th style="width:50px;">Order</th>
                        <th style="width:50px;">Page</th>
                        <th>Question</th>
                        <th style="width:70px;">Type</th>
                        <th style="width:55px;">Required</th>
                        <th style="width:55px;">Gate</th>
                        <th style="width:65px;">Status</th>
                        <th style="width:165px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="oha-conv-questions-body">
                <?php foreach ($questions as $q):
                    $page_label  = self::get_page_title($q->page_group, $active_context);
                    $hidden_row  = $q->hidden ? 'style="opacity:0.5;"' : '';
                    $gate_label  = $q->gate_for_group ? 'Gates p.' . $q->gate_for_group : '';
                ?>
                    <tr id="oha-conv-row-<?php echo $q->id; ?>" <?php echo $hidden_row; ?>>
                        <td><?php echo (int) $q->sort_order; ?></td>
                        <td title="<?php echo esc_attr($page_label); ?>"><?php echo (int) $q->page_group; ?></td>
                        <td><?php echo esc_html($q->question_text); ?></td>
                        <td><?php echo esc_html($type_labels[$q->question_type] ?? $q->question_type); ?></td>
                        <td><?php echo $q->required ? 'Yes' : 'No'; ?></td>
                        <td><?php echo esc_html($gate_label); ?></td>
                        <td>
                            <span class="oha-conv-status-<?php echo $q->id; ?>">
                                <?php echo $q->hidden ? '<span style="color:#999;">Hidden</span>' : '<span style="color:#0a8f0a;">Visible</span>'; ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="button button-small oha-conv-edit-btn"
                                data-id="<?php echo $q->id; ?>"
                                data-sort="<?php echo (int) $q->sort_order; ?>"
                                data-page="<?php echo (int) $q->page_group; ?>"
                                data-text="<?php echo esc_attr($q->question_text); ?>"
                                data-type="<?php echo esc_attr($q->question_type); ?>"
                                data-options="<?php echo esc_attr($q->options ?? ''); ?>"
                                data-scale-min="<?php echo (int) $q->scale_min; ?>"
                                data-scale-max="<?php echo (int) $q->scale_max; ?>"
                                data-scale-lmin="<?php echo esc_attr($q->scale_label_min); ?>"
                                data-scale-lmax="<?php echo esc_attr($q->scale_label_max); ?>"
                                data-required="<?php echo (int) $q->required; ?>"
                                data-hidden="<?php echo (int) $q->hidden; ?>"
                                data-gate-for="<?php echo (int) $q->gate_for_group; ?>"
                                data-gate-val="<?php echo esc_attr($q->gate_show_value); ?>"
                                data-context="<?php echo esc_attr($q->survey_context); ?>">Edit</button>
                            <button type="button" class="button button-small oha-conv-toggle-btn"
                                data-id="<?php echo $q->id; ?>"
                                data-hidden="<?php echo (int) $q->hidden; ?>">
                                <?php echo $q->hidden ? 'Show' : 'Hide'; ?>
                            </button>
                            <button type="button" class="button button-small oha-conv-delete-btn"
                                data-id="<?php echo $q->id; ?>"
                                style="color:#b32d2e;">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <hr style="margin:32px 0 20px;">
            <h2>Reset Respondent</h2>
            <p>Enter the email address of a respondent to delete their survey record. They will be able to start the survey again from scratch.</p>
            <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:8px;">
                <div>
                    <label for="oha-conv-reset-email" style="display:block;margin-bottom:4px;font-weight:600;">Email Address</label>
                    <input type="email" id="oha-conv-reset-email" style="width:280px;" placeholder="respondent@example.com">
                </div>
                <div>
                    <label for="oha-conv-reset-context" style="display:block;margin-bottom:4px;font-weight:600;">Survey</label>
                    <select id="oha-conv-reset-context">
                        <option value="attendee">Attendee</option>
                        <option value="vendor">Vendor</option>
                        <option value="volunteer">Volunteer</option>
                    </select>
                </div>
                <button type="button" class="button button-secondary" id="oha-conv-reset-btn">Reset Response</button>
            </div>
            <p id="oha-conv-reset-msg" style="display:none;margin-top:8px;"></p>
        </div>

        <!-- Modal -->
        <div id="oha-conv-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:100000;">
            <div style="background:#fff; margin:60px auto; max-width:640px; border-radius:4px; padding:24px; max-height:80vh; overflow-y:auto;">
                <h2 id="oha-conv-modal-title">Add Question</h2>
                <form id="oha-conv-question-form">
                    <input type="hidden" id="oha-conv-q-id" value="0">
                    <input type="hidden" id="oha-conv-q-context" value="<?php echo esc_attr($active_context); ?>">
                    <table class="form-table">
                        <tr>
                            <th><label for="oha-conv-q-text">Question Text</label></th>
                            <td><textarea id="oha-conv-q-text" rows="3" style="width:100%;" required></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="oha-conv-q-type">Type</label></th>
                            <td>
                                <select id="oha-conv-q-type">
                                    <option value="radio">Radio (single choice)</option>
                                    <option value="checkbox">Checkbox (multi-select)</option>
                                    <option value="scale">Scale (1-5 rating)</option>
                                    <option value="textarea">Text Area (open response)</option>
                                    <option value="text">Text (short answer)</option>
                                </select>
                            </td>
                        </tr>
                        <tr id="oha-conv-options-row">
                            <th><label for="oha-conv-q-options">Options</label></th>
                            <td>
                                <textarea id="oha-conv-q-options" rows="5" style="width:100%;" placeholder="One option per line"></textarea>
                                <p class="description">One option per line.</p>
                            </td>
                        </tr>
                        <tr id="oha-conv-scale-row" style="display:none;">
                            <th>Scale</th>
                            <td>
                                Min: <input type="number" id="oha-conv-q-smin" value="1" style="width:50px;">
                                Max: <input type="number" id="oha-conv-q-smax" value="5" style="width:50px;"><br><br>
                                Min label: <input type="text" id="oha-conv-q-slmin" style="width:180px;"><br>
                                Max label: <input type="text" id="oha-conv-q-slmax" style="width:180px;">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="oha-conv-q-page">Page Group</label></th>
                            <td>
                                <select id="oha-conv-q-page">
                                    <?php foreach ((self::$page_titles[$active_context] ?? []) as $num => $ptitle): ?>
                                        <option value="<?php echo $num; ?>"><?php echo esc_html($num . ' - ' . $ptitle); ?></option>
                                    <?php endforeach; ?>
                                    <option value="9">9 - (Custom)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="oha-conv-q-sort">Sort Order</label></th>
                            <td><input type="number" id="oha-conv-q-sort" value="100" style="width:80px;" step="10"></td>
                        </tr>
                        <tr>
                            <th>Required</th>
                            <td><label><input type="checkbox" id="oha-conv-q-required" value="1"> This question is required</label></td>
                        </tr>
                        <tr>
                            <th>Hidden</th>
                            <td><label><input type="checkbox" id="oha-conv-q-hidden" value="1"> Hide from survey</label></td>
                        </tr>
                        <tr>
                            <th>Gate</th>
                            <td>
                                Gates page group: <input type="number" id="oha-conv-q-gate-for" value="0" style="width:50px;" min="0">
                                (0 = not a gate)<br>
                                Show when answer equals: <input type="text" id="oha-conv-q-gate-val" style="width:180px;" placeholder='e.g. Yes or VIP ($80)'>
                            </td>
                        </tr>
                    </table>
                    <div id="oha-conv-form-msg" style="margin:8px 0; color:green;"></div>
                    <button type="submit" class="button button-primary">Save Question</button>
                    <button type="button" id="oha-conv-modal-close" class="button" style="margin-left:8px;">Cancel</button>
                </form>
            </div>
        </div>

        <script>
        (function($){
            var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            var nonce   = <?php echo wp_json_encode(wp_create_nonce('oha_survey_nonce')); ?>;

            function typeHasOptions(t) { return t === 'radio' || t === 'checkbox'; }
            function typeIsScale(t)    { return t === 'scale'; }
            function updateTypeFields() {
                var t = $('#oha-conv-q-type').val();
                $('#oha-conv-options-row').toggle(typeHasOptions(t));
                $('#oha-conv-scale-row').toggle(typeIsScale(t));
            }
            $('#oha-conv-q-type').on('change', updateTypeFields);

            $('#oha-conv-add-btn').on('click', function(){
                $('#oha-conv-modal-title').text('Add Question');
                $('#oha-conv-q-id').val('0');
                $('#oha-conv-q-text').val('');
                $('#oha-conv-q-type').val('radio');
                $('#oha-conv-q-options').val('');
                $('#oha-conv-q-smin').val('1'); $('#oha-conv-q-smax').val('5');
                $('#oha-conv-q-slmin').val(''); $('#oha-conv-q-slmax').val('');
                $('#oha-conv-q-page').val('1');
                $('#oha-conv-q-sort').val('100');
                $('#oha-conv-q-required').prop('checked', false);
                $('#oha-conv-q-hidden').prop('checked', false);
                $('#oha-conv-q-gate-for').val('0');
                $('#oha-conv-q-gate-val').val('');
                $('#oha-conv-form-msg').text('');
                updateTypeFields();
                $('#oha-conv-modal').show();
            });

            $(document).on('click', '.oha-conv-edit-btn', function(){
                var d = $(this).data();
                $('#oha-conv-modal-title').text('Edit Question');
                $('#oha-conv-q-id').val(d.id);
                $('#oha-conv-q-context').val(d.context);
                $('#oha-conv-q-text').val(d.text);
                $('#oha-conv-q-type').val(d.type);
                var opts = '';
                if (d.options) { try { opts = JSON.parse(d.options).join('\n'); } catch(e){} }
                $('#oha-conv-q-options').val(opts);
                $('#oha-conv-q-smin').val(d.scaleMin || 1); $('#oha-conv-q-smax').val(d.scaleMax || 5);
                $('#oha-conv-q-slmin').val(d.scaleLmin || ''); $('#oha-conv-q-slmax').val(d.scaleLmax || '');
                $('#oha-conv-q-page').val(d.page);
                $('#oha-conv-q-sort').val(d.sort);
                $('#oha-conv-q-required').prop('checked', d.required == 1);
                $('#oha-conv-q-hidden').prop('checked', d.hidden == 1);
                $('#oha-conv-q-gate-for').val(d.gateFor || 0);
                $('#oha-conv-q-gate-val').val(d.gateVal || '');
                $('#oha-conv-form-msg').text('');
                updateTypeFields();
                $('#oha-conv-modal').show();
            });

            $('#oha-conv-modal-close').on('click', function(){ $('#oha-conv-modal').hide(); });
            $('#oha-conv-modal').on('click', function(e){ if ($(e.target).is('#oha-conv-modal')) $(this).hide(); });

            $('#oha-conv-question-form').on('submit', function(e){
                e.preventDefault();
                var type = $('#oha-conv-q-type').val();
                var optionsRaw = $('#oha-conv-q-options').val().trim();
                var optionsJson = '';
                if (typeHasOptions(type) && optionsRaw) {
                    var arr = optionsRaw.split('\n').map(function(s){ return s.trim(); }).filter(Boolean);
                    optionsJson = JSON.stringify(arr);
                }
                $.post(ajaxUrl, {
                    action:          'oha_convention_save_question',
                    nonce:           nonce,
                    id:              $('#oha-conv-q-id').val(),
                    survey_context:  $('#oha-conv-q-context').val(),
                    question_text:   $('#oha-conv-q-text').val(),
                    question_type:   type,
                    options:         optionsJson,
                    scale_min:       $('#oha-conv-q-smin').val(),
                    scale_max:       $('#oha-conv-q-smax').val(),
                    scale_label_min: $('#oha-conv-q-slmin').val(),
                    scale_label_max: $('#oha-conv-q-slmax').val(),
                    page_group:      $('#oha-conv-q-page').val(),
                    sort_order:      $('#oha-conv-q-sort').val(),
                    required:        $('#oha-conv-q-required').is(':checked') ? 1 : 0,
                    hidden:          $('#oha-conv-q-hidden').is(':checked') ? 1 : 0,
                    gate_for_group:  $('#oha-conv-q-gate-for').val(),
                    gate_show_value: $('#oha-conv-q-gate-val').val(),
                }, function(res){
                    if (res.success) {
                        $('#oha-conv-form-msg').css('color','green').text('Saved. Reloading...');
                        setTimeout(function(){ location.reload(); }, 700);
                    } else {
                        $('#oha-conv-form-msg').css('color','red').text(res.data && res.data.message ? res.data.message : 'Error saving question.');
                    }
                });
            });

            $(document).on('click', '.oha-conv-toggle-btn', function(){
                var btn = $(this), id = btn.data('id');
                $.post(ajaxUrl, { action: 'oha_convention_toggle_question', nonce: nonce, id: id }, function(res){
                    if (res.success) {
                        var hidden = res.data.hidden;
                        btn.data('hidden', hidden).text(hidden ? 'Show' : 'Hide');
                        $('#oha-conv-row-' + id).css('opacity', hidden ? '0.5' : '1');
                        $('.oha-conv-status-' + id).html(hidden
                            ? '<span style="color:#999;">Hidden</span>'
                            : '<span style="color:#0a8f0a;">Visible</span>');
                    }
                });
            });

            $(document).on('click', '.oha-conv-delete-btn', function(){
                if (!confirm('Delete this question? This cannot be undone.')) return;
                var id = $(this).data('id');
                $.post(ajaxUrl, { action: 'oha_convention_delete_question', nonce: nonce, id: id }, function(res){
                    if (res.success) { $('#oha-conv-row-' + id).remove(); }
                    else { alert('Delete failed.'); }
                });
            });

            $('#oha-conv-reset-btn').on('click', function(){
                var email = $('#oha-conv-reset-email').val().trim();
                var ctx   = $('#oha-conv-reset-context').val();
                var msg   = $('#oha-conv-reset-msg');
                if (!email) { msg.css('color','red').text('Please enter an email address.').show(); return; }
                if (!confirm('Delete the survey response for ' + email + '? They will be able to re-take the survey.')) return;
                $.post(ajaxUrl, {
                    action:  'oha_convention_reset_response',
                    nonce:   nonce,
                    email:   email,
                    context: ctx,
                }, function(res){
                    if (res.success) {
                        msg.css('color','green').text(res.data.message).show();
                        $('#oha-conv-reset-email').val('');
                    } else {
                        msg.css('color','red').text(res.data && res.data.message ? res.data.message : 'Reset failed.').show();
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Admin AJAX: save question
    // -------------------------------------------------------------------------

    public function ajax_save_question() {
        check_ajax_referer('oha_survey_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oha_survey_convention_questions';

        $id             = (int) ($_POST['id'] ?? 0);
        $survey_context = sanitize_key($_POST['survey_context'] ?? 'attendee');
        $question_text  = sanitize_textarea_field($_POST['question_text'] ?? '');
        $question_type  = sanitize_key($_POST['question_type'] ?? 'radio');
        $options        = isset($_POST['options']) && $_POST['options'] !== '' ? wp_unslash($_POST['options']) : null;
        $scale_min      = (int) ($_POST['scale_min'] ?? 1);
        $scale_max      = (int) ($_POST['scale_max'] ?? 5);
        $slmin          = sanitize_text_field($_POST['scale_label_min'] ?? '');
        $slmax          = sanitize_text_field($_POST['scale_label_max'] ?? '');
        $page_group     = (int) ($_POST['page_group'] ?? 1);
        $sort_order     = (int) ($_POST['sort_order'] ?? 100);
        $required       = (int) ($_POST['required'] ?? 0);
        $hidden         = (int) ($_POST['hidden'] ?? 0);
        $gate_for_group = (int) ($_POST['gate_for_group'] ?? 0);
        $gate_show_val  = sanitize_text_field($_POST['gate_show_value'] ?? '');

        if (empty($question_text)) {
            wp_send_json_error(['message' => 'Question text is required.']);
        }

        $data = compact(
            'survey_context', 'sort_order', 'page_group', 'question_text', 'question_type',
            'options', 'scale_min', 'scale_max', 'required', 'hidden', 'gate_for_group'
        );
        $data['scale_label_min'] = $slmin;
        $data['scale_label_max'] = $slmax;
        $data['gate_show_value'] = $gate_show_val;

        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id]);
        } else {
            $wpdb->insert($table, $data);
        }

        wp_send_json_success();
    }

    // -------------------------------------------------------------------------
    // Admin AJAX: toggle hidden
    // -------------------------------------------------------------------------

    public function ajax_toggle_question() {
        check_ajax_referer('oha_survey_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'oha_survey_convention_questions';
        $id      = (int) ($_POST['id'] ?? 0);
        $current = (int) $wpdb->get_var($wpdb->prepare("SELECT hidden FROM $table WHERE id = %d", $id));
        $new_val = $current ? 0 : 1;
        $wpdb->update($table, ['hidden' => $new_val], ['id' => $id]);
        wp_send_json_success(['hidden' => $new_val]);
    }

    // -------------------------------------------------------------------------
    // Admin AJAX: delete question
    // -------------------------------------------------------------------------

    public function ajax_delete_question() {
        check_ajax_referer('oha_survey_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        global $wpdb;
        $table  = $wpdb->prefix . 'oha_survey_convention_questions';
        $id     = (int) ($_POST['id'] ?? 0);
        $result = $wpdb->delete($table, ['id' => $id]);
        $result !== false ? wp_send_json_success() : wp_send_json_error(['message' => 'Delete failed.']);
    }

    // -------------------------------------------------------------------------
    // Admin AJAX: reset respondent
    // -------------------------------------------------------------------------

    public function ajax_reset_response() {
        check_ajax_referer('oha_survey_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        $email   = sanitize_email($_POST['email'] ?? '');
        $context = sanitize_key($_POST['context'] ?? 'attendee');

        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Please enter a valid email address.']);
        }
        if (!array_key_exists($context, self::$context_labels)) {
            wp_send_json_error(['message' => 'Invalid survey context.']);
        }

        global $wpdb;
        $table       = $wpdb->prefix . 'oha_survey_responses';
        $survey_type = self::context_to_type($context);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE email = %s AND survey_type = %s",
            $email, $survey_type
        ));

        if (!$row) {
            wp_send_json_error(['message' => 'No response found for ' . $email . ' in the ' . self::$context_labels[$context] . ' survey.']);
        }

        $deleted = $wpdb->delete($table, ['id' => $row->id], ['%d']);
        if ($deleted === false) {
            wp_send_json_error(['message' => 'Database error. Could not delete the response.']);
        }

        wp_send_json_success(['message' => 'Response deleted for ' . $email . ' (' . self::$context_labels[$context] . '). They can now re-take the survey.']);
    }

    // -------------------------------------------------------------------------
    // Board results shortcode [oha_survey_results]
    // -------------------------------------------------------------------------

    public static function shortcode_survey_results($atts) {
        wp_enqueue_style(
            'oha-survey-hub-style',
            OHA_SURVEY_HUB_PLUGIN_URL . 'assets/css/survey-style.css',
            [],
            OHA_SURVEY_HUB_VERSION
        );
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
            [],
            '4',
            true
        );

        $types = [
            self::SURVEY_TYPE_ATTENDEE  => 'Attendee',
            self::SURVEY_TYPE_VENDOR    => 'Vendor',
            self::SURVEY_TYPE_VOLUNTEER => 'Volunteer',
        ];

        $all_data       = [];
        $all_chart_cfgs = [];
        foreach ($types as $type => $label) {
            $data                  = self::aggregate_responses($type);
            $all_data[$type]       = $data;
            $all_chart_cfgs[$type] = self::build_chart_configs($data['questions']);
        }

        ob_start();
        ?>
        <div class="oha-sr-wrap" id="oha-sr-wrap">

            <div class="oha-sr-tabs" role="tablist">
                <?php $first = true; foreach ($types as $type => $label): ?>
                    <?php $cnt = $all_data[$type]['count']; ?>
                    <button class="oha-sr-tab<?php echo $first ? ' active' : ''; ?><?php echo $cnt === 0 ? ' oha-sr-tab-empty' : ''; ?>"
                            role="tab"
                            data-type="<?php echo esc_attr($type); ?>"
                            data-target="oha-sr-panel-<?php echo esc_attr($type); ?>">
                        <?php echo esc_html($label); ?>
                        <span class="oha-sr-tab-count"><?php echo $cnt; ?></span>
                    </button>
                    <?php $first = false; ?>
                <?php endforeach; ?>
            </div>

            <?php $first = true; foreach ($types as $type => $label): ?>
                <?php $data = $all_data[$type]; ?>
                <div class="oha-sr-panel<?php echo $first ? '' : ' oha-sr-hidden'; ?>"
                     id="oha-sr-panel-<?php echo esc_attr($type); ?>"
                     data-type="<?php echo esc_attr($type); ?>"
                     role="tabpanel">

                    <?php if ($data['count'] === 0): ?>
                        <div class="oha-sr-empty-panel">
                            <p>No responses recorded for this survey yet.</p>
                        </div>
                    <?php else: ?>
                        <?php self::render_results_panel($data, self::type_to_context($type)); ?>
                    <?php endif; ?>
                </div>
                <?php $first = false; ?>
            <?php endforeach; ?>
        </div>

        <script>
        (function() {
            var CHART_CFGS  = <?php echo wp_json_encode($all_chart_cfgs); ?>;
            var initialized = {};
            var wrap        = document.getElementById('oha-sr-wrap');
            if (!wrap) return;

            function initVisibleCharts(type) {
                if (!window.Chart) return;
                var cfgs = CHART_CFGS[type] || [];
                cfgs.forEach(function(cfg) {
                    if (initialized[cfg.id]) return;
                    var canvas = document.getElementById(cfg.id);
                    if (!canvas || canvas.offsetWidth === 0) return;
                    initialized[cfg.id] = true;
                    new Chart(canvas, { type: cfg.chartType, data: cfg.data, options: cfg.options });
                });
            }

            wrap.addEventListener('click', function(e) {
                var tab = e.target.closest('.oha-sr-tab');
                if (tab) {
                    wrap.querySelectorAll('.oha-sr-tab').forEach(function(t) { t.classList.remove('active'); });
                    wrap.querySelectorAll('.oha-sr-panel').forEach(function(p) { p.classList.add('oha-sr-hidden'); });
                    tab.classList.add('active');
                    var panel = document.getElementById(tab.dataset.target);
                    if (panel) panel.classList.remove('oha-sr-hidden');
                    initVisibleCharts(tab.dataset.type);
                    return;
                }

                var hdr = e.target.closest('.oha-sr-section-header');
                if (hdr) {
                    var section = hdr.closest('.oha-sr-section');
                    var body    = section.querySelector('.oha-sr-section-body');
                    var arrow   = section.querySelector('.oha-sr-arrow');
                    var isOpen  = section.classList.contains('open');
                    section.classList.toggle('open', !isOpen);
                    body.style.display = isOpen ? 'none' : 'block';
                    arrow.innerHTML    = isOpen ? '&#9654;' : '&#9660;';
                    if (!isOpen) {
                        initVisibleCharts(section.closest('.oha-sr-panel').dataset.type);
                    }
                    return;
                }

                var expBtn = e.target.closest('.oha-sr-expand-btn');
                if (expBtn) {
                    var list = document.getElementById(expBtn.dataset.target);
                    if (!list) return;
                    var open = list.style.display !== 'none';
                    list.style.display = open ? 'none' : 'block';
                    expBtn.textContent = open ? 'Show responses' : 'Hide responses';
                }
            });

            function tryInit() {
                initVisibleCharts('<?php echo esc_js(self::SURVEY_TYPE_ATTENDEE); ?>');
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', tryInit);
            } else {
                tryInit();
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    private static function render_results_panel($data, $context = 'attendee') {
        $sections = [];
        foreach ($data['questions'] as $qd) {
            $pg = (int) $qd['q']->page_group;
            if (!isset($sections[$pg])) $sections[$pg] = [];
            $sections[$pg][] = $qd;
        }
        ksort($sections);

        $section_scores = [];
        foreach ($sections as $pg => $qds) {
            $section_scores[$pg] = self::compute_section_score($qds);
        }
        ?>
        <div class="oha-sr-meta-bar">
            <strong><?php echo $data['count']; ?></strong> response<?php echo $data['count'] !== 1 ? 's' : ''; ?>
            <?php if ($data['latest']): ?>
                &middot; most recent <?php echo esc_html(date('M j, Y', strtotime($data['latest']))); ?>
            <?php endif; ?>
        </div>

        <div class="oha-sr-scorecards">
            <?php foreach ($sections as $pg => $qds): ?>
                <?php
                $title  = self::get_page_title($pg, $context);
                $score  = $section_scores[$pg];
                $cls    = $score !== null ? self::score_color_class($score) : 'oha-sr-neutral';
                $max_n  = max(array_column($qds, 'n'));
                ?>
                <div class="oha-sr-scorecard <?php echo esc_attr($cls); ?>">
                    <div class="oha-sr-scorecard-title"><?php echo esc_html($title); ?></div>
                    <div class="oha-sr-scorecard-score">
                        <?php if ($score !== null): ?>
                            <?php echo number_format($score, 1); ?><span class="oha-sr-scorecard-max">/5</span>
                        <?php else: ?>
                            <span class="oha-sr-scorecard-dash">&#8212;</span>
                        <?php endif; ?>
                    </div>
                    <div class="oha-sr-scorecard-resp"><?php echo $max_n; ?> resp.</div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php $first = true; foreach ($sections as $pg => $qds): ?>
            <?php
            $title = self::get_page_title($pg, $context);
            $score = $section_scores[$pg];
            $cls   = $score !== null ? self::score_color_class($score) : '';
            ?>
            <div class="oha-sr-section<?php echo $first ? ' open' : ''; ?>">
                <div class="oha-sr-section-header">
                    <div class="oha-sr-section-left">
                        <span class="oha-sr-arrow"><?php echo $first ? '&#9660;' : '&#9654;'; ?></span>
                        <span class="oha-sr-section-title"><?php echo esc_html($title); ?></span>
                    </div>
                    <?php if ($score !== null): ?>
                        <span class="oha-sr-section-badge <?php echo esc_attr($cls); ?>">
                            <?php echo number_format($score, 1); ?>/5
                        </span>
                    <?php endif; ?>
                </div>
                <div class="oha-sr-section-body" style="<?php echo $first ? '' : 'display:none;'; ?>">
                    <div class="oha-sr-question-grid">
                        <?php foreach ($qds as $qd): ?>
                            <?php self::render_result_question($qd); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php $first = false; ?>
        <?php endforeach; ?>
        <?php
    }

    private static function build_chart_configs($questions_data) {
        $configs  = [];
        $palette  = ['#f39200', '#1e88e5', '#43a047', '#e53935', '#8e24aa', '#fb8c00', '#00897b', '#546e7a'];

        foreach ($questions_data as $qd) {
            $q       = $qd['q'];
            $n       = $qd['n'];
            $answers = $qd['answers'];
            if ($n === 0) continue;

            switch ($q->question_type) {

                case 'radio':
                    $counts  = array_count_values($answers);
                    $options = json_decode($q->options, true) ?: [];
                    $ordered = [];
                    foreach ($options as $opt) {
                        $ordered[$opt] = isset($counts[$opt]) ? $counts[$opt] : 0;
                    }
                    foreach ($counts as $opt => $cnt) {
                        if (!array_key_exists($opt, $ordered)) $ordered[$opt] = $cnt;
                    }
                    $ordered = array_filter($ordered, function($v) { return $v > 0; });
                    arsort($ordered);
                    $labels = array_keys($ordered);
                    $values = array_values($ordered);
                    $colors = [];
                    for ($i = 0; $i < count($labels); $i++) {
                        $colors[] = $palette[$i % count($palette)];
                    }
                    $configs[] = [
                        'id'        => 'oha-chart-' . $q->id,
                        'chartType' => 'doughnut',
                        'data'      => [
                            'labels'   => $labels,
                            'datasets' => [[
                                'data'            => $values,
                                'backgroundColor' => $colors,
                                'borderWidth'     => 3,
                                'borderColor'     => '#fff',
                                'hoverOffset'     => 8,
                            ]],
                        ],
                        'options' => [
                            'cutout'              => '60%',
                            'responsive'          => true,
                            'maintainAspectRatio' => true,
                            'plugins' => [
                                'legend' => [
                                    'position' => 'right',
                                    'labels'   => ['boxWidth' => 14, 'padding' => 14, 'font' => ['size' => 12]],
                                ],
                            ],
                        ],
                    ];
                    break;

                case 'checkbox':
                    $flat = [];
                    foreach ($answers as $a) {
                        if (is_array($a)) { foreach ($a as $v) $flat[] = $v; }
                        else { $flat[] = $a; }
                    }
                    $counts = array_count_values($flat);
                    arsort($counts);
                    $labels = array_keys($counts);
                    $pcts   = array_map(function($c) use ($n) { return round(($c / $n) * 100); }, array_values($counts));
                    $configs[] = [
                        'id'        => 'oha-chart-' . $q->id,
                        'chartType' => 'bar',
                        'data'      => [
                            'labels'   => $labels,
                            'datasets' => [[
                                'label'           => '% of respondents',
                                'data'            => $pcts,
                                'backgroundColor' => '#f39200',
                                'borderRadius'    => 5,
                                'borderSkipped'   => false,
                            ]],
                        ],
                        'options' => [
                            'indexAxis'           => 'y',
                            'responsive'          => true,
                            'maintainAspectRatio' => false,
                            'plugins' => [
                                'legend' => ['display' => false],
                            ],
                            'scales' => [
                                'x' => [
                                    'max'   => 100,
                                    'grid'  => ['color' => '#f0f0f0'],
                                    'ticks' => ['color' => '#888'],
                                ],
                                'y' => [
                                    'grid'  => ['display' => false],
                                    'ticks' => ['color' => '#333', 'font' => ['size' => 12]],
                                ],
                            ],
                        ],
                    ];
                    break;
            }
        }
        return $configs;
    }

    private static function aggregate_responses($survey_type) {
        global $wpdb;
        $context         = self::type_to_context($survey_type);
        $questions_table = $wpdb->prefix . 'oha_survey_convention_questions';
        $responses_table = $wpdb->prefix . 'oha_survey_responses';

        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $questions_table WHERE survey_context = %s AND hidden = 0 ORDER BY sort_order ASC",
            $context
        ));

        $raw = $wpdb->get_results($wpdb->prepare(
            "SELECT response_data, completed_at FROM $responses_table WHERE survey_type = %s AND is_completed = 1 ORDER BY completed_at DESC",
            $survey_type
        ));

        $count  = count($raw);
        $latest = $count > 0 ? $raw[0]->completed_at : null;

        $decoded = [];
        foreach ($raw as $r) {
            $decoded[] = json_decode($r->response_data, true) ?: [];
        }

        $result_questions = [];
        foreach ($questions as $q) {
            $key     = 'q_' . $q->id;
            $answers = [];
            foreach ($decoded as $rd) {
                if (isset($rd[$key]) && $rd[$key] !== '' && $rd[$key] !== null) {
                    $answers[] = $rd[$key];
                }
            }
            $result_questions[] = [
                'q'       => $q,
                'key'     => $key,
                'answers' => $answers,
                'n'       => count($answers),
            ];
        }

        return [
            'count'     => $count,
            'latest'    => $latest,
            'questions' => $result_questions,
        ];
    }

    private static function render_result_question($qd) {
        $q       = $qd['q'];
        $answers = $qd['answers'];
        $n       = $qd['n'];
        $is_wide = in_array($q->question_type, ['radio', 'checkbox'], true);

        echo '<div class="oha-sr-question' . ($is_wide ? ' oha-sr-question-wide' : '') . '">';
        echo '<div class="oha-sr-q-text">' . esc_html($q->question_text) . '</div>';

        if ($n === 0) {
            echo '<p class="oha-sr-no-answers">No answers recorded.</p>';
            echo '</div>';
            return;
        }

        switch ($q->question_type) {

            case 'scale':
                $max     = ((int) $q->scale_max) ?: 5;
                $min_val = ((int) $q->scale_min) ?: 1;
                $avg     = array_sum($answers) / $n;
                echo '<div class="oha-sr-scale-layout">';
                echo self::render_scale_gauge($avg, $max, $n);
                if ($q->scale_label_min || $q->scale_label_max) {
                    echo '<div class="oha-sr-scale-endpoints">';
                    echo '<span class="oha-sr-scale-lo">' . esc_html($q->scale_label_min) . '</span>';
                    echo '<span class="oha-sr-scale-hi">' . esc_html($q->scale_label_max) . '</span>';
                    echo '</div>';
                }
                // Score distribution mini-bars
                $dist = [];
                for ($i = $min_val; $i <= $max; $i++) $dist[$i] = 0;
                foreach ($answers as $a) { $v = (int) $a; if (isset($dist[$v])) $dist[$v]++; }
                $dist_max = max($dist) ?: 1;
                echo '<div class="oha-sr-dist">';
                foreach ($dist as $score => $cnt) {
                    $bar_pct = round(($cnt / $dist_max) * 100);
                    $bar_cls = self::score_color_class((float) $score, $max);
                    echo '<div class="oha-sr-dist-row">';
                    echo '<span class="oha-sr-dist-label">' . $score . '</span>';
                    echo '<div class="oha-sr-dist-track"><div class="oha-sr-dist-fill ' . $bar_cls . '" style="width:' . $bar_pct . '%"></div></div>';
                    echo '<span class="oha-sr-dist-cnt">' . $cnt . '</span>';
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
                break;

            case 'radio':
                echo '<div class="oha-sr-chart-wrap">';
                echo '<canvas id="oha-chart-' . esc_attr($q->id) . '"></canvas>';
                echo '</div>';
                break;

            case 'checkbox':
                $flat      = [];
                foreach ($answers as $a) {
                    if (is_array($a)) { foreach ($a as $v) $flat[] = $v; }
                    else { $flat[] = $a; }
                }
                $opt_count = count(array_count_values($flat));
                $h         = max(180, min(420, $opt_count * 44 + 60));
                echo '<div class="oha-sr-chart-wrap oha-sr-chart-bar" style="height:' . $h . 'px">';
                echo '<canvas id="oha-chart-' . esc_attr($q->id) . '"></canvas>';
                echo '</div>';
                echo '<p class="oha-sr-checkbox-note">% of ' . $n . ' respondents (multiple selections allowed).</p>';
                break;

            case 'textarea':
            case 'text':
                $non_empty = array_values(array_filter($answers, function($a) { return trim((string) $a) !== ''; }));
                $cnt       = count($non_empty);
                if ($cnt === 0) {
                    echo '<p class="oha-sr-no-answers">No answers recorded.</p>';
                } else {
                    $uid = 'oha-sr-ta-' . $q->id;
                    echo '<div class="oha-sr-textarea-meta">';
                    echo '<span>' . $cnt . ' response' . ($cnt !== 1 ? 's' : '') . '</span>';
                    echo '<button type="button" class="oha-sr-expand-btn" data-target="' . esc_attr($uid) . '">Show responses</button>';
                    echo '</div>';
                    echo '<div class="oha-sr-textarea-list" id="' . esc_attr($uid) . '" style="display:none">';
                    foreach ($non_empty as $a) {
                        echo '<div class="oha-sr-textarea-item">' . esc_html($a) . '</div>';
                    }
                    echo '</div>';
                }
                break;
        }

        echo '</div>';
    }

    private static function render_scale_gauge($avg, $max, $n) {
        $pct        = $avg / $max;
        $arc_len    = M_PI * 50;
        $fill_len   = $arc_len * $pct;
        $dash_off   = round($arc_len - $fill_len, 2);
        $arc_r      = round($arc_len, 2);
        $color      = self::gauge_color($avg, $max);
        $score_text = number_format($avg, 1);

        return '<div class="oha-sr-gauge-wrap">'
             . '<svg viewBox="0 0 120 75" class="oha-sr-gauge" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
             . '<path d="M 10,65 A 50,50 0 0,1 110,65" stroke="#e8e8e8" stroke-width="13" fill="none" stroke-linecap="round"/>'
             . '<path d="M 10,65 A 50,50 0 0,1 110,65" stroke="' . $color . '" stroke-width="13" fill="none" stroke-linecap="round"'
             . ' stroke-dasharray="' . $arc_r . '" stroke-dashoffset="' . $dash_off . '"/>'
             . '<text x="60" y="56" text-anchor="middle" font-size="21" font-weight="700" fill="' . $color . '">' . $score_text . '</text>'
             . '<text x="60" y="70" text-anchor="middle" font-size="9" fill="#aaa">out of ' . $max . ' (n=' . $n . ')</text>'
             . '</svg>'
             . '</div>';
    }

    private static function gauge_color($avg, $max) {
        $ratio = $avg / $max;
        if ($ratio >= 0.8) return '#4caf50';
        if ($ratio >= 0.6) return '#f39200';
        if ($ratio >= 0.4) return '#ff9800';
        return '#f44336';
    }

    private static function score_color_class($score, $max = 5) {
        $ratio = $score / $max;
        if ($ratio >= 0.8) return 'oha-sr-green';
        if ($ratio >= 0.6) return 'oha-sr-yellow';
        return 'oha-sr-red';
    }

    private static function compute_section_score($qds) {
        $scores = [];
        foreach ($qds as $qd) {
            if ($qd['q']->question_type === 'scale' && $qd['n'] > 0) {
                $scores[] = array_sum($qd['answers']) / $qd['n'];
            }
        }
        if (empty($scores)) return null;
        return array_sum($scores) / count($scores);
    }
}
