<?php
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
// 1.5. ENQUEUE WORDPRESS MEDIA LIBRARY
// ==========================================
add_action( 'admin_enqueue_scripts', 'cppm_load_admin_scripts' );
function cppm_load_admin_scripts($hook) {
    global $post_type;
    if ( 'custom_ebook' == $post_type ) {
        wp_enqueue_media();
    }
}

// ==========================================
// 2. ADMIN MENU & UI SETTINGS
// ==========================================
add_action( 'admin_menu', 'cppm_add_admin_pages' );
function cppm_add_admin_pages() {
    add_submenu_page( 'edit.php?post_type=custom_playlist', 'UI Customization', 'UI Settings', 'manage_options', 'cppm_ui_settings', 'cppm_render_settings_page' );
    add_submenu_page( 'edit.php?post_type=custom_playlist', 'Student Analytics', 'Analytics Dashboard', 'manage_options', 'cppm_analytics', 'cppm_render_analytics_page' );
}

function cppm_render_settings_page() {
    if ( isset($_POST['cppm_save_settings']) ) {
        update_option('cppm_ui_btn_color', sanitize_text_field($_POST['cppm_ui_btn_color']));
        update_option('cppm_ui_active_bg', sanitize_text_field($_POST['cppm_ui_active_bg']));
        update_option('cppm_custom_css', wp_strip_all_tags( wp_unslash( $_POST['cppm_custom_css'] ) ) );
        echo '<div class="notice notice-success is-dismissible"><p>Settings Saved!</p></div>';
    }
    $btn_color = get_option('cppm_ui_btn_color', '#2563eb');
    $active_bg = get_option('cppm_ui_active_bg', '#f0f7ff');
    $custom_css = get_option('cppm_custom_css', '');
    ?>
    <div class="wrap">
        <h1 style="margin-bottom: 20px;">Premium UI Settings</h1>
        <form method="post" style="background:#fff; padding:30px; max-width:800px; border:1px solid #ccd0d4; border-radius:8px;">
            <table class="form-table">
                <tr><th>Brand Primary Color</th><td><input type="color" name="cppm_ui_btn_color" value="<?php echo esc_attr($btn_color); ?>"></td></tr>
                <tr><th>Active Highlight (Light)</th><td><input type="color" name="cppm_ui_active_bg" value="<?php echo esc_attr($active_bg); ?>"></td></tr>
                <tr><th>Custom CSS</th><td><textarea name="cppm_custom_css" rows="8" style="width:100%; font-family:monospace;"><?php echo esc_textarea($custom_css); ?></textarea></td></tr>
            </table>
            <p class="submit"><input type="submit" name="cppm_save_settings" class="button button-primary" value="Apply UI Changes"></p>
        </form>
    </div>
    <?php
}

function cppm_render_analytics_page() {
    echo '<div class="wrap"><h1>Student Progress Analytics</h1><p>Student data goes here.</p></div>';
}

// ==========================================
// 3. WOOCOMMERCE: STRATEGY 2 ORDER METABOX
// ==========================================
add_action( 'woocommerce_admin_order_data_after_order_details', 'cppm_add_pdf_download_checkbox' );
function cppm_add_pdf_download_checkbox( $order ) {
    $granted = $order->get_meta( '_cppm_grant_pdf_downloads' );
    echo '<br class="clear" />';
    echo '<h3>📖 Custom LMS Permissions</h3>';
    woocommerce_wp_checkbox( array(
        'id'            => '_cppm_grant_pdf_downloads',
        'label'         => 'Grant PDF Download Rights',
        'description'   => 'Override security: Allow this specific student to download the PDFs associated with this order.',
        'value'         => $granted ? 'yes' : 'no',
        'wrapper_class' => 'form-field-wide'
    ) );
}

add_action( 'woocommerce_process_shop_order_meta', 'cppm_save_pdf_download_checkbox' );
function cppm_save_pdf_download_checkbox( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( isset( $_POST['_cppm_grant_pdf_downloads'] ) ) {
        $order->update_meta_data( '_cppm_grant_pdf_downloads', 'yes' );
    } else {
        $order->update_meta_data( '_cppm_grant_pdf_downloads', 'no' );
    }
    $order->save();
}

// ==========================================
// 4. COURSE BUILDER META BOXES
// ==========================================
add_action( 'add_meta_boxes', 'cppm_add_meta_boxes' );
function cppm_add_meta_boxes() { 
    add_meta_box( 'cppm_settings', 'Video Playlist Builder', 'cppm_video_meta_html', 'custom_playlist', 'normal', 'high' ); 
    add_meta_box( 'cppm_ebook_settings', 'Two-Tier Document Library Builder', 'cppm_ebook_meta_html', 'custom_ebook', 'normal', 'high' ); 
}

// --- A. VIDEO PLAYLIST BUILDER ---
function cppm_video_meta_html( $post ) {
    $videos = get_post_meta( $post->ID, '_cppm_videos_array', true );
    $product_id = get_post_meta( $post->ID, '_cppm_required_product', true );
    if ( empty( $videos ) || ! is_array( $videos ) ) { $videos = array( array('title' => '', 'url' => '') ); }
    ?>
    <div id="cppm-admin">
        <div style="background:#fff; padding:15px; border:1px solid #ccd0d4; border-radius:8px; margin-bottom:20px;">
            <p style="margin-top:0;"><strong>Link to WooCommerce Product:</strong></p>
            <select name="cppm_required_product" style="width:100%; max-width:500px; padding:6px;">
                <option value="">-- Select a Course Product --</option>
                <?php
                if ( function_exists('wc_get_products') ) {
                    $products = wc_get_products( array( 'status' => 'publish', 'limit' => -1 ) );
                    foreach ( $products as $product ) {
                        $selected = selected( $product_id, $product->get_id(), false );
                        echo '<option value="' . esc_attr( $product->get_id() ) . '" ' . $selected . '>' . esc_html( $product->get_name() ) . '</option>';
                    }
                }
                ?>
            </select>
        </div>
        <div id="cppm-repeater">
            <?php foreach ( $videos as $index => $vid ) : ?>
                <div class="cppm-row" style="margin-bottom:8px; display:flex; gap:10px; background:#f4f4f4; padding:10px; border-radius:8px;">
                    <input type="text" name="cppm_titles[]" value="<?php echo esc_attr($vid['title']); ?>" placeholder="Title" style="flex:1;">
                    <input type="text" name="cppm_urls[]" value="<?php echo esc_attr($vid['url']); ?>" placeholder="YouTube URL" style="flex:2;">
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="cppm-add" class="button button-secondary">+ Add Module</button>
    </div>
    <script>
        document.getElementById('cppm-add').addEventListener('click', function() {
            var container = document.getElementById('cppm-repeater');
            var row = container.children[0].cloneNode(true);
            row.querySelectorAll('input').forEach(i => i.value = '');
            container.appendChild(row);
        });
    </script>
    <?php
}

// --- B. TWO-TIER E-BOOK BUILDER (JSON STATE) ---
function cppm_ebook_meta_html( $post ) {
    $product_id = get_post_meta( $post->ID, '_cppm_ebook_required_product', true );
    $docs_json = get_post_meta( $post->ID, '_cppm_ebook_docs_json', true );
    
    // Default Empty State
    if ( empty( $docs_json ) ) { 
        $docs_json = '[{"title": "", "url": "", "chapters": []}]'; 
    }
    ?>
    <div id="cppm-ebook-admin">
        <div style="background:#fff; padding:15px; border:1px solid #ccd0d4; border-radius:8px; margin-bottom:20px;">
            <p style="margin-top:0;"><strong>Link to WooCommerce Product:</strong></p>
            <select name="cppm_ebook_required_product" style="width:100%; max-width:500px; padding:6px;">
                <option value="">-- Select a Course Product --</option>
                <?php
                if ( function_exists('wc_get_products') ) {
                    $products = wc_get_products( array( 'status' => 'publish', 'limit' => -1 ) );
                    foreach ( $products as $product ) {
                        $selected = selected( $product_id, $product->get_id(), false );
                        echo '<option value="' . esc_attr( $product->get_id() ) . '" ' . $selected . '>' . esc_html( $product->get_name() ) . '</option>';
                    }
                }
                ?>
            </select>
        </div>

        <input type="hidden" name="cppm_ebook_docs_json" id="cppm_ebook_docs_json" value="<?php echo esc_attr($docs_json); ?>">
        
        <div id="cppm_docs_container"></div>
        <button type="button" id="cppm_add_doc_btn" class="button button-primary" style="margin-top:15px;">+ Add New PDF Document</button>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const jsonInput = document.getElementById('cppm_ebook_docs_json');
        const container = document.getElementById('cppm_docs_container');
        let state = JSON.parse(jsonInput.value);

        function render() {
            container.innerHTML = '';
            state.forEach((doc, docIndex) => {
                let docHTML = `
                <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:15px; margin-bottom:15px; position:relative;">
                    <button type="button" class="button cppm-remove-doc" data-didx="${docIndex}" style="position:absolute; top:15px; right:15px; color:#dc2626; border-color:#dc2626;">Remove Doc</button>
                    <h3 style="margin-top:0;">Document ${docIndex + 1}</h3>
                    
                    <div style="display:flex; gap:10px; margin-bottom:15px;">
                        <input type="text" class="cppm-doc-title" data-didx="${docIndex}" value="${doc.title}" placeholder="Document Title (e.g. Mathematics Notes)" style="flex:1;">
                        <input type="text" class="cppm-doc-url" data-didx="${docIndex}" value="${doc.url}" placeholder="Secure PDF URL" style="flex:2;" id="pdf_url_${docIndex}">
                        <button type="button" class="button cppm-media-btn" data-didx="${docIndex}">Select PDF</button>
                    </div>

                    <div style="background:#ffffff; padding:15px; border-radius:6px; border:1px solid #e2e8f0;">
                        <h4 style="margin-top:0; color:#64748b;">Chapter Index (Optional)</h4>
                        <div id="chapters_${docIndex}">`;
                        
                doc.chapters.forEach((chap, chapIndex) => {
                    docHTML += `
                            <div style="display:flex; gap:10px; margin-bottom:8px;">
                                <input type="text" class="cppm-chap-title" data-didx="${docIndex}" data-cidx="${chapIndex}" value="${chap.title}" placeholder="Chapter Title" style="flex:2;">
                                <input type="number" class="cppm-chap-page" data-didx="${docIndex}" data-cidx="${chapIndex}" value="${chap.page}" placeholder="Page #" style="flex:1;">
                                <button type="button" class="button cppm-remove-chap" data-didx="${docIndex}" data-cidx="${chapIndex}">X</button>
                            </div>`;
                });

                docHTML += `
                        </div>
                        <button type="button" class="button button-secondary cppm-add-chap" data-didx="${docIndex}" style="margin-top:10px;">+ Add Chapter</button>
                    </div>
                </div>`;
                
                container.insertAdjacentHTML('beforeend', docHTML);
            });
            jsonInput.value = JSON.stringify(state);
        }

        // Event Delegation for the entire builder
        container.addEventListener('input', function(e) {
            let dIdx = e.target.getAttribute('data-didx');
            let cIdx = e.target.getAttribute('data-cidx');
            
            if (e.target.classList.contains('cppm-doc-title')) state[dIdx].title = e.target.value;
            if (e.target.classList.contains('cppm-doc-url')) state[dIdx].url = e.target.value;
            if (e.target.classList.contains('cppm-chap-title')) state[dIdx].chapters[cIdx].title = e.target.value;
            if (e.target.classList.contains('cppm-chap-page')) state[dIdx].chapters[cIdx].page = e.target.value;
            
            jsonInput.value = JSON.stringify(state);
        });

        container.addEventListener('click', function(e) {
            let dIdx = e.target.getAttribute('data-didx');
            
            if (e.target.classList.contains('cppm-add-chap')) {
                state[dIdx].chapters.push({title: '', page: ''});
                render();
            }
            if (e.target.classList.contains('cppm-remove-chap')) {
                let cIdx = e.target.getAttribute('data-cidx');
                state[dIdx].chapters.splice(cIdx, 1);
                render();
            }
            if (e.target.classList.contains('cppm-remove-doc')) {
                if(confirm('Remove this entire document?')) {
                    state.splice(dIdx, 1);
                    render();
                }
            }
            if (e.target.classList.contains('cppm-media-btn')) {
                e.preventDefault();
                let pdfUploader = wp.media({ title: 'Select PDF', button: { text: 'Use PDF' }, multiple: false, library: { type: 'application/pdf' } });
                pdfUploader.on('select', function() {
                    let attachment = pdfUploader.state().get('selection').first().toJSON();
                    state[dIdx].url = attachment.url;
                    render(); // Re-render to update the input field
                });
                pdfUploader.open();
            }
        });

        document.getElementById('cppm_add_doc_btn').addEventListener('click', function() {
            state.push({title: '', url: '', chapters: []});
            render();
        });

        render(); // Initial Load
    });
    </script>
    <?php
}

// ==========================================
// 5. SAVE POST DATA
// ==========================================
add_action( 'save_post', 'cppm_save_meta_data' );
function cppm_save_meta_data( $post_id ) {
    // Video Playlist
    if ( isset($_POST['cppm_titles']) ) {
        $data = array();
        for ($i=0; $i < count($_POST['cppm_titles']); $i++) {
            if(!empty($_POST['cppm_titles'][$i])) { $data[] = array('title' => sanitize_text_field($_POST['cppm_titles'][$i]), 'url' => esc_url_raw($_POST['cppm_urls'][$i])); }
        }
        update_post_meta( $post_id, '_cppm_videos_array', $data );
    }
    if ( isset($_POST['cppm_required_product']) ) update_post_meta( $post_id, '_cppm_required_product', sanitize_text_field($_POST['cppm_required_product']) );

    // Two-Tier E-Book Library
    if ( isset($_POST['cppm_ebook_docs_json']) ) {
        // WordPress automatically handles slashing, so we unslash before decoding to verify, then save raw JSON string securely
        $json_string = wp_unslash($_POST['cppm_ebook_docs_json']);
        $decoded = json_decode($json_string);
        if (json_last_error() === JSON_ERROR_NONE) {
            update_post_meta( $post_id, '_cppm_ebook_docs_json', wp_slash($json_string) );
        }
    }
    if ( isset($_POST['cppm_ebook_required_product']) ) update_post_meta( $post_id, '_cppm_ebook_required_product', sanitize_text_field($_POST['cppm_ebook_required_product']) );
}