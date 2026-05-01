<?php
/**
 * Core: Admin Post Types & Meta Boxes
 * Architecture: Modular / Asset Separated
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================================
// 1. REGISTER CUSTOM POST TYPES
// ==========================================
add_action( 'init', 'cppm_register_custom_post_types' );
function cppm_register_custom_post_types() {
    register_post_type( 'custom_playlist', array(
        'labels' => array( 'name' => 'Course Playlists', 'singular_name' => 'Playlist', 'add_new_item' => 'Add New Playlist' ),
        'public' => false, 'show_ui' => true, 'menu_position' => 20, 'menu_icon' => 'dashicons-video-alt3', 'supports' => array( 'title' ),
    ));

    register_post_type( 'custom_ebook', array(
        'labels' => array( 'name' => 'Secure E-Books', 'singular_name' => 'E-Book', 'add_new_item' => 'Add New E-Book' ),
        'public' => false, 'show_ui' => true, 'menu_position' => 21, 'menu_icon' => 'dashicons-book', 'supports' => array( 'title' ),
    ));
}

// ==========================================
// 2. ENQUEUE ADMIN ASSETS (MODULAR ARCHITECTURE)
// ==========================================
add_action( 'admin_enqueue_scripts', 'cppm_load_admin_scripts' );
function cppm_load_admin_scripts( $hook ) {
    global $post;

    // Only load these heavy scripts if we are on the post editor screen for our custom post types
    if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
        if ( 'custom_playlist' === $post->post_type || 'custom_ebook' === $post->post_type ) {
            
            // Enqueue native WP Media Library
            wp_enqueue_media();
            
            $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
            
            // Enqueue Custom CSS & JS
            wp_enqueue_style( 'cppm-admin-settings', $plugin_url . 'assets/css/admin-settings.css', array(), '1.0.0' );
            wp_enqueue_script( 'cppm-admin-settings-js', $plugin_url . 'assets/js/admin-settings.js', array('jquery'), '1.0.0', true );
        }
    }
}

// ==========================================
// 3. REGISTER META BOXES
// ==========================================
add_action( 'add_meta_boxes', 'cppm_add_meta_boxes' );
function cppm_add_meta_boxes() {
    add_meta_box( 'cppm_playlist_data', 'Playlist Videos & Security', 'cppm_playlist_meta_html', 'custom_playlist', 'normal', 'high' );
    add_meta_box( 'cppm_ebook_data', 'Secure E-Book Manager', 'cppm_ebook_meta_html', 'custom_ebook', 'normal', 'high' );
}

// ==========================================
// 4. HTML RENDERERS FOR META BOXES
// ==========================================

// 4.1 Video Playlist Meta Box
function cppm_playlist_meta_html( $post ) {
    $videos = get_post_meta( $post->ID, '_cppm_videos_array', true );
    $req_product = get_post_meta( $post->ID, '_cppm_required_product', true );
    if ( !is_array($videos) ) $videos = array();
    ?>
    <div style="margin-bottom: 20px; background: #f0f0f1; padding: 15px; border-left: 4px solid #2271b1;">
        <label style="font-weight:bold;">Link to WooCommerce Product ID (Required to access):</label><br>
        <input type="number" name="cppm_required_product" value="<?php echo esc_attr($req_product); ?>" style="width:100%; max-width:200px; padding:5px; margin-top:5px;" placeholder="e.g. 154">
        <p style="font-size:12px; color:#666;">If left blank, anyone can watch these videos.</p>
    </div>

    <button id="cppm-add-row" class="cppm-btn-add">+ Add Video</button>
    <div id="cppm-wrapper">
        <?php foreach ($videos as $video) : ?>
            <div class="cppm-row">
                <div class="cppm-row-col">
                    <label>Video Title</label><br>
                    <input type="text" name="cppm_titles[]" value="<?php echo esc_attr($video['title']); ?>" />
                </div>
                <div class="cppm-row-col">
                    <label>Video URL (MP4)</label><br>
                    <input type="url" name="cppm_urls[]" value="<?php echo esc_url($video['url']); ?>" />
                </div>
                <button class="cppm-btn-remove">X</button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

// 4.2 E-Book JSON Manager Meta Box
function cppm_ebook_meta_html( $post ) {
    $docs_json = get_post_meta( $post->ID, '_cppm_ebook_docs_json', true );
    ?>
    <p>Upload your PDFs here. Set a price greater than 0 to allow direct purchases. Free files (Price = 0) will be readable but not downloadable.</p>
    
    <button id="cppm-add-doc" class="cppm-btn-add">+ Add Document</button>
    <div id="cppm-docs-wrapper"></div>
    
    <input type="hidden" name="cppm_ebook_docs_json" id="cppm_ebook_docs_json" value="<?php echo esc_attr($docs_json); ?>">
    <?php
}

// ==========================================
// 5. SAVE POST DATA
// ==========================================
add_action( 'save_post', 'cppm_save_meta_data' );
function cppm_save_meta_data( $post_id ) {
    // Prevent autosave from overriding data
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

    // Save Video Playlist Data
    if ( isset($_POST['cppm_titles']) ) {
        $data = array();
        for ($i=0; $i < count($_POST['cppm_titles']); $i++) {
            if(!empty($_POST['cppm_titles'][$i])) { 
                $data[] = array(
                    'title' => sanitize_text_field($_POST['cppm_titles'][$i]), 
                    'url' => esc_url_raw($_POST['cppm_urls'][$i])
                ); 
            }
        }
        update_post_meta( $post_id, '_cppm_videos_array', $data );
    }
    
    if ( isset($_POST['cppm_required_product']) ) {
        update_post_meta( $post_id, '_cppm_required_product', sanitize_text_field($_POST['cppm_required_product']) );
    }

    // Save Two-Tier E-Book Library Data
    if ( isset($_POST['cppm_ebook_docs_json']) ) {
        // WordPress automatically handles slashing, so we unslash before decoding to verify, then save raw JSON string securely
        $json_string = wp_unslash($_POST['cppm_ebook_docs_json']);
        $decoded = json_decode($json_string);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            update_post_meta( $post_id, '_cppm_ebook_docs_json', wp_slash(wp_json_encode($decoded)) );
        }
    }
}