<?php
/**
 * Core: The "God-Mode" Override Engine (Speed & DOM Cleanup)
 * Architecture: Pure Logic (Nukes Theme/Woo Defaults Before Render)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp', 'cppm_nuke_theme_defaults', 9999 );
function cppm_nuke_theme_defaults() {
    
    // ==========================================
    // 1. THE LOGIN SCREEN TAKEOVER
    // ==========================================
    // If on the My Account page and NOT logged in, nuke the entire Astra Header & Footer.
    // This turns the page into a pure, native-app style split screen.
    if ( function_exists('is_account_page') && is_account_page() && ! is_user_logged_in() ) {
        // Stop the server from building the Header and Footer
        remove_action( 'astra_header', 'astra_header_markup' );
        remove_action( 'astra_footer', 'astra_footer_markup' );
        
        // Stop the server from building Page Titles
        remove_action( 'astra_entry_top', 'astra_entry_header_markup' );
        remove_action( 'astra_archive_header', 'astra_archive_page_info' );
    }

    // ==========================================
    // 2. THE STUDENT DASHBOARD CLEANUP
    // ==========================================
    // Once logged in, we want the header/footer back, but we still don't want Astra's default page titles cluttering our UI.
    if ( function_exists('is_account_page') && is_account_page() && is_user_logged_in() ) {
        remove_action( 'astra_entry_top', 'astra_entry_header_markup' );
    }

    // ==========================================
    // 3. SINGLE PRODUCT DOM REDUCTION
    // ==========================================
    if ( function_exists('is_product') && is_product() ) {
        // Remove standard WooCommerce meta (SKU, default categories, tags)
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
        
        // Nuke Astra's specific WooCommerce injections (which often duplicate Woo's defaults)
        remove_action( 'woocommerce_single_product_summary', 'astra_woo_single_product_category', 5 );
        remove_action( 'woocommerce_single_product_summary', 'astra_woo_single_product_category', 10 );
        remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
    }

    // ==========================================
    // 4. STOREFRONT & SEARCH DOM REDUCTION
    // ==========================================
    // Clean up the shop loops so our custom Flipkart-style grid renders cleanly without fighting the theme.
    if ( is_search() || ( function_exists('is_woocommerce') && is_woocommerce() && !is_product() ) ) {
        // Remove default WooCommerce sorting and result counts
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
        
        // Remove Astra/Woo Page Titles (We built our own custom ones)
        add_filter( 'woocommerce_show_page_title', '__return_false' );
        add_filter( 'astra_the_search_page_title', '__return_false' );
        remove_action( 'astra_archive_header', 'astra_archive_page_info' );
    }
}

// ==========================================
// 5. SEARCH RESULTS THUMBNAIL CLEANUP
// ==========================================
// This runs slightly later to ensure WooCommerce has fully loaded its loop template hooks
add_action('wp', 'cppm_nuke_search_thumbnails', 10000);
function cppm_nuke_search_thumbnails() {
    if ( is_search() ) {
        remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
        remove_action( 'woocommerce_before_shop_loop_item_title', 'astra_woo_shop_thumbnail', 10 );
        remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
        remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
    }
}