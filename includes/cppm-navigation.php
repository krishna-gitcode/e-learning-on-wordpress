<?php
/**
 * Core: Global Navigation, Mobile App Bar, and Dropdowns
 * Architecture: Modular / Asset Separated
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. ENQUEUE NAVIGATION ASSETS
// ==========================================
add_action( 'wp_enqueue_scripts', 'cppm_enqueue_navigation_assets', 99 );
function cppm_enqueue_navigation_assets() {
    $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
    
    // Enqueue CSS & JS globally since navigation is everywhere
    wp_enqueue_style( 'cppm-navigation-css', $plugin_url . 'assets/css/cppm-navigation.css', array(), '1.0.0' );
    wp_enqueue_script( 'cppm-navigation-js', $plugin_url . 'assets/js/cppm-navigation.js', array(), '1.0.0', true );
}

// ==========================================
// 2. DYNAMICALLY HIDE "MY CLASSROOM" IN MENU
// ==========================================
add_filter( 'wp_nav_menu_objects', 'cppm_hide_classroom_menu_item', 10, 2 );
function cppm_hide_classroom_menu_item( $items, $args ) {
    if ( is_user_logged_in() ) {
        return $items;
    }

    foreach ( $items as $key => $item ) {
        if ( strtolower( trim( $item->title ) ) === 'my classroom' ) {
            unset( $items[$key] );
        }
    }

    return $items;
}

// ==========================================
// 3. SMART ICON-BASED DROPDOWN MENU
// ==========================================
add_shortcode( 'cppm_auth_icon', 'cppm_render_auth_icon' );
function cppm_render_auth_icon() {
    ob_start();
    
    $is_logged_in = is_user_logged_in();
    $ui_brand     = get_option('cppm_ui_btn_color', '#2874f0'); // Defaulting to Flipkart Blue
    
    // Pass the brand color dynamically as a CSS variable to the wrapper
    echo '<div class="cppm-auth-wrap" style="--cppm-brand-color: ' . esc_attr($ui_brand) . ';">';
    
    if ( $is_logged_in ) {
        $current_user = wp_get_current_user();
        $first_name   = $current_user->user_firstname ? $current_user->user_firstname : $current_user->display_name;
        $my_account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink( 'myaccount' ) : home_url('/my-account/');
        
        ?>
        <div class="cppm-auth-trigger">
            <span class="cppm-auth-icon-circle">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </span>
            <span class="cppm-auth-text" style="display:inline-block; max-width: 90px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle;">
                <?php echo esc_html($first_name); ?>
            </span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:-4px;"><polyline points="6 9 12 15 18 9"></polyline></svg>
        </div>
        
        <div class="cppm-dropdown-content">
            <div class="cppm-dropdown-header">
                <span class="cppm-user-name"><?php echo esc_html($current_user->display_name); ?></span>
                <span class="cppm-user-email"><?php echo esc_html($current_user->user_email); ?></span>
            </div>
            <a href="<?php echo esc_url($my_account_url); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline></svg> My Classroom</a>
            <a href="<?php echo esc_url($my_account_url . 'orders/'); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> My Orders</a>
            <a href="<?php echo esc_url($my_account_url . 'edit-address/'); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> Manage Address</a>
            <a href="<?php echo wp_logout_url( home_url() ); ?>" style="color: #dc2626;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Logout</a>
        </div>
        <?php
    } else {
        $login_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
        ?>
        <div class="cppm-auth-trigger" onclick="window.location.href='<?php echo esc_url($login_url); ?>'">
            <span class="cppm-auth-icon-circle">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
            </span>
            <span class="cppm-auth-text">Login</span>
        </div>
        <?php
    }
    
    echo '</div>'; // close wrap
    
    return ob_get_clean();
}

// ==========================================
// 4. MOBILE BOTTOM NAVIGATION (FLIPKART/AMAZON STYLE)
// ==========================================
add_shortcode( 'cppm_mobile_bottom_nav', 'cppm_render_mobile_bottom_nav' );
function cppm_render_mobile_bottom_nav() {
    $ui_brand     = get_option('cppm_ui_btn_color', '#2874f0');
    $current_url  = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    
    $home_url     = home_url('/');
    $shop_url     = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
    $cart_url     = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
    $account_url  = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
    
    $is_home    = ( untrailingslashit($current_url) === untrailingslashit($home_url) ) ? 'active' : '';
    $is_shop    = ( strpos($current_url, '/shop/') !== false || strpos($current_url, '/product-category/') !== false ) ? 'active' : '';
    $is_cart    = ( strpos($current_url, '/cart/') !== false || strpos($current_url, '/checkout/') !== false ) ? 'active' : '';
    $is_account = ( strpos($current_url, '/my-account/') !== false ) ? 'active' : '';

    ob_start();
    ?>
    <div class="cppm-mobile-bottom-nav" style="--cppm-brand-color: <?php echo esc_attr($ui_brand); ?>;">
        <a href="<?php echo esc_url($home_url); ?>" class="cppm-nav-item <?php echo $is_home; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            Home
        </a>
        <a href="<?php echo esc_url($shop_url); ?>" class="cppm-nav-item <?php echo $is_shop; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            Categories
        </a>
        <a href="<?php echo esc_url($cart_url); ?>" class="cppm-nav-item <?php echo $is_cart; ?>">
            <div style="position:relative;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                <?php if ( function_exists('WC') && WC()->cart && WC()->cart->get_cart_contents_count() > 0 ) : ?>
                    <span style="position:absolute; top:-5px; right:-8px; background:#ef4444; color:#fff; font-size:10px; font-weight:bold; border-radius:50%; width:16px; height:16px; display:flex; align-items:center; justify-content:center;">
                        <?php echo WC()->cart->get_cart_contents_count(); ?>
                    </span>
                <?php endif; ?>
            </div>
            Cart
        </a>
        <a href="<?php echo esc_url($account_url); ?>" class="cppm-nav-item <?php echo $is_account; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            Account
        </a>
    </div>
    <?php
    return ob_get_clean();
}