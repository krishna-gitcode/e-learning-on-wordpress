<?php
/**
 * Core: Enrolled Courses Grid & Dynamic Auth Buttons
 * Architecture: Modular / Asset Separated
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. ENROLLED COURSES SHORTCODE
// ==========================================
add_shortcode('my_enrolled_courses', 'cppm_enrolled_courses_shortcode');
function cppm_enrolled_courses_shortcode($atts) {
    
    // 1. Auth Check
    if (!is_user_logged_in()) {
        return '<div style="padding:40px; text-align:center; background:#fef2f2; border-radius:16px; border:1px solid #f87171;"><h3 style="color:#dc2626; margin-top:0;">Authentication Required</h3><p style="color:#991b1b;">Please log in to view your enrolled courses.</p></div>';
    }

    // 2. Dependency Check
    if (!function_exists('wc_get_orders')) {
        return '<p>WooCommerce is required for this feature.</p>';
    }

    $current_user_id = get_current_user_id();

    // 3. Fetch completed orders
    $orders = wc_get_orders(array(
        'customer_id' => $current_user_id,
        'status'      => array('wc-completed'),
        'limit'       => -1,
    ));

    // 4. Extract unique Product IDs
    $enrolled_product_ids = array();
    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $enrolled_product_ids[] = $item->get_product_id();
        }
    }
    $enrolled_product_ids = array_unique($enrolled_product_ids);

    // 5. Empty State
    if (empty($enrolled_product_ids)) {
        return '<div style="padding:50px; text-align:center; background:#f8fafc; border-radius:16px; border:1px solid #e2e8f0;"><h3 style="color:#1e293b; margin-top:0;">No Enrollments Found</h3><p style="color:#64748b;">You have not enrolled in any courses yet. Visit the shop to get started!</p></div>';
    }

    // 6. ENQUEUE CSS ONLY WHEN SHORTCODE RUNS
    $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
    wp_enqueue_style( 'cppm-enrolled-courses-css', $plugin_url . 'assets/css/enrolled-courses.css', array(), '1.0.0' );

    // 7. Output Grid
    ob_start();
    ?>
    <div class="cppm-enrolled-grid">
        <?php
        foreach ($enrolled_product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;

            $course_page_id = get_post_meta($product_id, '_course_page_id', true);
            $course_link = !empty($course_page_id) ? get_permalink($course_page_id) : get_permalink($product_id);
            
            $thumb_url = get_the_post_thumbnail_url($product_id, 'large');
            if (!$thumb_url) { $thumb_url = 'https://via.placeholder.com/600x400?text=Course+Ready'; }
            ?>
            
            <a href="<?php echo esc_url($course_link); ?>" class="cppm-enrolled-card">
                <div class="cppm-enrolled-img-wrap">
                    <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($product->get_name()); ?>">
                    <div class="cppm-enrolled-badge">Enrolled ✓</div>
                </div>
                <div class="cppm-enrolled-content">
                    <h3 class="cppm-enrolled-title"><?php echo esc_html($product->get_name()); ?></h3>
                    <div class="cppm-enrolled-btn">
                        Open Classroom
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </div>
                </div>
            </a>
            
            <?php
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}

// ==========================================
// 2. DYNAMIC LOGIN/LOGOUT BUTTON SHORTCODE
// ==========================================
add_shortcode('auth_button', 'sarkari_musician_debug_btn');
function sarkari_musician_debug_btn() {
    if ( ! class_exists( 'WooCommerce' ) ) return '';

    if ( is_user_logged_in() ) {
        $logout_url = wc_get_endpoint_url( 'customer-logout', '', get_permalink( get_option('woocommerce_myaccount_page_id') ) );
        $final_logout = wp_nonce_url( $logout_url, 'customer-logout' );
        return '<a href="' . esc_url($final_logout) . '" class="nav-auth-btn">Logout</a>';
    } else {
        $hardcoded_login = home_url('/my-account/'); 
        return '<a href="' . esc_url($hardcoded_login) . '" class="nav-auth-btn">Login</a>';
    }
}

// ==========================================
// 3. FAST CUSTOM LOGOUT REDIRECT
// ==========================================
add_action('template_redirect', 'sarkari_musician_redirect_and_logout');
function sarkari_musician_redirect_and_logout() {
    global $wp;
    if (isset($wp->query_vars['customer-logout'])) {
        wp_logout(); // Kills the session immediately
        wp_safe_redirect(home_url()); // Send back to homepage
        exit;
    }
}