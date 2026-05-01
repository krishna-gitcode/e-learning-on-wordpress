<?php
/**
 * Core: Netflix-Style Video Player & Interactive Classroom
 * Architecture: Modular / Asset Separated
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. AJAX HANDLER: DELETE COMMENT
// ==========================================
add_action( 'wp_ajax_cppm_delete_comment', 'cppm_ajax_delete_comment' );
function cppm_ajax_delete_comment() {
    check_ajax_referer( 'cppm_dashboard_ajax_nonce', 'security' );

    $comment_id = isset($_POST['comment_id']) ? intval($_POST['comment_id']) : 0;
    if ( !$comment_id ) wp_send_json_error("Invalid comment ID.");

    $comment = get_comment($comment_id);
    if ( !$comment ) wp_send_json_error("Comment not found.");

    $current_user_id = get_current_user_id();
    if ( !$current_user_id ) wp_send_json_error("You must be logged in.");

    // SECURITY: Only allow deletion if user is the comment author OR an Administrator
    if ( $current_user_id != $comment->user_id && !current_user_can('manage_options') ) {
        wp_send_json_error("You do not have permission to delete this comment.");
    }

    // Force delete the comment (bypass trash)
    if ( wp_delete_comment( $comment_id, true ) ) {
        wp_send_json_success("Comment deleted successfully.");
    } else {
        wp_send_json_error("Failed to delete comment.");
    }
}

// ==========================================
// 2. COURSE PLAYLIST SHORTCODE
// ==========================================
add_shortcode( 'course_playlist', 'cppm_render_playlist' );
function cppm_render_playlist( $atts ) {
    
    // 1. DYNAMIC ROUTING: Check the URL first (e.g., ?course_id=88)
    $playlist_id = isset( $_GET['course_id'] ) ? intval( $_GET['course_id'] ) : 0;

    // 2. FALLBACK: Check shortcode attribute if URL is empty
    if ( ! $playlist_id ) {
        $atts = shortcode_atts( array( 'id' => '' ), $atts );
        $playlist_id = intval( $atts['id'] );
    }

    if ( empty( $playlist_id ) ) {
        return '<div style="padding:40px; text-align:center; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;"><h3 style="color:#0f172a; margin-top:0;">Course Not Found</h3><p>Please provide a valid Course ID in the URL.</p></div>';
    }

    $videos = get_post_meta( $playlist_id, '_cppm_videos_array', true );
    $prod_id = get_post_meta( $playlist_id, '_cppm_required_product', true );
    $current_user = wp_get_current_user();

    // 1. Auth Check
    if ( ! is_user_logged_in() ) return '<div style="padding:40px; text-align:center; background:#fff3f3; border-radius:12px;"><h3 style="color:#d32f2f; margin-top:0;">Locked</h3><p>Please log in to access this course.</p></div>';
    
    // 2. Enrollment Check
    if ( ! empty($prod_id) && function_exists('wc_customer_bought_product') ) {
        if ( ! wc_customer_bought_product( $current_user->user_email, $current_user->ID, $prod_id ) && ! current_user_can('manage_options') ) {
            return '<div style="padding:40px; text-align:center; background:#fff8e1; border-radius:12px;"><h3 style="color:#f57f17; margin-top:0;">Enrollment Required</h3><p>You must purchase this course to access it.</p></div>';
        }
    }
    
    if ( empty($videos) ) return '<div style="padding:40px; text-align:center;">No modules found.</div>';

    // 3. ENQUEUE ASSETS
    $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
    wp_enqueue_style( 'cppm-player-css', $plugin_url . 'assets/css/frontend-player.css', array(), '1.0.0' );
    wp_enqueue_script( 'cppm-player-js', $plugin_url . 'assets/js/frontend-player.js', array(), '1.0.0', true );

    wp_localize_script( 'cppm-player-js', 'cppmPlayerData', array(
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'ajaxNonce' => wp_create_nonce( 'cppm_dashboard_ajax_nonce' )
    ));

    // 4. Progress Logic
    $completed_data = get_user_meta( $current_user->ID, '_cppm_completed_videos', true );
    $completed_videos = isset( $completed_data[$playlist_id] ) ? $completed_data[$playlist_id] : array();
    $total_vids = count($videos);
    $completed_count = count($completed_videos);
    $progress_percent = ($total_vids > 0) ? round(($completed_count / $total_vids) * 100) : 0;
    
    $is_100_percent = ($progress_percent == 100);
    $cert_url = site_url('?cppm_certificate=' . $playlist_id);

    $last_vid_str = get_user_meta($current_user->ID, '_cppm_last_vid_' . $playlist_id, true);
    $last_vid = ($last_vid_str !== '') ? intval($last_vid_str) : 0;
    if ($last_vid >= $total_vids) $last_vid = 0; 
    $is_last_done = in_array($last_vid, $completed_videos);

    $saved_notes = get_user_meta($current_user->ID, '_cppm_notes_' . $playlist_id, true);

    $ui_brand = get_option('cppm_ui_btn_color', '#2563eb');
    $ui_active_bg = get_option('cppm_ui_active_bg', '#f0f7ff');
    $custom_css = get_option('cppm_custom_css', ''); 
    
    $uid = uniqid('px_');

    ob_start();

    if ( ! empty( $custom_css ) ) {
        echo '<style>' . wp_strip_all_tags( $custom_css ) . '</style>';
    }
    
    
    ?>

    <div id="playlist-<?php echo $uid; ?>" class="cppm-container" data-uid="<?php echo $uid; ?>" data-pid="<?php echo $playlist_id; ?>" data-total="<?php echo $total_vids; ?>" style="--brand: <?php echo esc_attr($ui_brand); ?>; --active-bg: <?php echo esc_attr($ui_active_bg); ?>;">
        <input type="hidden" id="cppm-active-index-<?php echo $uid; ?>" value="<?php echo $last_vid; ?>">
        
        <div class="cppm-player-container">
            <div class="cppm-video-box">
                <div class="cppm-video-frame" id="cppm-video-frame-<?php echo $uid; ?>">
                    <?php foreach ( $videos as $index => $vid ) : ?>
                        <div class="cppm-player-slide" id="player-slide-<?php echo $uid . '-' . $index; ?>" style="display: <?php echo $index === $last_vid ? 'block' : 'none'; ?>;">
                            <?php 
                            $vid_val = trim($vid['url']);
                            if ( is_numeric($vid_val) ) {
                                echo do_shortcode('[presto_player id="' . esc_attr($vid_val) . '"]');
                            } else {
                                echo do_shortcode('[presto_player src="' . esc_url($vid_val) . '"]');
                            }
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="cppm-toast-<?php echo $uid; ?>" class="cppm-auto-advance-toast"><div class="cppm-spinner"></div> Up next in 3...</div>
                
                <div class="cppm-header-actions">
                    <div>
                        <span id="cppm-active-label-<?php echo $uid; ?>" style="font-size:0.8rem; color:var(--text-sec); font-weight:700; text-transform:uppercase;">Lesson <?php echo ($last_vid + 1); ?></span>
                        <h1 id="cppm-active-title-<?php echo $uid; ?>" style="font-size: 1.5rem; margin: 0; color:var(--text-main);"><?php echo esc_html($videos[$last_vid]['title']); ?></h1>
                    </div>
                    <div class="cppm-btn-group">
                        <button type="button" class="cppm-utility-btn cppm-ux-darkmode" title="Toggle Dark Mode">🌙</button>
                        <button type="button" class="cppm-utility-btn cppm-ux-theater" title="Toggle Theater Mode">⛶</button>
                        <button type="button" class="cppm-action-btn cppm-ux-done" data-pid="<?php echo $playlist_id; ?>" data-idx="<?php echo $last_vid; ?>" id="done-btn-<?php echo $uid; ?>" style="display:<?php echo $is_last_done ? 'none' : 'block'; ?>;">Mark Complete ✓</button>
                    </div>
                </div>
            </div>

            <div class="cppm-notes-area">
                <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin:0; font-size:1.2rem; color:var(--text-main);">My Private Notes</h3>
                    <button type="button" class="cppm-utility-btn cppm-ux-timestamp">⏱ Timestamp</button>
                </div>
                <textarea id="cppm-notes-input-<?php echo $uid; ?>" style="width:100%; height:120px; padding:15px; border:1px solid var(--border); border-radius:12px; font-family:inherit; font-size:15px; resize:vertical; background:var(--bg-sec); color:var(--text-main); transition:0.3s; box-sizing: border-box;" placeholder="Type your notes here..."><?php echo esc_textarea($saved_notes); ?></textarea>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:15px;">
                    <span id="cppm-notes-status-<?php echo $uid; ?>" style="font-size:0.85rem; color:#10b981; font-weight:600; opacity:0; transition:0.3s;">✓ Notes saved!</span>
                    <button type="button" class="cppm-action-btn cppm-ux-save-notes">Save Notes</button>
                </div>
                <div id="cppm-bookmarks-<?php echo $uid; ?>"></div>
            </div>

            <div class="cppm-discussion-area">
                <?php 
                $get_comments_args = array( 'post_id' => get_the_ID(), 'status' => 'approve', 'order' => 'DESC' );
                if ( is_user_logged_in() ) { $get_comments_args['include_unapproved'] = array( $current_user->ID ); } 
                $comments = get_comments( $get_comments_args );

                $current_user_avatar = get_avatar( $current_user->ID, 40, '', '', array('style' => 'border-radius:50%;') );
                ?>
                
                <div class="cppm-custom-comment-form-wrapper">
                    <span style="font-size:1.3rem; font-weight:700; color:var(--text-main); margin-bottom:20px; display:block;">
                        <span id="cppm-comment-count-<?php echo $uid; ?>"><?php echo count($comments); ?></span> Comments
                    </span>
                    <form class="cppm-ajax-comment-form">
                        <input type="hidden" name="comment_post_ID" value="<?php echo get_the_ID(); ?>">
                        <div class="cppm-yt-form-row">
                            <div class="cppm-yt-avatar"><?php echo $current_user_avatar; ?></div>
                            <div class="cppm-yt-input-container">
                                <textarea name="comment" rows="1" class="cppm-yt-textarea" placeholder="Add a comment... (Press Enter to post, Shift+Enter for new line)" required></textarea>
                                <div class="cppm-yt-underline"></div>
                            </div>
                        </div>
                        <div class="cppm-yt-form-actions">
                            <button type="button" class="cppm-yt-cancel">Cancel</button>
                            <button type="submit" class="cppm-yt-submit" disabled>Comment</button>
                        </div>
                    </form>
                </div>

                <?php
                echo '<div id="cppm-comments-wrapper-' . $uid . '" style="margin-top:30px;">';
                if ( $comments ) {
                    foreach($comments as $c) {
                        $vid_index = get_comment_meta( $c->comment_ID, '_cppm_vid_index', true );
                        $badge = get_comment_meta( $c->comment_ID, '_cppm_vid_title', true );
                        $filter_attr = ($vid_index !== '') ? esc_attr($vid_index) : 'all';
                        
                        $is_instructor = user_can($c->user_id, 'manage_options');
                        $instructor_badge = $is_instructor ? '<span style="background:var(--text-main); color:var(--bg-main); font-size:10px; padding:2px 6px; border-radius:12px; margin-left:8px; font-weight:600;">Instructor</span>' : '';
                        
                        // DETERMINE IF USER CAN DELETE THIS COMMENT
                        $can_delete = ($current_user->ID && ($current_user->ID == $c->user_id || current_user_can('manage_options')));
                        $delete_btn = $can_delete ? '<span class="cppm-yt-delete" data-cid="' . esc_attr($c->comment_ID) . '" style="cursor:pointer; margin-left:16px; color:#ef4444; font-weight:600;" title="Delete Comment">Delete</span>' : '';

                        $display_style = ($filter_attr === 'all' || $filter_attr == $last_vid) ? 'flex' : 'none';

                        echo '<div class="cppm-yt-comment cppm-custom-comment" data-video-index="' . esc_attr($filter_attr) . '" style="display:' . $display_style . ';">';
                        echo '<div class="cppm-yt-avatar">' . get_avatar( $c, 40, '', '', array('style' => 'border-radius:50%;') ) . '</div>';
                        echo '<div class="cppm-yt-comment-body" style="flex:1;">';
                        echo '<div class="cppm-yt-comment-header"><span class="cppm-yt-author">' . esc_html($c->comment_author) . $instructor_badge . '</span><span class="cppm-yt-time">' . date('M j, Y', strtotime($c->comment_date)) . '</span></div>';
                        if ($badge) { echo '<div class="cppm-yt-badge">📍 ' . esc_html($badge) . '</div>'; }
                        $content = apply_filters('comment_text', $c->comment_content, $c);
                        echo '<div class="cppm-yt-text">' . wp_kses_post($content) . '</div>';
                        echo '<div class="cppm-yt-actions-row"><span style="cursor:pointer;">👍</span><span style="cursor:pointer; margin-left:8px;">👎</span><span style="cursor:pointer; margin-left:16px; font-weight:600;">Reply</span>' . $delete_btn . '</div>';
                        echo '</div></div>';
                    }
                }
                echo '</div>';
                ?>
            </div>
        </div>
        
        <div class="cppm-sidebar">
            <div class="cppm-progress-header">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:700; color:var(--text-main);">Progress</span>
                    <span style="font-size:0.9rem; color:var(--text-sec);"><b id="count-<?php echo $uid; ?>"><?php echo $completed_count; ?></b> / <?php echo $total_vids; ?></span>
                </div>
                <div class="cppm-bar-bg"><div id="fill-<?php echo $uid; ?>" class="cppm-bar-fill" style="width:<?php echo $progress_percent; ?>%"></div></div>
                <a href="<?php echo esc_url($cert_url); ?>" target="_blank" id="cppm-cert-btn-<?php echo $uid; ?>" class="cppm-cert-btn" style="display:<?php echo $is_100_percent ? 'block' : 'none'; ?>;">🏆 Download Certificate</a>
            </div>
            
            <div class="cppm-search-wrap">
                <input type="text" class="cppm-course-search" placeholder="🔍 Search lessons...">
            </div>

            <div class="cppm-list">
                <?php 
                function cppm_get_days_since_purchase_inline($user_id, $product_id) {
                    if (empty($product_id) || !function_exists('wc_get_orders')) return 9999;
                    $orders = wc_get_orders( array('customer_id' => $user_id, 'status' => array('wc-completed'), 'limit' => -1) );
                    foreach ( $orders as $order ) {
                        foreach ( $order->get_items() as $item ) {
                            if ( $item->get_product_id() == $product_id ) {
                                $order_date = $order->get_date_completed();
                                if ($order_date) {
                                    $now = new WC_DateTime();
                                    $diff = $now->diff($order_date);
                                    return $diff->days;
                                }
                            }
                        }
                    }
                    return 0;
                }
                
                $days_enrolled = cppm_get_days_since_purchase_inline($current_user->ID, $prod_id);
                
                foreach ( $videos as $index => $vid ) : 
                    $is_done = in_array($index, $completed_videos); 
                    $required_drip_days = isset($vid['drip']) ? intval($vid['drip']) : 0;
                    $is_locked = ($days_enrolled < $required_drip_days);
                    
                    if ($is_locked) {
                        $days_left = $required_drip_days - $days_enrolled;
                        echo '<div class="cppm-item" style="opacity: 0.6; cursor: not-allowed; background:var(--bg-sec); display:flex; justify-content:space-between;">';
                        echo '<div><span style="margin-right:8px;">🔒</span>' . esc_html($vid['title']) . '</div>';
                        echo '<div style="font-size:12px; color:var(--text-sec); font-weight:bold;">In ' . $days_left . ' days</div>';
                        echo '</div>';
                    } else {
                ?>
                    <button type="button" class="cppm-item cppm-switch-trigger <?php echo ($index === $last_vid ? 'active' : ''); ?>" data-target="<?php echo $index; ?>" data-title="<?php echo esc_attr($vid['title']); ?>" data-completed="<?php echo $is_done ? 'true' : 'false'; ?>" id="nav-<?php echo $uid . '-' . $index; ?>">
                        <div id="dot-<?php echo $uid . '-' . $index; ?>" style="width:10px; height:10px; border-radius:50%; border:2px solid <?php echo ($is_done ? '#10b981' : 'var(--border)'); ?>; background:<?php echo ($is_done ? '#10b981' : 'transparent'); ?>; flex-shrink:0;"></div>
                        <span><?php echo esc_html($vid['title']); ?></span>
                    </button>
                <?php 
                    }
                endforeach; 
                ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}