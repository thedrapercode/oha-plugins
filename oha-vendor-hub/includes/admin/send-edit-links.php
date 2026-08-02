<?php
if (!defined('ABSPATH')) exit;

/**
 * Send Edit Links — admin submenu page.
 *
 * Lets admins generate and send personalized 24-hour edit-link emails
 * to vendors filtered by status. Uses the same token system as the
 * [oha_vendor_edit] magic-link flow.
 *
 * Submenu location: Vendor Applications > Send Edit Links
 */
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=vendor_application',
        'Send Edit Links',
        'Send Edit Links',
        'manage_options',
        'oha-vh-send-edit-links',
        'oha_vh_send_edit_links_page'
    );
});

function oha_vh_send_edit_links_page() {
    if (!current_user_can('manage_options')) return;

    $edit_page_id  = (int) get_option('oha_vhcpt_vendor_edit_page_id', 0);
    $edit_page_url = $edit_page_id ? get_permalink($edit_page_id) : '';
    $settings_url  = admin_url('edit.php?post_type=vendor_application&page=oha-vhcpt-settings');
    $nonce         = wp_create_nonce('oha_vh_send_edit_links');

    $all_statuses = ['Invited', 'Submitted', 'Approved', 'Agreement Sent', 'Agreement Signed', 'Paid'];
    ?>
    <div class="wrap">
        <h1>Send Vendor Edit Links</h1>

        <?php if (!$edit_page_id): ?>
            <div class="notice notice-error">
                <p><strong>Vendor Edit Page is not configured.</strong>
                Go to <a href="<?php echo esc_url($settings_url); ?>">Vendor Hub Settings</a>
                and set the Vendor Edit Page before sending links.</p>
            </div>
        <?php else: ?>
            <div class="notice notice-info is-dismissible">
                <p>Each vendor will receive an email with the vendor update page URL and their Vendor ID number.
                They visit <strong><?php echo esc_html($edit_page_url); ?></strong>, enter their email and Vendor ID, and their application loads pre-filled.<br>
                Customize the email subject and body in
                <a href="<?php echo esc_url($settings_url); ?>">Vendor Hub Settings</a>
                under <em>Application Status &gt; Edit Link Email</em>.</p>
            </div>
        <?php endif; ?>

        <table class="form-table" style="max-width:640px;">
            <tr>
                <th scope="row">Send to vendors with status</th>
                <td>
                    <?php foreach ($all_statuses as $s): ?>
                        <label style="display:block;margin-bottom:5px;">
                            <input type="checkbox" class="oha-el-status" value="<?php echo esc_attr($s); ?>">
                            <?php echo esc_html($s); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>

        <p style="margin-top:0;">
            <button type="button" id="oha-el-count" class="button">Count Recipients</button>
            <span id="oha-el-count-result" style="margin-left:10px;color:#555;"></span>
        </p>

        <p>
            <button type="button" id="oha-el-send" class="button button-primary"
                <?php echo $edit_page_id ? '' : 'disabled title="Configure the Vendor Edit Page first"'; ?>>
                Send Edit Links
            </button>
            <button type="button" id="oha-el-test" class="button" style="margin-left:10px;"
                <?php echo $edit_page_id ? '' : 'disabled title="Configure the Vendor Edit Page first"'; ?>>
                Send Test Email to Me
            </button>
            <span style="margin-left:8px;color:#666;font-size:13px;">
                Test sends to <strong><?php echo esc_html(wp_get_current_user()->user_email); ?></strong>
                using the first vendor application on file.
            </span>
        </p>

        <div id="oha-el-result" style="margin-top:10px;max-width:640px;"></div>
    </div>

    <script>
    (function($){
        var nonce   = <?php echo wp_json_encode($nonce); ?>;
        var ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;

        function getStatuses() {
            var s = [];
            $('.oha-el-status:checked').each(function(){ s.push($(this).val()); });
            return s;
        }

        function showResult(html) {
            $('#oha-el-result').html(html);
        }

        $('#oha-el-count').on('click', function(){
            var statuses = getStatuses();
            if (!statuses.length) {
                $('#oha-el-count-result').text('Select at least one status.');
                return;
            }
            $('#oha-el-count-result').text('Counting...');
            $.post(ajaxUrl, {
                action: 'oha_vh_edit_links_count',
                nonce: nonce,
                vendor_statuses: statuses
            }, function(r){
                if (r.success) {
                    $('#oha-el-count-result').text(r.data.count + ' vendor(s) will receive a link.');
                } else {
                    $('#oha-el-count-result').text('Error: ' + (r.data || 'Unknown error'));
                }
            }).fail(function(){
                $('#oha-el-count-result').text('Request failed.');
            });
        });

        $('#oha-el-send').on('click', function(){
            var statuses = getStatuses();
            if (!statuses.length) {
                showResult('<div class="notice notice-warning inline"><p>Select at least one status.</p></div>');
                return;
            }
            if (!confirm('Send edit links to all vendors with the selected statuses?\n\nEach vendor will receive the vendor update page URL and their Vendor ID number. This cannot be undone.')) {
                return;
            }

            var btn = $(this);
            btn.prop('disabled', true).text('Sending...');
            showResult('<p style="color:#555;">Sending links, please wait...</p>');

            $.post(ajaxUrl, {
                action: 'oha_vh_edit_links_send',
                nonce: nonce,
                vendor_statuses: statuses
            }, function(r){
                btn.prop('disabled', false).text('Send Edit Links');
                if (r.success) {
                    showResult('<div class="notice notice-success inline"><p>' + r.data.message + '</p></div>');
                } else {
                    showResult('<div class="notice notice-error inline"><p>Error: ' + (r.data || 'Unknown error') + '</p></div>');
                }
            }).fail(function(){
                btn.prop('disabled', false).text('Send Edit Links');
                showResult('<div class="notice notice-error inline"><p>Request failed. Please try again.</p></div>');
            });
        });

        $('#oha-el-test').on('click', function(){
            var btn = $(this);
            btn.prop('disabled', true).text('Sending...');
            showResult('');

            $.post(ajaxUrl, {
                action: 'oha_vh_test_edit_link',
                nonce: nonce
            }, function(r){
                btn.prop('disabled', false).text('Send Test Email to Me');
                if (r.success) {
                    showResult('<div class="notice notice-success inline"><p>' + r.data + '</p></div>');
                } else {
                    showResult('<div class="notice notice-error inline"><p>Error: ' + (r.data || 'Unknown error') + '</p></div>');
                }
            }).fail(function(){
                btn.prop('disabled', false).text('Send Test Email to Me');
                showResult('<div class="notice notice-error inline"><p>Request failed. Please try again.</p></div>');
            });
        });
    })(jQuery);
    </script>
    <?php
}
