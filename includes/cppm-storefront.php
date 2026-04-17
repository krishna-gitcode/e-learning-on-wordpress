<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
        $data['cat_ids'] = array_unique( $data['cat_ids'] );
    }
    return $data;
}

// ==========================================
// 2. AJAX HANDLERS (WITH FILTERING LOGIC)
// ==========================================

add_action( 'wp_ajax_cppm_load_store_products', 'cppm_ajax_load_store_products' );
add_action( 'wp_ajax_nopriv_cppm_load_store_products', 'cppm_ajax_load_store_products' );

function cppm_ajax_load_store_products() {
    check_ajax_referer( 'cppm_store_ajax_nonce', 'security' );
    cppm_fetch_and_render_products(1);
}

add_action( 'wp_ajax_cppm_lazy_load_more', 'cppm_ajax_lazy_load_more' );
add_action( 'wp_ajax_nopriv_cppm_lazy_load_more', 'cppm_ajax_lazy_load_more' );

function cppm_ajax_lazy_load_more() {
    check_ajax_referer( 'cppm_store_ajax_nonce', 'security' );
    $paged = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 1;
    cppm_fetch_and_render_products($paged, true);
}

// Unified fetching function to keep code DRY
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
        'posts_per_page' => $is_lazy ? 5 : 10,
        'paged'          => $paged, 
    );

    $meta_query = array( 'relation' => 'AND' );

    // Apply Active Filters
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
        
        // Ensure sorting still applies alongside filters
        if ( $orderby ) {
            switch ( $orderby ) {
                case 'price': $args['orderby'] = 'meta_value_num'; $args['meta_key'] = '_price'; $args['order'] = 'ASC'; break;
                case 'price-desc': $args['orderby'] = 'meta_value_num'; $args['meta_key'] = '_price'; $args['order'] = 'DESC'; break;
                case 'rating': $args['orderby'] = 'meta_value_num'; $args['meta_key'] = '_wc_average_rating'; $args['order'] = 'DESC'; break;
                case 'date': $args['orderby'] = 'date'; $args['order'] = 'DESC'; break;
                default: $args['orderby'] = 'meta_value_num'; $args['meta_key'] = 'total_sales'; $args['order'] = 'DESC'; break;
            }
        }
    } 
    // THE "FOR YOU" ALGORITHM (Only runs if no manual filters are active)
    elseif ( $category_slug === 'all' && $orderby === 'popularity' ) {
        $args['meta_query'] = array(
            'relation' => 'OR',
            array( 'key' => '_sale_price', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ),
            array( 'key' => 'total_sales', 'value' => 1, 'compare' => '>=', 'type' => 'NUMERIC' ),
            array( 'key' => '_wc_average_rating', 'value' => 4.0, 'compare' => '>=', 'type' => 'DECIMAL' )
        );
        $args['orderby'] = 'modified';
        $args['order'] = 'DESC';

        $personal_data = cppm_get_user_personalized_data();
        if ( ! empty( $personal_data['product_ids'] ) ) {
            $args['post__not_in'] = $personal_data['product_ids']; 
        }
        if ( ! empty( $personal_data['cat_ids'] ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $personal_data['cat_ids'],
                    'operator' => 'IN'
                )
            );
        }
    } else {
        // Standard Sorting (No Filters, Specific Category)
        if ( $orderby ) {
            switch ( $orderby ) {
                case 'price': $args['meta_key'] = '_price'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'ASC'; break;
                case 'price-desc': $args['meta_key'] = '_price'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'DESC'; break;
                case 'rating': $args['meta_key'] = '_wc_average_rating'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'DESC'; break;
                case 'date': $args['orderby'] = 'date'; $args['order'] = 'DESC'; break;
                default: $args['meta_key'] = 'total_sales'; $args['orderby'] = array('meta_value_num' => 'DESC', 'date' => 'DESC'); break;
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
// 4. THE STOREFRONT SHORTCODE
// ==========================================
add_shortcode( 'cppm_storefront', 'cppm_render_storefront' );
function cppm_render_storefront() {
    ob_start();
    $ajax_nonce = wp_create_nonce( 'cppm_store_ajax_nonce' );

    // ALGORITHM UPDATE: Fetch ALL categories, ignore parent/child hierarchy.
    $all_raw_categories = get_terms( array( 
        'taxonomy'   => 'product_cat', 
        'hide_empty' => true 
    ) );

    global $wpdb;
    $processed_categories = array();
    $unique_names = array();

    if ( ! empty( $all_raw_categories ) && ! is_wp_error( $all_raw_categories ) ) {
        foreach ( $all_raw_categories as $category ) {
            // Skip the default "Uncategorized" tag
            if ( $category->slug === 'uncategorized' ) continue;

            // DEDUPLICATION: Ensure no category names repeat (fixes the 'e-Books' double bug)
            $lower_name = strtolower(trim($category->name));
            if ( in_array( $lower_name, $unique_names ) ) {
                continue; 
            }
            $unique_names[] = $lower_name;

            // Fetch absolute sales numbers for this specific category tag
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

    // Sort by sales descending
    usort( $processed_categories, function( $a, $b ) { 
        return $b->total_sales_count - $a->total_sales_count; 
    } );

    // Slice to top 15 so the slider doesn't become infinitely long
    $categories = array_slice( $processed_categories, 0, 15 );
    ?>

    <style>
        /* ==========================================
           FLIPKART-STYLE STOREFRONT CSS
        ========================================== */
        .cppm-store-wrapper { max-width: 1200px; margin: 0 auto; font-family: system-ui, -apple-system, sans-serif; background: #ffffff; padding-top: 15px; }

        .cppm-sticky-header {
            position: sticky; top: 0; 
            z-index: 20; 
            background-color: #ffffff; margin-top: -15px; padding-top: 15px; 
            border-bottom: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .cppm-search-container { margin-bottom: 16px; padding: 0 16px; }
        .cppm-search-form { position: relative; display: flex; align-items: center; }
        .cppm-search-form input[type="search"] { width: 100%; padding: 12px 12px 12px 42px; border: 1px solid #2874f0; border-radius: 8px; font-size: 15px; color: #1e293b; background-color: #f8fafc; outline: none; transition: all 0.2s; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05) !important; }
        .cppm-search-form input[type="search"]:focus { background-color: #ffffff; border-width: 2px; box-shadow: 0 6px 15px rgba(40, 116, 240, 0.1) !important; }
        .cppm-search-icon { position: absolute; left: 14px; color: #64748b; width: 18px; height: 18px; }

        .cppm-nav-sort-container { display: flex; justify-content: space-between; align-items: center; padding: 0 0 12px 0; }
        
        .cppm-cat-nav { display: flex; gap: 16px; overflow-x: auto; scrollbar-width: none; padding: 0 16px; -webkit-overflow-scrolling: touch; }
        .cppm-cat-nav::-webkit-scrollbar { display: none; }
        .cppm-cat-item { display: flex; flex-direction: column; align-items: center; text-decoration: none !important; color: #475569 !important; cursor: pointer; min-width: 64px; -webkit-tap-highlight-color: transparent; }
        .cppm-cat-image { width: 54px; height: 54px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 6px; overflow: hidden; border: 2px solid transparent; transition: all 0.2s ease; }
        .cppm-cat-image img { width: 100%; height: 100%; object-fit: cover; }
        .cppm-cat-name { font-size: 11px; font-weight: 600; white-space: nowrap; text-align: center; transition: color 0.2s ease; }
        .cppm-cat-item.active .cppm-cat-name { color: #0275d8 !important; }
        .cppm-cat-item.active .cppm-cat-image { border-color: #0275d8; background: #eff6ff; }

        .cppm-controls-wrapper { display: flex; gap: 8px; padding-right: 16px; align-items: center; }
        
        .cppm-filter-btn {
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 13px; font-weight: 600; color: #1e293b; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.2s;
        }
        .cppm-filter-btn:hover, .cppm-filter-btn.active { border-color: #0275d8; color: #0275d8; }

        .cppm-sort-select {
            border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px; font-size: 13px; font-weight: 600; color: #1e293b; background-color: #f8fafc; outline: none; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.02); -webkit-appearance: none; background-image: url('data:image/svg+xml;utf8,<svg fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>'); background-repeat: no-repeat; background-position-x: 95%; background-position-y: 50%; background-size: 18px; padding-right: 32px; transition: all 0.2s;
        }
        .cppm-sort-select:focus { border-color: #0275d8; background-color: #ffffff; }

        /* ==========================================
           MODAL FILTER UI 
        ========================================== */
        .cppm-filter-modal-overlay {
            display: none; 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center; justify-content: center;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .cppm-filter-modal-overlay.open {
            display: flex; opacity: 1;
        }
        .cppm-filter-modal {
            background: #ffffff; border-radius: 16px; width: 100%; max-width: 400px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transform: translateY(20px) scale(0.95);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            overflow: hidden;
        }
        .cppm-filter-modal-overlay.open .cppm-filter-modal {
            transform: translateY(0) scale(1);
        }
        .cppm-filter-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 20px; border-bottom: 1px solid #f1f5f9; background: #f8fafc;
        }
        .cppm-filter-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: #1e293b; }
        .cppm-filter-close { background: transparent; border: none; font-size: 22px; color: #94a3b8; cursor: pointer; line-height: 1; padding: 0; }
        .cppm-filter-close:hover { color: #ef4444; }
        .cppm-filter-body { padding: 20px; display: flex; flex-direction: column; gap: 20px; }
        
        .cppm-filter-group { display: flex; flex-direction: column; gap: 8px; }
        .cppm-filter-group label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .cppm-price-inputs { display: flex; gap: 12px; align-items: center; }
        .cppm-price-inputs input { flex: 1; width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .cppm-price-inputs input:focus { border-color: #0275d8; }
        .cppm-checkbox-label { display: flex; align-items: center; gap: 10px; font-size: 14px !important; text-transform: none !important; color: #1e293b !important; cursor: pointer; font-weight: 500 !important; letter-spacing: 0 !important; }
        .cppm-checkbox-label input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
        .cppm-apply-filters { background: #0275d8; color: #fff; border: none; padding: 14px; border-radius: 8px; font-weight: 600; font-size: 15px; cursor: pointer; width: 100%; transition: background 0.2s; margin-top: 10px; }
        .cppm-apply-filters:hover { background: #025aa5; }

        ul.products.cppm-strict-grid { display: grid !important; grid-template-columns: repeat(2, 1fr) !important; gap: 12px !important; padding: 0 12px !important; margin: 0 !important; list-style: none !important; position: relative; }
        ul.products.cppm-strict-grid::before, ul.products.cppm-strict-grid::after { display: none !important; }
        ul.products li.product { position: relative; background: #ffffff; border-radius: 12px; padding: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between; }
        ul.products li.product a.button { text-decoration: none !important; color: #ffffff !important; }
        ul.products li.product .woocommerce-loop-product__title { margin-top: 10px !important; font-size: 14px; }

        ul.products.cppm-strict-grid li.product > img, ul.products.cppm-strict-grid li.product > a > img, ul.products.cppm-strict-grid li.product > a > .onsale, ul.products.cppm-strict-grid li.product > .onsale, ul.products.cppm-strict-grid li.product .badge, ul.products.cppm-strict-grid li.product .badge-wrapper, ul.products.cppm-strict-grid li.product .star-rating { display: none !important; }
        .cppm-image-container img { display: block !important; width: 100% !important; height: auto !important; border-radius: 8px; }
        .cppm-abs-sale-badge { display: inline-block !important; }
        .cppm-image-container { position: relative; width: 100%; border-radius: 8px; overflow: hidden; }
        .cppm-abs-sale-badge { position: absolute; top: 6px; left: 6px; background-color: #0275d8; color: #ffffff; font-size: 10px; font-weight: 800; padding: 4px 6px; border-radius: 4px; z-index: 10; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .cppm-abs-rating { position: absolute; bottom: 26px; left: 6px; background: rgba(255, 255, 255, 0.95); padding: 3px 6px; border-radius: 4px; display: flex; align-items: center; gap: 3px; z-index: 10; box-shadow: 0 2px 5px rgba(0,0,0,0.2); line-height: 1; }
        .cppm-rating-val { font-size: 11px; font-weight: 700; color: #1e293b; }
        .cppm-star-icon { font-size: 12px; color: #f1c40f; margin-top: -1px; }
        .cppm-rating-sep { font-size: 10px; color: #cbd5e1; margin: 0 1px; }
        .cppm-rating-count { font-size: 11px; font-weight: 600; color: #64748b; }

        .cppm-count-sort-mobile { display: none; }

        @media (max-width: 767px) {
            .cppm-nav-sort-container { flex-direction: column; padding-bottom: 0; }
            .cppm-cat-nav { width: 100%; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 12px; }
            .cppm-controls-wrapper { display: none; }
            
            .cppm-count-sort-mobile { 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                padding: 0 16px 12px 16px; 
                gap: 12px; 
            }
            .cppm-count-sort-mobile .cppm-filter-btn {
                flex: 1; 
                justify-content: center;
                padding: 8px 10px; 
                font-size: 13px;
            }
            .cppm-count-sort-mobile .cppm-sort-select {
                flex: 1;
                padding: 8px 10px; 
                font-size: 13px; 
                padding-right: 28px; 
                background-position-x: 93%;
            }
        }
        @media (min-width: 768px) { ul.products.cppm-strict-grid { grid-template-columns: repeat(3, 1fr) !important; gap: 20px !important; padding: 0 20px !important; } }
        @media (min-width: 1024px) { ul.products.cppm-strict-grid { grid-template-columns: repeat(4, 1fr) !important; } }

        .cppm-store-loader, .cppm-load-more-loader { display: flex; justify-content: center; align-items: center; min-height: 200px; width: 100%; }
        .cppm-load-more-loader { min-height: 80px; padding: 20px 0; grid-column: 1 / -1; }
        .cppm-spinner { width: 36px; height: 36px; border: 3px solid #f1f5f9; border-top: 3px solid #0275d8; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>

    <div class="cppm-store-wrapper">
        <div class="cppm-sticky-header">
            
            <div class="cppm-search-container">
                <form class="cppm-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
                    <svg class="cppm-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="search" name="s" placeholder="Search for Courses, e-Books & More" required />
                    <input type="hidden" name="post_type" value="product" />
                </form>
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const productContainer = document.getElementById('cppm-store-products');
        const catButtons = document.querySelectorAll('.cppm-cat-item');
        const desktopSortSelect = document.querySelector('.cppm-main-sort-select');
        const mobileSortSelect = document.querySelector('.cppm-mobile-sort-select');
        
        // Modal Elements
        const filterToggleDesktop = document.getElementById('cppm-filter-toggle');
        const filterToggleMobile = document.getElementById('cppm-filter-toggle-mobile');
        const filterOverlay = document.getElementById('cppm-filter-overlay');
        const closeFiltersBtn = document.getElementById('cppm-close-filters');
        const applyFiltersBtn = document.getElementById('cppm-apply-filters-btn');
        
        const ajaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
        const ajaxNonce = "<?php echo $ajax_nonce; ?>";

        let currentCategory = 'all';
        let currentOrderBy = 'popularity';
        let currentPage = 1;
        let isLoadingProducts = false;
        let isFetchingMore = false;
        let hasMorePosts = true;

        // Modal Toggle Logic
        function openModal() {
            filterOverlay.classList.add('open');
            document.body.style.overflow = 'hidden'; 
        }
        function closeModal() {
            filterOverlay.classList.remove('open');
            document.body.style.overflow = '';
        }

        if(filterToggleDesktop) filterToggleDesktop.addEventListener('click', openModal);
        if(filterToggleMobile) filterToggleMobile.addEventListener('click', openModal);
        if(closeFiltersBtn) closeFiltersBtn.addEventListener('click', closeModal);
        
        filterOverlay.addEventListener('click', function(e) {
            if(e.target === filterOverlay) {
                closeModal();
            }
        });

        function loadStoreProducts(resetPaged = true, isLazy = false) {
            if (isLoadingProducts || isFetchingMore) return;

            if (resetPaged) {
                currentPage = 1;
                isLoadingProducts = true;
                hasMorePosts = true;
                productContainer.innerHTML = '<div class="cppm-store-loader"><div class="cppm-spinner"></div></div>';
            } else if (isLazy && hasMorePosts) {
                isFetchingMore = true;
                currentPage++;
                const grid = productContainer.querySelector('.products.cppm-strict-grid');
                if(grid) grid.insertAdjacentHTML('beforeend', '<li class="cppm-load-more-loader"><div class="cppm-spinner"></div></li>');
            } else {
                return; 
            }

            const formData = new FormData();
            formData.append('action', resetPaged ? 'cppm_load_store_products' : 'cppm_lazy_load_more');
            if (!resetPaged) formData.append('paged', currentPage);

            formData.append('category', currentCategory);
            formData.append('orderby', currentOrderBy);
            
            formData.append('min_price', document.getElementById('filter-min-price').value);
            formData.append('max_price', document.getElementById('filter-max-price').value);
            formData.append('min_rating', document.getElementById('filter-min-rating').value);
            formData.append('on_sale', document.getElementById('filter-on-sale').checked ? 'true' : 'false');
            
            formData.append('security', ajaxNonce);

            fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(response => resetPaged ? response.text() : response.json())
            .then(data => {
                if (resetPaged) {
                    productContainer.innerHTML = data;
                } else {
                    const loader = productContainer.querySelector('.cppm-load-more-loader');
                    if (loader) loader.remove();
                    if (data.success && data.data.content) {
                        const grid = productContainer.querySelector('.products.cppm-strict-grid');
                        if(grid) grid.insertAdjacentHTML('beforeend', data.data.content);
                        hasMorePosts = data.data.has_more;
                    }
                }
            })
            .catch(error => {
                console.error('AJAX error:', error);
                if (resetPaged) productContainer.innerHTML = '<div style="text-align:center; padding: 20px; color:red;">Connection error. Please try again.</div>';
            })
            .finally(() => {
                isLoadingProducts = false;
                isFetchingMore = false;
            });
        }

        applyFiltersBtn.addEventListener('click', function() {
            closeModal();
            loadStoreProducts(true);
        });

        catButtons.forEach(button => {
            button.addEventListener('click', function() {
                if (this.classList.contains('active')) return;
                catButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                currentCategory = this.getAttribute('data-category');
                loadStoreProducts(true);
            });
        });

        function handleSortChange(e) {
            currentOrderBy = e.target.value;
            if (desktopSortSelect) desktopSortSelect.value = currentOrderBy;
            if (mobileSortSelect) mobileSortSelect.value = currentOrderBy;
            loadStoreProducts(true);
        }

        if (desktopSortSelect) desktopSortSelect.addEventListener('change', handleSortChange);
        if (mobileSortSelect) mobileSortSelect.addEventListener('change', handleSortChange);

        const observerOptions = { root: null, rootMargin: '0px 0px 300px 0px', threshold: 0.1 };
        const loadMoreObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !isLoadingProducts && !isFetchingMore && hasMorePosts) {
                    loadStoreProducts(false, true); 
                }
            });
        }, observerOptions);

        function initialStoreLoad() {
            currentPage = 1; isLoadingProducts = true; hasMorePosts = true;
            productContainer.innerHTML = '<div class="cppm-store-loader"><div class="cppm-spinner"></div></div>';
            const formData = new FormData();
            formData.append('action', 'cppm_load_store_products'); 
            formData.append('category', currentCategory);
            formData.append('orderby', currentOrderBy);
            formData.append('security', ajaxNonce);

            fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(response => response.text()) 
            .then(html => {
                productContainer.innerHTML = html;
                setTimeout(() => {
                    const grid = productContainer.querySelector('.products.cppm-strict-grid');
                    if (grid) loadMoreObserver.observe(grid); 
                }, 100);
            })
            .finally(() => { isLoadingProducts = false; });
        }

        initialStoreLoad();
    });
    </script>

    <?php
    return ob_get_clean();
}