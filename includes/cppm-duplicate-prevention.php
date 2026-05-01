<?php
/**
 * Core: Duplicate Purchase Prevention Engine
 * Architecture: Modular / Asset Separated
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. ENQUEUE PRODUCT ALERTS CSS
// ==========================================
add_action( 'wp_enqueue_scripts', 'cppm_enqueue_product_alerts_css', 999 );
function cppm_enqueue_product_alerts_css() {
    // Only load this tiny CSS file on Single Product pages
    if ( function_exists('is_product') && is_product() ) {
        $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
        wp_enqueue_style( 'cppm-product-alerts', $plugin_url . 'assets/css/product-alerts.css', array(), '1.0.0' );
    }
}

// ==========================================
// 2. DISABLE "ADD TO CART" FOR OWNED COURSES
// ==========================================
add_filter( 'woocommerce_is_purchasable', 'cppm_prevent_course_repurchase', 10, 2 );
function cppm_prevent_course_repurchase( $purchasable, $product ) {
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        
        // Check if the current user has already bought this specific product
        if ( function_exists('wc_customer_bought_product') && wc_customer_bought_product( $current_user->user_email, $current_user->ID, $product->get_id() ) ) {
            return false; // Hides the Add to Cart button
        }
    }
    return $purchasable;
}

// ==========================================
// 3. SHOW "ALREADY ENROLLED" BANNER (CLEAN HTML)
// ==========================================
add_action( 'woocommerce_single_product_summary', 'cppm_show_already_enrolled_banner', 31 );
function cppm_show_already_enrolled_banner() {
    global $product;
    
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        
        if ( function_exists('wc_customer_bought_product') && wc_customer_bought_product( $current_user->user_email, $current_user->ID, $product->get_id() ) ) {
            
            // Get the dynamic WooCommerce My Account URL
            $classroom_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : site_url('/my-account/');
            
            // Render the clean HTML (CSS handles the styling externally)
            ?>
            <div class="cppm-enrolled-banner">
                <h4 class="cppm-enrolled-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    You already own this course
                </h4>
                <p class="cppm-enrolled-text">You have full access to the video modules and learning materials for this product.</p>
                <a href="<?php echo esc_url( $classroom_url ); ?>" class="cppm-enrolled-btn">Go to My Classroom &rarr;</a>
            </div>
            <?php
        }
    }
}

// ==========================================
// 4. CATCH DUPLICATE EMAILS AT CHECKOUT 
// ==========================================
add_action( 'woocommerce_after_checkout_validation', 'cppm_prevent_duplicate_email_checkout', 10, 2 );
function cppm_prevent_duplicate_email_checkout( $data, $errors ) {
    $billing_email = isset( $data['billing_email'] ) ? sanitize_email($data['billing_email']) : '';
    
    if ( ! empty( $billing_email ) && function_exists('wc_customer_bought_product') ) {
        // Look up the user by the email they just typed
        $user = get_user_by( 'email', $billing_email );
        $user_id = $user ? $user->ID : 0;
        
        // Loop through everything in their cart
        foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
            $product_id = $values['product_id'];
            
            // If they already bought it, throw a hard error and stop the checkout
            if ( wc_customer_bought_product( $billing_email, $user_id, $product_id ) ) {
                $product = wc_get_product( $product_id );
                $errors->add( 
                    'duplicate_purchase', 
                    sprintf( '<strong>Error:</strong> You already own the course "%s". Please remove it from your cart to proceed.', esc_html( $product->get_name() ) )
                );
            }
        }
    }
}