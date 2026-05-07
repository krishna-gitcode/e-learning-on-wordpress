<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function cppm_enrolled_courses_shortcode($atts) {
    // 1. Check if user is logged in
    if (!is_user_logged_in()) {
        return '<div style="padding:40px; text-align:center; background:#fef2f2; border-radius:16px; border:1px solid #f87171;"><h3 style="color:#dc2626; margin-top:0; font-family:inherit;">Authentication Required</h3><p style="color:#991b1b; font-family:inherit;">Please log in to view your enrolled courses.</p></div>';
    }

    // 2. Check if WooCommerce is active
    if (!function_exists('wc_get_orders')) {
        return '<p>WooCommerce is required for this feature.</p>';
    }

    $current_user_id = get_current_user_id();

    // 3. Fetch all completed orders for the current user
    $orders = wc_get_orders(array(
        'customer_id' => $current_user_id,
        'status'      => array('wc-completed'),
        'limit'       => -1,
    ));

    // 4. Extract unique Product IDs from those orders
    $enrolled_product_ids = array();
    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $enrolled_product_ids[] = $item->get_product_id();
        }
    }
    $enrolled_product_ids = array_unique($enrolled_product_ids);

    // 5. If no courses found, show a friendly message
    if (empty($enrolled_product_ids)) {
        return '<div style="padding:50px; text-align:center; background:#f8fafc; border-radius:16px; border:1px solid #e2e8f0;"><h3 style="color:#1e293b; margin-top:0; font-family:inherit;">No Enrollments Found</h3><p style="color:#64748b; font-family:inherit;">You have not enrolled in any courses yet. Visit the shop to get started!</p></div>';
    }

    // 6. Output the beautiful Grid
    ob_start();
    ?>
    <style>
        .cppm-enrolled-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)) !important;
            gap: 30px !important;
            width: 100% !important;
            padding: 20px 0 !important;
            margin: 0 !important;
            box-sizing: border-box !important;
        }
        .cppm-enrolled-card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #f3f4f6;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none !important;
            color: inherit !important;
            height: 100%;
        }
        .cppm-enrolled-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.1);
            border-color: #cbd5e1;
        }
        .cppm-enrolled-img-wrap {
            position: relative;
            width: 100%;
            padding-top: 60%; /* Aspect Ratio */
            background: #f8fafc;
        }
        .cppm-enrolled-img-wrap img {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            object-fit: cover;
        }
        .cppm-enrolled-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #10b981; /* Success Green */
            color: #ffffff;
            font-weight: 700;
            font-size: 11px;
            padding: 6px 14px;
            border-radius: 20px;
            box-shadow: 0 4px 10px rgba(16,185,129,0.3);
            z-index: 2;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .cppm-enrolled-content {
            padding: 25px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }
        .cppm-enrolled-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.3;
            margin: 0 0 20px 0;
            font-family: 'Jost', 'Poppins', sans-serif;
        }
        .cppm-enrolled-btn {
            margin-top: auto;
            background: #f1f5f9;
            color: #2563eb;
            text-align: center;
            padding: 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.2s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        .cppm-enrolled-card:hover .cppm-enrolled-btn {
            background: #2563eb;
            color: #ffffff;
        }
    </style>

    <div class="cppm-enrolled-grid">
        <?php
        foreach ($enrolled_product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;

            // Determine where the link should go. 
            // It checks if you linked a specific Classroom Page in the Woo settings, otherwise falls back to the product.
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
add_shortcode('my_enrolled_courses', 'cppm_enrolled_courses_shortcode');


add_shortcode('auth_button', 'sarkari_musician_debug_btn');
function sarkari_musician_debug_btn() {
    // 1. Force WooCommerce to be recognized
    if ( ! class_exists( 'WooCommerce' ) ) return '';

    if ( is_user_logged_in() ) {
        // This part is confirmed working for you
        $logout_url = wc_get_endpoint_url( 'customer-logout', '', get_permalink( get_option('woocommerce_myaccount_page_id') ) );
        $final_logout = wp_nonce_url( $logout_url, 'customer-logout' );
        return '<a href="' . esc_url($final_logout) . '" class="nav-auth-btn">Logout</a>';
    } else {
        /**
         * 2. THE LOGIN FIX
         * We are using a hardcoded relative path here. 
         * If your My Account page is at yourdomain.com/my-account/, 
         * this link will find it even if the WooCommerce settings are broken.
         */
        $hardcoded_login = home_url('/my-account/'); 
        
        return '<a href="' . esc_url($hardcoded_login) . '" class="nav-auth-btn">Login</a>';
    }
}
add_action('template_redirect', 'sarkari_musician_redirect_and_logout');
function sarkari_musician_redirect_and_logout() {
    global $wp;
    if (isset($wp->query_vars['customer-logout'])) {
        wp_logout(); // This kills the session immediately
        wp_safe_redirect(home_url()); // Send them back to the homepage
        exit;
    }
}