<?php
/**
 * PWA Cache Manager
 * 
 * Manages cache versioning and provides cache control endpoints
 */

if (!defined('ABSPATH')) exit;

class OHA_PWA_Cache_Manager {
    
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_endpoints']);
        add_action('save_post', [__CLASS__, 'maybe_bust_cache'], 10, 2);
    }
    
    /**
     * Register cache management endpoints
     */
    public static function register_endpoints() {
        // Get cache status
        register_rest_route('oha/v1', '/cache/status', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_cache_status'],
            'permission_callback' => '__return_true'
        ]);
        
        // Bust cache (admin only)
        register_rest_route('oha/v1', '/cache/bust', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'bust_cache'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    /**
     * Get current cache status
     */
    public static function get_cache_status() {
        return [
            'version' => get_option('oha_pwa_cache_version', time()),
            'last_updated' => get_option('oha_pwa_cache_updated', current_time('mysql')),
            'service_worker_version' => OHA_PWA_VERSION
        ];
    }
    
    /**
     * Bust the cache (force update)
     */
    public static function bust_cache() {
        $new_version = time();
        update_option('oha_pwa_cache_version', $new_version);
        update_option('oha_pwa_cache_updated', current_time('mysql'));
        
        return [
            'success' => true,
            'new_version' => $new_version,
            'message' => 'Cache cleared. Users will get updated content on next visit.'
        ];
    }
    
    /**
     * Maybe bust cache when convention content is updated
     */
    public static function maybe_bust_cache($post_id, $post) {
        // Only bust for convention post types
        $convention_types = ['session', 'speaker', 'vendor', 'room', 'announcement'];
        
        if (!in_array($post->post_type, $convention_types)) {
            return;
        }
        
        // Only bust if post is published
        if ($post->post_status !== 'publish') {
            return;
        }
        
        self::bust_cache();
    }
}
