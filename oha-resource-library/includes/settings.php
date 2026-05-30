<?php
if ( ! defined('ABSPATH') ) { exit; }
add_action('admin_menu', function(){
  add_submenu_page( 'edit.php?post_type=oha_resource', 'OHA Resource Library Settings', 'Settings', 'manage_options', 'oha-rl-settings', 'oha_rl_settings_page' );
});
function oha_rl_settings_page(){
  if ( isset($_POST['oha_rl_settings_nonce']) && wp_verify_nonce($_POST['oha_rl_settings_nonce'], 'oha_rl_save_settings') ){
    update_option('oha_rl_join_url', esc_url_raw($_POST['oha_rl_join_url'] ?? ''));
    update_option('oha_rl_items_per_page', max(1, intval($_POST['oha_rl_items_per_page'] ?? 24)));
    $base = sanitize_text_field($_POST['oha_rl_private_base'] ?? '');
    if ($base) update_option('oha_rl_private_base', untrailingslashit($base));
    $incoming = sanitize_text_field($_POST['oha_rl_incoming_dir'] ?? '');
    if ($incoming) update_option('oha_rl_incoming_dir', untrailingslashit($incoming));
    $files = sanitize_text_field($_POST['oha_rl_files_dir'] ?? '');
    if ($files) update_option('oha_rl_files_dir', untrailingslashit($files));
    $fresh = intval($_POST['oha_rl_fresh_days'] ?? 30); if ($fresh <= 0) $fresh = 30; update_option('oha_rl_fresh_days', $fresh);
    $newd = intval($_POST['oha_rl_new_days'] ?? 14); if ($newd <= 0) $newd = 14; update_option('oha_rl_new_days', $newd);
    update_option('oha_rl_enable_pdf_thumbs', isset($_POST['oha_rl_enable_pdf_thumbs']) ? '1' : '0');
    echo '<div class="updated"><p>Settings saved.</p></div>';
  }
  $join_url = esc_url( get_option('oha_rl_join_url', '') );
  $per_page = intval( get_option('oha_rl_items_per_page', 24) );
  $base = trailingslashit( get_option('oha_rl_private_base', trailingslashit(WP_CONTENT_DIR) . 'oha-protected') );
  $incoming = trailingslashit( get_option('oha_rl_incoming_dir', $base . 'incoming') );
  $files = trailingslashit( get_option('oha_rl_files_dir', $base . 'files') );
  $fresh_days = intval( get_option('oha_rl_fresh_days', 30) );
  $new_days = intval( get_option('oha_rl_new_days', 14) );
  $enable_thumbs = get_option('oha_rl_enable_pdf_thumbs', '0') === '1';
  ?>
  <div class="wrap">
    <h1>OHA Resource Library — Simple Private Mode</h1>
    <form method="post">
      <?php wp_nonce_field('oha_rl_save_settings','oha_rl_settings_nonce'); ?>
      <table class="form-table">
        <tr><th scope="row"><label for="oha_rl_join_url">Join / Upgrade URL</label></th>
          <td><input type="url" name="oha_rl_join_url" id="oha_rl_join_url" value="<?php echo $join_url; ?>" class="regular-text" placeholder="https://ohiohaunters.com/join" /></td></tr>
        <tr><th scope="row"><label for="oha_rl_items_per_page">Items per page (library)</label></th>
          <td><input type="number" name="oha_rl_items_per_page" id="oha_rl_items_per_page" value="<?php echo $per_page; ?>" min="6" max="60" /></td></tr>
        <tr><th scope="row">Private storage paths</th>
          <td>
            <p><label>Base: <input type="text" name="oha_rl_private_base" value="<?php echo esc_attr( untrailingslashit($base) ); ?>" size="60" /></label></p>
            <p><label>Incoming (SFTP drop): <input type="text" name="oha_rl_incoming_dir" value="<?php echo esc_attr( untrailingslashit($incoming) ); ?>" size="60" /></label></p>
            <p><label>Files (served via endpoint): <input type="text" name="oha_rl_files_dir" value="<?php echo esc_attr( untrailingslashit($files) ); ?>" size="60" /></label></p>
          </td></tr>
        <tr><th scope="row"><label for="oha_rl_fresh_days">"Updated" badge window (days)</label></th>
          <td><input type="number" name="oha_rl_fresh_days" id="oha_rl_fresh_days" value="<?php echo $fresh_days; ?>" min="1" max="120" /></td></tr>
        <tr><th scope="row"><label for="oha_rl_new_days">"New" badge window (days)</label></th>
          <td><input type="number" name="oha_rl_new_days" id="oha_rl_new_days" value="<?php echo $new_days; ?>" min="1" max="120" /></td></tr>
        <tr><th scope="row">PDF thumbnail generation</th>
          <td><label><input type="checkbox" name="oha_rl_enable_pdf_thumbs" <?php checked($enable_thumbs, true); ?> /> Generate first-page thumbnail for PDFs (Imagick/Ghostscript required)</label></td></tr>
      </table>
      <?php submit_button(); ?>
    </form>
  </div>
  <?php
}
