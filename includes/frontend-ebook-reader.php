<?php
/**
 * Core: Secure HTML5 Canvas E-Book Reader
 * Architecture: Modular / Asset Separated
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'ebook_reader', 'cppm_render_ebook_reader' );
function cppm_render_ebook_reader( $atts ) {
    
    // 1. DYNAMIC ROUTING: Check the URL first (e.g., ?ebook_id=42)
    $ebook_id = isset( $_GET['ebook_id'] ) ? intval( $_GET['ebook_id'] ) : 0;

    // 2. FALLBACK: Check shortcode attribute if URL is empty
    if ( ! $ebook_id ) {
        $atts = shortcode_atts( array( 'id' => '' ), $atts );
        $ebook_id = intval( $atts['id'] );
    }

    if ( empty( $ebook_id ) ) {
        return '<div style="padding:40px; text-align:center; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;"><h3 style="color:#0f172a; margin-top:0;">E-Book Not Found</h3><p>Please provide a valid E-Book ID in the URL.</p></div>';
    }

    $prod_id = get_post_meta( $ebook_id, '_cppm_ebook_required_product', true );
    $docs_json = get_post_meta( $ebook_id, '_cppm_ebook_docs_json', true );
    
    if ( empty($docs_json) ) { $docs_json = '[]'; }
    $current_user = wp_get_current_user();

    // 1. SECURITY & ACCESS GATES
    if ( ! is_user_logged_in() ) {
        return '<div style="padding:40px; text-align:center; background:#fff3f3; border-radius:12px; border:1px solid #fecaca;"><h3 style="color:#dc2626; margin-top:0;">Locked</h3><p>Please log in to read this material.</p></div>';
    }
    
    $has_access = false;
    
    if ( empty($prod_id) ) {
        $has_access = true; 
    } elseif ( function_exists('wc_customer_bought_product') && wc_customer_bought_product( $current_user->user_email, $current_user->ID, $prod_id ) ) {
        $has_access = true; 
    } elseif ( current_user_can('administrator') || current_user_can('shop_manager') ) {
        $has_access = true;
    }

    if ( ! $has_access ) {
        return '<div style="padding:40px; text-align:center; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;"><h3 style="color:#0f172a; margin-top:0;">Purchase Required</h3><p>You must purchase the associated product to access this secure e-book.</p><a href="'.get_permalink($prod_id).'" style="background:#2874f0; color:#fff; padding:10px 20px; border-radius:6px; text-decoration:none; display:inline-block; font-weight:bold; margin-top:10px;">View Product</a></div>';
    }

    // Decode docs to check for downloadable files
    $docs_data = json_decode($docs_json, true);
    if (!is_array($docs_data)) $docs_data = array();

    // 2. ENQUEUE ASSETS
    $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
    
    wp_enqueue_script( 'pdfjs-core', 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js', array(), '2.16.105', true );
    wp_enqueue_style( 'cppm-ebook-css', $plugin_url . 'assets/css/ebook-reader.css', array(), '1.0.0' );
    wp_enqueue_script( 'cppm-ebook-js', $plugin_url . 'assets/js/ebook-reader.js', array('pdfjs-core'), '1.0.0', true );

    $watermark_text = $current_user->user_firstname ? $current_user->user_firstname : $current_user->display_name;
    $watermark_text .= ' | ' . $current_user->user_email;

    wp_localize_script( 'cppm-ebook-js', 'cppmEbookData', array(
        'docs'      => $docs_data,
        'watermark' => esc_js( $watermark_text ),
        'workerSrc' => 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js'
    ));

    // 3. RENDER READER UI
    ob_start();
    ?>
    <div class="cppm-reader-wrapper">
        <div class="cppm-reader-toolbar">
            
            <div style="display:flex; gap:10px; align-items:center;">
                <?php if (count($docs_data) > 1) : ?>
                    <select class="cppm-doc-selector" id="cppm-doc-select">
                        <?php foreach($docs_data as $index => $doc) : ?>
                            <option value="<?php echo $index; ?>"><?php echo esc_html($doc['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <span style="color:#f8fafc; font-weight:bold; font-size:15px;"><?php echo esc_html($docs_data[0]['title']); ?></span>
                <?php endif; ?>
            </div>

            <div class="cppm-reader-controls">
                <button class="cppm-reader-btn" id="cppm-zoom-out" aria-label="Zoom Out">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
                </button>
                <button class="cppm-reader-btn" id="cppm-zoom-in" aria-label="Zoom In">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
                </button>
                <button class="cppm-reader-btn" id="cppm-fullscreen-btn" aria-label="Fullscreen">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
                </button>
            </div>

            <div class="cppm-reader-controls">
                <button class="cppm-reader-btn" id="cppm-prev-page">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <span class="cppm-page-info" id="cppm-page-info">1 / 1</span>
                <button class="cppm-reader-btn" id="cppm-next-page">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </div>
        </div>

        <div id="cppm-reader-area">
            <div id="cppm-reader-loader">Loading Secure Document...</div>
            <div id="cppm-canvas-wrap">
                <canvas id="cppm-pdf-canvas"></canvas>
                <canvas id="cppm-watermark-canvas"></canvas>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}