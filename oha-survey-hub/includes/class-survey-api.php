<?php
if (!defined('ABSPATH')) exit;

/**
 * OHA Survey Hub REST API  --  Namespace: oha-survey/v1
 *
 * Public endpoints (no authentication required):
 *   GET  /surveys                          list available survey types + titles
 *   GET  /definition/{type}               full page+question structure for one survey
 *   GET  /progress?session_id=X&survey_type=Y  load saved in-progress data
 *   POST /progress                         save partial progress (upsert)
 *   POST /submit                           complete + store a survey response
 *
 * Security:
 *   - All inputs sanitized and type-validated before DB use
 *   - Survey type validated against a strict whitelist
 *   - Session IDs validated as UUID/slug format (8-64 alphanumeric chars)
 *   - Response blobs capped at 64 KB
 *   - Rate limit on submit: max 10 completions per IP per hour (DB-based, no cache)
 *   - No raw credentials stored or returned anywhere
 *   - CORS open to any origin for now (tighten to app domain once known)
 *   - Emails and IPs never returned in any response
 *
 * Future: member-only surveys can be gated by adding is_user_logged_in() checks
 * per survey type once the mobile app auth flow is decided.
 */
class OHA_Survey_API {

    const NS = 'oha-survey/v1';

    public static function register() {
        add_action('rest_api_init', [self::class, 'register_routes']);
        add_filter('rest_pre_serve_request', [self::class, 'cors_headers'], 10, 3);
    }

    // ------------------------------------------------------------------
    // Route registration
    // ------------------------------------------------------------------

    public static function register_routes() {
        register_rest_route(self::NS, '/surveys', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'handle_get_surveys'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/definition/(?P<type>[a-z0-9_]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [self::class, 'handle_get_definition'],
            'permission_callback' => '__return_true',
            'args'                => [
                'type' => [
                    'validate_callback' => [self::class, 'is_valid_survey_type'],
                    'sanitize_callback' => 'sanitize_key',
                    'required'          => true,
                ],
            ],
        ]);

        register_rest_route(self::NS, '/progress', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [self::class, 'handle_get_progress'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'session_id'  => ['sanitize_callback' => 'sanitize_text_field', 'required' => true],
                    'survey_type' => ['sanitize_callback' => 'sanitize_key', 'required' => true],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'handle_save_progress'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::NS, '/submit', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'handle_submit'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ------------------------------------------------------------------
    // CORS -- allow any origin until mobile app domain is known
    // ------------------------------------------------------------------

    public static function cors_headers($served, $result, $request) {
        if (strpos($request->get_route(), '/oha-survey/') === false) {
            return $served;
        }
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit;
        }
        return $served;
    }

    // ------------------------------------------------------------------
    // Handlers
    // ------------------------------------------------------------------

    /**
     * GET /surveys
     * Returns a flat list of all available survey types with title + description.
     */
    public static function handle_get_surveys($request) {
        $result = [];

        // Hardcoded definition surveys
        foreach (OHA_Survey_Definitions::get_all_surveys() as $slug => $def) {
            $result[] = [
                'type'        => $slug,
                'title'       => $def['title'] ?? $slug,
                'description' => $def['description'] ?? '',
                'source'      => 'definition',
            ];
        }

        // Convention DB-backed surveys for the active year
        $year = OHA_Convention_Survey::get_year();
        $conv_types = [
            'convention_' . $year,
            'convention_' . $year . '_vendor',
            'convention_' . $year . '_volunteer',
        ];
        foreach ($conv_types as $conv_type) {
            $def = OHA_Convention_Survey::get_as_definition($conv_type);
            if ($def) {
                $result[] = [
                    'type'        => $conv_type,
                    'title'       => $def['title'] ?? $conv_type,
                    'description' => $def['description'] ?? '',
                    'source'      => 'convention',
                ];
            }
        }

        return rest_ensure_response(['surveys' => $result, 'active_year' => $year]);
    }

    /**
     * GET /definition/{type}
     * Returns the full page+question structure for a survey.
     * Safe to expose publicly -- no user data, only question definitions.
     */
    public static function handle_get_definition($request) {
        $type = $request->get_param('type');

        $def = OHA_Survey_Definitions::get_survey($type);
        if (!$def) {
            $def = OHA_Convention_Survey::get_as_definition($type);
        }
        if (!$def) {
            OHA_Survey_Debug::log_api('definition_not_found', ['type' => $type]);
            return new WP_Error('not_found', 'Survey type not found.', ['status' => 404]);
        }

        return rest_ensure_response($def);
    }

    /**
     * GET /progress?session_id=X&survey_type=Y
     * Returns saved in-progress data for a session.
     */
    public static function handle_get_progress($request) {
        $session_id  = sanitize_text_field($request->get_param('session_id'));
        $survey_type = sanitize_key($request->get_param('survey_type'));

        if (!self::is_valid_session_id($session_id)) {
            return new WP_Error('invalid_session', 'Invalid session_id format.', ['status' => 400]);
        }
        if (!self::is_valid_survey_type($survey_type)) {
            return new WP_Error('invalid_type', 'Unknown survey type.', ['status' => 400]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oha_survey_responses';
        $row   = $wpdb->get_row($wpdb->prepare(
            "SELECT response_data, current_page, total_pages FROM $table
             WHERE session_id = %s AND survey_type = %s AND is_completed = 0
             LIMIT 1",
            $session_id, $survey_type
        ));

        if (!$row) {
            return rest_ensure_response(['found' => false]);
        }

        $data = json_decode($row->response_data, true) ?: [];
        return rest_ensure_response([
            'found'        => true,
            'current_page' => (int) $row->current_page,
            'total_pages'  => (int) $row->total_pages,
            'responses'    => $data,
        ]);
    }

    /**
     * POST /progress
     * Body: { session_id, survey_type, current_page, total_pages, responses: {} }
     * Creates or updates an in-progress record.
     */
    public static function handle_save_progress($request) {
        $body = $request->get_json_params();
        if (empty($body)) {
            return new WP_Error('invalid_body', 'Request body must be JSON.', ['status' => 400]);
        }

        $session_id   = sanitize_text_field($body['session_id'] ?? '');
        $survey_type  = sanitize_key($body['survey_type'] ?? '');
        $current_page = max(1, (int) ($body['current_page'] ?? 1));
        $total_pages  = max(1, (int) ($body['total_pages'] ?? 1));
        $responses    = $body['responses'] ?? [];

        if (!self::is_valid_session_id($session_id)) {
            return new WP_Error('invalid_session', 'Invalid session_id format.', ['status' => 400]);
        }
        if (!self::is_valid_survey_type($survey_type)) {
            return new WP_Error('invalid_type', 'Unknown survey type.', ['status' => 400]);
        }
        if (!is_array($responses)) {
            return new WP_Error('invalid_responses', 'responses must be an object.', ['status' => 400]);
        }

        $json = wp_json_encode($responses);
        if (strlen($json) > 65536) {
            return new WP_Error('payload_too_large', 'Response data exceeds size limit.', ['status' => 413]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oha_survey_responses';
        $now   = current_time('mysql');

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE session_id = %s AND survey_type = %s AND is_completed = 0",
            $session_id, $survey_type
        ));

        if ($exists) {
            $wpdb->update(
                $table,
                [
                    'response_data' => $json,
                    'current_page'  => $current_page,
                    'total_pages'   => $total_pages,
                    'updated_at'    => $now,
                ],
                ['id' => (int) $exists],
                ['%s', '%d', '%d', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert($table, [
                'session_id'    => $session_id,
                'survey_type'   => $survey_type,
                'email'         => '',
                'response_data' => $json,
                'current_page'  => $current_page,
                'total_pages'   => $total_pages,
                'is_completed'  => 0,
                'ip_address'    => self::client_ip(),
                'user_agent'    => substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'started_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        return rest_ensure_response(['saved' => true, 'session_id' => $session_id]);
    }

    /**
     * POST /submit
     * Body: { session_id, survey_type, email?, responses: {} }
     * Marks the response as completed. Rate-limited to 10 per IP per hour.
     */
    public static function handle_submit($request) {
        $body = $request->get_json_params();
        if (empty($body)) {
            return new WP_Error('invalid_body', 'Request body must be JSON.', ['status' => 400]);
        }

        $session_id  = sanitize_text_field($body['session_id'] ?? '');
        $survey_type = sanitize_key($body['survey_type'] ?? '');
        $email       = sanitize_email($body['email'] ?? '');
        $responses   = $body['responses'] ?? [];

        if (!self::is_valid_session_id($session_id)) {
            return new WP_Error('invalid_session', 'Invalid session_id.', ['status' => 400]);
        }
        if (!self::is_valid_survey_type($survey_type)) {
            return new WP_Error('invalid_type', 'Unknown survey type.', ['status' => 400]);
        }
        if (!empty($email) && !is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email address.', ['status' => 400]);
        }
        if (!is_array($responses)) {
            return new WP_Error('invalid_responses', 'responses must be an object.', ['status' => 400]);
        }

        $json = wp_json_encode($responses);
        if (strlen($json) > 65536) {
            return new WP_Error('payload_too_large', 'Response data exceeds size limit.', ['status' => 413]);
        }

        if (self::is_rate_limited()) {
            OHA_Survey_Debug::log_api('rate_limit_hit', ['survey_type' => $survey_type, 'ip' => self::client_ip()]);
            return new WP_Error('rate_limited', 'Too many submissions. Please try again later.', ['status' => 429]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'oha_survey_responses';
        $now   = current_time('mysql');
        $ip    = self::client_ip();

        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE session_id = %s AND survey_type = %s",
            $session_id, $survey_type
        ));

        if ($existing_id) {
            $wpdb->update(
                $table,
                [
                    'response_data' => $json,
                    'email'         => $email,
                    'is_completed'  => 1,
                    'completed_at'  => $now,
                    'updated_at'    => $now,
                ],
                ['id' => (int) $existing_id],
                ['%s', '%s', '%d', '%s', '%s'],
                ['%d']
            );
            $response_id = (int) $existing_id;
        } else {
            $wpdb->insert($table, [
                'session_id'    => $session_id,
                'survey_type'   => $survey_type,
                'email'         => $email,
                'response_data' => $json,
                'current_page'  => 1,
                'total_pages'   => 1,
                'is_completed'  => 1,
                'ip_address'    => $ip,
                'user_agent'    => substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'started_at'    => $now,
                'completed_at'  => $now,
                'updated_at'    => $now,
            ]);
            $response_id = (int) $wpdb->insert_id;
        }

        // Fire same action as AJAX path so any post-submit hooks still run
        do_action('oha_survey_submitted', $response_id, $survey_type, $email);

        return rest_ensure_response([
            'submitted'   => true,
            'response_id' => $response_id,
        ]);
    }

    // ------------------------------------------------------------------
    // Validation helpers
    // ------------------------------------------------------------------

    /**
     * Returns true if $type is a known hardcoded or convention survey type.
     * Used as WP REST arg validate_callback and internally.
     */
    public static function is_valid_survey_type($type) {
        if (empty($type) || !is_string($type)) return false;

        // Hardcoded definition surveys
        $definitions = OHA_Survey_Definitions::get_all_surveys();
        if (isset($definitions[$type])) return true;

        // Convention DB surveys: convention_{4-digit-year}[_vendor|_volunteer]
        if (preg_match('/^convention_(\d{4})(_vendor|_volunteer)?$/', $type, $m)) {
            $year = (int) $m[1];
            return $year >= 2024 && $year <= 2035;
        }

        return false;
    }

    private static function is_valid_session_id($id) {
        return is_string($id) && preg_match('/^[a-zA-Z0-9_\-]{8,64}$/', $id) === 1;
    }

    // ------------------------------------------------------------------
    // Rate limiting (DB-based, no transient cache needed)
    // ------------------------------------------------------------------

    private static function is_rate_limited() {
        global $wpdb;
        $ip    = self::client_ip();
        $table = $wpdb->prefix . 'oha_survey_responses';
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE ip_address = %s AND is_completed = 1
               AND completed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            $ip
        ));
        return $count >= 10;
    }

    // ------------------------------------------------------------------
    // Utility
    // ------------------------------------------------------------------

    private static function client_ip() {
        // Prefer forwarded IP (Cloudflare / GoDaddy proxy); fall back to REMOTE_ADDR
        $ip = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return substr(trim($ip), 0, 45);
    }
}
