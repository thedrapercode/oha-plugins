<?php
/**
 * Cache Utility
 *
 * Centralized caching system using WordPress object cache
 *
 * @package OHA_Board_Hub
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OHA Cache class
 */
class OHA_Cache {
    
    const CACHE_GROUP = 'oha_board_hub';
    const DEFAULT_EXPIRATION = 3600; // 1 hour
    
    /**
     * Get cached value
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return mixed|false Cached value or false if not found
     */
    public static function get($key, $group = self::CACHE_GROUP) {
        return wp_cache_get($key, $group);
    }
    
    /**
     * Set cached value
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param string $group Cache group
     * @param int $expiration Expiration time in seconds
     * @return bool Success status
     */
    public static function set($key, $value, $group = self::CACHE_GROUP, $expiration = self::DEFAULT_EXPIRATION) {
        return wp_cache_set($key, $value, $group, $expiration);
    }
    
    /**
     * Delete cached value
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool Success status
     */
    public static function delete($key, $group = self::CACHE_GROUP) {
        return wp_cache_delete($key, $group);
    }
    
    /**
     * Flush entire cache group
     *
     * @param string $group Cache group
     * @return bool Success status
     */
    public static function flush_group($group = self::CACHE_GROUP) {
        if (function_exists('wp_cache_flush_group')) {
            return wp_cache_flush_group($group);
        }
        
        // Fallback: delete common keys if wp_cache_flush_group not available
        return false;
    }
    
    /**
     * Remember - cache callback result
     *
     * Executes callback and caches result if not already cached
     *
     * @param string $key Cache key
     * @param callable $callback Callback to execute if not cached
     * @param int $expiration Expiration time in seconds
     * @param string $group Cache group
     * @return mixed Cached or fresh value
     */
    public static function remember($key, $callback, $expiration = self::DEFAULT_EXPIRATION, $group = self::CACHE_GROUP) {
        $cached = self::get($key, $group);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $value = call_user_func($callback);
        self::set($key, $value, $group, $expiration);
        
        return $value;
    }
    
    /**
     * Remember forever - cache without expiration
     *
     * @param string $key Cache key
     * @param callable $callback Callback to execute if not cached
     * @param string $group Cache group
     * @return mixed Cached or fresh value
     */
    public static function remember_forever($key, $callback, $group = self::CACHE_GROUP) {
        return self::remember($key, $callback, 0, $group);
    }
    
    /**
     * Increment cached value
     *
     * @param string $key Cache key
     * @param int $offset Increment offset
     * @param string $group Cache group
     * @return int|false New value or false on failure
     */
    public static function increment($key, $offset = 1, $group = self::CACHE_GROUP) {
        return wp_cache_incr($key, $offset, $group);
    }
    
    /**
     * Decrement cached value
     *
     * @param string $key Cache key
     * @param int $offset Decrement offset
     * @param string $group Cache group
     * @return int|false New value or false on failure
     */
    public static function decrement($key, $offset = 1, $group = self::CACHE_GROUP) {
        return wp_cache_decr($key, $offset, $group);
    }
    
    /**
     * Add value only if it doesn't exist
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param string $group Cache group
     * @param int $expiration Expiration time in seconds
     * @return bool Success status
     */
    public static function add($key, $value, $group = self::CACHE_GROUP, $expiration = self::DEFAULT_EXPIRATION) {
        return wp_cache_add($key, $value, $group, $expiration);
    }
    
    /**
     * Generate cache key from multiple parts
     *
     * @param array $parts Key parts
     * @return string Cache key
     */
    public static function make_key($parts) {
        if (!is_array($parts)) {
            $parts = [$parts];
        }
        
        return md5(implode('_', $parts));
    }
    
    /**
     * Clear all OHA Board Hub caches
     */
    public static function clear_all() {
        self::flush_group(self::CACHE_GROUP);
        
        // Also clear related transients
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_oha_%' 
            OR option_name LIKE '_transient_timeout_oha_%'"
        );
        
        do_action('oha_cache_cleared');
    }
}
