<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'course_playlist', 'cppm_render_playlist' );
function cppm_render_playlist( $atts ) {
    $atts = shortcode_atts( array( 'id' => '' ), $atts );
    if ( empty( $atts['id'] ) ) return 'Missing ID';

    $playlist_id = intval( $atts['id'] );
    $videos = get_post_meta( $playlist_id, '_cppm_videos_array', true );
    $prod_id = get_post_meta( $playlist_id, '_cppm_required_product', true );
    $current_user = wp_get_current_user();

    if ( ! is_user_logged_in() ) return '<div style="padding:40px; text-align:center; background:#fff3f3; border-radius:12px;"><h3 style="color:#d32f2f; margin-top:0;">Locked</h3><p>Please log in to access this course.</p></div>';
    if ( ! empty($prod_id) && function_exists('wc_customer_bought_product') ) {
        if ( ! wc_customer_bought_product( $current_user->user_email, $current_user->ID, $prod_id ) ) return '<div style="padding:40px; text-align:center; background:#fff8e1; border-radius:12px;"><h3 style="color:#f57f17; margin-top:0;">Enrollment Required</h3><p>Purchase required.</p></div>';
    }
    if ( empty($videos) ) return 'No modules found.';

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
    ?>
    <style>
        #playlist-<?php echo $uid; ?> { 
            --brand: <?php echo $ui_brand; ?>; 
            --active-bg: <?php echo $ui_active_bg; ?>; 
            --bg-main: #ffffff; --bg-sec: #f9fafb; --bg-hover: #f3f4f6;
            --text-main: #111827; --text-sec: #6b7280; --border: #e5e7eb;
            --input-bg: transparent;
            display: grid; grid-template-columns: minmax(0, 7fr) minmax(0, 3fr); gap: 30px; font-family: system-ui, sans-serif; margin-top:30px; transition: background 0.3s, color 0.3s; 
        }

        #playlist-<?php echo $uid; ?>.cppm-dark-mode {
            --bg-main: #111827; --bg-sec: #1f2937; --bg-hover: #374151;
            --text-main: #f9fafb; --text-sec: #9ca3af; --border: #374151;
            --active-bg: #374151; --input-bg: #1f2937;
        }

        #playlist-<?php echo $uid; ?>.cppm-theater-mode { grid-template-columns: 1fr; }
        #playlist-<?php echo $uid; ?>.cppm-theater-mode .cppm-sidebar { display: none; }
        
        .cppm-player-container { min-width: 0; position: sticky; top: 0px; position: relative; }
        .cppm-video-box { position: relative; }
        
        /* Updated Video Frame for Slides */
        .cppm-video-frame { background: #000; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 20px rgba(0,0,0,0.1); position: relative; transition:0.3s; border: 1px solid var(--border); display: flex; flex-direction: column; }
        .cppm-player-slide { width: 100%; aspect-ratio: 16 / 9; }
        .cppm-player-slide iframe, .cppm-player-slide video { width: 100%; height: 100%; border: none; }

        .cppm-auto-advance-toast { position: absolute; bottom: 20px; right: 20px; background: rgba(0,0,0,0.85); color: #fff; padding: 12px 20px; border-radius: 8px; font-weight: bold; font-size: 14px; display: flex; align-items: center; gap: 10px; z-index: 999; opacity: 0; transform: translateY(10px); transition: 0.3s ease; pointer-events: none; }
        .cppm-auto-advance-toast.show { opacity: 1; transform: translateY(0); }
        .cppm-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: cppm-spin 1s linear infinite; }
        @keyframes cppm-spin { to { transform: rotate(360deg); } }

        .cppm-sidebar { min-width: 0; background: var(--bg-main); border-radius: 12px; border: 1px solid var(--border); display: flex; flex-direction: column; max-height: calc(100vh - 40px); transition: 0.3s; }
        .cppm-progress-header { padding: 20px; border-bottom: 1px solid var(--border); }
        .cppm-bar-bg { background: var(--bg-sec); height: 8px; border-radius: 4px; overflow: hidden; margin-top:10px; border: 1px solid var(--border); }
        .cppm-bar-fill { background: var(--brand); height: 100%; transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
        
        .cppm-cert-btn { display:block; text-align:center; padding:12px; background:#10b981; color:#fff; text-decoration:none; font-weight:bold; border-radius:8px; margin-top:15px; transition:0.2s; box-shadow:0 4px 6px rgba(16,185,129,0.2); }
        .cppm-cert-btn:hover { background:#059669; transform:translateY(-2px); color:#fff; }

        .cppm-search-wrap { padding: 15px 20px; border-bottom: 1px solid var(--border); }
        .cppm-course-search { width: 100%; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-sec); color: var(--text-main); font-size: 14px; outline: none; transition: 0.2s; font-family: inherit; box-sizing: border-box; }
        .cppm-course-search:focus { border-color: var(--brand); background: var(--bg-main); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

        .cppm-list { list-style: none; padding: 10px; margin: 0; overflow-y: auto; }
        .cppm-item { width: 100%; text-align: left; padding: 12px 15px; cursor: pointer; background: transparent; border: none; border-radius: 8px; display: flex; align-items: center; gap: 12px; margin-bottom:4px; font-size:15px; color: var(--text-main); transition: 0.2s;}
        .cppm-item:hover { background: var(--bg-hover); }
        .cppm-item.active { background: var(--active-bg); color: var(--brand); font-weight: 600; border: 1px solid var(--border); }
        
        .cppm-header-actions { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 15px; color: var(--text-main); }
        .cppm-btn-group { display: flex; gap: 10px; }
        .cppm-action-btn { background: var(--brand); color: #fff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .cppm-action-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.15); }
        
        .cppm-utility-btn { background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px; cursor: pointer; color: var(--text-main); transition: 0.2s; display:flex; align-items:center; gap:5px; font-weight:bold; }
        .cppm-utility-btn:hover { background: var(--bg-hover); }

        .cppm-notes-area { margin-top: 30px; padding: 25px; background: var(--bg-main); border-radius: 12px; border: 1px solid var(--border); transition:0.3s; }
        .cppm-timestamp-link { transition: 0.2s; }

        .cppm-discussion-area { margin-top: 30px; padding: 30px; background: var(--bg-main); border-radius: 12px; border: 1px solid var(--border); box-sizing: border-box; transition:0.3s; }
        .cppm-yt-form-row { display: flex; gap: 16px; margin-bottom: 20px; align-items: flex-start; }
        .cppm-yt-avatar img { border-radius: 50%; width: 40px; height: 40px; object-fit: cover; }
        .cppm-yt-input-container { flex: 1; position: relative; min-width: 0; }
        .cppm-yt-textarea { width: 100%; border: none; border-bottom: 1px solid var(--border); padding: 5px 0; font-family: inherit; font-size: 15px; resize: none; overflow: hidden; background: var(--input-bg); outline: none; transition: 0.3s; min-height: 28px; line-height: 1.4; color: var(--text-main); box-sizing: border-box; }
        .cppm-yt-underline { position: absolute; bottom: 0; left: 50%; width: 0; height: 2px; background: var(--brand); transition: width 0.3s ease, left 0.3s ease; }
        .cppm-yt-textarea:focus + .cppm-yt-underline { width: 100%; left: 0; }
        
        .cppm-yt-form-actions { display: none; justify-content: flex-end; gap: 10px; margin-top: 10px; }
        .cppm-yt-form-actions.active { display: flex; }
        .cppm-yt-cancel { background: transparent; border: none; font-weight: 600; cursor: pointer; padding: 8px 16px; border-radius: 18px; color: var(--text-main); font-size:14px; }
        .cppm-yt-cancel:hover { background: var(--bg-hover); }
        .cppm-yt-submit { background: var(--bg-hover); color: var(--text-sec); border: none; padding: 8px 16px; border-radius: 18px; font-weight: 600; cursor: not-allowed; transition: 0.2s; font-size:14px; }
        
        .cppm-yt-comment { display: flex; gap: 16px; margin-bottom: 24px; animation: cppmFadeIn 0.4s ease forwards; }
        .cppm-yt-comment-header { margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        .cppm-yt-author { font-weight: 600; color: var(--text-main); font-size: 14px; }
        .cppm-yt-time { font-size: 12px; color: var(--text-sec); }
        .cppm-yt-text { font-size: 15px; color: var(--text-main); line-height: 1.5; margin-bottom: 8px; word-wrap: break-word; }
        .cppm-yt-actions-row { display: flex; align-items: center; color: var(--text-sec); font-size: 13px; font-weight: 600; }
        .cppm-yt-badge { background: var(--bg-sec); color: var(--text-sec); border: 1px solid var(--border); font-size: 11px; padding: 2px 8px; border-radius: 12px; font-weight: 600; margin-bottom: 8px; display: inline-block; }
        
        @keyframes cppmFadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 768px) { 
    /* Switch to a flex column layout on mobile */
    #playlist-<?php echo $uid; ?> { display: flex; flex-direction: column; gap: 0; } 
    
    /* "Unwrap" the left column container so we can reorder its children */
    .cppm-player-container { display: contents; }
    
    /* Force the new mobile stacking order */
    .cppm-video-box { order: 1; }
    .cppm-sidebar { order: 2; margin-top: 30px; }
    .cppm-notes-area { order: 3; margin-top: 30px; }
    .cppm-discussion-area { order: 4; margin-top: 30px; }
    
    /* THE FIX: Only hide Theater Mode on mobile. Keep Dark Mode and Timestamps! */
    .cppm-ux-theater { display: none !important; } 
}

        <?php echo $custom_css; ?>
    </style>

    <div id="playlist-<?php echo $uid; ?>" class="cppm-container" data-uid="<?php echo $uid; ?>" data-pid="<?php echo $playlist_id; ?>" data-total="<?php echo $total_vids; ?>">
        <input type="hidden" id="cppm-active-index-<?php echo $uid; ?>" value="<?php echo $last_vid; ?>">
        
        <div class="cppm-player-container">
            <div class="cppm-video-box">
                <div class="cppm-video-frame" id="cppm-video-frame-<?php echo $uid; ?>">
                    <?php foreach ( $videos as $index => $vid ) : ?>
                        <div class="cppm-player-slide" id="player-slide-<?php echo $uid . '-' . $index; ?>" style="display: <?php echo $index === $last_vid ? 'block' : 'none'; ?>;">
                            <?php 
                            $vid_val = trim($vid['url']);
                            if ( is_numeric($vid_val) ) {
                                // If the user typed a number, it's a Presto Video ID
                                echo do_shortcode('[presto_player id="' . esc_attr($vid_val) . '"]');
                            } else {
                                // If the user pasted a raw YouTube/Vimeo URL, use the native WordPress Smart Embedder
                                global $wp_embed;
                                echo $wp_embed->run_shortcode('[embed]' . esc_url($vid_val) . '[/embed]');
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
                if ( is_user_logged_in() ) { $get_comments_args['include_unapproved'] = array( get_current_user_id() ); } 
                $comments = get_comments( $get_comments_args );

                $current_user_avatar = get_avatar( get_current_user_id(), 40, '', '', array('style' => 'border-radius:50%;') );
                
                $comment_args = array(
                    'title_reply' => '<span style="font-size:1.3rem; font-weight:700; color:var(--text-main);">' . count($comments) . ' Comments</span>',
                    'title_reply_before' => '<h3 id="reply-title" class="comment-reply-title" style="margin-bottom:20px; margin-top:0;">',
                    'title_reply_after' => '</h3>',
                    'logged_in_as' => '',
                    'comment_notes_before' => '',
                    'class_submit' => 'cppm-yt-submit',
                    'label_submit' => 'Comment',
                    'submit_button' => '<div class="cppm-yt-form-actions"><button type="button" class="cppm-yt-cancel">Cancel</button><input name="%1$s" type="submit" id="%2$s" class="%3$s" value="%4$s" disabled /></div>',
                    'comment_field' => '
                        <div class="cppm-yt-form-row">
                            <div class="cppm-yt-avatar">' . $current_user_avatar . '</div>
                            <div class="cppm-yt-input-container">
                                <textarea id="comment" name="comment" rows="1" class="cppm-yt-textarea" placeholder="Add a comment..." required></textarea>
                                <div class="cppm-yt-underline"></div>
                            </div>
                        </div>',
                );
                comment_form($comment_args, get_the_ID()); 
                
                echo '<div id="cppm-comments-wrapper-' . $uid . '" style="margin-top:30px;">';
                if ( $comments ) {
                    foreach($comments as $c) {
                        $vid_index = get_comment_meta( $c->comment_ID, '_cppm_vid_index', true );
                        $badge = get_comment_meta( $c->comment_ID, '_cppm_vid_title', true );
                        $filter_attr = ($vid_index !== '') ? esc_attr($vid_index) : 'all';
                        
                        $is_instructor = user_can($c->user_id, 'manage_options');
                        $instructor_badge = $is_instructor ? '<span style="background:var(--text-main); color:var(--bg-main); font-size:10px; padding:2px 6px; border-radius:12px; margin-left:8px; font-weight:600;">Instructor</span>' : '';
                        
                        $display_style = ($filter_attr === 'all' || $filter_attr == $last_vid) ? 'flex' : 'none';

                        echo '<div class="cppm-yt-comment cppm-custom-comment" data-video-index="' . esc_attr($filter_attr) . '" style="display:' . $display_style . ';">';
                        echo '<div class="cppm-yt-avatar">' . get_avatar( $c, 40, '', '', array('style' => 'border-radius:50%;') ) . '</div>';
                        echo '<div class="cppm-yt-comment-body">';
                        echo '<div class="cppm-yt-comment-header"><span class="cppm-yt-author">' . esc_html($c->comment_author) . $instructor_badge . '</span><span class="cppm-yt-time">' . date('M j, Y', strtotime($c->comment_date)) . '</span></div>';
                        if ($badge) { echo '<div class="cppm-yt-badge">📍 ' . esc_html($badge) . '</div>'; }
                        $content = apply_filters('comment_text', $c->comment_content, $c);
                        echo '<div class="cppm-yt-text">' . wp_kses_post($content) . '</div>';
                        echo '<div class="cppm-yt-actions-row"><span style="cursor:pointer;">👍</span><span style="cursor:pointer; margin-left:8px;">👎</span><span style="cursor:pointer; margin-left:16px; font-weight:600;">Reply</span></div>';
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
                // Helper Function defined earlier for Drip Days Check
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

add_action('wp_footer', 'cppm_inject_global_javascript', 99);
function cppm_inject_global_javascript() {
    ?>
    <script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
        
        var ajaxUrl = "<?php echo esc_url( admin_url('admin-ajax.php') ); ?>";

        var isDark = localStorage.getItem('cppm_theme') === 'dark';
        var containers = document.querySelectorAll('.cppm-container');
        containers.forEach(function(wrap) {
            var uid = wrap.getAttribute('data-uid');
            renderBookmarks(uid);
            if (isDark) {
                wrap.classList.add('cppm-dark-mode');
                var btn = wrap.querySelector('.cppm-ux-darkmode');
                if(btn) btn.innerText = '☀️';
            }
        });

        function filterComments(uid, target) {
            var wrap = document.getElementById('playlist-' + uid);
            if(!wrap) return;
            wrap.querySelectorAll('.cppm-custom-comment').forEach(function(c) {
                var cIdx = c.getAttribute('data-video-index');
                c.style.display = (cIdx == target || cIdx === 'all') ? 'flex' : 'none'; 
            });
        }

        function renderBookmarks(uid) {
            var notesArea = document.getElementById('cppm-notes-input-' + uid);
            var bookmarksContainer = document.getElementById('cppm-bookmarks-' + uid);
            if(!notesArea || !bookmarksContainer) return;
            
            var text = notesArea.value;
            var regex = /\[(\d{1,2}):(\d{2})\]/g;
            var match;
            var stamps = [];
            while ((match = regex.exec(text)) !== null) { 
                if (!stamps.includes(match[0])) stamps.push(match[0]); 
            }
            var html = '';
            stamps.forEach(function(stamp) {
                var timeStr = stamp.replace('[', '').replace(']', '');
                var parts = timeStr.split(':');
                var totalSecs = parseInt(parts[0]) * 60 + parseInt(parts[1]);
                html += '<button type="button" class="cppm-timestamp-link" data-seek="'+totalSecs+'" style="background:var(--bg-hover); color:var(--brand); border:1px solid var(--border); padding:6px 12px; border-radius:6px; font-weight:600; cursor:pointer; margin-right:10px; margin-bottom:10px;">⏱ '+timeStr+'</button>';
            });
            if (html !== '') {
                bookmarksContainer.innerHTML = '<div style="margin-top:20px; padding-top:20px; border-top:1px solid var(--border);"><strong style="font-size:0.85rem; color:var(--text-sec); display:block; margin-bottom:12px;">Quick Jump Bookmarks:</strong>' + html + '</div>';
            } else {
                bookmarksContainer.innerHTML = '';
            }
        }

        document.body.addEventListener('input', function(e) {
            if (e.target.matches('.cppm-course-search')) {
                var term = e.target.value.toLowerCase();
                var wrap = e.target.closest('.cppm-container');
                if(!wrap) return;
                wrap.querySelectorAll('.cppm-item').forEach(function(item) {
                    var titleText = item.innerText.toLowerCase();
                    item.style.display = titleText.includes(term) ? 'flex' : 'none';
                });
            }
            
            if (e.target.matches('.cppm-yt-textarea')) {
                e.target.style.height = 'auto';
                e.target.style.height = (e.target.scrollHeight) + 'px';
                
                var form = e.target.closest('form');
                var submitBtn = form.querySelector('.cppm-yt-submit');
                if(submitBtn) {
                    if(e.target.value.trim().length > 0) {
                        submitBtn.style.background = 'var(--brand)';
                        submitBtn.style.color = '#fff';
                        submitBtn.style.cursor = 'pointer';
                        submitBtn.disabled = false;
                    } else {
                        submitBtn.style.background = 'var(--bg-hover)';
                        submitBtn.style.color = 'var(--text-sec)';
                        submitBtn.style.cursor = 'not-allowed';
                        submitBtn.disabled = true;
                    }
                }
            }
        });

        document.body.addEventListener('click', function(e) {
            
            var darkBtn = e.target.closest('.cppm-ux-darkmode');
            if (darkBtn) {
                e.preventDefault();
                var wrap = darkBtn.closest('.cppm-container');
                if (wrap) {
                    var isNowDark = wrap.classList.toggle('cppm-dark-mode');
                    darkBtn.innerText = isNowDark ? '☀️' : '🌙';
                    localStorage.setItem('cppm_theme', isNowDark ? 'dark' : 'light');
                }
            }

            var switchBtn = e.target.closest('.cppm-switch-trigger');
            if (switchBtn) {
                e.preventDefault();
                var wrap = switchBtn.closest('.cppm-container');
                if(!wrap) return;
                var uid = wrap.getAttribute('data-uid');
                var pid = wrap.getAttribute('data-pid');
                var target = switchBtn.getAttribute('data-target');
                var title = switchBtn.getAttribute('data-title');

                wrap.querySelectorAll('.cppm-switch-trigger').forEach(function(b){ b.classList.remove('active'); });
                switchBtn.classList.add('active');

                var doneBtn = document.getElementById('done-btn-' + uid);
                var isDone = switchBtn.getAttribute('data-completed') === 'true';
                if(doneBtn) {
                    doneBtn.style.display = isDone ? 'none' : 'block';
                    doneBtn.setAttribute('data-idx', target);
                }

                var titleEl = document.getElementById('cppm-active-title-' + uid);
                var labelEl = document.getElementById('cppm-active-label-' + uid);
                if(titleEl) titleEl.innerText = title;
                if(labelEl) labelEl.innerText = 'Lesson ' + (parseInt(target) + 1);

                var idxField = document.getElementById('cppm-active-index-' + uid);
                if(idxField) idxField.value = target;
                filterComments(uid, target);

                // --- NEW FAST-SWITCHING LOGIC (NO AJAX) ---
                var frame = document.getElementById('cppm-video-frame-' + uid);
                if(frame) {
                    // Hide all slides
                    var slides = frame.querySelectorAll('.cppm-player-slide');
                    slides.forEach(function(slide) { slide.style.display = 'none'; });
                    
                    // Show only the clicked slide
                    var targetSlide = document.getElementById('player-slide-' + uid + '-' + target);
                    if(targetSlide) targetSlide.style.display = 'block';
                }

                // Save user's resume point silently
                var fdSave = new FormData();
                fdSave.append("action", "cppm_save_last_video");
                fdSave.append("playlist_id", pid);
                fdSave.append("video_index", target);
                fetch(ajaxUrl, { method: "POST", body: fdSave });
            }

            var theaterBtn = e.target.closest('.cppm-ux-theater');
            if (theaterBtn) {
                e.preventDefault();
                var wrap = theaterBtn.closest('.cppm-container');
                if(wrap) wrap.classList.toggle('cppm-theater-mode');
            }

            var doneBtn = e.target.closest('.cppm-ux-done');
            if (doneBtn) {
                e.preventDefault();
                var wrap = doneBtn.closest('.cppm-container');
                var uid = wrap.getAttribute('data-uid');
                var pid = doneBtn.getAttribute('data-pid');
                var idx = doneBtn.getAttribute('data-idx');
                
                doneBtn.style.display = 'none';
                
                var navBtn = document.getElementById("nav-" + uid + "-" + idx);
                if(navBtn) navBtn.setAttribute('data-completed', 'true');

                var dot = document.getElementById("dot-" + uid + "-" + idx);
                if(dot) { dot.style.borderColor = '#10b981'; dot.style.background = '#10b981'; }
                
                var cLabel = document.getElementById("count-" + uid);
                if(cLabel && wrap) {
                    var cur = parseInt(cLabel.innerText) + 1;
                    cLabel.innerText = cur;
                    var total = parseInt(wrap.getAttribute('data-total'));
                    var fill = document.getElementById("fill-" + uid);
                    if(fill) fill.style.width = ((cur / total) * 100) + "%";

                    if (cur === total) {
                        var certBtn = document.getElementById("cppm-cert-btn-" + uid);
                        if (certBtn) certBtn.style.display = 'block';

                        var overlay = document.createElement('div');
                        overlay.innerHTML = '<div style="position:fixed; top:0; left:0; width:100%; height:100%; pointer-events:none; z-index:99999; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.85); backdrop-filter:blur(5px); animation: cppmFadeInOut 4s forwards;"><div style="text-align:center; transform:scale(0.8); animation: cppmPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;"><h1 style="font-size:5rem; margin:0;">🎉</h1><h2 style="color:#10b981; font-size:2.5rem; margin:10px 0; font-weight:800;">Course Completed!</h2><p style="color:#4b5563; font-size:1.2rem; font-weight:600;">Amazing work. You reached 100%.</p></div></div><style>@keyframes cppmFadeInOut { 0%{opacity:0;} 10%{opacity:1;} 80%{opacity:1;} 100%{opacity:0;} } @keyframes cppmPop { to {transform:scale(1);} }</style>';
                        document.body.appendChild(overlay);
                        setTimeout(function(){ overlay.remove(); }, 4000);
                    }
                }
                
                var fd = new FormData();
                fd.append("action", "cppm_mark_completed");
                fd.append("playlist_id", pid);
                fd.append("video_index", idx);
                fetch(ajaxUrl, { method: "POST", body: fd });
            }

            var tsLink = e.target.closest('.cppm-timestamp-link');
            if (tsLink) {
                e.preventDefault();
                var wrap = tsLink.closest('.cppm-container');
                var targetSeconds = parseInt(tsLink.getAttribute('data-seek'));
                var frame = wrap.querySelector('.cppm-video-frame');
                if (frame) {
                    var player = frame.querySelector('presto-player');
                    if (player && player.player && typeof player.player.play === 'function') {
                        player.player.currentTime = targetSeconds;
                        player.player.play();
                        frame.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            }

            var insertBtn = e.target.closest('.cppm-ux-timestamp');
            if (insertBtn) {
                e.preventDefault();
                var wrap = insertBtn.closest('.cppm-container');
                var uid = wrap.getAttribute('data-uid');
                var notesArea = document.getElementById('cppm-notes-input-' + uid);
                
                var frame = wrap.querySelector('.cppm-video-frame');
                if (frame && notesArea) {
                    var player = frame.querySelector('presto-player');
                    if (player && player.player) {
                        var time = Math.floor(player.player.currentTime || 0);
                        var min = Math.floor(time / 60);
                        var sec = time % 60;
                        var ts = '[' + min + ':' + (sec < 10 ? '0' : '') + sec + '] ';
                        
                        var cursorPos = notesArea.selectionStart || notesArea.value.length;
                        var textBefore = notesArea.value.substring(0, cursorPos);
                        var textAfter = notesArea.value.substring(cursorPos);
                        notesArea.value = textBefore + ts + textAfter;
                        notesArea.focus();
                    } else {
                        alert("Start the video first to grab a timestamp.");
                    }
                }
            }

            var saveNotesBtn = e.target.closest('.cppm-ux-save-notes');
            if (saveNotesBtn) {
                e.preventDefault();
                var wrap = saveNotesBtn.closest('.cppm-container');
                var uid = wrap.getAttribute('data-uid');
                var pid = wrap.getAttribute('data-pid');
                var notesArea = document.getElementById('cppm-notes-input-' + uid);
                
                var origText = saveNotesBtn.innerText;
                saveNotesBtn.innerText = "Saving...";
                saveNotesBtn.disabled = true;

                var fd = new FormData();
                fd.append('action', 'cppm_save_notes');
                fd.append('playlist_id', pid);
                fd.append('notes', notesArea.value);

                fetch(ajaxUrl, { method: "POST", body: fd })
                .then(function(res){ return res.json(); })
                .then(function(data){
                    saveNotesBtn.innerText = origText;
                    saveNotesBtn.disabled = false;
                    renderBookmarks(uid);
                    
                    var status = document.getElementById('cppm-notes-status-' + uid);
                    if (status) {
                        status.style.opacity = '1';
                        setTimeout(function(){ status.style.opacity = '0'; }, 3000);
                    }
                });
            }
            
            if (e.target.matches('.cppm-yt-cancel')) {
                e.preventDefault();
                var form = e.target.closest('form');
                var txt = form.querySelector('.cppm-yt-textarea');
                var btn = form.querySelector('.cppm-yt-submit');
                if(txt) { txt.value = ''; txt.style.height = 'auto'; }
                if(btn) { btn.style.background = 'var(--bg-hover)'; btn.style.color = 'var(--text-sec)'; btn.style.cursor = 'not-allowed'; btn.disabled = true; }
                var actions = form.querySelector('.cppm-yt-form-actions');
                if(actions) actions.classList.remove('active');
            }
        });

        // AJAX Comments
        document.body.addEventListener('submit', function(e) {
            var form = e.target;
            if (form.matches('.cppm-container form.comment-form') || form.matches('.cppm-container #commentform') || form.closest('.cppm-discussion-area')) {
                e.preventDefault();
                var wrap = form.closest('.cppm-container');
                var uid = wrap.getAttribute('data-uid');
                
                var activeIdx = document.getElementById('cppm-active-index-' + uid).value;
                var activeBtn = document.getElementById('nav-' + uid + '-' + activeIdx);
                var activeTitle = activeBtn ? activeBtn.getAttribute('data-title') : '';

                var submitBtn = form.querySelector('input[type="submit"], button[type="submit"]');
                var origText = "Comment";
                if(submitBtn) {
                    origText = submitBtn.value || submitBtn.innerText;
                    if(submitBtn.value) submitBtn.value = "Posting..."; else submitBtn.innerText = "Posting...";
                    submitBtn.disabled = true;
                }

                var fd = new FormData(form);
                fd.append('action', 'cppm_submit_comment');
                fd.set('cppm_vid_index', activeIdx);
                fd.set('cppm_vid_title', activeTitle);
                
                fetch(ajaxUrl, { method: "POST", body: fd })
                .then(function(res){ return res.json(); })
                .then(function(data){
                    if(submitBtn) {
                        if(submitBtn.value) submitBtn.value = origText; else submitBtn.innerText = origText;
                        submitBtn.style.background = 'var(--bg-hover)';
                        submitBtn.style.color = 'var(--text-sec)';
                        submitBtn.style.cursor = 'not-allowed';
                        submitBtn.disabled = true;
                    }
                    if(data.success) {
                        var wrapList = document.getElementById('cppm-comments-wrapper-' + uid);
                        var noMsg = document.getElementById('cppm-no-comments-msg-' + uid);
                        if(noMsg) noMsg.remove();
                        if(wrapList) wrapList.insertAdjacentHTML('afterbegin', data.data);
                        var txt = form.querySelector('.cppm-yt-textarea');
                        if(txt) { txt.value = ''; txt.style.height = 'auto'; }
                        var actions = form.querySelector('.cppm-yt-form-actions');
                        if(actions) actions.classList.remove('active');
                    } else {
                        alert("Notice: " + data.data);
                    }
                }).catch(function(err){
                    if(submitBtn) {
                        if(submitBtn.value) submitBtn.value = origText; else submitBtn.innerText = origText;
                        submitBtn.disabled = false;
                    }
                });
            }
        });

        // Smart Auto-Advance
        document.body.addEventListener('ended', function(e) {
            var wrap = e.target.closest('.cppm-container');
            if(!wrap) return;
            
            var frame = wrap.querySelector('.cppm-video-frame');
            if (!frame || !frame.contains(e.target)) return; 

            var uid = wrap.getAttribute('data-uid');
            var totalVideos = parseInt(wrap.getAttribute('data-total'));
            var currentIndex = parseInt(document.getElementById('cppm-active-index-' + uid).value);

            var doneBtn = document.getElementById("done-btn-" + uid);
            if(doneBtn && doneBtn.style.display !== 'none') {
                doneBtn.click(); 
            }

            var nextIndex = currentIndex + 1;
            if (nextIndex < totalVideos) {
                var toast = document.getElementById("cppm-toast-" + uid);
                if (toast) toast.classList.add('show');
                setTimeout(function() {
                    if (toast) toast.classList.remove('show');
                    var nextBtn = document.getElementById("nav-" + uid + "-" + nextIndex);
                    if(nextBtn) nextBtn.click(); 
                }, 3000);
            }
        }, true); 

    });
    </script>
    <?php
}