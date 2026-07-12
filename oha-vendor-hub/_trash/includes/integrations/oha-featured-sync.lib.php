<?php
/**
 * OHA Board Hub – Featured Sync (1.3.15)
 * - Settings to wire discount -> Featured
 * - Hooks to update GeoDirectory detail.featured and attach Featured term + default category
 */

if (!defined('ABSPATH')) exit;

// Option name for this module
if (!defined('OHA_BHS_FEATURED_OPT')) define('OHA_BHS_FEATURED_OPT', 'oha_bhs_featured_sync');

/** Resolve (or create) the Featured term id */
if (!function_exists('oha_bhs_get_featured_term_id')) {
function oha_bhs_get_featured_term_id() {
    $taxonomy = 'gd_placecategory';
    $term = get_term_by('slug', 'featured', $taxonomy);
    if (!$term) { $term = get_term_by('name', 'Featured', $taxonomy); }
    if (!$term) {
        $res = wp_insert_term('Featured', $taxonomy, ['slug' => 'featured']);
        if (is_wp_error($res)) return 0;
        return (int) $res['term_id'];
    }
    return (int) $term->term_id;
}}

/** Low-level: set GeoDirectory detail.featured to 0/1 */
if (!function_exists('oha_bhs_set_gd_featured_flag')) {
function oha_bhs_set_gd_featured_flag( $listing_id, $featured_on ) {
    global $wpdb;
    $table = $wpdb->prefix . 'geodir_gd_place_detail';
    if (get_post_type($listing_id) !== 'gd_place') return false;
    $wpdb->update($table, ['featured' => $featured_on ? 1 : 0], ['post_id' => (int)$listing_id]);
    return true;
}}

/** Enforce Featured term + default category immediately (add-only) */
if (!function_exists('oha_bhs_enforce_featured_now')) {
function oha_bhs_enforce_featured_now( $listing_id ) {
    $term_id = oha_bhs_get_featured_term_id();
    if (!$term_id) return;

    // Attach Featured term (merge, don't replace)
    $ids = wp_get_object_terms($listing_id, 'gd_placecategory', ['fields' => 'ids']);
    if (!is_wp_error($ids)) {
        if (!in_array((int)$term_id, array_map('intval', (array)$ids), true)) {
            $merged = array_unique(array_merge((array)$ids, [(int)$term_id]));
            wp_set_object_terms($listing_id, $merged, 'gd_placecategory');
        }
    }

    // Sync default category (both keys are harmless to set)
    update_post_meta($listing_id, 'default_category', (int)$term_id);
    update_post_meta($listing_id, 'geodir_default_category', (int)$term_id);

    // Also keep GD detail default_category aligned
    global $wpdb;
    $table = $wpdb->prefix . 'geodir_gd_place_detail';
    $wpdb->update($table, ['default_category' => (int)$term_id], ['post_id' => (int)$listing_id]);
}}

/** Remove Featured term (only when explicitly unfeaturing) */
if (!function_exists('oha_bhs_remove_featured_term_now')) {
function oha_bhs_remove_featured_term_now( $listing_id ) {
    $term = get_term_by('slug', 'featured', 'gd_placecategory');
    if ($term) {
        wp_remove_object_terms($listing_id, (int)$term->term_id, 'gd_placecategory');
    }
}}

/** SETTINGS UI */

// The main menu is created by oha-board-hub-suite.php
// Featured Sync settings are accessible via the Board Hub Settings tab
// So we don't need a separate submenu item anymore

/*
add_action('admin_menu', function(){
    add_submenu_page(
        OHA_BHS_MENU,
        'Featured Sync',
        'Featured Sync',
        'manage_options',
        'oha-bhs-featured-sync',
        'oha_bhs_featured_sync_page'
    );
});
*/

function oha_bhs_featured_sync_defaults() {
    return array(
        'enabled'        => 1,
        'mode'           => 'actions', // actions | meta
        'auto_unfeature' => 1,
        'discount_cpt'   => 'oha_haunt_discount',
        'active_meta'    => 'is_active',
        'listing_meta'   => 'listing_id'
    );
}

function oha_bhs_featured_sync_get() {
    $opt = get_option(OHA_BHS_FEATURED_OPT, array());
    $def = oha_bhs_featured_sync_defaults();
    return wp_parse_args(is_array($opt) ? $opt : array(), $def);
}

function oha_bhs_featured_sync_save() {
    if (!current_user_can('manage_options')) return;
    if (!isset($_POST['oha_bhs_featured_sync_nonce']) || !wp_verify_nonce($_POST['oha_bhs_featured_sync_nonce'], 'oha_bhs_featured_sync')) return;

    $opt = oha_bhs_featured_sync_get();
    $opt['enabled']        = isset($_POST['enabled']) ? 1 : 0;
    $opt['mode']           = in_array($_POST['mode'] ?? 'actions', array('actions','meta'), true) ? $_POST['mode'] : 'actions';
    $opt['auto_unfeature'] = isset($_POST['auto_unfeature']) ? 1 : 0;
    $opt['discount_cpt']   = sanitize_key($_POST['discount_cpt'] ?? 'oha_haunt_discount');
    $opt['active_meta']    = sanitize_key($_POST['active_meta'] ?? 'is_active');
    $opt['listing_meta']   = sanitize_key($_POST['listing_meta'] ?? 'listing_id');

    update_option(OHA_BHS_FEATURED_OPT, $opt);
    add_settings_error('oha_bhs_featured_sync', 'saved', 'Featured Sync settings saved.', 'updated');
}

add_action('admin_init', function(){
    if (isset($_POST['oha_bhs_featured_sync_save'])) {
        oha_bhs_featured_sync_save();
    }
});

function oha_bhs_featured_sync_page(){
    if (!current_user_can('manage_options')) { echo '<div class="wrap"><h1>Featured Sync</h1><p>Insufficient permissions.</p></div>'; return; }
    $o = oha_bhs_featured_sync_get();
    settings_errors('oha_bhs_featured_sync');
    ?>
    <div class="wrap">
        <h1>Featured Sync</h1>
        <form method="post">
            <?php wp_nonce_field('oha_bhs_featured_sync','oha_bhs_featured_sync_nonce'); ?>
            <table class="form-table" role="presentation">
                <tr><th scope="row">Enable</th>
                    <td><label><input type="checkbox" name="enabled" <?php checked($o['enabled'],1); ?>> Turn on Featured sync</label></td>
                </tr>
                <tr><th scope="row">Trigger Mode</th>
                    <td>
                        <label><input type="radio" name="mode" value="actions" <?php checked($o['mode'],'actions'); ?>> Board Hub actions (recommended)</label><br>
                        <label><input type="radio" name="mode" value="meta" <?php checked($o['mode'],'meta'); ?>> Watch Discount meta (no code changes)</label>
                    </td>
                </tr>
                <tr><th scope="row">Auto-Unfeature on Deactivate</th>
                    <td><label><input type="checkbox" name="auto_unfeature" <?php checked($o['auto_unfeature'],1); ?>> When a discount deactivates, clear Featured</label></td>
                </tr>
                <tr><th scope="row">Discount CPT</th>
                    <td><input type="text" class="regular-text" name="discount_cpt" value="<?php echo esc_attr($o['discount_cpt']); ?>"></td>
                </tr>
                <tr><th scope="row">Active Meta Key</th>
                    <td><input type="text" class="regular-text" name="active_meta" value="<?php echo esc_attr($o['active_meta']); ?>"><p class="description">Meta key on the discount CPT that is truthy when active (e.g. 1/yes/true)</p></td>
                </tr>
                <tr><th scope="row">Listing ID Meta Key</th>
                    <td><input type="text" class="regular-text" name="listing_meta" value="<?php echo esc_attr($o['listing_meta']); ?>"><p class="description">Meta key on the discount CPT that stores the related gd_place post ID</p></td>
                </tr>
            </table>
            <p><button class="button button-primary" name="oha_bhs_featured_sync_save" value="1">Save Settings</button></p>
        </form>
        <hr>
        <h2>How it works</h2>
        <ol>
            <li>When enabled, Board Hub will keep GeoDirectory's <code>featured</code> flag, the <em>Featured</em> category, and <em>default_category</em> in sync.</li>
            <li><strong>Actions mode:</strong> call <code>do_action('oha_discount_activated', $listing_id)</code> or <code>do_action('oha_discount_deactivated', $listing_id)</code> in your discount workflow.</li>
            <li><strong>Meta mode:</strong> we watch your Discount CPT for changes to <em>Active Meta</em>. When true → feature; when false → unfeature (if checked).</li>
        </ol>
    </div>
    <?php
}

/** CORE HOOKS **/
add_action('plugins_loaded', function(){
    $o = oha_bhs_featured_sync_get();
    if (!$o['enabled']) return;

    // Seatbelt: if GD detail says featured=1, ensure Featured term + default exist (add-only).
    add_action('set_object_terms', function($object_id, $tt_ids, $taxonomy) {
        if ($taxonomy !== 'gd_placecategory' || get_post_type($object_id) !== 'gd_place') return;
        global $wpdb;
        $table = $wpdb->prefix . 'geodir_gd_place_detail';
        $val = $wpdb->get_var( $wpdb->prepare("SELECT featured FROM {$table} WHERE post_id=%d", $object_id) );
        if ((int)$val !== 1) return;
        oha_bhs_enforce_featured_now($object_id);
    }, 20, 3);

    // Actions mode: respond to Board Hub action hooks (add these do_action calls in your discount code)
    add_action('oha_discount_activated', function($listing_id){
        if (!$listing_id) return;
        oha_bhs_set_gd_featured_flag($listing_id, true);
        oha_bhs_enforce_featured_now($listing_id);
    }, 10, 1);

    add_action('oha_discount_deactivated', function($listing_id){
        $opts = oha_bhs_featured_sync_get();
        if (!$listing_id) return;
        if (!empty($opts['auto_unfeature'])) {
            oha_bhs_set_gd_featured_flag($listing_id, false);
            oha_bhs_remove_featured_term_now($listing_id);
        } else {
            // Leave the Featured term; just drop the GD flag
            oha_bhs_set_gd_featured_flag($listing_id, false);
        }
    }, 10, 1);

    // Meta mode watcher
    add_action('updated_post_meta', function($meta_id, $post_id, $meta_key, $meta_value){
        $o = oha_bhs_featured_sync_get();
        if ($o['mode'] !== 'meta') return;
        if (get_post_type($post_id) !== $o['discount_cpt']) return;
        if ($meta_key !== $o['active_meta']) return;

        $listing_id = (int) get_post_meta($post_id, $o['listing_meta'], true);
        if (!$listing_id) return;

        $v = is_string($meta_value) ? strtolower(trim($meta_value)) : $meta_value;
        $truthy = in_array($v, array('1','yes','true','on'), true) or $v === 1 or $v === true;

        if ($truthy) {
            oha_bhs_set_gd_featured_flag($listing_id, true);
            oha_bhs_enforce_featured_now($listing_id);
        } else {
            if (!empty($o['auto_unfeature'])) {
                oha_bhs_set_gd_featured_flag($listing_id, false);
                oha_bhs_remove_featured_term_now($listing_id);
            } else {
                oha_bhs_set_gd_featured_flag($listing_id, false);
            }
        }
    }, 10, 4);
});
