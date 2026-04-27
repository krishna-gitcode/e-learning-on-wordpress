<?php
/**
 * Phase 3: Flipkart-Style My Account Page (HPOS Analytics, Auto-Redirect, Premium UI)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. AUTO-REDIRECT ROOT TO "MY ORDERS"
// ==========================================
add_action('template_redirect', 'cppm_redirect_my_account_dashboard');
function cppm_redirect_my_account_dashboard() {
    // If user is on the base /my-account/ page (no endpoints) and on a desktop screen
    if ( is_account_page() && is_user_logged_in() && ! is_wc_endpoint_url() ) {
        // We only redirect if it's not an AJAX request and not mobile (handled by JS)
        if ( ! wp_is_mobile() ) {
            wp_safe_redirect( wc_get_endpoint_url( 'orders' ) );
            exit;
        }
    }
}

// ==========================================
// 2. REGISTER NEW CUSTOM ENDPOINTS
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

// ==========================================
// 3. BUILD THE MENU ARRAY (FLIPKART GROUPING)
// ==========================================
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
// 4. FUNCTIONAL FRONTEND DASHBOARDS
// ==========================================

// 4.1 Dedicated Product Builder Shell
add_action( 'woocommerce_account_create-product_endpoint', 'cppm_frontend_create_product' );
function cppm_frontend_create_product() {
    if ( ! (current_user_can('administrator') || current_user_can('seller') || current_user_can('author')) ) return;
    
    echo '<div class="cppm-builder-placeholder" style="text-align:center; padding: 60px 20px; background:#f8fafc; border-radius:12px; border: 1px dashed #cbd5e1;">
            <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#2874f0" stroke-width="1.5" style="margin-bottom:20px; opacity:0.8;"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            <h3 style="font-size:24px; font-weight:800; color:#0f172a; margin-bottom:12px;">Advanced Course Builder</h3>
            <p style="color:#64748b; font-size:15px; margin-bottom:30px; max-width:450px; margin-left:auto; margin-right:auto; line-height:1.6;">Creating premium courses and managing physical inventory requires multiple modules, pricing tiers, and media uploads. We have moved this to a dedicated full-screen builder.</p>
            <a href="#" class="cppm-btn-primary" style="display:inline-block; text-decoration:none; padding:14px 30px; font-size:16px;">Launch Product Builder &rarr;</a>
          </div>';
}

// 4.2 Universal Moderation Engine (Direct wp_comments SQL Query)
add_action( 'woocommerce_account_admin-reviews_endpoint', 'cppm_frontend_admin_reviews' );
function cppm_frontend_admin_reviews() {
    if ( ! current_user_can('administrator') ) return;

    // Process Actions (Approve, Hold, Trash, Edit)
    if ( isset($_POST['cppm_mod_action']) && isset($_POST['comment_id']) ) {
        $cid = intval($_POST['comment_id']);
        $action = sanitize_text_field($_POST['cppm_mod_action']);
        
        if ( $action === 'approve' ) { wp_set_comment_status($cid, 'approve'); echo '<div class="cppm-notice cppm-notice-success">Comment Approved.</div>'; }
        if ( $action === 'unapprove' ) { wp_set_comment_status($cid, 'hold'); echo '<div class="cppm-notice cppm-notice-warning">Comment placed on Hold.</div>'; }
        if ( $action === 'trash' ) { wp_trash_comment($cid); echo '<div class="cppm-notice cppm-notice-danger">Comment moved to Trash.</div>'; }
        if ( $action === 'edit' && isset($_POST['comment_content']) ) {
            wp_update_comment(array('comment_ID' => $cid, 'comment_content' => sanitize_textarea_field($_POST['comment_content'])));
            echo '<div class="cppm-notice cppm-notice-success">Comment Text Updated.</div>';
        }
    }

    echo '<h3 class="cppm-page-title">Moderate All Site Comments</h3>';
    
    // THE FIX: Bypass WooCommerce filters and query the wp_comments table directly
    global $wpdb;
    $comments = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}comments 
        WHERE comment_approved != 'trash' 
        AND comment_type != 'order_note' 
        ORDER BY comment_date DESC 
        LIMIT 50
    ");
    
    echo '<div class="cppm-table-wrap"><table class="cppm-admin-table"><thead><tr><th>User & Location</th><th>Comment / Review</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    
    if($comments) {
        foreach($comments as $c) {
            $rating = get_comment_meta($c->comment_ID, 'rating', true);
            $status_badge = $c->comment_approved === '1' ? '<span class="cppm-badge cppm-badge-success">Approved</span>' : '<span class="cppm-badge cppm-badge-warning">Pending</span>';
            $rating_text = $rating ? '<br><span style="color:#f59e0b; font-weight:bold; font-size:12px;">'.$rating.' ★ Rating</span>' : '';
            $post_type = get_post_type($c->comment_post_ID);
            $type_label = $post_type ? $post_type : 'Unknown';
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($c->comment_author) . '</strong><br><span style="font-size:11px; color:#64748b; text-transform:uppercase;">In '.esc_html($type_label).'</span><br><a href="'.get_permalink($c->comment_post_ID).'" target="_blank" style="font-size:12px; color:#2874f0; text-decoration:none; font-weight:600;">' . get_the_title($c->comment_post_ID) . '</a>'.$rating_text.'</td>';
            echo '<td>
                    <div id="comment-disp-'.$c->comment_ID.'">' . esc_html($c->comment_content) . '</div>
                    <form method="POST" id="comment-edit-'.$c->comment_ID.'" style="display:none; margin-top:10px;">
                        <input type="hidden" name="comment_id" value="'.$c->comment_ID.'">
                        <input type="hidden" name="cppm_mod_action" value="edit">
                        <textarea name="comment_content" style="width:100%; border:1px solid #cbd5e1; border-radius:4px; padding:8px; font-family:inherit; margin-bottom:8px;" rows="3">'.esc_html($c->comment_content).'</textarea><br>
                        <button type="submit" class="cppm-btn-sm cppm-btn-success">Save Edit</button>
                        <button type="button" class="cppm-btn-sm" onclick="document.getElementById(\'comment-edit-'.$c->comment_ID.'\').style.display=\'none\'; document.getElementById(\'comment-disp-'.$c->comment_ID.'\').style.display=\'block\';">Cancel</button>
                    </form>
                  </td>';
            echo '<td>' . $status_badge . '</td>';
            echo '<td>
                    <form method="POST" style="display:flex; gap:6px; flex-wrap:wrap;">
                        <input type="hidden" name="comment_id" value="'.$c->comment_ID.'">
                        '.($c->comment_approved === '1' ? '<button type="submit" name="cppm_mod_action" value="unapprove" class="cppm-btn-sm cppm-btn-warning">Hold</button>' : '<button type="submit" name="cppm_mod_action" value="approve" class="cppm-btn-sm cppm-btn-success">Approve</button>').'
                        <button type="button" class="cppm-btn-sm" onclick="document.getElementById(\'comment-disp-'.$c->comment_ID.'\').style.display=\'none\'; document.getElementById(\'comment-edit-'.$c->comment_ID.'\').style.display=\'block\';">Edit</button>
                        <button type="submit" name="cppm_mod_action" value="trash" class="cppm-btn-sm cppm-btn-danger" onclick="return confirm(\'Are you sure you want to delete this comment?\');">Trash</button>
                    </form>
                  </td>';
            echo '</tr>';
        }
    } else { echo '<tr><td colspan="4" style="text-align:center; padding:30px;">No comments found.</td></tr>'; }
    echo '</tbody></table></div>';
}

// 4.3 Master Sales Analytics (Native WC API - HPOS Compatible)
add_action( 'woocommerce_account_admin-sales_endpoint', 'cppm_frontend_admin_sales' );
function cppm_frontend_admin_sales() {
    if ( ! current_user_can('administrator') ) return;
    echo '<h3 class="cppm-page-title">Master Sales & Financial Analytics</h3>';
    
    // Use native WooCommerce API so it works perfectly with modern HPOS databases
    $orders = wc_get_orders(array(
        'limit' => 500, // Fetch up to 500 recent orders
        'status' => array('completed', 'processing', 'on-hold')
    ));

    $stats = array(
        'completed'  => array('count' => 0, 'worth' => 0),
        'processing' => array('count' => 0, 'worth' => 0),
        'on-hold'    => array('count' => 0, 'worth' => 0)
    );
    
    $total_earned = 0;
    $product_tally = array();

    // Process Orders via PHP API
    foreach($orders as $order) {
        $status = $order->get_status();
        $total = $order->get_total();
        
        if(isset($stats[$status])) {
            $stats[$status]['count']++;
            $stats[$status]['worth'] += $total;
        }

        if($status === 'completed' || $status === 'processing') {
            $total_earned += $total;
            foreach($order->get_items() as $item) {
                $name = $item->get_name();
                if(!isset($product_tally[$name])) {
                    $product_tally[$name] = array('count' => 0, 'earned' => 0, 'buyers' => array());
                }
                $product_tally[$name]['count'] += $item->get_quantity();
                $product_tally[$name]['earned'] += $item->get_total();
                $product_tally[$name]['buyers'][] = $order->get_billing_first_name();
            }
        }
    }

    // Sort Products by highest earnings
    uasort($product_tally, function($a, $b) {
        return $b['earned'] <=> $a['earned'];
    });

    // Render Summary Cards
    echo '<div class="cppm-stat-cards">
            <div class="cppm-stat-card"><div class="cppm-stat-title">Total Valid Revenue</div><div class="cppm-stat-val" style="color:#16a34a;">₹'.number_format($total_earned, 2).'</div></div>
            <div class="cppm-stat-card"><div class="cppm-stat-title">Completed Orders</div><div class="cppm-stat-val">'.$stats['completed']['count'].' <span style="font-size:12px; color:#64748b;">(₹'.number_format($stats['completed']['worth'], 2).')</span></div></div>
            <div class="cppm-stat-card"><div class="cppm-stat-title">Processing Orders</div><div class="cppm-stat-val">'.$stats['processing']['count'].' <span style="font-size:12px; color:#64748b;">(₹'.number_format($stats['processing']['worth'], 2).')</span></div></div>
            <div class="cppm-stat-card"><div class="cppm-stat-title">On Hold Orders</div><div class="cppm-stat-val" style="color:#ea580c;">'.$stats['on-hold']['count'].' <span style="font-size:12px; color:#64748b;">(₹'.number_format($stats['on-hold']['worth'], 2).')</span></div></div>
          </div>';

    echo '<h4 style="font-size: 16px; margin: 30px 0 15px 0;">Product Performance & Buyers</h4>';
    echo '<div class="cppm-table-wrap"><table class="cppm-admin-table"><thead><tr><th>Product Name</th><th>Units Sold</th><th>Total Revenue</th><th>Recent Buyers</th></tr></thead><tbody>';
    
    if(!empty($product_tally)) {
        $count = 0;
        foreach($product_tally as $name => $data) {
            if($count >= 20) break; // Limit to top 20
            $buyers_list = implode(', ', array_unique($data['buyers']));
            $buyers_trim = wp_trim_words($buyers_list, 8, ' & more...');
            echo '<tr>
                    <td><strong>' . esc_html($name) . '</strong></td>
                    <td><span class="cppm-badge cppm-badge-info">' . intval($data['count']) . ' Sold</span></td>
                    <td><strong>₹' . number_format(floatval($data['earned']), 2) . '</strong></td>
                    <td style="font-size:12px; color:#64748b;">' . esc_html($buyers_trim) . '</td>
                  </tr>';
            $count++;
        }
    } else { echo '<tr><td colspan="4" style="text-align:center; padding:30px;">No sales data yet.</td></tr>'; }
    echo '</tbody></table></div>';
}

// 4.4 Global Order Management
add_action( 'woocommerce_account_admin-orders_endpoint', 'cppm_frontend_admin_orders' );
function cppm_frontend_admin_orders() {
    if ( ! current_user_can('administrator') ) return;
    echo '<h3 class="cppm-page-title">Global Order Management</h3>';
    $orders = wc_get_orders( array( 'limit' => 20, 'orderby' => 'date', 'order' => 'DESC' ) );
    if ( empty($orders) ) { echo '<div class="cppm-notice cppm-notice-info">No orders found.</div>'; return; }
    
    echo '<div class="cppm-table-wrap"><table class="cppm-admin-table"><thead><tr><th>Order ID & Date</th><th>Customer Details</th><th>Current Status</th><th>Total Amount</th></tr></thead><tbody>';
    foreach ( $orders as $order ) {
        $status = $order->get_status();
        $badge_class = 'cppm-badge-info';
        if($status === 'completed') $badge_class = 'cppm-badge-success';
        if($status === 'on-hold') $badge_class = 'cppm-badge-warning';
        if($status === 'failed' || $status === 'cancelled') $badge_class = 'cppm-badge-danger';

        echo '<tr>
                <td><strong>#' . $order->get_id() . '</strong><br><span style="font-size:12px; color:#94a3b8;">' . wc_format_datetime( $order->get_date_created() ) . '</span></td>
                <td>' . esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) . '<br><a href="mailto:'.esc_attr($order->get_billing_email()).'" style="font-size:12px; color:#2874f0;">'.esc_html($order->get_billing_email()).'</a></td>
                <td><span class="cppm-badge ' . $badge_class . '">' . wc_get_order_status_name( $status ) . '</span></td>
                <td><strong>' . $order->get_formatted_order_total() . '</strong></td>
              </tr>';
    }
    echo '</tbody></table></div>';
}

// 4.5 Seller Dashboard Shell
add_action( 'woocommerce_account_seller-dash_endpoint', 'cppm_frontend_seller_dashboard' );
function cppm_frontend_seller_dashboard() {
    echo '<h3 class="cppm-page-title">My Sales & Analytics</h3><div class="cppm-notice cppm-notice-info">Your specific product sales will appear here.</div>';
    cppm_frontend_admin_sales(); 
}

// ==========================================
// 5. LAYOUT & SIDEBAR WRAPPER (FLIPKART STYLE)
// ==========================================

add_action('woocommerce_before_account_navigation', 'cppm_open_sidebar_wrapper', 1);
function cppm_open_sidebar_wrapper() {
    echo '<div class="cppm-sidebar-wrapper">';
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        $avatar = get_avatar( $current_user->ID, 50 );
        $first_name = $current_user->user_firstname ? $current_user->user_firstname : $current_user->display_name;
        
        echo '<div class="cppm-user-card">
                <div class="cppm-user-avatar">' . $avatar . '</div>
                <div class="cppm-user-info">
                    <span class="cppm-hello">Hello,</span>
                    <span class="cppm-name">' . esc_html($first_name) . '</span>
                </div>
              </div>';
    }
}

add_action('woocommerce_after_account_navigation', 'cppm_close_sidebar_wrapper', 99);
function cppm_close_sidebar_wrapper() {
    echo '</div>';
}

// ==========================================
// 6. CSS & JAVASCRIPT ENGINE
// ==========================================

add_action('wp_footer', 'cppm_password_change_modal');
function cppm_password_change_modal() {
    if ( ! is_account_page() || ! is_user_logged_in() ) return;
    ?>
    <div class="cppm-modal-overlay" id="cppm-pwd-modal">
        <div class="cppm-modal-box">
            <div class="cppm-modal-header"><h3>Change Password</h3><span class="cppm-close-modal" id="cppm-close-pwd">&times;</span></div>
            <div class="cppm-modal-body">
                <p class="cppm-pwd-notice"></p>
                <div class="cppm-form-row"><label>Current Password</label><input type="password" id="cppm_old_pwd"></div>
                <div class="cppm-form-row"><label>New Password</label><input type="password" id="cppm_new_pwd"></div>
                <button id="cppm_submit_pwd" class="cppm-btn-primary" style="width:100%;">Update Password</button>
            </div>
        </div>
    </div>
    <?php
}

add_action('wp_ajax_cppm_change_password_ajax', 'cppm_change_password_ajax');
function cppm_change_password_ajax() {
    $user = wp_get_current_user();
    if ( ! wp_check_password( $_POST['old_pwd'], $user->user_pass, $user->ID ) ) { wp_send_json_error( array('message' => 'Current password incorrect.') ); }
    wp_set_password( $_POST['new_pwd'], $user->ID );
    wp_send_json_success( array('message' => 'Success. Please log in again.') );
}

add_action('wp_head', 'cppm_my_account_styles_scripts', 9999);
function cppm_my_account_styles_scripts() {
    if ( ! is_account_page() ) return;
    ?>
    <style>
        /* Base Resets */
        body.woocommerce-account { background-color: #f1f3f6 !important; }
        .woocommerce-account .ast-archive-description, .woocommerce-account .entry-header { display: none !important; }
        
        /* Purge native password section from Profile tab entirely */
        .woocommerce-EditAccountForm fieldset:nth-of-type(2), 
        .woocommerce-EditAccountForm legend { display: none !important; }
        
        /* Premium WooCommerce Native Tables (Orders, Addresses) */
        table.woocommerce-orders-table, table.woocommerce-MyAccount-downloads { border: 1px solid #e2e8f0; border-radius: 8px; border-collapse: collapse; width: 100%; overflow: hidden; }
        table.woocommerce-orders-table th, table.woocommerce-MyAccount-downloads th { background: #f8fafc; padding: 15px; font-size: 12px; color: #475569; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        table.woocommerce-orders-table td, table.woocommerce-MyAccount-downloads td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #1e293b; vertical-align: middle; }
        .woocommerce-orders-table__cell-order-actions a.button { background: #2874f0; color: #fff; border-radius: 6px; padding: 8px 16px; font-weight: 600; text-decoration: none; display: inline-block; font-size: 13px; }
        .woocommerce-orders-table__cell-order-actions a.button:hover { background: #1e40af; }

        /* The Flipkart User Profile Box */
        .cppm-user-card { display: flex; align-items: center; gap: 15px; background: #ffffff; padding: 15px; border-radius: 4px; box-shadow: 0 1px 2px 0 rgba(0,0,0,.08); margin-bottom: 15px; }
        .cppm-user-avatar img { border-radius: 50%; width: 50px; height: 50px; display: block; }
        .cppm-hello { font-size: 12px; color: #212121; display: block; margin-bottom: 2px; }
        .cppm-name { font-size: 16px; font-weight: 600; color: #212121; display: block; }

        /* Desktop Layout */
        @media (min-width: 769px) {
            body.woocommerce-account .woocommerce { display: flex !important; flex-wrap: wrap !important; gap: 15px !important; align-items: flex-start !important; max-width: 1200px; margin: 0 auto; }
            .cppm-sidebar-wrapper { flex: 0 0 280px !important; width: 280px !important; position: sticky; top: 100px; }
            body.woocommerce-account .woocommerce-MyAccount-navigation { width: 100% !important; float: none !important; }
            body.woocommerce-account .woocommerce-MyAccount-content { flex: 1 !important; min-width: 0 !important; float: none !important; margin: 0 !important; background: #ffffff; padding: 35px; border-radius: 4px; box-shadow: 0 1px 2px 0 rgba(0,0,0,.08); }
        }

        /* Navigation Menu Structure */
        body.woocommerce-account .woocommerce-MyAccount-navigation ul { list-style: none; padding: 15px 0; margin: 0; display: flex; flex-direction: column; background: #ffffff; border-radius: 4px; box-shadow: 0 1px 2px 0 rgba(0,0,0,.08); }
        body.woocommerce-account .woocommerce-MyAccount-navigation ul li { margin: 0; }
        body.woocommerce-account .woocommerce-MyAccount-navigation ul li a { display: block; padding: 12px 20px 12px 55px; color: #212121; text-decoration: none; transition: 0.2s; font-size: 14px; line-height: 1.4; }
        body.woocommerce-account .woocommerce-MyAccount-navigation ul li a:hover { color: #2874f0; background: #f9f9f9; }
        body.woocommerce-account .woocommerce-MyAccount-navigation ul li.is-active a { color: #2874f0; background: #f4f8ff; font-weight: 600; }

        /* Navigation Headers */
        [class*="--account_settings_hdr"] a, [class*="--instructor_hdr"] a, [class*="--admin_hdr"] a, [class*="--legal_hdr"] a, .woocommerce-MyAccount-navigation-link--orders a, .woocommerce-MyAccount-navigation-link--customer-logout a { padding: 15px 20px !important; font-weight: 600 !important; color: #878787 !important; text-transform: uppercase; font-size: 14px !important; display: flex; align-items: center; gap: 15px; border-bottom: 1px solid #f0f0f0; margin-top: 5px; }
        [class*="_hdr"] a { pointer-events: none; }
        .woocommerce-MyAccount-navigation-link--orders a, .woocommerce-MyAccount-navigation-link--customer-logout a { pointer-events: auto; color: #212121 !important; cursor: pointer; }
        .woocommerce-MyAccount-navigation-link--orders a:hover { color: #2874f0 !important; }
        body.woocommerce-account .woocommerce-MyAccount-navigation ul li a svg { width: 20px; height: 20px; color: #2874f0; flex-shrink: 0; }

        /* Content UI & Forms */
        .cppm-page-title { font-size: 22px; font-weight: 700; color: #0f172a; margin-bottom: 25px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; }
        .cppm-form-row { margin-bottom: 15px; } .cppm-form-row label { font-weight: 600; font-size: 13px; color: #475569; display: block; margin-bottom: 8px; }
        .cppm-form-row input, .cppm-form-row textarea { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; color: #1e293b; font-family: inherit; font-size: 14px; }
        .cppm-form-row input:focus, .cppm-form-row textarea:focus { border-color: #2874f0; outline: none; box-shadow: 0 0 0 3px rgba(40,116,240,0.1); }
        .cppm-btn-primary { background: #2874f0; color: #fff; border: none; padding: 12px 24px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 14px; transition: 0.2s; }
        .cppm-btn-primary:hover { background: #1e40af; }
        .cppm-btn-sm { border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.2s; background: #e2e8f0; color: #334155; }
        .cppm-btn-success { background: #16a34a; color: #fff; } .cppm-btn-success:hover { background: #15803d; }
        .cppm-btn-warning { background: #f59e0b; color: #fff; } .cppm-btn-warning:hover { background: #d97706; }
        .cppm-btn-danger { background: #dc2626; color: #fff; } .cppm-btn-danger:hover { background: #b91c1c; }

        /* Master Analytics & Admin Tables */
        .cppm-stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .cppm-stat-card { background: #ffffff; padding: 25px; border-radius: 8px; border: 1px solid #e2e8f0; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .cppm-stat-title { font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; margin-bottom: 10px; }
        .cppm-stat-val { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1.2; }
        
        .cppm-table-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
        .cppm-admin-table { width: 100%; border-collapse: collapse; text-align: left; background: #ffffff; min-width: 600px; }
        .cppm-admin-table th { background: #f8fafc; padding: 15px; font-size: 12px; color: #475569; text-transform: uppercase; font-weight: 700; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .cppm-admin-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #1e293b; vertical-align: top; line-height: 1.5; }
        .cppm-admin-table tr:hover td { background: #f8fafc; }
        
        .cppm-badge { padding: 4px 10px; border-radius: 50px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; }
        .cppm-badge-info { background: #e0e7ff; color: #3730a3; } 
        .cppm-badge-success { background: #dcfce7; color: #166534; }
        .cppm-badge-warning { background: #ffedd5; color: #9a3412; }
        .cppm-badge-danger { background: #fee2e2; color: #991b1b; }
        .cppm-notice { padding: 12px 15px; border-radius: 6px; font-size: 14px; font-weight: 600; margin-bottom: 20px; }
        .cppm-notice-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .cppm-notice-warning { background: #ffedd5; color: #9a3412; border: 1px solid #fed7aa; }
        .cppm-notice-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .cppm-notice-info { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }

        /* Mobile UX */
        @media (max-width: 768px) {
            body.woocommerce-account { padding-bottom: 100px !important; } 
            body.cppm-is-mobile-root .woocommerce-MyAccount-content { display: none !important; }
            body.cppm-is-mobile-root .cppm-sidebar-wrapper { padding: 15px; }
            body.cppm-is-mobile-subpage .cppm-sidebar-wrapper { display: none !important; }
            body.cppm-is-mobile-subpage .woocommerce-MyAccount-content { padding: 20px; background: #ffffff; min-height: 100vh; }
            .cppm-mobile-back-btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 0; font-weight: 600; color: #2874f0; text-decoration: none; margin-bottom: 20px; }
        }

        /* Modal */
        .cppm-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 100000; align-items: center; justify-content: center; }
        .cppm-modal-overlay.open { display: flex; } .cppm-modal-box { background: #fff; width: 90%; max-width: 400px; border-radius: 8px; overflow: hidden; }
        .cppm-modal-header { padding: 20px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; } 
        .cppm-modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; }
        .cppm-modal-body { padding: 20px; }
        .cppm-pwd-notice { display: none; padding: 10px; border-radius: 4px; font-size: 13px; margin-bottom: 15px; }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.innerWidth <= 768) {
            var path = window.location.pathname.replace(/\/$/, "");
            if (path.endsWith('my-account')) { document.body.classList.add('cppm-is-mobile-root'); } 
            else { 
                document.body.classList.add('cppm-is-mobile-subpage');
                var contentArea = document.querySelector('.woocommerce-MyAccount-content');
                if(contentArea) { contentArea.insertAdjacentHTML('afterbegin', '<a href="' + window.location.origin + '/my-account/" class="cppm-mobile-back-btn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg> Back to Menu</a>'); }
            }
        }

        const icons = {
            'orders': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline></svg>',
            'account_settings_hdr': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
            'instructor_hdr': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
            'admin_hdr': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
            'legal_hdr': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
            'customer-logout': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>'
        };

        for (const [endpoint, svg] of Object.entries(icons)) {
            const linkItem = document.querySelector(`.woocommerce-MyAccount-navigation-link--${endpoint} a`);
            if (linkItem) { linkItem.innerHTML = svg + '<span>' + linkItem.innerHTML + '</span>'; }
        }
        
        const pwdLink = document.querySelector('.woocommerce-MyAccount-navigation-link--change-password a');
        if(pwdLink) pwdLink.setAttribute('id', 'cppm-trigger-pwd');

        const pwdTrigger = document.getElementById('cppm-trigger-pwd');
        const pwdModal = document.getElementById('cppm-pwd-modal');
        if(pwdTrigger && pwdModal) {
            pwdTrigger.addEventListener('click', (e) => { e.preventDefault(); pwdModal.classList.add('open'); });
            document.getElementById('cppm-close-pwd').addEventListener('click', () => { pwdModal.classList.remove('open'); });
            document.getElementById('cppm_submit_pwd').addEventListener('click', () => {
                const oldPwd = document.getElementById('cppm_old_pwd').value;
                const newPwd = document.getElementById('cppm_new_pwd').value;
                const notice = document.querySelector('.cppm-pwd-notice');
                if(!oldPwd || !newPwd) { notice.style.display='block'; notice.style.background='#fee2e2'; notice.style.color='#991b1b'; notice.innerText='Please fill both fields.'; return; }
                document.getElementById('cppm_submit_pwd').innerText = 'Updating...';
                fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=cppm_change_password_ajax&old_pwd=' + encodeURIComponent(oldPwd) + '&new_pwd=' + encodeURIComponent(newPwd)
                }).then(r => r.json()).then(res => {
                    notice.style.display='block';
                    if(res.success) { notice.style.background='#dcfce7'; notice.style.color='#166534'; notice.innerText=res.data.message; setTimeout(() => window.location.href='<?php echo wp_logout_url(home_url()); ?>', 2000);
                    } else { notice.style.background='#fee2e2'; notice.style.color='#991b1b'; notice.innerText=res.data.message; document.getElementById('cppm_submit_pwd').innerText = 'Update Password'; }
                });
            });
        }
    });
    </script>
    <?php
}