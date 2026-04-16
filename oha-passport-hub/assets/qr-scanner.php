<?php
/**
 * QR Scanner Interface Template
 * 
 * Mobile-optimized QR code scanner for badge trading
 * Used by oha_qr_scanner shortcode
 * 
 * @package OHA_PWA_Core
 * @version 3.4.0
 */

// Prevent direct access
defined('ABSPATH') || exit;

// Template variables
$show_instructions = isset($show_instructions) ? $show_instructions : true;
$auto_start = isset($auto_start) ? $auto_start : false;

?>

<div class="oha-qr-scanner-template">
    
    <!-- Scanner Status -->
    <div class="scanner-status" id="scanner-status">
        <div class="status-checking">
            <div class="status-icon">🔍</div>
            <h4>Checking Event Status...</h4>
            <div class="oha-spinner"></div>
        </div>
    </div>

    <!-- Scanner Interface -->
    <div class="scanner-interface" id="scanner-interface" style="display: none;">
        
        <!-- Camera Container -->
        <div class="camera-container" id="camera-container">
            <video id="camera-preview" autoplay playsinline muted></video>
            
            <!-- Scan Overlay -->
            <div class="scan-overlay" id="scan-overlay">
                <div class="scan-frame"></div>
                <p class="scan-instruction">Position QR code within the frame</p>
                <div class="scan-tips">
                    <small>💡 Hold camera steady • Good lighting helps • 2 feet away works best</small>
                </div>
            </div>
        </div>

        <!-- Scanner Controls -->
        <div class="scanner-controls">
            <button id="start-scan-btn" class="oha-btn-primary scanner-btn">
                📷 Start Scanning
            </button>
            
            <button id="stop-scan-btn" class="oha-btn-secondary scanner-btn" style="display: none;">
                ⏹️ Stop Scanning
            </button>
            
            <button id="switch-camera-btn" class="oha-btn-secondary scanner-btn" style="display: none;">
                🔄 Switch Camera
            </button>
        </div>

        <!-- Manual Input Fallback -->
        <div class="manual-input-section">
            <details class="manual-input-toggle">
                <summary>Having trouble? Enter QR code manually</summary>
                <div class="manual-input-content">
                    <input type="text" 
                           id="manual-qr-input" 
                           placeholder="Paste or type QR code here..." 
                           class="manual-qr-field">
                    <button id="process-manual-btn" class="oha-btn-secondary">
                        ✅ Process Code
                    </button>
                    <p class="manual-help">
                        <small>Ask the other actor to copy their QR code text and share it with you.</small>
                    </p>
                </div>
            </details>
        </div>
    </div>

    <!-- Scan Results -->
    <div class="scan-results" id="scan-results" style="display: none;">
        <!-- Results will be populated by JavaScript -->
    </div>

    <!-- Permission Request -->
    <div class="camera-permission" id="camera-permission" style="display: none;">
        <div class="permission-icon">📷</div>
        <h4>Camera Permission Required</h4>
        <p>Badge trading requires camera access to scan QR codes.</p>
        <button id="request-camera-btn" class="oha-btn-primary">
            📷 Enable Camera
        </button>
        <div class="permission-help">
            <p><small>
                If permission was denied, you can enable it in your browser settings or use the manual input option above.
            </small></p>
        </div>
    </div>

    <!-- Scanner Instructions -->
    <?php if ($show_instructions): ?>
    <div class="scanner-instructions">
        <h4>🎭 How to Trade Badges</h4>
        
        <div class="instruction-steps">
            <div class="step-item">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h5>Find Another Actor</h5>
                    <p>Look for actors wearing badges or attending the convention</p>
                </div>
            </div>
            
            <div class="step-item">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h5>Ask to Trade Badges</h5>
                    <p>Most actors are happy to trade! Just ask politely</p>
                </div>
            </div>
            
            <div class="step-item">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h5>Scan Their QR Code</h5>
                    <p>They'll show their code, you scan it with this tool</p>
                </div>
            </div>
            
            <div class="step-item">
                <div class="step-number">4</div>
                <div class="step-content">
                    <h5>Both Get Stamps!</h5>
                    <p>You both automatically receive a badge stamp</p>
                </div>
            </div>
        </div>

        <div class="trading-rules">
            <h5>📋 Trading Rules</h5>
            <ul>
                <li>✅ One trade per person per day</li>
                <li>✅ Both actors must have profiles</li>
                <li>✅ Trading only works during OHA events</li>
                <li>✅ Be respectful and have fun networking!</li>
                <li>❌ No trading your own QR code</li>
                <li>❌ Maximum 50 scans per day</li>
            </ul>
        </div>

        <div class="troubleshooting">
            <h5>🔧 Troubleshooting</h5>
            <details class="troubleshoot-item">
                <summary>Camera not working</summary>
                <ul>
                    <li>Check browser permissions</li>
                    <li>Try a different browser</li>
                    <li>Use manual input as backup</li>
                </ul>
            </details>
            
            <details class="troubleshoot-item">
                <summary>QR code won't scan</summary>
                <ul>
                    <li>Ensure good lighting</li>
                    <li>Hold camera 2 feet away</li>
                    <li>Make sure QR code is fully in frame</li>
                    <li>Ask actor to refresh their QR code</li>
                </ul>
            </details>
            
            <details class="troubleshoot-item">
                <summary>Trade failed</summary>
                <ul>
                    <li>Check if you've already traded today</li>
                    <li>Verify event is active</li>
                    <li>Both actors need profiles</li>
                </ul>
            </details>
        </div>
    </div>
    <?php endif; ?>

    <!-- Event Inactive Notice -->
    <div class="event-inactive-notice" id="event-inactive" style="display: none;">
        <div class="inactive-icon">⏸️</div>
        <h4>Badge Trading Unavailable</h4>
        <p>Badge trading is only available during active OHA events.</p>
        <div class="next-event">
            <strong>Next Event:</strong> OHA Convention 2026<br>
            <strong>Dates:</strong> May 9-10, 2026<br>
            <strong>Location:</strong> Ohio State Reformatory, Mansfield
        </div>
    </div>

</div>

<!-- Template Styles -->
<style>
.oha-qr-scanner-template {
    max-width: 600px;
    margin: 0 auto;
    padding: var(--oha-passport-spacing, 1rem);
}

/* Scanner Status */
.scanner-status {
    text-align: center;
    padding: var(--oha-passport-spacing-large, 2rem);
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius-large, 20px);
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
}

.status-icon {
    font-size: 3rem;
    margin-bottom: var(--oha-passport-spacing, 1rem);
}

.status-checking h4 {
    color: var(--oha-passport-text, #ffffff);
    margin-bottom: var(--oha-passport-spacing, 1rem);
}

/* Camera Container */
.camera-container {
    position: relative;
    width: 100%;
    max-width: 400px;
    margin: 0 auto var(--oha-passport-spacing-large, 2rem);
    aspect-ratio: 1;
    border-radius: var(--oha-passport-radius, 12px);
    overflow: hidden;
    background: var(--oha-passport-input-bg, #333333);
    border: 2px solid var(--oha-passport-border, #444444);
}

.camera-container.active {
    border-color: var(--oha-passport-accent, #ff6b35);
    box-shadow: var(--oha-passport-glow, 0 0 20px rgba(255, 107, 53, 0.3));
}

#camera-preview {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.scan-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    text-align: center;
}

.scan-frame {
    width: 60%;
    height: 60%;
    border: 3px solid var(--oha-passport-accent, #ff6b35);
    border-radius: var(--oha-passport-radius, 12px);
    position: relative;
    margin-bottom: var(--oha-passport-spacing, 1rem);
    animation: scanPulse 2s ease-in-out infinite;
}

@keyframes scanPulse {
    0%, 100% { 
        border-color: var(--oha-passport-accent, #ff6b35);
        transform: scale(1);
    }
    50% { 
        border-color: rgba(255, 107, 53, 0.5);
        transform: scale(1.05);
    }
}

.scan-instruction {
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.8);
}

.scan-tips {
    opacity: 0.8;
}

.scan-tips small {
    background: rgba(0, 0, 0, 0.5);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

/* Scanner Controls */
.scanner-controls {
    display: flex;
    gap: var(--oha-passport-spacing, 1rem);
    justify-content: center;
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
    flex-wrap: wrap;
}

.scanner-btn {
    min-width: 140px;
}

/* Manual Input */
.manual-input-section {
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius, 12px);
    border: 1px solid var(--oha-passport-border, #444444);
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
}

.manual-input-toggle summary {
    padding: var(--oha-passport-spacing, 1rem);
    cursor: pointer;
    color: var(--oha-passport-text, #ffffff);
    font-weight: 600;
    user-select: none;
}

.manual-input-toggle summary:hover {
    color: var(--oha-passport-accent, #ff6b35);
}

.manual-input-content {
    padding: 0 var(--oha-passport-spacing, 1rem) var(--oha-passport-spacing, 1rem);
    border-top: 1px solid var(--oha-passport-border, #444444);
}

.manual-qr-field {
    width: 100%;
    padding: 0.75rem;
    background: var(--oha-passport-input-bg, #333333);
    border: 2px solid var(--oha-passport-border, #444444);
    border-radius: var(--oha-passport-radius, 12px);
    color: var(--oha-passport-text, #ffffff);
    margin-bottom: var(--oha-passport-spacing, 1rem);
    box-sizing: border-box;
}

.manual-qr-field:focus {
    outline: none;
    border-color: var(--oha-passport-accent, #ff6b35);
    box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.2);
}

.manual-help {
    color: var(--oha-passport-text-muted, #cccccc);
    margin: 0;
    font-style: italic;
}

/* Scan Results */
.scan-results {
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius-large, 20px);
    padding: var(--oha-passport-spacing-large, 2rem);
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
    text-align: center;
    border: 1px solid var(--oha-passport-border, #444444);
}

/* Permission Request */
.camera-permission {
    text-align: center;
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius-large, 20px);
    padding: var(--oha-passport-spacing-large, 2rem);
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
    border: 2px dashed var(--oha-passport-border, #444444);
}

.permission-icon {
    font-size: 4rem;
    margin-bottom: var(--oha-passport-spacing, 1rem);
    opacity: 0.7;
}

.permission-help {
    margin-top: var(--oha-passport-spacing, 1rem);
    color: var(--oha-passport-text-muted, #cccccc);
}

/* Instructions */
.scanner-instructions {
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius, 12px);
    padding: var(--oha-passport-spacing-large, 2rem);
    border: 1px solid var(--oha-passport-border, #444444);
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
}

.scanner-instructions h4 {
    color: var(--oha-passport-accent, #ff6b35);
    text-align: center;
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
}

.instruction-steps {
    margin-bottom: var(--oha-passport-spacing-large, 2rem);
}

.step-item {
    display: flex;
    gap: var(--oha-passport-spacing, 1rem);
    margin-bottom: var(--oha-passport-spacing, 1rem);
    align-items: flex-start;
}

.step-number {
    background: var(--oha-passport-accent, #ff6b35);
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.step-content h5 {
    margin: 0 0 0.25rem 0;
    color: var(--oha-passport-text, #ffffff);
}

.step-content p {
    margin: 0;
    color: var(--oha-passport-text-muted, #cccccc);
    font-size: 0.9rem;
}

.trading-rules {
    background: rgba(255, 107, 53, 0.1);
    border-radius: var(--oha-passport-radius, 12px);
    padding: var(--oha-passport-spacing, 1rem);
    margin-bottom: var(--oha-passport-spacing, 1rem);
    border: 1px solid rgba(255, 107, 53, 0.3);
}

.trading-rules h5 {
    color: var(--oha-passport-accent, #ff6b35);
    margin: 0 0 var(--oha-passport-spacing, 1rem) 0;
}

.trading-rules ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.trading-rules li {
    padding: 0.25rem 0;
    color: var(--oha-passport-text-muted, #cccccc);
}

.troubleshooting h5 {
    color: var(--oha-passport-accent, #ff6b35);
    margin: 0 0 var(--oha-passport-spacing, 1rem) 0;
}

.troubleshoot-item {
    margin-bottom: 0.5rem;
    border: 1px solid var(--oha-passport-border, #444444);
    border-radius: var(--oha-passport-radius, 12px);
}

.troubleshoot-item summary {
    padding: 0.75rem;
    cursor: pointer;
    color: var(--oha-passport-text, #ffffff);
    font-weight: 600;
    user-select: none;
}

.troubleshoot-item summary:hover {
    color: var(--oha-passport-accent, #ff6b35);
}

.troubleshoot-item ul {
    padding: 0 0.75rem 0.75rem;
    margin: 0;
    color: var(--oha-passport-text-muted, #cccccc);
    border-top: 1px solid var(--oha-passport-border, #444444);
}

/* Event Inactive */
.event-inactive-notice {
    text-align: center;
    background: var(--oha-passport-card-bg, #2c2c2c);
    border-radius: var(--oha-passport-radius-large, 20px);
    padding: var(--oha-passport-spacing-large, 2rem);
    border: 2px solid var(--oha-passport-warning, #f39c12);
    color: var(--oha-passport-warning, #f39c12);
}

.inactive-icon {
    font-size: 4rem;
    margin-bottom: var(--oha-passport-spacing, 1rem);
}

.next-event {
    background: rgba(243, 156, 18, 0.1);
    border-radius: var(--oha-passport-radius, 12px);
    padding: var(--oha-passport-spacing, 1rem);
    margin-top: var(--oha-passport-spacing, 1rem);
    color: var(--oha-passport-text-muted, #cccccc);
}

/* Responsive Design */
@media (max-width: 768px) {
    .oha-qr-scanner-template {
        padding: 0.5rem;
    }
    
    .scanner-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .scanner-btn {
        min-width: auto;
    }
    
    .step-item {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    
    .camera-container {
        max-width: 300px;
    }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    .scan-frame {
        animation: none;
    }
}
</style>

<script>
// Auto-initialize scanner template
document.addEventListener('DOMContentLoaded', function() {
    if (typeof OHAActorPassport !== 'undefined') {
        // Scanner template is loaded, initialize if auto-start
        <?php if ($auto_start): ?>
        setTimeout(function() {
            OHAActorPassport.loadScanner();
        }, 1000);
        <?php endif; ?>
    }
});
</script>
