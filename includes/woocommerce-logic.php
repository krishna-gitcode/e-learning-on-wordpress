<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================================
// 1. PROGRESS & MEMORY TRACKING AJAX
// ==========================================
add_action( 'wp_ajax_cppm_mark_completed', 'cppm_ajax_done' );
function cppm_ajax_done() {
    if(!is_user_logged_in()) wp_send_json_error();
    $uid = get_current_user_id();
    $pid = intval($_POST['playlist_id']);
    $idx = intval($_POST['video_index']);
    $data = get_user_meta($uid, '_cppm_completed_videos', true);
    if(!is_array($data)) $data = array();
    if(!isset($data[$pid])) $data[$pid] = array();
    if(!in_array($idx, $data[$pid])) $data[$pid][] = $idx;
    update_user_meta($uid, '_cppm_completed_videos', $data);
    wp_send_json_success();
}

add_action( 'wp_ajax_cppm_save_last_video', 'cppm_ajax_save_last_video' );
function cppm_ajax_save_last_video() {
    if(!is_user_logged_in()) wp_send_json_error();
    $uid = get_current_user_id();
    $pid = intval($_POST['playlist_id']);
    $idx = intval($_POST['video_index']);
    update_user_meta($uid, '_cppm_last_vid_' . $pid, $idx);
    wp_send_json_success();
}

add_action( 'wp_ajax_cppm_save_notes', 'cppm_ajax_save_notes' );
function cppm_ajax_save_notes() {
    if(!is_user_logged_in()) wp_send_json_error();
    $uid = get_current_user_id();
    $pid = intval($_POST['playlist_id']);
    $notes = sanitize_textarea_field($_POST['notes']);
    update_user_meta($uid, '_cppm_notes_' . $pid, $notes);
    wp_send_json_success();
}

add_action( 'wp_ajax_cppm_load_video', 'cppm_ajax_load_video' );
function cppm_ajax_load_video() {
    if(!is_user_logged_in()) wp_send_json_error("Auth missing");
    $pid = intval($_POST['playlist_id']);
    $idx = intval($_POST['video_index']);
    $videos = get_post_meta($pid, '_cppm_videos_array', true);
    if (isset($videos[$idx])) {
        $html = do_shortcode('[presto_player src="' . esc_attr($videos[$idx]['url']) . '"]');
        wp_send_json_success(array('html' => $html));
    }
    wp_send_json_error("Video not found");
}

// ==========================================
// 2. YOUTUBE-STYLE AJAX COMMENTS
// ==========================================
add_action('wp_ajax_cppm_submit_comment', 'cppm_handle_ajax_comment');
function cppm_handle_ajax_comment() {
    if ( ! isset($_POST['comment_post_ID']) ) wp_send_json_error("Missing Post ID");
    $comment_data = wp_handle_comment_submission( wp_unslash( $_POST ) );
    if ( is_wp_error( $comment_data ) ) wp_send_json_error( $comment_data->get_error_message() );
    
    $vid_index = isset( $_POST['cppm_vid_index'] ) ? intval($_POST['cppm_vid_index']) : 0;
    $vid_title = isset( $_POST['cppm_vid_title'] ) ? sanitize_text_field($_POST['cppm_vid_title']) : '';
    add_comment_meta( $comment_data->comment_ID, '_cppm_vid_index', $vid_index );
    add_comment_meta( $comment_data->comment_ID, '_cppm_vid_title', $vid_title );

    ob_start();
    $is_instructor = user_can($comment_data->user_id, 'manage_options');
    $instructor_badge = $is_instructor ? '<span style="background:#111827; color:#fff; font-size:10px; padding:2px 6px; border-radius:12px; margin-left:8px; font-weight:600;">Instructor</span>' : '';
    
    echo '<div class="cppm-yt-comment cppm-new-comment-anim" data-video-index="' . esc_attr($vid_index) . '">';
    echo '<div class="cppm-yt-avatar">' . get_avatar( $comment_data, 40, '', '', array('style' => 'border-radius:50%;') ) . '</div>';
    echo '<div class="cppm-yt-comment-body">';
    echo '<div class="cppm-yt-comment-header"><span class="cppm-yt-author">' . esc_html($comment_data->comment_author) . $instructor_badge . '</span><span class="cppm-yt-time">Just now</span></div>';
    
    if ($vid_title) { echo '<div class="cppm-yt-badge">📍 ' . esc_html($vid_title) . '</div>'; }
    
    $content = apply_filters('comment_text', $comment_data->comment_content, $comment_data);
    echo '<div class="cppm-yt-text">' . wp_kses_post($content) . '</div>';
    
    echo '<div class="cppm-yt-actions-row">
            <span style="cursor:pointer;">👍</span>
            <span style="cursor:pointer; margin-left:8px;">👎</span>
            <span style="cursor:pointer; margin-left:16px; font-weight:600;">Reply</span>
          </div>';
    echo '</div></div>';
    
    wp_send_json_success( ob_get_clean() );
}

// ==========================================
// 3. WOOCOMMERCE DASHBOARD
// ==========================================
add_action( 'init', 'cppm_add_endpoint' );
function cppm_add_endpoint() { add_rewrite_endpoint( 'my-courses', EP_ROOT | EP_PAGES ); }

add_filter( 'woocommerce_account_menu_items', 'cppm_menu' );
function cppm_menu( $items ) { return array_merge( array('my-courses' => 'My Courses'), $items ); }

add_action( 'woocommerce_account_my-courses_endpoint', 'cppm_content' );
function cppm_content() {
    echo '<h3 style="margin-bottom:20px;">Your Enrolled Modules</h3>';
    $orders = wc_get_orders( array('customer_id' => get_current_user_id(), 'status' => 'completed') );
    $ids = array();
    foreach ($orders as $o) { foreach ($o->get_items() as $item) { $ids[] = $item->get_product_id(); } }
    $ids = array_unique($ids);
    if(empty($ids)) { echo 'No active enrollments found.'; } else {
        echo '<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:25px;">';
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product) continue;
            $page_id = get_post_meta($pid, '_course_page_id', true);
            $url = $page_id ? get_permalink($page_id) : get_permalink($pid);
            echo '<div style="background:#fff; border:1px solid #e5e7eb; padding:25px; border-radius:15px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); display:flex; flex-direction:column; justify-content:space-between;">';
            echo '<div><h4 style="margin:0 0 10px 0; color:#111827; font-size:1.1rem;">' . esc_html($product->get_name()) . '</h4></div>';
            echo '<a href="' . esc_url($url) . '" style="background:#2563eb; color:#fff; padding:12px; text-align:center; text-decoration:none; border-radius:10px; font-weight:600;">Open Classroom</a>';
            echo '</div>';
        }
        echo '</div>';
    }
}

add_action( 'woocommerce_product_options_general_product_data', 'cppm_wc_fields' );
function cppm_wc_fields() {
    echo '<div class="options_group">';
    $pages = get_posts( array('post_type' => 'page', 'posts_per_page' => -1) );
    $opts = array('' => '-- Link to Classroom Page --');
    foreach ($pages as $p) { $opts[$p->ID] = $p->post_title; }
    woocommerce_wp_select( array('id' => '_course_page_id', 'label' => 'Learning Dashboard Page', 'options' => $opts) );
    echo '</div>';
}
add_action( 'woocommerce_process_product_meta', 'cppm_save_wc' );
function cppm_save_wc( $post_id ) {
    if ( isset($_POST['_course_page_id']) ) update_post_meta($post_id, '_course_page_id', sanitize_text_field($_POST['_course_page_id']));
}

// ==========================================
// 4. CERTIFICATE GENERATOR ENGINE
// ==========================================
add_action( 'template_redirect', 'cppm_generate_certificate' );
function cppm_generate_certificate() {
    if ( isset($_GET['cppm_certificate']) && is_user_logged_in() ) {
        $pid = intval($_GET['cppm_certificate']);
        $uid = get_current_user_id();

        // 1. Verify Completion
        $videos = get_post_meta($pid, '_cppm_videos_array', true);
        $total_vids = is_array($videos) ? count($videos) : 0;
        $completed_data = get_user_meta($uid, '_cppm_completed_videos', true);
        $completed_vids = isset($completed_data[$pid]) ? count($completed_data[$pid]) : 0;

        if ( $total_vids === 0 || $completed_vids < $total_vids ) {
            wp_die('<h3>Certificate Locked</h3><p>You must reach 100% completion before downloading your certificate.</p>', 'Access Denied', array('response' => 403));
        }

        // 2. Fetch Data
        $user_info = wp_get_current_user();
        $student_name = $user_info->display_name;
        $course_name = get_the_title($pid);
        $date = date('F j, Y');

        // 3. Render the Certificate Template
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Certificate - <?php echo esc_attr($student_name); ?></title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@400;600&display=swap');
                body { background: #e5e7eb; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; font-family: 'Inter', sans-serif; }
                .cert-container { background: #fff; width: 1050px; height: 740px; padding: 40px; box-sizing: border-box; border: 15px solid #111827; position: relative; text-align: center; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
                .cert-border { border: 3px solid #d1d5db; height: 100%; padding: 50px; box-sizing: border-box; position: relative; }
                
                h1 { font-family: 'Playfair Display', serif; font-size: 50px; color: #111827; margin: 0 0 15px; letter-spacing: 3px; text-transform: uppercase; }
                h2 { font-family: 'Playfair Display', serif; font-size: 26px; color: #6b7280; margin: 0 0 45px; font-style: italic; font-weight: normal; }
                
                .student-name { font-family: 'Playfair Display', serif; font-size: 55px; color: #2563eb; margin: 10px 0; border-bottom: 2px solid #e5e7eb; display: inline-block; padding: 0 50px 10px; font-weight: bold; }
                
                p { font-size: 18px; color: #4b5563; margin: 25px 0 10px; }
                .course-name { font-size: 32px; color: #111827; font-weight: 600; margin: 15px 0; padding: 0 40px;}
                
                .footer { display: flex; justify-content: space-between; margin-top: 80px; padding: 0 40px; }
                .signature { border-top: 2px solid #9ca3af; width: 250px; padding-top: 15px; font-weight: bold; color: #111827; font-size:14px; text-transform:uppercase; letter-spacing:1px; }
                .signature span { font-weight: normal; font-size: 16px; display: block; margin-top: 8px; color: #4b5563; text-transform:none; letter-spacing:0; }
                
                .seal { position: absolute; bottom: 65px; left: 50%; transform: translateX(-50%); width: 110px; height: 110px; background: #fbbf24; border-radius: 50%; display: flex; justify-content: center; align-items: center; border: 4px dashed #fff; box-shadow: 0 0 0 5px #fbbf24; color: #fff; font-weight: bold; text-align: center; font-size: 13px; text-transform: uppercase; letter-spacing:1px; line-height:1.4; }
                
                .print-btn { position: fixed; top: 20px; right: 20px; background: #2563eb; color: #fff; border: none; padding: 14px 28px; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: 0.2s; z-index: 1000; display: flex; align-items: center; gap: 8px;}
                .print-btn:hover { background: #1d4ed8; transform: translateY(-2px); }
                
                /* Rules to ensure it looks perfect when printing to PDF */
                @media print {
                    @page { margin: 0; size: landscape; }
                    body { background: #fff; display: block; height: auto; }
                    .cert-container { box-shadow: none; width: 100%; height: 100vh; border: 15px solid #111827; }
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            <button class="print-btn no-print" onclick="window.print()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Save as PDF / Print
            </button>
            <div class="cert-container">
                <div class="cert-border">
                    <h1>Certificate of Completion</h1>
                    <h2>This is to certify that</h2>
                    
                    <div class="student-name"><?php echo esc_html($student_name); ?></div>
                    
                    <p>has successfully completed the comprehensive training program for</p>
                    
                    <div class="course-name"><?php echo esc_html($course_name); ?></div>
                    
                    <div class="seal">Official<br>Certified</div>

                    <div class="footer">
                        <div class="signature">
                            Date Completed
                            <span><?php echo esc_html($date); ?></span>
                        </div>
                        <div class="signature">
                            Platform Administrator
                            <span>Verified Issue</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                // Auto-trigger print dialogue after images load
                window.onload = function() { setTimeout(function(){ window.print(); }, 500); }
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

// ==========================================
// 5. COMMERCE LOGIC & PROTECTIONS (PHASE 1)
// ==========================================

// Fix Issue #6: Restrict Courses and E-Books to a maximum quantity of 1
add_filter( 'woocommerce_is_sold_individually', 'cppm_restrict_virtual_qty', 10, 2 );
function cppm_restrict_virtual_qty( $return, $product ) {
    // If it's a virtual or downloadable product, it should only be bought once per order
    if ( $product->is_virtual() || $product->is_downloadable() ) {
        return true;
    }

    // Fallback: Check categories just in case
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
    // If user is not logged in, and is trying to access the checkout page
    if ( ! is_user_logged_in() && is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) && ! is_wc_endpoint_url( 'order-received' ) ) {
        
        // Add a friendly notice
        wc_add_notice( 'Please log in or register to complete your purchase and access your classroom.', 'notice' );
        
        // Redirect to the WooCommerce "My Account" page (which acts as the login/registration page)
        wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
        exit;
    }
}