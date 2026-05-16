# OHA Survey Hub WordPress Plugin

A comprehensive anonymous survey system designed specifically for the Ohio Haunters Association (OHA) community. This plugin provides multiple survey types with progress tracking, data analytics, and robust backend management.

## Features

### 🎯 Survey Types
- **OHA Member Survey** - General membership feedback
- **Scare Actor & Crew Survey** - Actor and performer focused
- **Behind-the-Scenes Survey** - Technical crew and builders
- **Home Haunt Survey** - Home haunt operators
- **Industry Deep-Dive Survey** - Professional haunt owners

### 🔒 Privacy & Security
- Complete anonymity - emails only for duplicate prevention
- Secure data handling with WordPress best practices
- GDPR-compliant data collection
- No personal data association with responses

### 📊 Smart Features
- **Progress Tracking** - Save and resume surveys
- **Conditional Logic** - Questions that adapt based on answers
- **Priority Questions** - High-value questions shown first
- **Mobile Responsive** - Works perfectly on all devices
- **Accessibility** - WCAG 2.1 AA compliant

### 🎨 User Experience
- **Trust Building** - Clear privacy information upfront
- **Smart Pagination** - 5-10 questions per page
- **Auto-Save** - Progress saved automatically
- **Visual Progress** - Clear progress indicators
- **Elegant UI** - Modern, professional design

### 📈 Analytics & Reporting
- **Real-time Dashboard** - Live response tracking
- **Data Visualization** - Charts and graphs with Chart.js
- **Export Options** - CSV and JSON export
- **Response Management** - View individual responses
- **Bulk Operations** - Manage data efficiently

## Installation

### Prerequisites
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Modern web browser

### Install Steps

1. **Upload the Plugin**
   - Download the `oha-survey-hub.zip` file
   - Go to WordPress Admin → Plugins → Add New
   - Click "Upload Plugin" and select the zip file
   - Click "Install Now"

2. **Activate the Plugin**
   - Click "Activate Plugin" after installation
   - The plugin will create necessary database tables automatically

3. **Verify Installation**
   - Check WordPress Admin → Survey Hub
   - Ensure no error messages appear
   - Database tables should be created automatically

## Usage

### Shortcodes

#### Survey Selector Page
```
[oha_survey_selector]
```
Displays the main survey selection page with trust-building information and all available surveys.

#### Direct Survey Links
```
[oha_survey_member]
[oha_survey_scare_actor]
[oha_survey_behind_scenes]
[oha_survey_home_haunt]
[oha_survey_industry_deep_dive]
```

#### Direct Link Parameter
Add `direct_link="true"` to bypass the selector page:
```
[oha_survey_member direct_link="true"]
```

### Setting Up Survey Pages

#### Option 1: Single Survey Page
Create a page called "Surveys" and use:
```
[oha_survey_selector]
```

#### Option 2: Individual Survey Pages
Create separate pages for each survey type:
- `/member-survey/` → `[oha_survey_member direct_link="true"]`
- `/actor-survey/` → `[oha_survey_scare_actor direct_link="true"]`
- `/technical-survey/` → `[oha_survey_behind_scenes direct_link="true"]`
- `/home-haunt-survey/` → `[oha_survey_home_haunt direct_link="true"]`
- `/industry-survey/` → `[oha_survey_industry_deep_dive direct_link="true"]`

### Admin Dashboard

Access the admin interface at **WordPress Admin → Survey Hub**

#### Dashboard Features
- **Overview Stats** - Total responses, in-progress surveys
- **Response Counts** - Per survey type breakdown
- **Recent Activity** - 7-day activity chart
- **Quick Actions** - Common administrative tasks

#### Managing Responses
1. Go to **Survey Hub → Results**
2. Filter by survey type
3. View individual responses
4. Export data in CSV or JSON format

#### Analytics
1. Go to **Survey Hub → Analytics**
2. Select survey type
3. View charts and response distributions
4. Analyze question-by-question data

#### Settings
1. Go to **Survey Hub → Settings**
2. Configure email notifications
3. Set data retention policies
4. Manage cleanup settings

## Configuration

### Email Settings
- **Confirmation Emails**: Send emails to survey respondents
- **Admin Notifications**: Notify admins of new responses
- **Email Confirmation**: Require email verification (optional)

### Data Management
- **Data Retention**: Configure how long incomplete responses are kept
- **Cleanup Schedule**: Automatic cleanup of old data
- **Export Options**: Configure data export formats

### Security Settings
- All AJAX requests use WordPress nonces
- Data is sanitized and validated
- Rate limiting on submissions
- IP tracking for analysis (anonymous)

## Survey Structure

### Question Types
- **Text Input** - Short text responses
- **Textarea** - Long text responses
- **Select Dropdown** - Single choice from list
- **Radio Buttons** - Single choice with visible options
- **Checkboxes** - Multiple selections
- **Scale Rating** - Numeric rating scales (1-5, 0-10)

### Question Features
- **Required Fields** - Mark critical questions as required
- **Priority Marking** - Highlight high-priority questions
- **Conditional Logic** - Show/hide based on previous answers
- **Help Text** - Additional guidance for users

### Page Structure
Each survey follows this structure:
1. **Welcome Page** - Context and email collection
2. **Priority Questions** - Most important questions first
3. **Deep Dive** - Detailed follow-up questions
4. **Completion** - Thank you and next steps

## Technical Details

### Database Tables
- `wp_oha_survey_responses` - Individual survey responses
- `wp_oha_survey_analytics` - Aggregated analytics data

### File Structure
```
oha-survey-hub/
├── oha-survey-hub.php          # Main plugin file
├── includes/
│   ├── class-survey-data.php    # Data handling
│   ├── class-survey-processor.php # Survey logic
│   ├── class-survey-admin.php   # Admin interface
│   └── survey-definitions.php   # Question definitions
├── templates/
│   └── survey-selector.php      # Survey selector template
└── assets/
    ├── css/
    │   ├── survey-style.css      # Frontend styles
    │   └── admin-style.css       # Admin styles
    └── js/
        ├── survey-script.js      # Frontend functionality
        └── admin-script.js       # Admin functionality
```

### Dependencies
- **Chart.js** - Loaded from CDN for analytics charts
- **WordPress Core** - Uses standard WordPress functions
- **jQuery** - Included with WordPress

### Performance
- Optimized database queries
- AJAX-powered interface
- Minimal resource loading
- Efficient caching strategies

## Customization

### Styling
The plugin includes comprehensive CSS that can be customized:
- CSS variables for easy color changes
- Responsive design system
- Dark mode support
- High contrast mode
- Print styles included

### Adding Questions
Questions are defined in `includes/survey-definitions.php`. Each question includes:
- Unique ID
- Question text
- Input type
- Validation rules
- Priority level
- Conditional logic

### Custom Survey Types
New survey types can be added by:
1. Adding definitions in `survey-definitions.php`
2. Creating new shortcode handlers
3. Adding database handling logic

## Troubleshooting

### Common Issues

**Surveys not displaying**
- Check that shortcodes are properly placed
- Verify plugin is activated
- Check for JavaScript errors in browser console

**Data not saving**
- Verify database permissions
- Check PHP error logs
- Ensure AJAX is working (check network tab)

**Email issues**
- Check WordPress email configuration
- Verify SMTP settings if using custom mailer
- Check spam folders for notifications

**Permission errors**
- Ensure proper WordPress user capabilities
- Check file permissions on server
- Verify database table creation

### Debug Mode
Enable WordPress debug mode for troubleshooting:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

### Getting Help
1. Check this README for common solutions
2. Review WordPress error logs
3. Test with default WordPress theme
4. Contact the OHA development team

### Reporting Issues
When reporting issues, include:
- WordPress version
- PHP version
- Plugin version
- Steps to reproduce
- Error messages (if any)

## License

This plugin is developed specifically for the Ohio Haunters Association. All rights reserved.

## Changelog

### Version 1.0.0
- Initial release
- Complete survey system implementation
- Admin dashboard and analytics
- All survey types implemented
- Full responsive design
- Accessibility compliance

---

**Developed for the Ohio Haunters Association**
*Supporting the Ohio haunt community through better data and insights*
