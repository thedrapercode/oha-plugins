<?php
/**
 * OHA Convention Data
 * Handles CPT registration, booth data queries, and AJAX endpoints for Convention Mode.
 */

if (!defined('ABSPATH')) exit;

class OHA_Convention_Data {

    /**
     * Register the oha_booth custom post type and meta boxes.
     * Called on 'init'.
     */
    public static function register() {
        self::register_cpt();
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_oha_booth', [__CLASS__, 'save_booth_meta'], 10, 2);
    }

    // -----------------------------------------------------------------------
    // CPT
    // -----------------------------------------------------------------------

    public static function register_cpt() {
        register_post_type('oha_booth', [
            'labels' => [
                'name'               => __('Convention Booths', 'oha-modern-map'),
                'singular_name'      => __('Booth', 'oha-modern-map'),
                'add_new'            => __('Add Booth', 'oha-modern-map'),
                'add_new_item'       => __('Add New Booth', 'oha-modern-map'),
                'edit_item'          => __('Edit Booth', 'oha-modern-map'),
                'new_item'           => __('New Booth', 'oha-modern-map'),
                'view_item'          => __('View Booth', 'oha-modern-map'),
                'search_items'       => __('Search Booths', 'oha-modern-map'),
                'not_found'          => __('No booths found', 'oha-modern-map'),
                'not_found_in_trash' => __('No booths found in trash', 'oha-modern-map'),
                'menu_name'          => __('Booths', 'oha-modern-map'),
            ],
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => 'oha-modern-map',
            'show_in_rest'        => false,
            'query_var'           => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'rewrite'             => false,
            'supports'            => ['title', 'thumbnail', 'editor'],
        ]);
    }

    // -----------------------------------------------------------------------
    // Meta Boxes
    // -----------------------------------------------------------------------

    public static function add_meta_boxes() {
        add_meta_box(
            'oha_booth_location',
            __('Booth Location & Type', 'oha-modern-map'),
            [__CLASS__, 'render_location_meta_box'],
            'oha_booth',
            'normal',
            'high'
        );
        add_meta_box(
            'oha_booth_details',
            __('Booth Details', 'oha-modern-map'),
            [__CLASS__, 'render_details_meta_box'],
            'oha_booth',
            'normal',
            'default'
        );
    }

    public static function render_location_meta_box($post) {
        wp_nonce_field('oha_booth_meta', 'oha_booth_nonce');
        $x       = get_post_meta($post->ID, '_booth_x', true);
        $y       = get_post_meta($post->ID, '_booth_y', true);
        $floor   = get_post_meta($post->ID, '_booth_floor', true) ?: 1;
        $type    = get_post_meta($post->ID, '_booth_type', true) ?: 'vendor';
        $number  = get_post_meta($post->ID, '_booth_number', true);
        $width   = get_post_meta($post->ID, '_booth_width', true);
        $height  = get_post_meta($post->ID, '_booth_height', true);
        $color   = get_post_meta($post->ID, '_booth_color', true);
        $status  = get_post_meta($post->ID, '_booth_status', true) ?: 'open';
        $year    = get_post_meta($post->ID, '_booth_year', true);

        $settings     = self::get_settings();
        $floors       = $settings['floors'] ?? [];
        $active_year  = $settings['active_year'] ?? date('Y');
        if (!$year) $year = $active_year;
        ?>
        <table class="form-table" style="margin:0;">
            <tr>
                <th><label><?php _e('Convention Year', 'oha-modern-map'); ?></label></th>
                <td>
                    <strong style="color:#0073aa"><?php echo esc_html($year); ?></strong>
                    <input type="hidden" name="booth_year" value="<?php echo esc_attr($year); ?>">
                    <p class="description"><?php _e('Year is set from the active convention year in Convention Settings. Use the Booth Placement tool to add booths for the correct year.', 'oha-modern-map'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Booth Number', 'oha-modern-map'); ?></label></th>
                <td>
                    <input type="text" name="booth_number" value="<?php echo esc_attr($number); ?>" class="regular-text" placeholder="e.g. 217, A4">
                    <p class="description"><?php _e('Display number shown on map and in deep link (?oha_booth=217)', 'oha-modern-map'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Booth Type', 'oha-modern-map'); ?></label></th>
                <td>
                    <select name="booth_type">
                        <?php foreach (self::get_booth_types() as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($type, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Floor', 'oha-modern-map'); ?></label></th>
                <td>
                    <?php if (!empty($floors)) : ?>
                        <select name="booth_floor">
                            <?php foreach ($floors as $f) : ?>
                                <option value="<?php echo esc_attr($f['id']); ?>" <?php selected((int) $floor, (int) $f['id']); ?>>
                                    <?php echo esc_html($f['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else : ?>
                        <input type="number" name="booth_floor" value="<?php echo esc_attr($floor); ?>" min="1" max="99" style="width:80px">
                        <p class="description"><?php _e('Add floor plans under OHA Map > Convention Settings to get a dropdown.', 'oha-modern-map'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('X Position (%)', 'oha-modern-map'); ?></label></th>
                <td>
                    <input type="number" name="booth_x" value="<?php echo esc_attr($x); ?>" min="0" max="100" step="0.01" style="width:100px">
                    <p class="description"><?php _e('Set visually using OHA Map > Booth Placement', 'oha-modern-map'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Y Position (%)', 'oha-modern-map'); ?></label></th>
                <td>
                    <input type="number" name="booth_y" value="<?php echo esc_attr($y); ?>" min="0" max="100" step="0.01" style="width:100px">
                    <p class="description"><?php _e('Set visually using OHA Map > Booth Placement', 'oha-modern-map'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Width (%) <em>optional</em>', 'oha-modern-map'); ?></label></th>
                <td><input type="number" name="booth_width" value="<?php echo esc_attr($width); ?>" min="0" max="100" step="0.1" style="width:100px"></td>
            </tr>
            <tr>
                <th><label><?php _e('Height (%) <em>optional</em>', 'oha-modern-map'); ?></label></th>
                <td><input type="number" name="booth_height" value="<?php echo esc_attr($height); ?>" min="0" max="100" step="0.1" style="width:100px"></td>
            </tr>
            <tr>
                <th><label><?php _e('Status', 'oha-modern-map'); ?></label></th>
                <td>
                    <select name="booth_status">
                        <option value="open" <?php selected($status, 'open'); ?>><?php _e('Open', 'oha-modern-map'); ?></option>
                        <option value="closed" <?php selected($status, 'closed'); ?>><?php _e('Closed', 'oha-modern-map'); ?></option>
                        <option value="tbd" <?php selected($status, 'tbd'); ?>><?php _e('TBD', 'oha-modern-map'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Custom Color', 'oha-modern-map'); ?></label></th>
                <td>
                    <input type="text" name="booth_color" value="<?php echo esc_attr($color); ?>" class="oha-color-picker" placeholder="#ff6b35">
                    <p class="description"><?php _e('Overrides the default type color on the map.', 'oha-modern-map'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function render_details_meta_box($post) {
        $vendor_id  = get_post_meta($post->ID, '_booth_vendor_id', true);
        $vendor_hub_id = get_post_meta($post->ID, '_booth_vendor_hub_id', true);
        $hours      = get_post_meta($post->ID, '_booth_hours', true);
        $contact    = get_post_meta($post->ID, '_booth_contact', true);
        $website    = get_post_meta($post->ID, '_booth_website', true);

        // Resolve vendor hub name for display
        $vendor_hub_name = '';
        if ($vendor_hub_id) {
            $hub_company = get_post_meta((int) $vendor_hub_id, 'company_name', true);
            $vendor_hub_name = $hub_company ?: get_the_title((int) $vendor_hub_id);
        }
        ?>
        <table class="form-table" style="margin:0;">
            <tr>
                <th><label><?php _e('Vendor Hub Link', 'oha-modern-map'); ?></label></th>
                <td>
                    <?php if ($vendor_hub_name) : ?>
                        <strong style="color:#0073aa"><?php echo esc_html($vendor_hub_name); ?></strong>
                        <br>
                    <?php endif; ?>
                    <input type="number" name="booth_vendor_hub_id" value="<?php echo esc_attr($vendor_hub_id); ?>" style="width:120px" placeholder="Application ID">
                    <p class="description"><?php _e('Optional: link to a Vendor Hub application (vendor_application post ID) to show company name, logo, and details in the booth card. Use the Booth Placement tool to search and assign.', 'oha-modern-map'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Hours', 'oha-modern-map'); ?></label></th>
                <td>
                    <input type="text" name="booth_hours" value="<?php echo esc_attr($hours); ?>" class="regular-text" placeholder="e.g. Fri 6–11pm, Sat 5–12am">
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Contact / Table', 'oha-modern-map'); ?></label></th>
                <td>
                    <input type="text" name="booth_contact" value="<?php echo esc_attr($contact); ?>" class="regular-text" placeholder="Name or contact info">
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Website', 'oha-modern-map'); ?></label></th>
                <td>
                    <input type="url" name="booth_website" value="<?php echo esc_attr($website); ?>" class="regular-text" placeholder="https://">
                </td>
            </tr>
            <tr>
                <th><label><?php _e('GeoDirectory Vendor ID', 'oha-modern-map'); ?></label></th>
                <td>
                    <input type="number" name="booth_vendor_id" value="<?php echo esc_attr($vendor_id); ?>" style="width:120px" placeholder="GD Post ID">
                    <p class="description"><?php _e('Optional: link to a GeoDirectory listing for extra vendor info. Separate from the Vendor Hub link above.', 'oha-modern-map'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function save_booth_meta($post_id, $post) {
        if (!isset($_POST['oha_booth_nonce']) || !wp_verify_nonce($_POST['oha_booth_nonce'], 'oha_booth_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $fields = [
            'booth_number'    => '_booth_number',
            'booth_type'      => '_booth_type',
            'booth_status'    => '_booth_status',
            'booth_hours'     => '_booth_hours',
            'booth_contact'   => '_booth_contact',
            'booth_website'   => '_booth_website',
            'booth_year'      => '_booth_year',
        ];
        foreach ($fields as $post_key => $meta_key) {
            if (isset($_POST[$post_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$post_key]));
            }
        }

        // Numeric fields
        foreach (['booth_x', 'booth_y', 'booth_width', 'booth_height', 'booth_floor', 'booth_vendor_id', 'booth_vendor_hub_id'] as $key) {
            if (isset($_POST[$key]) && $_POST[$key] !== '') {
                update_post_meta($post_id, '_' . $key, floatval($_POST[$key]));
            }
        }

        // Color
        if (isset($_POST['booth_color'])) {
            $color = sanitize_hex_color($_POST['booth_color']);
            update_post_meta($post_id, '_booth_color', $color ?: '');
        }
    }

    // -----------------------------------------------------------------------
    // Data Queries
    // -----------------------------------------------------------------------

    /**
     * Get all published booths for the given floor and year.
     *
     * @param int    $floor_id  0 = all floors
     * @param string $year      '' = use active_year from settings
     * @return array
     */
    public static function get_booths($floor_id = 0, $year = '') {
        $settings = self::get_settings();
        if ($year === '') {
            $year = $settings['active_year'] ?? date('Y');
        }

        // Match booths for the active year OR booths that have no year set
        // (created before year tracking was added).
        $meta_query = [
            'relation' => 'OR',
            ['key' => '_booth_year', 'value' => $year, 'compare' => '='],
            ['key' => '_booth_year', 'compare' => 'NOT EXISTS'],
        ];

        if ($floor_id) {
            $meta_query[] = ['key' => '_booth_floor', 'value' => intval($floor_id), 'compare' => '=', 'type' => 'NUMERIC'];
        }

        $args = [
            'post_type'      => 'oha_booth',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'meta_key'       => '_booth_number',
            'order'          => 'ASC',
            'meta_query'     => $meta_query,
        ];

        $query  = new WP_Query($args);
        $booths = [];

        foreach ($query->posts as $post) {
            $x              = (float) get_post_meta($post->ID, '_booth_x', true);
            $y              = (float) get_post_meta($post->ID, '_booth_y', true);
            $floor          = (int)   get_post_meta($post->ID, '_booth_floor', true) ?: 1;
            $type           = get_post_meta($post->ID, '_booth_type', true) ?: 'general';
            $number         = get_post_meta($post->ID, '_booth_number', true);
            $color          = get_post_meta($post->ID, '_booth_color', true);
            $status         = get_post_meta($post->ID, '_booth_status', true) ?: 'open';
            $hours          = get_post_meta($post->ID, '_booth_hours', true);
            $contact        = get_post_meta($post->ID, '_booth_contact', true);
            $website        = get_post_meta($post->ID, '_booth_website', true);
            $vendor_id      = (int) get_post_meta($post->ID, '_booth_vendor_id', true);
            $vendor_hub_id  = (int) get_post_meta($post->ID, '_booth_vendor_hub_id', true);
            $width          = (float) get_post_meta($post->ID, '_booth_width', true);
            $height_pct     = (float) get_post_meta($post->ID, '_booth_height', true);
            $booth_year     = get_post_meta($post->ID, '_booth_year', true) ?: $year;

            // Booth description from post content (editor field)
            $description = '';
            if (!empty($post->post_content)) {
                $description = wp_trim_words(wp_strip_all_tags($post->post_content), 40, '...');
            }

            // Post thumbnail
            $thumbnail = '';
            if (has_post_thumbnail($post->ID)) {
                $img = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'medium');
                $thumbnail = $img ? $img[0] : '';
            }

            // Vendor Hub linkage (vendor_application CPT)
            $vendor_hub_name = '';
            $vendor_hub_logo = '';
            $vendor_hub_desc = '';
            if ($vendor_hub_id) {
                $hub_company = get_post_meta($vendor_hub_id, 'company_name', true);
                $vendor_hub_name = $hub_company ?: get_the_title($vendor_hub_id);
                $vendor_hub_logo = get_post_meta($vendor_hub_id, '_company_logo_url', true) ?: '';
                // Use post content/excerpt as description
                $hub_post = get_post($vendor_hub_id);
                if ($hub_post) {
                    $vendor_hub_desc = wp_trim_words($hub_post->post_excerpt ?: $hub_post->post_content, 20, '...');
                }
            }

            // GeoDirectory vendor linkage (optional, legacy)
            $vendor_name = $vendor_hub_name;
            $vendor_logo = $vendor_hub_logo;
            $vendor_desc = $vendor_hub_desc;
            if (!$vendor_name && $vendor_id && function_exists('geodir_get_post_info')) {
                $gd_post = geodir_get_post_info($vendor_id);
                if ($gd_post) {
                    $vendor_name = $gd_post->post_title ?? '';
                    $vendor_desc = $gd_post->post_excerpt ?? '';
                    if (!empty($gd_post->post_images)) {
                        $first_img = explode(',', $gd_post->post_images)[0] ?? '';
                        if ($first_img) {
                            $img = wp_get_attachment_image_src((int) $first_img, 'thumbnail');
                            $vendor_logo = $img ? $img[0] : '';
                        }
                    }
                }
            }

            $booths[] = [
                'id'             => $post->ID,
                'title'          => $post->post_title,
                'number'         => $number,
                'type'           => $type,
                'floor'          => $floor,
                'year'           => $booth_year,
                'x'              => $x,
                'y'              => $y,
                'width'          => $width,
                'height'         => $height_pct,
                'status'         => $status,
                'hours'          => $hours,
                'contact'        => $contact,
                'website'        => $website,
                'color'          => $color,
                'description'    => $description,
                'thumbnail'      => $thumbnail,
                'vendor_id'      => $vendor_id,
                'vendor_hub_id'  => $vendor_hub_id,
                'vendor_name'    => $vendor_name,
                'vendor_logo'    => $vendor_logo,
                'vendor_desc'    => $vendor_desc,
                'edit_url'       => current_user_can('edit_post', $post->ID) ? get_edit_post_link($post->ID) : '',
                'icon'           => self::get_icon_for_type($type),
                'type_color'     => $color ?: self::get_color_for_type($type),
                'type_label'     => self::get_booth_types()[$type] ?? ucfirst($type),
            ];
        }

        wp_reset_postdata();
        return apply_filters('oha_convention_booths', $booths);
    }

    /**
     * AJAX: Returns booth list for the requested floor and year.
     */
    public static function ajax_get_booths() {
        // No nonce check: read-only public endpoint. GET method used by the
        // convention map JS so the PWA service worker can cache the response
        // at a stable URL (/wp-admin/admin-ajax.php?action=oha_get_convention_booths&floor=0).
        $floor = isset($_REQUEST['floor']) ? intval($_REQUEST['floor']) : 0;
        $year  = isset($_REQUEST['year'])  ? sanitize_text_field($_REQUEST['year']) : '';
        wp_send_json_success(self::get_booths($floor, $year));
    }

    // -----------------------------------------------------------------------
    // AJAX: Vendor Hub List (for placement tool search)
    // -----------------------------------------------------------------------

    /**
     * AJAX: Returns vendor_application posts with status Paid or Agreement Signed.
     * Used by the placement tool vendor search dropdown.
     * Admin-only.
     */
    public static function ajax_get_vendor_hub_list() {
        check_ajax_referer('oha_map_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $search    = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $statuses  = ['Paid', 'paid', 'PAID', 'Agreement Signed', 'agreement signed', 'AGREEMENT SIGNED'];

        $args = [
            'post_type'      => 'vendor_application',
            'post_status'    => 'publish',
            'posts_per_page' => 30,
            'meta_query'     => [
                ['key' => '_oha_status', 'value' => $statuses, 'compare' => 'IN'],
            ],
            'orderby'        => 'meta_value',
            'meta_key'       => 'company_name',
            'order'          => 'ASC',
        ];

        // Text search filter
        if ($search) {
            $args['meta_query'][] = [
                'relation' => 'OR',
                ['key' => 'company_name', 'value' => $search, 'compare' => 'LIKE'],
                ['key' => 'first_name',   'value' => $search, 'compare' => 'LIKE'],
                ['key' => 'last_name',    'value' => $search, 'compare' => 'LIKE'],
            ];
            // Can't use nested meta_query with multiple LIKE easily; use title search instead
            unset($args['meta_query'][count($args['meta_query']) - 1]);
            $args['s'] = $search;
        }

        $query   = new WP_Query($args);
        $vendors = [];

        foreach ($query->posts as $post) {
            $company = get_post_meta($post->ID, 'company_name', true);
            $first   = get_post_meta($post->ID, 'first_name', true);
            $last    = get_post_meta($post->ID, 'last_name', true);
            $name    = $company ?: trim($first . ' ' . $last) ?: $post->post_title;

            // Skip if search term doesn't match name
            if ($search && stripos($name, $search) === false) continue;

            $status  = get_post_meta($post->ID, '_oha_status', true);
            $logo    = get_post_meta($post->ID, '_company_logo_url', true);
            $logo_id = (int) get_post_meta($post->ID, '_company_logo_id', true);
            if (!$logo && $logo_id) {
                $img  = wp_get_attachment_image_src($logo_id, [48, 48]);
                $logo = $img ? $img[0] : '';
            }

            $vendors[] = [
                'id'     => $post->ID,
                'name'   => $name,
                'status' => $status,
                'logo'   => $logo,
            ];
        }

        wp_reset_postdata();

        // If no vendor_application CPT exists, return empty with a helpful message
        if (empty($vendors) && !post_type_exists('vendor_application')) {
            wp_send_json_success([]);
            return;
        }

        wp_send_json_success($vendors);
    }

    // -----------------------------------------------------------------------
    // AJAX: Save Booth from Placement Tool
    // -----------------------------------------------------------------------

    /**
     * AJAX: Create or update a booth from the visual placement tool.
     * Admin-only.
     */
    public static function ajax_save_booth_placement() {
        check_ajax_referer('oha_map_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $settings    = self::get_settings();
        $active_year = $settings['active_year'] ?? date('Y');

        $booth_id    = intval($_POST['booth_id'] ?? 0);
        $number      = sanitize_text_field($_POST['number'] ?? '');
        $type        = sanitize_text_field($_POST['type'] ?? 'vendor');
        $floor       = intval($_POST['floor'] ?? 1);
        $x           = floatval($_POST['x'] ?? 0);
        $y           = floatval($_POST['y'] ?? 0);
        $width       = floatval($_POST['width'] ?? 0);
        $height      = floatval($_POST['height'] ?? 0);
        $vendor_hub_id = intval($_POST['vendor_hub_id'] ?? 0);
        $hours       = sanitize_text_field($_POST['hours'] ?? '');
        $status      = sanitize_text_field($_POST['status'] ?? 'open');
        $color       = sanitize_hex_color($_POST['color'] ?? '') ?: '';
        $year        = sanitize_text_field($_POST['year'] ?? $active_year);

        // Derive post title from vendor name or booth number
        $post_title = $number ? 'Booth ' . $number : 'New Booth';
        if ($vendor_hub_id) {
            $hub_company = get_post_meta($vendor_hub_id, 'company_name', true);
            if ($hub_company) {
                $post_title = ($number ? 'Booth ' . $number . ' - ' : '') . $hub_company;
            }
        }

        if ($booth_id > 0) {
            // Update existing
            wp_update_post(['ID' => $booth_id, 'post_title' => $post_title]);
        } else {
            // Create new
            $booth_id = wp_insert_post([
                'post_type'   => 'oha_booth',
                'post_status' => 'publish',
                'post_title'  => $post_title,
            ]);
            if (is_wp_error($booth_id)) {
                wp_send_json_error($booth_id->get_error_message());
            }
        }

        // Save all meta
        update_post_meta($booth_id, '_booth_number',       $number);
        update_post_meta($booth_id, '_booth_type',         $type);
        update_post_meta($booth_id, '_booth_floor',        $floor);
        update_post_meta($booth_id, '_booth_x',            $x);
        update_post_meta($booth_id, '_booth_y',            $y);
        update_post_meta($booth_id, '_booth_width',        $width);
        update_post_meta($booth_id, '_booth_height',       $height);
        update_post_meta($booth_id, '_booth_vendor_hub_id',$vendor_hub_id);
        update_post_meta($booth_id, '_booth_hours',        $hours);
        update_post_meta($booth_id, '_booth_status',       $status);
        update_post_meta($booth_id, '_booth_color',        $color);
        update_post_meta($booth_id, '_booth_year',         $year);

        // Return the full booth data (same shape as get_booths())
        $booths = self::get_booths(0, $year);
        $saved  = null;
        foreach ($booths as $b) {
            if ((int) $b['id'] === (int) $booth_id) {
                $saved = $b;
                break;
            }
        }

        if (!$saved) {
            // Fallback: build minimal response
            $saved = [
                'id'         => $booth_id,
                'title'      => $post_title,
                'number'     => $number,
                'type'       => $type,
                'floor'      => $floor,
                'year'       => $year,
                'x'          => $x,
                'y'          => $y,
                'type_color' => $color ?: self::get_color_for_type($type),
                'type_label' => self::get_booth_types()[$type] ?? ucfirst($type),
                'icon'       => self::get_icon_for_type($type),
                'status'     => $status,
            ];
        }

        wp_send_json_success($saved);
    }

    // -----------------------------------------------------------------------
    // AJAX: Delete Booth from Placement Tool
    // -----------------------------------------------------------------------

    /**
     * AJAX: Move a booth post to trash.
     * Admin-only.
     */
    public static function ajax_delete_booth_placement() {
        check_ajax_referer('oha_map_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        $booth_id = intval($_POST['booth_id'] ?? 0);
        if (!$booth_id) {
            wp_send_json_error('Invalid booth ID');
        }
        $result = wp_trash_post($booth_id);
        if ($result) {
            wp_send_json_success(['deleted' => $booth_id]);
        } else {
            wp_send_json_error('Could not delete booth');
        }
    }

    // -----------------------------------------------------------------------
    // AJAX: Clone Year Layout
    // -----------------------------------------------------------------------

    /**
     * AJAX: Copy booth positions from one year to another.
     * Copies: title, number, type, floor, x, y, width, height, color, status.
     * Does NOT copy: vendor_hub_id, hours, contact, website (re-assigned for new year).
     * Admin-only.
     */
    public static function ajax_clone_year_layout() {
        check_ajax_referer('oha_map_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $from_year = sanitize_text_field($_POST['from_year'] ?? '');
        $to_year   = sanitize_text_field($_POST['to_year'] ?? '');

        if (!$from_year || !$to_year || $from_year === $to_year) {
            wp_send_json_error('Invalid years specified');
        }

        $source_booths = self::get_booths(0, $from_year);

        if (empty($source_booths)) {
            wp_send_json_error('No booths found for year ' . $from_year);
        }

        $cloned = 0;
        foreach ($source_booths as $b) {
            $new_title = $b['number'] ? 'Booth ' . $b['number'] : ($b['title'] ?: 'Booth');
            $new_id = wp_insert_post([
                'post_type'   => 'oha_booth',
                'post_status' => 'publish',
                'post_title'  => $new_title,
            ]);
            if (is_wp_error($new_id)) continue;

            update_post_meta($new_id, '_booth_year',   $to_year);
            update_post_meta($new_id, '_booth_number', $b['number']);
            update_post_meta($new_id, '_booth_type',   $b['type']);
            update_post_meta($new_id, '_booth_floor',  $b['floor']);
            update_post_meta($new_id, '_booth_x',      $b['x']);
            update_post_meta($new_id, '_booth_y',      $b['y']);
            update_post_meta($new_id, '_booth_width',  $b['width']);
            update_post_meta($new_id, '_booth_height', $b['height']);
            update_post_meta($new_id, '_booth_color',  $b['color']);
            update_post_meta($new_id, '_booth_status', 'tbd'); // New year = TBD until confirmed

            $cloned++;
        }

        wp_send_json_success([
            'cloned'    => $cloned,
            'from_year' => $from_year,
            'to_year'   => $to_year,
        ]);
    }

    // -----------------------------------------------------------------------
    // Settings
    // -----------------------------------------------------------------------

    /**
     * Get convention settings with defaults.
     *
     * @return array
     */
    public static function get_settings() {
        $defaults = [
            'enabled'     => false,
            'active_year' => date('Y'),
            'event_name'  => '',
            'event_dates' => '',
            'venue_name'  => '',
            'pin_size'       => 5,
            'deep_link_zoom' => 10,
            'floors'         => [
                [
                    'id'        => 1,
                    'label'     => 'Main Hall',
                    'image_id'  => 0,
                    'image_url' => '',
                    'bg_color'  => '#1a1a2e',
                ],
            ],
        ];
        $saved    = get_option('oha_convention_settings', []);
        $settings = wp_parse_args($saved, $defaults);

        // Heal duplicate floor IDs — can occur when a floor was deleted then
        // a new floor was added before the maxFloorId fix (v2.11.4).
        $seen_ids = [];
        $max_id   = 0;
        foreach ($settings['floors'] as $f) {
            $max_id = max($max_id, (int) $f['id']);
        }
        $changed = false;
        foreach ($settings['floors'] as &$f) {
            if (in_array((int) $f['id'], $seen_ids, true)) {
                $f['id'] = ++$max_id;
                $changed = true;
            }
            $seen_ids[] = (int) $f['id'];
        }
        unset($f);
        if ($changed) {
            $saved_settings = get_option('oha_convention_settings', []);
            $saved_settings['floors'] = $settings['floors'];
            update_option('oha_convention_settings', $saved_settings);
        }

        return $settings;
    }

    /**
     * Get a single floor config by ID.
     *
     * @param int $floor_id
     * @return array|null
     */
    public static function get_floor($floor_id) {
        $settings = self::get_settings();
        foreach ($settings['floors'] as $floor) {
            if ((int) $floor['id'] === (int) $floor_id) {
                return $floor;
            }
        }
        return null;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    public static function get_booth_types() {
        return apply_filters('oha_convention_booth_types', [
            'vendor'     => __('Vendor', 'oha-modern-map'),
            'food'       => __('Food & Drink', 'oha-modern-map'),
            'food_truck' => __('Food Truck', 'oha-modern-map'),
            'restroom'   => __('Restrooms', 'oha-modern-map'),
            'class'      => __('Class / Seminar', 'oha-modern-map'),
            'stage'      => __('Stage / Performance', 'oha-modern-map'),
            'info'       => __('Information', 'oha-modern-map'),
            'exit'       => __('Exit / Entrance', 'oha-modern-map'),
            'general'    => __('General', 'oha-modern-map'),
        ]);
    }

    public static function get_color_for_type($type) {
        $colors = [
            'vendor'     => '#e0f908',
            'food'       => '#ffd700',
            'food_truck' => '#e67e22',
            'restroom'   => '#00b4d8',
            'class'      => '#9d4edd',
            'stage'      => '#e74c3c',
            'info'       => '#27ae60',
            'exit'       => '#95a5a6',
            'general'    => '#7f8c8d',
        ];
        return $colors[$type] ?? '#7f8c8d';
    }

    public static function get_icon_for_type($type) {
        $icons = [
            'vendor'     => 'conv-vendor',
            'food'       => 'conv-food',
            'food_truck' => 'conv-food',
            'restroom'   => 'conv-restroom',
            'class'      => 'conv-class',
            'stage'      => 'conv-stage',
            'info'       => 'conv-info',
            'exit'       => 'conv-exit',
            'general'    => 'conv-general',
        ];
        return $icons[$type] ?? 'conv-general';
    }
}
