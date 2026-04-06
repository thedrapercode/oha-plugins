/**
 * OHA Schedule Hub - Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Check if elements exist
        if ($('#oha-class-modal').length === 0) {
            console.log('OHA Schedule Hub: Admin elements not found');
            return;
        }
        
        // Modal functions
        var $modal = $('#oha-class-modal');
        var $form = $('#oha-class-form');
        
        // Open modal for new class
        $(document).on('click', '#oha-add-class-btn', function(e) {
            e.preventDefault();
            console.log('Add class button clicked');
            resetForm();
            $('#oha-modal-title').text('Add New Class');
            $modal.addClass('show').css('display', 'flex');
        });
        
        // Open modal for edit
        $(document).on('click', '.oha-btn-edit', function(e) {
            e.preventDefault();
            console.log('Edit button clicked');
            var classId = $(this).data('class-id');
            loadClassData(classId);
        });
        
        // Close modal
        $(document).on('click', '.oha-modal-close', function(e) {
            e.preventDefault();
            $modal.removeClass('show').fadeOut();
        });
        
        // Close modal on outside click
        $(window).on('click', function(e) {
            if ($(e.target).is('#oha-class-modal')) {
                $modal.removeClass('show').fadeOut();
            }
        });
        
        // Close modal on Escape key
        $(document).on('keydown', function(e) {
            if (e.key === "Escape" && $modal.is(':visible')) {
                $modal.removeClass('show').fadeOut();
            }
        });
        
        // Handle form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            saveClass();
        });
        
        // Delete class
        $(document).on('click', '.oha-btn-delete', function() {
            if (!confirm('Are you sure you want to delete this class?')) {
                return;
            }
            
            var classId = $(this).data('class-id');
            deleteClass(classId);
        });
        
        // Handle "Add New" options in selects
        $('#oha-class-day, #oha-class-instructor, #oha-class-track').on('change', function() {
            if ($(this).val() === '_new') {
                var fieldName = $(this).attr('id').replace('oha-class-', '');
                var newValue = prompt('Enter new ' + fieldName + ':');
                
                if (newValue) {
                    // Add new option
                    var $newOption = $('<option></option>')
                        .attr('value', newValue)
                        .text(newValue);
                    $(this).find('option:last').before($newOption);
                    $(this).val(newValue);
                } else {
                    $(this).val('');
                }
            }
        });
        
        // Auto-calculate end time
        $('#oha-class-start-time').on('change', function() {
            var startTime = $(this).val();
            if (startTime && !$('#oha-class-end-time').val()) {
                var duration = 75; // 1 hour 15 minutes in minutes
                var endTime = calculateEndTime(startTime, duration);
                $('#oha-class-end-time').val(endTime);
            }
        });
        
        function calculateEndTime(startTime, durationMinutes) {
            var timeParts = startTime.split(':');
            var hours = parseInt(timeParts[0]);
            var minutes = parseInt(timeParts[1]);
            
            var totalMinutes = (hours * 60) + minutes + durationMinutes;
            var newHours = Math.floor(totalMinutes / 60);
            var newMinutes = totalMinutes % 60;
            
            return ('0' + newHours).slice(-2) + ':' + ('0' + newMinutes).slice(-2);
        }
        
        function resetForm() {
            $form[0].reset();
            $('#oha-class-id').val('');
        }
        
        function loadClassData(classId) {
            $.ajax({
                url: ohaSchedule.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oha_get_class',
                    nonce: ohaSchedule.nonce,
                    class_id: classId
                },
                beforeSend: function() {
                    $modal.addClass('oha-loading').css('display', 'flex').addClass('show');
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        $('#oha-class-id').val(classId);
                        $('#oha-class-title').val(data.title);
                        $('#oha-class-short-desc').val(data.short_desc || '');
                        $('#oha-class-description').val(data.description);
                        $('#oha-class-day').val(data.day);
                        $('#oha-class-instructor').val(data.instructor);
                        $('#oha-class-start-time').val(data.start_time);
                        $('#oha-class-end-time').val(data.end_time);
                        $('#oha-class-room').val(data.room);
                        $('#oha-class-track').val(data.track);
                        $('#oha-class-level').val(data.level);
                        $('#oha-class-capacity').val(data.capacity);
                        $('#oha-class-year').val(data.year || '');
                        $('#oha-class-show-end-time').prop('checked', data.show_end_time === '1');
                        $('#oha-class-end-time-label').val(data.end_time_label || '');
                        
                        $('#oha-modal-title').text('Edit Class');
                    } else {
                        alert('Failed to load class data');
                        $modal.removeClass('show').hide();
                    }
                },
                complete: function() {
                    $modal.removeClass('oha-loading');
                }
            });
        }
        
        function saveClass() {
            var formData = {
                action:      'oha_save_class',
                nonce:       ohaSchedule.nonce,
                class_id:    $('#oha-class-id').val(),
                title:       $('#oha-class-title').val(),
                short_desc:  $('#oha-class-short-desc').val(),
                description: $('#oha-class-description').val(),
                day:         $('#oha-class-day').val(),
                instructor:  $('#oha-class-instructor').val(),
                start_time:  $('#oha-class-start-time').val(),
                end_time:    $('#oha-class-end-time').val(),
                room:        $('#oha-class-room').val(),
                track:       $('#oha-class-track').val(),
                level:       $('#oha-class-level').val(),
                capacity:    $('#oha-class-capacity').val(),
                year:           $('#oha-class-year').val(),
                show_end_time:  $('#oha-class-show-end-time').is(':checked') ? '1' : '0',
                end_time_label: $('#oha-class-end-time-label').val()
            };
            
            $.ajax({
                url: ohaSchedule.ajaxurl,
                type: 'POST',
                data: formData,
                beforeSend: function() {
                    $form.addClass('oha-loading');
                },
                success: function(response) {
                    if (response.success) {
                        $modal.removeClass('show').hide();
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                complete: function() {
                    $form.removeClass('oha-loading');
                }
            });
        }
        
        function deleteClass(classId) {
            $.ajax({
                url: ohaSchedule.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oha_delete_class',
                    nonce: ohaSchedule.nonce,
                    class_id: classId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                }
            });
        }
        
        // Export schedule
        $('#oha-export-schedule').on('click', function() {
            $.ajax({
                url: ohaSchedule.ajaxurl,
                type: 'POST',
                data: {
                    action: 'oha_export_schedule',
                    nonce: ohaSchedule.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var csv = convertToCSV(data);
                        downloadCSV(csv, 'oha-schedule-export.csv');
                    } else {
                        alert('Export failed');
                    }
                }
            });
        });
        
        function convertToCSV(data) {
            var csv = 'Year,Title,Instructor,Day,Start Time,End Time,Room,Track,Level,Capacity,Short Desc,Materials,Promoted,Description\n';

            data.forEach(function(row) {
                var line = [
                    escapeCSV(row.year),
                    escapeCSV(row.title),
                    escapeCSV(row.instructor),
                    escapeCSV(row.day),
                    escapeCSV(row.start_time),
                    escapeCSV(row.end_time),
                    escapeCSV(row.room),
                    escapeCSV(row.track),
                    escapeCSV(row.level),
                    escapeCSV(row.capacity),
                    escapeCSV(row.short_desc),
                    escapeCSV(row.materials),
                    escapeCSV(row.promoted ? 'Yes' : ''),
                    escapeCSV(row.description)
                ].join(',');

                csv += line + '\n';
            });

            return csv;
        }
        
        function escapeCSV(value) {
            if (!value) return '';
            value = String(value).replace(/"/g, '""');
            if (value.indexOf(',') !== -1 || value.indexOf('"') !== -1 || value.indexOf('\n') !== -1) {
                value = '"' + value + '"';
            }
            return value;
        }
        
        function downloadCSV(csv, filename) {
            var blob = new Blob([csv], { type: 'text/csv' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.click();
        }
        
    });
    
})(jQuery);
