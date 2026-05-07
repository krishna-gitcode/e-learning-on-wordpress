<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================
// 1. DISABLE "ADD TO CART" FOR OWNED COURSES
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
// 2. SHOW "ALREADY ENROLLED" BANNER
// ==========================================
add_action( 'woocommerce_single_product_summary', 'cppm_show_already_enrolled_banner', 31 );
function cppm_show_already_enrolled_banner() {
    global $product;
    
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        
        if ( function_exists('wc_customer_bought_product') && wc_customer_bought_product( $current_user->user_email, $current_user->ID, $product->get_id() ) ) {
            
            // Output a premium-looking success banner
            echo '<div style="background:#ecfdf5; border:1px solid #10b981; padding:20px; border-radius:8px; margin: 20px 0;">';
            echo '<h4 style="color:#047857; margin:0 0 8px 0; font-size:18px;">✅ You are already enrolled!</h4>';
            echo '<p style="color:#065f46; margin:0; font-size:14px;">You have lifetime access to this course material.</p>';
            
            // THE FIX: Redirect directly to the "My Classroom" page (ID: 43)
            $dashboard_url = get_permalink( 43 ); 
            
            echo '<a href="' . esc_url( $dashboard_url ) . '" style="display:inline-block; margin-top:15px; background:#10b981; color:#fff; padding:10px 20px; border-radius:6px; text-decoration:none; font-weight:bold; transition:0.2s;">Go to My Classroom</a>';
            echo '</div>';
        }
    }
}

// ==========================================
// 3. CATCH DUPLICATE EMAILS AT CHECKOUT 
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
                $errors->add( 'validation', 'An account with the email <strong>' . esc_html($billing_email) . '</strong> has already purchased <em>' . $product->get_name() . '</em>. Please log in to access your course.' );
            }
        }
    }
}