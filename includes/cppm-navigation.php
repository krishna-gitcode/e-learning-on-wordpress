<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================
// 1. DYNAMICALLY HIDE "MY CLASSROOM" IN MENU
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
// 2. SMART ICON-BASED DROPDOWN MENU
// ==========================================
// Shortcode: [cppm_auth_icon]
add_shortcode( 'cppm_auth_icon', 'cppm_render_auth_icon' );
function cppm_render_auth_icon() {
    ob_start();
    
    $is_logged_in = is_user_logged_in();
    $ui_brand     = get_option('cppm_ui_btn_color', '#2563eb');
    
    // URLs
    $login_url   = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url( home_url() );
    $profile_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : admin_url('profile.php');
    $logout_url  = wp_logout_url( home_url() );
    
    // Premium User SVG
    $icon_user = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';

    ?>
    <style>
        .cppm-auth-wrap {
            position: relative;
            display: inline-block;
            font-family: system-ui, sans-serif;
            z-index: 9999;
        }
        .cppm-auth-icon-btn {
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            width: 40px; 
            height: 40px; 
            border-radius: 50%;
            background: #f8fafc; 
            color: #0f172a;
            border: 1px solid #e2e8f0; 
            transition: all 0.2s ease;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            box-sizing: border-box;
            cursor: pointer;
        }
        .cppm-auth-wrap:hover .cppm-auth-icon-btn,
        .cppm-auth-wrap:focus-within .cppm-auth-icon-btn { 
            background: var(--brand); 
            color: #ffffff; 
            border-color: var(--brand); 
            transform: translateY(-2px); 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .cppm-auth-icon-btn svg {
            flex-shrink: 0;
        }
        .cppm-auth-dropdown {
            visibility: hidden;
            opacity: 0;
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: #ffffff;
            min-width: 160px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            padding: 8px 0;
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease;
            transform: translateY(-10px);
        }
        /* Show dropdown on hover or when tapped on mobile */
        .cppm-auth-wrap:hover .cppm-auth-dropdown,
        .cppm-auth-wrap:focus-within .cppm-auth-dropdown {
            visibility: visible;
            opacity: 1;
            transform: translateY(0);
        }
        /* Invisible bridge so the menu doesn't close when moving the mouse down */
        .cppm-auth-wrap::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            height: 15px;
        }
        .cppm-auth-dropdown a {
            padding: 10px 20px;
            color: #475569;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.2s;
        }
        .cppm-auth-dropdown a:hover {
            background: #f8fafc;
            color: var(--brand);
        }
        .cppm-auth-dropdown .cppm-logout-link:hover {
            color: #dc2626;
            background: #fef2f2;
        }
    </style>

    <div class="cppm-auth-wrap" style="--brand: <?php echo esc_attr( $ui_brand ); ?>;" tabindex="0">
        <?php if ( ! $is_logged_in ) : ?>
            
            <a href="<?php echo esc_url( $login_url ); ?>" title="Log In" class="cppm-auth-icon-btn">
                <?php echo $icon_user; ?>
            </a>
            
        <?php else : ?>
            
            <div class="cppm-auth-icon-btn">
                <?php echo $icon_user; ?>
            </div>
            
            <div class="cppm-auth-dropdown">
                <a href="<?php echo esc_url( $profile_url ); ?>">
                    <span style="font-size: 16px;">👤</span> Profile
                </a>
                <a href="<?php echo esc_url( get_permalink(43) ); ?>">
                    <span style="font-size: 16px;">🎓</span> My Classroom
                </a>
                <div style="height: 1px; background: #e2e8f0; margin: 4px 0;"></div>
                <a href="<?php echo esc_url( $logout_url ); ?>" class="cppm-logout-link">
                    <span style="font-size: 16px;">🚪</span> Log Out
                </a>
            </div>
            
        <?php endif; ?>
    </div>
    <?php
    
    return ob_get_clean();
}