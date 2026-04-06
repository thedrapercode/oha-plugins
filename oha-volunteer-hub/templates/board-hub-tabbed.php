<?php
/**
 * OHA Volunteer Hub - Tabbed Board Hub (Phase 4)
 * Complete volunteer management interface with 5 tabs
 * 
 * Variables available:
 * - $user_id (current user)
 * - $is_admin (boolean)
 * - $managed_areas (areas user manages)
 * - $all_areas (all areas in system)
 */

if (!defined('ABSPATH')) { exit; }

$active_tab = isset($_GET['vh_tab']) ? sanitize_text_field($_GET['vh_tab']) : 'volunteers';
$areas_to_show = $is_admin ? $all_areas : $managed_areas;

// Simple URL builder - only preserve tab parameter if it exists (for White Label CMS compatibility)
$external_param = '';
if (isset($_GET['tab']) && $_GET['tab'] !== 'vh_tab' && $_GET['tab'] !== 'vh_status' && $_GET['tab'] !== 'vh_search') {
    $external_param = '&tab=' . urlencode($_GET['tab']);
}
?>

<script>
// Make ajaxurl available for all tab scripts
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

// URL builder helper - preserves external tab parameter
function buildBoardHubUrl(vhTab, additionalParams) {
    let url = '?vh_tab=' + vhTab;
    
    // Preserve external tab parameter (for White Label CMS)
    const urlParams = new URLSearchParams(window.location.search);
    const externalTab = urlParams.get('tab');
    if (externalTab && externalTab !== 'vh_tab' && externalTab !== 'vh_status' && externalTab !== 'vh_search') {
        url += '&tab=' + encodeURIComponent(externalTab);
    }
    
    // Add additional parameters
    if (additionalParams) {
        for (const [key, value] of Object.entries(additionalParams)) {
            if (value) {
                url += '&' + key + '=' + encodeURIComponent(value);
            }
        }
    }
    
    return url;
}
</script>

<div class="oha-vh-board-hub">
    <h2 style="color: #333; margin-top: 0;">🎯 Volunteer Management Hub</h2>
    <p style="color: #666;">Complete volunteer, shift, and area management system</p>

    <!-- Tab Navigation -->
    <div class="oha-vh-tabs">
        <a href="?vh_tab=volunteers<?php echo $external_param; ?>" class="oha-vh-tab <?php echo $active_tab === 'volunteers' ? 'active' : ''; ?>">
            👥 Volunteers
        </a>
        <a href="?vh_tab=assign<?php echo $external_param; ?>" class="oha-vh-tab <?php echo $active_tab === 'assign' ? 'active' : ''; ?>">
            📍 Assign
        </a>
        <a href="?vh_tab=shifts<?php echo $external_param; ?>" class="oha-vh-tab <?php echo $active_tab === 'shifts' ? 'active' : ''; ?>">
            📅 Shifts
        </a>
        <a href="?vh_tab=areas<?php echo $external_param; ?>" class="oha-vh-tab <?php echo $active_tab === 'areas' ? 'active' : ''; ?>">
            🎯 Areas
        </a>
        <a href="?vh_tab=export<?php echo $external_param; ?>" class="oha-vh-tab <?php echo $active_tab === 'export' ? 'active' : ''; ?>">
            📥 Export
        </a>
        <a href="?vh_tab=schedule<?php echo $external_param; ?>" class="oha-vh-tab <?php echo $active_tab === 'schedule' ? 'active' : ''; ?>">
            📋 Schedule
        </a>
        <a href="?vh_tab=guide<?php echo $external_param; ?>" class="oha-vh-tab <?php echo $active_tab === 'guide' ? 'active' : ''; ?>">
            📖 Guide
        </a>
    </div>

    <!-- Tab Content -->
    <div class="oha-vh-tab-content">
        <?php
        switch ($active_tab) {
            case 'volunteers':
                include __DIR__ . '/board-hub-tabs/volunteers-tab.php';
                break;
            case 'assign':
                include __DIR__ . '/board-hub-tabs/assign-tab.php';
                break;
            case 'shifts':
                include __DIR__ . '/board-hub-tabs/shifts-tab.php';
                break;
            case 'areas':
                include __DIR__ . '/board-hub-tabs/areas-tab.php';
                break;
            case 'export':
                include __DIR__ . '/board-hub-tabs/export-tab.php';
                break;
            case 'schedule':
                include __DIR__ . '/board-hub-tabs/schedule-tab.php';
                break;
            case 'guide':
                include __DIR__ . '/board-hub-tabs/guide-tab.php';
                break;
            default:
                echo '<p>Invalid tab.</p>';
        }
        ?>
    </div>
</div>

<style>
/* Tabbed Board Hub Styles - COMPREHENSIVE COLOR FIXES */
.oha-vh-board-hub {
    background: white !important;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    color: #333 !important;
}

/* Force all text elements to be dark */
.oha-vh-board-hub,
.oha-vh-board-hub *:not(.status-badge):not(.preset-button):not(a.oha-vh-tab) {
    color: #333 !important;
}

.oha-vh-board-hub h1,
.oha-vh-board-hub h2,
.oha-vh-board-hub h3,
.oha-vh-board-hub h4,
.oha-vh-board-hub h5,
.oha-vh-board-hub h6 {
    color: #333 !important;
}

.oha-vh-board-hub p,
.oha-vh-board-hub div,
.oha-vh-board-hub span,
.oha-vh-board-hub label,
.oha-vh-board-hub td,
.oha-vh-board-hub th,
.oha-vh-board-hub li,
.oha-vh-board-hub strong {
    color: #333 !important;
}

.oha-vh-board-hub .tab-header h3,
.oha-vh-board-hub .tab-header h4 {
    color: #333 !important;
    margin-top: 0;
}

.oha-vh-board-hub .tab-header p {
    color: #666 !important;
}

.oha-vh-board-hub a:not(.oha-vh-tab):not(.button) {
    color: #2271b1 !important;
}

.oha-vh-board-hub input,
.oha-vh-board-hub select,
.oha-vh-board-hub textarea {
    color: #333 !important;
    background: white !important;
}

/* Status badges keep their white text */
.status-badge {
    color: white !important;
}

/* Tabs styling */
.oha-vh-tabs {
    display: flex;
    gap: 10px;
    margin: 30px 0 20px;
    border-bottom: 2px solid #ddd;
    flex-wrap: wrap;
}

.oha-vh-tab {
    padding: 12px 24px;
    text-decoration: none;
    color: #555 !important;
    border-bottom: 3px solid transparent;
    transition: all 0.3s;
    font-weight: 600;
    display: inline-block;
}

.oha-vh-tab:hover {
    color: #e74c3c !important;
    background: #f5f5f5;
    text-decoration: none !important;
}

.oha-vh-tab.active {
    color: #e74c3c !important;
    border-bottom-color: #e74c3c;
    background: #fff3f3;
}

.oha-vh-tab-content {
    padding: 30px 0;
}

/* Table styles */
.oha-vh-board-hub table th,
.oha-vh-board-hub table td {
    color: #333 !important;
}

/* Card styles */
.oha-vh-board-hub .summary-card .card-number,
.oha-vh-board-hub .summary-card .card-label,
.oha-vh-board-hub .stat-number,
.oha-vh-board-hub .stat-label {
    color: #333 !important;
}

/* UNIVERSAL MODAL STYLES - Proper Lightbox Overlay */
.oha-modal {
    display: none;
    position: fixed;
    z-index: 999999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: hidden; /* Changed from 'auto' to fix flexbox centering */
    background-color: rgba(0,0,0,0.7);
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
}

.oha-modal.show {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.oha-modal-content {
    background-color: white;
    padding: 30px;
    border-radius: 8px;
    width: 90%;
    max-width: 700px;
    max-height: 85vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease-out;
}

.oha-modal-content * {
    color: #333 !important;
}

.oha-modal-content h3,
.oha-modal-content h4 {
    margin-top: 0;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.oha-modal-close {
    position: absolute;
    right: 15px;
    top: 15px;
    font-size: 32px;
    font-weight: bold;
    cursor: pointer;
    color: #999 !important;
    line-height: 1;
    transition: color 0.2s;
    z-index: 10;
}

.oha-modal-close:hover {
    color: #333 !important;
}

/* Mobile responsive tabs */
@media (max-width: 768px) {
    .oha-vh-tabs {
        flex-direction: column;
        gap: 5px;
    }
    
    .oha-vh-tab {
        width: 100%;
        text-align: center;
        padding: 15px;
    }
    
    .oha-modal-content {
        width: 95%;
        max-height: 90vh;
        padding: 20px;
    }
}
</style>
