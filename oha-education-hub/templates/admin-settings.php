<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php $s = oha_education_get_settings(); ?>
<div class="wrap">
<h1>Education Hub Settings</h1>

<?php if ( isset( $_GET['saved'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
<?php endif; ?>

<p>Configure all content for the <code>[oha_education]</code> shortcode. Changes take effect immediately on save.</p>
<p><strong>Shortcode usage:</strong> <code>[oha_education]</code> &mdash; Optional attrs: <code>track=""</code>, <code>year=""</code>, <code>show_hero="0"</code>, <code>show_values="0"</code>, <code>show_faq="0"</code>, <code>show_cta="0"</code></p>

<style>
.oha-admin-section { background:#fff; border:1px solid #c3c4c7; border-radius:4px; margin:16px 0 24px; padding:0 20px 16px; }
.oha-admin-section h2 { border-bottom:1px solid #eee; margin:0 -20px 16px; padding:12px 20px; font-size:14px; font-weight:600; }
.oha-admin-section .form-table th { width:180px; padding:12px 10px; }
.oha-faq-row { background:#f9f9f9; border:1px solid #ddd; border-radius:4px; padding:10px 12px; margin-bottom:8px; }
.oha-faq-row label { display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:3px; }
.oha-faq-row input.widefat { margin-bottom:6px; }
.oha-faq-row .button { margin-top:4px; }
.oha-faq-empty { color:#999; font-style:italic; padding:10px 0; }
</style>

<form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
<?php wp_nonce_field( 'oha_education_save' ); ?>
<input type="hidden" name="action" value="oha_education_save">

<!-- General -->
<div class="oha-admin-section">
    <h2>General</h2>
    <table class="form-table">
    <tr>
        <th><label for="default_track">Default Track Slug</label></th>
        <td>
            <input type="text" id="default_track" name="default_track" value="<?php echo esc_attr( $s['default_track'] ); ?>" class="regular-text">
            <p class="description">The <code>class_track</code> taxonomy slug to display. Default: <code>education</code>. Override per-shortcode with <code>track="slug"</code>.</p>
        </td>
    </tr>
    </table>
</div>

<!-- Hero -->
<div class="oha-admin-section">
    <h2>Hero Section</h2>
    <table class="form-table">
    <tr>
        <th><label for="hero_heading">Heading</label></th>
        <td><input type="text" id="hero_heading" name="hero_heading" value="<?php echo esc_attr( $s['hero_heading'] ); ?>" class="large-text"></td>
    </tr>
    <tr>
        <th><label for="hero_subheading">Subheading</label></th>
        <td><textarea id="hero_subheading" name="hero_subheading" rows="3" class="large-text"><?php echo esc_textarea( $s['hero_subheading'] ); ?></textarea></td>
    </tr>
    <tr>
        <th><label for="hero_btn_text">Button Text</label></th>
        <td><input type="text" id="hero_btn_text" name="hero_btn_text" value="<?php echo esc_attr( $s['hero_btn_text'] ); ?>" class="regular-text"></td>
    </tr>
    <tr>
        <th><label for="hero_btn_url">Button URL</label></th>
        <td>
            <input type="text" id="hero_btn_url" name="hero_btn_url" value="<?php echo esc_attr( $s['hero_btn_url'] ); ?>" class="large-text">
            <p class="description">Use <code>#oha-edu-sessions</code> to scroll to the classes grid on the same page.</p>
        </td>
    </tr>
    </table>
</div>

<!-- Value Cards -->
<div class="oha-admin-section">
    <h2>Value Cards (shown below hero)</h2>
    <table class="form-table">
    <?php for ( $i = 1; $i <= 3; $i++ ) : ?>
    <tr>
        <th>Card <?php echo $i; ?></th>
        <td>
            <p>
                <label for="value_<?php echo $i; ?>_icon">Icon (emoji, HTML entity, or leave blank)</label><br>
                <input type="text" id="value_<?php echo $i; ?>_icon" name="value_<?php echo $i; ?>_icon" value="<?php echo esc_attr( $s[ "value_{$i}_icon" ] ); ?>" class="regular-text">
            </p>
            <p>
                <label for="value_<?php echo $i; ?>_title">Title</label><br>
                <input type="text" id="value_<?php echo $i; ?>_title" name="value_<?php echo $i; ?>_title" value="<?php echo esc_attr( $s[ "value_{$i}_title" ] ); ?>" class="large-text">
            </p>
            <p>
                <label for="value_<?php echo $i; ?>_desc">Description</label><br>
                <textarea id="value_<?php echo $i; ?>_desc" name="value_<?php echo $i; ?>_desc" rows="2" class="large-text"><?php echo esc_textarea( $s[ "value_{$i}_desc" ] ); ?></textarea>
            </p>
        </td>
    </tr>
    <?php endfor; ?>
    </table>
</div>

<!-- Sessions -->
<div class="oha-admin-section">
    <h2>Sessions Section</h2>
    <table class="form-table">
    <tr>
        <th><label for="sessions_heading">Heading</label></th>
        <td><input type="text" id="sessions_heading" name="sessions_heading" value="<?php echo esc_attr( $s['sessions_heading'] ); ?>" class="large-text"></td>
    </tr>
    <tr>
        <th><label for="sessions_subheading">Subheading / Note</label></th>
        <td><textarea id="sessions_subheading" name="sessions_subheading" rows="2" class="large-text"><?php echo esc_textarea( $s['sessions_subheading'] ); ?></textarea></td>
    </tr>
    </table>
    <p style="margin-top:0;padding:0 10px 8px;color:#666;font-size:13px;">
        Class data is pulled live from OHA Schedule Hub. To add, edit, or remove classes go to <strong>Classes</strong> in the WP admin menu.
    </p>
</div>

<!-- FAQ -->
<div class="oha-admin-section">
    <h2>FAQ Section</h2>
    <table class="form-table">
    <tr>
        <th><label for="faq_heading">Section Heading</label></th>
        <td><input type="text" id="faq_heading" name="faq_heading" value="<?php echo esc_attr( $s['faq_heading'] ); ?>" class="large-text"></td>
    </tr>
    <tr>
        <th>Questions &amp; Answers</th>
        <td>
            <div id="oha-edu-faq-repeater"></div>
            <button type="button" id="oha-edu-faq-add" class="button" style="margin-top:4px;">+ Add Question</button>
            <input type="hidden" id="oha-edu-faq-json" name="faq_items" value="<?php echo esc_attr( $s['faq_items'] ); ?>">
            <p class="description">Each pair renders as an accordion item. Answers support basic HTML (bold, italic, links).</p>
        </td>
    </tr>
    </table>
</div>

<!-- CTA -->
<div class="oha-admin-section">
    <h2>Call to Action Strip (bottom)</h2>
    <table class="form-table">
    <tr>
        <th><label for="cta_heading">Heading</label></th>
        <td><input type="text" id="cta_heading" name="cta_heading" value="<?php echo esc_attr( $s['cta_heading'] ); ?>" class="large-text"></td>
    </tr>
    <tr>
        <th><label for="cta_subheading">Subheading</label></th>
        <td><textarea id="cta_subheading" name="cta_subheading" rows="2" class="large-text"><?php echo esc_textarea( $s['cta_subheading'] ); ?></textarea></td>
    </tr>
    <tr>
        <th><label for="cta_btn_text">Button Text</label></th>
        <td><input type="text" id="cta_btn_text" name="cta_btn_text" value="<?php echo esc_attr( $s['cta_btn_text'] ); ?>" class="regular-text"></td>
    </tr>
    <tr>
        <th><label for="cta_btn_url">Button URL</label></th>
        <td><input type="text" id="cta_btn_url" name="cta_btn_url" value="<?php echo esc_attr( $s['cta_btn_url'] ); ?>" class="large-text"></td>
    </tr>
    </table>
</div>

<?php submit_button( 'Save Settings' ); ?>
</form>
</div>
