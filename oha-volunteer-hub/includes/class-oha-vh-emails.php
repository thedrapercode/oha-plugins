<?php
/**
 * OHA Volunteer Hub - Email System (Phase 3)
 * Handles all email notifications for volunteer management
 */

namespace OHA\VH;

if (!defined('ABSPATH')) { exit; }

class Emails {

    public static function init() {
        // Emails are invoked directly when actions happen
    }

    /**
     * Send assignment notification email.
     * Uses the Assignment Email template from Settings > Email Templates.
     * Placeholders: {first_name}, {last_name}, {area_name}, {area_icon}, {area_description},
     *               {shift_time}, {convention_name}, {convention_date}, {portal_url}, {manager_info}
     */
    public static function send_assignment_email($volunteer_id, $area_id, $shift_id = null) {
        $volunteer = Database::get_volunteer($volunteer_id);
        $area      = Database::get_area($area_id);

        if (!$volunteer || !$area) {
            error_log('OHA Volunteer Hub: send_assignment_email failed - volunteer_id=' . $volunteer_id . ' (' . ($volunteer ? 'found' : 'NOT FOUND') . ') area_id=' . $area_id . ' (' . ($area ? 'found' : 'NOT FOUND') . ')');
            return false;
        }

        // Build shift time string
        $shift_info = '';
        if ($shift_id) {
            global $wpdb;
            $shift = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}oha_volunteer_shifts WHERE id = %d",
                $shift_id
            ));
            if ($shift) {
                if ($shift->shift_type === 'all_day') {
                    $shift_info = 'All Day on ' . date('F j, Y', strtotime($shift->shift_date));
                } else {
                    $shift_info = date('F j, Y', strtotime($shift->shift_date)) . ' from ' .
                                  date('g:i A', strtotime($shift->shift_start)) . ' to ' .
                                  date('g:i A', strtotime($shift->shift_end));
                }
            }
        }

        // Build manager info string
        $managers      = json_decode($area->area_managers, true) ?: [];
        $manager_lines = [];
        foreach ($managers as $manager_id) {
            $manager = get_user_by('id', $manager_id);
            if ($manager) {
                $manager_lines[] = $manager->display_name . ' (' . $manager->user_email . ')';
            }
        }
        $manager_info = !empty($manager_lines)
            ? "Your Area Manager(s):\n" . implode("\n", $manager_lines)
            : '';

        $convention_name     = Settings::get('convention_name', get_option('oha_vh_convention_name', 'OHA Convention 2026'));
        $convention_date_raw = Settings::get('convention_date', get_option('oha_vh_convention_date', '2026-05-09'));
        $convention_date     = date('F j, Y', strtotime($convention_date_raw));
        $portal_url          = home_url('/volunteer-portal');

        // Use saved template, fall back to default
        $subject = Settings::get('assignment_subject', Settings::get_default_assignment_subject());
        $body    = Settings::get('assignment_body',    Settings::get_default_assignment_email());

        $placeholders = [
            '{first_name}'       => $volunteer->first_name,
            '{last_name}'        => $volunteer->last_name,
            '{area_name}'        => $area->icon . ' ' . $area->area_name,
            '{area_icon}'        => $area->icon,
            '{area_description}' => $area->description,
            '{shift_time}'       => $shift_info ?: $convention_date,
            '{convention_name}'  => $convention_name,
            '{convention_date}'  => $convention_date,
            '{portal_url}'       => $portal_url,
            '{manager_info}'     => $manager_info,
        ];

        $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
        $body    = str_replace(array_keys($placeholders), array_values($placeholders), $body);

        // Strip any HTML that may have been saved into the template editor
        $body = preg_replace('/<br\s*\/?>/i', "\n", $body);
        $body = preg_replace('/<\/p>/i', "\n\n", $body);
        $body = wp_strip_all_tags($body);
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = preg_replace('/\n{3,}/', "\n\n", trim($body));

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        return wp_mail($volunteer->email, $subject, $body, $headers);
    }

    /**
     * Send welcome/confirmation email on signup.
     * Note: SignupForm::send_confirmation_email() is the primary caller from the signup form.
     * This method is used for manual-add flows from the board hub.
     */
    public static function send_confirmation_email($volunteer_id) {
        $volunteer = Database::get_volunteer($volunteer_id);
        if (!$volunteer) {
            return false;
        }

        $convention_name     = Settings::get('convention_name', get_option('oha_vh_convention_name', 'OHA Convention 2026'));
        $convention_date_raw = Settings::get('convention_date', get_option('oha_vh_convention_date', '2026-05-09'));
        $convention_date     = date('F j, Y', strtotime($convention_date_raw));
        $coordinator_email   = Settings::get('volunteer_coordinator_email', get_option('admin_email'));

        $subject = Settings::get('confirmation_subject', 'Volunteer Application Received - ' . $convention_name);
        $body    = Settings::get('confirmation_body', Settings::get_default_confirmation_email());

        $placeholders = [
            '{first_name}'                  => $volunteer->first_name,
            '{last_name}'                   => $volunteer->last_name,
            '{email}'                       => $volunteer->email,
            '{convention_name}'             => $convention_name,
            '{convention_date}'             => $convention_date,
            '{volunteer_coordinator_email}' => $coordinator_email,
        ];

        $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
        $body    = str_replace(array_keys($placeholders), array_values($placeholders), $body);

        $body = preg_replace('/<br\s*\/?>/i', "\n", $body);
        $body = preg_replace('/<\/p>/i', "\n\n", $body);
        $body = wp_strip_all_tags($body);
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = preg_replace('/\n{3,}/', "\n\n", trim($body));

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        return wp_mail($volunteer->email, $subject, $body, $headers);
    }

    /**
     * Send approval notification.
     * Uses the Approval Email template from Settings > Email Templates.
     * Placeholders: {first_name}, {last_name}, {convention_name}, {volunteer_coordinator_email}
     */
    public static function send_approval_email($volunteer_id) {
        $volunteer = Database::get_volunteer($volunteer_id);
        if (!$volunteer) {
            return false;
        }

        $convention_name   = Settings::get('convention_name', get_option('oha_vh_convention_name', 'OHA Convention 2026'));
        $coordinator_email = Settings::get('volunteer_coordinator_email', get_option('admin_email'));

        $subject = Settings::get('approval_subject', Settings::get_default_approval_subject());
        $body    = Settings::get('approval_body',    Settings::get_default_approval_email());

        $placeholders = [
            '{first_name}'                  => $volunteer->first_name,
            '{last_name}'                   => $volunteer->last_name,
            '{convention_name}'             => $convention_name,
            '{volunteer_coordinator_email}' => $coordinator_email,
        ];

        $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
        $body    = str_replace(array_keys($placeholders), array_values($placeholders), $body);

        $body = preg_replace('/<br\s*\/?>/i', "\n", $body);
        $body = preg_replace('/<\/p>/i', "\n\n", $body);
        $body = wp_strip_all_tags($body);
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = preg_replace('/\n{3,}/', "\n\n", trim($body));

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        return wp_mail($volunteer->email, $subject, $body, $headers);
    }

    /**
     * Send reminder email (24 hours before)
     */
    public static function send_reminder_email($assignment_id) {
        global $wpdb;

        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, v.first_name, v.email, ar.area_name, ar.icon, s.shift_date, s.shift_start, s.shift_end, s.shift_type
             FROM {$wpdb->prefix}oha_volunteer_assignments a
             LEFT JOIN {$wpdb->prefix}oha_volunteers v ON a.volunteer_id = v.id
             LEFT JOIN {$wpdb->prefix}oha_volunteer_areas ar ON a.area_id = ar.id
             LEFT JOIN {$wpdb->prefix}oha_volunteer_shifts s ON a.shift_id = s.id
             WHERE a.id = %d",
            $assignment_id
        ));

        if (!$assignment) {
            return false;
        }

        $email = $assignment->email ?: $assignment->manual_email;
        $name  = $assignment->first_name ?: explode(' ', $assignment->manual_name)[0];

        if (!$email) {
            return false;
        }

        $shift_info = '';
        if ($assignment->shift_type === 'all_day') {
            $shift_info = 'All Day';
        } elseif ($assignment->shift_start) {
            $shift_info = date('g:i A', strtotime($assignment->shift_start)) . ' - ' .
                          date('g:i A', strtotime($assignment->shift_end));
        }

        $convention_name     = Settings::get('convention_name', get_option('oha_vh_convention_name', 'OHA Convention 2026'));
        $convention_date_raw = Settings::get('convention_date', get_option('oha_vh_convention_date', '2026-05-09'));
        $convention_date     = date('l, F j, Y', strtotime($convention_date_raw));
        $coordinator_email   = Settings::get('volunteer_coordinator_email', get_option('admin_email'));
        $coordinator_phone   = Settings::get('volunteer_coordinator_phone', '');

        $subject = Settings::get('reminder_subject', Settings::get_default_reminder_subject());
        $body    = Settings::get('reminder_body',    Settings::get_default_reminder_email());

        $placeholders = [
            '{first_name}'                  => $name,
            '{area_name}'                   => $assignment->icon . ' ' . $assignment->area_name,
            '{shift_time}'                  => $shift_info,
            '{convention_name}'             => $convention_name,
            '{convention_date}'             => $convention_date,
            '{volunteer_coordinator_email}' => $coordinator_email,
            '{volunteer_coordinator_phone}' => $coordinator_phone,
        ];

        $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
        $body    = str_replace(array_keys($placeholders), array_values($placeholders), $body);

        $body = preg_replace('/<br\s*\/?>/i', "\n", $body);
        $body = preg_replace('/<\/p>/i', "\n\n", $body);
        $body = wp_strip_all_tags($body);
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = preg_replace('/\n{3,}/', "\n\n", trim($body));

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        return wp_mail($email, $subject, $body, $headers);
    }

    /**
     * Send message from area manager to volunteer(s)
     */
    public static function send_area_message($to, $volunteer_name, $subject, $message, $area) {
        $convention_date_raw = Settings::get('convention_date', '2026-05-09');
        $formatted_date = date('l, F j, Y', strtotime($convention_date_raw));

        $email_body  = "Dear {$volunteer_name},\n\n";
        $email_body .= "You have a message from your {$area->icon} {$area->area_name} area manager:\n\n";
        $email_body .= "--------------------------------------\n\n";
        $email_body .= $message . "\n\n";
        $email_body .= "--------------------------------------\n\n";

        $managers = json_decode($area->area_managers, true) ?: [];
        if (!empty($managers)) {
            $email_body .= "YOUR AREA MANAGERS:\n";
            foreach ($managers as $manager_id) {
                $manager = get_user_by('id', $manager_id);
                if ($manager) {
                    $email_body .= "- {$manager->display_name} - {$manager->user_email}\n";
                }
            }
            $email_body .= "\n";
        }

        $email_body .= "OHA Convention 2026\n";
        $email_body .= "Date: {$formatted_date}\n\n";
        $email_body .= "Visit your volunteer portal: " . home_url('/volunteer-portal/') . "\n\n";
        $email_body .= "Thank you for volunteering!\n\n";
        $email_body .= "-- The OHA Convention Team";

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        return wp_mail($to, $subject, $email_body, $headers);
    }
}
