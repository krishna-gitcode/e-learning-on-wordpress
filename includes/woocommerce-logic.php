<?php
/**
 * Core: WooCommerce Overrides, AJAX Handlers & PDF Certificate Engine
 * Architecture: Pure Logic (No Assets)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. PROGRESS & MEMORY TRACKING AJAX
// ==========================================

// Mark Video as Completed
add_action( 'wp_ajax_cppm_mark_completed', 'cppm_ajax_done' );
function cppm_ajax_done() {
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Unauthorized' );
    
    $uid = get_current_user_id();
    $pid = intval( $_POST['playlist_id'] );
    $idx = intval( $_POST['video_index'] );
    
    $data = get_user_meta( $uid, '_cppm_completed_videos', true );
    if ( ! is_array( $data ) ) $data = array();
    if ( ! isset( $data[$pid] ) ) $data[$pid] = array();
    if ( ! in_array( $idx, $data[$pid] ) ) $data[$pid][] = $idx;
    
    update_user_meta( $uid, '_cppm_completed_videos', $data );
    wp_send_json_success();
}

// Save Last Watched Video Index
add_action( 'wp_ajax_cppm_save_last_video', 'cppm_ajax_save_last_video' );
function cppm_ajax_save_last_video() {
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Unauthorized' );
    
    $uid = get_current_user_id();
    $pid = intval( $_POST['playlist_id'] );
    $idx = intval( $_POST['video_index'] );
    
    update_user_meta( $uid, '_cppm_last_vid_' . $pid, $idx );
    wp_send_json_success();
}

// Save Private Notes
add_action( 'wp_ajax_cppm_save_notes', 'cppm_ajax_save_notes' );
function cppm_ajax_save_notes() {
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Unauthorized' );
    
    $uid   = get_current_user_id();
    $pid   = intval( $_POST['playlist_id'] );
    $notes = sanitize_textarea_field( $_POST['notes'] );
    
    update_user_meta( $uid, '_cppm_notes_' . $pid, $notes );
    wp_send_json_success();
}

// Submit Discussion Comment
add_action( 'wp_ajax_cppm_submit_comment', 'cppm_ajax_submit_comment' );
function cppm_ajax_submit_comment() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( "You must be logged in to comment." );
    }

    $post_id = isset( $_POST['comment_post_ID'] ) ? intval( $_POST['comment_post_ID'] ) : 0;
    $content = isset( $_POST['comment'] ) ? trim( $_POST['comment'] ) : '';
    $vid_idx = isset( $_POST['cppm_vid_index'] ) ? sanitize_text_field( $_POST['cppm_vid_index'] ) : '';
    $vid_ttl = isset( $_POST['cppm_vid_title'] ) ? sanitize_text_field( $_POST['cppm_vid_title'] ) : '';

    if ( ! $post_id || empty( $content ) ) {
        wp_send_json_error( "Invalid comment data." );
    }

    $current_user = wp_get_current_user();
    $commentdata = array(
        'comment_post_ID'      => $post_id,
        'comment_author'       => $current_user->display_name,
        'comment_author_email' => $current_user->user_email,
        'comment_author_url'   => $current_user->user_url,
        'comment_content'      => $content,
        'comment_type'         => '',
        'comment_parent'       => 0,
        'user_id'              => $current_user->ID,
        'comment_approved'     => 1, 
    );

    $comment_id = wp_insert_comment( $commentdata );

    if ( $comment_id ) {
        update_comment_meta( $comment_id, '_cppm_vid_index', $vid_idx );
        update_comment_meta( $comment_id, '_cppm_vid_title', $vid_ttl );

        $c = get_comment( $comment_id );
        ob_start();
        $is_instructor = user_can( $c->user_id, 'manage_options' );
        $badge_html = $is_instructor ? '<span style="background:var(--text-main); color:var(--bg-main); font-size:10px; padding:2px 6px; border-radius:12px; margin-left:8px; font-weight:600;">Instructor</span>' : '';
        
        echo '<div class="cppm-yt-comment cppm-custom-comment" data-video-index="' . esc_attr($vid_idx) . '" style="display:flex;">';
        echo '<div class="cppm-yt-avatar">' . get_avatar( $c, 40, '', '', array('style' => 'border-radius:50%;') ) . '</div>';
        echo '<div class="cppm-yt-comment-body" style="flex:1;">';
        echo '<div class="cppm-yt-comment-header"><span class="cppm-yt-author">' . esc_html($c->comment_author) . $badge_html . '</span><span class="cppm-yt-time">Just now</span></div>';
        if ( $vid_ttl ) { echo '<div class="cppm-yt-badge">📍 ' . esc_html($vid_ttl) . '</div>'; }
        echo '<div class="cppm-yt-text">' . wp_kses_post( apply_filters( 'comment_text', $c->comment_content, $c ) ) . '</div>';
        echo '<div class="cppm-yt-actions-row"><span style="cursor:pointer;">👍</span><span style="cursor:pointer; margin-left:8px;">👎</span><span style="cursor:pointer; margin-left:16px; font-weight:600;">Reply</span></div>';
        echo '</div></div>';
        
        wp_send_json_success( ob_get_clean() );
    } else {
        wp_send_json_error( "Failed to save comment." );
    }
}

// ==========================================
// 2. WOOCOMMERCE CORE OVERRIDES
// ==========================================

// Fix Issue #16: Prevent Multiple Qty of the Same Digital Product
add_filter( 'woocommerce_is_sold_individually', 'cppm_restrict_virtual_qty', 10, 2 );
function cppm_restrict_virtual_qty( $return, $product ) {
    if ( $product->is_virtual() || $product->is_downloadable() ) {
        return true;
    }

    $terms = get_the_terms( $product->get_id(), 'product_cat' );
    if ( $terms && ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            $slug = strtolower( $term->slug );
            if ( strpos($slug, 'ebook') !== false || strpos($slug, 'e-book') !== false || $slug === 'music-course' || $slug === 'course' ) {
                return true; 
            }
        }
    }
    return $return;
}

// Fix Issue #17: Redirect Guest Users to Login Page when attempting to Checkout
add_action( 'template_redirect', 'cppm_guest_checkout_redirect' );
function cppm_guest_checkout_redirect() {
    if ( ! is_user_logged_in() && is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) && ! is_wc_endpoint_url( 'order-received' ) ) {
        wc_add_notice( 'You must be logged in to checkout. Please log in or create an account.', 'error' );
        $login_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
        wp_redirect( $login_url );
        exit;
    }
}

// ==========================================
// 3. AUTOMATED CERTIFICATE ENGINE (FPDF)
// ==========================================
function cppm_generate_certificate_pdf( $user, $course_id ) {
    if ( ! class_exists('setasign\Fpdi\Fpdi') ) {
        require_once plugin_dir_path(__DIR__) . 'fpdf/fpdf.php';
        require_once plugin_dir_path(__DIR__) . 'fpdi/src/autoload.php';
    }

    $pdf = new \setasign\Fpdi\Fpdi();
    $pdf->AddPage('L', 'A4'); 
    
    // Set the source template
    $template_path = plugin_dir_path(__DIR__) . 'assets/certificate-template.pdf'; 
    if ( file_exists( $template_path ) ) {
        $pdf->setSourceFile( $template_path );
        $tplIdx = $pdf->importPage( 1 );
        $pdf->useTemplate( $tplIdx, 0, 0, 297, 210, true ); 
    }

    $student_name = $user->user_firstname . ' ' . $user->user_lastname;
    if ( trim( $student_name ) === '' ) { $student_name = $user->display_name; }

    $course_title = get_the_title( $course_id );
    $date_completed = date( 'F j, Y' );
    $cert_id = 'SM-' . strtoupper( substr( md5( $user->ID . $course_id . time() ), 0, 8 ) );

    // Draw Student Name
    $pdf->SetFont('Arial', 'B', 32);
    $pdf->SetTextColor(30, 41, 59); // Slate 800
    $pdf->SetXY(0, 95);
    $pdf->Cell(297, 15, utf8_decode( $student_name ), 0, 1, 'C');

    // Draw Course Title
    $pdf->SetFont('Arial', 'I', 20);
    $pdf->SetTextColor(71, 85, 105); // Slate 600
    $pdf->SetXY(0, 120);
    $pdf->Cell(297, 10, utf8_decode( 'For successfully completing: ' . $course_title ), 0, 1, 'C');

    // Draw Meta Data (Date & ID)
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor(100, 116, 139); 
    
    $pdf->SetXY(40, 160);
    $pdf->Cell(60, 10, utf8_decode( 'Date: ' . $date_completed ), 0, 0, 'L');
    
    $pdf->SetXY(197, 160);
    $pdf->Cell(60, 10, utf8_decode( 'Cert ID: ' . $cert_id ), 0, 0, 'R');

    // Secure Output
    $pdf->Output('D', 'Sarkari_Musician_Certificate_' . $cert_id . '.pdf');
    exit;
}

add_action( 'template_redirect', 'cppm_handle_certificate_download' );
function cppm_handle_certificate_download() {
    if ( isset( $_GET['cppm_certificate'] ) && is_user_logged_in() ) {
        
        $course_id = intval( $_GET['cppm_certificate'] );
        $current_user = wp_get_current_user();

        // 1. Verify 100% Completion
        $completed_data = get_user_meta( $current_user->ID, '_cppm_completed_videos', true );
        $completed_videos = isset( $completed_data[$course_id] ) ? $completed_data[$course_id] : array();
        
        $videos = get_post_meta( $course_id, '_cppm_videos_array', true );
        $total_vids = is_array( $videos ) ? count( $videos ) : 0;
        
        if ( $total_vids === 0 || count( $completed_videos ) < $total_vids ) {
            wp_die( 'You have not completed this course yet.', 'Access Denied', array( 'response' => 403 ) );
        }

        // 2. Verify Enrollment (To prevent direct URL manipulation)
        $prod_id = get_post_meta( $course_id, '_cppm_required_product', true );
        if ( ! empty($prod_id) && function_exists('wc_customer_bought_product') ) {
            if ( ! wc_customer_bought_product( $current_user->user_email, $current_user->ID, $prod_id ) && ! current_user_can('manage_options') ) {
                wp_die( 'You do not own this course.', 'Access Denied', array( 'response' => 403 ) );
            }
        }

        // 3. Generate & Download
        cppm_generate_certificate_pdf( $current_user, $course_id );
    }
}