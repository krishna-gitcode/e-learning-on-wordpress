<?php
/**
 * Core: Single Product Enhancements & Social Sharing
 * Architecture: Modular / Asset Separated
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. ENQUEUE PRODUCT ASSETS
// ==========================================
add_action( 'wp_enqueue_scripts', 'cppm_enqueue_product_assets', 999 );
function cppm_enqueue_product_assets() {
    if ( function_exists('is_product') && is_product() ) {
        $plugin_url = plugin_dir_url( dirname( __FILE__ ) );

        wp_enqueue_style( 'cppm-single-product-css', $plugin_url . 'assets/css/single-product.css', array(), '1.0.0' );
        wp_enqueue_script( 'cppm-single-product-js', $plugin_url . 'assets/js/single-product.js', array(), '1.0.0', true );
    }
}

// ==========================================
// 2. CLEAN UP DEFAULT WOOCOMMERCE & ASTRA CLUTTER
// ==========================================
add_action( 'wp', 'cppm_clean_single_product_layout' );
function cppm_clean_single_product_layout() {
    if ( is_product() ) {
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
        remove_action( 'woocommerce_single_product_summary', 'astra_woo_single_product_category', 5 );
        remove_action( 'woocommerce_single_product_summary', 'astra_woo_single_product_category', 10 );
        remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
    }
}

// ==========================================
// 3. INJECT MODERN UI CATEGORY BADGES
// ==========================================
add_action( 'woocommerce_single_product_summary', 'cppm_custom_single_categories', 4 );
function cppm_custom_single_categories() {
    global $product;
    if ( ! $product ) return;
    
    // Outputs the categories using our custom wrapper for the CSS file
    $cats = wc_get_product_category_list( $product->get_id(), '', '<div class="cppm-product-cats">', '</div>' );
    if ( $cats ) {
        echo wp_kses_post( $cats );
    }
}

// ==========================================
// 4. DYNAMIC PRODUCT FAVICON
// ==========================================
add_filter( 'get_site_icon_url', 'cppm_dynamic_product_favicon', 99, 3 );
function cppm_dynamic_product_favicon( $url, $size, $blog_id ) {
    if ( function_exists('is_product') && is_product() ) {
        global $post;
        if ( $post && has_post_thumbnail( $post->ID ) ) {
            $product_thumb_url = get_the_post_thumbnail_url( $post->ID, 'thumbnail' );
            if ( $product_thumb_url ) {
                return $product_thumb_url; 
            }
        }
    }
    return $url; 
}

// ==========================================
// 5. FLOATING PRODUCT IMAGE SHARE ICON (HTML ONLY)
// ==========================================
add_action( 'woocommerce_product_thumbnails', 'cppm_floating_share_icon_html', 99 );
function cppm_floating_share_icon_html() {
    global $product;
    if ( ! $product ) return;
    
    $product_url   = get_permalink( $product->get_id() );
    $product_title = $product->get_name();
    ?>
    <button id="cppm-share-btn" class="cppm-floating-share" data-url="<?php echo esc_url($product_url); ?>" data-title="<?php echo esc_attr($product_title); ?>" aria-label="Share this product">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="18" cy="5" r="3"></circle>
            <circle cx="6" cy="12" r="3"></circle>
            <circle cx="18" cy="19" r="3"></circle>
            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
        </svg>
    </button>
    <div id="cppm-share-toast" class="cppm-share-toast">Link Copied!</div>
    <?php
}

// ==========================================
// 6. FORCE LARGE IMAGE PREVIEWS FOR WHATSAPP/SOCIALS
// ==========================================
add_action( 'wp_head', 'cppm_force_large_social_previews', 5 );
function cppm_force_large_social_previews() {
    if ( function_exists('is_product') && is_product() ) {
        global $post;
        $product = wc_get_product( $post->ID );
        if ( ! $product ) return;

        $title = $product->get_name();
        $raw_desc = $product->get_short_description() ? $product->get_short_description() : $product->get_description();
        $description = wp_trim_words( wp_strip_all_tags( $raw_desc ), 20, '...' );
        $url = get_permalink( $product->get_id() );
        
        $image_id = $product->get_image_id();
        if ( $image_id ) {
            $image_data = wp_get_attachment_image_src( $image_id, 'full' );
            if ( $image_data ) {
                $image_url    = $image_data[0];
                $image_width  = $image_data[1];
                $image_height = $image_data[2];

                echo "\n";
                echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
                echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
                echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
                echo '<meta property="og:type" content="product" />' . "\n";
                echo '<meta property="og:image" content="' . esc_url( $image_url ) . '" />' . "\n";
                echo '<meta property="og:image:secure_url" content="' . esc_url( $image_url ) . '" />' . "\n";
                echo '<meta property="og:image:width" content="' . esc_attr( $image_width ) . '" />' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr( $image_height ) . '" />' . "\n";
                
                echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
                echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
                echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "\n";
                echo '<meta name="twitter:image" content="' . esc_url( $image_url ) . '" />' . "\n";
                echo "\n";
            }
        }
    }
}