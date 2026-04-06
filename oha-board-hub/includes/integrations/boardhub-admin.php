<?php
/**
 * Board Hub Admin Dashboard - CARD-BASED UI
 * Modern, clean card interface for vendor management
 * 
 * VERSION 2.2.0 - PHASE 2A
 * Features:
 * - Enhanced statistics dashboard (6 stat cards)
 * - Recent activity feed
 * - Volunteer count
 * - OHA member statistics
 * - New vendor tracking (30 days)
 * - Professional grid layout
 * - Fixed AJAX parameter mismatch (send_option → option)
 * - Added ajaxurl definition for frontend
 * - Force style tag rendering
 * - Increased CSS specificity
 * - Added debug console logging
 * - Better error handling
 */

if (!defined('ABSPATH')) exit;

add_shortcode('oha_vendor_boardhub', function() {
    if (!current_user_can('manage_options')) {
        return '<p>Access denied. Admin privileges required.</p>';
    }

    // Handle CSV export - INLINE VERSION (actually works!)
    if (isset($_POST['oha_export_csv']) && wp_verify_nonce($_POST['oha_export_nonce'], 'oha_export_vendors')) {
        // Generate CSV inline
        $vendors_query = new WP_Query([
            'post_type' => 'vendor_application',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=oha-vendors-' . date('Y-m-d-His') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Prevent CSV injection: prefix formula-trigger characters with apostrophe.
        $scsv = function($v) {
            if (!is_string($v) || $v === '') return $v;
            if (preg_match('/^[=+\-@\t\r]/', $v)) return "'" . $v;
            return $v;
        };

        $output = fopen('php://output', 'w');

        // CSV Headers
        fputcsv($output, [
            'ID', 'Date', 'Company', 'Contact', 'Email', 'Phone',
            'Website', 'Product Type', 'Booths', 'Electric', 'Air',
            'Table', 'Sponsor', 'Status', 'Signed Date', 'PDF Emailed'
        ]);

        if ($vendors_query->have_posts()) {
            while ($vendors_query->have_posts()) {
                $vendors_query->the_post();
                $id = get_the_ID();

                fputcsv($output, [
                    $id,
                    get_the_date('Y-m-d H:i', $id),
                    $scsv(get_post_meta($id, 'company_name', true)),
                    $scsv(trim(get_post_meta($id, 'first_name', true) . ' ' . get_post_meta($id, 'last_name', true))),
                    $scsv(get_post_meta($id, 'email', true)),
                    $scsv(get_post_meta($id, 'phone', true)),
                    $scsv(get_post_meta($id, 'website', true)),
                    $scsv(get_post_meta($id, 'product_type', true)),
                    get_post_meta($id, 'booths', true) ?: '1',
                    get_post_meta($id, 'need_electric', true) ? 'Yes' : 'No',
                    get_post_meta($id, 'need_air', true) ? 'Yes' : 'No',
                    get_post_meta($id, 'need_table', true) ? 'Yes' : 'No',
                    get_post_meta($id, 'sponsor', true) ? 'Yes' : 'No',
                    $scsv(get_post_meta($id, '_oha_status', true) ?: 'Submitted'),
                    $scsv(get_post_meta($id, '_signature_date', true)),
                    get_post_meta($id, '_pdf_emailed', true) ? 'Yes' : 'No'
                ]);
            }
            wp_reset_postdata();
        }

        fclose($output);
        exit;
    }

    // Get all vendor applications
    $args = [
        'post_type' => 'vendor_application',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'orderby' => 'date',
        'order' => 'DESC'
    ];

    // Handle search
    if (!empty($_GET['s'])) {
        $search = sanitize_text_field($_GET['s']);
        $args['meta_query'] = [
            'relation' => 'OR',
            ['key' => 'company_name', 'value' => $search, 'compare' => 'LIKE'],
            ['key' => 'email', 'value' => $search, 'compare' => 'LIKE'],
            ['key' => 'first_name', 'value' => $search, 'compare' => 'LIKE'],
            ['key' => 'last_name', 'value' => $search, 'compare' => 'LIKE'],
        ];
    }

    $vendors = get_posts($args);
    
    ob_start();
    
    // Force style tag rendering - prevents stripping by themes/page builders
    echo '<style type="text/css">';
    ?>
/* Board Hub Card-Based UI */
    .oha-boardhub {
        max-width: 1400px;
        margin: 20px auto;
        padding: 20px;
    }
    
    .oha-boardhub-header {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .oha-boardhub-title {
        font-size: 24px;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }
    
    .oha-boardhub-stats {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .oha-stat {
        text-align: center;
        padding: 10px 20px;
        background: #f8fafc;
        border-radius: 6px;
    }
    
    .oha-stat-label {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 4px;
    }
    
    .oha-stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
    }
    
    .oha-boardhub-controls {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .oha-search-box {
        position: relative;
    }
    
    .oha-search-box input {
        padding: 10px 15px 10px 40px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 14px;
        min-width: 250px;
    }
    
    .oha-search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }
    
    .oha-btn {
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .oha-btn-primary {
        background: #3b82f6;
        color: #fff;
    }
    
    .oha-btn-primary:hover {
        background: #2563eb;
    }
    
    .oha-vendor-cards {
        display: grid;
        gap: 15px;
    }
    
    .oha-vendor-card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.2s;
    }
    
    .oha-vendor-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .oha-card-header {
        padding: 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        user-select: none;
    }
    
    .oha-card-main-info {
        display: flex;
        gap: 20px;
        align-items: center;
        flex: 1;
    }
    
    .oha-card-id {
        font-size: 14px;
        font-weight: 700;
        color: #64748b;
        min-width: 60px;
    }
    
    .oha-card-company {
        font-size: 18px;
        font-weight: 600;
        color: #1e293b;
    }
    
    .oha-card-contact {
        font-size: 14px;
        color: #64748b;
    }
    
    .oha-card-status {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-agreement-sent { background: #dbeafe; color: #1e40af; }
    .status-agreement-signed { background: #e0e7ff; color: #4338ca; }
    .status-paid { background: #d1fae5; color: #065f46; }
    .status-cancelled { background: #fee2e2; color: #991b1b; }
    
    .oha-card-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    
    .oha-expand-icon {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        transition: transform 0.2s;
    }
    
    .oha-card-expanded .oha-expand-icon {
        transform: rotate(180deg);
    }
    
    .oha-card-body {
        display: none !important; /* Increased specificity to prevent theme conflicts */
        padding: 20px;
        background: #f8fafc;
    }
    
    .oha-card-expanded .oha-card-body {
        display: block !important; /* Increased specificity to prevent theme conflicts */
    }
    
    .oha-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .oha-card-section {
        background: #fff;
        padding: 15px;
        border-radius: 6px;
    }
    
    .oha-card-section-title {
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 10px;
    }
    
    .oha-info-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .oha-info-row:last-child {
        border-bottom: none;
    }
    
    .oha-info-label {
        font-weight: 600;
        color: #475569;
    }
    
    .oha-info-value {
        color: #1e293b;
    }
    
    .oha-logo-preview {
        max-width: 100%;
        height: auto;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
        margin-bottom: 10px;
    }
    
    .oha-btn-small {
        padding: 6px 12px;
        font-size: 12px;
        border-radius: 4px;
        text-decoration: none;
        display: inline-block;
    }
    
    .oha-send-dropdown-wrapper {
        position: relative;
        display: inline-block;
    }
    
    .oha-send-dropdown-toggle {
        padding: 8px 16px;
        background: #3b82f6;
        color: #fff;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .oha-send-dropdown-toggle:hover {
        background: #2563eb;
    }
    
    .oha-send-dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1000;
        min-width: 280px;
        margin-top: 4px;
    }
    
    .oha-send-dropdown-menu.active {
        display: block;
    }
    
    .oha-send-option {
        display: block;
        width: 100%;
        padding: 12px 16px;
        border: none;
        background: none;
        text-align: left;
        cursor: pointer;
        font-size: 14px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .oha-send-option:last-child {
        border-bottom: none;
    }
    
    .oha-send-option:hover {
        background: #f8fafc;
    }
    
    .oha-send-option-title {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 4px;
    }
    
    .oha-send-option-desc {
        font-size: 12px;
        color: #64748b;
    }
    
    .oha-send-status {
        padding: 8px 12px;
        background: #f1f5f9;
        border-radius: 4px;
        font-size: 12px;
        color: #475569;
        margin-top: 8px;
    }
    
    .oha-history-item {
        padding: 10px;
        background: #fff;
        border-left: 3px solid #3b82f6;
        margin-bottom: 8px;
        border-radius: 4px;
    }
    
    .oha-history-date {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 4px;
    }
    
    .oha-history-action {
        font-size: 14px;
        color: #1e293b;
    }
    
    @media (max-width: 768px) {
        .oha-card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        
        .oha-card-main-info {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        
        .oha-card-grid {
            grid-template-columns: 1fr;
        }
    }
    <?php
    echo '</style>';
    ?>
    
    <div class="oha-boardhub">
        <!-- Enhanced Dashboard Statistics -->
        <div class="oha-boardhub-header">
            <div style="width:100%;">
                <h1 class="oha-boardhub-title">🎃 OHA Vendor Board Hub</h1>
                
                <!-- Quick Stats Grid -->
                <div class="oha-stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin:20px 0;">
                    <?php
                    global $wpdb;
                    
                    // Vendor statistics
                    $total = count($vendors);
                    $paid = 0;
                    $pending = 0;
                    $total_booths = 0;
                    
                    foreach ($vendors as $v) {
                        $status = get_post_meta($v->ID, '_oha_status', true);
                        if ($status === 'Paid') $paid++;
                        if (in_array($status, ['Submitted', 'In Review', 'Approved', 'Pending', ''])) $pending++;
                        $booths = get_post_meta($v->ID, 'booths', true);
                        $total_booths += intval($booths ?: 1);
                    }
                    
                    // Recent vendors (last 30 days)
                    $recent_vendors = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->posts}
                         WHERE post_type = 'vendor_application'
                         AND post_date >= %s",
                        date('Y-m-d H:i:s', strtotime('-30 days'))
                    ));
                    
                    // Volunteers
                    $volunteers = $wpdb->get_var(
                        "SELECT COUNT(*) FROM {$wpdb->posts} 
                         WHERE post_type = 'volunteer'"
                    );
                    
                    // OHA Members
                    $total_members = $wpdb->get_var(
                        "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
                         INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
                         WHERE m.meta_key = 'uwp_member_type'
                         AND m.meta_value = 'oha_member'"
                    );
                    
                    $new_members = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u
                         INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
                         WHERE m.meta_key = 'uwp_member_type'
                         AND m.meta_value = 'oha_member'
                         AND u.user_registered >= %s",
                        date('Y-m-d H:i:s', strtotime('-30 days'))
                    ));
                    ?>
                    
                    <div class="oha-stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;text-align:center;">
                        <div style="font-size:32px;margin-bottom:10px;">🎪</div>
                        <div class="oha-stat-value" style="font-size:28px;font-weight:700;color:#1e293b;margin:5px 0;"><?php echo $total; ?></div>
                        <div class="oha-stat-label" style="font-size:12px;color:#64748b;text-transform:uppercase;">Total Applications</div>
                        <div style="font-size:11px;color:#999;margin-top:5px;"><?php echo $recent_vendors; ?> new (30 days)</div>
                    </div>
                    
                    <div class="oha-stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;text-align:center;">
                        <div style="font-size:32px;margin-bottom:10px;">💰</div>
                        <div class="oha-stat-value" style="font-size:28px;font-weight:700;color:#059669;margin:5px 0;"><?php echo $paid; ?></div>
                        <div class="oha-stat-label" style="font-size:12px;color:#64748b;text-transform:uppercase;">Paid & Confirmed</div>
                        <div style="font-size:11px;color:#999;margin-top:5px;">Ready for event</div>
                    </div>
                    
                    <div class="oha-stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;text-align:center;">
                        <div style="font-size:32px;margin-bottom:10px;">⏳</div>
                        <div class="oha-stat-value" style="font-size:28px;font-weight:700;color:#dc2626;margin:5px 0;"><?php echo $pending; ?></div>
                        <div class="oha-stat-label" style="font-size:12px;color:#64748b;text-transform:uppercase;">Pending Payment</div>
                        <div style="font-size:11px;color:#999;margin-top:5px;">Awaiting completion</div>
                    </div>
                    
                    <div class="oha-stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;text-align:center;">
                        <div style="font-size:32px;margin-bottom:10px;">📦</div>
                        <div class="oha-stat-value" style="font-size:28px;font-weight:700;color:#7c3aed;margin:5px 0;"><?php echo $total_booths; ?></div>
                        <div class="oha-stat-label" style="font-size:12px;color:#64748b;text-transform:uppercase;">Total Booths</div>
                        <div style="font-size:11px;color:#999;margin-top:5px;">Reserved & paid</div>
                    </div>
                    
                    <div class="oha-stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;text-align:center;">
                        <div style="font-size:32px;margin-bottom:10px;">🙋</div>
                        <div class="oha-stat-value" style="font-size:28px;font-weight:700;color:#0891b2;margin:5px 0;"><?php echo $volunteers ?: '0'; ?></div>
                        <div class="oha-stat-label" style="font-size:12px;color:#64748b;text-transform:uppercase;">Volunteers</div>
                        <div style="font-size:11px;color:#999;margin-top:5px;">Signed up</div>
                    </div>
                    
                    <div class="oha-stat-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;text-align:center;">
                        <div style="font-size:32px;margin-bottom:10px;">👥</div>
                        <div class="oha-stat-value" style="font-size:28px;font-weight:700;color:#a0232a;margin:5px 0;"><?php echo $total_members ?: '0'; ?></div>
                        <div class="oha-stat-label" style="font-size:12px;color:#64748b;text-transform:uppercase;">OHA Members</div>
                        <div style="font-size:11px;color:#999;margin-top:5px;"><?php echo $new_members; ?> new (30 days)</div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <?php
                $recent_payments = $wpdb->get_results(
                    "SELECT p.ID, p.post_modified, m.meta_value as company_name
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
                     INNER JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id
                     WHERE p.post_type = 'vendor_application'
                     AND m.meta_key = 'company_name'
                     AND m2.meta_key = '_oha_status'
                     AND m2.meta_value = 'Paid'
                     ORDER BY p.post_modified DESC
                     LIMIT 5"
                );
                
                if ($recent_payments):
                ?>
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin:20px 0;">
                    <h3 style="margin:0 0 15px 0;font-size:16px;color:#1e293b;">Recent Activity</h3>
                    <?php foreach ($recent_payments as $payment): ?>
                        <div style="padding:10px 0;border-bottom:1px solid #f1f5f9;">
                            💰 <strong><?php echo esc_html($payment->company_name); ?></strong> completed payment
                            <div style="font-size:12px;color:#94a3b8;margin-top:3px;">
                                <?php echo human_time_diff(strtotime($payment->post_modified), current_time('timestamp')); ?> ago
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Search & Export Controls -->
            <div class="oha-boardhub-controls" style="width:100%;display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;">
                <form method="get" class="oha-search-box">
                    <span class="oha-search-icon">🔍</span>
                    <input type="text" name="s" placeholder="Search vendors..." value="<?php echo esc_attr($_GET['s'] ?? ''); ?>">
                </form>
                
                <form method="post">
                    <?php wp_nonce_field('oha_export_vendors', 'oha_export_nonce'); ?>
                    <button type="submit" name="oha_export_csv" class="oha-btn oha-btn-primary">
                        📊 Export CSV
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Vendor Cards -->
        <div class="oha-vendor-cards">
            <?php if (empty($vendors)): ?>
                <div style="text-align:center;padding:40px;background:#fff;border-radius:8px;">
                    <p style="font-size:18px;color:#64748b;">No vendors found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($vendors as $vendor): 
                    $app_id = $vendor->ID;
                    
                    // Get all meta data
                    $email = get_post_meta($app_id, 'email', true);
                    $company = get_post_meta($app_id, 'company_name', true);
                    $first = get_post_meta($app_id, 'first_name', true);
                    $last = get_post_meta($app_id, 'last_name', true);
                    $phone = get_post_meta($app_id, 'phone', true);
                    $status = get_post_meta($app_id, '_oha_status', true) ?: 'Pending';
                    $booths = get_post_meta($app_id, 'booths', true) ?: 1;
                    
                    // Checkboxes
                    $need_electric = get_post_meta($app_id, 'need_electric', true);
                    $need_air = get_post_meta($app_id, 'need_air', true);
                    $need_table = get_post_meta($app_id, 'need_table', true);
                    
                    // Logo
                    $logo_id = get_post_meta($app_id, '_company_logo_id', true);
                    $logo_url = get_post_meta($app_id, '_company_logo_url', true);
                    
                    // Status class
                    $status_class = strtolower(str_replace(' ', '-', $status));
                    ?>
                    
                    <div class="oha-vendor-card" data-vendor-id="<?php echo $app_id; ?>">
                        <!-- Card Header (Always Visible) -->
                        <div class="oha-card-header" onclick="toggleCard(<?php echo $app_id; ?>)">
                            <div class="oha-card-main-info">
                                <div class="oha-card-id">#<?php echo $app_id; ?></div>
                                <div>
                                    <div class="oha-card-company"><?php echo esc_html($company); ?></div>
                                    <div class="oha-card-contact"><?php echo esc_html($first . ' ' . $last); ?> • <?php echo esc_html($email); ?></div>
                                </div>
                            </div>
                            
                            <div class="oha-card-actions">
                                <span class="oha-card-status status-<?php echo $status_class; ?>">
                                    <?php echo esc_html($status); ?>
                                </span>
                                
                                <div class="oha-expand-icon">
                                    ▼
                                </div>
                            </div>
                        </div>
                        
                        <!-- Card Body (Expandable) -->
                        <div class="oha-card-body">
                            <div class="oha-card-grid">
                                <!-- Contact Info -->
                                <div class="oha-card-section">
                                    <div class="oha-card-section-title">📞 Contact Information</div>
                                    <div class="oha-info-row">
                                        <span class="oha-info-label">Email:</span>
                                        <span class="oha-info-value">
                                            <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
                                        </span>
                                    </div>
                                    <div class="oha-info-row">
                                        <span class="oha-info-label">Phone:</span>
                                        <span class="oha-info-value"><?php echo esc_html($phone); ?></span>
                                    </div>
                                    <div class="oha-info-row">
                                        <span class="oha-info-label">Date:</span>
                                        <span class="oha-info-value"><?php echo get_the_date('M j, Y', $app_id); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Booth Info -->
                                <div class="oha-card-section">
                                    <div class="oha-card-section-title">🏪 Booth Details</div>
                                    <div class="oha-info-row">
                                        <span class="oha-info-label">Booths:</span>
                                        <span class="oha-info-value"><?php echo esc_html($booths); ?></span>
                                    </div>
                                    <div class="oha-info-row">
                                        <span class="oha-info-label">Electric:</span>
                                        <span class="oha-info-value"><?php echo $need_electric ? '✅ Yes' : '❌ No'; ?></span>
                                    </div>
                                    <div class="oha-info-row">
                                        <span class="oha-info-label">Air:</span>
                                        <span class="oha-info-value"><?php echo $need_air ? '✅ Yes' : '❌ No'; ?></span>
                                    </div>
                                    <div class="oha-info-row">
                                        <span class="oha-info-label">Table:</span>
                                        <span class="oha-info-value"><?php echo $need_table ? '✅ Yes' : '❌ No'; ?></span>
                                    </div>
                                </div>
                                
                                <!-- Logo -->
                                <?php if ($logo_id && $logo_url): ?>
                                <div class="oha-card-section">
                                    <div class="oha-card-section-title">🎨 Company Logo</div>
                                    <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" class="oha-logo-preview">
                                    <a href="<?php echo esc_url($logo_url); ?>" download class="oha-btn oha-btn-primary oha-btn-small">
                                        Download Logo
                                    </a>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Actions -->
                                <div class="oha-card-section">
                                    <div class="oha-card-section-title">⚡ Quick Actions</div>
                                    
                                    <!-- Send Dropdown -->                                    <div class="oha-send-dropdown-wrapper">
                                        <button type="button" class="oha-send-dropdown-toggle" onclick="event.stopPropagation(); toggleSendDropdown(<?php echo $app_id; ?>)">
                                            📧 Send
                                            <span>▼</span>
                                        </button>
                                        <div class="oha-send-dropdown-menu" id="send-menu-<?php echo $app_id; ?>">
                                            <button type="button" class="oha-send-option" onclick="sendEmail(<?php echo $app_id; ?>, 'with_payment')">
                                                <div class="oha-send-option-title">📝 Full Application WITH Payment</div>
                                                <div class="oha-send-option-desc">Standard flow - form + Stripe payment</div>
                                            </button>
                                            <button type="button" class="oha-send-option" onclick="sendEmail(<?php echo $app_id; ?>, 'no_payment')">
                                                <div class="oha-send-option-title">✅ Full Application WITHOUT Payment</div>
                                                <div class="oha-send-option-desc">Pre-paid vendors - no payment required</div>
                                            </button>
                                            <button type="button" class="oha-send-option" onclick="sendEmail(<?php echo $app_id; ?>, 'payment_only')">
                                                <div class="oha-send-option-title">💳 Payment Link Only</div>
                                                <div class="oha-send-option-desc">Form completed, just needs payment</div>
                                            </button>
                                        </div>
                                        <div class="oha-send-status" id="send-status-<?php echo $app_id; ?>"></div>
                                    </div>
                                    
                                    <?php
                                    // PDF Download Link
                                    $pdf_path = get_post_meta($app_id, '_pdf_path', true);
                                    if ($pdf_path && file_exists($pdf_path)): 
                                        $pdf_url = str_replace(ABSPATH, home_url('/'), $pdf_path);
                                    ?>
                                    <a href="<?php echo esc_url($pdf_url); ?>" class="oha-btn oha-btn-primary oha-btn-small" download style="margin-top:10px;display:block;text-align:center;">
                                        📄 Download PDF
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- History -->
                            <?php 
                            // Collect history from multiple possible meta keys (different plugin versions)
                            $history = array();
                            $found_keys = array(); // Track which keys had data
                            
                            // Current version
                            $current_history = get_post_meta($app_id, '_oha_history', true);
                            if ($current_history && is_array($current_history)) {
                                $history = array_merge($history, $current_history);
                                $found_keys['_oha_history'] = count($current_history);
                            }
                            
                            // Older versions - try common meta key patterns
                            $legacy_keys = array(
                                'oha_history',
                                'vendor_history', 
                                '_vendor_history',
                                'oha_vendor_history',
                                '_oha_vendor_history',
                                'application_history',
                                '_application_history'
                            );
                            
                            foreach ($legacy_keys as $legacy_key) {
                                $legacy_history = get_post_meta($app_id, $legacy_key, true);
                                if ($legacy_history && is_array($legacy_history)) {
                                    $history = array_merge($history, $legacy_history);
                                    $found_keys[$legacy_key] = count($legacy_history);
                                }
                            }
                            
                            // Sort by timestamp (oldest to newest)
                            if (!empty($history)) {
                                usort($history, function($a, $b) {
                                    $time_a = isset($a['timestamp']) ? $a['timestamp'] : 0;
                                    $time_b = isset($b['timestamp']) ? $b['timestamp'] : 0;
                                    // Handle string timestamps
                                    if (!is_numeric($time_a)) {
                                        $time_a = strtotime($time_a);
                                    }
                                    if (!is_numeric($time_b)) {
                                        $time_b = strtotime($time_b);
                                    }
                                    return $time_a - $time_b;
                                });
                            }
                            
                            if (!empty($history)): 
                            ?>
                            <div class="oha-card-section" style="margin-top:20px;">
                                <div class="oha-card-section-title">📜 History (<?php echo count($history); ?> events from <?php echo count($found_keys); ?> source<?php echo count($found_keys) > 1 ? 's' : ''; ?>)</div>
                                
                                <?php if (count($found_keys) > 1): ?>
                                <div style="background: #e3f2fd; padding: 8px 12px; margin-bottom: 10px; border-radius: 4px; font-size: 11px;">
                                    <strong>Merged history from:</strong> 
                                    <?php 
                                    $key_list = array();
                                    foreach ($found_keys as $key => $count) {
                                        $key_list[] = $key . ' (' . $count . ')';
                                    }
                                    echo implode(', ', $key_list);
                                    ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php foreach (array_reverse($history) as $entry): 
                                    // Handle different timestamp formats
                                    $timestamp = isset($entry['timestamp']) ? $entry['timestamp'] : 0;
                                    if (!is_numeric($timestamp)) {
                                        $timestamp = strtotime($timestamp);
                                    }
                                    if (empty($timestamp)) {
                                        continue; // Skip entries without valid timestamp
                                    }
                                ?>
                                <div class="oha-history-item">
                                    <div class="oha-history-date">
                                        <?php echo date('M j, Y g:i A', $timestamp); ?>
                                    </div>
                                    <div class="oha-history-action">
                                        <?php 
                                        switch ($entry['type']) {
                                            // Original Board Hub events
                                            case 'application_submitted':
                                                echo '📝 Application submitted';
                                                break;
                                            case 'final_application_signed':
                                                echo '✍️ Agreement signed';
                                                break;
                                            case 'payment_received':
                                                echo '💰 Payment received ($' . ($entry['amount'] ?? '0') . ')';
                                                break;
                                            case 'pdf_generated':
                                                echo '📄 PDF generated';
                                                break;
                                            case 'email_sent':
                                                echo '📧 Email sent to ' . ($entry['to'] ?? 'vendor');
                                                break;
                                            case 'pre_paid_vendor':
                                                echo '✅ Pre-paid vendor confirmed';
                                                break;
                                            
                                            // Vendor Hub events
                                            case 'payment_completed_webhook':
                                                $amount = $entry['amount'] ?? 0;
                                                echo '💳 Payment completed via Stripe webhook ($' . number_format($amount, 2) . ')';
                                                break;
                                            case 'agreement_sent':
                                                echo '📧 Agreement email sent to vendor';
                                                break;
                                            case 'agreement_sent_with_payment':
                                                echo '📧 Agreement + payment link sent';
                                                break;
                                            case 'agreement_sent_no_payment':
                                                echo '📧 Agreement sent (no payment required)';
                                                break;
                                            case 'payment_link_sent':
                                                echo '💳 Payment link sent to vendor';
                                                break;
                                            case 'agreement_recovered':
                                                $recovered_by = $entry['recovered_by_name'] ?? 'admin';
                                                echo '🔧 Agreement regenerated by ' . esc_html($recovered_by) . ' (Recovery Tool)';
                                                break;
                                            case 'status_manually_changed':
                                                $changed_by = $entry['changed_by'] ?? 'admin';
                                                $status_from = $entry['status_from'] ?? '';
                                                $status_to = $entry['status_to'] ?? '';
                                                echo '✏️ Status manually changed by ' . esc_html($changed_by) . ': "' . esc_html($status_from) . '" → "' . esc_html($status_to) . '"';
                                                break;
                                            case 'status_corrected':
                                                $corrected_by = $entry['corrected_by'] ?? 'admin';
                                                $status_from = $entry['status_from'] ?? '';
                                                $status_to = $entry['status_to'] ?? '';
                                                echo '⚠️ Status corrected by ' . esc_html($corrected_by) . ': "' . esc_html($status_from) . '" → "' . esc_html($status_to) . '"';
                                                break;
                                            case 'bulk_status_reset':
                                                $reset_by = $entry['reset_by'] ?? 'admin';
                                                echo '🔄 Bulk status reset by ' . esc_html($reset_by);
                                                break;
                                            case 'payment_manually_synced':
                                                $synced_by = $entry['synced_by'] ?? 'admin';
                                                $amount = $entry['amount'] ?? 0;
                                                echo '💳 Payment manually synced from Stripe by ' . esc_html($synced_by) . ' ($' . number_format($amount, 2) . ')';
                                                break;
                                            
                                            // Fallback for unknown types
                                            default:
                                                $type_display = isset($entry['type']) ? $entry['type'] : 'unknown_event';
                                                echo '📝 ' . esc_html(ucwords(str_replace('_', ' ', $type_display)));
                                                // Show additional info if available
                                                if (isset($entry['note']) && !empty($entry['note'])) {
                                                    echo ': ' . esc_html($entry['note']);
                                                } else if (isset($entry['message']) && !empty($entry['message'])) {
                                                    echo ': ' . esc_html($entry['message']);
                                                }
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    // Define ajaxurl for frontend (it's only available in admin by default)
    var ajaxurl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
    </script>
    
    <script>
    // Toggle card expand/collapse
    function toggleCard(vendorId) {
        console.log('Toggling card:', vendorId); // Debug logging
        const card = document.querySelector(`[data-vendor-id="${vendorId}"]`);
        if (card) {
            card.classList.toggle('oha-card-expanded');
            console.log('Card expanded:', card.classList.contains('oha-card-expanded')); // Debug logging
        } else {
            console.error('Card not found for vendor ID:', vendorId);
        }
    }
    
    // Toggle send dropdown
    function toggleSendDropdown(vendorId) {
        const menu = document.getElementById(`send-menu-${vendorId}`);
        
        // Close all other dropdowns
        document.querySelectorAll('.oha-send-dropdown-menu').forEach(m => {
            if (m.id !== `send-menu-${vendorId}`) {
                m.classList.remove('active');
            }
        });
        
        menu.classList.toggle('active');
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.oha-send-dropdown-wrapper')) {
            document.querySelectorAll('.oha-send-dropdown-menu').forEach(m => {
                m.classList.remove('active');
            });
        }
    });
    
    // Send email via AJAX
    function sendEmail(appId, sendOption) {
        const statusDiv = document.getElementById(`send-status-${appId}`);
        const menu = document.getElementById(`send-menu-${appId}`);
        
        // Close dropdown
        menu.classList.remove('active');
        
        // Show loading
        statusDiv.innerHTML = '⏳ Sending...';
        statusDiv.style.display = 'block';
        
        // Confirmation messages
        const confirmMessages = {
            'with_payment': 'Send full application link with payment to this vendor?',
            'no_payment': 'Send full application link WITHOUT payment (pre-paid)?',
            'payment_only': 'Send payment link only to this vendor?'
        };
        
        if (!confirm(confirmMessages[sendOption])) {
            statusDiv.innerHTML = '';
            statusDiv.style.display = 'none';
            return;
        }
        
        // Send AJAX request
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'oha_send_vendor_email',
                app_id: appId,
                option: sendOption, // FIXED: Was 'send_option', must be 'option' to match PHP handler
                nonce: '<?php echo wp_create_nonce('oha_send_vendor_email'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('AJAX Response:', data); // Debug logging
            if (data.success) {
                statusDiv.innerHTML = '✅ Email sent successfully!';
                statusDiv.style.background = '#d1fae5';
                statusDiv.style.color = '#065f46';
                setTimeout(() => {
                    statusDiv.style.display = 'none';
                }, 3000);
            } else {
                console.error('Email send failed:', data.data); // Debug logging
                statusDiv.innerHTML = '❌ Error: ' + (data.data || 'Unknown error');
                statusDiv.style.background = '#fee2e2';
                statusDiv.style.color = '#991b1b';
            }
        })
        .catch(error => {
            console.error('AJAX Error:', error); // Debug logging
            statusDiv.innerHTML = '❌ Network error';
            statusDiv.style.background = '#fee2e2';
            statusDiv.style.color = '#991b1b';
        });
    }
    
    // Confirm script loaded
    console.log('OHA Board Hub JavaScript loaded successfully');
    console.log('Total vendor cards:', document.querySelectorAll('.oha-vendor-card').length);
    </script>
    
    <?php
    return ob_get_clean();
});
