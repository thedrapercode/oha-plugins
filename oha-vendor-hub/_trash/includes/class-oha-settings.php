<?php
/**
 * Settings Manager
 *
 * Centralized settings management with dot notation support
 *
 * @package OHA_Board_Hub
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OHA Settings Manager class
 */
class OHA_Settings {
    
    /**
     * Single instance
     *
     * @var OHA_Settings
     */
    private static $instance = null;
    
    /**
     * Settings cache
     *
     * @var array
     */
    private $settings = null;
    
    /**
     * Default settings
     *
     * @var array
     */
    private $defaults = null;
    
    /**
     * Get singleton instance
     *
     * @return OHA_Settings
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->defaults = $this->get_defaults();
        $this->load();
    }
    
    /**
     * Load settings from database
     */
    private function load() {
        $saved = get_option(OHA_BHS_OPT, []);
        $this->settings = $this->merge_with_defaults($saved);
    }
    
    /**
     * Get setting value
     *
     * @param string|null $path Dot notation path (e.g., 'stripe.secret_key')
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get($path = null, $default = null) {
        if (null === $path) {
            return $this->settings;
        }
        
        $keys = explode('.', $path);
        $value = $this->settings;
        
        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }
        
        return $value;
    }
    
    /**
     * Set setting value
     *
     * @param string $path Dot notation path
     * @param mixed $value Value to set
     * @return bool Success status
     */
    public function set($path, $value) {
        $keys = explode('.', $path);
        $settings = &$this->settings;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $settings[$key] = $value;
            } else {
                if (!isset($settings[$key]) || !is_array($settings[$key])) {
                    $settings[$key] = [];
                }
                $settings = &$settings[$key];
            }
        }
        
        return true;
    }
    
    /**
     * Update setting and save to database
     *
     * @param string $path Dot notation path
     * @param mixed $value Value to set
     * @return bool Success status
     */
    public function update($path, $value) {
        $this->set($path, $value);
        return $this->save();
    }
    
    /**
     * Save settings to database
     *
     * @return bool Success status
     */
    public function save() {
        $result = update_option(OHA_BHS_OPT, $this->settings);
        
        if ($result) {
            do_action('oha_settings_saved', $this->settings);
        }
        
        return $result;
    }
    
    /**
     * Merge saved settings with defaults
     *
     * @param array $saved Saved settings
     * @return array Merged settings
     */
    private function merge_with_defaults($saved) {
        if (!is_array($saved)) {
            $saved = [];
        }
        return array_replace_recursive($this->defaults, $saved);
    }
    
    /**
     * Get default settings
     *
     * @return array Default settings
     */
    public function get_defaults() {
        if (null !== $this->defaults) {
            return $this->defaults;
        }
        
        return [
            'stripe' => [
                'secret_key' => '',
                'webhook_secret' => '',
                'endpoint_path' => 'oha/v1/stripe-webhook',
                'timestamp_tolerance' => 300,
                'portal_return_url' => home_url('/'),
                'invoice_limit' => 12,
                'price_filter' => '',
                'price_signup_20' => '',
                'price_yearly_10' => '',
                'membership_level_id' => 0,
                'trial_end_iso' => '2027-01-01T00:00:00Z',
                'log' => 1,
            ],
            'vendor' => [
                'front_cap' => 'edit_posts',
                'convention_2026_page_id' => 0,
            ],
            'swpm' => [
                'free_level_id' => 2,
                'paid_level_id' => 10,
            ],
        ];
    }
    
    /**
     * Reset settings to defaults
     *
     * @return bool Success status
     */
    public function reset() {
        $this->settings = $this->get_defaults();
        return $this->save();
    }
    
    /**
     * Export settings
     *
     * @return string JSON encoded settings
     */
    public function export() {
        return wp_json_encode($this->settings, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import settings from JSON
     *
     * @param string $json JSON encoded settings
     * @return bool|WP_Error Success status or error
     */
    public function import($json) {
        $imported = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Invalid JSON format', 'oha-board-hub'));
        }
        
        if (!is_array($imported)) {
            return new WP_Error('invalid_data', __('Invalid settings data', 'oha-board-hub'));
        }
        
        $this->settings = $this->merge_with_defaults($imported);
        return $this->save();
    }
}
