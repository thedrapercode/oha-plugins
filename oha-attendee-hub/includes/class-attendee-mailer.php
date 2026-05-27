<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_Attendee_Mailer {

	// ------------------------------------------------------------------
	// Option accessors
	// ------------------------------------------------------------------
	public static function get_subject() : string {
		return get_option( 'oha_attendee_welcome_subject', 'Welcome to OHA Convention {{year}} - {{site_name}}' );
	}

	public static function get_body() : string {
		return get_option( 'oha_attendee_welcome_body', self::default_body() );
	}

	public static function get_from_name() : string {
		return get_option( 'oha_attendee_welcome_from_name', get_bloginfo( 'name' ) );
	}

	public static function get_from_email() : string {
		return get_option( 'oha_attendee_welcome_from_email', (string) get_option( 'admin_email' ) );
	}

	private static function default_body() : string {
		return "Hello {{first_name}},\n\nThank you for attending OHA Convention {{year}}. Your ticket level was: {{ticket_level}}.\n\nYou can log in or create a password for your OHA account at the link below. Your username is: {{username}}\n\n{{set_password_link}}\n\nWe hope to see you at the next convention.\n\nThe OHA Team\n{{site_url}}";
	}

	// ------------------------------------------------------------------
	// Render: replace merge tags in subject and body
	// ------------------------------------------------------------------
	public static function render( object $attendee, string $subject_tpl, string $body_tpl ) : array {
		$user = $attendee->user_id ? get_user_by( 'ID', (int) $attendee->user_id ) : null;

		$set_password_link = '';
		if ( $user && (int) $attendee->wp_user_created === 1 ) {
			$key = get_password_reset_key( $user );
			if ( ! is_wp_error( $key ) ) {
				$reset_url         = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );
				$set_password_link = 'Set your password: ' . $reset_url;
			}
		}

		$tags = [
			'{{first_name}}'       => $attendee->first_name,
			'{{last_name}}'        => $attendee->last_name,
			'{{email}}'            => $attendee->email,
			'{{username}}'         => $user ? $user->user_login : '',
			'{{year}}'             => (string) $attendee->year,
			'{{ticket_level}}'     => $attendee->ticket_level,
			'{{add_ons}}'          => $attendee->add_on_name,
			'{{purchase_amount}}'  => $attendee->purchase_amount,
			'{{set_password_link}}' => $set_password_link,
			'{{site_name}}'        => get_bloginfo( 'name' ),
			'{{site_url}}'         => get_bloginfo( 'url' ),
		];

		return [
			'subject' => str_replace( array_keys( $tags ), array_values( $tags ), $subject_tpl ),
			'body'    => str_replace( array_keys( $tags ), array_values( $tags ), $body_tpl ),
		];
	}

	// ------------------------------------------------------------------
	// AJAX: preview rendered email using first attendee for the year
	// ------------------------------------------------------------------
	public static function ajax_preview() : void {
		check_ajax_referer( 'oha_attendee_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		$year    = isset( $_POST['year'] ) ? (int) $_POST['year'] : (int) date( 'Y' );
		// wp_unslash before using — do NOT sanitize further so special chars are preserved
		$subject = wp_unslash( $_POST['subject'] ?? '' );
		$body    = wp_unslash( $_POST['body']    ?? '' );

		$samples = OHA_Attendee_DB::get_attendees( [ 'year' => $year, 'limit' => 1 ] );
		if ( empty( $samples ) ) {
			wp_send_json_error( 'No attendees found for ' . $year . '. Import attendees first.' );
		}

		wp_send_json_success( self::render( $samples[0], $subject, $body ) );
	}

	// ------------------------------------------------------------------
	// AJAX: save template options (subject, body, from name/email)
	// The body is stored as-is (after wp_unslash only) so all characters
	// typed by the admin are preserved exactly — no HTML stripping.
	// ------------------------------------------------------------------
	public static function ajax_save_template() : void {
		check_ajax_referer( 'oha_attendee_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		// Subject: plain text — sanitize_text_field is fine
		update_option( 'oha_attendee_welcome_subject',    sanitize_text_field( wp_unslash( $_POST['subject']    ?? '' ) ) );
		// Body: preserve all characters — wp_unslash only, no stripping
		update_option( 'oha_attendee_welcome_body',       wp_unslash( $_POST['body']       ?? '' ) );
		update_option( 'oha_attendee_welcome_from_name',  sanitize_text_field( wp_unslash( $_POST['from_name']  ?? '' ) ) );
		update_option( 'oha_attendee_welcome_from_email', sanitize_email(      wp_unslash( $_POST['from_email'] ?? '' ) ) );

		wp_send_json_success();
	}

	// ------------------------------------------------------------------
	// AJAX: return unsent count for a given year
	// ------------------------------------------------------------------
	public static function ajax_unsent_count() : void {
		check_ajax_referer( 'oha_attendee_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		$year = isset( $_POST['year'] ) ? (int) $_POST['year'] : (int) date( 'Y' );
		wp_send_json_success( [ 'count' => OHA_Attendee_DB::count_unsent( $year ) ] );
	}

	// ------------------------------------------------------------------
	// AJAX: send one batch of welcome emails
	// Saves template first, then sends up to $batch unsent rows.
	// Called repeatedly by the admin JS until remaining = 0.
	// ------------------------------------------------------------------
	public static function ajax_send_batch() : void {
		check_ajax_referer( 'oha_attendee_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		$year    = isset( $_POST['year'] )  ? (int) $_POST['year']  : (int) date( 'Y' );
		$batch   = isset( $_POST['batch'] ) ? (int) $_POST['batch'] : 25;
		$subject = wp_unslash( $_POST['subject']    ?? self::get_subject() );
		$body    = wp_unslash( $_POST['body']       ?? self::get_body() );
		$f_name  = wp_unslash( $_POST['from_name']  ?? self::get_from_name() );
		$f_email = sanitize_email( wp_unslash( $_POST['from_email'] ?? self::get_from_email() ) );

		// Persist template on every batch so a page refresh restores the current draft
		update_option( 'oha_attendee_welcome_subject',    sanitize_text_field( $subject ) );
		update_option( 'oha_attendee_welcome_body',       $body );
		update_option( 'oha_attendee_welcome_from_name',  sanitize_text_field( $f_name ) );
		update_option( 'oha_attendee_welcome_from_email', $f_email );

		$rows    = OHA_Attendee_DB::get_unsent( $year, $batch );
		$sent    = 0;
		$failed  = 0;
		$from    = $f_name . ' <' . $f_email . '>';
		$headers = [ 'Content-Type: text/plain; charset=UTF-8', 'From: ' . $from ];

		foreach ( $rows as $attendee ) {
			$rendered = self::render( $attendee, $subject, $body );
			$ok = wp_mail( $attendee->email, $rendered['subject'], $rendered['body'], $headers );
			if ( $ok ) {
				OHA_Attendee_DB::mark_welcome_sent( (int) $attendee->id );
				$sent++;
			} else {
				$failed++;
			}
		}

		wp_send_json_success( [
			'sent'      => $sent,
			'failed'    => $failed,
			'remaining' => OHA_Attendee_DB::count_unsent( $year ),
		] );
	}
}
