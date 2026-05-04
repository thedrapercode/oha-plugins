<?php
/**
 * PWA Manifest Generator
 * 
 * Generates the manifest.json file for the OHA Convention PWA
 */

if (!defined('ABSPATH')) exit;

class OHA_PWA_Manifest {
    
    /**
     * Generate manifest JSON
     */
    public static function generate() {
        $site_name = get_bloginfo('name');
        $icon_url = OHA_PWA_URL . 'assets/icons/';
        
        $manifest = [
            'id'          => '/convention/',
            'name'        => 'OHC - Ohio Haunters Convention',
            'short_name'  => 'OHC',
            'description' => 'The official app for the Ohio Haunters Convention. Schedules, vendors, maps, announcements, and updates—all in one place.',
            'start_url'   => '/convention/?source=pwa',
            'scope'       => OHA_PWA_SCOPE,
            'display'     => 'standalone',
            'orientation' => 'portrait-primary',
            'theme_color' => '#8B0000',
            'background_color' => '#8B0000',
            
            'icons' => [
                [
                    'src' => 'https://ohiohaunters.com/wp-content/uploads/2025/12/OHC.jpg',
                    'sizes' => '512x512',
                    'type' => 'image/jpeg',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => $icon_url . 'icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any'
                ],
                [
                    'src' => $icon_url . 'icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any'
                ]
            ],
            
            'screenshots' => [],
            
            'categories' => ['entertainment', 'events', 'business'],
            
            'shortcuts' => [
                [
                    'name' => 'My Schedule',
                    'short_name' => 'Favorites',
                    'description' => 'View your favorited events',
                    'url' => '/convention/2026-convention/my-schedule/',
                    'icons' => [
                        [
                            'src' => $icon_url . 'icon-192.png',
                            'sizes' => '192x192',
                            'type' => 'image/png'
                        ]
                    ]
                ],
                [
                    'name' => 'Event Schedule',
                    'short_name' => 'Schedule',
                    'description' => 'Full event schedule',
                    'url' => '/convention/2026-convention/schedule/',
                    'icons' => [
                        [
                            'src' => $icon_url . 'icon-192.png',
                            'sizes' => '192x192',
                            'type' => 'image/png'
                        ]
                    ]
                ],
                [
                    'name' => 'Vendors',
                    'short_name' => 'Vendors',
                    'description' => 'Browse vendor directory',
                    'url' => '/convention/2026-convention/vendors/',
                    'icons' => [
                        [
                            'src' => $icon_url . 'icon-192.png',
                            'sizes' => '192x192',
                            'type' => 'image/png'
                        ]
                    ]
                ],
                [
                    'name' => 'Venue Map',
                    'short_name' => 'Map',
                    'description' => 'Interactive venue map',
                    'url' => '/convention/2026-convention/map/',
                    'icons' => [
                        [
                            'src' => $icon_url . 'icon-192.png',
                            'sizes' => '192x192',
                            'type' => 'image/png'
                        ]
                    ]
                ]
            ],
            
            'prefer_related_applications' => false
        ];
        
        // Allow customization via filter
        $manifest = apply_filters('oha_pwa_manifest', $manifest);
        
        return wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Get manifest as array
     */
    public static function get_manifest_array() {
        return json_decode(self::generate(), true);
    }
}
