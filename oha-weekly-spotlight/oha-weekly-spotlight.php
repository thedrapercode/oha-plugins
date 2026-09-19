<?php
/**
 * Plugin Name: OHA Weekly Haunt Spotlight
 * Description: Logs GeoDirectory listing creates and updates throughout the week, then auto-publishes a single digest blog post every Sunday at 8:00 AM in the site's timezone.
 * Version:     1.3.0
 * Author:      OHA
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OHA_SPOTLIGHT_VERSION',   '1.3.0' );
define( 'OHA_SPOTLIGHT_LOG_KEY',   'oha_spotlight_listing_log' );
define( 'OHA_SPOTLIGHT_CRON_HOOK', 'oha_spotlight_weekly_publish' );
define( 'OHA_SPOTLIGHT_DRAFT_KEY', 'oha_spotlight_draft_id' );

// ── Frontend stylesheet ────────────────────────────────────────────────────────
// Enqueued only on singular posts in the Haunt Highlight category.

add_action( 'wp_enqueue_scripts', 'oha_spotlight_enqueue_styles' );

function oha_spotlight_enqueue_styles() {
    if ( ! is_singular( 'post' ) ) return;

    $cat = get_term_by( 'name', 'Haunt Highlight', 'category' );
    if ( ! $cat ) return;
    if ( ! has_term( $cat->term_id, 'category' ) ) return;

    wp_enqueue_style(
        'oha-spotlight',
        plugin_dir_url( __FILE__ ) . 'assets/spotlight.css',
        [],
        OHA_SPOTLIGHT_VERSION
    );
}

// ── Activation / Deactivation ──────────────────────────────────────────────────

register_activation_hook( __FILE__, 'oha_spotlight_activate' );
register_deactivation_hook( __FILE__, 'oha_spotlight_deactivate' );

function oha_spotlight_activate() {
    if ( ! wp_next_scheduled( OHA_SPOTLIGHT_CRON_HOOK ) ) {
        wp_schedule_single_event( oha_spotlight_next_sunday_8am(), OHA_SPOTLIGHT_CRON_HOOK );
    }
}

function oha_spotlight_deactivate() {
    wp_clear_scheduled_hook( OHA_SPOTLIGHT_CRON_HOOK );
}

// ── Next Sunday 8:00 AM (site timezone → UTC timestamp for WP cron) ────────────

function oha_spotlight_next_sunday_8am() {
    $tz  = wp_timezone();
    $now = new DateTime( 'now', $tz );

    $day_of_week       = (int) $now->format( 'w' ); // 0 = Sun … 6 = Sat
    $days_until_sunday = ( 7 - $day_of_week ) % 7;

    if ( $days_until_sunday === 0 ) {
        $today_8am = clone $now;
        $today_8am->setTime( 8, 0, 0 );
        if ( $now >= $today_8am ) {
            $days_until_sunday = 7;
        }
    }

    $target = clone $now;
    $target->modify( "+{$days_until_sunday} days" );
    $target->setTime( 8, 0, 0 );

    $utc = clone $target;
    $utc->setTimezone( new DateTimeZone( 'UTC' ) );
    return $utc->getTimestamp();
}

// ── Capture old WP post state before save (for change detection) ───────────────
// pre_post_update fires before wp_update_post commits, so get_post() still
// returns the OLD title/content at this point.

add_action( 'pre_post_update', 'oha_spotlight_capture_old_wp_state', 10, 2 );

function oha_spotlight_capture_old_wp_state( $post_id, $data ) {
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'gd_place' ) return;

    set_transient( 'oha_spotlight_old_wp_' . $post_id, [
        'title'   => $post->post_title,
        'content' => $post->post_content,
    ], 60 );
}

// ── Capture old GD detail state before GeoDirectory overwrites it ──────────────
// Priority 1 runs before GD's own save_post hook (typically priority 10+),
// so geodir_get_post_info() still returns the OLD GD field values.

add_action( 'save_post_gd_place', 'oha_spotlight_capture_old_gd_state', 1 );

function oha_spotlight_capture_old_gd_state( $post_id ) {
    if ( ! function_exists( 'geodir_get_post_info' ) ) return;
    $old_gd = geodir_get_post_info( $post_id );
    if ( $old_gd ) {
        set_transient( 'oha_spotlight_old_gd_' . $post_id, $old_gd, 60 );
    }
}

// ── Compare old vs new state and return a human-readable changes list ──────────

function oha_spotlight_detect_changes( $post_id, $post ) {
    $changes = [];

    // ── WP post fields ──────────────────────────────────────────────────────
    $old_wp = get_transient( 'oha_spotlight_old_wp_' . $post_id );
    delete_transient( 'oha_spotlight_old_wp_' . $post_id );

    if ( $old_wp ) {
        if ( isset( $old_wp['title'] ) && $old_wp['title'] !== $post->post_title ) {
            $changes[] = 'Haunt name updated';
        }
        if ( isset( $old_wp['content'] )
             && trim( $old_wp['content'] ) !== trim( $post->post_content ) ) {
            $changes[] = 'Description updated';
        }
    }

    // ── GD detail fields ────────────────────────────────────────────────────
    $old_gd = get_transient( 'oha_spotlight_old_gd_' . $post_id );
    delete_transient( 'oha_spotlight_old_gd_' . $post_id );

    if ( $old_gd && function_exists( 'geodir_get_post_info' ) ) {
        $new_gd = geodir_get_post_info( $post_id );

        if ( $new_gd ) {
            // Map location (lat/lng together)
            $lat_diff = isset( $old_gd->latitude, $new_gd->latitude )
                && abs( (float) $old_gd->latitude - (float) $new_gd->latitude ) > 0.0001;
            $lng_diff = isset( $old_gd->longitude, $new_gd->longitude )
                && abs( (float) $old_gd->longitude - (float) $new_gd->longitude ) > 0.0001;
            if ( $lat_diff || $lng_diff ) {
                $changes[] = 'Map location updated';
            }

            // Known GD core address / status fields
            $field_labels = [
                'street'   => 'Street address updated',
                'city'     => 'City updated',
                'region'   => 'State/region updated',
                'zip'      => 'ZIP code updated',
                'country'  => 'Country updated',
                'featured' => 'Featured status changed',
            ];
            foreach ( $field_labels as $field => $label ) {
                $old_val = isset( $old_gd->$field ) ? (string) $old_gd->$field : null;
                $new_val = isset( $new_gd->$field ) ? (string) $new_gd->$field : null;
                if ( $old_val !== null && $old_val !== $new_val ) {
                    $changes[] = $label;
                }
            }

            // Catch-all: any remaining custom GD fields that changed
            $skip = array_merge(
                array_keys( $field_labels ),
                [ 'post_id', 'latitude', 'longitude', 'post_title', 'post_status',
                  'post_type', 'post_author', 'post_date', 'default_category' ]
            );
            $custom_changed = false;
            foreach ( $old_gd as $field => $old_val ) {
                if ( in_array( $field, $skip, true ) ) continue;
                if ( isset( $new_gd->$field )
                     && (string) $old_val !== (string) $new_gd->$field ) {
                    $custom_changed = true;
                    break;
                }
            }
            if ( $custom_changed ) {
                $changes[] = 'Listing details updated';
            }
        }
    }

    return $changes;
}

// ── Log every gd_place save ────────────────────────────────────────────────────
// geodir_post_saved fires after GeoDirectory writes to its detail table.
// Signature: ($postarr, $gd_post, $post WP_Post, $update bool)

add_action( 'geodir_post_saved', 'oha_spotlight_log_listing', 10, 4 );

function oha_spotlight_log_listing( $postarr, $gd_post, $post, $is_update ) {
    if ( ! ( $post instanceof WP_Post ) ) return;
    if ( $post->post_type   !== 'gd_place' ) return;
    if ( $post->post_status !== 'publish'  ) return;

    $post_id = (int) $post->ID;
    $log     = get_option( OHA_SPOTLIGHT_LOG_KEY, [] );

    // Only log the first event for each listing this week
    foreach ( $log as $entry ) {
        if ( (int) $entry['post_id'] === $post_id ) {
            // Clean up transients even if skipping
            delete_transient( 'oha_spotlight_old_wp_' . $post_id );
            delete_transient( 'oha_spotlight_old_gd_' . $post_id );
            return;
        }
    }

    $changes = [];
    if ( $is_update ) {
        $changes = oha_spotlight_detect_changes( $post_id, $post );
    } else {
        // Clean up transients for new listings
        delete_transient( 'oha_spotlight_old_wp_' . $post_id );
        delete_transient( 'oha_spotlight_old_gd_' . $post_id );
    }

    $log[] = [
        'post_id'   => $post_id,
        'timestamp' => time(),
        'is_update' => (bool) $is_update,
        'changes'   => $changes,
    ];

    update_option( OHA_SPOTLIGHT_LOG_KEY, $log, false );
}

// ── Render a single haunt card ─────────────────────────────────────────────────

function oha_spotlight_render_card( $item, $is_update ) {
    $badge_class = $is_update ? 'ohaws-card__badge--updated' : 'ohaws-card__badge--new';
    $badge_text  = $is_update ? 'Updated' : 'New';

    $html  = '<div class="ohaws-card">';

    $html .= '<div class="ohaws-card__thumb">';
    if ( ! empty( $item['thumbnail_url'] ) ) {
        $html .= '<img src="' . esc_url( $item['thumbnail_url'] ) . '" '
               . 'alt="' . esc_attr( $item['title'] ) . '">';
    }
    $html .= '<span class="ohaws-card__badge ' . $badge_class . '">' . $badge_text . '</span>';
    $html .= '</div>';

    $html .= '<div class="ohaws-card__body">';
    $html .= '<h3 class="ohaws-card__title">' . esc_html( $item['title'] ) . '</h3>';

    if ( ! empty( $item['location'] ) ) {
        $html .= '<p class="ohaws-card__location">' . esc_html( $item['location'] ) . '</p>';
    }

    if ( $is_update && ! empty( $item['changes'] ) ) {
        $html .= '<ul class="ohaws-card__changes">';
        foreach ( $item['changes'] as $change ) {
            $html .= '<li>' . esc_html( $change ) . '</li>';
        }
        $html .= '</ul>';
    } elseif ( ! empty( $item['description'] ) ) {
        $html .= '<p class="ohaws-card__desc">' . esc_html( $item['description'] ) . '</p>';
    } else {
        $fallback = $is_update
            ? 'This listing was recently updated in the directory.'
            : 'This haunt was newly added to the OHA directory.';
        $html .= '<p class="ohaws-card__desc">' . $fallback . '</p>';
    }

    $html .= '<div><a href="' . esc_url( $item['url'] ) . '" class="ohaws-card__btn">View Listing &rarr;</a></div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

// ── Shared: resolve log entries into listing arrays ────────────────────────────

function oha_spotlight_build_listings( $log ) {
    $new_listings     = [];
    $updated_listings = [];

    foreach ( $log as $entry ) {
        $post_id = (int) $entry['post_id'];
        $post    = get_post( $post_id );
        if ( ! $post || $post->post_status === 'trash' ) continue;

        $title    = get_the_title( $post_id );
        $url      = get_permalink( $post_id );
        $location = '';

        if ( function_exists( 'geodir_get_post_info' ) ) {
            $gd_info = geodir_get_post_info( $post_id );
            $city    = ! empty( $gd_info->city )   ? trim( $gd_info->city )   : '';
            $region  = ! empty( $gd_info->region ) ? trim( $gd_info->region ) : '';

            if ( $city && $region )   $location = $city . ', ' . $region;
            elseif ( $city )          $location = $city;
            elseif ( $region )        $location = $region;
        }

        // Thumbnail — uses featured image (works with standard GD setups)
        $thumbnail_url = get_the_post_thumbnail_url( $post_id, 'medium' ) ?: '';

        // Description — excerpt preferred, then trimmed content
        $description = '';
        if ( ! empty( $post->post_excerpt ) ) {
            $description = wp_strip_all_tags( $post->post_excerpt );
        } elseif ( ! empty( $post->post_content ) ) {
            $description = wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '...' );
        }

        $item = [
            'title'         => $title,
            'url'           => $url,
            'location'      => $location,
            'thumbnail_url' => $thumbnail_url,
            'description'   => $description,
            'changes'       => isset( $entry['changes'] ) ? $entry['changes'] : [],
        ];

        if ( $entry['is_update'] ) {
            $updated_listings[] = $item;
        } else {
            $new_listings[] = $item;
        }
    }

    return [ $new_listings, $updated_listings ];
}

// ── Shared: build post title + HTML content ────────────────────────────────────

function oha_spotlight_build_content( $new_listings, $updated_listings ) {
    $tz         = wp_timezone();
    $end_date   = new DateTime( 'now', $tz );
    $start_date = ( clone $end_date )->modify( '-6 days' );

    if ( $start_date->format( 'F Y' ) === $end_date->format( 'F Y' ) ) {
        $date_range = $start_date->format( 'F j' ) . '&ndash;' . $end_date->format( 'j, Y' );
    } else {
        $date_range = $start_date->format( 'F j' ) . '&ndash;' . $end_date->format( 'F j, Y' );
    }

    $total = count( $new_listings ) + count( $updated_listings );

    $content  = '<div class="ohaws-wrap">';
    $content .= '<p class="ohaws-intro">The following haunts were <strong>added or updated</strong> '
              . 'in the OHA Haunt Directory this week (' . $date_range . '). '
              . 'Select any listing below for details, hours, and location.</p>';

    if ( ! empty( $new_listings ) ) {
        $content .= '<h2 class="ohaws-section-heading">New Listings</h2>';
        $content .= '<div class="ohaws-grid">';
        foreach ( $new_listings as $item ) {
            $content .= oha_spotlight_render_card( $item, false );
        }
        $content .= '</div>';
    }

    if ( ! empty( $updated_listings ) ) {
        $content .= '<h2 class="ohaws-section-heading">Updated Listings</h2>';
        $content .= '<div class="ohaws-grid">';
        foreach ( $updated_listings as $item ) {
            $content .= oha_spotlight_render_card( $item, true );
        }
        $content .= '</div>';
    }

    $content .= '<p class="ohaws-footer"><strong>' . $total . ' haunt' . ( $total !== 1 ? 's' : '' ) . '</strong> '
              . 'active in the directory this week. Check back every Sunday for new additions and updates.</p>';
    $content .= '</div>';

    $title = 'Haunt Directory Updates: ' . wp_strip_all_tags( $date_range );

    return [ $title, $content ];
}

// ── Shared: get or create the "Haunt Highlight" category ──────────────────────

function oha_spotlight_get_category_id() {
    $cat = get_term_by( 'name', 'Haunt Highlight', 'category' );
    if ( $cat ) return (int) $cat->term_id;

    $result = wp_insert_term( 'Haunt Highlight', 'category' );
    return is_wp_error( $result ) ? get_option( 'default_category', 1 ) : (int) $result['term_id'];
}

// ── Shared: create the spotlight post with a given status ──────────────────────

function oha_spotlight_create_post( $status = 'publish' ) {
    $log = get_option( OHA_SPOTLIGHT_LOG_KEY, [] );
    if ( empty( $log ) ) return null;

    [ $new_listings, $updated_listings ] = oha_spotlight_build_listings( $log );
    if ( empty( $new_listings ) && empty( $updated_listings ) ) return null;

    [ $title, $content ] = oha_spotlight_build_content( $new_listings, $updated_listings );
    $cat_id = oha_spotlight_get_category_id();

    $args = [
        'post_title'    => $title,
        'post_content'  => $content,
        'post_status'   => $status,
        'post_type'     => 'post',
        'post_category' => [ $cat_id ],
    ];

    if ( $status === 'publish' ) {
        $args['post_date']     = current_time( 'mysql' );
        $args['post_date_gmt'] = current_time( 'mysql', true );
    }

    $post_id = wp_insert_post( $args );
    return is_wp_error( $post_id ) ? null : $post_id;
}

// ── Weekly cron callback ───────────────────────────────────────────────────────

add_action( OHA_SPOTLIGHT_CRON_HOOK, 'oha_spotlight_publish' );

function oha_spotlight_publish() {
    // Always reschedule first so the chain continues even if this run has nothing
    wp_schedule_single_event( oha_spotlight_next_sunday_8am(), OHA_SPOTLIGHT_CRON_HOOK );

    $post_id = oha_spotlight_create_post( 'publish' );

    if ( $post_id ) {
        // Clear the weekly log
        update_option( OHA_SPOTLIGHT_LOG_KEY, [], false );

        // Remove any admin preview draft — the real post is live now
        $draft_id = (int) get_option( OHA_SPOTLIGHT_DRAFT_KEY, 0 );
        if ( $draft_id && get_post( $draft_id ) ) {
            wp_delete_post( $draft_id, true );
        }
        update_option( OHA_SPOTLIGHT_DRAFT_KEY, 0, false );
    }
}

// ── Admin menu ─────────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'oha_spotlight_admin_menu' );

function oha_spotlight_admin_menu() {
    add_management_page(
        'Haunt Spotlight',
        'Haunt Spotlight',
        'manage_options',
        'oha-spotlight',
        'oha_spotlight_admin_page'
    );
}

// ── Admin action: generate draft and redirect to live preview ──────────────────

add_action( 'admin_post_oha_spotlight_generate_draft', 'oha_spotlight_action_generate_draft' );

function oha_spotlight_action_generate_draft() {
    check_admin_referer( 'oha_spotlight_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    // Delete previous draft if one exists
    $old_id = (int) get_option( OHA_SPOTLIGHT_DRAFT_KEY, 0 );
    if ( $old_id && get_post( $old_id ) ) {
        wp_delete_post( $old_id, true );
    }

    $post_id = oha_spotlight_create_post( 'draft' );

    if ( ! $post_id ) {
        wp_redirect( admin_url( 'tools.php?page=oha-spotlight&spotlight_msg=empty' ) );
        exit;
    }

    update_option( OHA_SPOTLIGHT_DRAFT_KEY, $post_id, false );

    // Send admin to the WordPress frontend preview of the draft
    wp_redirect( get_preview_post_link( $post_id ) );
    exit;
}

// ── Admin action: delete the preview draft ─────────────────────────────────────

add_action( 'admin_post_oha_spotlight_delete_draft', 'oha_spotlight_action_delete_draft' );

function oha_spotlight_action_delete_draft() {
    check_admin_referer( 'oha_spotlight_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

    $draft_id = (int) get_option( OHA_SPOTLIGHT_DRAFT_KEY, 0 );
    if ( $draft_id && get_post( $draft_id ) ) {
        wp_delete_post( $draft_id, true );
    }
    update_option( OHA_SPOTLIGHT_DRAFT_KEY, 0, false );

    wp_redirect( admin_url( 'tools.php?page=oha-spotlight&spotlight_msg=draft_deleted' ) );
    exit;
}

// ── Admin page ─────────────────────────────────────────────────────────────────

function oha_spotlight_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $log      = get_option( OHA_SPOTLIGHT_LOG_KEY, [] );
    $next     = wp_next_scheduled( OHA_SPOTLIGHT_CRON_HOOK );
    $tz       = wp_timezone();
    $draft_id = (int) get_option( OHA_SPOTLIGHT_DRAFT_KEY, 0 );
    $draft    = $draft_id ? get_post( $draft_id ) : null;

    // Clean up stale draft reference
    if ( $draft_id && ! $draft ) {
        update_option( OHA_SPOTLIGHT_DRAFT_KEY, 0, false );
        $draft_id = 0;
    }

    [ $new_listings, $updated_listings ] = oha_spotlight_build_listings( $log );
    $total = count( $new_listings ) + count( $updated_listings );
    $msg   = isset( $_GET['spotlight_msg'] ) ? sanitize_key( $_GET['spotlight_msg'] ) : '';

    echo '<div class="wrap">';
    echo '<h1>Haunt Spotlight</h1>';

    // ── Flash messages ──────────────────────────────────────────────────────
    if ( $msg === 'empty' ) {
        echo '<div class="notice notice-warning"><p>The queue is empty — add or update some listings first.</p></div>';
    } elseif ( $msg === 'draft_deleted' ) {
        echo '<div class="notice notice-success is-dismissible"><p>Preview draft deleted.</p></div>';
    }

    // ── Schedule info ───────────────────────────────────────────────────────
    echo '<h2>Next Scheduled Post</h2>';
    if ( $next ) {
        $date = ( new DateTime( '@' . $next ) )->setTimezone( $tz )->format( 'l, F j, Y \a\t g:i A T' );
        echo '<p>The weekly digest will auto-publish on <strong>' . esc_html( $date ) . '</strong>.</p>';
    } else {
        echo '<p style="color:red;"><strong>No cron event is scheduled.</strong> Try deactivating and reactivating the plugin.</p>';
    }

    // ── Draft banner ────────────────────────────────────────────────────────
    if ( $draft ) {
        echo '<div class="notice notice-info" style="padding:10px 16px;">';
        echo '<p><strong>Preview draft exists:</strong> &ldquo;' . esc_html( $draft->post_title ) . '&rdquo;</p>';
        echo '<p>';
        echo '<a href="' . esc_url( get_preview_post_link( $draft_id ) ) . '" target="_blank" class="button button-primary">&#x1F441; Preview</a> ';
        echo '<a href="' . esc_url( get_edit_post_link( $draft_id ) ) . '" class="button">Edit in WordPress</a> ';

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;margin-left:4px;">';
        wp_nonce_field( 'oha_spotlight_nonce' );
        echo '<input type="hidden" name="action" value="oha_spotlight_delete_draft">';
        echo '<button type="submit" class="button" onclick="return confirm(\'Delete this preview draft?\')">Delete Draft</button>';
        echo '</form>';

        echo '</p>';
        echo '<p style="color:#666;font-size:13px;">This draft is for preview only. The Sunday cron will publish a fresh live post and clean up this draft automatically.</p>';
        echo '</div>';
    }

    // ── Queue ───────────────────────────────────────────────────────────────
    echo '<h2>Current Queue</h2>';

    if ( $total === 0 ) {
        echo '<p>No listings have been added or updated since the last post was published.</p>';
    } else {
        if ( ! empty( $new_listings ) ) {
            echo '<h3>&#x1F195; New Listings (' . count( $new_listings ) . ')</h3><ul>';
            foreach ( $new_listings as $item ) {
                echo '<li><a href="' . esc_url( $item['url'] ) . '" target="_blank">' . esc_html( $item['title'] ) . '</a>';
                if ( $item['location'] ) echo ' &mdash; ' . esc_html( $item['location'] );
                echo '</li>';
            }
            echo '</ul>';
        }

        if ( ! empty( $updated_listings ) ) {
            echo '<h3>&#x1F504; Updated Listings (' . count( $updated_listings ) . ')</h3><ul>';
            foreach ( $updated_listings as $item ) {
                echo '<li><a href="' . esc_url( $item['url'] ) . '" target="_blank">' . esc_html( $item['title'] ) . '</a>';
                if ( $item['location'] ) echo ' &mdash; ' . esc_html( $item['location'] );
                if ( ! empty( $item['changes'] ) ) {
                    echo ' <span style="color:#666;font-size:12px;">(' . esc_html( implode( ', ', $item['changes'] ) ) . ')</span>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }

        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:16px;">';
        wp_nonce_field( 'oha_spotlight_nonce' );
        echo '<input type="hidden" name="action" value="oha_spotlight_generate_draft">';
        $btn_label = $draft ? 'Regenerate Draft Preview' : 'Generate Draft Preview';
        echo '<button type="submit" class="button button-primary button-large">' . $btn_label . '</button>';
        echo ' &nbsp;<span style="color:#666;font-size:13px;">Builds the post as a draft and opens the live preview.</span>';
        echo '</form>';
    }

    echo '</div>'; // .wrap
}

// ── Sitewide admin notice (dashboard + plugin page only) ──────────────────────

add_action( 'admin_notices', 'oha_spotlight_admin_notice' );

function oha_spotlight_admin_notice() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $screen = get_current_screen();
    if ( ! in_array( $screen->id, [ 'dashboard', 'tools_page_oha-spotlight' ], true ) ) return;

    $next = wp_next_scheduled( OHA_SPOTLIGHT_CRON_HOOK );
    if ( ! $next ) return;

    $log   = get_option( OHA_SPOTLIGHT_LOG_KEY, [] );
    $count = count( $log );
    $tz    = wp_timezone();
    $date  = ( new DateTime( '@' . $next ) )->setTimezone( $tz )->format( 'D, F j \a\t g:i A T' );

    echo '<div class="notice notice-info"><p>'
        . '<strong>OHA Weekly Haunt Spotlight:</strong> '
        . esc_html( $count ) . ' listing' . ( $count !== 1 ? 's' : '' ) . ' queued &mdash; '
        . 'next post publishes <strong>' . esc_html( $date ) . '</strong>. '
        . '<a href="' . esc_url( admin_url( 'tools.php?page=oha-spotlight' ) ) . '">Manage &rarr;</a>'
        . '</p></div>';
}
