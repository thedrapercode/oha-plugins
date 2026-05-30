<?php
if ( ! defined('ABSPATH') ) { exit; }
function oha_rl_get_private_base(){
  $default = trailingslashit(WP_CONTENT_DIR) . 'oha-protected';
  $set = get_option('oha_rl_private_base', $default);
  return trailingslashit( $set );
}
function oha_rl_get_incoming_dir(){
  $base = oha_rl_get_private_base();
  $set = get_option('oha_rl_incoming_dir', $base . 'incoming');
  return trailingslashit( $set );
}
function oha_rl_get_files_dir(){
  $base = oha_rl_get_private_base();
  $set = get_option('oha_rl_files_dir', $base . 'files');
  return trailingslashit( $set );
}
add_action('init', function(){
  add_rewrite_rule('^resource-download/([0-9]+)/?$', 'index.php?oha_dl_id=$matches[1]', 'top');
  add_rewrite_tag('%oha_dl_id%','([0-9]+)');
});
add_action('template_redirect', function(){
  $id = get_query_var('oha_dl_id');
  if ( ! $id ) return;
  if ( ! is_user_logged_in() || ! function_exists('oha_rl_user_is_member') || ! oha_rl_user_is_member() ){
    wp_die('Members only.', 'Forbidden', array('response'=>403));
  }
  $path = get_post_meta($id, '_oha_private_path', true);
  $name = get_post_meta($id, '_oha_private_name', true);
  if ( ! $path || ! file_exists($path) ){
    wp_die('File not found.', 'Not Found', array('response'=>404));
  }
  $mime = 'application/octet-stream';
  if ( function_exists('mime_content_type') ){
    $m = @mime_content_type($path);
    if ($m) $mime = $m;
  }
  if ( ! $name ) $name = basename($path);
  nocache_headers();
  header('Content-Type: ' . $mime);
  header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
  header('Content-Length: ' . filesize($path));
  header('X-Accel-Buffering: no');
  $fp = fopen($path, 'rb');
  if ($fp){
    while (!feof($fp)) { echo fread($fp, 8192); @ob_flush(); flush(); }
    fclose($fp);
  }
  exit;
});
add_action('wp_head', function () {
  if (is_singular('oha_resource') || is_post_type_archive('oha_resource')) {
    if (!function_exists('oha_rl_user_is_member') || !oha_rl_user_is_member()) {
      echo '<meta name="robots" content="noindex,nofollow" />' . "\n";
    }
  }
}, 1);
add_action('send_headers', function () {
  if ((is_singular('oha_resource') || is_post_type_archive('oha_resource'))
      && (!function_exists('oha_rl_user_is_member') || !oha_rl_user_is_member())) {
    header('X-Robots-Tag: noindex, nofollow', true);
  }
});
