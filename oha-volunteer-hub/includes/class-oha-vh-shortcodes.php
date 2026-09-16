<?php
/**
 * OHA Volunteer Hub - Shortcodes (Phase 3)
 * Volunteer management shortcodes only - task system removed
 */

namespace OHA\VH;

if (!defined('ABSPATH')) { exit; }

class Shortcodes {

    public static function init() {
        // Phase 3 shortcodes - volunteer system only
        add_shortcode('oha_volunteer_signup', [__CLASS__, 'signup_form']);
        add_shortcode('oha_volunteer_board_hub', [__CLASS__, 'board_hub']);
        add_shortcode('oha_volunteer_portal', [__CLASS__, 'volunteer_portal']);
        add_shortcode('oha_area_manager_dashboard', [__CLASS__, 'area_manager_dashboard']);
        add_shortcode('oha_volunteer_handbook', [__CLASS__, 'volunteer_handbook']);
    }

    /**
     * Signup form shortcode
     */
    public static function signup_form($atts) {
        return SignupForm::render();
    }

    /**
     * Board hub - tabbed volunteer management interface (Phase 4)
     */
    public static function board_hub($atts) {
        if (!is_user_logged_in() || !Core::can_manage()) {
            return '<div class="oha-vh-error">Access restricted to board members and administrators.</div>';
        }

        wp_enqueue_style('oha-vh');
        wp_enqueue_script('oha-vh');

        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        $managed_areas = Database::get_areas_by_manager($user_id);
        $all_areas = Database::get_areas();

        // If not admin and no managed areas, show error
        if (!$is_admin && empty($managed_areas)) {
            return '<div style="text-align: center; padding: 60px;">
                <h3>No Areas Assigned</h3>
                <p>You haven\'t been assigned as a manager to any areas yet. Contact an administrator to be added as an area manager.</p>
            </div>';
        }

        ob_start();
        include OHA_VH_PATH . 'templates/board-hub-tabbed.php';
        return ob_get_clean();
    }

    /**
     * Volunteer portal - shows volunteer's assignments
     */
    public static function volunteer_portal($atts) {
        if (!is_user_logged_in()) {
            return '<div class="oha-vh-login-required" style="text-align: center; padding: 60px 20px; background: white; border-radius: 8px;">
                <div style="font-size: 72px; margin-bottom: 20px;">🔒</div>
                <h3>Login Required</h3>
                <p>Please log in to view your volunteer assignments.</p>
                <p><a href="' . wp_login_url(get_permalink()) . '" class="button button-primary">Log In</a></p>
            </div>';
        }

        $user_id     = get_current_user_id();
        $user_email  = wp_get_current_user()->user_email;
        $active_year = Database::get_active_year();

        global $wpdb;
        $volunteer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oha_volunteers WHERE (user_id = %d OR email = %s) AND convention_year = %d LIMIT 1",
            $user_id, $user_email, $active_year
        ));

        if (!$volunteer) {
            $signup_url = rtrim(get_option('oha_convention_base_url', '/convention'), '/') . '/volunteers/';
            return '<div style="text-align: center; padding: 60px 20px; background: white; border-radius: 8px;">
                <div style="font-size: 72px; margin-bottom: 20px;">📋</div>
                <h3>Not Registered Yet</h3>
                <p>You haven\'t signed up as a volunteer yet!</p>
                <p><a href="' . esc_url($signup_url) . '" class="button button-primary">Sign Up Now</a></p>
            </div>';
        }

        $assignments = Database::get_assignments_by_volunteer($volunteer->id);
        $messages = Database::get_messages_for_volunteer($volunteer->id);

        ob_start();
        include OHA_VH_PATH . 'templates/volunteer-portal.php';
        return ob_get_clean();
    }

    /**
     * Area manager dashboard - for board members
     */
    public static function area_manager_dashboard($atts) {
        if (!is_user_logged_in()) {
            return '<div>Please log in to view this page.</div>';
        }

        $user_id = get_current_user_id();
        $managed_areas = Database::get_areas_by_manager($user_id);

        if (empty($managed_areas) && !Core::can_manage()) {
            return '<div style="text-align: center; padding: 60px;">
                <h3>No Areas Assigned</h3>
                <p>You haven\'t been assigned as a manager to any areas yet.</p>
            </div>';
        }

        if (Core::can_manage() && empty($managed_areas)) {
            $managed_areas = Database::get_areas();
        }

        ob_start();
        include OHA_VH_PATH . 'templates/area-manager-dashboard.php';
        return ob_get_clean();
    }

    /**
     * Volunteer handbook shortcode
     */
    public static function volunteer_handbook($atts) {
        wp_enqueue_style('oha-vh');
        
        ob_start();
        include OHA_VH_PATH . 'templates/volunteer-handbook.php';
        return ob_get_clean();
    }
}
