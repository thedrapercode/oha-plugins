<?php
/**
 * Actor Passport Card Template
 * 
 * Displays individual actor passport cards for OHA events
 * Used by shortcodes and AJAX responses
 * 
 * @package OHA_PWA_Core
 * @version 3.4.0
 */

// Prevent direct access
defined('ABSPATH') || exit;

// Template variables should be passed in
$profile = isset($profile) ? $profile : array();
$show_qr = isset($show_qr) ? $show_qr : true;
$compact = isset($compact) ? $compact : false;

if (empty($profile)) {
    return;
}

$character_name = esc_html($profile['character_name'] ?? '');
$character_type = esc_html($profile['character_type'] ?? '');
$haunt_name = esc_html($profile['haunt_name'] ?? '');
$character_bio = esc_html($profile['character_bio'] ?? '');
$stamps_earned = intval($profile['stamps_earned'] ?? 0);
$created_at = $profile['created_at'] ?? '';
$qr_code_hash = $profile['qr_code_hash'] ?? '';

?>

<div class="oha-passport-card <?php echo $compact ? 'compact' : ''; ?>" data-profile-id="<?php echo intval($profile['id'] ?? 0); ?>">
    
    <!-- Passport Header -->
    <div class="passport-card-header">
        <div class="character-info">
            <h4 class="character-name"><?php echo $character_name; ?></h4>
            <?php if ($character_type): ?>
                <span class="character-type"><?php echo $character_type; ?></span>
            <?php endif; ?>
        </div>
        
        <?php if ($stamps_earned > 0): ?>
            <div class="stamp-badge">
                <span class="stamp-count"><?php echo $stamps_earned; ?></span>
                <span class="stamp-label">Stamps</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Haunt Information -->
    <?php if ($haunt_name): ?>
        <div class="passport-haunt-info">
            <div class="haunt-label">Works at:</div>
            <div class="haunt-name"><?php echo $haunt_name; ?></div>
        </div>
    <?php endif; ?>

    <!-- Character Bio -->
    <?php if ($character_bio && !$compact): ?>
        <div class="passport-bio">
            <div class="bio-label">Character Bio:</div>
            <p class="bio-text"><?php echo nl2br($character_bio); ?></p>
        </div>
    <?php endif; ?>

    <!-- Stats Section -->
    <?php if (!$compact): ?>
        <div class="passport-stats">
            <div class="stat-item">
                <span class="stat-value"><?php echo $stamps_earned; ?></span>
                <span class="stat-label">Badge Stamps</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo $created_at ? date('M Y', strtotime($created_at)) : 'New'; ?></span>
                <span class="stat-label">Member Since</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- QR Code Section -->
    <?php if ($show_qr && $qr_code_hash): ?>
        <div class="passport-qr-section">
            <div class="qr-header">
                <h5>🎭 Trading QR Code</h5>
                <p>Share this with other actors to trade badges</p>
            </div>
            
            <div class="qr-code-display">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($qr_code_hash); ?>&format=png&margin=10" 
                     alt="QR Code for <?php echo $character_name; ?>" 
                     class="qr-image">
                
                <div class="qr-info">
                    <p class="qr-character"><?php echo $character_name; ?></p>
                    <?php if ($haunt_name): ?>
                        <p class="qr-haunt"><?php echo $haunt_name; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="passport-actions">
        <button class="oha-btn-secondary edit-profile-btn" data-profile-id="<?php echo intval($profile['id']); ?>">
            ✏️ Edit Profile
        </button>
        
        <?php if ($show_qr): ?>
            <button class="oha-btn-primary show-qr-btn" data-profile-id="<?php echo intval($profile['id']); ?>">
                📱 Show QR
            </button>
        <?php endif; ?>
    </div>

    <!-- User Content Disclaimer -->
    <div class="passport-disclaimer">
        <p><small>⚠️ Content is user-controlled. OHA and haunts do not monitor or verify profile information.</small></p>
    </div>
</div>

<!-- CSS for this template -->
<style>
.oha-passport-card {
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius-large, 20px);
    padding: var(--oha-passport-spacing-large, 2rem);
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
    border: 1px solid var(--oha-passport-border, #444444);
    box-shadow: var(--oha-passport-shadow, 0 4px 20px rgba(0, 0, 0, 0.3));
    position: relative;
    overflow: hidden;
}

.oha-passport-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--oha-passport-accent, #ff6b35), var(--oha-passport-primary, #2d1b3d));
}

.passport-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--oha-passport-spacing, 1rem);
    padding-bottom: var(--oha-passport-spacing, 1rem);
    border-bottom: 2px solid var(--oha-passport-border, #444444);
}

.character-name {
    font-family: var(--oha-passport-font-primary, 'Creepster', cursive);
    font-size: 1.5rem;
    color: var(--oha-passport-accent, #ff6b35);
    margin: 0 0 0.5rem 0;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

.character-type {
    background: var(--oha-passport-accent, #ff6b35);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stamp-badge {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: var(--oha-passport-success, #27ae60);
    color: white;
    padding: 0.5rem;
    border-radius: var(--oha-passport-radius, 12px);
    min-width: 60px;
}

.stamp-count {
    font-size: 1.2rem;
    font-weight: 800;
    font-family: var(--oha-passport-font-mono, monospace);
}

.stamp-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.passport-haunt-info {
    background: rgba(255, 107, 53, 0.1);
    border-left: 4px solid var(--oha-passport-accent, #ff6b35);
    padding: 0.75rem;
    margin-bottom: var(--oha-passport-spacing, 1rem);
    border-radius: 0 var(--oha-passport-radius, 12px) var(--oha-passport-radius, 12px) 0;
}

.haunt-label {
    font-size: 0.8rem;
    color: var(--oha-passport-text-muted, #cccccc);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.haunt-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--oha-passport-text, #ffffff);
}

.passport-bio {
    margin-bottom: var(--oha-passport-spacing, 1rem);
}

.bio-label {
    font-size: 0.8rem;
    color: var(--oha-passport-text-muted, #cccccc);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.bio-text {
    color: var(--oha-passport-text-muted, #cccccc);
    line-height: 1.6;
    font-style: italic;
    margin: 0;
}

.passport-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--oha-passport-spacing, 1rem);
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
}

.stat-item {
    text-align: center;
    padding: var(--oha-passport-spacing, 1rem);
    background: var(--oha-passport-input-bg, #333333);
    border-radius: var(--oha-passport-radius, 12px);
    border: 1px solid var(--oha-passport-border, #444444);
}

.stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--oha-passport-accent, #ff6b35);
    font-family: var(--oha-passport-font-mono, monospace);
}

.stat-label {
    display: block;
    font-size: 0.8rem;
    color: var(--oha-passport-text-muted, #cccccc);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.25rem;
}

.passport-qr-section {
    background: var(--oha-passport-input-bg, #333333);
    border-radius: var(--oha-passport-radius, 12px);
    padding: var(--oha-passport-spacing, 1rem);
    margin-bottom: var(--oha-passport-spacing, 1rem);
    text-align: center;
    border: 1px solid var(--oha-passport-border, #444444);
}

.qr-header h5 {
    color: var(--oha-passport-accent, #ff6b35);
    margin: 0 0 0.5rem 0;
}

.qr-header p {
    color: var(--oha-passport-text-muted, #cccccc);
    font-size: 0.9rem;
    margin: 0 0 1rem 0;
}

.qr-code-display {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--oha-passport-spacing, 1rem);
    flex-wrap: wrap;
}

.qr-image {
    border-radius: var(--oha-passport-radius, 12px);
    background: white;
    padding: 0.5rem;
}

.qr-character {
    font-weight: 600;
    color: var(--oha-passport-text, #ffffff);
    margin: 0;
}

.qr-haunt {
    color: var(--oha-passport-text-muted, #cccccc);
    font-size: 0.9rem;
    margin: 0;
}

.passport-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--oha-passport-spacing, 1rem);
    margin-bottom: var(--oha-passport-spacing, 1rem);
}

.passport-disclaimer {
    border-top: 1px solid var(--oha-passport-border, #444444);
    padding-top: var(--oha-passport-spacing, 1rem);
    text-align: center;
}

.passport-disclaimer p {
    margin: 0;
    color: var(--oha-passport-text-light, #999999);
}

/* Compact version */
.oha-passport-card.compact {
    padding: var(--oha-passport-spacing, 1rem);
}

.oha-passport-card.compact .passport-stats,
.oha-passport-card.compact .passport-qr-section,
.oha-passport-card.compact .passport-bio {
    display: none;
}

.oha-passport-card.compact .passport-actions {
    grid-template-columns: 1fr;
}

/* Responsive design */
@media (max-width: 768px) {
    .passport-card-header {
        flex-direction: column;
        gap: var(--oha-passport-spacing, 1rem);
    }
    
    .qr-code-display {
        flex-direction: column;
    }
    
    .passport-actions {
        grid-template-columns: 1fr;
    }
}
</style>
