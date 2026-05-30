<?php
/**
 * Plugin Name: OHA Resource Library
 * Description: Simple Private Mode: members-only resource library with private storage, session-gated downloads, bulk private import, badges, filters, and thumbnails.
 * Version: 0.5.0
 * Author: OHA + ChatGPT
 * License: GPLv2 or later
 * Text Domain: oha-resource-library
 */
if ( ! defined('ABSPATH') ) { exit; }
define('OHA_RL_VER', '0.5.0');
define('OHA_RL_DIR', plugin_dir_path(__FILE__));
define('OHA_RL_URL', plugin_dir_url(__FILE__));

require_once OHA_RL_DIR . 'includes/cpt.php';
require_once OHA_RL_DIR . 'includes/meta.php';
require_once OHA_RL_DIR . 'includes/membership.php';
require_once OHA_RL_DIR . 'includes/shortcode-library.php';
require_once OHA_RL_DIR . 'includes/downloads-private.php';
require_once OHA_RL_DIR . 'includes/settings.php';
require_once OHA_RL_DIR . 'includes/bulk-import-private.php';

function oha_rl_activate(){
  oha_rl_register_cpt();

  // Ensure protected folders exist
  $base = oha_rl_get_private_base();
  $incoming = oha_rl_get_incoming_dir();
  $files = oha_rl_get_files_dir();

  if ( ! file_exists( $base ) ) { wp_mkdir_p( $base ); }
  if ( ! file_exists( $incoming ) ) { wp_mkdir_p( $incoming ); }
  if ( ! file_exists( $files ) ) { wp_mkdir_p( $files ); }

  // Write .htaccess to block direct access (Apache)
  $ht = "Require all denied\n";
  @file_put_contents( trailingslashit($base) . ".htaccess", $ht );

  if ( ! get_role('oha_member') ) { add_role('oha_member', 'OHA Member', array('read' => true)); }
  flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'oha_rl_activate');

function oha_rl_deactivate(){ flush_rewrite_rules(); }
register_deactivation_hook(__FILE__, 'oha_rl_deactivate');

add_action('wp_enqueue_scripts', function(){
  wp_register_style('oha-rl', OHA_RL_URL . 'assets/css/style.css', array(), OHA_RL_VER);
});
