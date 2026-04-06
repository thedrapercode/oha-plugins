<?php
/**
 * Guide Tab - Board Member How-To Guide
 * Content is editable in WP Admin -> Volunteer Hub -> Settings -> Guide tab.
 */

if (!defined('ABSPATH')) { exit; }

$settings     = get_option('oha_vh_settings', []);
$guide_html   = !empty($settings['board_guide_content'])
                ? $settings['board_guide_content']
                : \OHA\VH\Settings::get_default_guide_html();
?>

<div class="oha-vh-guide-tab">
    <div class="tab-header">
        <h3>Board Member Guide</h3>
        <p>Step-by-step instructions for managing volunteers. To edit this content, go to
           <strong>WP Admin &rsaquo; Volunteer Hub &rsaquo; Settings &rsaquo; Guide</strong>.</p>
    </div>

    <div class="guide-body">
        <?php echo wp_kses_post($guide_html); ?>
    </div>
</div>

<style>
.oha-vh-guide-tab .guide-body {
    max-width: 820px;
    line-height: 1.75;
    font-size: 15px;
}

.oha-vh-guide-tab .guide-body h2 {
    color: #c0392b !important;
    font-size: 20px;
    border-bottom: 2px solid #e74c3c;
    padding-bottom: 6px;
    margin-top: 40px !important;
}

.oha-vh-guide-tab .guide-body h2:first-child {
    margin-top: 10px !important;
}

.oha-vh-guide-tab .guide-body h3 {
    color: #2271b1 !important;
    font-size: 16px;
    margin-top: 28px !important;
}

.oha-vh-guide-tab .guide-body p {
    color: #333 !important;
    margin-bottom: 12px;
}

.oha-vh-guide-tab .guide-body ul,
.oha-vh-guide-tab .guide-body ol {
    padding-left: 22px;
    margin-bottom: 14px;
    color: #333 !important;
}

.oha-vh-guide-tab .guide-body li {
    margin-bottom: 6px;
    color: #333 !important;
}

.oha-vh-guide-tab .guide-body .step-card {
    background: #f9f9f9;
    border-left: 5px solid #e74c3c;
    padding: 16px 20px;
    margin: 16px 0;
    border-radius: 0 6px 6px 0;
}

.oha-vh-guide-tab .guide-body .email-note {
    background: #e8f4fd;
    border: 1px solid #bcd4ee;
    border-radius: 6px;
    padding: 12px 16px;
    margin: 10px 0 16px;
    font-size: 14px;
    color: #1a4a7a !important;
}

.oha-vh-guide-tab .guide-body .email-note strong {
    color: #1a4a7a !important;
}

.oha-vh-guide-tab .guide-body .tip-card {
    background: #fffbf0;
    border: 1px solid #f6d860;
    border-radius: 6px;
    padding: 12px 16px;
    margin: 10px 0 16px;
    font-size: 14px;
}

.oha-vh-guide-tab .guide-body .step-number {
    display: inline-block;
    background: #e74c3c;
    color: white !important;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    line-height: 28px;
    text-align: center;
    font-weight: bold;
    font-size: 14px;
    margin-right: 10px;
    flex-shrink: 0;
}

@media print {
    .oha-vh-guide-tab .tab-header p { display: none; }
}
</style>
