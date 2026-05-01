<?php
/**
 * Core: Instructor Portal (Web-Book & Video Playlist Editor)
 * Architecture: Modular / Asset Separated
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. FRONTEND INSTRUCTOR PORTAL SHORTCODE
// ==========================================
add_shortcode('cppm_instructor_portal', 'cppm_render_instructor_portal');

function cppm_render_instructor_portal() {
    
    // SECURITY GATEWAY: ONLY ADMINS AND SHOP MANAGERS ALLOWED
    if ( ! is_user_logged_in() ) {
        return '<div style="text-align:center; padding: 50px; background: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 600px; margin: 40px auto;">' . 
               '<h3 style="color: #ef4444; margin-top:0;">Access Denied</h3>' . 
               '<p style="color: #64748b;">You must be logged in to view this portal.</p>' . 
               '<a href="' . (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url()) . '" style="background:#2874f0; color:#fff; padding:10px 20px; border-radius:6px; text-decoration:none; display:inline-block; margin-top:15px; font-weight:bold;">Log In</a></div>';
    }

    if ( ! ( current_user_can('administrator') || current_user_can('shop_manager') ) ) {
        return '<div style="text-align:center; padding: 50px;"><h3>Unauthorized Access</h3><p>You do not have permission to access the Instructor Portal.</p></div>';
    }

    // ---------------------------------------------------------
    // ASSET ENQUEUE ENGINE (ONLY LOADS ON THIS SPECIFIC PAGE)
    // ---------------------------------------------------------
    $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
    
    // 1. Enqueue ABCjs Library (From CDN)
    wp_enqueue_script( 'abcjs-basic', 'https://cdnjs.cloudflare.com/ajax/libs/abcjs/6.0.0/abcjs-basic-min.js', array(), '6.0.0', true );
    
    // 2. Enqueue our extracted CSS
    wp_enqueue_style( 'cppm-portal-css', $plugin_url . 'assets/css/instructor-portal.css', array(), '1.0.0' );
    
    // 3. Enqueue our extracted JS
    wp_enqueue_script( 'cppm-portal-js', $plugin_url . 'assets/js/instructor-portal.js', array('abcjs-basic', 'jquery'), '1.0.0', true );

    // 4. Fetch the existing data to pass to JS
    $post_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $existing_json = '[]';
    
    if ( $post_id ) {
        $meta = get_post_meta($post_id, '_cppm_web_book_json', true);
        if ( !empty($meta) ) {
            $existing_json = wp_unslash($meta);
        }
    }

    // 5. Securely localize the PHP data into a JS object for the browser
    wp_localize_script( 'cppm-portal-js', 'cppmPortalData', array(
        'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
        'postId'   => $post_id,
        'bookData' => json_decode( $existing_json )
    ));

    // ---------------------------------------------------------
    // HTML RENDERER
    // ---------------------------------------------------------
    ob_start(); 
    ?>
    
    <div class="cppm-portal-wrapper">
        <div class="cppm-portal-sidebar">
            <div class="cppm-sidebar-header">
                <h3>Course Modules</h3>
                <button id="cppm-add-chapter" class="cppm-btn-secondary" style="padding: 5px 10px; font-size: 12px;">+ Chapter</button>
            </div>
            <div id="cppm-chapter-list">
                </div>
        </div>
        
        <div class="cppm-portal-main">
            <div class="cppm-portal-toolbar">
                <button id="cppm-preview-btn" class="cppm-btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 5px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    Preview Page
                </button>
                <button id="cppm-save-btn" class="cppm-btn-primary">Save Course</button>
            </div>
            <div class="cppm-editor-area">
                <input type="text" id="cppm-page-title" placeholder="Page Title (e.g., Introduction to Bugle)">
                <textarea id="cppm-page-content" placeholder="Enter text or ABCjs notation here...&#10;&#10;Example Notation:&#10;X:1&#10;T:Scale&#10;K:C&#10;CDEFGABc"></textarea>
                
                <div id="cppm-abc-preview" class="cppm-live-notation" style="display:none;"></div>
            </div>
        </div>
    </div>

    <div id="cppm-preview-modal" class="cppm-modal">
        <div class="cppm-modal-content">
            <span class="cppm-close">&times;</span>
            <div id="cppm-modal-body"></div>
        </div>
    </div>

    <?php
    return ob_get_clean();
}

// ==========================================
// 2. AJAX HANDLER: SAVE PORTAL DATA
// ==========================================
add_action('wp_ajax_cppm_save_portal_data', 'cppm_save_portal_data_ajax');
function cppm_save_portal_data_ajax() {
    // Security checks
    if ( ! is_user_logged_in() || ! (current_user_can('administrator') || current_user_can('shop_manager')) ) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $book_data = isset($_POST['book_data']) ? wp_unslash($_POST['book_data']) : '[]';

    if ( $post_id ) {
        // Update existing post
        update_post_meta( $post_id, '_cppm_web_book_json', wp_slash($book_data) );
    } else {
        // Create new post
        $new_post_id = wp_insert_post(array(
            'post_title'  => 'New Course Portal Draft',
            'post_type'   => 'custom_playlist', // Or custom_ebook depending on your setup
            'post_status' => 'publish'
        ));
        update_post_meta( $new_post_id, '_cppm_web_book_json', wp_slash($book_data) );
    }

    wp_send_json_success(array('message' => 'Saved securely.'));
}