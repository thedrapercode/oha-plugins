<?php
if (!defined('ABSPATH')) exit;

/**
 * Default fields configuration.
 * These keys must match existing meta keys on vendor_application posts.
 */
function oha_vhcpt_fields_default(){
    return [
        ['key'=>'first_name','label'=>'First Name','type'=>'text','required'=>true],
        ['key'=>'last_name','label'=>'Last Name','type'=>'text','required'=>true],
        ['key'=>'company_name','label'=>'Company Name','type'=>'text','required'=>true],
        ['key'=>'email','label'=>'Email','type'=>'email','required'=>true],
        ['key'=>'phone','label'=>'Phone','type'=>'text','required'=>true],
        ['key'=>'address_street','label'=>'Address','type'=>'text','required'=>true],
        ['key'=>'address_city','label'=>'City','type'=>'text','required'=>true],
        ['key'=>'address_state','label'=>'US States','type'=>'state','required'=>true],
        ['key'=>'address_zip','label'=>'Zip','type'=>'text','required'=>true],
        // Website as a simple text box (no URL validation or formatting enforcement).
        ['key'=>'website','label'=>'Company Website (if applicable)','type'=>'text','required'=>false],
        // Products as multiline textarea so longer descriptions can wrap.
        ['key'=>'product_type','label'=>'What products are you selling?','type'=>'textarea','required'=>true],
        ['key'=>'booths','label'=>'Number of booth spaces requested','type'=>'number','required'=>true],
        ['key'=>'sponsor','label'=>'Would you be interested in being a sponsor?','type'=>'checkbox','required'=>false],
    ];
}

/**
 * Frontend Vendor Application Form
 *
 * Shortcode: [oha_vendor_form]
 *
 * NOTE: This form is intentionally open to the public (no login required).
 */
add_shortcode('oha_vendor_form', function(){
    // Keep styling the same as before.
    wp_enqueue_style('oha-vhcpt');

    // Applications closed check — must come before anything else.
    if (get_option('oha_vhcpt_apps_closed', 0)) {
        $closed_msg = get_option('oha_vhcpt_apps_closed_message', 'Applications are closed for the 2026 Ohio Haunters Convention. Please keep us in mind for 2027.');
        return '<div class="oha-vh-notice" style="padding:1.2em 1.5em;border-left:4px solid #b32020;background:#fff5f5;border-radius:0 4px 4px 0;margin:1em 0;">' . wp_kses_post($closed_msg) . '</div>';
    }

    $user   = wp_get_current_user();
    $fields = get_option('oha_vhcpt_fields', oha_vhcpt_fields_default());
    $msg    = '';

    // Check for success redirect parameter (Post/Redirect/Get pattern)
    if (isset($_GET['vendor_submitted']) && $_GET['vendor_submitted'] === 'success') {
        $msg = '<div class="oha-vh-success">✅ Your vendor application has been submitted successfully! We will review it and contact you soon at the email address you provided.</div>';
        // Don't show the form after successful submission
        return $msg;
    }

    // Handle submission.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oha_vhcpt_nonce']) && wp_verify_nonce($_POST['oha_vhcpt_nonce'],'oha_vhcpt_submit')){
        
        // Honeypot check - if filled, it's a bot
        if (!empty($_POST['vendor_website_url'])) {
            // Silently fail - show success to avoid retry
            $msg = '<div class="oha-vh-success">✅ Your vendor application has been submitted successfully! We will review it and contact you soon at the email address you provided.</div>' ;
            return $msg;
        }


        $meta = [];
        $errors = [];

        foreach($fields as $f){
            $key = $f['key'];
            $raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';

            switch($f['type']){
                case 'email':
                    $meta[$key] = sanitize_email($raw);
                    break;
                case 'number':
                    $meta[$key] = intval($raw);
                    break;
                case 'checkbox':
                    $meta[$key] = isset($_POST[$key]) ? 1 : 0;
                    break;
                case 'textarea':
                    $meta[$key] = sanitize_textarea_field($raw);
                    break;
                default:
                    // Includes 'text', 'state', and any other simple text-ish types.
                    $meta[$key] = sanitize_text_field($raw);
                    break;
            }

            // Server-side validation for required fields
            if (!empty($f['required'])){
                if (empty($meta[$key]) && $meta[$key] !== '0' && $meta[$key] !== 0) {
                    $errors[] = sprintf('The field "%s" is required.', $f['label']);
                }
            }
        }

        // If validation errors, show them and stop
        if (!empty($errors)) {
            $msg = '<div class="oha-vh-error">❌ Please correct the following errors:<ul>';
            foreach($errors as $err) {
                $msg .= '<li>' . esc_html($err) . '</li>';
            }
            $msg .= '</ul></div>';
        } else {
            // Check for duplicate submission by email within last 10 minutes
            $recent_duplicate = new WP_Query([
                'post_type' => 'vendor_application',
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => 'email',
                        'value' => $meta['email'],
                        'compare' => '='
                    ]
                ],
                'date_query' => [
                    [
                        'after' => '10 minutes ago'
                    ]
                ],
                'posts_per_page' => 1
            ]);

            if ($recent_duplicate->have_posts()) {
                wp_reset_postdata();
                $msg = '<div class="oha-vh-error">⚠️ We recently received an application from this email address. If you need to update your application, please contact us directly at <a href="mailto:info@ohiohaunters.com">info@ohiohaunters.com</a>.</div>';
            } else {
                // Build a sensible title.
                $title = '';
                if (!empty($meta['company_name'])){
                    $title = $meta['company_name'];
                } elseif (!empty($meta['first_name']) || !empty($meta['last_name'])) {
                    $title = trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? ''));
                }
                if ($title === ''){
                    $title = 'Vendor Application';
                }

                $post_id = wp_insert_post([
                    'post_type'   => 'vendor_application',
                    'post_status' => 'publish',
                    'post_title'  => $title,
                    'post_author' => get_current_user_id(),
                ], true);

                if (is_wp_error($post_id)){
                    $msg = '<div class="oha-vh-error">❌ Error creating application: '.esc_html($post_id->get_error_message()).'</div>';
                } else {
                    foreach($meta as $k => $v){
                        update_post_meta($post_id, $k, $v);
                    }
                    // Initial status.
                    update_post_meta($post_id, '_oha_status', 'Submitted');

                    // Fire automated email for the "Submitted" step, if configured.
                    if (function_exists('oha_vhcpt_maybe_send_status_email')){
                        oha_vhcpt_maybe_send_status_email($post_id, 'Submitted');
                    }

                                        // Inline success (legacy behavior) - do not redirect from inside a shortcode
                    $msg = '<div class="oha-vh-success">✅ Your vendor application has been submitted successfully! We will review it and contact you soon at the email address you provided.</div>';
                    return $msg;

                }
            }
        }
    }

    ob_start();
    echo $msg;
    ?>
    <form class="oha-vh-form" method="post">
        <?php wp_nonce_field('oha_vhcpt_submit', 'oha_vhcpt_nonce'); ?>
        
        <!-- Honeypot field - hidden from real users, caught by bots -->
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
            <label>Leave this field blank
                <input type="text" name="vendor_website_url" value="" tabindex="-1" autocomplete="off">
            </label>
        </div>

        <?php foreach($fields as $f):
            $key   = esc_attr($f['key']);
            $label = esc_html($f['label']);
            $req   = !empty($f['required']) ? 'required' : '';
            $prefill = '';
            if ($key === 'first_name' && $user && $user->ID){
                $prefill = esc_attr($user->user_firstname);
            } elseif ($key === 'last_name' && $user && $user->ID){
                $prefill = esc_attr($user->user_lastname);
            } elseif ($key === 'email' && $user && $user->ID){
                $prefill = esc_attr($user->user_email);
            }
        ?>
        <div class="oha-vh-row">
            <label for="<?php echo $key; ?>"><?php echo $label; ?><?php echo $req ? ' *' : ''; ?></label>
            <?php if ($f['type'] === 'state'): ?>
                <select id="<?php echo $key; ?>" name="<?php echo $key; ?>" <?php echo $req; ?>>
                    <option value=""><?php esc_html_e('Select…','oha-vhcpt'); ?></option>
                    <?php echo oha_vhcpt_render_states(); ?>
                </select>
            <?php elseif ($f['type'] === 'checkbox'): ?>
                <input type="checkbox" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="1">
            <?php elseif ($f['type'] === 'textarea'): ?>
                <textarea id="<?php echo $key; ?>" name="<?php echo $key; ?>" rows="4" <?php echo $req; ?>><?php echo esc_textarea($prefill); ?></textarea>
            <?php else: ?>
                <input
                    type="<?php echo esc_attr($f['type'] === 'number' ? 'number' : 'text'); ?>"
                    id="<?php echo $key; ?>"
                    name="<?php echo $key; ?>"
                    value="<?php echo $prefill; ?>"
                    <?php echo $req; ?>
                >
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <button type="submit" class="oha-vh-btn" id="oha-submit-btn">
            <?php esc_html_e('Submit Application','oha-vhcpt'); ?>
        </button>
    </form>

    <script>
    (function(){
        var form = document.querySelector('.oha-vh-form');
        var btn = document.getElementById('oha-submit-btn');
        if (form && btn) {
            var submitted = false;
            form.addEventListener('submit', function(e){
                // Prevent double submission
                if (submitted) {
                    e.preventDefault();
                    return false;
                }
                
                // Disable button to prevent double-clicks
                submitted = true;
                btn.disabled = true;
                btn.classList.add('submitting');
                btn.textContent = 'Submitting...';
                
                // Re-enable after 5 seconds as fallback (in case validation fails)
                setTimeout(function(){
                    submitted = false;
                    btn.disabled = false;
                    btn.classList.remove('submitting');
                    btn.textContent = 'Submit Application';
                }, 5000);
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
});
