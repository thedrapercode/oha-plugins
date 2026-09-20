<?php
/**
 * OHA search bar partial.
 * Lightweight GD-compatible search form for listing pages.
 * Submits to the GD search page. Autocomplete attaches automatically via
 * gd-autocomplete.js which targets any form containing input[name="snear"].
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'geodir_search_page_base_url' ) ) return;
$oha_search_action = geodir_search_page_base_url();
?>
<div class="oha-search-bar" role="search" aria-label="Search Haunts">
    <p class="oha-search-bar__label">Search Ohio Haunts</p>
    <form class="oha-search-bar__form" name="geodir-listing-search"
          action="<?php echo esc_url( $oha_search_action ); ?>" method="get">
        <input type="hidden" name="geodir_search" value="1">
        <input type="hidden" name="sgeo_lat" class="sgeo_lat" value="">
        <input type="hidden" name="sgeo_lon" class="sgeo_lon" value="">
        <div class="oha-search-bar__row">
            <div class="oha-search-bar__field oha-search-bar__field--icon">
                <svg class="oha-search-bar__field-icon" viewBox="0 0 24 24" width="15" height="15" aria-hidden="true" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <input type="text" name="s" class="oha-search-bar__input"
                       placeholder="Haunt Name" autocomplete="off">
            </div>
            <div class="oha-search-bar__field oha-search-bar__field--icon">
                <svg class="oha-search-bar__field-icon" viewBox="0 0 24 24" width="15" height="15" aria-hidden="true" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                <input type="text" name="snear" class="oha-search-bar__input"
                       placeholder="City, State" autocomplete="off">
            </div>
            <button type="submit" class="oha-search-bar__btn" aria-label="Search">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            </button>
        </div>
    </form>
</div>