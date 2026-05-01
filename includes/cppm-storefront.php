<?php
/**
 * Core: Custom Storefront, Personalization & AJAX Pagination
 * Architecture: Modular / Asset Separated
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. HELPER: PERSONALIZATION ENGINE
// ==========================================
function cppm_get_user_personalized_data() {
    $data = array( 'product_ids' => array(), 'cat_ids' => array() );
    
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        $customer_orders = wc_get_orders( array(
            'customer' => $current_user->user_email,
            'status'   => array( 'completed', 'processing' ),
            'limit'    => -1,
        ) );
        
        foreach ( $customer_orders as $order ) {
            foreach ( $order->get_items() as $item ) {
                $pid = $item->get_product_id();
                $data['product_ids'][] = $pid;
                
                $terms = get_the_terms( $pid, 'product_cat' );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    foreach ( $terms as $term ) {
                        $data['cat_ids'][] = $term->term_id;
                    }
                }
            }
        }
        $data['product_ids'] = array_unique( $data['product_ids'] );
        $data['cat_ids']     = array_unique( $data['cat_ids'] );
    }
    return $data;
}

// ==========================================
// 2. AJAX HANDLERS (WITH FILTERING LOGIC)
// ==========================================
add_action( 'wp_ajax_cppm_load_store_products', 'cppm_ajax_load_store_products' );
add_action( 'wp_ajax_nopriv_cppm_load_store_products', 'cppm_ajax_load_store_products' );

function cppm_ajax_load_store_products() {
    cppm_fetch_and_render_products(1);
}

add_action( 'wp_ajax_cppm_lazy_load_more', 'cppm_ajax_lazy_load_more' );
add_action( 'wp_ajax_nopriv_cppm_lazy_load_more', 'cppm_ajax_lazy_load_more' );

function cppm_ajax_lazy_load_more() {
    $paged = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 1;
    cppm_fetch_and_render_products($paged, true);
}

function cppm_fetch_and_render_products($paged = 1, $is_lazy = false) {
    $category_slug = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : 'all';
    $orderby       = isset( $_POST['orderby'] ) ? sanitize_text_field( $_POST['orderby'] ) : 'popularity';
    
    // Filter Variables
    $min_price  = isset( $_POST['min_price'] ) && $_POST['min_price'] !== '' ? floatval( $_POST['min_price'] ) : null;
    $max_price  = isset( $_POST['max_price'] ) && $_POST['max_price'] !== '' ? floatval( $_POST['max_price'] ) : null;
    $min_rating = isset( $_POST['min_rating'] ) && $_POST['min_rating'] !== '' ? floatval( $_POST['min_rating'] ) : null;
    $on_sale    = isset( $_POST['on_sale'] ) && $_POST['on_sale'] === 'true' ? true : false;

    $has_filters = ($min_price !== null || $max_price !== null || $min_rating !== null || $on_sale);

    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 12, 
        'paged'          => $paged, 
    );

    $meta_query = array( 'relation' => 'AND' );

    if ( $has_filters ) {
        if ( $on_sale ) {
            $meta_query[] = array( 'key' => '_sale_price', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' );
        }
        if ( $min_rating !== null ) {
            $meta_query[] = array( 'key' => '_wc_average_rating', 'value' => $min_rating, 'compare' => '>=', 'type' => 'DECIMAL' );
        }
        if ( $min_price !== null || $max_price !== null ) {
            $price_query = array( 'key' => '_price', 'type' => 'NUMERIC' );
            if ( $min_price !== null && $max_price !== null ) {
                $price_query['value'] = array($min_price, $max_price);
                $price_query['compare'] = 'BETWEEN';
            } elseif ( $min_price !== null ) {
                $price_query['value'] = $min_price;
                $price_query['compare'] = '>=';
            } else {
                $price_query['value'] = $max_price;
                $price_query['compare'] = '<=';
            }
            $meta_query[] = $price_query;
        }
        $args['meta_query'] = $meta_query;
        
        if ( $orderby ) {
            switch ( $orderby ) {
                case 'price': $args['meta_key'] = '_price'; $args['orderby'] = array('meta_value_num' => 'ASC', 'ID' => 'DESC'); break;
                case 'price-desc': $args['meta_key'] = '_price'; $args['orderby'] = array('meta_value_num' => 'DESC', 'ID' => 'DESC'); break;
                case 'rating': $args['meta_key'] = '_wc_average_rating'; $args['orderby'] = array('meta_value_num' => 'DESC', 'ID' => 'DESC'); break;
                case 'date': $args['orderby'] = 'date'; $args['order'] = 'DESC'; break;
                default: $args['meta_key'] = 'total_sales'; $args['orderby'] = array('meta_value_num' => 'DESC', 'ID' => 'DESC'); break;
            }
        }
    } 
    elseif ( $category_slug === 'all' && $orderby === 'popularity' ) {
        $args['meta_key'] = 'total_sales';
        $args['orderby'] = array( 'meta_value_num' => 'DESC', 'ID' => 'DESC' );

        $personal_data = cppm_get_user_personalized_data();
        if ( ! empty( $personal_data['product_ids'] ) ) {
            $args['post__not_in'] = $personal_data['product_ids']; 
        }
    } else {
        if ( $orderby ) {
            switch ( $orderby ) {
                case 'price': $args['meta_key'] = '_price'; $args['orderby'] = array('meta_value_num' => 'ASC', 'ID' => 'DESC'); break;
                case 'price-desc': $args['meta_key'] = '_price'; $args['orderby'] = array('meta_value_num' => 'DESC', 'ID' => 'DESC'); break;
                case 'rating': $args['meta_key'] = '_wc_average_rating'; $args['orderby'] = array('meta_value_num' => 'DESC', 'ID' => 'DESC'); break;
                case 'date': $args['orderby'] = 'date'; $args['order'] = 'DESC'; break;
                default: $args['meta_key'] = 'total_sales'; $args['orderby'] = array('meta_value_num' => 'DESC', 'ID' => 'DESC'); break;
            }
        }
    }

    if ( $category_slug !== 'all' ) {
        $args['tax_query'][] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $category_slug,
        );
    }

    $products = new WP_Query( $args );
    $content_html = '';

    if ( $products->have_posts() ) {
        ob_start();
        if ( ! $is_lazy ) echo '<ul class="products columns-4 cppm-strict-grid">';
        while ( $products->have_posts() ) {
            $products->the_post();
            wc_get_template_part( 'content', 'product' );
        }
        if ( ! $is_lazy ) echo '</ul>';
        $content_html = ob_get_clean();
    } elseif ( ! $is_lazy ) {
        $content_html = '<div style="text-align:center; padding: 40px; color: #64748b; font-size: 15px;">No products match your filters.</div>';
    }
    
    wp_reset_postdata();

    if ( $is_lazy ) {
        $has_more = ( $paged < $products->max_num_pages );
        wp_send_json_success( array( 'content' => $content_html, 'has_more' => $has_more ) );
    } else {
        echo $content_html;
    }
    wp_die();
}

// ==========================================
// 3. THE CUSTOM IMAGE & BADGE INJECTOR
// ==========================================
add_action( 'woocommerce_before_shop_loop_item_title', 'cppm_custom_product_image_block', 10 );
function cppm_custom_product_image_block() {
    global $product;
    
    echo '<div class="cppm-image-container">';
    if ( $product->is_on_sale() ) {
        echo '<span class="cppm-abs-sale-badge">SALE!</span>';
    }
    echo woocommerce_get_product_thumbnail();
    
    $review_count = $product->get_review_count();
    if ( $review_count > 0 ) {
        $avg = number_format( (float)$product->get_average_rating(), 1, '.', '' );
        $formatted_count = $review_count < 1000 ? $review_count : ($review_count < 1000000 ? floor($review_count / 1000) . 'k' : floor($review_count / 1000000) . 'm');

        echo '<div class="cppm-abs-rating">';
        echo '<span class="cppm-rating-val">' . esc_html($avg) . '</span>';
        echo '<span class="cppm-star-icon">★</span>';
        echo '<span class="cppm-rating-sep">|</span>';
        echo '<span class="cppm-rating-count">' . esc_html($formatted_count) . '</span>';
        echo '</div>';
    }
    echo '</div>'; 
}

// ==========================================
// 4. THE STOREFRONT SHORTCODE & ASSET ENQUEUE
// ==========================================
add_shortcode( 'cppm_storefront', 'cppm_render_storefront' );
function cppm_render_storefront() {
    
    // ENQUEUE ASSETS ONLY WHEN SHORTCODE IS USED
    $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
    wp_enqueue_style( 'cppm-storefront-css', $plugin_url . 'assets/css/storefront.css', array(), '1.0.0' );
    wp_enqueue_script( 'cppm-storefront-js', $plugin_url . 'assets/js/storefront.js', array(), '1.0.0', true );
    
    $ajax_nonce = wp_create_nonce( 'cppm_store_ajax_nonce' );
    wp_localize_script( 'cppm-storefront-js', 'cppmStorefrontData', array(
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'ajaxNonce' => $ajax_nonce
    ));

    // RENDER HTML
    ob_start();
    
    $all_raw_categories = get_terms( array( 
        'taxonomy'   => 'product_cat', 
        'hide_empty' => true 
    ) );

    global $wpdb;
    $processed_categories = array();
    $unique_names = array();

    if ( ! empty( $all_raw_categories ) && ! is_wp_error( $all_raw_categories ) ) {
        foreach ( $all_raw_categories as $category ) {
            if ( $category->slug === 'uncategorized' ) continue;

            $lower_name = strtolower(trim($category->name));
            if ( in_array( $lower_name, $unique_names ) ) continue; 
            $unique_names[] = $lower_name;

            $sales = $wpdb->get_var( $wpdb->prepare( "
                SELECT SUM(CAST(pm.meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->term_relationships} tr ON pm.post_id = tr.object_id
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE pm.meta_key = 'total_sales' AND tt.term_id = %d AND tt.taxonomy = 'product_cat'
            ", $category->term_id ) );
            
            $category->total_sales_count = (int) $sales;
            $processed_categories[] = $category;
        }
    }

    usort( $processed_categories, function( $a, $b ) { 
        return $b->total_sales_count - $a->total_sales_count; 
    } );

    $categories = array_slice( $processed_categories, 0, 15 );
    ?>

    <div class="cppm-store-wrapper">
        <div class="cppm-sticky-header">
            
            <div class="cppm-search-container">
                <div class="cppm-search-form cppm-trigger-search" style="cursor: pointer;">
                    <svg class="cppm-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" placeholder="Search for Courses, e-Books & More" readonly style="cursor:pointer;padding-left:36px;" />
                </div>
            </div>

            <div class="cppm-nav-sort-container">
                <div class="cppm-cat-nav">
                    <a class="cppm-cat-item active" data-category="all">
                        <div class="cppm-cat-image"><span style="font-size: 22px;">🛍️</span></div>
                        <span class="cppm-cat-name">For You</span>
                    </a>
                    <?php if ( ! empty( $categories ) ) : ?>
                        <?php foreach ( $categories as $category ) : ?>
                            <?php $thumbnail_id = get_term_meta( $category->term_id, 'thumbnail_id', true ); $image_url = $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : ''; ?>
                            <a class="cppm-cat-item" data-category="<?php echo esc_attr( $category->slug ); ?>">
                                <div class="cppm-cat-image"><?php echo $image_url ? '<img src="'.esc_url($image_url).'">' : '<span style="font-size: 22px; color:#94a3b8;">📁</span>'; ?></div>
                                <span class="cppm-cat-name"><?php echo esc_html( $category->name ); ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="cppm-controls-wrapper">
                    <button type="button" class="cppm-filter-btn" id="cppm-filter-toggle">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg> Filters
                    </button>
                    <select class="cppm-sort-select cppm-main-sort-select">
                        <option value="popularity" selected>Recommended</option>
                        <option value="rating">Rating</option>
                        <option value="date">Latest</option>
                        <option value="price">Price: Low to High</option>
                        <option value="price-desc">Price: High to Low</option>
                    </select>
                </div>
            </div>

            <div class="cppm-count-sort-mobile">
                <button type="button" class="cppm-filter-btn" id="cppm-filter-toggle-mobile">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg> Filters
                </button>
                <select class="cppm-sort-select cppm-mobile-sort-select">
                    <option value="popularity" selected>Recommended</option>
                    <option value="rating">Rating</option>
                    <option value="date">Latest</option>
                    <option value="price">Price: Low to High</option>
                    <option value="price-desc">Price: High to Low</option>
                </select>
            </div>
            
        </div> 

        <div id="cppm-store-products">
            <div class="cppm-store-loader"><div class="cppm-spinner"></div></div>
        </div>

    </div>

    <div class="cppm-filter-modal-overlay" id="cppm-filter-overlay">
        <div class="cppm-filter-modal">
            <div class="cppm-filter-header">
                <h3>Filter Options</h3>
                <button type="button" class="cppm-filter-close" id="cppm-close-filters">&times;</button>
            </div>
            <div class="cppm-filter-body">
                <div class="cppm-filter-group">
                    <label>Price Range (₹)</label>
                    <div class="cppm-price-inputs">
                        <input type="number" id="filter-min-price" placeholder="Min">
                        <span style="color: #cbd5e1; font-weight: bold;">-</span>
                        <input type="number" id="filter-max-price" placeholder="Max">
                    </div>
                </div>
                <div class="cppm-filter-group">
                    <label>Customer Rating</label>
                    <select class="cppm-sort-select" id="filter-min-rating" style="width: 100%;">
                        <option value="">Any Rating</option>
                        <option value="4">4★ & Above</option>
                        <option value="3">3★ & Above</option>
                    </select>
                </div>
                <div class="cppm-filter-group">
                    <label>Special Offers</label>
                    <label class="cppm-checkbox-label">
                        <input type="checkbox" id="filter-on-sale"> Show only items on Sale
                    </label>
                </div>
                <button type="button" class="cppm-apply-filters" id="cppm-apply-filters-btn">Apply Filters</button>
            </div>
        </div>
    </div>

    <?php
    return ob_get_clean();
}