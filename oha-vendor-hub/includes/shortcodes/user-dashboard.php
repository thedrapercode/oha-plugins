<?php
if (!defined('ABSPATH')) exit;

add_shortcode('oha_vendor_dashboard', function(){
    if (!is_user_logged_in()) return '<p>Please log in to view your submissions.</p>';
    wp_enqueue_style('oha-vhcpt');

    $q = new WP_Query([
        'post_type' => 'vendor_application',
        'post_status' => 'any',
        'author' => get_current_user_id(),
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    ob_start(); ?>
    <div class="oha-vh-user-dash">
        <h3>Your Vendor Submissions</h3>
        <?php if (!$q->have_posts()): ?>
            <p>No submissions yet.</p>
        <?php else: ?>
        <table class="oha-vh-table">
            <thead><tr>
                <th>Date</th><th>Company</th><th>Status</th><th>Agreement</th><th>Payment</th>
            </tr></thead>
            <tbody>
            <?php while($q->have_posts()): $q->the_post(); $id = get_the_ID(); ?>
                <tr>
                    <td><?php echo esc_html(get_the_date('Y-m-d', $id)); ?></td>
                    <td><?php echo esc_html(get_post_meta($id,'company_name',true) ?: get_the_title()); ?></td>
                    <td><span class="oha-badge status-<?php echo esc_attr(strtolower(str_replace(' ','_', get_post_meta($id,'_oha_status',true)))); ?>"><?php echo esc_html(get_post_meta($id,'_oha_status',true) ?: 'Submitted'); ?></span></td>
                    <td><?php $a = get_post_meta($id,'_agreement_url',true); echo $a ? '<a href="'.esc_url($a).'" target="_blank" rel="noopener">Open</a>' : '—'; ?></td>
                    <td><?php $p = get_post_meta($id,'_payment_url',true); echo $p ? '<a href="'.esc_url($p).'" target="_blank" rel="noopener">Pay</a>' : '—'; ?></td>
                </tr>
            <?php endwhile; wp_reset_postdata(); ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
});
