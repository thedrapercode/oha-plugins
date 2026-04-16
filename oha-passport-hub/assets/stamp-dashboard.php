<?php
/**
 * Stamps Dashboard Template
 * 
 * Displays user's badge stamp collection and trading statistics
 * Used by oha_my_stamps shortcode
 * 
 * @package OHA_PWA_Core
 * @version 3.4.0
 */

// Prevent direct access
defined('ABSPATH') || exit;

// Template variables
$show_leaderboard = isset($show_leaderboard) ? $show_leaderboard : false;
$compact = isset($compact) ? $compact : false;

?>

<div class="oha-stamps-dashboard-template">

    <!-- Dashboard Header -->
    <div class="stamps-dashboard-header">
        <div class="header-content">
            <h3>🏆 My Badge Collection</h3>
            <p>Your trading stamps and networking progress at OHA events</p>
        </div>
        <div class="header-actions">
            <button id="refresh-stamps" class="oha-btn-secondary refresh-btn">
                🔄 Refresh
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div class="stamps-loading" id="stamps-loading">
        <div class="oha-spinner"></div>
        <p>Loading your stamp collection...</p>
    </div>

    <!-- No Profile State -->
    <div class="no-profile-notice" id="no-profile" style="display: none;">
        <div class="notice-icon">🎭</div>
        <h4>Create Your Actor Profile First</h4>
        <p>You need an actor profile to collect badge stamps.</p>
        <a href="/actor-passport/" class="oha-btn-primary">Create Profile</a>
    </div>

    <!-- Main Dashboard Content -->
    <div class="stamps-dashboard-content" id="dashboard-content" style="display: none;">
        
        <!-- Statistics Overview -->
        <div class="stamps-statistics" id="stamps-stats">
            <!-- Stats loaded via AJAX -->
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity-section">
            <div class="section-header">
                <h4>📋 Recent Badge Trades</h4>
                <div class="activity-filters">
                    <select id="activity-filter" class="activity-filter-select">
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="event">Current Event</option>
                    </select>
                </div>
            </div>
            
            <div class="recent-stamps-container" id="recent-stamps">
                <!-- Recent stamps loaded via AJAX -->
            </div>
        </div>

        <!-- Trading Partners -->
        <div class="trading-partners-section" id="partners-section">
            <h4>🤝 Trading Partners</h4>
            <div class="partners-container" id="trading-partners">
                <!-- Partners loaded via AJAX -->
            </div>
        </div>

        <!-- Achievements & Milestones -->
        <div class="achievements-section" id="achievements-section">
            <h4>🎯 Achievements</h4>
            <div class="achievements-grid" id="achievements-grid">
                <!-- Achievements loaded via AJAX -->
            </div>
        </div>

        <!-- Export & Sharing -->
        <div class="export-section">
            <h4>📤 Share Your Progress</h4>
            <div class="export-actions">
                <button id="export-stamps-csv" class="oha-btn-secondary">
                    📊 Export CSV
                </button>
                <button id="generate-summary" class="oha-btn-secondary">
                    📋 Generate Summary
                </button>
                <button id="share-progress" class="oha-btn-primary">
                    🎭 Share Progress
                </button>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div class="empty-stamps-state" id="empty-stamps" style="display: none;">
        <div class="empty-illustration">
            <div class="empty-icon">🎭</div>
            <div class="empty-badges">
                <div class="badge-placeholder"></div>
                <div class="badge-placeholder"></div>
                <div class="badge-placeholder"></div>
            </div>
        </div>
        
        <div class="empty-content">
            <h4>No Badge Stamps Yet</h4>
            <p>Start networking with other actors at OHA events to collect your first stamps!</p>
            
            <div class="getting-started">
                <h5>Getting Started:</h5>
                <ul>
                    <li>✅ Create your actor profile</li>
                    <li>📱 Show your QR code to other actors</li>
                    <li>📷 Scan their QR codes in return</li>
                    <li>🏆 Watch your collection grow!</li>
                </ul>
            </div>
            
            <div class="empty-actions">
                <a href="/qr-scanner/" class="oha-btn-primary">
                    📷 Start Scanning
                </a>
                <a href="/actor-passport/" class="oha-btn-secondary">
                    🎭 View My Profile
                </a>
            </div>
        </div>
    </div>

    <!-- Simple Leaderboard -->
    <?php if ($show_leaderboard): ?>
    <div class="simple-leaderboard-section" id="leaderboard-section">
        <div class="leaderboard-header">
            <h4>🌟 Community Highlights</h4>
            <p><small>Celebrating our most active networkers (not competitive!)</small></p>
        </div>
        <div class="leaderboard-content" id="leaderboard-content">
            <!-- Leaderboard loaded via AJAX -->
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Template Styles -->
<style>
.oha-stamps-dashboard-template {
    max-width: 1000px;
    margin: 0 auto;
    padding: var(--oha-passport-spacing, 1rem);
}

/* Header */
.stamps-dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    background: linear-gradient(135deg, var(--oha-passport-primary, #2d1b3d), var(--oha-passport-accent, #ff6b35));
    border-radius: var(--oha-passport-radius-large, 20px);
    padding: var(--oha-passport-spacing-large, 2rem);
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
    color: white;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

.header-content h3 {
    font-family: var(--oha-passport-font-primary, 'Creepster', cursive);
    font-size: 2rem;
    margin: 0 0 0.5rem 0;
}

.header-content p {
    margin: 0;
    opacity: 0.9;
}

.refresh-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
}

.refresh-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
}

/* Loading State */
.stamps-loading {
    text-align: center;
    padding: var(--oha-passport-spacing-large, 2rem);
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius-large, 20px);
    color: var(--oha-passport-text-muted, #cccccc);
}

/* No Profile Notice */
.no-profile-notice {
    text-align: center;
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius-large, 20px);
    padding: var(--oha-passport-spacing-large, 2rem);
    border: 2px dashed var(--oha-passport-border, #444444);
}

.notice-icon {
    font-size: 4rem;
    margin-bottom: var(--oha-passport-spacing, 1rem);
    opacity: 0.7;
}

/* Statistics Overview */
.stamps-statistics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: var(--oha-passport-spacing, 1rem);
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
}

.stat-card {
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius, 12px);
    padding: var(--oha-passport-spacing, 1rem);
    text-align: center;
    border: 1px solid var(--oha-passport-border, #444444);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--oha-passport-shadow, 0 4px 20px rgba(0, 0, 0, 0.3));
    border-color: var(--oha-passport-accent, #ff6b35);
}

.stat-card .stat-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.stat-card .stat-value {
    display: block;
    font-size: 2rem;
    font-weight: 800;
    color: var(--oha-passport-accent, #ff6b35);
    font-family: var(--oha-passport-font-mono, monospace);
    line-height: 1;
}

.stat-card .stat-label {
    display: block;
    font-size: 0.8rem;
    color: var(--oha-passport-text-muted, #cccccc);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.5rem;
}

.stat-card .stat-description {
    font-size: 0.7rem;
    color: var(--oha-passport-text-light, #999999);
    margin-top: 0.25rem;
}

/* Section Headers */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--oha-passport-spacing, 1rem);
}

.section-header h4 {
    color: var(--oha-passport-accent, #ff6b35);
    margin: 0;
    font-size: 1.3rem;
}

.activity-filter-select {
    background: var(--oha-passport-input-bg, #333333);
    border: 1px solid var(--oha-passport-border, #444444);
    border-radius: var(--oha-passport-radius, 12px);
    color: var(--oha-passport-text, #ffffff);
    padding: 0.5rem;
    font-size: 0.9rem;
}

/* Recent Activity */
.recent-activity-section {
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius-large, 20px);
    padding: var(--oha-passport-spacing-large, 2rem);
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
    border: 1px solid var(--oha-passport-border, #444444);
}

.recent-stamps-container {
    display: grid;
    gap: var(--oha-passport-spacing, 1rem);
    max-height: 400px;
    overflow-y: auto;
}

.stamp-trade-item {
    display: flex;
    align-items: center;
    gap: var(--oha-passport-spacing, 1rem);
    background: var(--oha-passport-input-bg, #333333);
    border-radius: var(--oha-passport-radius, 12px);
    padding: var(--oha-passport-spacing, 1rem);
    border: 1px solid var(--oha-passport-border, #444444);
    transition: all 0.3s ease;
}

.stamp-trade-item:hover {
    background: var(--oha-passport-border, #444444);
    border-color: var(--oha-passport-accent, #ff6b35);
}

.trade-icon {
    background: var(--oha-passport-accent, #ff6b35);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.trade-details {
    flex: 1;
}

.trade-partner {
    font-weight: 600;
    color: var(--oha-passport-text, #ffffff);
    margin-bottom: 0.25rem;
}

.trade-info {
    display: flex;
    gap: var(--oha-passport-spacing, 1rem);
    font-size: 0.8rem;
    color: var(--oha-passport-text-muted, #cccccc);
    flex-wrap: wrap;
}

.trade-haunt,
.trade-date,
.trade-type {
    background: var(--oha-passport-card-bg, #2c2c2c);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    white-space: nowrap;
}

/* Trading Partners */
.trading-partners-section {
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius-large, 20px);
    padding: var(--oha-passport-spacing-large, 2rem);
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
    border: 1px solid var(--oha-passport-border, #444444);
}

.partners-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: var(--oha-passport-spacing, 1rem);
}

.partner-card {
    background: var(--oha-passport-input-bg, #333333);
    border-radius: var(--oha-passport-radius, 12px);
    padding: var(--oha-passport-spacing, 1rem);
    text-align: center;
    border: 1px solid var(--oha-passport-border, #444444);
    transition: all 0.3s ease;
}

.partner-card:hover {
    transform: translateY(-2px);
    border-color: var(--oha-passport-accent, #ff6b35);
}

.partner-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--oha-passport-accent, #ff6b35);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    margin: 0 auto var(--oha-passport-spacing, 1rem);
    font-size: 1.2rem;
}

.partner-name {
    font-weight: 600;
    color: var(--oha-passport-text, #ffffff);
    margin-bottom: 0.25rem;
}

.partner-haunt {
    font-size: 0.8rem;
    color: var(--oha-passport-text-muted, #cccccc);
    font-style: italic;
    margin-bottom: 0.5rem;
}

.partner-trades {
    font-size: 0.7rem;
    color: var(--oha-passport-text-light, #999999);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Achievements */
.achievements-section {
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius-large, 20px);
    padding: var(--oha-passport-spacing-large, 2rem);
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
    border: 1px solid var(--oha-passport-border, #444444);
}

.achievements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: var(--oha-passport-spacing, 1rem);
}

.achievement-card {
    display: flex;
    align-items: center;
    gap: var(--oha-passport-spacing, 1rem);
    background: var(--oha-passport-input-bg, #333333);
    border-radius: var(--oha-passport-radius, 12px);
    padding: var(--oha-passport-spacing, 1rem);
    border: 1px solid var(--oha-passport-border, #444444);
    transition: all 0.3s ease;
}

.achievement-card.unlocked {
    border-color: var(--oha-passport-success, #27ae60);
    background: rgba(39, 174, 96, 0.1);
}

.achievement-card.unlocked:hover {
    box-shadow: 0 0 20px rgba(39, 174, 96, 0.3);
}

.achievement-icon {
    font-size: 2rem;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.achievement-card.unlocked .achievement-icon {
    background: var(--oha-passport-success, #27ae60);
    color: white;
}

.achievement-card:not(.unlocked) .achievement-icon {
    background: var(--oha-passport-secondary, #4a4a4a);
    color: var(--oha-passport-text-muted, #cccccc);
    opacity: 0.5;
}

.achievement-info {
    flex: 1;
}

.achievement-title {
    font-weight: 600;
    color: var(--oha-passport-text, #ffffff);
    margin-bottom: 0.25rem;
}

.achievement-description {
    font-size: 0.8rem;
    color: var(--oha-passport-text-muted, #cccccc);
    line-height: 1.4;
}

.achievement-progress {
    font-size: 0.7rem;
    color: var(--oha-passport-accent, #ff6b35);
    margin-top: 0.25rem;
    font-family: var(--oha-passport-font-mono, monospace);
}

/* Export Section */
.export-section {
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius-large, 20px);
    padding: var(--oha-passport-spacing-large, 2rem);
    border: 1px solid var(--oha-passport-border, #444444);
    text-align: center;
}

.export-section h4 {
    color: var(--oha-passport-accent, #ff6b35);
    margin-bottom: var(--oha-passport-spacing, 1rem);
}

.export-actions {
    display: flex;
    gap: var(--oha-passport-spacing, 1rem);
    justify-content: center;
    flex-wrap: wrap;
}

/* Empty State */
.empty-stamps-state {
    text-align: center;
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius-large, 20px);
    padding: var(--oha-passport-spacing-large, 2rem);
    border: 2px dashed var(--oha-passport-border, #444444);
}

.empty-illustration {
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: var(--oha-passport-spacing, 1rem);
    opacity: 0.7;
}

.empty-badges {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: var(--oha-passport-spacing, 1rem);
}

.badge-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--oha-passport-border, #444444);
    opacity: 0.3;
    animation: fadeInOut 2s ease-in-out infinite;
}

.badge-placeholder:nth-child(2) {
    animation-delay: 0.5s;
}

.badge-placeholder:nth-child(3) {
    animation-delay: 1s;
}

@keyframes fadeInOut {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 0.6; }
}

.empty-content h4 {
    color: var(--oha-passport-accent, #ff6b35);
    margin-bottom: var(--oha-passport-spacing, 1rem);
}

.getting-started {
    background: rgba(255, 107, 53, 0.1);
    border-radius: var(--oha-passport-radius, 12px);
    padding: var(--oha-passport-spacing, 1rem);
    margin: var(--oha-passport-spacing-large, 2rem) 0;
    text-align: left;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

.getting-started h5 {
    color: var(--oha-passport-accent, #ff6b35);
    margin: 0 0 var(--oha-passport-spacing, 1rem) 0;
    text-align: center;
}

.getting-started ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.getting-started li {
    padding: 0.25rem 0;
    color: var(--oha-passport-text-muted, #cccccc);
}

.empty-actions {
    display: flex;
    gap: var(--oha-passport-spacing, 1rem);
    justify-content: center;
    flex-wrap: wrap;
}

/* Simple Leaderboard */
.simple-leaderboard-section {
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius-large, 20px);
    padding: var(--oha-passport-spacing-large, 2rem);
    border: 1px solid var(--oha-passport-border, #444444);
    margin-top: var(--oha-passport-spacing-large, 2rem);
}

.leaderboard-header {
    text-align: center;
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
}

.leaderboard-header h4 {
    color: var(--oha-passport-accent, #ff6b35);
    margin-bottom: 0.5rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .oha-stamps-dashboard-template {
        padding: 0.5rem;
    }
    
    .stamps-dashboard-header {
        flex-direction: column;
        gap: var(--oha-passport-spacing, 1rem);
        text-align: center;
    }
    
    .stamps-statistics {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .section-header {
        flex-direction: column;
        gap: var(--oha-passport-spacing, 1rem);
        text-align: center;
    }
    
    .export-actions {
        flex-direction: column;
        max-width: 300px;
        margin: 0 auto;
    }
    
    .partners-container {
        grid-template-columns: 1fr;
    }
    
    .achievements-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .stamps-statistics {
        grid-template-columns: 1fr;
    }
    
    .trade-info {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .empty-actions {
        flex-direction: column;
        max-width: 200px;
        margin: 0 auto;
    }
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .stat-card,
    .stamp-trade-item,
    .partner-card,
    .achievement-card {
        border-width: 2px;
    }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .badge-placeholder,
    .stat-card,
    .stamp-trade-item,
    .partner-card,
    .achievement-card {
        animation: none;
        transition: none;
    }
}
</style>
