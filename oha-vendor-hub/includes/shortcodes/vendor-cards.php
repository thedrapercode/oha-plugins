<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * [oha_vendor_cards] — Public vendor card grid for website pages.
 *
 * Displays vendors with _oha_status = Paid in a responsive card grid.
 * Light theme, no favorites button. Client-side search included.
 *
 * Atts:
 *   title="Our Convention Vendors"  — section heading (empty string to hide)
 *   search="yes"                          — show/hide search bar (default: yes)
 */
function oha_vendor_cards_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'title'  => 'Our Convention Vendors',
        'search' => 'yes',
    ], $atts, 'oha_vendor_cards' );

    $show_search = ( strtolower( trim( $atts['search'] ) ) !== 'no' );

    // Admin-controlled field visibility
    $cards_fields = get_option( 'oha_vhcpt_cards_fields', [ 'website' => true, 'products' => true ] );
    $show_website  = ! empty( $cards_fields['website'] );
    $show_products = ! empty( $cards_fields['products'] );

    // Admin-set placeholder image
    $ph_id  = (int) get_option( 'oha_vhcpt_cards_placeholder_id', 0 );
    $ph_url = '';
    if ( $ph_id ) {
        $src = wp_get_attachment_image_src( $ph_id, [ 400, 300 ] );
        if ( $src ) $ph_url = $src[0];
    }
    if ( ! $ph_url ) {
        $ph_url = get_option( 'oha_vhcpt_cards_placeholder_url', '' );
    }

    $active_year = intval( get_option( 'oha_vhcpt_active_year', date('Y') ) );

    $vendors = get_posts( [
        'post_type'      => 'vendor_application',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => '_oha_status',
                'value'   => [ 'Paid', 'paid', 'PAID' ],
                'compare' => 'IN',
            ],
            [
                'key'     => 'company_name',
                'compare' => 'EXISTS',
            ],
            [
                'key'     => '_convention_year',
                'value'   => $active_year,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ],
        'orderby'  => 'meta_value',
        'meta_key' => 'company_name',
        'order'    => 'ASC',
    ] );

    $uid = 'oha-vc-' . wp_rand( 1000, 9999 );

    ob_start(); ?>
    <style>
    .oha-vc-wrap { font-family: inherit; }
    .oha-vc-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px; }
    .oha-vc-title { font-size: 1.5em; font-weight: 700; margin: 0; color: #1a1a1a; }
    .oha-vc-count { font-size: 13px; color: #666; }
    .oha-vc-search-wrap { width: 100%; max-width: 340px; }
    .oha-vc-search {
        width: 100%;
        box-sizing: border-box;
        padding: 9px 14px;
        border: 1.5px solid #d0d0d0;
        border-radius: 8px;
        font-size: 14px;
        outline: none;
        transition: border-color .15s;
        background: #fff;
        color: #1a1a1a;
    }
    .oha-vc-search:focus { border-color: #c0392b; }
    .oha-vc-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }
    @media (max-width: 860px) {
        .oha-vc-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
    }
    @media (max-width: 500px) {
        .oha-vc-grid { grid-template-columns: 1fr; gap: 12px; }
        .oha-vc-search-wrap { max-width: 100%; }
    }
    .oha-vc-card {
        background: #fff;
        border: 1px solid #e5e5e5;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,.07);
        display: flex;
        flex-direction: column;
        transition: box-shadow .15s, transform .15s;
    }
    .oha-vc-card:hover { box-shadow: 0 6px 18px rgba(0,0,0,.12); transform: translateY(-2px); }
    .oha-vc-card[data-hidden="1"] { display: none; }
    .oha-vc-logo-wrap {
        width: 100%;
        aspect-ratio: 4/3;
        background: #f7f7f7;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-bottom: 1px solid #eee;
        flex-shrink: 0;
    }
    .oha-vc-logo {
        max-width: 100%;
        max-height: 100%;
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 16px;
        box-sizing: border-box;
    }
    .oha-vc-placeholder {
        font-size: 52px;
        line-height: 1;
    }
    .oha-vc-body { padding: 14px 16px 16px; flex: 1; display: flex; flex-direction: column; gap: 6px; }
    .oha-vc-name { font-size: 15px; font-weight: 700; color: #1a1a1a; line-height: 1.25; margin: 0; }
    .oha-vc-products { font-size: 12px; color: #666; margin: 0; }
    .oha-vc-site-link {
        display: inline-block;
        margin-top: auto;
        padding-top: 8px;
        font-size: 12px;
        color: #c0392b;
        text-decoration: none;
        font-weight: 600;
        word-break: break-all;
    }
    .oha-vc-site-link:hover { text-decoration: underline; }
    .oha-vc-no-results { grid-column: 1/-1; text-align: center; color: #888; padding: 32px 0; font-size: 15px; display: none; }
    </style>

    <div class="oha-vc-wrap" id="<?php echo esc_attr( $uid ); ?>">

        <div class="oha-vc-header">
            <?php if ( $atts['title'] !== '' ) : ?>
                <h2 class="oha-vc-title"><?php echo esc_html( $atts['title'] ); ?></h2>
            <?php endif; ?>
            <span class="oha-vc-count"><?php echo count( $vendors ); ?> vendor<?php echo count( $vendors ) !== 1 ? 's' : ''; ?> confirmed</span>
            <?php if ( $show_search ) : ?>
                <div class="oha-vc-search-wrap">
                    <input type="search" class="oha-vc-search" placeholder="Search vendors..." aria-label="Search vendors">
                </div>
            <?php endif; ?>
        </div>

        <div class="oha-vc-grid">

            <?php if ( empty( $vendors ) ) : ?>
                <p style="color:#666;grid-column:1/-1;">No confirmed vendors yet — check back soon!</p>
            <?php else : ?>

                <?php foreach ( $vendors as $vendor ) :
                    $company  = get_post_meta( $vendor->ID, 'company_name', true );
                    $website  = get_post_meta( $vendor->ID, 'website', true );
                    $products = get_post_meta( $vendor->ID, 'product_type', true );
                    $logo_id  = get_post_meta( $vendor->ID, '_company_logo_id', true );
                    $logo_url = '';

                    if ( $logo_id ) {
                        $src = wp_get_attachment_image_src( (int) $logo_id, [ 400, 300 ] );
                        if ( $src ) $logo_url = $src[0];
                    }
                    if ( ! $logo_url ) {
                        $logo_url = get_post_meta( $vendor->ID, '_company_logo_url', true );
                    }

                    $display_name = $company ? $company : get_the_title( $vendor->ID );
                    $site_label   = '';
                    if ( $website ) {
                        $site_label = preg_replace( '#^https?://(www\.)?#i', '', rtrim( $website, '/' ) );
                    }
                ?>
                <div class="oha-vc-card"
                     data-name="<?php echo esc_attr( strtolower( $display_name ) ); ?>"
                     data-products="<?php echo esc_attr( strtolower( $products ) ); ?>">

                    <div class="oha-vc-logo-wrap">
                        <?php if ( $logo_url ) : ?>
                            <img class="oha-vc-logo"
                                 src="<?php echo esc_url( $logo_url ); ?>"
                                 alt="<?php echo esc_attr( $display_name ); ?> logo"
                                 loading="lazy">
                        <?php elseif ( $ph_url ) : ?>
                            <img class="oha-vc-logo"
                                 src="<?php echo esc_url( $ph_url ); ?>"
                                 alt="Vendor placeholder"
                                 loading="lazy">
                        <?php else : ?>
                            <span class="oha-vc-placeholder">&#x1F6CD;</span>
                        <?php endif; ?>
                    </div>

                    <div class="oha-vc-body">
                        <p class="oha-vc-name"><?php echo esc_html( $display_name ); ?></p>
                        <?php if ( $show_products && $products ) : ?>
                            <p class="oha-vc-products"><?php echo esc_html( $products ); ?></p>
                        <?php endif; ?>
                        <?php if ( $show_website && $website && $site_label ) : ?>
                            <a class="oha-vc-site-link"
                               href="<?php echo esc_url( $website ); ?>"
                               target="_blank"
                               rel="noopener noreferrer">
                                <?php echo esc_html( $site_label ); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>

            <?php endif; ?>

            <p class="oha-vc-no-results">No vendors match your search.</p>
        </div>

    </div>

    <?php if ( $show_search ) : ?>
    <script>
    (function(){
        var wrap  = document.getElementById(<?php echo json_encode( $uid ); ?>);
        if (!wrap) return;
        var input = wrap.querySelector('.oha-vc-search');
        var cards = wrap.querySelectorAll('.oha-vc-card');
        var none  = wrap.querySelector('.oha-vc-no-results');
        var count = wrap.querySelector('.oha-vc-count');
        var total = cards.length;

        input.addEventListener('input', function(){
            var q = this.value.trim().toLowerCase();
            var visible = 0;
            cards.forEach(function(c){
                var match = !q
                    || c.dataset.name.indexOf(q) !== -1
                    || c.dataset.products.indexOf(q) !== -1;
                c.dataset.hidden = match ? '0' : '1';
                if (match) visible++;
            });
            none.style.display   = (visible === 0) ? 'block' : 'none';
            count.textContent    = (q ? visible + ' of ' + total : total)
                + ' vendor' + (total !== 1 ? 's' : '')
                + (q ? ' found' : ' confirmed');
        });
    })();
    </script>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}
add_shortcode( 'oha_vendor_cards', 'oha_vendor_cards_shortcode' );
