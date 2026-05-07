<?php
/**
 * Core: Mock Test Engine & TCS iON Emulator
 * Architecture: Custom Post Type + Base64 JSON Ledger Logic
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. REGISTER MOCK TEST DATABASE (CPT)
// ==========================================
add_action( 'init', 'cppm_register_mock_test_cpt' );
function cppm_register_mock_test_cpt() {
    register_post_type( 'cppm_mock_test', array(
        'labels' => array(
            'name'          => 'Mock Tests',
            'singular_name' => 'Mock Test',
            'add_new_item'  => 'Add New Mock Test',
            'edit_item'     => 'Edit Mock Test'
        ),
        'public'              => true,
        'exclude_from_search' => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'menu_icon'           => 'dashicons-welcome-write-blog',
        'supports'            => array( 'title' ), 
        'menu_position'       => 56,
    ));
}

// ==========================================
// 2. THE DYNAMIC LAUNCHER UI (SHORTCODE)
// ==========================================
function cppm_get_product_for_mock_test( $test_id ) {
    $args = array(
        'post_type'      => array( 'product', 'product_variation' ),
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array( 'key' => '_cppm_mock_test_id', 'value' => $test_id, 'compare' => '=' )
        )
    );
    $query = new WP_Query( $args );
    return ! empty( $query->posts ) ? $query->posts[0] : false;
}

add_shortcode( 'cppm_mock_launcher', 'cppm_render_mock_launcher' );
function cppm_render_mock_launcher( $atts ) {
    if ( ! is_user_logged_in() ) return '<div style="padding:20px; background:#fee2e2; color:#991b1b; border-radius:8px; text-align:center;">Please log in.</div>';

    $test_id = isset( $_GET['test_id'] ) ? intval( $_GET['test_id'] ) : 0;
    if ( ! $test_id ) return '<div style="padding:40px; text-align:center; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;">No test specified.</div>';

    $user_id = get_current_user_id();
    $test_title = get_the_title( $test_id );
    if ( empty( $test_title ) ) return '<div style="padding:20px; background:#fee2e2; color:#991b1b; text-align:center;">Error: Invalid Test.</div>';

    $ledger = get_user_meta( $user_id, '_cppm_mock_balances', true );
    $balance = ( is_array($ledger) && isset($ledger['test_id_'.$test_id]) ) ? intval($ledger['test_id_'.$test_id]) : 0;
    $product_id = cppm_get_product_for_mock_test( $test_id );

    ob_start();
    ?>
    <div class="cppm-mock-launcher-card" style="max-width:500px; margin:0 auto; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:30px; text-align:center; box-shadow:0 10px 25px rgba(0,0,0,0.05); font-family:'Jost', sans-serif;">
        <div style="width:60px; height:60px; background:#f1f5f9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
        </div>
        <h3 style="margin:0 0 10px; font-size:22px; color:#0f172a;"><?php echo esc_html( $test_title ); ?></h3>
        
        <?php if ( $balance > 0 ) : ?>
            <div style="display:inline-block; background:#dcfce7; color:#166534; padding:6px 12px; border-radius:20px; font-size:13px; font-weight:600; margin-bottom:25px;">You have <?php echo $balance; ?> attempt(s) remaining</div>
            <p style="color:#64748b; font-size:14px; margin-bottom:25px; line-height:1.5;">Once you click start, 1 attempt will be deducted.</p>
            <a href="<?php echo home_url( '/exam-portal/?test_id=' . $test_id ); ?>" style="display:block; width:100%; background:#10b981; color:#fff; padding:14px; border-radius:8px; text-decoration:none; font-weight:700;">Start Mock Test</a>
        <?php else : ?>
            <div style="display:inline-block; background:#fee2e2; color:#991b1b; padding:6px 12px; border-radius:20px; font-size:13px; font-weight:600; margin-bottom:25px;">0 attempts remaining</div>
            <p style="color:#64748b; font-size:14px; margin-bottom:25px; line-height:1.5;">Recharge your wallet to try again.</p>
            <?php if ( $product_id ) : ?>
                <a href="<?php echo wc_get_checkout_url() . '?add-to-cart=' . $product_id . '&buy_now=1'; ?>" style="display:block; width:100%; background:#3b82f6; color:#fff; padding:14px; border-radius:8px; text-decoration:none; font-weight:700;">Recharge Attempts</a>
            <?php else : ?>
                <div style="padding:10px; background:#f1f5f9; color:#475569; border-radius:6px; font-size:14px;">Recharge unavailable.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ==========================================
// 3. MOCK TEST AJAX SAVE & TOGGLE ENGINE (BASE64 ARMOR)
// ==========================================
add_action( 'wp_ajax_cppm_save_mock_test', 'cppm_ajax_save_mock_test' );
function cppm_ajax_save_mock_test() {
    check_ajax_referer( 'cppm_mock_save_action', 'security' );
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
    // Retrieve the base64 string directly - WordPress won't corrupt this!
    $test_data_b64 = isset( $_POST['test_data_b64'] ) ? sanitize_text_field( $_POST['test_data_b64'] ) : ''; 
    $edit_id = isset( $_POST['edit_id'] ) ? intval( $_POST['edit_id'] ) : 0; 

    if ( empty( $title ) || empty( $test_data_b64 ) ) wp_send_json_error( 'Data is missing.' );

    $post_data = array( 'post_title' => $title, 'post_type' => 'cppm_mock_test', 'post_status' => 'publish' );

    if ( $edit_id > 0 ) {
        $post_data['ID'] = $edit_id;
        $existing_post = get_post( $edit_id );
        if ( $existing_post ) $post_data['post_status'] = $existing_post->post_status;
        $post_id = wp_update_post( $post_data );
    } else {
        $post_id = wp_insert_post( $post_data );
    }

    if ( is_wp_error( $post_id ) ) wp_send_json_error( 'Database error: ' . $post_id->get_error_message() );

    // Save the Base64 string safely to the database
    update_post_meta( $post_id, '_cppm_mock_test_b64', $test_data_b64 );

    wp_send_json_success( array( 'message' => 'Test successfully saved!', 'post_id' => $post_id ) );
}

add_action( 'wp_ajax_cppm_toggle_mock_status', 'cppm_ajax_toggle_mock_status' );
function cppm_ajax_toggle_mock_status() {
    check_ajax_referer( 'cppm_mock_save_action', 'security' );
    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) wp_send_json_error( 'Invalid Post ID.' );

    $post = get_post( $post_id );
    if ( ! $post || ( $post->post_author != get_current_user_id() && ! current_user_can('manage_options') ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    $new_status = ($post->post_status === 'publish') ? 'draft' : 'publish';
    wp_update_post( array( 'ID' => $post_id, 'post_status' => $new_status ) );

    wp_send_json_success( array( 'new_status' => $new_status, 'label' => ($new_status === 'publish') ? 'Published' : 'Draft' ) );
}

