/**
 * OHA Board Hub Suite - Frontend JavaScript
 *
 * @package OHA_Board_Hub
 * @since 1.5.0
 */

(function($) {
    'use strict';
    
    /**
     * Main frontend object
     */
    const OHAFrontend = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Form submissions
            $('.oha-form').on('submit', this.handleFormSubmit.bind(this));
            
            // Form validation
            $('.oha-form input, .oha-form select').on('blur', this.validateField.bind(this));
        },
        
        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            const form = $(e.currentTarget);
            
            // Validate all fields
            let isValid = true;
            form.find('[required]').each(function() {
                if (!OHAFrontend.validateField.call(this)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                this.showMessage('error', ohaData.strings.error || 'Please fill in all required fields correctly.');
                
                // Scroll to first error
                const firstError = form.find('.oha-form-field-error').first();
                if (firstError.length) {
                    $('html, body').animate({
                        scrollTop: firstError.offset().top - 100
                    }, 300);
                }
                
                return false;
            }
            
            // Show loading state
            const submitBtn = form.find('[type="submit"]');
            submitBtn.prop('disabled', true).addClass('oha-loading');
        },
        
        /**
         * Validate individual field
         */
        validateField: function() {
            const field = $(this);
            const value = field.val().trim();
            const fieldWrap = field.closest('.oha-form-field');
            let isValid = true;
            let errorMessage = '';
            
            // Clear previous errors
            fieldWrap.removeClass('oha-form-field-error');
            fieldWrap.find('.oha-error-message').remove();
            
            // Required field
            if (field.prop('required') && !value) {
                isValid = false;
                errorMessage = 'This field is required.';
            }
            
            // Email validation
            if (isValid && field.attr('type') === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid email address.';
                }
            }
            
            // Phone validation (basic)
            if (isValid && field.attr('type') === 'tel' && value) {
                const phoneRegex = /^[\d\s\-\(\)\+]+$/;
                if (!phoneRegex.test(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid phone number.';
                }
            }
            
            // Min length
            const minLength = field.attr('minlength');
            if (isValid && minLength && value.length < parseInt(minLength)) {
                isValid = false;
                errorMessage = 'Minimum length is ' + minLength + ' characters.';
            }
            
            // Show error if invalid
            if (!isValid) {
                fieldWrap.addClass('oha-form-field-error');
                field.after('<span class="oha-error-message">' + errorMessage + '</span>');
            }
            
            return isValid;
        },
        
        /**
         * Show message
         */
        showMessage: function(type, message) {
            const messageClass = 'oha-message oha-message-' + type;
            const messageEl = $('<div>')
                .addClass(messageClass)
                .html('<p>' + message + '</p>')
                .hide();
            
            // Insert at top of form or content
            const target = $('.oha-form, .oha-frontend-wrap').first();
            if (target.length) {
                target.prepend(messageEl);
                messageEl.slideDown();
                
                // Scroll to message
                $('html, body').animate({
                    scrollTop: messageEl.offset().top - 100
                }, 300);
            }
            
            // Auto-dismiss success messages
            if (type === 'success') {
                setTimeout(function() {
                    messageEl.slideUp(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }
    };
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        OHAFrontend.init();
    });
    
    // Export for global access
    window.OHAFrontend = OHAFrontend;
    
})(jQuery);
