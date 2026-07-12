/**
 * OHA Passport Hub JavaScript
 *
 * Handles all passport-related functionality:
 * - Character profile creation/editing
 * - QR code generation and display
 * - QR code scanning with multi-mode routing:
 *     • Actor badge trading    (64-char hex hash)
 *     • Vendor check-in        (VND:{vendor_id})
 *     • Scavenger hunt         (LOC:{location_hash})
 *     • General URL opener     (http:// or https://)
 * - Badge trading and stamp collection
 * - Mobile-optimized interactions
 *
 * @package OHA_Passport_Hub
 * @version 2.0.0
 */

(function($) {
    // Backward compat — some pages may localize as ohaActorPassport (PWA plugin legacy)
    if (typeof ohaActorPassport !== "undefined" && typeof ohaPassportHub === "undefined") {
        window.ohaPassportHub = ohaActorPassport;
    }
    'use strict';

    // Main Actor Passport Manager
    window.OHAActorPassport = {
        
        // Configuration
        config: {
            scanner: null,
            scannerActive: false,
            cameraStream: null,
            currentProfile: null,
            qrRefreshInterval: null,
            facingMode: 'environment',   // start with rear camera
            lastScanned: null            // debounce — prevent double-processing same QR
        },

        // Initialize Actor Passport system
        init: function() {
            console.log('OHA Passport Hub v1.0.0 initializing...');
            
            this.bindEvents();
            this.loadPassportCard();
            this.setupTabs();
            this.checkEventStatus();
            
            // Load QR code library if needed
            this.loadQRLibraries();
        },

        // Bind event handlers
        bindEvents: function() {
            
            // Profile form handling
            $(document).on('submit', '#actor-profile-form', this.handleProfileSubmit.bind(this));
            $(document).on('click', '#edit-profile', this.showEditForm.bind(this));
            $(document).on('click', '#cancel-edit', this.hideEditForm.bind(this));
            
            // QR code handling
            $(document).on('click', '#show-qr', this.showQRCode.bind(this));
            $(document).on('click', '#refresh-qr', this.refreshQRCode.bind(this));
            
            // Scanner handling
            $(document).on('click', '#start-scan', this.startScanner.bind(this));
            $(document).on('click', '#stop-scan', this.stopScanner.bind(this));
            $(document).on('click', '#switch-camera', this.switchCamera.bind(this));
            $(document).on('click', '#process-manual', this.processManualQR.bind(this));
            
            // Character bio counter
            $(document).on('input', '#character_bio', this.updateBioCounter.bind(this));
            
            // Tab navigation (extends v3.3.0)
            $(document).on('click', '.oha-nav-btn', this.handleTabClick.bind(this));
            
            // Load stamps when tab becomes active
            $(document).on('click', '[data-tab="stamps"]', this.loadMyStamps.bind(this));
            
            // Auto-refresh QR codes periodically
            this.startQRRefreshTimer();
        },

        // Setup tab navigation
        setupTabs: function() {
            // Show first tab by default
            $('.oha-tab-content').hide();
            $('.oha-tab-content.active').show();
        },

        // Handle tab clicks
        handleTabClick: function(e) {
            e.preventDefault();
            
            var $btn = $(e.currentTarget);
            var tabName = $btn.data('tab');
            
            // Update active states
            $('.oha-nav-btn').removeClass('active');
            $btn.addClass('active');
            
            $('.oha-tab-content').removeClass('active').hide();
            $('#' + tabName + '-tab').addClass('active').show();
            
            // Load content for specific tabs
            if (tabName === 'passport') {
                this.loadPassportCard();
            } else if (tabName === 'scanner') {
                this.loadScanner();
            } else if (tabName === 'stamps') {
                this.loadMyStamps();
            }
        },

        // Load passport card
        loadPassportCard: function() {
            // PHP already renders the login prompt for guests; skip the AJAX call
            if (typeof ohaPassportHub !== 'undefined' && !ohaPassportHub.is_logged_in) {
                return;
            }
            $.ajax({
                url: ohaPassportHub.ajax_url,
                type: 'GET',
                data: {
                    action: 'oha_get_actor_profile',
                    nonce: ohaPassportHub.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.has_profile) {
                            this.displayProfileCard(response.data.profile);
                        } else {
                            this.showCreateProfileForm();
                        }
                    } else {
                        this.showError('Failed to load profile: ' + response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Network error loading profile');
                }.bind(this)
            });
        },

        // Display profile card
        displayProfileCard: function(profile) {
            this.config.currentProfile = profile;
            
            var html = `
                <div class="passport-card-display">
                    <div class="passport-header">
                        <h4>${this.escapeHtml(profile.character_name)}</h4>
                        ${profile.character_type ? `<span class="character-type">${this.escapeHtml(profile.character_type)}</span>` : ''}
                    </div>
                    
                    ${profile.haunt_name ? `
                        <div class="passport-haunt">
                            <strong>Works at:</strong> ${this.escapeHtml(profile.haunt_name)}
                        </div>
                    ` : ''}
                    
                    ${profile.character_bio ? `
                        <div class="passport-bio">
                            <p>${this.escapeHtml(profile.character_bio)}</p>
                        </div>
                    ` : ''}
                    
                    <div class="passport-stats">
                        <div class="stat-item">
                            <span class="stat-value">${profile.stamps_earned || 0}</span>
                            <span class="stat-label">Badge Stamps</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${profile.created_at ? this.formatDate(profile.created_at) : 'Today'}</span>
                            <span class="stat-label">Member Since</span>
                        </div>
                    </div>
                    
                    <div class="passport-actions">
                        <button id="edit-profile" class="oha-btn-secondary">✏️ Edit Profile</button>
                        <button id="show-qr" class="oha-btn-primary">📱 Show QR Code</button>
                    </div>
                </div>
            `;
            
            $('#passport-card').html(html);
            $('#passport-form').hide();
            $('#qr-code-section').hide();
            
            // Pre-fill form for editing
            this.fillEditForm(profile);
        },

        // Show create profile form
        showCreateProfileForm: function() {
            $('#passport-card').html(`
                <div class="no-passport">
                    <div class="empty-icon">🎭</div>
                    <h4>Create Your Actor Passport</h4>
                    <p>Start networking with other actors at OHA events!</p>
                    <button id="create-profile" class="oha-btn-primary">Create Passport</button>
                </div>
            `);
            
            $(document).on('click', '#create-profile', function() {
                this.showEditForm();
            }.bind(this));
        },

        // Show edit form
        showEditForm: function() {
            $('#passport-form').show();
            $('#qr-code-section').hide();
            
            // Focus first input
            $('#character_name').focus();
        },

        // Hide edit form
        hideEditForm: function() {
            $('#passport-form').hide();
            if (this.config.currentProfile) {
                this.displayProfileCard(this.config.currentProfile);
            }
        },

        // Fill edit form with current profile data
        fillEditForm: function(profile) {
            $('#character_name').val(profile.character_name || '');
            $('#character_type').val(profile.character_type || '');
            $('#haunt_name').val(profile.haunt_name || '');
            $('#character_bio').val(profile.character_bio || '');
            
            this.updateBioCounter();
        },

        // Handle profile form submission
        handleProfileSubmit: function(e) {
            e.preventDefault();
            
            var $form = $('#actor-profile-form');
            var $submitBtn = $form.find('button[type="submit"]');
            var $btnText = $submitBtn.find('.btn-text');
            var $btnLoading = $submitBtn.find('.btn-loading');
            
            // Show loading state
            $btnText.hide();
            $btnLoading.show();
            $submitBtn.prop('disabled', true);
            
            var formData = {
                action: this.config.currentProfile ? 'oha_update_actor_profile' : 'oha_create_actor_profile',
                nonce: ohaPassportHub.nonce,
                character_name: $('#character_name').val().trim(),
                character_type: $('#character_type').val(),
                haunt_name: $('#haunt_name').val().trim(),
                character_bio: $('#character_bio').val().trim()
            };
            
            $.ajax({
                url: ohaPassportHub.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        this.showSuccess(response.data.message);
                        this.config.currentProfile = response.data.profile;
                        this.displayProfileCard(response.data.profile);
                        $('#passport-form').hide();
                        OHAPassportBadges.evaluate();
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Network error saving profile');
                }.bind(this),
                complete: function() {
                    // Reset button state
                    $btnText.show();
                    $btnLoading.hide();
                    $submitBtn.prop('disabled', false);
                }.bind(this)
            });
        },

        // Update bio character counter
        updateBioCounter: function() {
            var currentLength = $('#character_bio').val().length;
            $('#bio-count').text(currentLength);
            
            if (currentLength > 450) {
                $('#bio-count').css('color', '#ff6b6b');
            } else {
                $('#bio-count').css('color', '#666');
            }
        },

        // Show QR Code
        showQRCode: function() {
            if (!this.config.currentProfile) {
                this.showError('Please create a profile first');
                return;
            }
            
            $('#qr-code-section').show();
            
            $.ajax({
                url: ohaPassportHub.ajax_url,
                type: 'GET',
                data: {
                    action: 'oha_get_qr_data',
                    nonce: ohaPassportHub.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.displayQRCode(response.data);
                    } else {
                        this.showError('Failed to load QR code: ' + response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Network error loading QR code');
                }.bind(this)
            });
        },

        // Display QR Code
        displayQRCode: function(qrData) {
            var html = `
                <div class="qr-display-card">
                    <img src="${qrData.qr_url}" alt="QR Code" class="qr-image">
                    <p class="qr-character">${this.escapeHtml(qrData.character_name)}</p>
                    <p class="qr-expires">Expires: ${this.formatDateTime(qrData.expires_at)}</p>
                    ${qrData.was_refreshed ? '<p class="qr-refreshed">✅ QR Code automatically refreshed</p>' : ''}
                </div>
            `;
            
            $('#qr-display').html(html);
        },

        // Refresh QR Code
        refreshQRCode: function() {
            $.ajax({
                url: ohaPassportHub.ajax_url,
                type: 'POST',
                data: {
                    action: 'oha_generate_qr_code',
                    nonce: ohaPassportHub.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showSuccess('QR code refreshed!');
                        this.showQRCode(); // Reload QR display
                    } else {
                        this.showError('Failed to refresh QR code: ' + response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Network error refreshing QR code');
                }.bind(this)
            });
        },

        // Load QR Scanner
        loadScanner: function() {
            this.checkEventStatus();
        },

        // Check event status
        checkEventStatus: function() {
            $.ajax({
                url: ohaPassportHub.ajax_url,
                type: 'GET',
                data: {
                    action: 'oha_check_event_active',
                    nonce: ohaPassportHub.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.displayEventStatus(response.data);
                    }
                }.bind(this)
            });
        },

        // Display event status
        displayEventStatus: function(eventData) {
            var statusClass = eventData.is_active ? 'event-active' : 'event-inactive';
            var statusIcon = eventData.is_active ? '✅' : '⏸️';
            
            var html = `
                <div class="event-status ${statusClass}">
                    <p>${statusIcon} ${eventData.message}</p>
                    ${eventData.is_active ? '' : '<p><small>Badge trading is only available during OHA events</small></p>'}
                </div>
            `;
            
            $('#event-status').html(html);
            
            // Enable/disable scanner based on event status
            if (eventData.is_active) {
                $('#start-scan').prop('disabled', false).text('Start Scanning');
            } else {
                $('#start-scan').prop('disabled', true).text('Event Not Active');
                this.stopScanner();
            }
        },

        // ─────────────────────────────────────────────────────────────────────
        // QR Library detection & initialisation
        // Strategy:
        //   1. BarcodeDetector API  — Chrome 88+, Android Chrome, Edge (fastest)
        //   2. jsQR (bundled)       — iOS Safari PWA, Firefox, older Android
        //   3. Manual text entry    — always available as last resort
        // ─────────────────────────────────────────────────────────────────────
        loadQRLibraries: function() {
            var self = this;

            // Camera availability check
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                this.config.cameraSupported = true;
            } else {
                this.config.cameraSupported = false;
                $('#scanner-interface').append('<p class="camera-warning">⚠️ Camera not available on this device — use the manual code entry below.</p>');
            }

            // BarcodeDetector availability check (Chrome / Android)
            if ('BarcodeDetector' in window) {
                BarcodeDetector.getSupportedFormats().then(function(formats) {
                    if (formats.includes('qr_code')) {
                        self.config.barcodeDetector = new BarcodeDetector({ formats: ['qr_code'] });
                        self.config.decoderType = 'BarcodeDetector';
                    } else {
                        self.config.decoderType = 'jsQR';
                    }
                }).catch(function() {
                    self.config.decoderType = 'jsQR';
                });
            } else {
                // jsQR is bundled and loaded via wp_enqueue_script dependency
                this.config.decoderType = 'jsQR';
            }

            // Warn if neither camera nor jsQR
            if (!this.config.cameraSupported) {
                this.config.decoderType = 'manual';
            }
        },

        // Start QR scanner — opens camera then calls appropriate decode path
        startScanner: function() {
            if (!this.config.cameraSupported) {
                this.showError('Camera not supported on this device — use manual entry below');
                return;
            }

            var self = this;

            navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: self.config.facingMode,
                    width:  { ideal: 640 },
                    height: { ideal: 480 }
                }
            })
            .then(function(stream) {
                self.config.cameraStream  = stream;
                self.config.scannerActive = true;

                var video = document.getElementById('camera-preview');
                video.srcObject = stream;
                video.setAttribute('playsinline', true); // iOS requires this
                video.play();

                $('#start-scan').hide();
                $('#stop-scan, #switch-camera').show();
                $('#camera-container').addClass('active');

                self.showSuccess('Camera active — aim at a badge QR code.');
                self.startScanLoop();
            })
            .catch(function(err) {
                var msg = err.name === 'NotAllowedError'
                    ? 'Camera permission denied. Please allow camera access and try again.'
                    : 'Unable to access camera: ' + err.message;
                self.showError(msg);
            });
        },

        // Stop QR scanner and release camera
        stopScanner: function() {
            this.config.scannerActive = false;

            if (this.config.cameraStream) {
                this.config.cameraStream.getTracks().forEach(function(track) { track.stop(); });
                this.config.cameraStream = null;
            }

            if (this.config.scanCanvas) {
                this.config.scanCanvas = null;
                this.config.scanCtx    = null;
            }

            $('#stop-scan, #switch-camera').hide();
            $('#start-scan').show();
            $('#camera-container').removeClass('active');

            var video = document.getElementById('camera-preview');
            if (video) { video.srcObject = null; }
        },

        // Toggle between rear (environment) and front (user) camera
        switchCamera: function() {
            // Flip facing mode
            this.config.facingMode = (this.config.facingMode === 'environment') ? 'user' : 'environment';

            // Stop current stream then restart with new facing mode
            if (this.config.cameraStream) {
                this.config.cameraStream.getTracks().forEach(function(track) { track.stop(); });
                this.config.cameraStream = null;
            }
            this.config.scannerActive = false;
            this.startScanner();
        },

        // ─────────────────────────────────────────────────────────────────────
        // Scan loop — runs on requestAnimationFrame for performance
        // Captures a video frame to a hidden canvas then decodes with
        // BarcodeDetector or jsQR.  Throttled to max ~10fps to keep CPU light
        // on mobile (100ms delay between decode attempts).
        // ─────────────────────────────────────────────────────────────────────
        startScanLoop: function() {
            if (!this.config.scannerActive) { return; }

            var self  = this;
            var video = document.getElementById('camera-preview');

            // Wait until video has real dimensions before capturing
            if (!video || video.readyState < 2 || video.videoWidth === 0) {
                setTimeout(function() { self.startScanLoop(); }, 200);
                return;
            }

            // Create / reuse offscreen canvas
            if (!self.config.scanCanvas) {
                self.config.scanCanvas = document.createElement('canvas');
                self.config.scanCtx    = self.config.scanCanvas.getContext('2d');
            }

            var canvas = self.config.scanCanvas;
            var ctx    = self.config.scanCtx;
            canvas.width  = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            // ── BarcodeDetector path ──────────────────────────────────────
            if (self.config.decoderType === 'BarcodeDetector' && self.config.barcodeDetector) {
                self.config.barcodeDetector.detect(canvas)
                    .then(function(codes) {
                        if (codes.length > 0 && self.config.scannerActive) {
                            self.onQRDecoded(codes[0].rawValue);
                        } else {
                            setTimeout(function() { self.startScanLoop(); }, 100);
                        }
                    })
                    .catch(function() {
                        setTimeout(function() { self.startScanLoop(); }, 100);
                    });

            // ── jsQR path ─────────────────────────────────────────────────
            } else if (typeof jsQR === 'function') {
                var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                var code      = jsQR(imageData.data, imageData.width, imageData.height, {
                    inversionAttempts: 'dontInvert'
                });

                if (code && self.config.scannerActive) {
                    self.onQRDecoded(code.data);
                } else {
                    setTimeout(function() { self.startScanLoop(); }, 100);
                }

            // ── Fallback — jsQR not yet loaded; keep waiting ──────────────
            } else {
                setTimeout(function() { self.startScanLoop(); }, 300);
            }
        },

        // Called once a QR value is successfully decoded from camera
        onQRDecoded: function(rawValue) {
            if (!rawValue || this.config.lastScanned === rawValue) { return; }
            this.config.lastScanned = rawValue;   // debounce same code

            this.stopScanner();
            this.showSuccess('QR code detected — processing...');
            this.processQRScan(rawValue);

            // Allow re-scanning after 3 seconds
            var self = this;
            setTimeout(function() { self.config.lastScanned = null; }, 3000);
        },

        // Process manual QR input
        processManualQR: function() {
            var qrCode = $('#manual-qr').val().trim();
            
            if (!qrCode) {
                this.showError('Please enter a QR code');
                return;
            }
            
            this.processQRScan(qrCode);
        },

        // Process QR scan result
        processQRScan: function(qrHash) {
            
            $.ajax({
                url: ohaPassportHub.ajax_url,
                type: 'POST',
                data: {
                    action: 'oha_process_qr_scan',
                    qr_hash: qrHash,
                    nonce: ohaPassportHub.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.displayScanSuccess(response.data);
                        this.stopScanner();
                        OHAPassportBadges.evaluate();
                    } else {
                        this.displayScanError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Network error processing QR scan');
                }.bind(this)
            });
        },

        // Display scan success
        displayScanSuccess: function(data) {
            var passportUrl = (typeof ohaPassportHub !== 'undefined' && ohaPassportHub.passport_page_url)
                ? ohaPassportHub.passport_page_url
                : '/convention/passport/';
            var html = `
                <div class="scan-result success">
                    <div class="result-icon">&#10003;</div>
                    <h4>Stamp Collected!</h4>
                    <p>You traded badges with <strong>${this.escapeHtml(data.target_character)}</strong></p>
                    ${data.target_haunt ? `<p>From: ${this.escapeHtml(data.target_haunt)}</p>` : ''}
                    <p>Total stamps: <strong>${data.new_total}</strong></p>
                    <div class="oha-scan-after-actions">
                        <button class="oha-btn-secondary" onclick="OHAActorPassport.clearScanResults()">Scan More</button>
                        <a href="${passportUrl}" class="oha-btn-primary oha-scan-passport-link">View My Passport</a>
                    </div>
                </div>
            `;

            $('#scan-results').html(html).show();
        },

        // Display scan error
        displayScanError: function(message) {
            var html = `
                <div class="scan-result error">
                    <div class="result-icon">❌</div>
                    <h4>Scan Failed</h4>
                    <p>${this.escapeHtml(message)}</p>
                    <button class="oha-btn-secondary" onclick="OHAActorPassport.clearScanResults()">Try Again</button>
                </div>
            `;
            
            $('#scan-results').html(html).show();
        },

        // Clear scan results
        clearScanResults: function() {
            $('#scan-results').hide().html('');
            $('#manual-qr').val('');
        },

        // Load my stamps
        loadMyStamps: function() {
            $.ajax({
                url: ohaPassportHub.ajax_url,
                type: 'GET',
                data: {
                    action: 'oha_get_my_stamps',
                    nonce: ohaPassportHub.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.displayMyStamps(response.data);
                    } else {
                        this.showError('Failed to load stamps: ' + response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError('Network error loading stamps');
                }.bind(this)
            });
        },

        // Display my stamps
        displayMyStamps: function(data) {
            
            // Display statistics
            if (data.stats) {
                var statsHtml = `
                    <div class="stamp-stats-grid">
                        <div class="stat-card">
                            <div class="stat-value">${data.stats.total_stamps}</div>
                            <div class="stat-label">Total Stamps</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${data.stats.unique_partners}</div>
                            <div class="stat-label">Trading Partners</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${data.stats.different_haunts}</div>
                            <div class="stat-label">Haunts Connected</div>
                        </div>
                    </div>
                `;
                
                $('#stamp-stats').html(statsHtml);
            }
            
            // Display stamps list
            if (data.stamps && data.stamps.length > 0) {
                var stampsHtml = '';
                
                data.stamps.forEach(function(stamp) {
                    // For vendor/location stamps, partner_name may be null; use location column instead
                    var displayName = stamp.partner_name || stamp.location || '—';
                    stampsHtml += `
                        <div class="stamp-item">
                            <div class="stamp-header">
                                <span class="stamp-partner">${this.escapeHtml(displayName)}</span>
                                <span class="stamp-date">${this.formatDate(stamp.created_at)}</span>
                            </div>
                            ${stamp.partner_haunt ? `<div class="stamp-haunt">${this.escapeHtml(stamp.partner_haunt)}</div>` : ''}
                            <div class="stamp-type">${this.formatStampType(stamp.stamp_type)}</div>
                        </div>
                    `;
                }.bind(this));
                
                $('#stamps-list').html(stampsHtml);
                $('#empty-stamps').hide();
            } else {
                $('#stamps-list').html('');
                $('#empty-stamps').show();
            }
        },

        // Start QR refresh timer
        startQRRefreshTimer: function() {
            // Refresh QR codes every hour if displayed
            this.config.qrRefreshInterval = setInterval(function() {
                if ($('#qr-code-section').is(':visible')) {
                    this.showQRCode();
                }
            }.bind(this), 60 * 60 * 1000); // 1 hour
        },

        // Utility functions
        escapeHtml: function(text) {
            if (!text) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        formatDate: function(dateStr) {
            if (!dateStr) return '';
            var date = new Date(dateStr);
            return date.toLocaleDateString();
        },

        formatDateTime: function(dateStr) {
            if (!dateStr) return '';
            var date = new Date(dateStr);
            return date.toLocaleString();
        },

        formatStampType: function(type) {
            var types = {
                'trade':              '🤝 Badge Trade',
                'workshop':           '🎓 Workshop',
                'volunteer':          '🙋 Volunteer',
                'special':            '⭐ Special',
                'vendor':             '🏪 Vendor Check-in',
                'location':           '📍 Scavenger Find',
                'scavenger_complete': '🏆 Hunt Complete!',
            };
            return types[type] || '🎭 ' + type;
        },

        // Show success message
        showSuccess: function(message) {
            this.showMessage(message, 'success');
        },

        // Show error message  
        showError: function(message) {
            this.showMessage(message, 'error');
        },

        // Show message
        showMessage: function(message, type) {
            var $msg = $('<div class="oha-message oha-message-' + type + '">' + this.escapeHtml(message) + '</div>');
            
            // Remove existing messages
            $('.oha-message').remove();
            
            // Add new message
            $('body').append($msg);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                $msg.fadeOut(300, function() {
                    $msg.remove();
                });
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof ohaPassportHub !== 'undefined') {
            window.OHAActorPassport.init();
        }
        // Init new scanner types if their containers are on the page
        OHAScannerFactory.init();
    });

})(jQuery);

// ─────────────────────────────────────────────────────────────────────────────
// OHAScannerFactory
// Creates and boots scanner instances for vendor, hunt, url, and smart scanners.
// Each scanner shares the same camera/jsQR infrastructure but routes decoded
// QR values differently based on data-mode attribute.
// ─────────────────────────────────────────────────────────────────────────────
(function($) {
    'use strict';

    /**
     * Generic camera scanner that can be re-used by any container.
     * Accepts a config object:
     *   containerId   — '#oha-vendor-scanner'
     *   videoId       — '#vendor-camera-preview'
     *   startBtnId    — '#vendor-start-scan'
     *   stopBtnId     — '#vendor-stop-scan'
     *   switchBtnId   — '#vendor-switch-cam'
     *   resultsId     — '#vendor-scan-results'
     *   mode          — 'vendor' | 'location' | 'url' | 'smart'
     *   onDecoded     — function(rawValue) called once a QR is read
     */
    function OHAScanner(cfg) {
        this.cfg           = cfg;
        this.cameraStream  = null;
        this.scannerActive = false;
        this.facingMode    = 'environment';
        this.lastScanned   = null;
        this.scanCanvas    = null;
        this.scanCtx       = null;
        this.decoderType   = 'jsQR';
        this.barcodeDetector = null;
    }

    OHAScanner.prototype.init = function() {
        var self = this;
        var cfg  = this.cfg;

        // Camera support check
        this.cameraSupported = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

        // BarcodeDetector check
        if ('BarcodeDetector' in window) {
            BarcodeDetector.getSupportedFormats().then(function(fmts) {
                if (fmts.includes('qr_code')) {
                    self.barcodeDetector = new BarcodeDetector({ formats: ['qr_code'] });
                    self.decoderType = 'BarcodeDetector';
                }
            }).catch(function() {});
        }

        $(document).on('click', cfg.startBtnId,  function() { self.startScanner(); });
        $(document).on('click', cfg.stopBtnId,   function() { self.stopScanner(); });
        $(document).on('click', cfg.switchBtnId, function() { self.switchCamera(); });
    };

    OHAScanner.prototype.startScanner = function() {
        var self = this;
        if (!this.cameraSupported) {
            this.showResult('⚠️ Camera not available on this device.', 'error');
            return;
        }
        navigator.mediaDevices.getUserMedia({
            video: { facingMode: self.facingMode, width: { ideal: 640 }, height: { ideal: 480 } }
        }).then(function(stream) {
            self.cameraStream  = stream;
            self.scannerActive = true;
            var video = document.querySelector(self.cfg.videoId);
            video.srcObject = stream;
            video.setAttribute('playsinline', true);
            video.play();
            $(self.cfg.startBtnId).hide();
            $(self.cfg.stopBtnId + ',' + self.cfg.switchBtnId).show();
            $(self.cfg.videoId).closest('.camera-container').addClass('active');
            self.startScanLoop();
        }).catch(function(err) {
            var msg = err.name === 'NotAllowedError'
                ? '📷 Camera permission denied. Tap the camera icon in your browser bar to allow access.'
                : '📷 Cannot access camera: ' + err.message;
            self.showResult(msg, 'error');
        });
    };

    OHAScanner.prototype.stopScanner = function() {
        this.scannerActive = false;
        if (this.cameraStream) {
            this.cameraStream.getTracks().forEach(function(t) { t.stop(); });
            this.cameraStream = null;
        }
        this.scanCanvas = null;
        this.scanCtx    = null;
        $(this.cfg.stopBtnId + ',' + this.cfg.switchBtnId).hide();
        $(this.cfg.startBtnId).show();
        var video = document.querySelector(this.cfg.videoId);
        if (video) video.srcObject = null;
        $(this.cfg.videoId).closest('.camera-container').removeClass('active');
    };

    OHAScanner.prototype.switchCamera = function() {
        this.facingMode = (this.facingMode === 'environment') ? 'user' : 'environment';
        if (this.cameraStream) {
            this.cameraStream.getTracks().forEach(function(t) { t.stop(); });
            this.cameraStream = null;
        }
        this.scannerActive = false;
        this.startScanner();
    };

    OHAScanner.prototype.startScanLoop = function() {
        if (!this.scannerActive) return;
        var self  = this;
        var video = document.querySelector(self.cfg.videoId);
        if (!video || video.readyState < 2 || video.videoWidth === 0) {
            setTimeout(function() { self.startScanLoop(); }, 200);
            return;
        }
        if (!self.scanCanvas) {
            self.scanCanvas = document.createElement('canvas');
            self.scanCtx    = self.scanCanvas.getContext('2d');
        }
        var canvas = self.scanCanvas;
        var ctx    = self.scanCtx;
        canvas.width  = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        if (self.decoderType === 'BarcodeDetector' && self.barcodeDetector) {
            self.barcodeDetector.detect(canvas).then(function(codes) {
                if (codes.length > 0 && self.scannerActive) {
                    self.onQRDecoded(codes[0].rawValue);
                } else {
                    setTimeout(function() { self.startScanLoop(); }, 100);
                }
            }).catch(function() {
                setTimeout(function() { self.startScanLoop(); }, 100);
            });
        } else if (typeof jsQR === 'function') {
            var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            var code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });
            if (code && self.scannerActive) {
                self.onQRDecoded(code.data);
            } else {
                setTimeout(function() { self.startScanLoop(); }, 100);
            }
        } else {
            setTimeout(function() { self.startScanLoop(); }, 300);
        }
    };

    OHAScanner.prototype.onQRDecoded = function(rawValue) {
        if (!rawValue || this.lastScanned === rawValue) return;
        this.lastScanned = rawValue;
        var self = this;
        this.stopScanner();
        if (typeof this.cfg.onDecoded === 'function') {
            this.cfg.onDecoded(rawValue);
        }
        setTimeout(function() { self.lastScanned = null; }, 3000);
    };

    OHAScanner.prototype.showResult = function(msg, type) {
        var html = '<div class="scan-result ' + (type || 'info') + '"><p>' +
            (typeof msg === 'string' ? msg.replace(/</g,'&lt;').replace(/>/g,'&gt;') : msg) +
            '</p></div>';
        $(this.cfg.resultsId).html(html).show();
    };

    OHAScanner.prototype.clearResult = function() {
        $(this.cfg.resultsId).hide().html('');
    };

    // ─────────────────────────────────────────────────────────────────────
    // QR routing — shared by all scanner modes
    // ─────────────────────────────────────────────────────────────────────
    function routeQRValue(rawValue, mode, scanner) {
        var val = rawValue.trim();

        // URL — open directly
        if (/^https?:\/\//i.test(val)) {
            if (mode === 'url' || mode === 'smart') {
                scanner.showResult('🔗 Opening link…', 'success');
                setTimeout(function() { window.location.href = val; }, 600);
            } else {
                scanner.showResult('📎 URL found: <a href="' + encodeURI(val) + '" target="_blank">' + val + '</a>', 'success');
            }
            return;
        }

        // Vendor check-in
        if (val.startsWith('VND:')) {
            var vendorId = parseInt(val.substring(4), 10);
            if (!vendorId) {
                scanner.showResult('❌ Invalid vendor QR code', 'error');
                return;
            }
            if (mode === 'url') {
                scanner.showResult('⚠️ This is a vendor QR — use the Vendor Scanner or Smart Scanner instead.', 'error');
                return;
            }
            processVendorScan(vendorId, scanner);
            return;
        }

        // Scavenger location
        if (val.startsWith('LOC:')) {
            var locHash = val.substring(4);
            if (!locHash) {
                scanner.showResult('❌ Invalid location QR code', 'error');
                return;
            }
            if (mode === 'url' || mode === 'vendor') {
                scanner.showResult('⚠️ This is a scavenger hunt marker — use the Scavenger Hunt scanner.', 'error');
                return;
            }
            processLocationScan(locHash, scanner);
            return;
        }

        // Actor badge trade (64-char hex or any unrecognised value)
        if (mode === 'trade' || mode === 'smart') {
            // Delegate to the existing OHAActorPassport.processQRScan, but show result in scanner's div
            if (typeof ohaPassportHub !== 'undefined') {
                processActorScan(val, scanner);
            } else {
                scanner.showResult('❌ Cannot process QR code — Passport Hub not loaded', 'error');
            }
            return;
        }

        scanner.showResult('⚠️ Unrecognised QR code format for this scanner mode', 'error');
    }

    function processVendorScan(vendorId, scanner) {
        scanner.showResult('⏳ Checking in at vendor…', 'info');
        $.ajax({
            url:  ohaPassportHub.ajax_url,
            type: 'POST',
            data: {
                action:    'oha_scan_vendor_qr',
                vendor_id: vendorId,
                nonce:     ohaPassportHub.nonce,
            },
            success: function(resp) {
                if (!resp.success) {
                    scanner.showResult('❌ ' + resp.data, 'error');
                    return;
                }
                var d = resp.data;
                var html =
                    '<div class="oha-vendor-result">' +
                        '<div class="vendor-result-name">🏪 ' + escHtml(d.vendor.vendor_name) + '</div>' +
                        (d.vendor.booth_number ? '<div class="vendor-result-booth">Booth ' + escHtml(d.vendor.booth_number) + '</div>' : '') +
                        (d.vendor.description  ? '<div class="vendor-result-desc">'  + escHtml(d.vendor.description)  + '</div>' : '') +
                        (d.vendor.website_url  ? '<div class="vendor-result-link"><a href="' + encodeURI(d.vendor.website_url) + '" target="_blank">🔗 Visit Website</a></div>' : '') +
                        '<div class="vendor-stamp-msg ' + (d.stamp_recorded ? 'stamp-success' : 'stamp-already') + '">' + escHtml(d.message) + '</div>' +
                        '<button class="oha-btn-secondary oha-scan-again-btn" style="margin-top:12px;">Scan Another</button>' +
                    '</div>';
                scanner.showResult(html, d.stamp_recorded ? 'success' : 'info');
            },
            error: function() {
                scanner.showResult('❌ Network error — please try again', 'error');
            },
        });
    }

    function processLocationScan(locHash, scanner) {
        scanner.showResult('⏳ Recording location stamp…', 'info');
        $.ajax({
            url:  ohaPassportHub.ajax_url,
            type: 'POST',
            data: {
                action:   'oha_scan_location_qr',
                qr_hash:  locHash,
                nonce:    ohaPassportHub.nonce,
            },
            success: function(resp) {
                if (!resp.success) {
                    scanner.showResult('❌ ' + resp.data, 'error');
                    return;
                }
                var d    = resp.data;
                var prog = d.progress;
                var html =
                    '<div class="oha-location-result">' +
                        '<div class="location-result-name">📍 ' + escHtml(d.location.name) + '</div>' +
                        (d.location.description ? '<div class="location-result-desc">' + escHtml(d.location.description) + '</div>' : '') +
                        '<div class="location-stamp-msg ' + (d.stamp_recorded ? 'stamp-success' : 'stamp-already') + '">' + escHtml(d.message) + '</div>' +
                        '<div class="hunt-progress-mini">' +
                            '<div class="hunt-progress-bar"><div class="hunt-progress-fill" style="width:' + prog.percent + '%"></div></div>' +
                            '<div class="hunt-progress-label">' + prog.found + ' / ' + prog.total + ' locations found</div>' +
                        '</div>';

                if (d.completed) {
                    html += '<div class="hunt-completion-badge">🏆 ' + escHtml(d.completion_message) + '</div>';
                }

                html += '<button class="oha-btn-secondary oha-scan-again-btn" style="margin-top:12px;">Scan Another</button>' +
                    '</div>';

                scanner.showResult(html, d.stamp_recorded ? 'success' : 'info');

                // Refresh the hunt progress display if it exists on the page
                if (window.OHAScavengerHunt && typeof window.OHAScavengerHunt.loadProgress === 'function') {
                    window.OHAScavengerHunt.loadProgress();
                }
            },
            error: function() {
                scanner.showResult('❌ Network error — please try again', 'error');
            },
        });
    }

    function processActorScan(qrHash, scanner) {
        scanner.showResult('Processing badge trade…', 'info');
        var passportUrl = (typeof ohaPassportHub !== 'undefined' && ohaPassportHub.passport_page_url)
            ? ohaPassportHub.passport_page_url
            : '/convention/2026-convention/passport/';
        $.ajax({
            url:  ohaPassportHub.ajax_url,
            type: 'POST',
            data: {
                action:  'oha_process_qr_scan',
                qr_hash: qrHash,
                nonce:   ohaPassportHub.nonce,
            },
            success: function(resp) {
                if (!resp.success) {
                    scanner.showResult('Error: ' + resp.data, 'error');
                    return;
                }
                var d    = resp.data;
                var html =
                    '<div class="scan-result success">' +
                        '<div class="result-icon oha-stamp-check">&#10003;</div>' +
                        '<h4>Stamp Collected!</h4>' +
                        '<p>You traded badges with <strong>' + escHtml(d.target_character) + '</strong></p>' +
                        (d.target_haunt ? '<p>From: ' + escHtml(d.target_haunt) + '</p>' : '') +
                        '<p>Total stamps: <strong>' + d.new_total + '</strong></p>' +
                        '<div class="oha-scan-after-actions">' +
                            '<button class="oha-btn-secondary oha-scan-again-btn">Scan More</button>' +
                            '<a href="' + passportUrl + '" class="oha-btn-primary oha-scan-passport-link">View My Passport</a>' +
                        '</div>' +
                    '</div>';
                $(scanner.cfg.resultsId).html(html).show();
                OHAPassportBadges.evaluate();
            },
            error: function() {
                scanner.showResult('Network error — please try again', 'error');
            },
        });
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Badge unlock evaluation + toast
    // ─────────────────────────────────────────────────────────────────────
    window.OHAPassportBadges = {
        evaluate: function() {
            if (typeof ohaPassportHub === 'undefined') return;
            $.ajax({
                url:  ohaPassportHub.ajax_url,
                type: 'POST',
                data: {
                    action: 'oha_evaluate_badges',
                    nonce:  ohaPassportHub.nonce,
                },
                success: function(resp) {
                    if (!resp.success || !resp.data || !resp.data.newly_unlocked || !resp.data.newly_unlocked.length) return;
                    resp.data.newly_unlocked.forEach(function(badge) {
                        window.OHAPassportBadges.showBadgeToast(badge.badge_name);
                    });
                },
            });
        },

        showBadgeToast: function(badgeName) {
            var $toast = $('<div class="oha-badge-toast"></div>');
            $toast.text('Badge Unlocked: ' + badgeName);
            $('body').append($toast);
            // Force reflow so CSS transition fires
            void $toast[0].offsetWidth;
            $toast.addClass('oha-badge-toast-visible');
            setTimeout(function() {
                $toast.addClass('oha-badge-toast-hiding');
                setTimeout(function() { $toast.remove(); }, 400);
            }, 3000);
        },
    };

    // ─────────────────────────────────────────────────────────────────────
    // Scavenger Hunt progress display
    // ─────────────────────────────────────────────────────────────────────
    window.OHAScavengerHunt = {
        loadProgress: function() {
            var $wrap = $('#hunt-progress-wrap');
            var $list = $('#hunt-locations-list');
            if (!$wrap.length) return;

            var eventSlug = $('#oha-scavenger-hunt').data('event') || '';

            $.ajax({
                url:  ohaPassportHub.ajax_url,
                type: 'GET',
                data: { action: 'oha_get_scavenger_progress', event_slug: eventSlug, nonce: ohaPassportHub.nonce },
                success: function(resp) {
                    if (!resp.success) {
                        $wrap.html('<p style="color:#888;">Log in and create a passport to track your hunt progress.</p>');
                        return;
                    }
                    var d    = resp.data;
                    var prog = d.progress;

                    // Progress bar
                    var pct = prog.percent;
                    var barHtml =
                        '<div class="oha-hunt-progress">' +
                            (prog.is_complete ? '<div class="hunt-complete-banner">🏆 You completed the hunt!</div>' : '') +
                            '<div class="hunt-progress-bar"><div class="hunt-progress-fill" style="width:' + pct + '%"></div></div>' +
                            '<div class="hunt-progress-label">' + prog.found + ' / ' + prog.total + ' locations found' +
                                (prog.remaining > 0 ? ' — ' + prog.remaining + ' remaining' : '') +
                            '</div>' +
                        '</div>';
                    $wrap.html(barHtml);

                    // Locations list
                    if (d.locations && d.locations.length) {
                        var listHtml = '<ul class="oha-hunt-locations-list">';
                        d.locations.forEach(function(loc) {
                            if (loc.found) {
                                listHtml += '<li class="hunt-loc found"><span class="hunt-loc-icon">✅</span>' +
                                    '<span class="hunt-loc-name">' + escHtml(loc.name) + '</span></li>';
                            } else {
                                listHtml += '<li class="hunt-loc not-found"><span class="hunt-loc-icon">❓</span>' +
                                    '<span class="hunt-loc-hint">' + (loc.hint ? escHtml(loc.hint) : 'Keep looking…') + '</span></li>';
                            }
                        });
                        listHtml += '</ul>';
                        $list.html(listHtml);
                    }
                },
                error: function() {
                    $wrap.html('<p style="color:#888;">Could not load hunt progress.</p>');
                },
            });
        },
    };

    // ─────────────────────────────────────────────────────────────────────
    // Factory init — boots all scanner containers found on the page
    // ─────────────────────────────────────────────────────────────────────
    window.OHAScannerFactory = {
        init: function() {
            if (typeof ohaPassportHub === 'undefined') return;

            // Vendor scanner
            if ($('#oha-vendor-scanner').length) {
                var vs = new OHAScanner({
                    containerId: '#oha-vendor-scanner',
                    videoId:     '#vendor-camera-preview',
                    startBtnId:  '#vendor-start-scan',
                    stopBtnId:   '#vendor-stop-scan',
                    switchBtnId: '#vendor-switch-cam',
                    resultsId:   '#vendor-scan-results',
                    mode:        'vendor',
                    onDecoded:   function(v) { routeQRValue(v, 'vendor', vs); },
                });
                vs.init();
            }

            // Scavenger hunt scanner
            if ($('#oha-scavenger-hunt').length) {
                var hs = new OHAScanner({
                    containerId: '#oha-scavenger-hunt',
                    videoId:     '#hunt-camera-preview',
                    startBtnId:  '#hunt-start-scan',
                    stopBtnId:   '#hunt-stop-scan',
                    switchBtnId: '#hunt-switch-cam',
                    resultsId:   '#hunt-scan-results',
                    mode:        'location',
                    onDecoded:   function(v) { routeQRValue(v, 'location', hs); },
                });
                hs.init();
                // Load progress immediately
                window.OHAScavengerHunt.loadProgress();

                // Leaderboard placeholder
                var $lbList = $('#hunt-leaderboard-list');
                if ($lbList.length) {
                    $lbList.html('<p style="color:#888;">Leaderboard data visible in admin.</p>');
                }
            }

            // URL scanner
            if ($('#oha-url-scanner').length) {
                var us = new OHAScanner({
                    containerId: '#oha-url-scanner',
                    videoId:     '#url-camera-preview',
                    startBtnId:  '#url-start-scan',
                    stopBtnId:   '#url-stop-scan',
                    switchBtnId: '#url-switch-cam',
                    resultsId:   '#url-scan-results',
                    mode:        'url',
                    onDecoded:   function(v) { routeQRValue(v, 'url', us); },
                });
                us.init();
                // Manual URL open button
                $(document).on('click', '#url-manual-go', function() {
                    var url = $('#url-manual-input').val().trim();
                    if (/^https?:\/\//i.test(url)) {
                        window.location.href = url;
                    } else if (url) {
                        window.location.href = 'https://' + url;
                    }
                });
            }

            // Smart scanner
            if ($('#oha-smart-scanner').length) {
                var ss = new OHAScanner({
                    containerId: '#oha-smart-scanner',
                    videoId:     '#smart-camera-preview',
                    startBtnId:  '#smart-start-scan',
                    stopBtnId:   '#smart-stop-scan',
                    switchBtnId: '#smart-switch-cam',
                    resultsId:   '#smart-scan-results',
                    mode:        'smart',
                    onDecoded:   function(v) { routeQRValue(v, 'smart', ss); },
                });
                ss.init();
                // Manual input
                $(document).on('click', '#smart-process-manual', function() {
                    var val = $('#smart-manual-qr').val().trim();
                    if (val) { routeQRValue(val, 'smart', ss); }
                });
            }

            // "Scan Again" button — clears result and re-enables scan button
            $(document).on('click', '.oha-scan-again-btn', function() {
                var $res = $(this).closest('.oha-scan-results, .scan-result').parent();
                $res.hide().html('');
                // Show the Start button of whichever scanner is nearby
                $(this).closest('[data-mode]').find('[id$="-start-scan"]').show();
            });
        },
    };

})(jQuery);
