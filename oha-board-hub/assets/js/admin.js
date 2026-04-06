/**
 * OHA Board Hub Suite - Admin JavaScript
 *
 * @package OHA_Board_Hub
 * @since 1.5.0
 */

(function($) {
    'use strict';
    
    /**
     * Main admin object
     */
    const OHAAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initComponents();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Search form handler
            $('.oha-sa-search').on('submit', this.handleSearch.bind(this));
            
            // Action buttons
            $(document).on('click', '.oha-action-btn', this.handleAction.bind(this));
            
            // Form validation
            $('form').on('submit', this.validateForm.bind(this));
        },
        
        /**
         * Initialize components
         */
        initComponents: function() {
            // Add loading states
            this.initLoadingStates();
            
            // Initialize tooltips if available
            if (typeof $.fn.tooltip !== 'undefined') {
                $('[data-tooltip]').tooltip();
            }
        },
        
        /**
         * Handle search form submission
         */
        handleSearch: async function(e) {
            e.preventDefault();
            
            const form = $(e.currentTarget);
            const query = form.find('input[name="q"]').val().trim();
            const resultsContainer = $('.oha-sa-results');
            
            if (query.length < 2) {
                this.showNotice('error', ohaAdminData.strings.error || 'Search query must be at least 2 characters.');
                return;
            }
            
            try {
                resultsContainer.html('<p>' + (ohaAdminData.strings.searching || 'Searching...') + '</p>');
                
                const response = await fetch(ohaAdminData.restUrl + 'search', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': ohaAdminData.nonce
                    },
                    body: JSON.stringify({ query: query })
                });
                
                if (!response.ok) {
                    throw new Error('Search request failed');
                }
                
                const data = await response.json();
                this.renderSearchResults(data, resultsContainer);
                
            } catch (error) {
                console.error('Search error:', error);
                resultsContainer.html('<p class="error">' + (ohaAdminData.strings.error || 'Search failed. Please try again.') + '</p>');
            }
        },
        
        /**
         * Render search results
         */
        renderSearchResults: function(data, container) {
            if (!data || !data.results || data.results.length === 0) {
                container.html('<p><em>No results found.</em></p>');
                return;
            }
            
            // Results would be rendered here
            // This is a placeholder - the actual implementation depends on the search endpoint
            container.html('<p>Results loaded: ' + data.results.length + '</p>');
        },
        
        /**
         * Handle action button clicks
         */
        handleAction: async function(e) {
            e.preventDefault();
            
            const btn = $(e.currentTarget);
            const action = btn.data('action');
            const itemId = btn.data('item-id');
            const confirmMessage = btn.data('confirm');
            
            // Confirmation dialog
            if (confirmMessage && !confirm(confirmMessage)) {
                return;
            } else if (!confirmMessage && !confirm(ohaAdminData.strings.confirm || 'Are you sure?')) {
                return;
            }
            
            const originalText = btn.text();
            
            try {
                // Disable button and show loading
                btn.prop('disabled', true)
                   .addClass('oha-loading')
                   .text(ohaAdminData.strings.loading || 'Processing...');
                
                const response = await fetch(ohaAdminData.restUrl + 'action/' + action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': ohaAdminData.nonce
                    },
                    body: JSON.stringify({ 
                        item_id: itemId,
                        action: action
                    })
                });
                
                if (!response.ok) {
                    throw new Error('Action request failed');
                }
                
                const data = await response.json();
                
                if (data.success) {
                    this.showNotice('success', data.message || 'Action completed successfully.');
                    
                    // Trigger refresh if needed
                    if (data.refresh) {
                        setTimeout(() => window.location.reload(), 1000);
                    }
                } else {
                    this.showNotice('error', data.message || 'Action failed.');
                }
                
            } catch (error) {
                console.error('Action error:', error);
                this.showNotice('error', ohaAdminData.strings.error || 'An error occurred. Please try again.');
            } finally {
                // Re-enable button
                btn.prop('disabled', false)
                   .removeClass('oha-loading')
                   .text(originalText);
            }
        },
        
        /**
         * Validate form before submission
         */
        validateForm: function(e) {
            const form = $(e.currentTarget);
            
            // Check required fields
            let isValid = true;
            form.find('[required]').each(function() {
                const field = $(this);
                if (!field.val() || field.val().trim() === '') {
                    field.addClass('error');
                    isValid = false;
                } else {
                    field.removeClass('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                this.showNotice('error', 'Please fill in all required fields.');
            }
            
            return isValid;
        },
        
        /**
         * Initialize loading states
         */
        initLoadingStates: function() {
            // Add loading class to forms on submit
            $('form').on('submit', function() {
                const submitBtn = $(this).find('[type="submit"]');
                submitBtn.addClass('oha-loading');
            });
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            const noticeClass = 'oha-notice oha-notice-' + type;
            const notice = $('<div>')
                .addClass(noticeClass)
                .append($('<p>').text(message))
                .hide();
            
            // Insert after page title or at top of content
            const target = $('.wrap h1').first();
            if (target.length) {
                target.after(notice);
            } else {
                $('.oha-admin-content').prepend(notice);
            }
            
            notice.slideDown();
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notice.slideUp(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        /**
         * Show loading overlay
         */
        showLoading: function() {
            if ($('#oha-loading-overlay').length === 0) {
                $('body').append(
                    '<div id="oha-loading-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:999999;display:flex;align-items:center;justify-content:center;">' +
                    '<div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.3);">' +
                    '<p style="margin:0;font-size:16px;">' + (ohaAdminData.strings.loading || 'Loading...') + '</p>' +
                    '</div></div>'
                );
            }
        },
        
        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('#oha-loading-overlay').fadeOut(function() {
                $(this).remove();
            });
        }
    };
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        OHAAdmin.init();
    });
    
    // Export for global access
    window.OHAAdmin = OHAAdmin;
    
})(jQuery);
