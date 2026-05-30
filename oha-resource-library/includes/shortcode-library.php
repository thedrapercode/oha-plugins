<?php
if ( ! defined('ABSPATH') ) { exit; }

add_shortcode( 'oha_resources', function( $atts ) {
    wp_enqueue_style( 'oha-rl' );

    $atts = shortcode_atts( array( 'per_page' => 24 ), $atts, 'oha_resources' );

    $sel_audience = isset( $_GET['audience'] )      ? sanitize_title( wp_unslash( $_GET['audience'] ) )      : '';
    $sel_type     = isset( $_GET['resource_type'] ) ? sanitize_title( wp_unslash( $_GET['resource_type'] ) ) : '';
    $sel_search   = isset( $_GET['s'] )             ? sanitize_text_field( wp_unslash( $_GET['s'] ) )         : '';

    $tax_query = array( 'relation' => 'AND' );
    if ( $sel_audience ) {
        $tax_query[] = array( 'taxonomy' => 'audience',      'field' => 'slug', 'terms' => array( $sel_audience ) );
    }
    if ( $sel_type ) {
        $tax_query[] = array( 'taxonomy' => 'resource_type', 'field' => 'slug', 'terms' => array( $sel_type ) );
    }

    $paged = max( 1, (int) get_query_var( 'paged' ) );
    $loop  = new WP_Query( array(
        'post_type'      => 'oha_resource',
        'posts_per_page' => (int) $atts['per_page'],
        'paged'          => $paged,
        's'              => $sel_search,
        'tax_query'      => $tax_query,
    ) );

    $fresh_ts = time() - ( intval( get_option( 'oha_rl_fresh_days', 30 ) ) * DAY_IN_SECONDS );
    $new_ts   = time() - ( intval( get_option( 'oha_rl_new_days',   14 ) ) * DAY_IN_SECONDS );

    $audience_terms = get_terms( array( 'taxonomy' => 'audience',      'hide_empty' => true ) );
    $type_terms     = get_terms( array( 'taxonomy' => 'resource_type', 'hide_empty' => true ) );

    $is_member = function_exists( 'oha_rl_user_is_member' ) && oha_rl_user_is_member();
    $join_url  = esc_url( get_option( 'oha_rl_join_url', wp_login_url( get_permalink() ) ) );
    $has_filter = $sel_audience || $sel_type || $sel_search;
    $clear_url  = esc_url( strtok( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : get_permalink(), '?' ) );

    ob_start(); ?>
    <div class="oha-rl-wrap">

      <form class="oha-rl-filters" method="get">
        <input type="search" name="s" value="<?php echo esc_attr( $sel_search ); ?>" placeholder="Search resources..." />

        <select name="audience">
          <option value="">All Audiences</option>
          <?php if ( ! is_wp_error( $audience_terms ) ) foreach ( $audience_terms as $t ) : ?>
            <option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $sel_audience, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
          <?php endforeach; ?>
        </select>

        <select name="resource_type">
          <option value="">All Types</option>
          <?php if ( ! is_wp_error( $type_terms ) ) foreach ( $type_terms as $t ) : ?>
            <option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $sel_type, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
          <?php endforeach; ?>
        </select>

        <button type="submit">Filter</button>
        <?php if ( $has_filter ) : ?>
          <a class="oha-rl-clear" href="<?php echo $clear_url; ?>">Clear</a>
        <?php endif; ?>
      </form>

      <?php if ( ! $is_member ) : ?>
        <div class="oha-rl-member-notice">
          OHA Resources are available to OHA members. <a href="<?php echo $join_url; ?>">Join or log in</a> to access downloads, videos, and documents.
        </div>
      <?php endif; ?>

      <div class="oha-grid">
        <?php if ( $loop->have_posts() ) : while ( $loop->have_posts() ) : $loop->the_post();
          $post_id = get_the_ID();

          // Badges
          $badges     = array();
          $modified   = get_post_modified_time( 'U' );
          $created    = get_post_time( 'U' );
          $forced_new = get_post_meta( $post_id, '_oha_badge_new',     true ) === '1';
          $forced_upd = get_post_meta( $post_id, '_oha_badge_updated', true ) === '1';
          if ( $forced_new || ( $created && $created >= $new_ts ) ) {
              $badges[] = '<span class="badge badge-new">New</span>';
          }
          if ( $forced_upd || ( $modified && $modified >= $fresh_ts && ( ! $created || $modified - $created > DAY_IN_SECONDS ) ) ) {
              $badges[] = '<span class="badge badge-updated">Updated</span>';
          }

          // Type
          $types     = get_the_terms( $post_id, 'resource_type' );
          $type_slug = ( $types && ! is_wp_error( $types ) ) ? $types[0]->slug : '';
          $type_name = ( $types && ! is_wp_error( $types ) ) ? $types[0]->name : '';
          $is_video  = in_array( $type_slug, array( 'video', 'meeting-recording' ), true );

          // Embed
          $embed_url = get_post_meta( $post_id, '_oha_embed_url', true );

          // Audience
          $aud_terms = get_the_terms( $post_id, 'audience' );

          // CTA
          if ( $is_member ) {
              if ( $embed_url ) {
                  $cta_label = 'Watch';
                  $cta_href  = esc_url( get_permalink() );
                  $cta_class = 'oha-rl-btn';
              } else {
                  $file_path = get_post_meta( $post_id, '_oha_private_path', true );
                  if ( $file_path && file_exists( $file_path ) ) {
                      $cta_label = 'Download';
                      $cta_href  = esc_url( home_url( '/resource-download/' . $post_id ) );
                      $cta_class = 'oha-rl-btn';
                  } else {
                      $cta_label = 'View';
                      $cta_href  = esc_url( get_permalink() );
                      $cta_class = 'oha-rl-btn';
                  }
              }
          } else {
              $cta_label = 'Members Only';
              $cta_href  = $join_url;
              $cta_class = 'oha-rl-btn oha-rl-btn-locked';
          }
        ?>
          <article class="oha-card<?php echo $is_video ? ' oha-card-video' : ''; ?>">
            <a class="oha-thumb" href="<?php echo esc_url( get_permalink() ); ?>">
              <?php if ( has_post_thumbnail() ) : ?>
                <?php the_post_thumbnail( 'medium' ); ?>
                <?php if ( $is_video ) : ?><div class="oha-play-overlay"><span>&#9654;</span></div><?php endif; ?>
              <?php elseif ( $is_video ) : ?>
                <div class="oha-thumb-placeholder oha-thumb-video">
                  <span class="oha-play-icon">&#9654;</span>
                </div>
              <?php else : ?>
                <div class="oha-thumb-placeholder">
                  <span class="oha-type-abbr"><?php echo esc_html( strtoupper( substr( $type_name ?: 'OHA', 0, 3 ) ) ); ?></span>
                </div>
              <?php endif; ?>
            </a>

            <div class="oha-card-body">
              <div class="oha-card-meta-top">
                <?php echo implode( ' ', $badges ); ?>
                <?php if ( $type_name ) : ?>
                  <span class="oha-type-badge"><?php echo esc_html( $type_name ); ?></span>
                <?php endif; ?>
              </div>

              <h3 class="oha-card-title">
                <a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_title(); ?></a>
              </h3>

              <p class="oha-card-excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>

              <div class="oha-card-footer">
                <?php if ( $aud_terms && ! is_wp_error( $aud_terms ) ) : ?>
                  <span class="oha-card-audience"><?php echo esc_html( implode( ', ', wp_list_pluck( $aud_terms, 'name' ) ) ); ?></span>
                <?php else : ?>
                  <span></span>
                <?php endif; ?>
                <a class="<?php echo esc_attr( $cta_class ); ?>" href="<?php echo $cta_href; ?>"><?php echo esc_html( $cta_label ); ?></a>
              </div>
            </div>
          </article>

        <?php endwhile;
        else : ?>
          <p class="oha-rl-empty">No resources found.</p>
        <?php endif;
        wp_reset_postdata(); ?>
      </div>

      <?php if ( $loop->max_num_pages > 1 ) :
        $big = 999999999;
        echo '<nav class="oha-pagination">';
        echo paginate_links( array(
            'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
            'format'  => '?paged=%#%',
            'current' => max( 1, $paged ),
            'total'   => $loop->max_num_pages,
        ) );
        echo '</nav>';
      endif; ?>

    </div>
    <?php
    return ob_get_clean();
} );

// Download button / embed renderer (used on single resource pages)
function oha_rl_download_button( $post_id = 0 ) {
    if ( ! $post_id ) $post_id = get_the_ID();
    if ( ! $post_id ) return '';

    $join_url = esc_url( get_option( 'oha_rl_join_url', wp_login_url( get_permalink( $post_id ) ) ) );

    if ( ! function_exists( 'oha_rl_user_is_member' ) || ! oha_rl_user_is_member() ) {
        return '<div class="oha-cta-locked"><p>This resource is for OHA members.</p><a class="oha-rl-btn" href="' . $join_url . '">Join OHA to Access</a></div>';
    }

    // Video embed takes priority over download
    $embed_url = get_post_meta( $post_id, '_oha_embed_url', true );
    if ( $embed_url ) {
        $embed = wp_oembed_get( esc_url( $embed_url ) );
        if ( $embed ) {
            return '<div class="oha-embed-wrap">' . $embed . '</div>';
        }
    }

    $file_path = get_post_meta( $post_id, '_oha_private_path', true );
    if ( $file_path && file_exists( $file_path ) ) {
        $url = home_url( sprintf( '/resource-download/%d', $post_id ) );
        return '<a class="oha-rl-btn" href="' . esc_url( $url ) . '">Download</a>';
    }

    return '';
}

add_shortcode( 'oha_download_button', function( $atts ) {
    $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'oha_download_button' );
    return oha_rl_download_button( intval( $atts['id'] ) );
} );
