<?php
/**
 * Board Hub Admin Dashboard - CARD-BASED UI
 * Modern, clean card interface for vendor management
 * 
 * FIXED VERSION - v2.1.2
 * Fixes applied:
 * - Fixed AJAX parameter mismatch (send_option → option)
 * - Added ajaxurl definition for frontend
 * - Force style tag rendering
 * - Increased CSS specificity
 * - Added debug console logging
 * - Better error handling
 */

if (!defined('ABSPATH')) exit;

// Register vh_year as a WordPress query var so canonical redirect doesn't strip it.
add_filter('query_vars', function($vars) {
    $vars[] = 'vh_year';
    return $vars;
});

add_shortcode('oha_vendor_boardhub', function() {
    if (!current_user_can('manage_options')) {
        return '<p>Access denied. Admin privileges required.</p>';
    }

    // CSV export is now handled by init hook in actions.php

    // Collect all distinct years for the filter dropdown.
    global $wpdb;
    $year_rows = $wpdb->get_col(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
         WHERE meta_key = '_convention_year' AND meta_value != ''
         ORDER BY meta_value DESC"
    );

    // Preserve the active Board Hub tab (e.g. ?tab=vendors) across filter form submissions.
    $current_tab = sanitize_text_field(get_query_var('tab', $_GET['tab'] ?? ''));

    // Read year filter — use get_query_var (respects WP canonical) with $_GET fallback.
    // 'all' means show every year. Default to active year so current-year vendors show first.
    $active_year  = strval(get_option('oha_vhcpt_active_year', date('Y')));
    $raw_year     = get_query_var('vh_year', null);
    if ($raw_year === null) {
        $raw_year = isset($_GET['vh_year']) ? $_GET['vh_year'] : null;
    }
    if ($raw_year === null) {
        // No explicit selection — default to active year if year data exists, otherwise show all.
        $filter_year = !empty($year_rows) ? $active_year : '';
    } elseif ($raw_year === 'all') {
        $filter_year = '';
    } else {
        $filter_year = sanitize_text_field($raw_year);
    }

    // Get all vendor applications
    $args = [
        'post_type' => 'vendor_application',
        'posts_per_page' => -1,
        'post_status' => 'any',
        'orderby' => 'date',
        'order' => 'DESC'
    ];

    // Handle search and year filter — combined into meta_query if both present.
    $meta_clauses = [];

    if (!empty($_GET['s'])) {
        $search = sanitize_text_field($_GET['s']);
        $meta_clauses[] = [
            'relation' => 'OR',
            ['key' => 'company_name', 'value' => $search, 'compare' => 'LIKE'],
            ['key' => 'email', 'value' => $search, 'compare' => 'LIKE'],
            ['key' => 'first_name', 'value' => $search, 'compare' => 'LIKE'],
            ['key' => 'last_name', 'value' => $search, 'compare' => 'LIKE'],
        ];
    }

    if ($filter_year !== '') {
        $meta_clauses[] = ['key' => '_convention_year', 'value' => $filter_year, 'compare' => '='];
    }

    if (!empty($meta_clauses)) {
        $args['meta_query'] = count($meta_clauses) === 1
            ? $meta_clauses[0]
            : array_merge(['relation' => 'AND'], $meta_clauses);
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
    
    /* Modal Styles */
    .oha-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.6);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    
    .oha-vendor-modal {
        background: #fff;
        max-width: 900px;
        width: 95%;
        max-height: 90vh;
        overflow: auto;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0,0,0,.3);
    }
    
    .oha-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .oha-modal-header h2 {
        margin: 0;
        font-size: 24px;
        color: #1e293b;
    }
    
    .oha-modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #64748b;
        padding: 0;
        width: 32px;
        height: 32px;
        border-radius: 6px;
    }
    
    .oha-modal-close:hover {
        background: #f1f5f9;
    }
    
    .oha-modal-body {
        padding: 20px;
    }
    
    .oha-details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .oha-details-grid h3 {
        margin: 0 0 12px;
        font-size: 16px;
        color: #475569;
    }
    
    .oha-details-grid p {
        margin: 8px 0;
        font-size: 14px;
        color: #1e293b;
    }
    
    .oha-full-width {
        grid-column: 1 / -1;
    }
    
    .oha-btn-secondary {
        background: #64748b;
    }
    
    .oha-btn-secondary:hover {
        background: #475569;
    }

    /* === Bulk Email Panel === */
    .oha-bulk-email-wrap {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,.1);
        margin-bottom: 20px;
        overflow: hidden;
    }
    .oha-bulk-email-header {
        padding: 16px 20px;
        background: #1e40af;
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .oha-bulk-email-header h2 {
        margin: 0;
        font-size: 18px;
        color: #fff;
        font-weight: 700;
    }
    .oha-bulk-email-body { padding: 20px; }
    .oha-be-field { margin-bottom: 18px; }
    .oha-be-field > label {
        display: block;
        font-weight: 700;
        font-size: 12px;
        color: #374151;
        margin-bottom: 7px;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .oha-be-status-checks {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 20px;
        margin-bottom: 10px;
    }
    .oha-be-status-checks label {
        font-weight: 500;
        font-size: 14px;
        text-transform: none;
        letter-spacing: 0;
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        color: #1e293b;
    }
    .oha-be-recipient-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 13px;
        color: #475569;
    }
    .oha-be-count-badge {
        background: #dbeafe;
        color: #1e40af;
        font-weight: 700;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 13px;
        min-width: 28px;
        text-align: center;
        display: inline-block;
    }
    .oha-be-subject-input {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 15px;
        box-sizing: border-box;
    }
    .oha-be-subject-input:focus {
        border-color: #3b82f6;
        outline: none;
        box-shadow: 0 0 0 3px rgba(59,130,246,.15);
    }
    .oha-be-merge-hint {
        font-size: 12px;
        color: #64748b;
        margin-top: 6px;
    }
    .oha-be-merge-hint code {
        background: #f1f5f9;
        padding: 1px 5px;
        border-radius: 3px;
        font-family: monospace;
        font-size: 11px;
    }
    .oha-be-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 6px;
    }
    .oha-btn-bulk-send {
        background: #15803d;
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        padding: 11px 28px;
    }
    .oha-btn-bulk-send:hover { background: #166534; }
    .oha-btn-bulk-send:disabled { background: #86efac; cursor: not-allowed; }
    .oha-btn-be-cancel {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1 !important;
    }
    .oha-btn-be-cancel:hover { background: #e2e8f0; }
    .oha-be-progress {
        margin-top: 14px;
        padding: 13px 16px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        line-height: 1.5;
    }
    .oha-be-progress.sending { background:#eff6ff; border-left:4px solid #3b82f6; color:#1e40af; }
    .oha-be-progress.done    { background:#f0fdf4; border-left:4px solid #22c55e; color:#15803d; }
    .oha-be-progress.error   { background:#fef2f2; border-left:4px solid #ef4444; color:#991b1b; }
    .oha-btn-bulk-open {
        background: #1e40af;
        color: #fff;
        font-weight: 600;
    }
    .oha-btn-bulk-open:hover { background: #1d4ed8; }

    /* === Fast-Track Invite Panel (v3.3.0) === */
    .oha-fasttrack-wrap {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,.1);
        margin-bottom: 20px;
        overflow: hidden;
    }
    .oha-fasttrack-header {
        padding: 16px 20px;
        background: #065f46;
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .oha-fasttrack-header h2 {
        margin: 0;
        font-size: 18px;
        color: #fff;
        font-weight: 700;
    }
    .oha-fasttrack-body { padding: 20px; }
    .oha-ft-field { margin-bottom: 16px; }
    .oha-ft-field > label {
        display: block;
        font-weight: 700;
        font-size: 12px;
        color: #374151;
        margin-bottom: 7px;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .oha-ft-input {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 15px;
        box-sizing: border-box;
    }
    .oha-ft-input:focus {
        border-color: #059669;
        outline: none;
        box-shadow: 0 0 0 3px rgba(5,150,105,.15);
    }
    .oha-ft-hint {
        font-size: 12px;
        color: #64748b;
        margin-top: 5px;
    }
    .oha-btn-fasttrack-send {
        background: #059669;
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        padding: 11px 28px;
    }
    .oha-btn-fasttrack-send:hover { background: #047857; }
    .oha-btn-fasttrack-send:disabled { background: #6ee7b7; cursor: not-allowed; }
    .oha-ft-result {
        margin-top: 14px;
        padding: 13px 16px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
    }
    .oha-ft-result.success { background:#f0fdf4; border-left:4px solid #22c55e; color:#15803d; }
    .oha-ft-result.error   { background:#fef2f2; border-left:4px solid #ef4444; color:#991b1b; }
    .oha-btn-fasttrack-open {
        background: #065f46;
        color: #fff;
        font-weight: 600;
    }
    .oha-btn-fasttrack-open:hover { background: #047857; }

    /* === Admin Status Override (v3.5.0) === */
    .oha-status-override-wrap {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #e2e8f0;
    }
    .oha-status-override-row {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .oha-status-sel {
        flex: 1;
        padding: 7px 10px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 13px;
        background: #fff;
        color: #1e293b;
    }
    .oha-btn-override {
        background: #92400e;
        color: #fff;
        padding: 7px 14px;
        font-size: 13px;
        white-space: nowrap;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        font-weight: 600;
    }
    .oha-btn-override:hover { background: #78350f; }
    .oha-override-result {
        margin-top: 6px;
        font-size: 12px;
        font-weight: 600;
    }
    .oha-override-result.success { color: #065f46; }
    .oha-override-result.error   { color: #991b1b; }
    /* Override confirmation modal */
    .oha-override-modal-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.6);
        z-index: 99998;
        align-items: center;
        justify-content: center;
    }
    .oha-override-modal-backdrop.active { display: flex; }
    .oha-override-modal {
        background: #fff;
        max-width: 500px;
        width: 92%;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0,0,0,.3);
        overflow: hidden;
    }
    .oha-override-modal-header {
        background: #92400e;
        padding: 16px 20px;
    }
    .oha-override-modal-header h2 {
        margin: 0;
        font-size: 18px;
        color: #fff;
        font-weight: 700;
    }
    .oha-override-modal-body {
        padding: 20px;
        font-size: 15px;
        line-height: 1.6;
        color: #1e293b;
    }
    .oha-override-warning {
        background: #fef3c7;
        border: 1px solid #f59e0b;
        border-radius: 6px;
        padding: 12px 14px;
        margin-top: 12px;
        font-size: 13px;
        color: #78350f;
        line-height: 1.5;
    }
    .oha-override-modal-footer {
        padding: 14px 20px;
        background: #f8fafc;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    .oha-btn-override-confirm {
        background: #92400e;
        color: #fff;
        font-weight: 700;
        padding: 10px 22px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 14px;
    }
    .oha-btn-override-confirm:hover { background: #78350f; }
    .oha-btn-override-confirm:disabled { background: #d97706; cursor: not-allowed; }
    .oha-btn-override-cancel {
        background: #e2e8f0;
        color: #475569;
        padding: 10px 22px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
    }
    .oha-btn-override-cancel:hover { background: #cbd5e1; }

    <?php
    echo '</style>';
    ?>
    
    <div class="oha-boardhub">
        <!-- Header with Stats -->
        <div class="oha-boardhub-header">
            <div>
                <h1 class="oha-boardhub-title">🎃 OHA Vendor Board Hub</h1>
                <div class="oha-boardhub-stats">
                    <?php
                    $total = count($vendors);
                    $paid = 0;
                    $pending = 0;
                    $total_booths = 0;
                    
                    foreach ($vendors as $v) {
                        $status = get_post_meta($v->ID, '_oha_status', true);
                        if ($status === 'Paid') $paid++;
                        if ($status === 'Pending' || empty($status)) $pending++;
                        $booths = get_post_meta($v->ID, 'booths', true);
                        $total_booths += intval($booths);
                    }
                    ?>
                    <div class="oha-stat">
                        <div class="oha-stat-label">Total Vendors</div>
                        <div class="oha-stat-value" id="oha-stat-total"><?php echo $total; ?></div>
                    </div>
                    <div class="oha-stat">
                        <div class="oha-stat-label">Paid</div>
                        <div class="oha-stat-value" id="oha-stat-paid" style="color:#059669;"><?php echo $paid; ?></div>
                    </div>
                    <div class="oha-stat">
                        <div class="oha-stat-label">Pending</div>
                        <div class="oha-stat-value" id="oha-stat-pending" style="color:#dc2626;"><?php echo $pending; ?></div>
                    </div>
                    <div class="oha-stat">
                        <div class="oha-stat-label">Total Booths</div>
                        <div class="oha-stat-value" style="color:#7c3aed;"><?php echo $total_booths; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Controls -->
            <div class="oha-boardhub-controls">
                <form method="get" class="oha-search-box">
                    <span class="oha-search-icon">🔍</span>
                    <input type="text" name="s" placeholder="Search vendors..." value="<?php echo esc_attr($_GET['s'] ?? ''); ?>">
                    <?php if ($filter_year !== ''): ?>
                        <input type="hidden" name="vh_year" value="<?php echo esc_attr($filter_year); ?>">
                    <?php endif; ?>
                    <?php if ($current_tab !== ''): ?>
                        <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">
                    <?php endif; ?>
                </form>

                <?php if (!empty($year_rows)): ?>
                <form method="get" style="display:flex;align-items:center;gap:6px;">
                    <?php if (!empty($_GET['s'])): ?>
                        <input type="hidden" name="s" value="<?php echo esc_attr($_GET['s']); ?>">
                    <?php endif; ?>
                    <?php if ($current_tab !== ''): ?>
                        <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>">
                    <?php endif; ?>
                    <label for="vh_year_filter" style="font-size:13px;color:#475569;font-weight:600;">Year:</label>
                    <select id="vh_year_filter" name="vh_year" onchange="this.form.submit()" style="padding:8px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">
                        <option value="all" <?php selected($filter_year, ''); ?>>All Years</option>
                        <?php foreach ($year_rows as $yr): ?>
                            <option value="<?php echo esc_attr($yr); ?>" <?php selected($filter_year, $yr); ?>><?php echo esc_html($yr); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endif; ?>
                
                <form method="post">
                    <?php wp_nonce_field('oha_export_vendors', 'oha_export_nonce'); ?>
                    <button type="submit" name="oha_export_csv" class="oha-btn oha-btn-primary">
                        📊 Export CSV
                    </button>
                </form>

                <button type="button" class="oha-btn oha-btn-bulk-open" id="oha-be-toggle-btn" onclick="ohaToggleBulkEmail()">
                    ✉️ Bulk Email Vendors
                </button>

                <button type="button" class="oha-btn oha-btn-fasttrack-open" id="oha-ft-toggle-btn" onclick="ohaToggleFastTrack()">
                    ⚡ Fast-Track Invite
                </button>
            </div>
        </div>

        <!-- Bulk Email Panel (v3.2.0) -->
        <div id="oha-bulk-email-panel" class="oha-bulk-email-wrap" style="display:none;">
            <div class="oha-bulk-email-header">
                <h2>✉️ Bulk Email Vendors</h2>
                <button type="button" onclick="ohaToggleBulkEmail()" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;padding:0;line-height:1;">✕</button>
            </div>
            <div class="oha-bulk-email-body">

                <!-- Status targeting -->
                <div class="oha-be-field">
                    <label>Send to vendors with these statuses</label>
                    <div class="oha-be-status-checks">
                        <?php
                        $be_statuses = ['Submitted','Approved','Agreement Sent','Agreement Signed','Paid'];
                        foreach ($be_statuses as $be_s):
                        ?>
                        <label>
                            <input type="checkbox" class="oha-be-status-cb" value="<?php echo esc_attr($be_s); ?>"
                                <?php echo in_array($be_s, ['Approved','Agreement Sent','Agreement Signed'], true) ? 'checked' : ''; ?>>
                            <?php echo esc_html($be_s); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="oha-be-recipient-bar">
                        <button type="button" class="oha-btn oha-btn-primary" onclick="ohaBeCountRecipients()" style="padding:6px 14px;font-size:13px;">Count Recipients</button>
                        <span>Will send to <span class="oha-be-count-badge" id="oha-be-count">—</span> vendors</span>
                    </div>
                </div>

                <!-- Subject line -->
                <div class="oha-be-field">
                    <label>Subject Line</label>
                    <input type="text" id="oha-be-subject" class="oha-be-subject-input" placeholder="e.g. Important update about <?php echo esc_attr(get_option('oha_vhcpt_convention_name', 'OHA Convention ' . get_option('oha_vhcpt_active_year', date('Y')))); ?>" />
                </div>

                <!-- Body (TinyMCE rich editor) -->
                <div class="oha-be-field">
                    <label>Email Message</label>
                    <?php
                    wp_editor('', 'oha_be_body', [
                        'textarea_name' => 'oha_be_body',
                        'textarea_rows' => 14,
                        'media_buttons' => false,
                        'teeny'         => false,
                        'quicktags'     => true,
                        'tinymce'       => [
                            'toolbar1' => 'bold,italic,underline,link,bullist,numlist,blockquote,hr,forecolor,removeformat',
                            'toolbar2' => '',
                        ],
                    ]);
                    ?>
                    <p class="oha-be-merge-hint">
                        Merge tags: <code>{{first_name}}</code> <code>{{last_name}}</code> <code>{{display_name}}</code> <code>{{email}}</code> <code>{{site_name}}</code> <code>{{unsubscribe_url}}</code>
                    </p>
                </div>

                <!-- Actions -->
                <div class="oha-be-actions">
                    <button type="button" id="oha-be-send-btn" class="oha-btn oha-btn-bulk-send" onclick="ohaBeQueueSend()">
                        🚀 Queue &amp; Send
                    </button>
                    <button type="button" class="oha-btn oha-btn-be-cancel" onclick="ohaToggleBulkEmail()">
                        Cancel
                    </button>
                    <span style="font-size:13px;color:#64748b;margin-left:4px;">Emails send in the background — this page will not freeze.</span>
                </div>

                <!-- Progress indicator -->
                <div id="oha-be-progress" class="oha-be-progress" style="display:none;"></div>

            </div>
        </div>
        
        <!-- Fast-Track Invite Panel (v3.3.0) -->
        <div id="oha-fasttrack-panel" class="oha-fasttrack-wrap" style="display:none;">
            <div class="oha-fasttrack-header">
                <h2>⚡ Fast-Track Vendor Invite</h2>
                <button type="button" onclick="ohaToggleFastTrack()" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;padding:0;line-height:1;">✕</button>
            </div>
            <div class="oha-fasttrack-body">
                <p style="color:#475569;margin-top:0;">Send a professional invitation email with a direct link to the vendor application form. Use this to fast-track new vendors you want to bring on board.</p>

                <div class="oha-ft-field">
                    <label for="oha-ft-name">Vendor Name <span style="font-weight:400;text-transform:none;font-size:11px;">(optional — personalizes the greeting)</span></label>
                    <input type="text" id="oha-ft-name" class="oha-ft-input" placeholder="e.g. Jane Smith or Haunted Emporium" />
                </div>

                <div class="oha-ft-field">
                    <label for="oha-ft-email">Email Address <span style="color:#dc2626;">*</span></label>
                    <input type="email" id="oha-ft-email" class="oha-ft-input" placeholder="vendor@example.com" />
                    <p class="oha-ft-hint">A professional invitation with event details and the application link will be sent to this address.</p>
                </div>

                <div class="oha-be-actions">
                    <button type="button" id="oha-ft-send-btn" class="oha-btn oha-btn-fasttrack-send" onclick="ohaFtSendInvite()">
                        ✉️ Send Invitation
                    </button>
                    <button type="button" class="oha-btn oha-btn-be-cancel" onclick="ohaToggleFastTrack()">
                        Cancel
                    </button>
                </div>

                <div id="oha-ft-result" class="oha-ft-result" style="display:none;"></div>
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
                    $conv_year  = get_post_meta($app_id, '_convention_year', true);
                    $perm_id    = get_post_meta($app_id, '_vendor_permanent_id', true) ?: $app_id;
                    
                    // Booth requirements
                    $tables_needed = get_post_meta($app_id, 'tables_needed', true);
                    $need_electric = get_post_meta($app_id, 'need_electric', true);
                    $need_air      = get_post_meta($app_id, 'need_air', true);
                    $need_table    = get_post_meta($app_id, 'need_table', true);
                    
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
                                <div class="oha-card-id">
                                    <div>VID-<?php echo esc_html($perm_id); ?></div>
                                    <?php if ($conv_year): ?>
                                        <div style="font-size:11px;color:#94a3b8;font-weight:500;"><?php echo esc_html($conv_year); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="oha-card-company"><?php echo esc_html($company); ?></div>
                                    <div class="oha-card-contact"><?php echo esc_html($first . ' ' . $last); ?> • <?php echo esc_html($email); ?></div>
                                </div>
                            </div>
                            
                            <div class="oha-card-actions">
                                <span class="oha-card-status status-<?php echo $status_class; ?>" id="oha-status-badge-<?php echo $app_id; ?>">
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
                                        <span class="oha-info-label">Tables:</span>
                                        <span class="oha-info-value"><?php echo $tables_needed !== '' ? esc_html($tables_needed) : '—'; ?></span>
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
                                    
                                    <!-- Send Dropdown -->
                                    <div class="oha-send-dropdown-wrapper">
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
                                    // Manual Send Agreement button (for vendors who already have agreement)
                                    $agreement_url = get_post_meta($app_id, '_agreement_url', true);
                                    if (empty($agreement_url)) {
                                        $agreement_url = get_post_meta($app_id, '_pdf_url', true);
                                    }
                                    if (empty($agreement_url)) {
                                        $agreement_url = get_post_meta($app_id, '_agreement_file', true);
                                    }
                                    
                                    if (!empty($agreement_url) && ($status === 'Paid' || $status === 'Agreement Signed')): 
                                    ?>
                                    <button type="button" class="oha-btn oha-btn-secondary oha-btn-small" onclick="manualSendAgreement(<?php echo $app_id; ?>)" style="margin-top:10px;width:100%;">
                                        📧 Resend Agreement Email
                                    </button>
                                    <div class="oha-send-status" id="manual-send-status-<?php echo $app_id; ?>"></div>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Download Agreement Button - Check ALL meta key variations
                                    $agreement_url = get_post_meta($app_id, '_agreement_url', true);
                                    if (empty($agreement_url)) {
                                        $agreement_url = get_post_meta($app_id, '_pdf_url', true);
                                    }
                                    if (empty($agreement_url)) {
                                        $pdf_path = get_post_meta($app_id, '_pdf_path', true);
                                        if ($pdf_path && file_exists($pdf_path)) {
                                            $agreement_url = str_replace(ABSPATH, home_url('/'), $pdf_path);
                                        }
                                    }
                                    if (empty($agreement_url)) {
                                        $agreement_file = get_post_meta($app_id, '_agreement_file', true);
                                        if ($agreement_file && file_exists($agreement_file)) {
                                            $agreement_url = str_replace(ABSPATH, home_url('/'), $agreement_file);
                                        }
                                    }
                                    
                                    if (!empty($agreement_url)): 
                                    ?>
                                    <a href="<?php echo esc_url($agreement_url); ?>" class="oha-btn oha-btn-primary oha-btn-small" target="_blank" style="margin-top:10px;display:block;text-align:center;">
                                        📄 Download Agreement
                                    </a>
                                    <?php endif; ?>
                                    
                                    <!-- View All Details Button -->
                                    <button type="button" class="oha-btn oha-btn-secondary oha-btn-small" onclick="viewVendorDetails(<?php echo $app_id; ?>)" style="margin-top:10px;width:100%;">
                                        🔍 View All Details
                                    </button>

                                    <!-- Admin Status Override (v3.5.0) -->
                                    <div class="oha-status-override-wrap">
                                        <div class="oha-card-section-title" style="margin-bottom:8px;">🔧 Override Status</div>
                                        <div class="oha-status-override-row">
                                            <select id="oha-status-sel-<?php echo $app_id; ?>" class="oha-status-sel"
                                                    data-app-id="<?php echo $app_id; ?>"
                                                    data-current-status="<?php echo esc_attr($status); ?>">
                                                <option value="">— Select new status —</option>
                                                <?php foreach (['Submitted','Approved','Agreement Sent','Agreement Signed','Paid'] as $opt): ?>
                                                <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="button" class="oha-btn-override"
                                                    onclick="event.stopPropagation();ohaShowStatusOverrideConfirm(<?php echo $app_id; ?>)">
                                                Change
                                            </button>
                                        </div>
                                        <div id="oha-override-result-<?php echo $app_id; ?>" class="oha-override-result" style="display:none;"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- History -->
                            <?php 
                            $history = get_post_meta($app_id, '_oha_history', true);
                            if ($history && is_array($history) && !empty($history)): 
                            ?>
                            <div class="oha-card-section" style="margin-top:20px;">
                                <div class="oha-card-section-title">📜 History</div>
                                <?php foreach (array_reverse($history) as $entry): ?>
                                <div class="oha-history-item">
                                    <div class="oha-history-date">
                                        <?php echo date('M j, Y g:i A', $entry['timestamp']); ?>
                                    </div>
                                    <div class="oha-history-action">
                                        <?php 
                                        switch ($entry['type']) {
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
                                            case 'admin_status_override':
                                                $from_s = $entry['status_from'] ?? '?';
                                                $to_s   = $entry['status_to']   ?? '?';
                                                echo '🔧 Admin overrode status: ' . esc_html($from_s) . ' → ' . esc_html($to_s);
                                                break;
                                            default:
                                                echo '• ' . esc_html($entry['type']);
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

    <!-- Admin Status Override Confirmation Modal (v3.5.0) — position:fixed, placement in DOM doesn't matter -->
    <div id="oha-override-modal-backdrop" class="oha-override-modal-backdrop"
         onclick="if(event.target===this)ohaHideStatusOverrideModal()">
        <div class="oha-override-modal">
            <div class="oha-override-modal-header">
                <h2>⚠️ Change Vendor Status</h2>
            </div>
            <div id="oha-override-modal-body" class="oha-override-modal-body"></div>
            <div class="oha-override-modal-footer">
                <button type="button" class="oha-btn-override-cancel" onclick="ohaHideStatusOverrideModal()">Cancel</button>
                <button type="button" id="oha-override-confirm-btn" class="oha-btn-override-confirm">Yes, Change Status</button>
            </div>
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

    // Manual Send Agreement Email
    function manualSendAgreement(appId) {
        const statusDiv = document.getElementById(`manual-send-status-${appId}`);
        
        if (!confirm('Resend agreement email with download link to this vendor?')) {
            return;
        }
        
        if (statusDiv) {
            statusDiv.innerHTML = '⏳ Sending...';
            statusDiv.style.display = 'block';
        }
        
        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'oha_manual_send_agreement',
                app_id: appId,
                nonce: '<?php echo wp_create_nonce("oha_manual_send_agreement"); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (statusDiv) {
                if (data.success) {
                    statusDiv.innerHTML = '✅ Agreement email sent!';
                    statusDiv.style.background = '#d1fae5';
                    statusDiv.style.color = '#065f46';
                    setTimeout(() => statusDiv.style.display = 'none', 3000);
                } else {
                    statusDiv.innerHTML = '❌ Error: ' + (data.data || 'Unknown error');
                    statusDiv.style.background = '#fee2e2';
                    statusDiv.style.color = '#991b1b';
                }
            }
        })
        .catch(error => {
            console.error('Manual send error:', error);
            if (statusDiv) {
                statusDiv.innerHTML = '❌ Network error';
                statusDiv.style.background = '#fee2e2';
                statusDiv.style.color = '#991b1b';
            }
        });
    }
    
    // HTML escaping helper — prevents stored XSS when inserting user-supplied
    // vendor data (company name, contact fields, etc.) into innerHTML.
    function escHtml(str) {
        var s = String(str == null ? '' : str);
        return s.replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
    }

    // View Vendor Details Modal
    function viewVendorDetails(appId) {
        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'oha_get_vendor_details',
                app_id: appId,
                nonce: '<?php echo wp_create_nonce("oha_get_vendor_details"); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showVendorDetailsModal(data.data);
            } else {
                alert('Error: ' + (data.data || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error');
        });
    }
    
    function showVendorDetailsModal(v) {
        // All user-supplied string values are passed through escHtml() before
        // being inserted into innerHTML to prevent stored XSS.
        var websiteDisplay = v.website ? escHtml(v.website) : 'N/A';
        // Sanitize email for href — strip anything that isn't a safe email char
        var safeEmail = escHtml(v.email || '');
        var safeEmailHref = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/.test(v.email || '')
            ? escHtml(v.email)
            : '';

        var historyHtml = '';
        if (v.history && v.history.length) {
            historyHtml = '<div class="oha-full-width"><h3>\uD83D\uDCDC History</h3>' +
                v.history.map(function(e) {
                    return '<div class="oha-history-item"><small>' + escHtml(e.date) + '</small><br>' + escHtml(e.description) + '</div>';
                }).join('') +
            '</div>';
        }

        var signedHtml = v.signature_date
            ? '<p><strong>Signed:</strong> ' + escHtml(v.signature_date) + '</p>'
            : '';

        var html = '<div class="oha-modal-backdrop" onclick="if(event.target===this)this.remove()">'
            + '<div class="oha-vendor-modal" onclick="event.stopPropagation()">'
            + '<div class="oha-modal-header">'
            + '<h2>' + escHtml(v.company_name) + '</h2>'
            + '<button onclick="this.closest(\'.oha-modal-backdrop\').remove()" class="oha-modal-close">\u2715</button>'
            + '</div>'
            + '<div class="oha-modal-body">'
            + '<div class="oha-details-grid">'
            + '<div><h3>\uD83D\uDCDE Contact</h3>'
            + '<p><strong>Name:</strong> ' + escHtml(v.first_name) + ' ' + escHtml(v.last_name) + '</p>'
            + (safeEmailHref
                ? '<p><strong>Email:</strong> <a href="mailto:' + safeEmailHref + '">' + safeEmail + '</a></p>'
                : '<p><strong>Email:</strong> ' + safeEmail + '</p>')
            + '<p><strong>Phone:</strong> ' + escHtml(v.phone) + '</p>'
            + '<p><strong>Website:</strong> ' + websiteDisplay + '</p>'
            + '</div>'
            + '<div><h3>\uD83D\uDCE6 Products</h3><p>' + escHtml(v.product_type) + '</p></div>'
            + (v.vendor_private_notes ? '<div class="oha-full-width" style="background:#fffbe6;border:1px solid #e6c200;border-left:4px solid #e6c200;border-radius:4px;padding:0.75em 1em;margin-top:0.5em"><h3 style="margin:0 0 0.4em;font-size:0.9em;text-transform:uppercase;letter-spacing:0.05em;color:#7a6000">Note from Vendor (Board Only)</h3><p style="margin:0;white-space:pre-wrap">' + escHtml(v.vendor_private_notes) + '</p></div>' : '')
            + '<div><h3>\uD83C\uDFEA Booth</h3>'
            + '<p><strong>Booths:</strong> ' + escHtml(v.booths) + '</p>'
            + '<p><strong>Tables Needed:</strong> ' + (v.tables_needed !== undefined && v.tables_needed !== '' ? escHtml(String(v.tables_needed)) : '&mdash;') + '</p>'
            + '<p><strong>Returning Vendor:</strong> ' + (v.returning_vendor ? 'Yes' : 'No') + '</p>'
            + '<p><strong>Electric:</strong> ' + (v.need_electric ? '\u2705' : '\u274C') + '</p>'
            + '<p><strong>Air:</strong> ' + (v.need_air ? '\u2705' : '\u274C') + '</p>'
            + '<p><strong>Table:</strong> ' + (v.need_table ? '\u2705' : '\u274C') + '</p>'
            + (v.booth_preference ? '<p><strong>Booth Preference:</strong> ' + escHtml(v.booth_preference) + '</p>' : '')
            + '</div>'
            + '<div><h3>\uD83D\uDCC4 Status</h3>'
            + '<p><strong>Status:</strong> ' + escHtml(v.status) + '</p>'
            + '<p><strong>Submitted:</strong> ' + escHtml(v.date) + '</p>'
            + (v.convention_year ? '<p><strong>Convention Year:</strong> ' + escHtml(v.convention_year) + '</p>' : '')
            + '<p><strong>Vendor ID:</strong> VID-' + escHtml(String(v.vendor_permanent_id)) + '</p>'
            + signedHtml
            + '</div>'
            + historyHtml
            + '</div>'
            + '</div>'
            + '</div>'
            + '</div>';

        document.body.insertAdjacentHTML('beforeend', html);
    }
    console.log('OHA Board Hub JavaScript loaded successfully');
    console.log('Total vendor cards:', document.querySelectorAll('.oha-vendor-card').length);
    </script>

    <script type="text/javascript">
    /* Bulk Email Vendors — OHA Vendor Hub v3.2.0 */
    (function() {
        var ajaxUrl  = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        var beNonce  = '<?php echo wp_create_nonce('oha_vh_bulk_email'); ?>';
        var pollTimer = null;

        // Expose functions to global scope for onclick attributes
        window.ohaToggleBulkEmail = function() {
            var panel = document.getElementById('oha-bulk-email-panel');
            var btn   = document.getElementById('oha-be-toggle-btn');
            if (!panel) return;
            var isOpen = panel.style.display !== 'none';
            panel.style.display = isOpen ? 'none' : 'block';
            if (btn) btn.textContent = isOpen ? '\u2709\uFE0F Bulk Email Vendors' : '\u2715 Close Bulk Email';
            if (!isOpen) {
                // Scroll panel into view
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        };

        window.ohaBeCountRecipients = function() {
            var statuses = ohaBeGetSelectedStatuses();
            if (!statuses.length) {
                document.getElementById('oha-be-count').textContent = '0';
                return;
            }
            var countEl = document.getElementById('oha-be-count');
            countEl.textContent = '...';

            var body = new URLSearchParams({ action: 'oha_vh_bulk_email_count', nonce: beNonce });
            statuses.forEach(function(s) { body.append('vendor_statuses[]', s); });

            fetch(ajaxUrl, { method: 'POST', body: body })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    countEl.textContent = res.success ? res.data.count : '?';
                })
                .catch(function() { countEl.textContent = '?'; });
        };

        window.ohaBeQueueSend = function() {
            var statuses    = ohaBeGetSelectedStatuses();
            var subject     = (document.getElementById('oha-be-subject') || {}).value || '';
            var htmlContent = ohaBeGetEditorContent();
            var progressEl  = document.getElementById('oha-be-progress');
            var sendBtn     = document.getElementById('oha-be-send-btn');

            if (!statuses.length) { alert('Please select at least one vendor status.'); return; }
            if (!subject.trim())  { alert('Please enter a subject line.'); return; }
            if (!htmlContent.trim()) { alert('Please write your email message.'); return; }

            if (!confirm('Send this email to all vendors with status: ' + statuses.join(', ') + '?\n\nThis cannot be undone.')) {
                return;
            }

            sendBtn.disabled = true;
            sendBtn.textContent = 'Queueing...';
            ohaBeShowProgress('sending', 'Queueing recipients...');

            var body = new URLSearchParams({
                action:       'oha_vh_bulk_email_vendors',
                nonce:        beNonce,
                subject:      subject,
                html_content: htmlContent,
            });
            statuses.forEach(function(s) { body.append('vendor_statuses[]', s); });

            fetch(ajaxUrl, { method: 'POST', body: body })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    sendBtn.disabled  = false;
                    sendBtn.textContent = '\uD83D\uDE80 Queue & Send';
                    if (res.success) {
                        var d = res.data;
                        ohaBeShowProgress('sending',
                            '\u2705 ' + d.queued + ' emails queued (batch size: ' + d.batch_size + '). ' +
                            'Sending in the background\u2014you can close this panel. ' +
                            '<br><small>Campaign ID: ' + d.campaign_id + ' \u2014 Polling for progress...</small>'
                        );
                        ohaBeStartPolling(d.campaign_id);
                    } else {
                        ohaBeShowProgress('error', '\u274C ' + (res.data || 'Unknown error'));
                    }
                })
                .catch(function(err) {
                    sendBtn.disabled = false;
                    sendBtn.textContent = '\uD83D\uDE80 Queue & Send';
                    ohaBeShowProgress('error', '\u274C Network error. Please try again.');
                });
        };

        function ohaBeGetSelectedStatuses() {
            var cbs = document.querySelectorAll('.oha-be-status-cb:checked');
            return Array.from(cbs).map(function(cb) { return cb.value; });
        }

        function ohaBeGetEditorContent() {
            // TinyMCE active instance takes priority; fall back to raw textarea value
            if (typeof tinymce !== 'undefined') {
                var ed = tinymce.get('oha_be_body');
                if (ed) return ed.getContent();
            }
            var ta = document.getElementById('oha_be_body');
            return ta ? ta.value : '';
        }

        function ohaBeShowProgress(type, msg) {
            var el = document.getElementById('oha-be-progress');
            if (!el) return;
            el.style.display = 'block';
            el.className     = 'oha-be-progress ' + type;
            el.innerHTML     = msg;
        }

        function ohaBeStartPolling(campaignId) {
            if (pollTimer) clearInterval(pollTimer);
            pollTimer = setInterval(function() {
                var body = new URLSearchParams({
                    action:      'oha_vh_bulk_email_status',
                    nonce:       beNonce,
                    campaign_id: campaignId,
                });
                fetch(ajaxUrl, { method: 'POST', body: body })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (!res.success) return;
                        var d = res.data;
                        if (d.status === 'sent') {
                            clearInterval(pollTimer);
                            ohaBeShowProgress('done',
                                '\u2705 Done! ' + d.sent_count + ' sent' +
                                (d.fail_count > 0 ? ', ' + d.fail_count + ' failed' : '') +
                                '. View full report in WP Admin \u2192 JEmail.'
                            );
                        } else if (d.status === 'sending') {
                            ohaBeShowProgress('sending',
                                '\u23F3 Sending\u2026 ' + d.sent_count + ' of ' + d.total_count + ' sent, ' +
                                d.queued + ' queued' +
                                (d.fail_count > 0 ? ', ' + d.fail_count + ' failed' : '') +
                                '.<br><small>Batches process automatically. You can navigate away.</small>'
                            );
                        }
                    });
            }, 8000);
        }

        // Auto-count on status checkbox change
        document.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('oha-be-status-cb')) {
                ohaBeCountRecipients();
            }
        });

    })();
    </script>

    <script type="text/javascript">
    /* Fast-Track Vendor Invite — OHA Vendor Hub v3.3.0 */
    (function() {
        var ftNonce = '<?php echo wp_create_nonce('oha_vh_fasttrack_invite'); ?>';

        window.ohaToggleFastTrack = function() {
            var panel = document.getElementById('oha-fasttrack-panel');
            var btn   = document.getElementById('oha-ft-toggle-btn');
            if (!panel) return;
            var isOpen = panel.style.display !== 'none';
            panel.style.display = isOpen ? 'none' : 'block';
            if (btn) btn.textContent = isOpen ? '\u26A1 Fast-Track Invite' : '\u2715 Close Invite';
            if (!isOpen) {
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        };

        window.ohaFtSendInvite = function() {
            var emailInput = document.getElementById('oha-ft-email');
            var nameInput  = document.getElementById('oha-ft-name');
            var resultDiv  = document.getElementById('oha-ft-result');
            var sendBtn    = document.getElementById('oha-ft-send-btn');

            var email = emailInput ? emailInput.value.trim() : '';
            var name  = nameInput  ? nameInput.value.trim()  : '';

            if (!email) { alert('Please enter an email address.'); return; }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }

            if (!confirm('Send a fast-track vendor invitation to ' + email + '?')) return;

            sendBtn.disabled    = true;
            sendBtn.textContent = 'Sending\u2026';
            if (resultDiv) resultDiv.style.display = 'none';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action:       'oha_vh_fasttrack_invite',
                    nonce:        ftNonce,
                    invite_email: email,
                    invite_name:  name,
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                sendBtn.disabled    = false;
                sendBtn.textContent = '\u2709\uFE0F Send Invitation';
                if (resultDiv) {
                    resultDiv.style.display = 'block';
                    if (res.success) {
                        resultDiv.className = 'oha-ft-result success';
                        resultDiv.innerHTML = '\u2705 ' + (res.data || 'Invitation sent successfully!');
                        if (emailInput) emailInput.value = '';
                        if (nameInput)  nameInput.value  = '';
                    } else {
                        resultDiv.className = 'oha-ft-result error';
                        resultDiv.innerHTML = '\u274C ' + (res.data || 'Failed to send invitation.');
                    }
                }
            })
            .catch(function() {
                sendBtn.disabled    = false;
                sendBtn.textContent = '\u2709\uFE0F Send Invitation';
                if (resultDiv) {
                    resultDiv.style.display = 'block';
                    resultDiv.className = 'oha-ft-result error';
                    resultDiv.innerHTML = '\u274C Network error. Please try again.';
                }
            });
        };
    })();
    </script>

    <script type="text/javascript">
    /* Admin Status Override — OHA Vendor Hub v3.5.0 */
    (function() {
        var overrideNonce = '<?php echo wp_create_nonce('oha_vh_admin_override'); ?>';
        var pendingAppId  = 0;
        var pendingStatus = '';

        window.ohaShowStatusOverrideConfirm = function(appId) {
            var sel = document.getElementById('oha-status-sel-' + appId);
            if (!sel || !sel.value) {
                alert('Please select a new status from the dropdown first.');
                return;
            }
            var newStatus   = sel.value;
            var curStatus   = sel.getAttribute('data-current-status') || 'Submitted';
            var companyEl   = document.querySelector('[data-vendor-id="' + appId + '"] .oha-card-company');
            var companyName = companyEl ? companyEl.textContent.trim() : 'Vendor #' + appId;

            if (newStatus === curStatus) {
                alert('"' + companyName + '" is already set to "' + curStatus + '". Please choose a different status.');
                return;
            }

            pendingAppId  = appId;
            pendingStatus = newStatus;

            // Build warning text based on direction of change
            var warningHtml = '';
            if (curStatus === 'Paid' && newStatus !== 'Paid') {
                warningHtml = '<div class="oha-override-warning">'
                    + '<strong>Reverting from Paid:</strong> Stripe payment markers and pre-paid flags will be cleared so this vendor can sign the agreement and pay through the normal flow again. '
                    + 'The existing agreement file and signature date are kept as a historical record.'
                    + '</div>';
            } else if (newStatus === 'Paid') {
                warningHtml = '<div class="oha-override-warning">'
                    + '<strong>Marking as Paid without Stripe:</strong> Only do this if payment has been confirmed through another method. '
                    + 'This will make the vendor appear in the public vendor card grid.'
                    + '</div>';
            }

            var bodyEl = document.getElementById('oha-override-modal-body');
            if (!bodyEl) return;
            bodyEl.innerHTML = '<p>You are about to change <strong>' + escHtml(companyName) + '</strong>\'s status:</p>'
                + '<p style="margin:14px 0;">'
                + '<span style="background:#fee2e2;color:#991b1b;padding:5px 12px;border-radius:4px;font-weight:600;">' + escHtml(curStatus) + '</span>'
                + '<span style="color:#94a3b8;margin:0 10px;font-size:18px;">→</span>'
                + '<span style="background:#dbeafe;color:#1e40af;padding:5px 12px;border-radius:4px;font-weight:600;">' + escHtml(newStatus) + '</span>'
                + '</p>'
                + warningHtml;

            var confirmBtn = document.getElementById('oha-override-confirm-btn');
            if (confirmBtn) {
                confirmBtn.disabled    = false;
                confirmBtn.textContent = 'Yes, Change Status';
                confirmBtn.onclick     = ohaDoStatusOverride;
            }

            var backdrop = document.getElementById('oha-override-modal-backdrop');
            if (backdrop) backdrop.classList.add('active');
        };

        window.ohaHideStatusOverrideModal = function() {
            var backdrop = document.getElementById('oha-override-modal-backdrop');
            if (backdrop) backdrop.classList.remove('active');
            pendingAppId  = 0;
            pendingStatus = '';
        };

        window.ohaDoStatusOverride = function() {
            if (!pendingAppId || !pendingStatus) return;

            // Capture locals before modal reset clears the pending vars
            var appId     = pendingAppId;
            var newStatus = pendingStatus;

            var confirmBtn = document.getElementById('oha-override-confirm-btn');
            if (confirmBtn) {
                confirmBtn.disabled    = true;
                confirmBtn.textContent = 'Updating\u2026';
            }

            fetch(ajaxurl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action:     'oha_vh_admin_override_status',
                    nonce:      overrideNonce,
                    app_id:     appId,
                    new_status: newStatus,
                }),
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (confirmBtn) {
                    confirmBtn.disabled    = false;
                    confirmBtn.textContent = 'Yes, Change Status';
                }
                ohaHideStatusOverrideModal();

                if (res.success) {
                    var d = res.data;

                    // Update the status badge on the card header
                    var badge = document.getElementById('oha-status-badge-' + d.app_id);
                    if (badge) {
                        // Strip old status- classes
                        var oldClasses = badge.className.split(' ').filter(function(c) { return !c.startsWith('status-'); });
                        var newClass   = 'status-' + d.new_status.toLowerCase().replace(/\s+/g, '-');
                        badge.className = oldClasses.concat([newClass]).join(' ');
                        badge.textContent = d.new_status;
                    }

                    // Update the select's data-current-status so subsequent changes are correct
                    var sel = document.getElementById('oha-status-sel-' + d.app_id);
                    if (sel) {
                        sel.setAttribute('data-current-status', d.new_status);
                        sel.value = ''; // reset to placeholder
                    }

                    // Update stat counters in the header bar
                    var paidEl    = document.getElementById('oha-stat-paid');
                    var pendingEl = document.getElementById('oha-stat-pending');
                    var totalEl   = document.getElementById('oha-stat-total');
                    if (paidEl)    paidEl.textContent    = d.paid_count;
                    if (pendingEl) pendingEl.textContent  = d.pending_count;
                    if (totalEl)   totalEl.textContent    = d.total_count;

                    // Show inline success result under the dropdown
                    var resultEl = document.getElementById('oha-override-result-' + d.app_id);
                    if (resultEl) {
                        resultEl.className     = 'oha-override-result success';
                        resultEl.textContent   = '\u2705 Status changed to ' + d.new_status;
                        resultEl.style.display = 'block';
                        setTimeout(function() { resultEl.style.display = 'none'; }, 6000);
                    }
                } else {
                    var resultEl = document.getElementById('oha-override-result-' + appId);
                    if (resultEl) {
                        resultEl.className     = 'oha-override-result error';
                        resultEl.textContent   = '\u274C Error: ' + (res.data || 'Unknown error');
                        resultEl.style.display = 'block';
                    }
                }
            })
            .catch(function() {
                if (confirmBtn) {
                    confirmBtn.disabled    = false;
                    confirmBtn.textContent = 'Yes, Change Status';
                }
                ohaHideStatusOverrideModal();
                var resultEl = document.getElementById('oha-override-result-' + appId);
                if (resultEl) {
                    resultEl.className     = 'oha-override-result error';
                    resultEl.textContent   = '\u274C Network error. Please try again.';
                    resultEl.style.display = 'block';
                }
            });
        };

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') ohaHideStatusOverrideModal();
        });

    })();
    </script>

    <?php
    return ob_get_clean();
});
