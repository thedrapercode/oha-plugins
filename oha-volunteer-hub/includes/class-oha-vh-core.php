<?php
namespace OHA\VH;

if (!defined('ABSPATH')) { exit; }

class Core {

    const CAPABILITY = 'manage_oha_volunteers';

    // Task system removed in Phase 3 - using dedicated tables instead

    public static function register_caps() {
        // Create a primitive capability and give to admins by default.
        $role = get_role('administrator');
        if ($role && !$role->has_cap(self::CAPABILITY)) {
            $role->add_cap(self::CAPABILITY);
        }
        // Optional: if OHA Board role exists, grant it too.
        $oha_board = get_role('oha_board');
        if ($oha_board && !$oha_board->has_cap(self::CAPABILITY)) {
            $oha_board->add_cap(self::CAPABILITY);
        }
    }

    public static function can_manage() : bool {
        return current_user_can(self::CAPABILITY) || current_user_can('manage_options');
    }

    public static function init_assets() {
        add_action('wp_enqueue_scripts', [__CLASS__,'enqueue']);
        add_action('admin_enqueue_scripts', [__CLASS__,'enqueue']);
    }

    public static function enqueue() {
        wp_register_style('oha-vh', OHA_VH_URL.'assets/css/oha-vh.css', [], OHA_VH_VERSION);
        wp_register_script('oha-vh', OHA_VH_URL.'assets/js/oha-vh.js', ['jquery'], OHA_VH_VERSION, true);
        wp_localize_script('oha-vh','OHA_VH_AJAX',[
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('oha_vh_nonce'),
            'portal_nonce' => wp_create_nonce('oha_vh_portal')
        ]);
    }

    /** Helpers to read/write meta safely */
    public static function get_meta(int $post_id, string $key, $default='') {
        $v = get_post_meta($post_id, $key, true);
        return $v === '' ? $default : $v;
    }

    public static function update_meta(int $post_id, string $key, $value) {
        update_post_meta($post_id, $key, $value);
    }
}
