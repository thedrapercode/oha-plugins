<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_Attendee_Importer {

	// CSV column positions (0-based). Matches the ticketing export format:
	// Date | Event Name | Last Name | First Name | Purchase Amount | Ticket Level |
	// Email | Add-On Name | Phone | Address 1 | Address 2 | City | State | Zip | Country
	const COL_DATE   = 0;
	const COL_EVENT  = 1;
	const COL_LAST   = 2;
	const COL_FIRST  = 3;
	const COL_AMOUNT = 4;
	const COL_TICKET = 5;
	const COL_EMAIL  = 6;
	const COL_ADDON  = 7;
	const COL_PHONE  = 8;
	const COL_ADDR1  = 9;
	const COL_ADDR2  = 10;
	const COL_CITY   = 11;
	const COL_STATE  = 12;
	const COL_ZIP    = 13;
	const COL_COUNTRY = 14;

	// ------------------------------------------------------------------
	// Admin POST: receive the uploaded file, stash it, redirect to preview
	// ------------------------------------------------------------------
	public static function handle_upload() : void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'oha_attendee_upload' );

		$error_url = admin_url( 'admin.php?page=oha-attendee-import&upload_error=1' );

		if ( empty( $_FILES['csv_file'] ) || (int) $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_safe_redirect( $error_url ); exit;
		}

		$ext = strtolower( pathinfo( $_FILES['csv_file']['name'], PATHINFO_EXTENSION ) );
		if ( $ext !== 'csv' ) {
			wp_safe_redirect( add_query_arg( 'upload_error', 'bad_type', $error_url ) ); exit;
		}

		$dest = WP_CONTENT_DIR . '/oha-attendee-tmp-' . get_current_user_id() . '.csv';
		if ( ! move_uploaded_file( $_FILES['csv_file']['tmp_name'], $dest ) ) {
			wp_safe_redirect( $error_url ); exit;
		}

		set_transient( 'oha_attendee_csv_' . get_current_user_id(), $dest, HOUR_IN_SECONDS );

		$year = isset( $_POST['year'] ) ? (int) $_POST['year'] : (int) date( 'Y' );
		wp_safe_redirect( admin_url( 'admin.php?page=oha-attendee-import&stage=preview&year=' . $year ) );
		exit;
	}

	// ------------------------------------------------------------------
	// AJAX: return preview stats + first 10 rows
	// ------------------------------------------------------------------
	public static function ajax_preview() : void {
		check_ajax_referer( 'oha_attendee_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		$year     = isset( $_POST['year'] ) ? (int) $_POST['year'] : (int) date( 'Y' );
		$csv_path = get_transient( 'oha_attendee_csv_' . get_current_user_id() );

		if ( ! $csv_path || ! file_exists( $csv_path ) ) {
			wp_send_json_error( 'Upload not found. Please re-upload the file.' );
		}

		$rows = self::parse_csv( $csv_path );
		if ( is_wp_error( $rows ) ) wp_send_json_error( $rows->get_error_message() );

		$new_count  = 0;
		$exist_count = 0;
		$skipped    = 0;
		$preview    = [];

		foreach ( $rows as $i => $row ) {
			$email = sanitize_email( $row[ self::COL_EMAIL ] ?? '' );
			if ( ! is_email( $email ) ) { $skipped++; continue; }

			$is_new = ! get_user_by( 'email', $email );
			$is_new ? $new_count++ : $exist_count++;

			if ( count( $preview ) < 10 ) {
				$preview[] = [
					'first'  => sanitize_text_field( $row[ self::COL_FIRST ]  ?? '' ),
					'last'   => sanitize_text_field( $row[ self::COL_LAST ]   ?? '' ),
					'email'  => $email,
					'ticket' => sanitize_text_field( $row[ self::COL_TICKET ] ?? '' ),
					'status' => $is_new ? 'new' : 'existing',
				];
			}
		}

		wp_send_json_success( [
			'year'     => $year,
			'total'    => count( $rows ),
			'new'      => $new_count,
			'existing' => $exist_count,
			'skipped'  => $skipped,
			'preview'  => $preview,
		] );
	}

	// ------------------------------------------------------------------
	// AJAX: run the full import
	// ------------------------------------------------------------------
	public static function ajax_import() : void {
		check_ajax_referer( 'oha_attendee_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

		$year     = isset( $_POST['year'] ) ? (int) $_POST['year'] : (int) date( 'Y' );
		$csv_path = get_transient( 'oha_attendee_csv_' . get_current_user_id() );

		if ( ! $csv_path || ! file_exists( $csv_path ) ) {
			wp_send_json_error( 'Upload not found. Please re-upload the file.' );
		}

		$rows = self::parse_csv( $csv_path );
		if ( is_wp_error( $rows ) ) wp_send_json_error( $rows->get_error_message() );

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors  = [];

		foreach ( $rows as $row ) {
			$email = sanitize_email( $row[ self::COL_EMAIL ] ?? '' );
			if ( ! is_email( $email ) ) { $skipped++; continue; }

			$first   = sanitize_text_field( $row[ self::COL_FIRST ]   ?? '' );
			$last    = sanitize_text_field( $row[ self::COL_LAST ]    ?? '' );
			$ticket  = sanitize_text_field( $row[ self::COL_TICKET ]  ?? '' );
			$addon   = sanitize_text_field( $row[ self::COL_ADDON ]   ?? '' );
			$amount  = sanitize_text_field( $row[ self::COL_AMOUNT ]  ?? '' );
			$phone   = sanitize_text_field( $row[ self::COL_PHONE ]   ?? '' );
			$addr1   = sanitize_text_field( $row[ self::COL_ADDR1 ]   ?? '' );
			$addr2   = sanitize_text_field( $row[ self::COL_ADDR2 ]   ?? '' );
			$city    = sanitize_text_field( $row[ self::COL_CITY ]    ?? '' );
			$state   = sanitize_text_field( $row[ self::COL_STATE ]   ?? '' );
			$zip     = sanitize_text_field( $row[ self::COL_ZIP ]     ?? '' );
			$country = sanitize_text_field( $row[ self::COL_COUNTRY ] ?? '' );
			$event   = sanitize_text_field( $row[ self::COL_EVENT ]   ?? '' );

			$wp_user = get_user_by( 'email', $email );
			$is_new  = false;

			if ( ! $wp_user ) {
				// Build a unique username
				$base = sanitize_user( strtolower( $first . '_' . $last ), true );
				if ( ! $base ) $base = sanitize_user( strtolower( strstr( $email, '@', true ) ), true );
				if ( ! $base ) $base = 'attendee';
				$username = $base;
				$suffix   = 1;
				while ( username_exists( $username ) ) {
					$username = $base . $suffix++;
				}

				$user_id = wp_insert_user( [
					'user_login'   => $username,
					'user_email'   => $email,
					'first_name'   => $first,
					'last_name'    => $last,
					'display_name' => trim( $first . ' ' . $last ) ?: $username,
					'role'         => 'oha_convention_attendee',
					'user_pass'    => wp_generate_password( 20, true, false ),
				] );

				if ( is_wp_error( $user_id ) ) {
					$errors[] = "Could not create user for {$email}: " . $user_id->get_error_message();
					$skipped++;
					continue;
				}

				$wp_user = get_user_by( 'ID', $user_id );
				$is_new  = true;
				$created++;

				// Set UsersWP form type so UWP doesn't default these accounts to form 1 (OHA Member).
				// Form ID is configured in Attendee Hub > Settings.
				$uwp_form_id = (int) get_option( 'oha_attendee_uwp_form_id', 4 );
				if ( $uwp_form_id ) {
					update_user_meta( $user_id, '_uwp_register_form_id', $uwp_form_id );
				}
			} else {
				$user_id = $wp_user->ID;
				// Fill in missing name fields
				if ( $first && ! $wp_user->first_name )
					update_user_meta( $user_id, 'first_name', $first );
				if ( $last && ! $wp_user->last_name )
					update_user_meta( $user_id, 'last_name', $last );
				$updated++;
			}

			// Fill in contact fields only if blank on account
			$meta_map = [
				'oha_phone'     => $phone,
				'oha_address_1' => $addr1,
				'oha_address_2' => $addr2,
				'oha_city'      => $city,
				'oha_state'     => $state,
				'oha_zip'       => $zip,
				'oha_country'   => $country,
			];
			foreach ( $meta_map as $key => $value ) {
				if ( $value && ! get_user_meta( $user_id, $key, true ) ) {
					update_user_meta( $user_id, $key, $value );
				}
			}

			// Track convention years on user meta
			$years = get_user_meta( $user_id, 'oha_convention_years', true );
			$years = is_array( $years ) ? $years : [];
			if ( ! in_array( $year, $years, true ) ) {
				$years[] = $year;
				update_user_meta( $user_id, 'oha_convention_years', $years );
			}

			// Store per-year ticket data on user meta
			update_user_meta( $user_id, "oha_convention_{$year}_ticket_level",    $ticket );
			update_user_meta( $user_id, "oha_convention_{$year}_add_on",          $addon );
			update_user_meta( $user_id, "oha_convention_{$year}_purchase_amount", $amount );

			// Upsert the attendee record
			OHA_Attendee_DB::upsert( [
				'year'            => $year,
				'email'           => $email,
				'first_name'      => $first,
				'last_name'       => $last,
				'event_name'      => $event,
				'ticket_level'    => $ticket,
				'add_on_name'     => $addon,
				'purchase_amount' => $amount,
				'phone'           => $phone,
				'address_1'       => $addr1,
				'address_2'       => $addr2,
				'city'            => $city,
				'state'           => $state,
				'zip'             => $zip,
				'country'         => $country,
				'import_date'     => current_time( 'mysql' ),
				'user_id'         => $user_id,
				'wp_user_created' => $is_new ? 1 : 0,
				'welcome_sent'    => 0,
			] );
		}

		// Clean up temp file
		@unlink( $csv_path );
		delete_transient( 'oha_attendee_csv_' . get_current_user_id() );

		wp_send_json_success( [
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
			'errors'  => $errors,
		] );
	}

	// ------------------------------------------------------------------
	// Parse CSV, skip header row, skip blank rows
	// ------------------------------------------------------------------
	private static function parse_csv( string $path ) : array|\WP_Error {
		$handle = @fopen( $path, 'r' );
		if ( ! $handle ) return new WP_Error( 'file_error', 'Could not open the CSV file.' );

		$rows           = [];
		$header_skipped = false;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( ! $header_skipped ) {
				$header_skipped = true;
				continue;
			}
			if ( ! array_filter( $row ) ) continue;
			$rows[] = $row;
		}
		fclose( $handle );
		return $rows;
	}
}
