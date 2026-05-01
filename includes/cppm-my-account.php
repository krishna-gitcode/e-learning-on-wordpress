<?php
/**
 * Core: My Account & Authentication Engine
 * Architecture: Modular / Asset Separated
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. AUTO-REDIRECT ROOT TO "MY ORDERS"
// ==========================================
add_action('template_redirect', 'cppm_redirect_my_account_dashboard');
function cppm_redirect_my_account_dashboard() {
    if ( is_account_page() && is_user_logged_in() && ! is_wc_endpoint_url() ) {
        if ( ! wp_is_mobile() ) {
            wp_safe_redirect( wc_get_endpoint_url( 'orders' ) );
            exit;
        }
    }
}

// ==========================================
// 2. ENDPOINTS & MENU ARRAY
// ==========================================
add_filter( 'woocommerce_get_query_vars', 'cppm_add_admin_endpoints' );
function cppm_add_admin_endpoints( $vars ) {
    $vars['track-orders']   = 'track-orders';
    $vars['seller-dash']    = 'seller-dash';
    $vars['create-product'] = 'create-product';
    $vars['admin-orders']   = 'admin-orders';
    $vars['admin-sales']    = 'admin-sales';
    $vars['admin-reviews']  = 'admin-reviews';
    return $vars;
}

add_filter( 'woocommerce_account_menu_items', 'cppm_flipkart_account_menu' );
function cppm_flipkart_account_menu( $menu_links ) {
    $new_menu = array();
    $new_menu['orders']               = 'MY ORDERS';
    $new_menu['account_settings_hdr'] = 'ACCOUNT SETTINGS';
    $new_menu['edit-account']         = 'Profile Information'; 
    $new_menu['edit-address']         = 'Manage Addresses';
    $new_menu['change-password']      = 'Change Password'; 

    if ( current_user_can('administrator') || current_user_can('seller') || current_user_can('author') ) {
        $new_menu['instructor_hdr'] = 'INSTRUCTOR PORTAL'; 
        $new_menu['seller-dash']    = 'My Sales & Analytics';
        $new_menu['create-product'] = 'Create New Course/Product';
    }

    if ( current_user_can('administrator') ) {
        $new_menu['admin_hdr']     = 'PLATFORM ADMIN'; 
        $new_menu['admin-orders']  = 'Global Order Management';
        $new_menu['admin-sales']   = 'Master Sales Reports';
        $new_menu['admin-reviews'] = 'Moderate All Reviews';
    }

    $new_menu['legal_hdr']       = 'HELP & LEGAL';
    $new_menu['help-center']     = 'Help Center';
    $new_menu['terms']           = 'Terms & Conditions';
    $new_menu['privacy']         = 'Privacy Policy';
    $new_menu['refunds']         = 'Refund Policy';
    $new_menu['customer-logout'] = 'Logout';

    return $new_menu;
}

add_filter( 'woocommerce_get_endpoint_url', 'cppm_custom_account_urls', 10, 4 );
function cppm_custom_account_urls( $url, $endpoint, $value, $permalink ) {
    if ( $endpoint === 'help-center' ) return site_url('/contact-us/');
    if ( $endpoint === 'terms' ) return site_url('/terms-conditions/');
    if ( $endpoint === 'privacy' ) return site_url('/privacy-policy/');
    if ( $endpoint === 'refunds' ) return site_url('/refund-policy/');
    if ( $endpoint === 'change-password' ) return '#change-password'; 
    if ( strpos($endpoint, '_hdr') !== false ) return '#'; 
    return $url;
}

// ==========================================
// 3. FRONTEND DASHBOARD VIEWS
// ==========================================
add_action( 'woocommerce_account_create-product_endpoint', 'cppm_frontend_create_product' );
function cppm_frontend_create_product() {
    if ( ! (current_user_can('administrator') || current_user_can('seller')) ) return;
    echo '<div class="cppm-builder-placeholder" style="text-align:center; padding: 60px 20px; background:#f8fafc; border-radius:12px; border: 1px dashed #cbd5e1;">
            <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#2874f0" stroke-width="1.5" style="margin-bottom:20px; opacity:0.8;"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            <h3 style="font-size:24px; font-weight:800; color:#0f172a; margin-bottom:12px;">Advanced Course Builder</h3>
            <p style="color:#64748b; font-size:15px; margin-bottom:30px;">Creating premium courses requires multiple modules and media uploads. We have moved this to a dedicated full-screen builder.</p>
            <a href="#" class="cppm-btn-primary" style="display:inline-block; text-decoration:none;">Launch Product Builder &rarr;</a>
          </div>';
}

add_action( 'woocommerce_account_admin-reviews_endpoint', 'cppm_frontend_admin_reviews' );
function cppm_frontend_admin_reviews() {
    if ( ! current_user_can('administrator') ) return;
    
    // Process Actions
    if ( isset($_POST['cppm_mod_action']) && isset($_POST['comment_id']) ) {
        $cid = intval($_POST['comment_id']);
        $action = sanitize_text_field($_POST['cppm_mod_action']);
        if ( $action === 'approve' ) { wp_set_comment_status($cid, 'approve'); echo '<div class="cppm-notice cppm-notice-success">Comment Approved.</div>'; }
        if ( $action === 'unapprove' ) { wp_set_comment_status($cid, 'hold'); echo '<div class="cppm-notice cppm-notice-warning">Comment placed on Hold.</div>'; }
        if ( $action === 'trash' ) { wp_trash_comment($cid); echo '<div class="cppm-notice cppm-notice-danger">Comment moved to Trash.</div>'; }
        if ( $action === 'edit' && isset($_POST['comment_content']) ) {
            wp_update_comment(array('comment_ID' => $cid, 'comment_content' => sanitize_textarea_field($_POST['comment_content'])));
            echo '<div class="cppm-notice cppm-notice-success">Comment Updated.</div>';
        }
    }

    echo '<h3 class="cppm-page-title">Moderate All Site Comments</h3>';
    global $wpdb;
    $comments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}comments WHERE comment_approved != 'trash' AND comment_type != 'order_note' ORDER BY comment_date DESC LIMIT 50");
    
    echo '<div class="cppm-table-wrap"><table class="cppm-admin-table"><thead><tr><th>User & Location</th><th>Comment</th><th>Actions</th></tr></thead><tbody>';
    if($comments) {
        foreach($comments as $c) {
            $post_type = get_post_type($c->comment_post_ID);
            echo '<tr>';
            echo '<td><strong>' . esc_html($c->comment_author) . '</strong><br><a href="'.get_permalink($c->comment_post_ID).'" target="_blank">' . get_the_title($c->comment_post_ID) . '</a></td>';
            echo '<td><div id="cdisp-'.$c->comment_ID.'">' . esc_html($c->comment_content) . '</div></td>';
            echo '<td>
                    <form method="POST" style="display:flex; gap:6px;">
                        <input type="hidden" name="comment_id" value="'.$c->comment_ID.'">
                        <button type="submit" name="cppm_mod_action" value="trash" class="cppm-btn-sm" style="background:#dc2626; color:#fff;">Trash</button>
                    </form>
                  </td>';
            echo '</tr>';
        }
    } else { echo '<tr><td colspan="3">No comments found.</td></tr>'; }
    echo '</tbody></table></div>';
}

add_action( 'woocommerce_account_admin-sales_endpoint', 'cppm_frontend_admin_sales' );
function cppm_frontend_admin_sales() {
    if ( ! current_user_can('administrator') ) return;
    echo '<h3 class="cppm-page-title">Master Sales Analytics</h3>';
    
    $cached_html = get_transient( 'cppm_admin_sales_report_html' );
    if ( false !== $cached_html ) { echo $cached_html; return; }

    $orders = wc_get_orders(array('limit' => 100, 'status' => array('completed', 'processing')));
    $total_earned = 0;
    foreach($orders as $order) { $total_earned += $order->get_total(); }

    ob_start();
    echo '<div class="cppm-stat-cards"><div class="cppm-stat-card"><div class="cppm-stat-title">Total Revenue (Last 100)</div><div class="cppm-stat-val">₹'.number_format($total_earned, 2).'</div></div></div>';
    $final_html = ob_get_clean();
    
    set_transient( 'cppm_admin_sales_report_html', $final_html, 3600 );
    echo $final_html;
}

add_action( 'woocommerce_account_admin-orders_endpoint', 'cppm_frontend_admin_orders' );
function cppm_frontend_admin_orders() {
    if ( ! current_user_can('administrator') ) return;
    echo '<h3 class="cppm-page-title">Global Orders</h3><p>Use WooCommerce backend for full order management.</p>';
}

add_action( 'woocommerce_account_seller-dash_endpoint', 'cppm_frontend_seller_dashboard' );
function cppm_frontend_seller_dashboard() {
    echo '<h3 class="cppm-page-title">My Sales & Analytics</h3>';
    cppm_frontend_admin_sales(); 
}

// ==========================================
// 4. LAYOUT WRAPPERS & MODALS
// ==========================================
add_action('woocommerce_before_account_navigation', 'cppm_open_sidebar_wrapper', 1);
function cppm_open_sidebar_wrapper() {
    echo '<div class="cppm-sidebar-wrapper">';
    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();
        echo '<div class="cppm-user-card"><div class="cppm-user-avatar">' . get_avatar($user->ID, 50) . '</div>
              <div class="cppm-user-info"><span class="cppm-hello">Hello,</span><span class="cppm-name">' . esc_html($user->display_name) . '</span></div></div>';
    }
}

add_action('woocommerce_after_account_navigation', 'cppm_close_sidebar_wrapper', 99);
function cppm_close_sidebar_wrapper() { echo '</div>'; }

add_action('wp_footer', 'cppm_password_change_modal');
function cppm_password_change_modal() {
    if ( ! is_account_page() || ! is_user_logged_in() ) return;
    echo '<div class="cppm-modal-overlay" id="cppm-pwd-modal"><div class="cppm-modal-box">
            <div class="cppm-modal-header"><h3>Change Password</h3><span class="cppm-close-modal" id="cppm-close-pwd">&times;</span></div>
            <div class="cppm-modal-body"><p class="cppm-pwd-notice" style="display:none;"></p>
                <div class="cppm-form-row"><label>Current Password</label><input type="password" id="cppm_old_pwd"></div>
                <div class="cppm-form-row"><label>New Password</label><input type="password" id="cppm_new_pwd"></div>
                <button id="cppm_submit_pwd" class="cppm-btn-primary" style="width:100%;">Update Password</button>
            </div></div></div>';
}

add_action('wp_ajax_cppm_change_password_ajax', 'cppm_change_password_ajax');
function cppm_change_password_ajax() {
    $user = wp_get_current_user();
    if ( ! wp_check_password( $_POST['old_pwd'], $user->user_pass, $user->ID ) ) { wp_send_json_error( array('message' => 'Current password incorrect.') ); }
    wp_set_password( $_POST['new_pwd'], $user->ID );
    wp_send_json_success( array('message' => 'Success. Please log in again.') );
}

// ==========================================
// 5. ASSET ENQUEUE ENGINE
// ==========================================
add_action( 'wp_enqueue_scripts', 'cppm_enqueue_account_assets', 999 );
function cppm_enqueue_account_assets() {
    if ( is_account_page() ) {
        $plugin_url = plugin_dir_url( dirname( __FILE__ ) );

        if ( ! is_user_logged_in() ) {
            // Load Auth Assets
            wp_enqueue_style( 'cppm-auth-style', $plugin_url . 'assets/css/frontend-auth.css', array(), '1.0.0' );
            wp_enqueue_script( 'cppm-auth-script', $plugin_url . 'assets/js/frontend-auth.js', array(), '1.0.0', true );

            $custom_logo_id = get_theme_mod( 'custom_logo' );
            $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
            if ( ! $logo_url ) { $logo_url = get_option('cppm_login_logo', 'https://sarkarimusician.store/wp-content/uploads/2026/04/logo-1.png');) }

            wp_localize_script( 'cppm-auth-script', 'cppmAuthData', array(
                'logoUrl' => esc_url( $logo_url ),
                'homeUrl' => esc_url( home_url() )
            ));
        } else {
            // Load Dashboard Assets
            wp_enqueue_style( 'cppm-account-style', $plugin_url . 'assets/css/my-account.css', array(), '1.0.0' );
            wp_enqueue_script( 'cppm-account-script', $plugin_url . 'assets/js/my-account.js', array(), '1.0.0', true );

            wp_localize_script( 'cppm-account-script', 'cppmAccountData', array(
                'homeUrl'   => esc_url( home_url() ),
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'logoutUrl' => wp_logout_url( home_url() )
            ));
        }
    }
}