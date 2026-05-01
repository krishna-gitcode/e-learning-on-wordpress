<?php
/**
 * Core: Search Results UI & Grid/List View Toggles
 * Architecture: Modular / Asset Separated
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. ENQUEUE SEARCH & LAYOUT ASSETS
// ==========================================
add_action( 'wp_enqueue_scripts', 'cppm_enqueue_search_assets', 999 );
function cppm_enqueue_search_assets() {
    // Only load these assets on Search pages, the Shop page, or Product Category pages
    if ( is_search() || ( function_exists('is_woocommerce') && is_woocommerce() ) ) {
        $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
        
        wp_enqueue_style( 'cppm-search-css', $plugin_url . 'assets/css/search-results.css', array(), '1.0.0' );
        wp_enqueue_script( 'cppm-search-js', $plugin_url . 'assets/js/search-results.js', array(), '1.0.0', true );
    }
}

// ==========================================
// 2. KILL DEFAULT THEME & WOOCOMMERCE CLUTTER
// ==========================================
add_filter( 'woocommerce_show_page_title', '__return_false' );
add_filter( 'astra_the_search_page_title', '__return_false' );
remove_action( 'astra_archive_header', 'astra_archive_page_info' );

// Remove default Woo sorting and result counts
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

// Clean up product cards specifically on search result pages
add_action('wp', 'cppm_remove_search_thumbnails', 11);
function cppm_remove_search_thumbnails() {
    if ( is_search() ) {
        remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
        remove_action( 'woocommerce_before_shop_loop_item_title', 'astra_woo_shop_thumbnail', 10 );
        remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
        remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
    }
}

// ==========================================
// 3. RENDER CUSTOM SEARCH HEADER & TOGGLES
// ==========================================
add_filter( 'woocommerce_product_loop_start', 'cppm_global_strict_grid', 99 );
function cppm_global_strict_grid( $html ) {
    
    // 1. Override the default UL to force our responsive strict grid class
    $html = '<ul class="products columns-4 cppm-strict-grid">';
    
    // 2. Calculate the dynamic title
    $search_query = get_search_query();
    $title = is_search() ? 'Results for "' . esc_html($search_query) . '"' : woocommerce_page_title(false);

    // 3. Inject our clean Search Header and HTML structure
    ob_start();
    ?>
    <div class="cppm-search-header">
        <h1 class="cppm-search-title"><?php echo $title; ?></h1>
        <div class="cppm-header-actions">
            <button class="cppm-search-trigger">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <span>Search</span>
            </button>
            
            <div class="cppm-view-toggle">
                <button class="cppm-view-btn active" data-view="grid" aria-label="Grid View">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </button>
                <button class="cppm-view-btn" data-view="list" aria-label="List View">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                </button>
            </div>
        </div>
    </div>

    <div class="cppm-search-overlay">
        <div class="cppm-search-box">
            <form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:flex; width:100%; position:relative;">
                <input type="search" name="s" placeholder="Search for courses, instruments..." value="<?php echo esc_attr(get_search_query()); ?>" autocomplete="off">
                <input type="hidden" name="post_type" value="product" />
                <button type="submit" aria-label="Submit Search">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </button>
            </form>
        </div>
    </div>
    <?php
    $header = ob_get_clean();
    
    // Return the custom header followed by the opening <ul> tag
    return $header . $html;
}