<?php
if ( ! defined('ABSPATH') ) { exit; }
add_action('admin_menu', function(){
  add_submenu_page('edit.php?post_type=oha_resource','Bulk Import (Private)','Bulk Import (Private)','manage_options','oha-rl-bulk-private','oha_rl_bulk_private_page');
});
function oha_rl_bulk_private_page(){
  $incoming = untrailingslashit( oha_rl_get_incoming_dir() );
  $files_dir = untrailingslashit( oha_rl_get_files_dir() );
  if ( isset($_POST['oha_rl_do_import']) && check_admin_referer('oha_rl_bulk_private','oha_rl_bulk_nonce') ){
    $count = 0;
    if ( is_dir($incoming) ){
      $files = array_diff(scandir($incoming), array('.', '..'));
      foreach ($files as $file){
        $src = $incoming . '/' . $file;
        if ( is_dir($src) ) continue;
        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        $allowed = array('pdf','doc','docx','ppt','pptx','xlsx','csv','txt','rtf','zip');
        if ( ! in_array($ext, $allowed, true) ) continue;
        $title = preg_replace('/[_-]+/',' ', pathinfo($file, PATHINFO_FILENAME));
        $post_id = wp_insert_post(array('post_type'=>'oha_resource','post_status'=>'publish','post_title'=> ucwords($title),'post_content'=>'','post_author'=>get_current_user_id(),));
        if ( $post_id && ! is_wp_error($post_id) ){
          if ( ! file_exists($files_dir) ) wp_mkdir_p($files_dir);
          $dest = $files_dir . '/' . wp_unique_filename($files_dir, $file);
          rename($src, $dest);
          update_post_meta($post_id, '_oha_private_path', $dest);
          update_post_meta($post_id, '_oha_private_name', $file);
          // Audience by filename tokens
          $lower = strtolower($file);
          $aud = array();
          if ( strpos($lower, 'actor') !== false ) $aud[] = 'actors';
          if ( strpos($lower, 'owner') !== false ) $aud[] = 'haunt-owners';
          if ( strpos($lower, 'staff') !== false ) $aud[] = 'haunt-staff';
          if ( strpos($lower, 'vendor') !== false ) $aud[] = 'vendors';
          if ( strpos($lower, 'sponsor') !== false ) $aud[] = 'sponsors';
          if ( $aud ) wp_set_object_terms($post_id, $aud, 'audience', true);
          // Short description
          if ( ! has_excerpt($post_id) ){
            $desc = ucwords( preg_replace('/[\s_\-]+/',' ', pathinfo($file, PATHINFO_FILENAME)) );
            wp_update_post(array('ID'=>$post_id, 'post_excerpt'=>$desc));
          }
          $count++;
        }
      }
    }
    echo '<div class="updated"><p>Imported '.intval($count).' files.</p></div>';
  }
  $candidates = array();
  if ( is_dir($incoming) ){
    $files = array_diff(scandir($incoming), array('.', '..'));
    foreach ($files as $file){
      $src = $incoming . '/' . $file;
      if ( is_dir($src) ) continue;
      $candidates[] = $file;
    }
  }
  ?>
  <div class="wrap">
    <h1>Bulk Import (Private) — From SFTP Incoming Folder</h1>
    <p>Drop files via SFTP into: <code><?php echo esc_html( untrailingslashit($incoming) ); ?></code>. Then click import to create Resource posts and move files into the private files folder.</p>
    <form method="post" style="margin:0 0 1rem 0;">
      <?php wp_nonce_field('oha_rl_bulk_private','oha_rl_bulk_nonce'); ?>
      <button type="submit" name="oha_rl_do_import" class="button button-primary">Import All</button>
    </form>
    <h2>Ready to Import (<?php echo count($candidates); ?>)</h2>
    <?php if ( $candidates ): ?>
      <table class="widefat striped"><thead><tr><th>File</th></tr></thead><tbody>
      <?php foreach ($candidates as $f): ?>
        <tr><td><?php echo esc_html($f); ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php else: ?>
      <p>No files found in the incoming folder.</p>
    <?php endif; ?>
  </div>
  <?php
}
