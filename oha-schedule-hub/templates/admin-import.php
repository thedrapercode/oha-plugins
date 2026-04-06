<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Bulk Import Classes</h1>
    
    <div class="oha-import-container">
        <div class="card">
            <h2>Option 1: Paste CSV Data</h2>
            <p>Format: <code>Title, Instructor, Day, Start Time, End Time, Room, Description</code></p>
            <p>Example: <code>Lighting & Controllers, Allen Redmon, Saturday, 10:00, 11:15, Room A, Learn about lighting...</code></p>
            
            <textarea id="oha-csv-import" rows="10" style="width: 100%; font-family: monospace;"></textarea>
            
            <button type="button" class="button button-primary" id="oha-import-csv">Import from CSV</button>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>Option 2: Import from Spreadsheet</h2>
            <p>Upload an Excel or CSV file with the following columns:</p>
            <ul style="list-style: disc; margin-left: 30px;">
                <li>Title</li>
                <li>Instructor</li>
                <li>Day (Saturday/Sunday)</li>
                <li>Start Time (HH:MM format)</li>
                <li>End Time (HH:MM format)</li>
                <li>Room/Location</li>
                <li>Description (optional)</li>
                <li>Track/Category (optional)</li>
                <li>Level (optional)</li>
                <li>Capacity (optional)</li>
            </ul>
            
            <input type="file" id="oha-file-import" accept=".csv,.xlsx,.xls">
            <button type="button" class="button button-primary" id="oha-import-file">Import from File</button>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>Option 3: Quick Add from Current Schedule</h2>
            <p>Based on your uploaded schedule, here's a pre-formatted import:</p>
            
            <button type="button" class="button button-primary" id="oha-import-preset">Import 2026 Schedule</button>
            
            <div id="oha-preset-data" style="display:none;">
                [
                    {
                        "title": "Lighting, Sound, and Controllers for Props and Special Effects",
                        "instructor": "Allen Redmon",
                        "day": "Saturday",
                        "start_time": "10:00",
                        "end_time": "11:15",
                        "description": "Learn how to use lighting controllers and sound systems for haunted props and effects.",
                        "track": "Technical"
                    },
                    {
                        "title": "Queue Line Acting",
                        "instructor": "Max Bryant",
                        "day": "Saturday",
                        "start_time": "11:45",
                        "end_time": "13:00",
                        "description": "Master the art of entertaining guests while they wait in line.",
                        "track": "Acting"
                    },
                    {
                        "title": "Mobile Attraction to Promote Seasonal Brick and Mortar Attraction",
                        "instructor": "Harry Siebert",
                        "day": "Saturday",
                        "start_time": "13:30",
                        "end_time": "14:45",
                        "description": "Learn strategies for using mobile haunts to promote your main attraction.",
                        "track": "Business"
                    },
                    {
                        "title": "Makeup/Airbrush/Application",
                        "instructor": "Matt Seel",
                        "day": "Saturday",
                        "start_time": "15:15",
                        "end_time": "16:30",
                        "description": "Hands-on workshop covering airbrush techniques and makeup application.",
                        "track": "Makeup"
                    },
                    {
                        "title": "The Art of Acting: Do's & Don'ts",
                        "instructor": "Tommy Payton",
                        "day": "Sunday",
                        "start_time": "10:00",
                        "end_time": "11:15",
                        "description": "Essential acting techniques and common mistakes to avoid in haunted attractions.",
                        "track": "Acting"
                    },
                    {
                        "title": "Social Media Basics for Haunt Owners",
                        "instructor": "Matt Cole",
                        "day": "Sunday",
                        "start_time": "11:45",
                        "end_time": "13:00",
                        "description": "Marketing your haunt effectively using social media platforms.",
                        "track": "Business"
                    },
                    {
                        "title": "The Art of Corpsing",
                        "instructor": "Kenneth Baker",
                        "day": "Sunday",
                        "start_time": "13:30",
                        "end_time": "14:45",
                        "description": "Learn professional corpsing techniques for realistic prop creation.",
                        "track": "Makeup"
                    }
                ]
            </div>
        </div>
        
        <div id="oha-import-results" style="margin-top: 20px;"></div>
    </div>
</div>

<style>
.oha-import-container .card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.oha-import-container h2 {
    margin-top: 0;
    font-size: 18px;
}

.oha-import-container code {
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
}

#oha-import-results {
    padding: 15px;
    border-radius: 4px;
    display: none;
}

#oha-import-results.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    display: block;
}

#oha-import-results.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    display: block;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Import preset data
    $('#oha-import-preset').on('click', function() {
        var presetData = JSON.parse($('#oha-preset-data').text());
        importClasses(presetData);
    });
    
    // Import from CSV
    $('#oha-import-csv').on('click', function() {
        var csvText = $('#oha-csv-import').val();
        if (!csvText) {
            alert('Please paste CSV data first');
            return;
        }
        
        var classes = parseCSV(csvText);
        importClasses(classes);
    });
    
    // Import from file
    $('#oha-import-file').on('click', function() {
        var fileInput = document.getElementById('oha-file-import');
        if (!fileInput.files.length) {
            alert('Please select a file first');
            return;
        }
        
        var file = fileInput.files[0];
        var reader = new FileReader();
        
        reader.onload = function(e) {
            var text = e.target.result;
            var classes = parseCSV(text);
            importClasses(classes);
        };
        
        reader.readAsText(file);
    });
    
    function parseCSV(csvText) {
        var lines = csvText.trim().split('\n');
        var classes = [];
        
        for (var i = 0; i < lines.length; i++) {
            var line = lines[i].trim();
            if (!line) continue;
            
            var parts = line.split(',').map(function(part) {
                return part.trim().replace(/^["']|["']$/g, '');
            });
            
            if (parts.length >= 5) {
                classes.push({
                    title: parts[0],
                    instructor: parts[1],
                    day: parts[2],
                    start_time: parts[3],
                    end_time: parts[4],
                    room: parts[5] || '',
                    description: parts[6] || ''
                });
            }
        }
        
        return classes;
    }
    
    function importClasses(classes) {
        $('#oha-import-results').removeClass('success error').hide().text('Importing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'oha_bulk_import',
                nonce: '<?php echo wp_create_nonce('oha_schedule_nonce'); ?>',
                classes: JSON.stringify(classes)
            },
            success: function(response) {
                if (response.success) {
                    $('#oha-import-results')
                        .addClass('success')
                        .text(response.data.message)
                        .show();
                } else {
                    $('#oha-import-results')
                        .addClass('error')
                        .text('Error: ' + response.data)
                        .show();
                }
            },
            error: function() {
                $('#oha-import-results')
                    .addClass('error')
                    .text('An error occurred during import')
                    .show();
            }
        });
    }
});
</script>
