<?php
/**
 * Plugin Name: OHA Vendor Sort
 * Description: Controls sort order for [oha_convention_vendors] cards. Configure under Settings > Vendor Sort.
 * Version: 1.0.0
 * Author: OHA
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OHA_VENDOR_SORT_VERSION', '1.0.0' );

// ── Admin ──────────────────────────────────────────────────────────────────

add_action( 'admin_menu', function () {
    add_options_page( 'Vendor Sort', 'Vendor Sort', 'manage_options', 'oha-vendor-sort', 'oha_vendor_sort_page' );
} );

add_action( 'admin_init', function () {
    register_setting( 'oha_vendor_sort', 'oha_vendor_sort_order', [
        'type'              => 'string',
        'default'           => 'alpha',
        'sanitize_callback' => function ( $v ) {
            return in_array( $v, [ 'alpha', 'booth' ], true ) ? $v : 'alpha';
        },
    ] );
} );

function oha_vendor_sort_page() {
    $current = get_option( 'oha_vendor_sort_order', 'alpha' );
    ?>
    <div class="wrap">
        <h1>Vendor Sort Order</h1>
        <p>Controls how cards appear in the <code>[oha_convention_vendors]</code> shortcode output.</p>
        <form method="post" action="options.php">
            <?php settings_fields( 'oha_vendor_sort' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Sort Order</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="oha_vendor_sort_order" value="alpha" <?php checked( $current, 'alpha' ); ?>>
                                Alphabetical (A-Z by company name)
                            </label>
                            <br><br>
                            <label>
                                <input type="radio" name="oha_vendor_sort_order" value="booth" <?php checked( $current, 'booth' ); ?>>
                                Booth Number (numeric, lowest first; vendors without a booth number go to the end)
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Save Sort Order' ); ?>
        </form>
    </div>
    <?php
}

// ── Frontend ───────────────────────────────────────────────────────────────

add_action( 'wp_footer', function () {
    $order = get_option( 'oha_vendor_sort_order', 'alpha' );
    ?>
    <script>
    (function () {
        var ORDER = <?php echo json_encode( $order ); ?>;

        function sortVendors() {
            var grid = document.querySelector('.oha-vendors-grid');
            if (!grid) return;
            var cards = Array.prototype.slice.call(grid.querySelectorAll('.oha-vendor-card'));
            if (!cards.length) return;

            cards.sort(function (a, b) {
                if (ORDER === 'booth') {
                    var ba = a.getAttribute('data-booth') || '';
                    var bb = b.getAttribute('data-booth') || '';
                    var na = ba !== '' ? parseFloat(ba) : Infinity;
                    var nb = bb !== '' ? parseFloat(bb) : Infinity;
                    if (na !== nb) return na - nb;
                    // Tie-break by name
                    return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '');
                }
                return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '');
            });

            cards.forEach(function (c) { grid.appendChild(c); });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', sortVendors);
        } else {
            sortVendors();
        }
    })();
    </script>
    <?php
} );
