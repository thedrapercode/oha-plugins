/**
 * OHA Survey Hub - Admin JavaScript
 * Backend administration functionality
 */

(function($) {
    'use strict';
    
    const OHAAdmin = {
        
        init: function() {
            this.setupEventHandlers();
            this.setupAjaxHandlers();
            this.setupCharts();
        },
        
        setupEventHandlers: function() {
            // Toggle response details
            $(document).on('click', '.oha-toggle-response', function() {
                const responseId = $(this).data('response-id');
                if (responseId) {
                    OHAAdmin.toggleResponse(responseId);
                }
            });
            
            // Export functionality
            $(document).on('click', '[data-export]', function(e) {
                e.preventDefault();
                const surveyType = $(this).data('survey-type') || '';
                const format = $(this).data('format') || 'csv';
                OHAAdmin.exportSurveyData(surveyType, format);
            });
            
            // Cleanup functionality
            $(document).on('click', '[data-cleanup]', function(e) {
                e.preventDefault();
                OHAAdmin.cleanupOldResponses();
            });
            
            // Auto-refresh stats
            if ($('.oha-survey-admin').length) {
                setInterval(function() {
                    OHAAdmin.refreshStats();
                }, 60000); // Refresh every minute
            }
            
            // Survey type filter
            $('#survey-type-filter').on('change', function() {
                const selectedType = $(this).val();
                OHAAdmin.filterSurveyType(selectedType);
            });
            
            // Bulk actions
            $('.oha-bulk-action').on('click', function(e) {
                e.preventDefault();
                const action = $(this).data('action');
                const selectedItems = $('.oha-response-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();
                
                if (selectedItems.length === 0) {
                    alert('Please select at least one item.');
                    return;
                }
                
                OHAAdmin.performBulkAction(action, selectedItems);
            });
            
            // Select all checkbox
            $('#select-all-responses').on('change', function() {
                $('.oha-response-checkbox').prop('checked', $(this).prop('checked'));
            });
            
            // Search functionality
            $('#survey-search').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                OHAAdmin.searchSurveys(searchTerm);
            });
        },
        
        setupAjaxHandlers: function() {
            // Setup CSRF protection for all AJAX requests
            $.ajaxSetup({
                beforeSend: function(xhr, settings) {
                    if (settings.type === 'POST' && settings.data && typeof settings.data === 'object') {
                        settings.data.nonce = settings.data.nonce || oha_survey_ajax.nonce;
                    }
                }
            });
        },
        
        setupCharts: function() {
            // Initialize Chart.js charts if library is loaded
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded. Charts will not be displayed.');
                return;
            }
            
            // Activity chart
            this.initActivityChart();
            
            // Response distribution charts
            this.initResponseDistributionCharts();
            
            // Analytics charts
            this.initAnalyticsCharts();
        },
        
        initActivityChart: function() {
            const canvas = document.getElementById('activityChart');
            if (!canvas) return;
            
            // Chart is initialized in PHP-generated script
            // This is just a placeholder for additional chart setup
        },
        
        initResponseDistributionCharts: function() {
            $('.oha-distribution-chart').each(function() {
                const canvas = this;
                const data = JSON.parse($(canvas).data('chart-data') || '[]');
                
                if (data.length === 0) return;
                
                new Chart(canvas, {
                    type: 'pie',
                    data: {
                        labels: data.map(item => item.label),
                        datasets: [{
                            data: data.map(item => item.value),
                            backgroundColor: [
                                '#f39200', '#1e1e1e', '#666666', '#999999', 
                                '#cccccc', '#ff6b35', '#004e89', '#ffc857'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            });
        },
        
        initAnalyticsCharts: function() {
            // Analytics charts are handled by PHP-generated scripts
            // This function can be extended for additional chart customization
        },
        
        toggleResponse: function(responseId) {
            const contentDiv = $('#oha-response-' + responseId);
            const button = $('[data-response-id="' + responseId + '"]');

            if (contentDiv.is(':visible')) {
                contentDiv.slideUp(200);
                button.text('View Details');
            } else {
                contentDiv.slideDown(200);
                button.text('Hide Details');
            }
        },
        
        showResponseModal: function(responseData) {
            // Create modal HTML
            const modalHtml = `
                <div id="oha-response-modal" class="oha-modal-overlay">
                    <div class="oha-modal-content">
                        <div class="oha-modal-header">
                            <h3>Response Details - #${responseData.id}</h3>
                            <button type="button" class="oha-modal-close" onclick="OHAAdmin.closeResponseModal()">×</button>
                        </div>
                        <div class="oha-modal-body">
                            <div class="oha-response-meta">
                                <p><strong>Email:</strong> ${responseData.email}</p>
                                <p><strong>Completed:</strong> ${responseData.completed_at}</p>
                                <p><strong>IP Address:</strong> ${responseData.ip_address}</p>
                            </div>
                            <div class="oha-response-answers">
                                ${responseData.answers.map(answer => `
                                    <div class="oha-answer-item ${answer.priority ? 'priority' : ''}">
                                        <div class="oha-answer-question">
                                            ${answer.question}
                                            ${answer.priority ? '<span class="oha-priority-badge">★</span>' : ''}
                                        </div>
                                        <div class="oha-answer-value">${answer.answer}</div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal and add new one
            $('#oha-response-modal').remove();
            $('body').append(modalHtml);
            $('#oha-response-modal').fadeIn(300);
        },
        
        closeResponseModal: function() {
            $('#oha-response-modal').fadeOut(300, function() {
                $(this).remove();
            });
        },
        
        exportSurveyData: function(surveyType, format) {
            // Show loading indicator
            const loadingHtml = '<div class="oha-export-loading">Preparing export...</div>';
            $('body').append(loadingHtml);
            
            const params = new URLSearchParams({
                action: 'oha_survey_export',
                survey_type: surveyType,
                format: format,
                nonce: oha_survey_ajax?.export_nonce || oha_survey_ajax?.nonce || ''
            });
            
            const exportUrl = (oha_survey_ajax?.ajax_url || ajaxurl) + '?' + params.toString();
            
            // Create temporary link and trigger download
            const link = document.createElement('a');
            link.href = exportUrl;
            link.download = '';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Remove loading indicator
            setTimeout(function() {
                $('.oha-export-loading').remove();
            }, 2000);
        },
        
        cleanupOldResponses: function() {
            if (!confirm('Are you sure you want to clean up old incomplete responses? This action cannot be undone.')) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oha_survey_cleanup_old_responses',
                    nonce: oha_survey_ajax?.nonce || ''
                },
                beforeSend: function() {
                    $('[data-cleanup]').prop('disabled', true).text('Cleaning up...');
                },
                success: function(response) {
                    if (response.success) {
                        const deleted = response.data?.deleted || 0;
                        alert('Cleanup completed successfully. ' + deleted + ' old records were removed.');
                        location.reload(); // Refresh to update stats
                    } else {
                        alert('Cleanup failed: ' + (response.data?.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Network error occurred during cleanup.');
                },
                complete: function() {
                    $('[data-cleanup]').prop('disabled', false).text('Clean Up Old Data');
                }
            });
        },
        
        refreshStats: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oha_survey_get_stats',
                    nonce: oha_survey_ajax?.nonce || ''
                },
                success: function(response) {
                    if (response.success && response.data) {
                        OHAAdmin.updateStatsDisplay(response.data);
                    }
                },
                error: function() {
                    console.log('Failed to refresh stats');
                }
            });
        },
        
        updateStatsDisplay: function(stats) {
            // Update total responses
            $('.oha-stat-item').each(function() {
                const label = $(this).find('.oha-stat-label').text();
                const numberEl = $(this).find('.oha-stat-number');
                
                if (label.includes('Total Responses')) {
                    numberEl.text(stats.total_responses?.toLocaleString() || '0');
                } else if (label.includes('In Progress')) {
                    numberEl.text(stats.in_progress?.toLocaleString() || '0');
                }
            });
            
            // Update survey-specific counts
            if (stats.by_type) {
                $('.oha-survey-item').each(function() {
                    const surveyName = $(this).find('.oha-survey-name').text().toLowerCase();
                    const surveyType = OHAAdmin.getSurveyTypeFromName(surveyName);
                    
                    if (stats.by_type[surveyType]) {
                        $(this).find('.oha-completed').text(
                            stats.by_type[surveyType].completed + ' completed'
                        );
                        $(this).find('.oha-in-progress').text(
                            stats.by_type[surveyType].in_progress + ' in progress'
                        );
                    }
                });
            }
        },
        
        getSurveyTypeFromName: function(name) {
            const typeMap = {
                'oha member': 'member',
                'scare actor': 'scare_actor',
                'behind-the-scenes': 'behind_scenes',
                'home haunt': 'home_haunt',
                'industry deep-dive': 'industry_deep_dive',
                'haunt owner': 'industry_deep_dive'
            };
            
            for (const [key, value] of Object.entries(typeMap)) {
                if (name.includes(key)) {
                    return value;
                }
            }
            
            return 'member';
        },
        
        filterSurveyType: function(selectedType) {
            if (!selectedType) {
                $('.oha-survey-item, .oha-response-card').show();
                return;
            }
            
            $('.oha-survey-item').each(function() {
                const itemType = $(this).data('survey-type');
                $(this).toggle(itemType === selectedType);
            });
            
            $('.oha-response-card').each(function() {
                const itemType = $(this).data('survey-type');
                $(this).toggle(itemType === selectedType);
            });
        },
        
        performBulkAction: function(action, selectedItems) {
            if (!confirm('Are you sure you want to perform this action on ' + selectedItems.length + ' items?')) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'oha_survey_bulk_action',
                    bulk_action: action,
                    items: selectedItems,
                    nonce: oha_survey_ajax?.nonce || ''
                },
                beforeSend: function() {
                    $('.oha-bulk-action').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        alert('Bulk action completed successfully.');
                        location.reload();
                    } else {
                        alert('Bulk action failed: ' + (response.data?.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Network error occurred during bulk action.');
                },
                complete: function() {
                    $('.oha-bulk-action').prop('disabled', false);
                }
            });
        },
        
        searchSurveys: function(searchTerm) {
            if (!searchTerm) {
                $('.oha-survey-item, .oha-response-card, .oha-survey-summary-card').show();
                return;
            }
            
            $('.oha-survey-item').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(searchTerm));
            });
            
            $('.oha-response-card').each(function() {
                const text = $(this).find('.oha-response-answers').text().toLowerCase();
                $(this).toggle(text.includes(searchTerm));
            });
            
            $('.oha-survey-summary-card').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(searchTerm));
            });
        },
        
        // Utility functions
        formatNumber: function(num) {
            if (num >= 1000000) {
                return (num / 1000000).toFixed(1) + 'M';
            } else if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'K';
            }
            return num.toString();
        },
        
        copyToClipboard: function(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    OHAAdmin.showNotification('Copied to clipboard!', 'success');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    OHAAdmin.showNotification('Copied to clipboard!', 'success');
                } catch (err) {
                    OHAAdmin.showNotification('Failed to copy to clipboard', 'error');
                }
                document.body.removeChild(textArea);
            }
        },
        
        showNotification: function(message, type) {
            const notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after(notification);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notification.fadeOut(function() {
                    notification.remove();
                });
            }, 5000);
            
            // Add dismiss functionality
            notification.on('click', '.notice-dismiss', function() {
                notification.fadeOut(function() {
                    notification.remove();
                });
            });
        },
        
        // Accessibility helpers
        announceToScreenReader: function(message) {
            const announcement = $('<div aria-live="polite" class="sr-only">' + message + '</div>');
            $('body').append(announcement);
            setTimeout(function() {
                announcement.remove();
            }, 1000);
        }
    };
    
    // Initialize admin functionality when document is ready
    $(document).ready(function() {
        if ($('.oha-survey-admin').length) {
            OHAAdmin.init();
        }
    });
    
    // Global functions for backwards compatibility and inline usage
    window.ohaCleanupOldResponses = function() {
        OHAAdmin.cleanupOldResponses();
    };
    
    window.ohaExportSurveyData = function(surveyType, format) {
        OHAAdmin.exportSurveyData(surveyType, format);
    };
    
    window.ohaToggleResponse = function(responseId) {
        OHAAdmin.toggleResponse(responseId);
    };
    
    window.ohaCopyShortcode = function(shortcode) {
        OHAAdmin.copyToClipboard(shortcode);
    };
    
    // Expose OHAAdmin for external use if needed
    window.OHAAdmin = OHAAdmin;

})(jQuery);

// Analytics: expand text response blocks
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.oha-expand-btn');
        if (!btn) return;
        var uid    = btn.getAttribute('data-uid');
        var wrap   = btn.closest('.oha-text-responses');
        if (!wrap) return;
        wrap.querySelectorAll('.oha-text-hidden').forEach(function (el) {
            el.classList.remove('oha-text-hidden');
        });
        btn.remove();
    });
}());
