<?php
/**
 * Core: Mock Test Membership & Access Engine
 * Architecture: WooCommerce Variation Integration & Ledger System
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. ADD CUSTOM FIELDS TO WOOCOMMERCE VARIATIONS
// ==========================================
// This allows you to set the attempts and link the Test ID on a per-variation basis (e.g. 3 attempts for $5, Unlimited for $20)
add_action( 'woocommerce_product_after_variable_attributes', 'cppm_mock_test_variation_fields', 10, 3 );
function cppm_mock_test_variation_fields( $loop, $variation_data, $variation ) {
    echo '<div class="options_group" style="background:#f8fafc; padding:15px; border:1px solid #cbd5e1; border-radius:8px; margin-top:10px;">';
    echo '<h4 style="margin-top:0; color:#0f172a;">Mock Test Membership Settings</h4>';

    // 1. Checkbox: Is this variation a Mock Test Pass?
    woocommerce_wp_checkbox( array(
        'id'            => '_cppm_is_mock_test[' . $loop . ']',
        'wrapper_class' => 'form-row form-row-full',
        'label'         => 'Is this a Mock Test Pass?',
        'description'   => 'Enable this to grant mock test attempts upon purchase.',
        'value'         => get_post_meta( $variation->ID, '_cppm_is_mock_test', true )
    ) );

    // 2. Input: Linked Mock Test Post ID
    woocommerce_wp_text_input( array(
        'id'            => '_cppm_mock_test_id[' . $loop . ']',
        'wrapper_class' => 'form-row form-row-first',
        'label'         => 'Linked Mock Test (Post ID)',
        'placeholder'   => 'e.g., 1024',
        'value'         => get_post_meta( $variation->ID, '_cppm_mock_test_id', true )
    ) );

    // 3. Select: Number of Attempts
    woocommerce_wp_select( array(
        'id'            => '_cppm_mock_test_attempts[' . $loop . ']',
        'wrapper_class' => 'form-row form-row-last',
        'label'         => 'Attempts Granted',
        'options'       => array(
            ''   => 'Select Attempts',
            '3'  => '3 Attempts',
            '10' => '10 Attempts',
            '-1' => 'Unlimited Attempts'
        ),
        'value'         => get_post_meta( $variation->ID, '_cppm_mock_test_attempts', true )
    ) );

    echo '</div>';
}

// ==========================================
// 2. SAVE THE WOOCOMMERCE VARIATION FIELDS
// ==========================================
add_action( 'woocommerce_save_product_variation', 'cppm_save_mock_test_variation_fields', 10, 2 );
function cppm_save_mock_test_variation_fields( $variation_id, $i ) {
    $is_mock_test = isset( $_POST['_cppm_is_mock_test'][$i] ) ? 'yes' : 'no';
    update_post_meta( $variation_id, '_cppm_is_mock_test', $is_mock_test );

    if ( isset( $_POST['_cppm_mock_test_id'][$i] ) ) {
        update_post_meta( $variation_id, '_cppm_mock_test_id', sanitize_text_field( $_POST['_cppm_mock_test_id'][$i] ) );
    }

    if ( isset( $_POST['_cppm_mock_test_attempts'][$i] ) ) {
        update_post_meta( $variation_id, '_cppm_mock_test_attempts', sanitize_text_field( $_POST['_cppm_mock_test_attempts'][$i] ) );
    }
}

// ==========================================
// 3. THE LEDGER ENGINE: GRANT ACCESS ON PURCHASE
// ==========================================
add_action( 'woocommerce_order_status_completed', 'cppm_grant_mock_test_access' );
function cppm_grant_mock_test_access( $order_id ) {
    $order = wc_get_order( $order_id );
    $user_id = $order->get_user_id();

    // If a guest bought it somehow, abort (they should be logged in via our previous logic)
    if ( ! $user_id ) return;

    foreach ( $order->get_items() as $item ) {
        // Check if the purchased item was a variation
        $variation_id = $item->get_variation_id();
        $target_id = $variation_id > 0 ? $variation_id : $item->get_product_id();

        $is_mock_test = get_post_meta( $target_id, '_cppm_is_mock_test', true );

        if ( $is_mock_test === 'yes' ) {
            $test_id = get_post_meta( $target_id, '_cppm_mock_test_id', true );
            $attempts_bought = intval( get_post_meta( $target_id, '_cppm_mock_test_attempts', true ) );

            if ( $test_id ) {
                $meta_key = '_cppm_mock_test_balance_' . $test_id;
                $current_balance = get_user_meta( $user_id, $meta_key, true );

                // If they already have unlimited, do nothing
                if ( $current_balance == -1 ) {
                    continue; 
                }

                // If they just bought Unlimited, override everything and set to -1
                if ( $attempts_bought == -1 ) {
                    update_user_meta( $user_id, $meta_key, -1 );
                } 
                // Otherwise, add the newly bought attempts to their existing balance
                else {
                    $new_balance = intval( $current_balance ) + $attempts_bought;
                    update_user_meta( $user_id, $meta_key, $new_balance );
                }
            }
        }
    }
}

// ==========================================
// 4. HELPER: CHECK STUDENT'S ATTEMPT BALANCE
// ==========================================
// We will call this function in Phase 9 before letting them start the test
function cppm_get_test_balance( $user_id, $test_id ) {
    $balance = get_user_meta( $user_id, '_cppm_mock_test_balance_' . $test_id, true );
    if ( $balance === '' ) return 0; // Never bought it
    return intval( $balance ); // Returns -1 for unlimited, or the number of remaining attempts
}

// ==========================================
// 5. HELPER: DEDUCT ATTEMPT AFTER EXAM
// ==========================================
// We will call this function in Phase 9 when they submit their exam
function cppm_deduct_test_attempt( $user_id, $test_id ) {
    $balance = cppm_get_test_balance( $user_id, $test_id );
    
    // If unlimited or empty, do nothing
    if ( $balance == -1 || $balance == 0 ) return; 

    // Deduct 1 attempt
    $new_balance = $balance - 1;
    update_user_meta( $user_id, '_cppm_mock_test_balance_' . $test_id, $new_balance );
}