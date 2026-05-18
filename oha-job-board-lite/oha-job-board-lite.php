<?php
/*
Plugin Name: OHA Job Board Lite
Description: Modern job board for Ohio Haunters Association. Haunt listing owners and OHA members post jobs. GeoDirectory integration, Board Hub moderation panel, email apply, and external link apply.
Version: 0.5.0
Author: OHA
Requires Plugins: geodirectory
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OJB_VERSION', '0.5.0' );
define( 'OJB_URL',     plugin_dir_url( __FILE__ ) );
define( 'OJB_PATH',    plugin_dir_path( __FILE__ ) );

class OHA_Job_Board_Lite {

	const CPT     = 'oha_job';
	const CPT_APP = 'oha_job_app';
	const OPTION  = 'oha_job_board_options';

	public function __construct() {
		add_action( 'init',               [ $this, 'register_statuses' ] );
		add_action( 'init',               [ $this, 'register_cpts' ] );
		add_action( 'init',               [ $this, 'handle_actions' ] );
		add_action( 'add_meta_boxes',     [ $this, 'register_metaboxes' ] );
		add_action( 'save_post',          [ $this, 'save_job_meta' ] );
		add_action( 'admin_menu',         [ $this, 'admin_menu' ] );
		add_action( 'admin_init',         [ $this, 'register_settings' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'frontend_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );

		// AJAX
		add_action( 'wp_ajax_oha_search_places',        [ $this, 'ajax_search_places' ] );
		add_action( 'wp_ajax_nopriv_oha_search_places', [ $this, 'ajax_search_places' ] );
		add_action( 'wp_ajax_oha_app_status',           [ $this, 'ajax_app_status' ] );

		// Shortcodes
		add_shortcode( 'oha_job_list',       [ $this, 'sc_job_list' ] );
		add_shortcode( 'oha_job_submit',     [ $this, 'sc_job_submit' ] );
		add_shortcode( 'oha_job_apply',      [ $this, 'sc_job_apply' ] );
		add_shortcode( 'oha_my_jobs',        [ $this, 'sc_my_jobs' ] );
		add_shortcode( 'oha_job_admin',      [ $this, 'sc_job_admin' ] );
		add_shortcode( 'oha_jobs_boardhub',  [ $this, 'sc_jobs_boardhub' ] );
		add_shortcode( 'oha_haunt_jobs',     [ $this, 'sc_haunt_jobs' ] );
		add_shortcode( 'oha_job_applicants', [ $this, 'sc_job_applicants' ] );
	}

	/* =========================================================
	 * STATUSES
	 * ======================================================= */
	public function register_statuses() {
		$statuses = [
			'oha_denied'     => 'Denied',
			'oha_needs_info' => 'Needs Info',
			'oha_archived'   => 'Archived',
		];
		foreach ( $statuses as $status => $label ) {
			register_post_status( $status, [
				'label'                     => $label,
				'public'                    => false,
				'protected'                 => true,
				'show_in_admin_status_list' => true,
				'show_in_admin_all_list'    => true,
				'label_count'               => _n_noop(
					$label . ' <span class="count">(%s)</span>',
					$label . ' <span class="count">(%s)</span>'
				),
			] );
		}
	}

	/* =========================================================
	 * CPTs
	 * ======================================================= */
	public function register_cpts() {
		register_post_type( self::CPT, [
			'label'        => 'Jobs',
			'public'       => true,
			'has_archive'  => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-id',
			'supports'     => [ 'title', 'editor', 'author', 'excerpt' ],
			'rewrite'      => [ 'slug' => 'haunt-jobs' ],
		] );
		register_post_type( self::CPT_APP, [
			'label'    => 'Job Applications',
			'public'   => false,
			'show_ui'  => false,
			'supports' => [ 'title', 'editor', 'author' ],
		] );
	}

	/* =========================================================
	 * FIELD DEFINITIONS
	 * ======================================================= */
	private function fields_def() {
		return [
			'role_type'     => [
				'label'    => 'Role Type',
				'type'     => 'select',
				'required' => true,
				'choices'  => [ 'Actor', 'Crew', 'Makeup', 'Security', 'Customer Service', 'Management', 'Volunteer', 'Technical', 'Photography / Video', 'Other' ],
			],
			'employment'    => [
				'label'    => 'Employment Type',
				'type'     => 'select',
				'required' => true,
				'choices'  => [ 'Seasonal', 'Part-time', 'Full-time', 'Contract', 'Volunteer' ],
			],
			'pay'           => [
				'label'       => 'Pay / Compensation',
				'type'        => 'text',
				'required'    => false,
				'placeholder' => 'e.g. $12/hr, TBD, Volunteer',
			],
			'start_date'    => [ 'label' => 'Start Date',        'type' => 'date',  'required' => false ],
			'end_date'      => [ 'label' => 'End Date / Expiry', 'type' => 'date',  'required' => false ],
			'contact_email' => [
				'label'       => 'Contact Email',
				'type'        => 'email',
				'required'    => false,
				'placeholder' => 'Applications sent here',
			],
			'contact_url'   => [
				'label'       => 'Application Link',
				'type'        => 'url',
				'required'    => false,
				'placeholder' => 'https://',
			],
			'place_id'      => [ 'label' => 'Link to Haunt Listing', 'type' => 'gd_place', 'required' => false ],
			'city'          => [ 'label' => 'City',  'type' => 'text', 'required' => false ],
			'state'         => [ 'label' => 'State', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g. OH' ],
		];
	}

	private function app_fields_def() {
		return [
			'age_range'    => [
				'label'   => 'Age Range',
				'type'    => 'select',
				'choices' => [ 'Under 16', '16-17', '18-20', '21+', 'Prefer not to say' ],
			],
			'experience'   => [
				'label'   => 'Haunt Experience',
				'type'    => 'select',
				'choices' => [ 'First Season', '1-2 Seasons', '3-5 Seasons', 'Veteran (6+)' ],
			],
			'availability' => [
				'label'   => 'Availability',
				'type'    => 'select',
				'choices' => [ 'All Nights', 'Weekends Only', 'Fridays and Saturdays', 'Flexible - Contact Me' ],
			],
			'phone'        => [
				'label'       => 'Phone Number',
				'type'        => 'tel',
				'placeholder' => 'Optional',
			],
			'video_url'    => [
				'label'       => 'Casting Video Link',
				'type'        => 'url',
				'placeholder' => 'YouTube, TikTok, or Vimeo URL',
			],
		];
	}

	private function video_info( $url ) {
		if ( ! $url ) return null;
		if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m ) ) {
			return [
				'type'  => 'youtube',
				'thumb' => 'https://img.youtube.com/vi/' . $m[1] . '/mqdefault.jpg',
				'url'   => $url,
			];
		}
		return [ 'type' => 'other', 'thumb' => null, 'url' => $url ];
	}

	/* =========================================================
	 * ACCESS HELPERS
	 * ======================================================= */
	private function user_can_post_job() {
		if ( ! is_user_logged_in() ) return false;

		$uid = get_current_user_id();
		$o   = get_option( self::OPTION, [] );

		// Admins / editors always allowed
		if ( current_user_can( 'edit_others_posts' ) ) return true;

		// Listing owners bypass membership gate if option enabled
		if ( ! empty( $o['listing_owners_can_post'] ) ) {
			$has_place = get_posts( [
				'post_type'      => 'gd_place',
				'post_status'    => 'publish',
				'author'         => $uid,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			] );
			if ( ! empty( $has_place ) ) return true;
		}

		// No membership gate — any logged-in user allowed
		if ( empty( $o['membership_required'] ) ) return true;

		// UsersWP account type check
		$uwp_type = get_user_meta( $uid, 'uwp_account_type', true );
		if ( $uwp_type && in_array( $uwp_type, [ 'member', 'board-member', 'paid', 'oha_member', 'listing_owner' ], true ) ) return true;

		// WordPress role check (non-subscriber roles assumed to be members)
		$user = wp_get_current_user();
		if ( ! empty( $user->roles ) ) {
			$non_sub = array_diff( (array) $user->roles, [ 'subscriber' ] );
			if ( ! empty( $non_sub ) ) return true;
		}

		// Board Hub membership flag (legacy)
		if ( get_user_meta( $uid, 'oha_card_sent', true ) ) return true;

		return false;
	}

	private function is_moderator() {
		return current_user_can( 'edit_others_posts' ) || current_user_can( 'manage_options' );
	}

	/* =========================================================
	 * ADMIN METABOX
	 * ======================================================= */
	public function register_metaboxes() {
		add_meta_box( 'oha_job_details', 'Job Details', [ $this, 'metabox_render' ], self::CPT, 'normal', 'high' );
	}

	public function metabox_render( $post ) {
		wp_nonce_field( 'oha_job_save', 'oha_job_nonce' );
		echo '<table class="form-table"><tbody>';
		foreach ( $this->fields_def() as $key => $def ) {
			$val = get_post_meta( $post->ID, "_oha_$key", true );
			echo '<tr><th><label for="oha_' . esc_attr( $key ) . '">' . esc_html( $def['label'] ) . '</label></th><td>';
			$this->render_field( $key, $def, $val, 'admin' );
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public function save_job_meta( $post_id ) {
		if ( ! isset( $_POST['oha_job_nonce'] ) || ! wp_verify_nonce( $_POST['oha_job_nonce'], 'oha_job_save' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( get_post_type( $post_id ) !== self::CPT ) return;
		foreach ( $this->fields_def() as $key => $def ) {
			$this->save_field( $post_id, $key, $def );
		}
	}

	/* =========================================================
	 * FIELD RENDER HELPER
	 * ======================================================= */
	private function render_field( $key, $def, $val = '', $context = 'frontend' ) {
		$name = 'oha_' . $key;
		$uid  = get_current_user_id();
		$ph   = isset( $def['placeholder'] ) ? ' placeholder="' . esc_attr( $def['placeholder'] ) . '"' : '';

		switch ( $def['type'] ) {
			case 'select':
				echo '<select name="' . esc_attr( $name ) . '" id="oha_' . esc_attr( $key ) . '" class="ojb-select">';
				if ( $context === 'frontend' ) echo '<option value="">— Select —</option>';
				foreach ( $def['choices'] as $c ) {
					printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $c ), selected( $val, $c, false ) );
				}
				echo '</select>';
				break;

			case 'gd_place':
				$sel = intval( $val );
				if ( $this->is_moderator() ) {
					$search_val = $sel ? esc_attr( get_the_title( $sel ) ) : '';
					echo '<input type="text" class="' . ( $context === 'admin' ? 'regular-text ' : '' ) . 'ojb-place-search" data-target="#oha_' . esc_attr( $key ) . '" placeholder="Search haunts..." value="' . $search_val . '" />';
					echo '<input type="hidden" name="' . esc_attr( $name ) . '" id="oha_' . esc_attr( $key ) . '" value="' . esc_attr( $sel ) . '" />';
					if ( $context === 'admin' ) echo '<p class="description">Type 2+ characters to search GeoDirectory places.</p>';
				} else {
					$places = get_posts( [ 'post_type' => 'gd_place', 'posts_per_page' => 200, 'post_status' => 'publish', 'author' => $uid ] );
					echo '<select name="' . esc_attr( $name ) . '" id="oha_' . esc_attr( $key ) . '" class="ojb-select"><option value="">— Select your haunt —</option>';
					foreach ( $places as $p ) {
						printf( '<option value="%d" %s>%s</option>', $p->ID, selected( $sel, $p->ID, false ), esc_html( $p->post_title ) );
					}
					echo '</select>';
				}
				$o = get_option( self::OPTION, [] );
				if ( ! empty( $o['add_haunt_url'] ) ) {
					echo '<br><a href="' . esc_url( $o['add_haunt_url'] ) . '" target="_blank" rel="noopener" class="ojb-link-small">+ Add a New Haunt</a>';
				}
				break;

			default:
				printf(
					'<input type="%s" name="%s" id="oha_%s" value="%s" class="ojb-input"%s />',
					esc_attr( $def['type'] ),
					esc_attr( $name ),
					esc_attr( $key ),
					esc_attr( $val ),
					$ph
				);
		}
	}

	private function save_field( $post_id, $key, $def ) {
		$field = 'oha_' . $key;
		$val   = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : '';

		if ( $def['type'] === 'email' )    $val = sanitize_email( $val );
		elseif ( $def['type'] === 'url' )  $val = esc_url_raw( $val );
		elseif ( $def['type'] === 'gd_place' ) $val = intval( $val );
		else $val = sanitize_text_field( $val );

		update_post_meta( $post_id, "_oha_$key", $val );
	}

	/* =========================================================
	 * ADMIN MENU & SETTINGS
	 * ======================================================= */
	public function admin_menu() {
		$parent = 'oha-board-hub';
		$cap    = 'edit_posts';
		if ( function_exists( 'menu_page_url' ) && menu_page_url( $parent, false ) ) {
			add_submenu_page( $parent, 'Jobs', 'Jobs', $cap, 'oha-job-board', [ $this, 'admin_page' ] );
		} else {
			add_menu_page( 'Jobs', 'Jobs', $cap, 'oha-job-board', [ $this, 'admin_page' ], 'dashicons-id', 26 );
		}
	}

	public function register_settings() {
		register_setting( 'oha_job_board', self::OPTION, [ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ] );
		add_settings_section( 'main', 'Job Board Settings', function () {
			echo '<p>Control who can post jobs and how applications are handled.</p>';
		}, 'oha_job_board' );

		$fields = [
			'membership_required'     => [ 'Require OHA membership to submit jobs', 'checkbox' ],
			'listing_owners_can_post' => [ 'Listing owners can always post (overrides membership gate)', 'checkbox' ],
			'contact_visibility'      => [ 'Show contact/apply details to', 'select_vis' ],
			'join_url'                => [ 'Join / Login URL', 'url', '/membership/' ],
			'add_haunt_url'           => [ 'Add Haunt URL', 'url', '/add-listing/' ],
			'admin_notify_email'      => [ 'Notify this email on new job submissions', 'email', '' ],
			'submit_success_url'      => [ 'Redirect after successful job submit (leave blank to show message)', 'url', '' ],
		];

		foreach ( $fields as $key => [ $label, $type, $placeholder ] ) {
			add_settings_field( $key, $label, function () use ( $key, $type, $placeholder ) {
				$o   = get_option( self::OPTION, [] );
				$val = $o[ $key ] ?? '';
				if ( $type === 'checkbox' ) {
					echo '<input type="checkbox" name="' . self::OPTION . '[' . $key . ']" value="1" ' . checked( ! empty( $val ), true, false ) . ' />';
				} elseif ( $type === 'select_vis' ) {
					echo '<select name="' . self::OPTION . '[' . $key . ']"><option value="public" ' . selected( $val, 'public', false ) . '>Everyone</option><option value="members" ' . selected( $val, 'members', false ) . '>Members only</option></select>';
				} else {
					printf( '<input type="%s" name="%s[%s]" class="regular-text" value="%s" placeholder="%s" />', esc_attr( $type ), self::OPTION, $key, esc_attr( $val ), esc_attr( $placeholder ) );
				}
			}, 'oha_job_board', 'main' );
		}
	}

	public function sanitize_settings( $opts ) {
		return [
			'membership_required'     => ! empty( $opts['membership_required'] ) ? 1 : 0,
			'listing_owners_can_post' => ! empty( $opts['listing_owners_can_post'] ) ? 1 : 0,
			'contact_visibility'      => in_array( $opts['contact_visibility'] ?? 'members', [ 'public', 'members' ], true ) ? $opts['contact_visibility'] : 'members',
			'join_url'                => esc_url_raw( $opts['join_url'] ?? '' ),
			'add_haunt_url'           => esc_url_raw( $opts['add_haunt_url'] ?? '' ),
			'admin_notify_email'      => sanitize_email( $opts['admin_notify_email'] ?? '' ),
			'submit_success_url'      => esc_url_raw( $opts['submit_success_url'] ?? '' ),
		];
	}

	public function admin_page() {
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Insufficient permissions.' );
		echo '<div class="wrap"><h1>OHA Job Board</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'oha_job_board' );
		do_settings_sections( 'oha_job_board' );
		submit_button( 'Save Settings' );
		echo '</form>';
		echo '<hr />';
		echo '<h2>Job Moderation</h2>';
		echo do_shortcode( '[oha_job_admin per_page="50"]' );
		echo '</div>';
	}

	/* =========================================================
	 * HELPERS
	 * ======================================================= */
	private function gate_contact( $email, $url ) {
		$o   = get_option( self::OPTION, [] );
		$vis = $o['contact_visibility'] ?? 'members';
		if ( $vis === 'public' || is_user_logged_in() ) return [ $email, $url ];
		$join = ! empty( $o['join_url'] ) ? esc_url( $o['join_url'] ) : wp_login_url( get_permalink() );
		return [ '(members only)', $join ];
	}

	private function status_badge( $status ) {
		$map = [
			'publish'        => [ 'Active',        'ojb-badge--active' ],
			'pending'        => [ 'Pending Review', 'ojb-badge--pending' ],
			'draft'          => [ 'Draft',          'ojb-badge--draft' ],
			'oha_needs_info' => [ 'Needs Info',     'ojb-badge--info' ],
			'oha_denied'     => [ 'Denied',         'ojb-badge--denied' ],
			'oha_archived'   => [ 'Archived',       'ojb-badge--archived' ],
		];
		return $map[ $status ] ?? [ ucfirst( str_replace( 'oha_', '', $status ) ), 'ojb-badge--draft' ];
	}

	private function relative_date( $date_str ) {
		if ( ! $date_str ) return '';
		$diff = time() - strtotime( $date_str );
		if ( $diff < 86400 )   return 'Today';
		if ( $diff < 172800 )  return 'Yesterday';
		if ( $diff < 604800 )  return floor( $diff / 86400 ) . ' days ago';
		if ( $diff < 2592000 ) return floor( $diff / 604800 ) . ' weeks ago';
		return date( 'M j, Y', strtotime( $date_str ) );
	}

	private function get_app_count( $job_id ) {
		$q = new WP_Query( [
			'post_type'      => self::CPT_APP,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => '_oha_job_id',
			'meta_value'     => $job_id,
		] );
		return (int) $q->found_posts;
	}

	private function notify_admin_new_job( $job_id ) {
		$o     = get_option( self::OPTION, [] );
		$email = ! empty( $o['admin_notify_email'] ) ? $o['admin_notify_email'] : get_option( 'admin_email' );
		if ( ! $email ) return;
		$title  = get_the_title( $job_id );
		$author = get_userdata( get_post_field( 'post_author', $job_id ) );
		$link   = admin_url( 'post.php?post=' . $job_id . '&action=edit' );
		wp_mail(
			$email,
			'[OHA Job Board] New job pending review: ' . $title,
			"A new job has been submitted for review.\n\nTitle: $title\nSubmitted by: " .
			( $author ? $author->display_name . ' <' . $author->user_email . '>' : 'Unknown' ) .
			"\n\nReview it here: $link"
		);
	}

	private function notify_submitter( $job_id, $action ) {
		$author = get_userdata( get_post_field( 'post_author', $job_id ) );
		if ( ! $author || ! $author->user_email ) return;
		$title = get_the_title( $job_id );
		$msgs  = [
			'approve'  => [ 'Your job posting is live!',         "Your job posting \"{$title}\" has been approved and is now live on the OHA Job Board." ],
			'deny'     => [ 'Your job posting was not approved',  "Your job posting \"{$title}\" was not approved. Please contact the OHA board for more information." ],
			'needinfo' => [ 'Action needed on your job posting',  "We need more information about your job posting \"{$title}\" before it can be approved. Please log in and update your listing." ],
		];
		if ( ! isset( $msgs[ $action ] ) ) return;
		[ $subject, $body ] = $msgs[ $action ];
		wp_mail( $author->user_email, '[OHA] ' . $subject, $body . "\n\nThis is an automated message from the OHA Job Board." );
	}

	/* =========================================================
	 * JOB CARD HTML
	 * ======================================================= */
	private function render_job_card( $id, $args = [] ) {
		$defaults = [ 'show_apply' => true, 'show_actions' => false, 'context' => 'public' ];
		$args     = array_merge( $defaults, $args );

		$role       = get_post_meta( $id, '_oha_role_type',     true );
		$employment = get_post_meta( $id, '_oha_employment',    true );
		$pay        = get_post_meta( $id, '_oha_pay',           true );
		$start      = get_post_meta( $id, '_oha_start_date',    true );
		$end        = get_post_meta( $id, '_oha_end_date',      true );
		$place_id   = intval( get_post_meta( $id, '_oha_place_id', true ) );
		$city       = get_post_meta( $id, '_oha_city',          true );
		$state      = get_post_meta( $id, '_oha_state',         true );
		$status     = get_post_status( $id );

		$contact_email = get_post_meta( $id, '_oha_contact_email', true );
		$contact_url   = get_post_meta( $id, '_oha_contact_url',   true );
		[ $show_email, $show_url ] = $this->gate_contact( $contact_email, $contact_url );

		$location   = implode( ', ', array_filter( [ $city, $state ] ) );
		$haunt_html = $place_id ? '<a href="' . esc_url( get_permalink( $place_id ) ) . '" class="ojb-haunt-link">' . esc_html( get_the_title( $place_id ) ) . '</a>' : '';
		$posted_ago = $this->relative_date( get_post_time( 'Y-m-d H:i:s', false, $id ) );
		$is_new     = ( time() - strtotime( get_post_time( 'Y-m-d H:i:s', false, $id ) ) ) < ( 7 * DAY_IN_SECONDS );

		[ $status_text, $status_class ] = $this->status_badge( $status );

		// Determine apply CTA
		$gated   = ( $show_email === '(members only)' );
		$has_url = ! $gated && ! empty( $show_url );
		$has_email = ! $gated && ! empty( $show_email );

		ob_start();
		?>
		<div class="ojb-card" data-role="<?php echo esc_attr( $role ); ?>" data-employment="<?php echo esc_attr( $employment ); ?>">

			<div class="ojb-card__head">
				<div class="ojb-card__meta-top">
					<?php if ( $employment ) : ?><span class="ojb-badge ojb-badge--employment"><?php echo esc_html( $employment ); ?></span><?php endif; ?>
					<?php if ( $role ) : ?><span class="ojb-badge ojb-badge--role"><?php echo esc_html( $role ); ?></span><?php endif; ?>
					<?php if ( $is_new ) : ?><span class="ojb-badge ojb-badge--new">New</span><?php endif; ?>
					<?php if ( $args['context'] !== 'public' ) : ?>
						<span class="ojb-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span>
					<?php endif; ?>
				</div>
				<h3 class="ojb-card__title">
					<a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a>
				</h3>
				<?php if ( $haunt_html || $location ) : ?>
				<p class="ojb-card__subtitle">
					<?php if ( $haunt_html ) : ?><span>&#127875; <?php echo $haunt_html; ?></span><?php endif; ?>
					<?php if ( $location ) : ?><span>&#128205; <?php echo esc_html( $location ); ?></span><?php endif; ?>
				</p>
				<?php endif; ?>
			</div>

			<div class="ojb-card__body">
				<?php
				$excerpt = get_the_excerpt( $id ) ?: wp_trim_words( get_post_field( 'post_content', $id ), 25 );
				if ( $excerpt ) :
				?><p class="ojb-card__excerpt"><?php echo esc_html( $excerpt ); ?></p><?php endif; ?>
				<ul class="ojb-card__details">
					<?php if ( $pay ) : ?><li><span class="ojb-detail-label">Pay</span><span><?php echo esc_html( $pay ); ?></span></li><?php endif; ?>
					<?php if ( $start ) : ?><li><span class="ojb-detail-label">Starts</span><span><?php echo esc_html( date( 'M j, Y', strtotime( $start ) ) ); ?></span></li><?php endif; ?>
					<?php if ( $end ) : ?><li><span class="ojb-detail-label">Ends</span><span><?php echo esc_html( date( 'M j, Y', strtotime( $end ) ) ); ?></span></li><?php endif; ?>
				</ul>
			</div>

			<div class="ojb-card__foot">
				<span class="ojb-posted-date"><?php echo esc_html( $posted_ago ); ?></span>
				<?php if ( $args['show_apply'] ) : ?>
					<div class="ojb-card__cta">
						<?php if ( $gated ) : ?>
							<a href="<?php echo esc_url( $show_url ); ?>" class="ojb-btn ojb-btn--primary ojb-btn--sm">Login to Apply</a>
						<?php else : ?>
							<?php if ( $has_url ) : ?>
								<a href="<?php echo esc_url( $show_url ); ?>" target="_blank" rel="noopener" class="ojb-btn ojb-btn--primary ojb-btn--sm">Apply Now</a>
							<?php endif; ?>
							<?php if ( $has_email ) : ?>
								<button class="ojb-btn ojb-btn--sm <?php echo $has_url ? 'ojb-btn--outline' : 'ojb-btn--primary'; ?> ojb-apply-toggle" data-job="<?php echo $id; ?>">
									<?php echo $has_url ? 'Apply by Email' : 'Apply'; ?>
								</button>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if ( $args['show_actions'] ) : ?>
					<?php $this->render_card_actions( $id, $status ); ?>
				<?php endif; ?>
			</div>

			<?php if ( $args['show_apply'] && $has_email ) : ?>
			<div class="ojb-apply-form-wrap" id="ojb-apply-<?php echo $id; ?>" style="display:none;">
				<?php echo do_shortcode( '[oha_job_apply job_id="' . $id . '"]' ); ?>
			</div>
			<?php endif; ?>

		</div>
		<?php
		return ob_get_clean();
	}

	private function render_card_actions( $id, $status ) {
		$nonce    = wp_create_nonce( 'oha_job_act_' . $id );
		$can_mod  = $this->is_moderator();
		$is_owner = get_current_user_id() && ( intval( get_post_field( 'post_author', $id ) ) === get_current_user_id() );

		echo '<div class="ojb-card-actions">';
		if ( $can_mod ) {
			if ( in_array( $status, [ 'pending', 'oha_needs_info', 'oha_denied' ] ) )
				echo '<a href="' . esc_url( add_query_arg( [ 'oha_action' => 'approve', 'job' => $id, '_wpnonce' => $nonce ] ) ) . '" class="ojb-action ojb-action--approve">Approve</a>';
			if ( $status !== 'oha_denied' )
				echo '<a href="' . esc_url( add_query_arg( [ 'oha_action' => 'deny', 'job' => $id, '_wpnonce' => $nonce ] ) ) . '" class="ojb-action ojb-action--deny">Deny</a>';
			if ( $status !== 'oha_needs_info' )
				echo '<a href="' . esc_url( add_query_arg( [ 'oha_action' => 'needinfo', 'job' => $id, '_wpnonce' => $nonce ] ) ) . '" class="ojb-action ojb-action--info">Need Info</a>';
		}
		if ( $can_mod || $is_owner ) {
			if ( $status !== 'oha_archived' )
				echo '<a href="' . esc_url( add_query_arg( [ 'oha_action' => 'archive', 'job' => $id, '_wpnonce' => $nonce ] ) ) . '" class="ojb-action ojb-action--archive">Archive</a>';
			else
				echo '<a href="' . esc_url( add_query_arg( [ 'oha_action' => 'reopen', 'job' => $id, '_wpnonce' => $nonce ] ) ) . '" class="ojb-action ojb-action--approve">Reopen</a>';
		}
		if ( current_user_can( 'edit_post', $id ) )
			echo '<a href="' . esc_url( get_edit_post_link( $id ) ) . '" class="ojb-action ojb-action--edit">Edit</a>';
		echo '</div>';
	}

	private function render_applicant_card( $app_id ) {
		$name    = get_post_meta( $app_id, '_oha_app_name',         true );
		$email   = get_post_meta( $app_id, '_oha_app_email',        true );
		$message = get_post_meta( $app_id, '_oha_app_message',      true );
		$phone   = get_post_meta( $app_id, '_oha_app_phone',        true );
		$age     = get_post_meta( $app_id, '_oha_app_age_range',    true );
		$exp     = get_post_meta( $app_id, '_oha_app_experience',   true );
		$avail   = get_post_meta( $app_id, '_oha_app_availability', true );
		$video   = get_post_meta( $app_id, '_oha_app_video_url',    true );
		$status  = get_post_meta( $app_id, '_oha_app_status',       true ) ?: 'new';
		$date    = get_post_time( 'M j, Y', false, $app_id );

		$status_map = [
			'new'         => [ 'New',         'ojb-badge--draft' ],
			'shortlisted' => [ 'Shortlisted', 'ojb-badge--active' ],
			'declined'    => [ 'Declined',    'ojb-badge--denied' ],
		];
		[ $status_label, $status_class ] = $status_map[ $status ] ?? [ 'New', 'ojb-badge--draft' ];

		$vid = $this->video_info( $video );

		ob_start();
		?>
		<div class="ojb-applicant-card" data-app="<?php echo $app_id; ?>" data-status="<?php echo esc_attr( $status ); ?>">
			<div class="ojb-applicant-card__head">
				<div class="ojb-applicant-card__identity">
					<strong class="ojb-applicant-name"><?php echo esc_html( $name ?: 'Unknown' ); ?></strong>
					<span class="ojb-applicant-date"><?php echo esc_html( $date ); ?></span>
				</div>
				<span class="ojb-app-status-badge ojb-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
			</div>

			<div class="ojb-applicant-card__body">
				<div class="ojb-applicant-card__contacts">
					<?php if ( $email ) : ?>
					<a href="mailto:<?php echo esc_attr( $email ); ?>" class="ojb-applicant-contact"><?php echo esc_html( $email ); ?></a>
					<?php endif; ?>
					<?php if ( $phone ) : ?>
					<a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ); ?>" class="ojb-applicant-contact"><?php echo esc_html( $phone ); ?></a>
					<?php endif; ?>
				</div>

				<?php if ( $age || $exp || $avail ) : ?>
				<div class="ojb-applicant-card__tags">
					<?php if ( $age )   : ?><span class="ojb-badge ojb-badge--info"><?php echo esc_html( $age ); ?></span><?php endif; ?>
					<?php if ( $exp )   : ?><span class="ojb-badge ojb-badge--role"><?php echo esc_html( $exp ); ?></span><?php endif; ?>
					<?php if ( $avail ) : ?><span class="ojb-badge ojb-badge--employment"><?php echo esc_html( $avail ); ?></span><?php endif; ?>
				</div>
				<?php endif; ?>

				<?php if ( $message ) : ?>
				<p class="ojb-applicant-card__message"><?php echo esc_html( $message ); ?></p>
				<?php endif; ?>

				<?php if ( $vid ) : ?>
				<div class="ojb-applicant-card__video">
					<?php if ( $vid['type'] === 'youtube' && $vid['thumb'] ) : ?>
					<a href="<?php echo esc_url( $vid['url'] ); ?>" target="_blank" rel="noopener" class="ojb-video-thumb">
						<img src="<?php echo esc_url( $vid['thumb'] ); ?>" alt="Casting video" loading="lazy" />
						<span class="ojb-video-play">&#9654;</span>
					</a>
					<?php else : ?>
					<a href="<?php echo esc_url( $vid['url'] ); ?>" target="_blank" rel="noopener" class="ojb-btn ojb-btn--outline ojb-btn--sm">Watch Casting Video</a>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>

			<div class="ojb-applicant-card__foot">
				<div class="ojb-app-status-actions">
					<button class="ojb-app-status-btn ojb-app-status-btn--shortlist" data-app="<?php echo $app_id; ?>" type="button">Shortlist</button>
					<button class="ojb-app-status-btn ojb-app-status-btn--decline" data-app="<?php echo $app_id; ?>" type="button">Decline</button>
					<?php if ( $email ) : ?>
					<a href="mailto:<?php echo esc_attr( $email ); ?>" class="ojb-btn ojb-btn--outline ojb-btn--sm">Email</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* =========================================================
	 * SHORTCODE: Job List  [oha_job_list]
	 * Attrs: role, employment, place, per_page, show_filters
	 * ======================================================= */
	public function sc_job_list( $atts ) {
		$a = shortcode_atts( [
			'role'         => '',
			'employment'   => '',
			'place'        => '',
			'per_page'     => 12,
			'show_filters' => 'yes',
		], $atts );

		$meta = [];
		if ( $a['role'] )       $meta[] = [ 'key' => '_oha_role_type', 'value' => sanitize_text_field( $a['role'] ) ];
		if ( $a['employment'] ) $meta[] = [ 'key' => '_oha_employment', 'value' => sanitize_text_field( $a['employment'] ) ];
		if ( $a['place'] )      $meta[] = [ 'key' => '_oha_place_id', 'value' => intval( $a['place'] ) ];

		$q = new WP_Query( [
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => intval( $a['per_page'] ),
			'meta_query'     => $meta,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		ob_start();
		echo '<div class="ojb-wrap" id="ojb-list-wrap">';

		if ( $a['show_filters'] !== 'no' ) {
			$fields      = $this->fields_def();
			$role_opts   = $fields['role_type']['choices'];
			$emp_opts    = $fields['employment']['choices'];
			echo '<div class="ojb-filters">';
			echo '<div class="ojb-filters__search"><input type="search" id="ojb-search" placeholder="&#128269; Search jobs, haunts..." class="ojb-input" /></div>';
			echo '<div class="ojb-filters__selects">';
			echo '<select id="ojb-filter-role" class="ojb-select"><option value="">All Roles</option>';
			foreach ( $role_opts as $c ) echo '<option value="' . esc_attr( $c ) . '">' . esc_html( $c ) . '</option>';
			echo '</select>';
			echo '<select id="ojb-filter-employment" class="ojb-select"><option value="">All Types</option>';
			foreach ( $emp_opts as $c ) echo '<option value="' . esc_attr( $c ) . '">' . esc_html( $c ) . '</option>';
			echo '</select>';
			echo '</div></div>';
		}

		if ( $q->have_posts() ) {
			echo '<div class="ojb-grid" id="ojb-grid">';
			while ( $q->have_posts() ) {
				$q->the_post();
				echo $this->render_job_card( get_the_ID() );
			}
			wp_reset_postdata();
			echo '</div>';
		} else {
			echo '<div class="ojb-empty"><p>No open positions found.</p><p>Check back soon — haunts are always looking for great people!</p></div>';
		}

		if ( $q->max_num_pages > 1 ) {
			echo '<div class="ojb-pagination">';
			echo paginate_links( [ 'total' => $q->max_num_pages, 'current' => max( 1, get_query_var( 'paged' ) ) ] );
			echo '</div>';
		}

		echo '</div>';
		return ob_get_clean();
	}

	/* =========================================================
	 * SHORTCODE: Submit Form  [oha_job_submit]
	 * ======================================================= */
	public function sc_job_submit( $atts ) {
		$o = get_option( self::OPTION, [] );

		if ( ! is_user_logged_in() ) {
			$join = ! empty( $o['join_url'] ) ? $o['join_url'] : wp_login_url( get_permalink() );
			return '<div class="ojb-gate"><p>Please <a href="' . esc_url( $join ) . '">log in</a> to post a job.</p></div>';
		}

		if ( ! $this->user_can_post_job() ) {
			$join = ! empty( $o['join_url'] ) ? $o['join_url'] : '/membership/';
			return '<div class="ojb-gate"><p>OHA membership is required to post jobs. <a href="' . esc_url( $join ) . '">Become a member</a> to get started.</p></div>';
		}

		$message = '';
		if ( ! empty( $_POST['oha_job_submit_nonce'] ) && wp_verify_nonce( $_POST['oha_job_submit_nonce'], 'oha_job_submit' ) ) {
			$title = sanitize_text_field( $_POST['title'] ?? '' );
			$desc  = wp_kses_post( $_POST['description'] ?? '' );
			if ( $title ) {
				$post_id = wp_insert_post( [
					'post_type'    => self::CPT,
					'post_status'  => 'pending',
					'post_title'   => $title,
					'post_content' => $desc,
					'post_author'  => get_current_user_id(),
				] );
				if ( $post_id && ! is_wp_error( $post_id ) ) {
					foreach ( $this->fields_def() as $key => $def ) {
						$this->save_field( $post_id, $key, $def );
					}
					$this->notify_admin_new_job( $post_id );
					if ( ! empty( $o['submit_success_url'] ) ) {
						wp_safe_redirect( esc_url_raw( $o['submit_success_url'] ) );
						exit;
					}
					$message = '<div class="ojb-msg ojb-msg--success">Your job has been submitted for review! We\'ll notify you by email once it\'s approved.</div>';
				} else {
					$message = '<div class="ojb-msg ojb-msg--error">There was a problem submitting your job. Please try again.</div>';
				}
			} else {
				$message = '<div class="ojb-msg ojb-msg--error">Job title is required.</div>';
			}
		}

		ob_start();
		echo '<div class="ojb-wrap ojb-form-wrap">';
		echo $message;
		echo '<form method="post" class="ojb-form" id="ojb-submit-form">';
		wp_nonce_field( 'oha_job_submit', 'oha_job_submit_nonce' );

		// Section 1: Job Details
		echo '<div class="ojb-form__section">';
		echo '<h3 class="ojb-form__section-title">Job Details</h3>';
		echo '<div class="ojb-field ojb-field--required"><label>Job Title</label><input type="text" name="title" required class="ojb-input" placeholder="e.g. Scare Actor, Makeup Artist, Crew Lead" /></div>';
		echo '<div class="ojb-field"><label>Description</label><textarea name="description" rows="6" class="ojb-textarea" placeholder="Describe the role, what you\'re looking for, hours, any requirements..."></textarea></div>';
		echo '</div>';

		// Section 2: Role Info
		echo '<div class="ojb-form__section">';
		echo '<h3 class="ojb-form__section-title">Role Information</h3>';
		echo '<div class="ojb-form__row">';
		foreach ( [ 'role_type', 'employment', 'pay' ] as $key ) {
			$def = $this->fields_def()[ $key ];
			$req = ! empty( $def['required'] ) ? ' ojb-field--required' : '';
			echo '<div class="ojb-field' . $req . '"><label>' . esc_html( $def['label'] ) . '</label>';
			$this->render_field( $key, $def, '', 'frontend' );
			echo '</div>';
		}
		echo '</div></div>';

		// Section 3: Schedule & Location
		echo '<div class="ojb-form__section">';
		echo '<h3 class="ojb-form__section-title">Schedule &amp; Location</h3>';
		echo '<div class="ojb-form__row">';
		foreach ( [ 'start_date', 'end_date', 'city', 'state' ] as $key ) {
			$def = $this->fields_def()[ $key ];
			echo '<div class="ojb-field"><label>' . esc_html( $def['label'] ) . '</label>';
			$this->render_field( $key, $def, '', 'frontend' );
			echo '</div>';
		}
		echo '</div></div>';

		// Section 4: How to Apply
		echo '<div class="ojb-form__section">';
		echo '<h3 class="ojb-form__section-title">How to Apply</h3>';
		echo '<p class="ojb-hint" style="margin-top:0;">Provide at least one option — a contact email, an application link, or both.</p>';
		$def_email = $this->fields_def()['contact_email'];
		$def_url   = $this->fields_def()['contact_url'];
		echo '<div class="ojb-form__row">';
		echo '<div class="ojb-field"><label>' . esc_html( $def_email['label'] ) . '</label>';
		$this->render_field( 'contact_email', $def_email, '', 'frontend' );
		echo '<span class="ojb-hint">Applicants will email you directly.</span></div>';
		echo '<div class="ojb-field"><label>' . esc_html( $def_url['label'] ) . '</label>';
		$this->render_field( 'contact_url', $def_url, '', 'frontend' );
		echo '<span class="ojb-hint">Link to your application form or Indeed/etc.</span></div>';
		echo '</div></div>';

		// Section 5: Haunt Link
		echo '<div class="ojb-form__section">';
		echo '<h3 class="ojb-form__section-title">Link to Your Haunt <span class="ojb-optional">(Optional)</span></h3>';
		echo '<div class="ojb-field"><label>' . esc_html( $this->fields_def()['place_id']['label'] ) . '</label>';
		$this->render_field( 'place_id', $this->fields_def()['place_id'], '', 'frontend' );
		echo '<span class="ojb-hint">Connecting a haunt listing shows applicants more about your attraction.</span></div>';
		echo '</div>';

		echo '<div class="ojb-form__actions"><button type="submit" class="ojb-btn ojb-btn--primary ojb-btn--lg">Submit Job Posting</button></div>';
		echo '</form></div>';
		return ob_get_clean();
	}

	/* =========================================================
	 * SHORTCODE: Apply  [oha_job_apply job_id="X"]
	 * ======================================================= */
	public function sc_job_apply( $atts ) {
		$a      = shortcode_atts( [ 'job_id' => 0 ], $atts );
		$job_id = intval( $a['job_id'] );
		if ( ! $job_id || get_post_type( $job_id ) !== self::CPT ) return '';

		$contact_email = get_post_meta( $job_id, '_oha_contact_email', true );
		$contact_url   = get_post_meta( $job_id, '_oha_contact_url',   true );
		[ $show_email, $show_url ] = $this->gate_contact( $contact_email, $contact_url );

		$msg = '';
		if ( ! empty( $_POST['oha_job_apply_nonce'] )
			&& intval( $_POST['job_id'] ) === $job_id
			&& wp_verify_nonce( $_POST['oha_job_apply_nonce'], 'oha_job_apply_' . $job_id )
		) {
			$name  = sanitize_text_field( $_POST['name']    ?? '' );
			$email = sanitize_email( $_POST['email']        ?? '' );
			$note  = wp_kses_post( $_POST['message']        ?? '' );

			if ( $name && $email && is_email( $email ) ) {
				$app_id = wp_insert_post( [
					'post_type'    => self::CPT_APP,
					'post_status'  => 'publish',
					'post_title'   => 'Application for #' . $job_id . ' — ' . $name,
					'post_content' => $note,
					'post_author'  => get_current_user_id() ?: 0,
				] );
				if ( $app_id && ! is_wp_error( $app_id ) ) {
					update_post_meta( $app_id, '_oha_job_id',           $job_id );
					update_post_meta( $app_id, '_oha_app_name',         $name );
					update_post_meta( $app_id, '_oha_app_email',        $email );
					update_post_meta( $app_id, '_oha_app_message',      wp_strip_all_tags( $note ) );
					update_post_meta( $app_id, '_oha_app_phone',        sanitize_text_field( $_POST['app_phone']        ?? '' ) );
					update_post_meta( $app_id, '_oha_app_age_range',    sanitize_text_field( $_POST['app_age_range']    ?? '' ) );
					update_post_meta( $app_id, '_oha_app_experience',   sanitize_text_field( $_POST['app_experience']   ?? '' ) );
					update_post_meta( $app_id, '_oha_app_availability', sanitize_text_field( $_POST['app_availability'] ?? '' ) );
					update_post_meta( $app_id, '_oha_app_video_url',    esc_url_raw( $_POST['app_video_url']            ?? '' ) );
				}
				if ( $show_email && $show_email !== '(members only)' ) {
					@wp_mail(
						$show_email,
						'Job Application: ' . get_the_title( $job_id ),
						"From: $name <$email>\n\n" . wp_strip_all_tags( $note )
					);
				}
				$msg = '<div class="ojb-msg ojb-msg--success">Application sent! Good luck!</div>';
			} else {
				$msg = '<div class="ojb-msg ojb-msg--error">Please fill in your name and a valid email address.</div>';
			}
		}

		ob_start();
		echo $msg;
		if ( $show_email === '(members only)' ) {
			echo '<a href="' . esc_url( $show_url ) . '" class="ojb-btn ojb-btn--primary">Login / Join to Apply</a>';
		} else {
			if ( ! empty( $show_email ) ) {
				echo '<form method="post" class="ojb-form ojb-apply-form">';
				wp_nonce_field( 'oha_job_apply_' . $job_id, 'oha_job_apply_nonce' );
				echo '<input type="hidden" name="job_id" value="' . esc_attr( $job_id ) . '" />';
				echo '<div class="ojb-form__row">';
				echo '<div class="ojb-field ojb-field--required"><label>Your Name</label><input type="text" name="name" required class="ojb-input" /></div>';
				echo '<div class="ojb-field ojb-field--required"><label>Your Email</label><input type="email" name="email" required class="ojb-input" /></div>';
				echo '</div>';
				echo '<div class="ojb-field"><label>Message <span class="ojb-optional">(optional)</span></label><textarea name="message" rows="4" class="ojb-textarea" placeholder="Tell them a bit about yourself, your experience, availability..."></textarea></div>';

				// About You section
				echo '<div class="ojb-apply-about">';
				echo '<p class="ojb-apply-about__label">About You <span class="ojb-optional">(optional — helps the venue)</span></p>';
				echo '<div class="ojb-form__row ojb-apply-about__row">';
				foreach ( $this->app_fields_def() as $key => $def ) {
					echo '<div class="ojb-field"><label>' . esc_html( $def['label'] ) . '</label>';
					if ( $def['type'] === 'select' ) {
						echo '<select name="app_' . esc_attr( $key ) . '" class="ojb-select"><option value="">- Select -</option>';
						foreach ( $def['choices'] as $c ) {
							echo '<option value="' . esc_attr( $c ) . '">' . esc_html( $c ) . '</option>';
						}
						echo '</select>';
					} else {
						$ph = isset( $def['placeholder'] ) ? ' placeholder="' . esc_attr( $def['placeholder'] ) . '"' : '';
						echo '<input type="' . esc_attr( $def['type'] ) . '" name="app_' . esc_attr( $key ) . '" class="ojb-input"' . $ph . ' />';
					}
					echo '</div>';
				}
				echo '</div></div>';

				echo '<div class="ojb-form__actions"><button type="submit" class="ojb-btn ojb-btn--primary">Send Application</button></div>';
				echo '</form>';
			}
		}
		return ob_get_clean();
	}

	/* =========================================================
	 * SHORTCODE: My Jobs  [oha_my_jobs]
	 * ======================================================= */
	public function sc_my_jobs( $atts ) {
		if ( ! is_user_logged_in() ) return '<div class="ojb-gate"><p>Please log in to manage your jobs.</p></div>';

		$a = shortcode_atts( [ 'per_page' => 50 ], $atts );
		$q = new WP_Query( [
			'post_type'      => self::CPT,
			'post_status'    => [ 'publish', 'pending', 'draft', 'oha_needs_info', 'oha_denied', 'oha_archived' ],
			'author'         => get_current_user_id(),
			'posts_per_page' => intval( $a['per_page'] ),
		] );

		ob_start();
		echo '<div class="ojb-wrap ojb-my-jobs">';
		echo '<div class="ojb-my-jobs__header"><h3>My Job Postings</h3></div>';

		if ( $q->have_posts() ) {
			echo '<div class="ojb-my-jobs-list">';
			while ( $q->have_posts() ) {
				$q->the_post();
				$id    = get_the_ID();
				$apps  = $this->get_app_count( $id );
				$start = get_post_meta( $id, '_oha_start_date', true );
				$nonce = wp_create_nonce( 'oha_job_act_' . $id );
				[ $status_text, $status_class ] = $this->status_badge( get_post_status( $id ) );
				$st    = get_post_status( $id );
				?>
				<div class="ojb-my-job-item">
					<div class="ojb-my-job-item__main">
						<h4><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php the_title(); ?></a></h4>
						<div class="ojb-my-job-item__meta">
							<span class="ojb-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span>
							<?php if ( $start ) : ?><span class="ojb-meta-item">Starts <?php echo esc_html( date( 'M j, Y', strtotime( $start ) ) ); ?></span><?php endif; ?>
							<?php if ( $apps ) : ?>
						<button class="ojb-apps-toggle ojb-meta-apps" type="button"
							data-job="<?php echo $id; ?>"
							data-label="<?php echo esc_attr( $apps . ' application' . ( $apps !== 1 ? 's' : '' ) ); ?>">
							<?php echo $apps; ?> application<?php echo $apps !== 1 ? 's' : ''; ?>
						</button>
						<?php endif; ?>
						</div>
					</div>
					<div class="ojb-my-job-item__actions">
						<?php if ( current_user_can( 'edit_post', $id ) ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>" class="ojb-action ojb-action--edit">Edit</a>
						<?php endif; ?>
						<?php if ( $st === 'oha_archived' ) : ?>
							<a href="<?php echo esc_url( add_query_arg( [ 'oha_action' => 'reopen', 'job' => $id, '_wpnonce' => $nonce ] ) ); ?>" class="ojb-action ojb-action--approve">Reopen</a>
						<?php else : ?>
							<a href="<?php echo esc_url( add_query_arg( [ 'oha_action' => 'archive', 'job' => $id, '_wpnonce' => $nonce ] ) ); ?>" class="ojb-action ojb-action--archive">Archive</a>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( $apps ) : ?>
				<div class="ojb-applicant-panel" id="ojb-applicants-<?php echo $id; ?>" style="display:none;">
					<?php echo $this->sc_job_applicants( [ 'job_id' => $id ] ); ?>
				</div>
				<?php endif; ?>
				<?php
			}
			echo '</div>';
			wp_reset_postdata();
		} else {
			echo '<div class="ojb-empty"><p>You haven\'t posted any jobs yet.</p></div>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/* =========================================================
	 * SHORTCODE: Job Admin (moderation)  [oha_job_admin]
	 * Attrs: per_page, status
	 * ======================================================= */
	public function sc_job_admin( $atts ) {
		if ( ! $this->is_moderator() ) return '<p>Insufficient permissions.</p>';

		$a     = shortcode_atts( [ 'per_page' => 20, 'status' => '' ], $atts );
		$stati = [ 'pending', 'oha_needs_info', 'oha_denied', 'oha_archived', 'publish' ];
		if ( $a['status'] ) $stati = [ sanitize_key( $a['status'] ) ];

		$q = new WP_Query( [
			'post_type'      => self::CPT,
			'post_status'    => $stati,
			'posts_per_page' => intval( $a['per_page'] ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		$counts = [];
		foreach ( [ 'pending' => 'Pending', 'publish' => 'Active', 'oha_needs_info' => 'Needs Info', 'oha_denied' => 'Denied', 'oha_archived' => 'Archived' ] as $s => $lbl ) {
			$cq = new WP_Query( [ 'post_type' => self::CPT, 'post_status' => [ $s ], 'posts_per_page' => -1 ] );
			$counts[ $s ] = (int) $cq->found_posts;
		}

		ob_start();
		echo '<div class="ojb-admin-wrap">';

		// Stats bar
		echo '<div class="ojb-admin-stats">';
		foreach ( $counts as $s => $n ) {
			[ , $cls ] = $this->status_badge( $s );
			$lbl       = $this->status_badge( $s )[0];
			echo '<div class="ojb-admin-stat"><span class="ojb-admin-stat__num">' . $n . '</span><span class="ojb-admin-stat__lbl ojb-badge ' . esc_attr( $cls ) . '">' . esc_html( $lbl ) . '</span></div>';
		}
		echo '</div>';

		if ( $q->have_posts() ) {
			echo '<div class="ojb-admin-table-wrap"><table class="ojb-admin-table"><thead><tr>';
			echo '<th>Job</th><th>Status</th><th>Haunt</th><th>Submitted</th><th>Author</th><th>Actions</th>';
			echo '</tr></thead><tbody>';
			while ( $q->have_posts() ) {
				$q->the_post();
				$id     = get_the_ID();
				$status = get_post_status( $id );
				[ $status_text, $status_class ] = $this->status_badge( $status );
				$place_id = intval( get_post_meta( $id, '_oha_place_id', true ) );
				$haunt    = $place_id ? '<a href="' . esc_url( get_permalink( $place_id ) ) . '">' . esc_html( get_the_title( $place_id ) ) . '</a>' : '—';
				$apps     = $this->get_app_count( $id );
				echo '<tr>';
				echo '<td><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( get_the_title() ) . '</a>';
				if ( $apps ) echo ' <span class="ojb-meta-apps ojb-badge ojb-badge--info">' . $apps . ' apps</span>';
				echo '</td>';
				echo '<td><span class="ojb-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_text ) . '</span></td>';
				echo '<td>' . $haunt . '</td>';
				echo '<td>' . get_the_date( 'M j, Y' ) . '</td>';
				echo '<td>' . esc_html( get_the_author() ) . '</td>';
				echo '<td>';
				$this->render_card_actions( $id, $status );
				echo '</td></tr>';
			}
			echo '</tbody></table></div>';
			wp_reset_postdata();
		} else {
			echo '<div class="ojb-empty"><p>No jobs found.</p></div>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	/* =========================================================
	 * SHORTCODE: Board Hub Frontend Panel  [oha_jobs_boardhub]
	 * Use on the Board Hub members-only frontend page for board members
	 * ======================================================= */
	public function sc_jobs_boardhub( $atts ) {
		if ( ! $this->is_moderator() ) {
			return '<div class="ojb-gate"><p>Board members only.</p></div>';
		}
		$a = shortcode_atts( [ 'per_page' => 50 ], $atts );
		return $this->sc_job_admin( [ 'per_page' => $a['per_page'] ] );
	}

	/* =========================================================
	 * SHORTCODE: Haunt Jobs (GD auto-detect)  [oha_haunt_jobs]
	 * Auto-detects current gd_place post — use on single listing templates
	 * ======================================================= */
	public function sc_haunt_jobs( $atts ) {
		$a = shortcode_atts( [ 'place_id' => 0, 'per_page' => 5 ], $atts );

		if ( ! $a['place_id'] ) {
			global $post;
			if ( $post && $post->post_type === 'gd_place' ) {
				$a['place_id'] = $post->ID;
			}
		}
		if ( ! $a['place_id'] ) return '';

		$place_id    = intval( $a['place_id'] );
		$place_title = get_the_title( $place_id );

		$q = new WP_Query( [
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => intval( $a['per_page'] ),
			'meta_query'     => [ [ 'key' => '_oha_place_id', 'value' => $place_id, 'compare' => '=' ] ],
		] );
		if ( ! $q->have_posts() ) return '';

		ob_start();
		echo '<div class="ojb-wrap ojb-haunt-jobs">';
		echo '<h3 class="ojb-haunt-jobs__title">Open Positions at ' . esc_html( $place_title ) . '</h3>';
		echo '<div class="ojb-grid">';
		while ( $q->have_posts() ) {
			$q->the_post();
			echo $this->render_job_card( get_the_ID() );
		}
		wp_reset_postdata();
		echo '</div></div>';
		return ob_get_clean();
	}

	/* =========================================================
	 * SHORTCODE: Job Applicants  [oha_job_applicants job_id="X"]
	 * For venue owners and moderators — lists all applicants for a job.
	 * ======================================================= */
	public function sc_job_applicants( $atts ) {
		$a      = shortcode_atts( [ 'job_id' => 0 ], $atts );
		$job_id = intval( $a['job_id'] );

		if ( ! is_user_logged_in() ) return '<div class="ojb-gate"><p>Please log in to view applicants.</p></div>';
		if ( ! $job_id ) return '';

		$uid        = get_current_user_id();
		$job_author = intval( get_post_field( 'post_author', $job_id ) );
		if ( ! $this->is_moderator() && $uid !== $job_author ) {
			return '<div class="ojb-gate"><p>You do not have permission to view these applicants.</p></div>';
		}

		$q = new WP_Query( [
			'post_type'      => self::CPT_APP,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => '_oha_job_id',
			'meta_value'     => $job_id,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		if ( ! $q->have_posts() ) {
			return '<div class="ojb-empty" style="padding:20px 0;"><p>No applications received yet.</p></div>';
		}

		ob_start();
		echo '<div class="ojb-applicant-list">';
		while ( $q->have_posts() ) {
			$q->the_post();
			echo $this->render_applicant_card( get_the_ID() );
		}
		wp_reset_postdata();
		echo '</div>';
		return ob_get_clean();
	}

	/* =========================================================
	 * ACTION HANDLER (approve / deny / archive / reopen / needinfo)
	 * ======================================================= */
	public function handle_actions() {
		if ( empty( $_GET['oha_action'] ) || empty( $_GET['job'] ) ) return;

		$job_id = intval( $_GET['job'] );
		$action = sanitize_key( $_GET['oha_action'] );
		$nonce  = $_GET['_wpnonce'] ?? '';

		if ( ! wp_verify_nonce( $nonce, 'oha_job_act_' . $job_id ) ) return;

		$can_mod  = $this->is_moderator();
		$is_owner = get_current_user_id() && ( intval( get_post_field( 'post_author', $job_id ) ) === get_current_user_id() );

		switch ( $action ) {
			case 'approve':
				if ( $can_mod ) {
					wp_update_post( [ 'ID' => $job_id, 'post_status' => 'publish' ] );
					$this->notify_submitter( $job_id, 'approve' );
				}
				break;
			case 'deny':
				if ( $can_mod ) {
					wp_update_post( [ 'ID' => $job_id, 'post_status' => 'oha_denied' ] );
					$this->notify_submitter( $job_id, 'deny' );
				}
				break;
			case 'needinfo':
				if ( $can_mod ) {
					wp_update_post( [ 'ID' => $job_id, 'post_status' => 'oha_needs_info' ] );
					$this->notify_submitter( $job_id, 'needinfo' );
				}
				break;
			case 'archive':
				if ( $can_mod || $is_owner ) wp_update_post( [ 'ID' => $job_id, 'post_status' => 'oha_archived' ] );
				break;
			case 'reopen':
				if ( $can_mod || $is_owner ) wp_update_post( [ 'ID' => $job_id, 'post_status' => 'publish' ] );
				break;
		}

		if ( ! headers_sent() ) {
			wp_safe_redirect( remove_query_arg( [ 'oha_action', 'job', '_wpnonce' ] ) );
			exit;
		}
	}

	/* =========================================================
	 * AJAX: Search GeoDirectory places
	 * ======================================================= */
	public function ajax_search_places() {
		check_ajax_referer( 'oha_search_places', 'nonce' );
		$term = sanitize_text_field( $_GET['q'] ?? '' );
		$out  = [];
		if ( strlen( $term ) >= 2 ) {
			$args = [ 'post_type' => 'gd_place', 's' => $term, 'posts_per_page' => 20, 'post_status' => 'publish' ];
			if ( ! $this->is_moderator() ) $args['author'] = get_current_user_id() ?: 0;
			$q = new WP_Query( $args );
			while ( $q->have_posts() ) {
				$q->the_post();
				$out[] = [ 'id' => get_the_ID(), 'text' => get_the_title() ];
			}
			wp_reset_postdata();
		}
		wp_send_json( $out );
	}

	/* =========================================================
	 * AJAX: Update applicant status (shortlist / decline / reset)
	 * ======================================================= */
	public function ajax_app_status() {
		check_ajax_referer( 'oha_app_status', 'nonce' );

		$app_id = intval( $_POST['app_id'] ?? 0 );
		$status = sanitize_key( $_POST['status'] ?? '' );

		if ( ! $app_id ) wp_send_json_error( 'Invalid application.' );
		if ( ! in_array( $status, [ 'shortlisted', 'declined', '' ], true ) ) wp_send_json_error( 'Invalid status.' );

		$job_id     = intval( get_post_meta( $app_id, '_oha_job_id', true ) );
		$job_author = intval( get_post_field( 'post_author', $job_id ) );
		if ( ! $this->is_moderator() && get_current_user_id() !== $job_author ) {
			wp_send_json_error( 'Unauthorized.' );
		}

		update_post_meta( $app_id, '_oha_app_status', $status );
		wp_send_json_success( [ 'status' => $status ?: 'new' ] );
	}

	/* =========================================================
	 * ASSETS
	 * ======================================================= */
	public function frontend_assets() {
		if ( is_singular() || is_page() || is_post_type_archive( self::CPT ) ) {
			wp_enqueue_style( 'ojb-frontend', OJB_URL . 'assets/frontend.css', [], OJB_VERSION );
			wp_enqueue_script( 'ojb-frontend', OJB_URL . 'assets/frontend.js', [], OJB_VERSION, true );
			wp_localize_script( 'ojb-frontend', 'OJBConfig', [
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'oha_search_places' ),
				'app_nonce' => wp_create_nonce( 'oha_app_status' ),
			] );
		}
	}

	public function admin_assets( $hook ) {
		if ( in_array( $hook, [ 'post.php', 'post-new.php' ] ) || ( isset( $_GET['page'] ) && $_GET['page'] === 'oha-job-board' ) ) {
			wp_enqueue_style( 'ojb-admin-css', OJB_URL . 'assets/frontend.css', [], OJB_VERSION );
			wp_enqueue_script( 'ojb-admin', OJB_URL . 'assets/admin.js', [], OJB_VERSION, true );
			wp_localize_script( 'ojb-admin', 'OJBConfig', [
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'oha_search_places' ),
			] );
		}
	}
}

add_action( 'plugins_loaded', function () { new OHA_Job_Board_Lite(); } );
