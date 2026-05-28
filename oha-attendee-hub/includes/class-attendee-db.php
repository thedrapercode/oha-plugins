<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_Attendee_DB {

	const SCHEMA_VERSION = '1.1.0';
	const SCHEMA_OPTION  = 'oha_attendee_schema_version';

	public static function table() : string {
		global $wpdb;
		return $wpdb->prefix . 'oha_attendees';
	}

	public static function addons_table() : string {
		global $wpdb;
		return $wpdb->prefix . 'oha_attendee_addons';
	}

	public static function create_table() : void {
		global $wpdb;
		$t       = self::table();
		$ta      = self::addons_table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$t} (
			id               bigint(20)   NOT NULL AUTO_INCREMENT,
			year             int(4)       NOT NULL DEFAULT 2026,
			email            varchar(200) NOT NULL DEFAULT '',
			first_name       varchar(100) NOT NULL DEFAULT '',
			last_name        varchar(100) NOT NULL DEFAULT '',
			event_name       varchar(200) NOT NULL DEFAULT '',
			ticket_level     varchar(200) NOT NULL DEFAULT '',
			add_on_name      varchar(500) NOT NULL DEFAULT '',
			purchase_amount  varchar(50)  NOT NULL DEFAULT '',
			phone            varchar(50)  NOT NULL DEFAULT '',
			address_1        varchar(200) NOT NULL DEFAULT '',
			address_2        varchar(200) NOT NULL DEFAULT '',
			city             varchar(100) NOT NULL DEFAULT '',
			state            varchar(100) NOT NULL DEFAULT '',
			zip              varchar(20)  NOT NULL DEFAULT '',
			country          varchar(100) NOT NULL DEFAULT '',
			import_date      datetime     DEFAULT NULL,
			user_id          bigint(20)   DEFAULT NULL,
			wp_user_created  tinyint(1)   NOT NULL DEFAULT 0,
			welcome_sent     tinyint(1)   NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY email_year (email(191), year),
			KEY user_id (user_id),
			KEY year (year),
			KEY welcome_sent (welcome_sent)
		) {$charset};";

		$sql_addons = "CREATE TABLE {$ta} (
			id           bigint(20)   NOT NULL AUTO_INCREMENT,
			attendee_id  bigint(20)   NOT NULL,
			year         int(4)       NOT NULL DEFAULT 2026,
			email        varchar(200) NOT NULL DEFAULT '',
			addon_name   varchar(200) NOT NULL DEFAULT '',
			amount       decimal(8,2) NOT NULL DEFAULT 0.00,
			PRIMARY KEY  (id),
			KEY attendee_id (attendee_id),
			KEY year (year),
			KEY addon_name (addon_name(191))
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		dbDelta( $sql_addons );
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
	}

	public static function maybe_upgrade() : void {
		if ( get_option( self::SCHEMA_OPTION ) !== self::SCHEMA_VERSION ) {
			self::create_table();
		}
	}

	/**
	 * Insert or update a row identified by (email, year).
	 */
	public static function upsert( array $data ) : int {
		global $wpdb;
		$t          = self::table();
		$existing_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$t} WHERE email = %s AND year = %d",
			$data['email'], $data['year']
		) );
		if ( $existing_id ) {
			$wpdb->update( $t, $data, [ 'id' => (int) $existing_id ] );
			return (int) $existing_id;
		}
		$wpdb->insert( $t, $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete all add-on rows for an attendee. Called before re-inserting on re-import.
	 */
	public static function delete_addons_for_attendee( int $attendee_id ) : void {
		global $wpdb;
		$wpdb->delete( self::addons_table(), [ 'attendee_id' => $attendee_id ] );
	}

	/**
	 * Insert a single add-on row into wp_oha_attendee_addons.
	 */
	public static function insert_addon( array $data ) : void {
		global $wpdb;
		$wpdb->insert( self::addons_table(), $data );
	}

	/**
	 * Get all add-on rows for a specific attendee record.
	 */
	public static function get_addons_for_attendee( int $attendee_id ) : array {
		global $wpdb;
		$ta = self::addons_table();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$ta} WHERE attendee_id = %d ORDER BY addon_name ASC",
			$attendee_id
		) );
	}

	/**
	 * Get all attendees who have a specific add-on in a given year (roster query).
	 */
	public static function get_roster_by_addon( string $addon_name, int $year ) : array {
		global $wpdb;
		$t  = self::table();
		$ta = self::addons_table();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT a.first_name, a.last_name, a.email, a.ticket_level, ad.amount
			 FROM {$ta} ad
			 INNER JOIN {$t} a ON a.id = ad.attendee_id
			 WHERE ad.addon_name = %s AND ad.year = %d
			 ORDER BY a.last_name ASC, a.first_name ASC",
			$addon_name, $year
		) );
	}

	public static function get_attendees( array $args = [] ) : array {
		global $wpdb;
		$t      = self::table();
		$where  = [ '1=1' ];
		$values = [];

		if ( ! empty( $args['year'] ) ) {
			$where[]  = 'year = %d';
			$values[] = (int) $args['year'];
		}
		if ( ! empty( $args['search'] ) ) {
			$s        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s)';
			$values[] = $s; $values[] = $s; $values[] = $s;
		}
		if ( ! empty( $args['ticket_level'] ) ) {
			$where[]  = 'ticket_level = %s';
			$values[] = $args['ticket_level'];
		}
		if ( ! empty( $args['addon'] ) ) {
			$where[]  = 'add_on_name LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['addon'] ) . '%';
		}
		if ( isset( $args['welcome_sent'] ) && $args['welcome_sent'] !== '' ) {
			$where[]  = 'welcome_sent = %d';
			$values[] = (int) $args['welcome_sent'];
		}

		$limit  = isset( $args['limit'] )  ? (int) $args['limit']  : 50;
		$offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;

		$sql      = "SELECT * FROM {$t} WHERE " . implode( ' AND ', $where ) . " ORDER BY last_name ASC, first_name ASC LIMIT %d OFFSET %d";
		$values[] = $limit;
		$values[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, ...$values ) );
	}

	public static function count_attendees( array $args = [] ) : int {
		global $wpdb;
		$t      = self::table();
		$where  = [ '1=1' ];
		$values = [];

		if ( ! empty( $args['year'] ) ) {
			$where[]  = 'year = %d';
			$values[] = (int) $args['year'];
		}
		if ( ! empty( $args['search'] ) ) {
			$s        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s)';
			$values[] = $s; $values[] = $s; $values[] = $s;
		}
		if ( ! empty( $args['ticket_level'] ) ) {
			$where[]  = 'ticket_level = %s';
			$values[] = $args['ticket_level'];
		}
		if ( ! empty( $args['addon'] ) ) {
			$where[]  = 'add_on_name LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $args['addon'] ) . '%';
		}
		if ( isset( $args['welcome_sent'] ) && $args['welcome_sent'] !== '' ) {
			$where[]  = 'welcome_sent = %d';
			$values[] = (int) $args['welcome_sent'];
		}

		$sql = "SELECT COUNT(*) FROM {$t} WHERE " . implode( ' AND ', $where );
		return (int) ( $values ? $wpdb->get_var( $wpdb->prepare( $sql, ...$values ) ) : $wpdb->get_var( $sql ) );
	}

	public static function get_years() : array {
		global $wpdb;
		return $wpdb->get_col( "SELECT DISTINCT year FROM {$wpdb->prefix}oha_attendees ORDER BY year DESC" );
	}

	public static function get_unsent( int $year, int $limit = 50 ) : array {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$t} WHERE year = %d AND welcome_sent = 0 AND user_id IS NOT NULL LIMIT %d",
			$year, $limit
		) );
	}

	public static function count_unsent( int $year ) : int {
		global $wpdb;
		$t = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$t} WHERE year = %d AND welcome_sent = 0 AND user_id IS NOT NULL",
			$year
		) );
	}

	public static function mark_welcome_sent( int $id ) : void {
		global $wpdb;
		$wpdb->update( self::table(), [ 'welcome_sent' => 1 ], [ 'id' => $id ] );
	}

	public static function get_distinct_ticket_levels( int $year = 0 ) : array {
		global $wpdb;
		$t = self::table();
		if ( $year ) {
			return $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT ticket_level FROM {$t} WHERE year = %d AND ticket_level != '' ORDER BY ticket_level ASC", $year ) );
		}
		return $wpdb->get_col( "SELECT DISTINCT ticket_level FROM {$t} WHERE ticket_level != '' ORDER BY ticket_level ASC" );
	}

	/**
	 * Returns unique add-on names. Queries the addons table first; falls back to
	 * comma-sep parsing on the main table for records imported before v1.1.0.
	 */
	public static function get_distinct_addons( int $year = 0 ) : array {
		global $wpdb;
		$ta = self::addons_table();
		if ( $year ) {
			$addons = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT addon_name FROM {$ta} WHERE year = %d AND addon_name != '' ORDER BY addon_name ASC", $year ) );
		} else {
			$addons = $wpdb->get_col( "SELECT DISTINCT addon_name FROM {$ta} WHERE addon_name != '' ORDER BY addon_name ASC" );
		}
		if ( $addons ) return $addons;

		// Fall back: parse comma-sep from main table (pre-v1.1.0 data)
		$t = self::table();
		if ( $year ) {
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT add_on_name FROM {$t} WHERE year = %d AND add_on_name != ''", $year ) );
		} else {
			$rows = $wpdb->get_col( "SELECT DISTINCT add_on_name FROM {$t} WHERE add_on_name != ''" );
		}
		$result = [];
		foreach ( $rows as $val ) {
			foreach ( array_map( 'trim', explode( ',', $val ) ) as $a ) {
				if ( $a && ! in_array( $a, $result, true ) ) $result[] = $a;
			}
		}
		sort( $result );
		return $result;
	}

	/**
	 * Returns all rows for a year (no pagination) for stats computation.
	 */
	public static function get_all_for_stats( int $year ) : array {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE year = %d ORDER BY last_name ASC", $year ) );
	}

	/**
	 * Returns per-addon counts and revenue from the addons table (v1.1.0+ imports).
	 * Returns empty array if the addons table has no data for the year.
	 */
	public static function get_addon_stats( int $year ) : array {
		global $wpdb;
		$ta   = self::addons_table();
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT addon_name, COUNT(*) as cnt, SUM(amount) as total FROM {$ta} WHERE year = %d AND addon_name != '' GROUP BY addon_name ORDER BY cnt DESC",
			$year
		) );
		$result = [];
		foreach ( $rows as $r ) {
			$result[ $r->addon_name ] = [ 'count' => (int) $r->cnt, 'total' => (float) $r->total ];
		}
		return $result;
	}
}
