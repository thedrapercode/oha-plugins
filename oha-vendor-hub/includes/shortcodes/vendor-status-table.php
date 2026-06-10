<?php
/**
 * Vendor Status Table Shortcode + UsersWP Account Tab Integration
 *
 * Read-only, searchable, paginated table showing vendor names, contact names, and OHA status.
 * Access is restricted to specific user IDs configured in Vendor Hub Settings.
 *
 * SHORTCODE:  [oha_vendor_status_table]
 *   - Place on any page; renders the table for allowed users, silent to everyone else.
 *   - Optional attribute: per_page (default 20)  e.g. [oha_vendor_status_table per_page="25"]
 *
 * USERSWP TAB: automatically adds a "Vendor Status" tab to /myaccount/
 *   - Uses the same uwp_account_available_tabs / uwp_account_page_title /
 *     uwp_account_content_extra_item hooks as the site's existing custom tabs.
 *   - The tab label is NEVER rendered for users outside the allowed list —
 *     they see zero indication the tab exists.
 *
 * Access control:
 *  - manage_options users (admins) always have access.
 *  - Additional user IDs can be added in Vendor Hub Settings → Vendor Status Table.
 *  - Default allowed user ID: 183.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'oha_vendor_status_table', 'oha_vhcpt_vendor_status_table_shortcode' );

/**
 * Check if the current user is allowed to view the vendor status table.
 *
 * @return bool
 */
function oha_vhcpt_status_table_can_view() {
    if ( ! is_user_logged_in() ) {
        return false;
    }
    // Admins always have access.
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }
    $current_uid = get_current_user_id();
    $allowed_ids = oha_vhcpt_get_status_table_viewer_ids();
    return in_array( $current_uid, $allowed_ids, true );
}

/**
 * Get the list of allowed viewer user IDs.
 *
 * @return int[]
 */
function oha_vhcpt_get_status_table_viewer_ids() {
    $raw = get_option( 'oha_vhcpt_status_table_viewer_ids', '183' );
    if ( empty( $raw ) ) {
        return [ 183 ];
    }
    $parts = array_map( 'trim', explode( ',', $raw ) );
    $ids   = [];
    foreach ( $parts as $part ) {
        if ( is_numeric( $part ) && (int) $part > 0 ) {
            $ids[] = (int) $part;
        }
    }
    return empty( $ids ) ? [ 183 ] : $ids;
}

/**
 * Render the vendor status table shortcode.
 *
 * @param array $atts Shortcode attributes. Supports: per_page (int, default 20).
 * @return string
 */
function oha_vhcpt_vendor_status_table_shortcode( $atts = [] ) {
    if ( ! oha_vhcpt_status_table_can_view() ) {
        return '<p style="color:#666;font-style:italic;">You do not have permission to view this content.</p>';
    }

    $atts     = shortcode_atts( [ 'per_page' => 20, 'year' => '' ], $atts, 'oha_vendor_status_table' );
    $per_page   = max( 1, (int) $atts['per_page'] );
    $year_filter = $atts['year'] !== '' ? intval( $atts['year'] ) : 0;

    $query_args = [
        'post_type'      => 'vendor_application',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    if ( $year_filter ) {
        $query_args['meta_query'] = [ [
            'key'     => '_convention_year',
            'value'   => $year_filter,
            'compare' => '=',
            'type'    => 'NUMERIC',
        ] ];
    }

    $vendors = get_posts( $query_args );

    // Status badge colour map.
    $status_colours = [
        'submitted'        => [ 'bg' => '#e2e8f0', 'text' => '#475569' ],
        'in_review'        => [ 'bg' => '#fef3c7', 'text' => '#92400e' ],
        'approved'         => [ 'bg' => '#d1fae5', 'text' => '#065f46' ],
        'denied'           => [ 'bg' => '#fee2e2', 'text' => '#991b1b' ],
        'agreement_sent'   => [ 'bg' => '#dbeafe', 'text' => '#1e40af' ],
        'agreement_signed' => [ 'bg' => '#e0e7ff', 'text' => '#3730a3' ],
        'paid'             => [ 'bg' => '#bbf7d0', 'text' => '#14532d' ],
    ];

    /**
     * Convert a status string to a normalised key for colour lookup.
     *
     * @param string $status
     * @return string
     */
    $status_key = function ( $status ) {
        return strtolower( str_replace( [ ' ', '-' ], '_', trim( $status ) ) );
    };

    ob_start();

    // Inline styles — scoped to this widget so we never bleed into the theme.
    ?>
    <style type="text/css">
    /* === OHA Vendor Status Table === */
    .oha-vst-wrap {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        max-width: 960px;
        margin: 0 auto;
    }
    .oha-vst-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }
    .oha-vst-header h3 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 600;
        color: #1e293b;
    }
    .oha-vst-search-wrap {
        position: relative;
    }
    .oha-vst-search {
        padding: 8px 12px 8px 36px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 0.9rem;
        width: 240px;
        outline: none;
        background: #fff;
        color: #1e293b;
        transition: border-color .2s;
    }
    .oha-vst-search:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99,102,241,.12);
    }
    .oha-vst-search-icon {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        pointer-events: none;
    }
    .oha-vst-meta {
        font-size: 0.8rem;
        color: #64748b;
    }
    .oha-vst-table-wrap {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 420px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fff;
    }
    .oha-vst-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    .oha-vst-table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f8fafc;
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: #475569;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 1px solid #e2e8f0;
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
    }
    .oha-vst-table thead th:hover {
        background: #f1f5f9;
        color: #1e293b;
    }
    .oha-vst-table thead th .sort-arrow {
        display: inline-block;
        margin-left: 4px;
        opacity: .4;
        font-size: .75em;
    }
    .oha-vst-table thead th.sorted-asc .sort-arrow::after  { content: "▲"; opacity: 1; }
    .oha-vst-table thead th.sorted-desc .sort-arrow::after { content: "▼"; opacity: 1; }
    .oha-vst-table thead th:not(.sorted-asc):not(.sorted-desc) .sort-arrow::after { content: "⇅"; }
    .oha-vst-table tbody tr {
        border-bottom: 1px solid #f1f5f9;
        transition: background .12s;
    }
    .oha-vst-table tbody tr:last-child {
        border-bottom: none;
    }
    .oha-vst-table tbody tr:hover {
        background: #f8fafc;
    }
    .oha-vst-table tbody td {
        padding: 11px 16px;
        color: #1e293b;
        vertical-align: middle;
    }
    .oha-vst-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .oha-vst-no-results {
        text-align: center;
        color: #94a3b8;
        font-style: italic;
        padding: 32px 16px;
    }
    /* === Pagination === */
    .oha-vst-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 14px;
    }
    .oha-vst-page-info {
        font-size: 0.82rem;
        color: #64748b;
    }
    .oha-vst-page-btns {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    .oha-vst-page-btn {
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #fff;
        color: #475569;
        font-size: 0.85rem;
        cursor: pointer;
        line-height: 1;
        transition: background .15s, border-color .15s, color .15s;
    }
    .oha-vst-page-btn:hover:not([disabled]) {
        background: #f1f5f9;
        border-color: #94a3b8;
        color: #1e293b;
    }
    .oha-vst-page-btn.active {
        background: #6366f1;
        border-color: #6366f1;
        color: #fff;
        font-weight: 600;
        cursor: default;
    }
    .oha-vst-page-btn[disabled] {
        opacity: 0.35;
        cursor: not-allowed;
    }
    @media (max-width: 600px) {
        .oha-vst-search { width: 100%; }
        .oha-vst-header { flex-direction: column; align-items: flex-start; }
        .oha-vst-pagination { flex-direction: column; align-items: flex-start; }
    }
    </style>

    <div class="oha-vst-wrap">
        <div class="oha-vst-header">
            <h3>Vendor Status<?php echo $year_filter ? ' - ' . esc_html( $year_filter ) : ''; ?></h3>
            <div>
                <div class="oha-vst-search-wrap">
                    <svg class="oha-vst-search-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="oha-vst-search" class="oha-vst-search" placeholder="Search vendors…" autocomplete="off">
                </div>
            </div>
        </div>
        <p class="oha-vst-meta" id="oha-vst-count-label">
            <?php
            $total = count( $vendors );
            printf(
                'Showing <strong id="oha-vst-visible">%d</strong> of <strong>%d</strong> vendor application%s',
                min( $per_page, $total ),
                $total,
                $total === 1 ? '' : 's'
            );
            ?>
        </p>

        <div class="oha-vst-table-wrap">
            <table class="oha-vst-table" id="oha-vst-table">
                <thead>
                    <tr>
                        <th data-col="0">
                            Vendor / Company Name <span class="sort-arrow"></span>
                        </th>
                        <th data-col="1">
                            Contact Name <span class="sort-arrow"></span>
                        </th>
                        <th data-col="2">
                            OHA Status <span class="sort-arrow"></span>
                        </th>
                    </tr>
                </thead>
                <tbody id="oha-vst-tbody">
                    <?php if ( empty( $vendors ) ) : ?>
                        <tr>
                            <td colspan="3" class="oha-vst-no-results">No vendor applications found.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $vendors as $vendor ) :
                            $company = trim( get_post_meta( $vendor->ID, 'company_name', true ) );
                            $first   = trim( get_post_meta( $vendor->ID, 'first_name',   true ) );
                            $last    = trim( get_post_meta( $vendor->ID, 'last_name',    true ) );
                            $contact = trim( $first . ' ' . $last );
                            if ( empty( $company ) ) {
                                $company = $contact ?: '(No name)';
                            }
                            $raw_status  = get_post_meta( $vendor->ID, '_oha_status', true ) ?: 'Submitted';
                            $skey        = $status_key( $raw_status );
                            $colour      = $status_colours[ $skey ] ?? [ 'bg' => '#f1f5f9', 'text' => '#475569' ];
                        ?>
                        <tr data-search="<?php echo esc_attr( strtolower( $company . ' ' . $contact . ' ' . $raw_status ) ); ?>">
                            <td><?php echo esc_html( $company ); ?></td>
                            <td><?php echo esc_html( $contact ?: '—' ); ?></td>
                            <td>
                                <span class="oha-vst-badge" style="background:<?php echo esc_attr( $colour['bg'] ); ?>;color:<?php echo esc_attr( $colour['text'] ); ?>;">
                                    <?php echo esc_html( $raw_status ); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="oha-vst-pagination" id="oha-vst-pagination"></div>
    </div>

    <script type="text/javascript">
    (function () {
        'use strict';

        var PER_PAGE     = <?php echo (int) $per_page; ?>;
        var currentPage  = 1;
        var currentQuery = '';
        var sortState    = { col: -1, dir: 'asc' };

        var searchInput  = document.getElementById('oha-vst-search');
        var tbody        = document.getElementById('oha-vst-tbody');
        var table        = document.getElementById('oha-vst-table');
        var headers      = table ? table.querySelectorAll('thead th') : [];
        var paginationEl = document.getElementById('oha-vst-pagination');
        var countLabel   = document.getElementById('oha-vst-count-label');

        if (!searchInput || !tbody) return;

        // ── Helpers ─────────────────────────────────────────────────

        function getAllRows() {
            return Array.from(tbody.querySelectorAll('tr[data-search]'));
        }

        function getFilteredRows() {
            var q   = currentQuery.toLowerCase().trim();
            var all = getAllRows();
            if (!q) return all;
            return all.filter(function (row) {
                return row.getAttribute('data-search').indexOf(q) !== -1;
            });
        }

        // ── Render page ──────────────────────────────────────────────

        function renderPage() {
            var filtered   = getFilteredRows();
            var total      = filtered.length;
            var totalPages = Math.max(1, Math.ceil(total / PER_PAGE));

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1)         currentPage = 1;

            var start = (currentPage - 1) * PER_PAGE;
            var end   = start + PER_PAGE;

            // Build a Set of rows that belong on this page for O(1) lookup.
            var pageSet = {};
            for (var i = start; i < end && i < total; i++) {
                pageSet[i] = filtered[i];
            }

            // Show only rows in the current page's slice of filtered rows.
            getAllRows().forEach(function (row) {
                row.style.display = 'none';
            });
            for (var i = start; i < end && i < total; i++) {
                filtered[i].style.display = '';
            }

            // "No results" message.
            var noResult = tbody.querySelector('.oha-vst-no-results-dynamic');
            if (total === 0) {
                if (!noResult) {
                    var tr = document.createElement('tr');
                    tr.className = 'oha-vst-no-results-dynamic';
                    tr.innerHTML = '<td colspan="3" class="oha-vst-no-results">No results match your search.</td>';
                    tbody.appendChild(tr);
                }
            } else if (noResult) {
                noResult.remove();
            }

            updateCountLabel(total, start, end);
            renderPagination(total, totalPages);
        }

        function updateCountLabel(total, start, end) {
            if (!countLabel) return;
            var allTotal   = getAllRows().length;
            var showStart  = total > 0 ? start + 1 : 0;
            var showEnd    = Math.min(end, total);

            if (currentQuery) {
                countLabel.innerHTML =
                    'Showing <strong>' + showStart + '&#8211;' + showEnd + '</strong>' +
                    ' of <strong>' + total + '</strong> matching vendor' + (total === 1 ? '' : 's') +
                    ' <span style="color:#94a3b8;">(filtered from ' + allTotal + ' total)</span>';
            } else {
                countLabel.innerHTML =
                    'Showing <strong>' + showStart + '&#8211;' + showEnd + '</strong>' +
                    ' of <strong>' + total + '</strong> vendor application' + (total === 1 ? '' : 's');
            }
        }

        // ── Pagination bar ───────────────────────────────────────────

        function renderPagination(total, totalPages) {
            if (!paginationEl) return;

            if (totalPages <= 1) {
                paginationEl.innerHTML = '';
                paginationEl.style.display = 'none';
                return;
            }
            paginationEl.style.display = '';

            // Page-number window: up to 5 buttons centred on current page.
            var winStart = Math.max(1, currentPage - 2);
            var winEnd   = Math.min(totalPages, winStart + 4);
            winStart     = Math.max(1, winEnd - 4);

            function btn(label, page, extra) {
                var disabled = (extra === 'disabled') ? ' disabled' : '';
                var active   = (extra === 'active')   ? ' active'   : '';
                return '<button class="oha-vst-page-btn' + active + '" data-page="' + page + '"' + disabled + '>' + label + '</button>';
            }

            var html = '<div class="oha-vst-page-info">Page ' + currentPage + ' of ' + totalPages + '</div>';
            html += '<div class="oha-vst-page-btns">';
            html += btn('«', 1,                   currentPage === 1          ? 'disabled' : '');
            html += btn('‹', currentPage - 1,     currentPage === 1          ? 'disabled' : '');
            for (var p = winStart; p <= winEnd; p++) {
                html += btn(p, p, p === currentPage ? 'active' : '');
            }
            html += btn('›', currentPage + 1,     currentPage === totalPages ? 'disabled' : '');
            html += btn('»', totalPages,           currentPage === totalPages ? 'disabled' : '');
            html += '</div>';

            paginationEl.innerHTML = html;

            paginationEl.querySelectorAll('.oha-vst-page-btn:not([disabled]):not(.active)').forEach(function (b) {
                b.addEventListener('click', function () {
                    currentPage = parseInt(b.getAttribute('data-page'), 10);
                    renderPage();
                    // Scroll the top of the widget into view — works inside iframes too.
                    var wrap = document.querySelector('.oha-vst-wrap');
                    if (wrap) {
                        wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });
        }

        // ── Search ───────────────────────────────────────────────────

        searchInput.addEventListener('input', function () {
            currentQuery = this.value;
            currentPage  = 1;
            renderPage();
        });

        // ── Sort ─────────────────────────────────────────────────────

        headers.forEach(function (th) {
            th.addEventListener('click', function () {
                var col = parseInt(th.getAttribute('data-col'), 10);
                if (sortState.col === col) {
                    sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
                } else {
                    sortState.col = col;
                    sortState.dir = 'asc';
                }

                headers.forEach(function (h) { h.classList.remove('sorted-asc', 'sorted-desc'); });
                th.classList.add('sorted-' + sortState.dir);

                // Re-order all rows in the DOM so getFilteredRows() stays in sort order.
                var rows = getAllRows();
                rows.sort(function (a, b) {
                    var aText = (a.cells[col] ? a.cells[col].textContent.trim() : '').toLowerCase();
                    var bText = (b.cells[col] ? b.cells[col].textContent.trim() : '').toLowerCase();
                    if (aText < bText) return sortState.dir === 'asc' ? -1 : 1;
                    if (aText > bText) return sortState.dir === 'asc' ?  1 : -1;
                    return 0;
                });
                rows.forEach(function (row) { tbody.appendChild(row); });

                currentPage = 1;
                renderPage();
            });
        });

        // ── Initial render ───────────────────────────────────────────
        renderPage();

    })();
    </script>
    <?php

    return ob_get_clean();
}


/* ==========================================================================
   UsersWP /myaccount/ Tab Integration
   Mirrors the exact same hook pattern used by the site's existing custom tabs
   (uwp_account_available_tabs, uwp_account_page_title, uwp_account_form_display).
   ========================================================================== */

/**
 * Register the "Vendor Status" tab in the UsersWP account nav.
 *
 * Only injected when the current user has permission — the tab label itself
 * never appears for anyone outside the allowed list.
 */
add_filter( 'uwp_account_available_tabs', 'oha_vhcpt_register_account_tab', 10, 1 );

function oha_vhcpt_register_account_tab( $tabs ) {
    // Guard: only show the tab for allowed users.
    if ( ! oha_vhcpt_status_table_can_view() ) {
        return $tabs;
    }

    $tabs['vendor-status'] = [
        'title' => __( 'Vendor Status', 'userswp' ),
        'icon'  => 'fas fa-store-alt',
    ];

    return $tabs;
}

/**
 * Set the page heading when the Vendor Status tab is active.
 * Matches the uwp_account_page_title pattern used by the site's existing tabs.
 */
add_filter( 'uwp_account_page_title', 'oha_vhcpt_account_tab_title', 10, 2 );

function oha_vhcpt_account_tab_title( $title, $type ) {
    if ( $type === 'vendor-status' ) {
        $title = __( 'Vendor Status', 'userswp' );
    }
    return $title;
}

/**
 * Render the tab content when active.
 * Uses uwp_account_form_display — the same hook as the site's existing
 * Track Order and Membership tabs.
 *
 * The shortcode function already performs its own access check, so even if
 * someone crafts a direct request to this tab type, they get the same
 * "no permission" message and zero data.
 */
add_filter( 'uwp_account_form_display', 'oha_vhcpt_account_tab_content', 10, 1 );

function oha_vhcpt_account_tab_content( $type ) {
    if ( $type !== 'vendor-status' ) {
        return;
    }
    // Re-uses the shortcode renderer — single source of truth for output & access.
    echo oha_vhcpt_vendor_status_table_shortcode();
}
