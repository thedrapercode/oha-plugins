<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OHA_Attendee_DB {

	const SCHEMA_VERSION = '1.0.0';
	const SCHEMA_OPTION  = 'oha_attendee_schema_version';

	public static function table() : string {
		global $wpdb;
		return $wpdb->prefix . 'oha_attendees';
	}

	public static function create_table() : void {
		global $wpdb;
		$t       = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$t} (
			id               bigint(20)   NOT NULL AUTO_INCREMENT,
			year             int(4)       NOT NULL DEFAULT 2026,
			email            varchar(200) NOT NULL DEFAULT '',
			first_name       varchar(100) NOT NULL DEFAULT '',
			last_name        varchar(100) NOT NULL DEFAULT '',
			event_name       varchar(200) NOT NULL DEFAULT '',
			ticket_level     varchar(200) NOT NULL DEFAULT '',
			add_on_name      varchar(200) NOT NULL DEFAULT '',
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
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
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
	 * Returns unique individual add-on names across all attendees.
	 * Add-ons are stored comma-separated so we split them in PHP.
	 */
	public static function get_distinct_addons( int $year = 0 ) : array {
		global $wpdb;
		$t = self::table();
		if ( $year ) {
			$rows = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT add_on_name FROM {$t} WHERE year = %d AND add_on_name != ''", $year ) );
		} else {
			$rows = $wpdb->get_col( "SELECT DISTINCT add_on_name FROM {$t} WHERE add_on_name != ''" );
		}
		$addons = [];
		foreach ( $rows as $val ) {
			foreach ( array_map( 'trim', explode( ',', $val ) ) as $a ) {
				if ( $a && ! in_array( $a, $addons, true ) ) $addons[] = $a;
			}
		}
		sort( $addons );
		return $addons;
	}

	/**
	 * Returns all rows for a year (no pagination) for stats computation.
	 */
	public static function get_all_for_stats( int $year ) : array {
		global $wpdb;
		$t = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE year = %d ORDER BY last_name ASC", $year ) );
	}
}
