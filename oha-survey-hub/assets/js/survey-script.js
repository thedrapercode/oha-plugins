/**
 * OHA Survey Hub - Main JavaScript
 * Handles survey navigation, validation, progress saving, and submission
 */

(function($) {
    'use strict';
    
    let currentPage = 0;
    let totalPages = 0;
    let surveyData = {};
    let sessionId = null;
    let saveTimeout = null;
    let submissionInProgress = false;
    
    const OHASurvey = {
        
        init: function() {
            this.generateSessionId();
            this.setupEventHandlers();
            this.setupFormValidation();
            this.setupConditionalFields();
            // Load progress after email validation (so we have an email to look up)
            this.updateUI();
            
            // Setup autosave
            this.setupAutosave();
        },
        
        generateSessionId: function() {
            sessionId = 'oha_survey_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },
        
        setupEventHandlers: function() {
            // Navigation buttons
            $('.oha-btn-next').on('click', this.handleNext.bind(this));
            $('.oha-btn-prev').on('click', this.handlePrev.bind(this));
            $('.oha-btn-save').on('click', this.handleSave.bind(this));
            $('.oha-btn-submit').on('click', this.handleSubmit.bind(this));
            
            // Form input changes
            $('.oha-survey-content').on('input change', 'input, select, textarea', this.handleFieldChange.bind(this));
            
            // Email validation
            $('#oha-survey-email').on('blur', this.validateEmail.bind(this));

            // Load progress when checkbox is checked after email is already entered
            $('#oha-save-progress').on('change', function() {
                if ($(this).is(':checked') && OHASurvey.isValidEmail($('#oha-survey-email').val())) {
                    OHASurvey.loadExistingProgress();
                }
            });
            
            // Checkbox group limitations
            $('.oha-checkbox-group[data-max-selections]').each(function() {
                const maxSelections = parseInt($(this).data('max-selections'));
                $(this).on('change', 'input[type="checkbox"]', function() {
                    const checked = $(this).closest('.oha-checkbox-group').find('input[type="checkbox"]:checked');
                    if (checked.length > maxSelections) {
                        $(this).prop('checked', false);
                        OHASurvey.showMessage('error', 'You can select a maximum of ' + maxSelections + ' options.');
                    }
                });
            });
            
            // Window beforeunload - warn about unsaved changes
            $(window).on('beforeunload', function() {
                if (Object.keys(surveyData).length > 0 && !OHASurvey.isCompleted) {
                    return 'You have unsaved survey progress. Are you sure you want to leave?';
                }
            });
            
            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if (e.ctrlKey || e.metaKey) {
                    switch(e.key) {
                        case 'ArrowLeft':
                            e.preventDefault();
                            $('.oha-btn-prev:visible').click();
                            break;
                        case 'ArrowRight':
                            e.preventDefault();
                            $('.oha-btn-next:visible').click();
                            break;
                        case 's':
                            e.preventDefault();
                            $('.oha-btn-save:visible').click();
                            break;
                    }
                }
            });
        },
        
        setupFormValidation: function() {
            // Real-time validation
            $('.oha-survey-content').on('blur', 'input[required], select[required], textarea[required]', function() {
                OHASurvey.validateField($(this));
            });
        },
        
        setupConditionalFields: function() {
            $('.oha-field-conditional').each(function() {
                const field = $(this);
                const dependsOn = field.data('depends-on');
                const dependsValue = field.data('depends-value').toString().split(',');
                
                // Initially hide conditional fields
                field.hide();
                
                // Watch for changes in the dependency field
                $('[name="' + dependsOn + '"]').on('change', function() {
                    const currentValue = $(this).val() || $(this).filter(':checked').val();
                    
                    if (dependsValue.includes(currentValue)) {
                        field.slideDown(300);
                        field.removeClass('oha-hidden');
                    } else {
                        field.slideUp(300);
                        field.addClass('oha-hidden');
                        // Clear values in hidden fields
                        field.find('input, select, textarea').val('').prop('checked', false);
                    }
                });
            });
        },
        
        updateConditionalFields: function() {
            $('.oha-field-conditional').each(function() {
                const field = $(this);
                const dependsOn = field.data('depends-on');
                const dependsValue = field.data('depends-value').toString().split(',');
                
                // Get current value of the dependency field
                const dependencyField = $('[name="' + dependsOn + '"]');
                let currentValue = '';
                
                if (dependencyField.is(':radio')) {
                    currentValue = dependencyField.filter(':checked').val() || '';
                } else if (dependencyField.is(':checkbox')) {
                    const checkedValues = dependencyField.filter(':checked').map(function() { return $(this).val(); }).get();
                    currentValue = checkedValues.join(',');
                } else {
                    currentValue = dependencyField.val() || '';
                }
                
                // Show or hide field based on current value
                if (dependsValue.some(val => currentValue.includes(val))) {
                    field.show();
                    field.removeClass('oha-hidden');
                } else {
                    field.hide();
                    field.addClass('oha-hidden');
                    // Clear values in hidden fields
                    field.find('input, select, textarea').val('').prop('checked', false);
                }
            });
        },
        
        setupAutosave: function() {
            // Save progress every 30 seconds
            setInterval(function() {
                if (Object.keys(surveyData).length > 0 && currentPage > 0) {
                    OHASurvey.saveProgressSilent();
                }
            }, 30000);
        },
        
        loadExistingProgress: function() {
            const email = $('#oha-survey-email').val();
            const surveyType = $('#oha-survey-container').data('survey-type');
            
            if (email && surveyType) {
                $.ajax({
                    url: oha_survey_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'oha_survey_load_progress',
                        nonce: oha_survey_ajax.nonce,
                        email: email,
                        survey_type: surveyType
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            surveyData = response.data.response_data || {};
                            currentPage = response.data.current_page || 0;
                            sessionId = response.data.session_id;
                            
                            OHASurvey.populateFields();
                            OHASurvey.goToPage(currentPage);
                            OHASurvey.showMessage('info', 'Previous progress loaded. You can continue where you left off.');
                        }
                    }
                });
            }
        },
        
        handleNext: function() {
            if (!this.validateCurrentPage()) {
                return;
            }
            
            this.collectPageData();
            
            totalPages = $('.oha-survey-page').length;
            
            if (currentPage < totalPages - 1) {
                this.goToPage(currentPage + 1);
            }
        },
        
        handlePrev: function() {
            if (currentPage > 0) {
                this.goToPage(currentPage - 1);
            }
        },
        
        handleSave: function() {
            this.collectAllData();
            this.saveProgress();
        },
        
        handleSubmit: function() {
            if (!this.validateAllPages()) {
                this.showMessage('error', oha_survey_ajax.strings.required_fields);
                return;
            }
            
            this.collectAllData();
            this.submitSurvey();
        },
        
        handleFieldChange: function(e) {
            // Prevent infinite loops by checking if already processing this event
            if (e.target._ohaProcessing) {
                return;
            }
            e.target._ohaProcessing = true;
            
            const field = $(e.target);
            
            // Clear any validation errors
            field.removeClass('error');
            field.closest('.oha-field-group').find('.oha-field-error').remove();
            
            // Handle conditional logic without triggering more events
            OHASurvey.updateConditionalFields();
            
            // Debounced autosave
            if (saveTimeout) {
                clearTimeout(saveTimeout);
            }
            saveTimeout = setTimeout(function() {
                if (currentPage > 0) { // Don't save on the first page
                    OHASurvey.collectAllData();
                    OHASurvey.saveProgressSilent();
                }
            }, 3000); // Increased delay to reduce frequency
            
            // Clear processing flag after a short delay
            setTimeout(function() {
                if (e.target) {
                    e.target._ohaProcessing = false;
                }
            }, 100);
        },
        
        goToPage: function(pageNumber) {
            if (pageNumber < 0 || pageNumber >= $('.oha-survey-page').length) {
                return;
            }
            
            currentPage = pageNumber;
            totalPages = $('.oha-survey-page').length;
            
            // Hide all pages
            $('.oha-survey-page').hide();
            
            // Show current page with animation
            $('.oha-survey-page').eq(currentPage).fadeIn(300);
            
            // Update UI
            this.updateUI();
            
            // Scroll to top
            $('html, body').animate({
                scrollTop: $('.oha-survey-container').offset().top - 50
            }, 300);
            
            // Focus first input on new page
            setTimeout(function() {
                $('.oha-survey-page').eq(currentPage).find('input, select, textarea').first().focus();
            }, 350);
        },
        
        updateUI: function() {
            totalPages = $('.oha-survey-page').length;
            const isFirstPage = currentPage === 0;
            const isLastPage = currentPage === totalPages - 1;
            
            // Update progress bar
            const progress = totalPages > 0 ? Math.round((currentPage / (totalPages - 1)) * 100) : 0;
            $('.oha-progress-fill').css('width', progress + '%');
            $('.oha-current-page').text(currentPage + 1);
            $('.oha-total-pages').text(totalPages);
            
            // Update navigation buttons
            $('.oha-btn-prev').toggle(!isFirstPage);
            $('.oha-btn-next').toggle(!isLastPage);
            $('.oha-btn-submit').toggle(isLastPage);
            $('.oha-btn-save').toggle(currentPage > 0 && !isLastPage);
        },
        
        validateCurrentPage: function() {
            const currentPageEl = $('.oha-survey-page').eq(currentPage);
            let isValid = true;
            
            // Clear previous errors
            currentPageEl.find('.oha-field-error').remove();
            currentPageEl.find('.error').removeClass('error');
            
            // Validate required fields on current page
            currentPageEl.find('input[required], select[required], textarea[required]').each(function() {
                if (!OHASurvey.validateField($(this))) {
                    isValid = false;
                }
            });
            
            // Email validation on first page
            if (currentPage === 0) {
                const email = $('#oha-survey-email').val();
                if (!email || !this.isValidEmail(email)) {
                    this.showFieldError($('#oha-survey-email'), 'Please enter a valid email address');
                    isValid = false;
                }
            }
            
            return isValid;
        },
        
        validateAllPages: function() {
            let isValid = true;
            let firstInvalidPage = null;
            
            $('.oha-survey-page').each(function(index) {
                const page = $(this);
                
                page.find('input[required], select[required], textarea[required]').each(function() {
                    const field = $(this);
                    // Skip validation for hidden conditional fields
                    if (field.closest('.oha-field-conditional.oha-hidden').length > 0) {
                        return;
                    }
                    
                    if (!OHASurvey.validateField(field)) {
                        isValid = false;
                        if (firstInvalidPage === null) {
                            firstInvalidPage = index;
                        }
                    }
                });
            });

            // If invalid, jump to the first page with a problem so the user can see it.
            if (!isValid && firstInvalidPage !== null) {
                OHASurvey.goToPage(firstInvalidPage);
            }
            
            return isValid;
        },
        
        validateField: function(field) {
            const value = field.val();
            const fieldType = field.attr('type');
            const isRequired = field.prop('required');
            
            // Skip validation for hidden conditional fields
            if (field.closest('.oha-field-conditional.oha-hidden').length > 0) {
                return true;
            }
            
            field.removeClass('error');
            field.closest('.oha-field-group').find('.oha-field-error').remove();
            
            // Required validation (treat "0" as a valid value)
            if (isRequired) {
                // Checkbox group: require at least one checked for the group
                if (field.is('input[type="checkbox"]')) {
                    const name = field.attr('name');
                    const anyChecked = $('input[name="' + name + '"]:checked').length > 0;
                    if (!anyChecked) {
                        this.showFieldError(field.closest('.oha-field-group'), 'Please select at least one option');
                        return false;
                    }
                } else if (field.is('input[type="radio"]')) {
                    // Radio group handled below, but don't fail here just because this specific radio has a value
                } else {
                    const missing = (value === null || value === undefined || value === '');
                    if (missing) {
                        this.showFieldError(field, 'This field is required');
                        return false;
                    }
                }
            }
            
            // Email validation
            if (fieldType === 'email' && value && !this.isValidEmail(value)) {
                this.showFieldError(field, 'Please enter a valid email address');
                return false;
            }
            
            // Radio group validation
            if (field.is('input[type="radio"][required]')) {
                const name = field.attr('name');
                const checked = $('input[name="' + name + '"]:checked').length > 0;
                if (!checked) {
                    this.showFieldError(field.closest('.oha-radio-group'), 'Please select an option');
                    return false;
                }
            }
            
            return true;
        },
        
        showFieldError: function(field, message) {
            field.addClass('error');
            const errorEl = $('<div class="oha-field-error">' + message + '</div>');
            field.closest('.oha-field-group').append(errorEl);
        },
        
        collectPageData: function() {
            const currentPageEl = $('.oha-survey-page').eq(currentPage);
            this.collectDataFromElement(currentPageEl);
        },
        
        collectAllData: function() {
            surveyData = {};
            $('.oha-survey-page').each(function() {
                OHASurvey.collectDataFromElement($(this));
            });
        },
        
        collectDataFromElement: function(element) {
            element.find('input, select, textarea').each(function() {
                const field = $(this);
                const name = field.attr('name');
                const type = field.attr('type');
                
                if (!name || name === 'survey_email') return;
                
                // Skip hidden conditional fields
                if (field.closest('.oha-field-conditional.oha-hidden').length > 0) {
                    return;
                }
                
                if (type === 'checkbox') {
                    // Strip [] suffix so jQuery doesn't produce double-bracket keys
                    // (e.g. "field[]" -> "field") which PHP can't parse cleanly.
                    const storageKey = name.replace(/\[\]$/, '');
                    if (!surveyData[storageKey]) {
                        surveyData[storageKey] = [];
                    }
                    if (field.is(':checked')) {
                        surveyData[storageKey].push(field.val());
                    }
                } else if (type === 'radio') {
                    if (field.is(':checked')) {
                        surveyData[name] = field.val();
                    }
                } else {
                    const value = field.val();
                    // Save even if the value is "0" (valid for many scales)
                    if (value !== null && value !== undefined && value !== '') {
                        surveyData[name] = value;
                    }
                }
            });
        },
        
        populateFields: function() {
            for (const [name, value] of Object.entries(surveyData)) {
                // Checkboxes are stored without [] but the HTML name has [] appended.
                // Try bare name first; fall back to name + '[]'.
                let fields = $('[name="' + name + '"]');
                const bracketName = name + '[]';
                if (fields.length === 0) {
                    fields = $('[name="' + bracketName + '"]');
                }

                if (fields.is('input[type="checkbox"]')) {
                    // Handle checkbox arrays
                    if (Array.isArray(value)) {
                        value.forEach(function(val) {
                            $('[name="' + name + '"][value="' + val + '"], [name="' + bracketName + '"][value="' + val + '"]').prop('checked', true);
                        });
                    }
                } else if (fields.is('input[type="radio"]')) {
                    // Handle radio buttons
                    $('[name="' + name + '"][value="' + value + '"]').prop('checked', true);
                } else {
                    // Handle text inputs, selects, textareas
                    fields.val(value);
                }
                
                // Trigger change event for conditional fields
                fields.trigger('change');
            }
        },
        
        saveProgress: function() {
            const email = $('#oha-survey-email').val();
            const surveyType = $('#oha-survey-container').data('survey-type');
            
            if (!email || !surveyType) {
                this.showMessage('error', 'Email and survey type are required');
                return;
            }
            
            const saveBtn = $('.oha-btn-save');
            const originalText = saveBtn.html();
            
            saveBtn.html('<span class="oha-btn-icon">⏳</span>' + oha_survey_ajax.strings.saving).prop('disabled', true);
            
            $.ajax({
                url: oha_survey_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'oha_survey_save_progress',
                    nonce: oha_survey_ajax.nonce,
                    survey_type: surveyType,
                    email: email,
                    session_id: sessionId,
                    responses: surveyData,
                    current_page: currentPage,
                    total_pages: totalPages
                },
                success: function(response) {
                    if (response.success) {
                        OHASurvey.showMessage('success', oha_survey_ajax.strings.saved);
                    } else {
                        OHASurvey.showMessage('error', response.data?.message || oha_survey_ajax.strings.error);
                    }
                },
                error: function() {
                    OHASurvey.showMessage('error', oha_survey_ajax.strings.error);
                },
                complete: function() {
                    saveBtn.html(originalText).prop('disabled', false);
                }
            });
        },
        
        saveProgressSilent: function() {
            const email = $('#oha-survey-email').val();
            const surveyType = $('#oha-survey-container').data('survey-type');
            
            if (!email || !surveyType || Object.keys(surveyData).length === 0 || submissionInProgress) {
                return;
            }
            
            // Temporarily disable browser warnings during auto-save
            const originalBeforeUnload = window.onbeforeunload;
            window.onbeforeunload = null;
            
            $.ajax({
                url: oha_survey_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'oha_survey_save_progress',
                    nonce: oha_survey_ajax.nonce,
                    survey_type: surveyType,
                    email: email,
                    session_id: sessionId,
                    responses: surveyData,
                    current_page: currentPage,
                    total_pages: totalPages
                },
                success: function(response) {
                    // Silent save - no user notification except console
                    if (response && response.success) {
                        console.log('Progress auto-saved');
                        OHASurvey.showSaveIndicator();
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Auto-save failed:', error);
                },
                complete: function() {
                    // Restore beforeunload after save completes
                    setTimeout(function() {
                        if (!submissionInProgress) {
                            window.onbeforeunload = originalBeforeUnload;
                        }
                    }, 500);
                }
            });
        },
        
        showSaveIndicator: function() {
            // Show a non-intrusive save indicator
            let $indicator = $('.oha-save-indicator');
            if ($indicator.length === 0) {
                $indicator = $('<div class="oha-save-indicator" style="position:fixed;top:20px;right:20px;background:#28a745;color:white;padding:6px 12px;border-radius:4px;font-size:12px;z-index:1001;opacity:0;transition:opacity 0.3s;">✓ Saved</div>');
                $('body').append($indicator);
            }
            
            $indicator.stop().css('opacity', 1);
            setTimeout(function() {
                $indicator.css('opacity', 0);
            }, 2000);
        },
        
        submitSurvey: function() {
            if (submissionInProgress) {
                console.log('Submission already in progress');
                return;
            }
            
            const email = $('#oha-survey-email').val();
            const surveyType = $('#oha-survey-container').data('survey-type');
            
            if (!email || !surveyType) {
                OHASurvey.showMessage('error', 'Missing survey data. Please refresh and try again.');
                return;
            }
            
            submissionInProgress = true;
            const submitBtn = $('.oha-btn-submit');
            const originalText = submitBtn.html();
            
            // Clear any auto-save timeout and disable beforeunload warnings
            if (saveTimeout) {
                clearTimeout(saveTimeout);
            }
            window.onbeforeunload = null;
            
            submitBtn.html('<span class="oha-btn-icon">⏳</span>' + oha_survey_ajax.strings.submitting).prop('disabled', true);
            
            $.ajax({
                url: oha_survey_ajax.ajax_url,
                type: 'POST',
                timeout: 30000, // 30 second timeout
                data: {
                    action: 'oha_survey_submit',
                    nonce: oha_survey_ajax.nonce,
                    survey_type: surveyType,
                    email: email,
                    session_id: sessionId,
                    responses: surveyData,
                    total_pages: totalPages,
                    current_page: currentPage
                },
                success: function(response) {
                    console.log('Submission response:', response);
                    if (response && response.success) {
                        OHASurvey.isCompleted = true;
                        OHASurvey.showCompletionMessage();
                    } else {
                        console.error('Submission failed:', response);
                        const errorMsg = response?.data?.message || response?.message || 'Submission failed. Please try again.';
                        OHASurvey.showMessage('error', errorMsg);
                        submitBtn.html(originalText).prop('disabled', false);
                        submissionInProgress = false;
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Submission error:', {xhr, status, error});
                    let errorMsg = 'Network error. Please check your connection and try again.';
                    
                    if (status === 'timeout') {
                        errorMsg = 'Request timed out. Please try again.';
                    } else if (xhr.status === 403) {
                        errorMsg = 'Permission denied. Please refresh the page and try again.';
                    } else if (xhr.status === 500) {
                        errorMsg = 'Server error. Please try again or contact support.';
                    }
                    
                    OHASurvey.showMessage('error', errorMsg);
                    submitBtn.html(originalText).prop('disabled', false);
                    submissionInProgress = false;
                }
            });
        },
        
        showCompletionMessage: function() {
            const container = $('.oha-survey-container');
            const completionHtml = `
                <div class="oha-completion-message">
                    <div class="oha-completion-icon">✅</div>
                    <h2>Survey Completed!</h2>
                    <p>Thank you for your participation. Your responses help OHA better serve the Ohio haunt community.</p>
                    <div class="oha-completion-details">
                        <h4>What happens next?</h4>
                        <ul>
                            <li>Your responses are completely anonymous and secure</li>
                            <li>Results will be analyzed and shared with the community</li>
                            <li>You may receive a confirmation email shortly</li>
                            <li>Watch for survey findings in upcoming OHA communications</li>
                        </ul>
                    </div>
                    <div class="oha-completion-actions">
                        <a href="${window.location.origin}" class="oha-btn oha-btn-primary">Return to OHA Website</a>
                        <a href="#" onclick="window.print()" class="oha-btn oha-btn-secondary">Print Confirmation</a>
                    </div>
                </div>
            `;
            
            container.fadeOut(300, function() {
                container.html(completionHtml).fadeIn(300);
            });
        },
        
        validateEmail: function() {
            const email = $('#oha-survey-email').val();
            const surveyType = $('#oha-survey-container').data('survey-type');
            
            if (!email || !this.isValidEmail(email)) {
                return;
            }
            
            // Check if email already completed this survey
            $.ajax({
                url: oha_survey_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'oha_survey_validate_email',
                    nonce: oha_survey_ajax.nonce,
                    email: email,
                    survey_type: surveyType
                },
                success: function(response) {
                    if (!response.success) {
                        OHASurvey.showMessage('error', response.data?.message || 'Email validation failed');
                        $('#oha-survey-email').addClass('error');
                    } else {
                        $('#oha-survey-email').removeClass('error');
                        // If user opted to save progress, attempt to load any in-progress session
                        if ($('#oha-save-progress').is(':checked')) {
                            OHASurvey.loadExistingProgress();
                        }
                    }
                }
            });
        },
        
        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },
        
        showMessage: function(type, message) {
            const messageHtml = `
                <div class="oha-message oha-${type}">
                    ${message}
                </div>
            `;
            
            const messageContainer = $('.oha-survey-messages');
            const messageEl = $(messageHtml);
            
            messageContainer.append(messageEl);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                messageEl.fadeOut(300, function() {
                    messageEl.remove();
                });
            }, 5000);
            
            // Click to dismiss
            messageEl.on('click', function() {
                $(this).fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.oha-survey-container').length) {
            OHASurvey.init();
        }
    });
    
})(jQuery);
