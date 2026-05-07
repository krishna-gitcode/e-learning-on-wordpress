<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. KILL DEFAULT THEME & WOOCOMMERCE CLUTTER
// ==========================================

add_filter( 'woocommerce_show_page_title', '__return_false' );
add_filter( 'astra_the_search_page_title', '__return_false' );
remove_action( 'astra_archive_header', 'astra_archive_page_info' );

remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

add_action('wp', 'cppm_remove_search_thumbnails', 11);
function cppm_remove_search_thumbnails() {
    if ( is_search() ) {
        remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
        remove_action( 'woocommerce_before_shop_loop_item_title', 'astra_woo_shop_thumbnail', 10 );
        remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
        remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
    }
}

add_action('wp_head', 'cppm_nuke_astra_search_defaults', 99);
function cppm_nuke_astra_search_defaults() {
    if ( is_search() ) {
        echo '<style>
            .ast-archive-description, .page-header.ast-no-title, section.ast-archive-description, .woocommerce-result-count, .woocommerce-ordering { display: none !important; margin: 0 !important; padding: 0 !important; }
        </style>';
    }
}

// ==========================================
// 2. SEARCH QUERY FILTER ENGINE
// ==========================================
add_action('woocommerce_product_query', 'cppm_apply_search_filters');
function cppm_apply_search_filters($q) {
    if ( ! is_admin() && $q->is_main_query() && is_search() ) {
        $meta_query = $q->get( 'meta_query' );
        if ( empty( $meta_query ) ) { $meta_query = array( 'relation' => 'AND' ); }

        if ( isset( $_GET['on_sale'] ) && $_GET['on_sale'] == '1' ) {
            $meta_query[] = array( 'key' => '_sale_price', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' );
        }
        
        if ( isset( $_GET['min_rating'] ) && !empty($_GET['min_rating']) ) {
            $meta_query[] = array( 'key' => '_wc_average_rating', 'value' => floatval($_GET['min_rating']), 'compare' => '>=', 'type' => 'DECIMAL' );
        }

        $q->set( 'meta_query', $meta_query );

        if ( isset( $_GET['category'] ) && !empty($_GET['category']) ) {
            $tax_query = $q->get( 'tax_query' );
            if ( empty( $tax_query ) ) { $tax_query = array(); }
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($_GET['category']),
            );
            $q->set( 'tax_query', $tax_query );
        }
    }
}

// ==========================================
// 3. STICKY SEARCH HEADER, TOGGLES & FILTER MODAL
// ==========================================
add_action( 'woocommerce_before_main_content', 'cppm_sticky_search_ui', 20 );
function cppm_sticky_search_ui() {
    if ( ! is_search() ) return;
    
    $search_query = get_search_query();
    $min_price    = isset($_GET['min_price']) ? esc_attr($_GET['min_price']) : '';
    $max_price    = isset($_GET['max_price']) ? esc_attr($_GET['max_price']) : '';
    $min_rating   = isset($_GET['min_rating']) ? esc_attr($_GET['min_rating']) : '';
    $on_sale      = isset($_GET['on_sale']) && $_GET['on_sale'] == '1' ? 'checked' : '';
    $orderby      = isset($_GET['orderby']) ? esc_attr($_GET['orderby']) : 'relevance';
    $selected_cat = isset($_GET['category']) ? esc_attr($_GET['category']) : '';

    $categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );
    ?>

    <style>
        /* FIX Z-INDEX OVERLAP */
        #masthead, .ast-main-header-wrap, .site-header, .ast-builder-header { position: relative !important; z-index: 99999 !important; }

        /* Sticky Header */
        .cppm-sticky-search { position: sticky; top: 60px; z-index: 10; background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); padding: 15px 20px; border-bottom: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 20px; display: flex; flex-direction: column; gap: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        .cppm-search-header-title { font-size: 14px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin: 0; }
        .cppm-search-header-title span { color: #0f172a; }
        
        .cppm-search-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .cppm-search-input-fake { flex: 1; min-width: 200px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 15px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: 0.2s; }
        .cppm-search-input-fake:hover { border-color: #2874f0; background: #ffffff; }
        .cppm-search-input-fake span { color: #0f172a; font-weight: 500; font-size: 15px; }
        
        .cppm-filter-trigger { background: #ffffff; border: 1px solid #cbd5e1; color: #1e293b; padding: 10px 15px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
        .cppm-filter-trigger:hover { border-color: #2874f0; color: #2874f0; }

        .cppm-view-toggles { display: flex; background: #f1f5f9; border-radius: 8px; padding: 4px; border: 1px solid #cbd5e1; }
        .cppm-view-btn { background: transparent; border: none; padding: 6px 10px; cursor: pointer; border-radius: 6px; color: #64748b; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .cppm-view-btn.active { background: #ffffff; color: #2874f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .cppm-view-btn svg { width: 18px; height: 18px; }

        /* Filter Modal */
        .cppm-filter-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); backdrop-filter: blur(4px); z-index: 100000; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s; padding: 20px; }
        .cppm-filter-overlay.open { display: flex; opacity: 1; }
        .cppm-filter-modal { background: #ffffff; border-radius: 16px; width: 100%; max-width: 400px; transform: translateY(20px); transition: transform 0.3s; overflow: hidden; max-height: 90vh; display: flex; flex-direction: column; }
        .cppm-filter-overlay.open .cppm-filter-modal { transform: translateY(0); }
        
        .cppm-modal-header { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #f8fafc; }
        .cppm-modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; }
        .cppm-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; padding: 0; line-height: 1; }
        
        .cppm-modal-body { padding: 20px; overflow-y: auto; }
        .cppm-filter-group { margin-bottom: 20px; }
        .cppm-filter-group label { display: block; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 8px; }
        .cppm-price-row { display: flex; gap: 10px; align-items: center; }
        .cppm-price-row input { flex: 1; width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; }
        .cppm-filter-select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; background: #ffffff; }
        .cppm-check-row { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; color: #1e293b; cursor: pointer; }
        .cppm-check-row input { width: 18px; height: 18px; cursor: pointer; }
        
        .cppm-apply-btn { width: 100%; background: #2874f0; color: #ffffff; padding: 14px; border: none; border-radius: 8px; font-weight: 700; font-size: 15px; cursor: pointer; }

        /* ==========================================
           GRID VIEW DYNAMIC CSS (BASE)
        ========================================== */
        ul.products.cppm-strict-grid { display: grid !important; grid-template-columns: repeat(2, 1fr) !important; gap: 12px !important; margin: 0 0 40px 0 !important; list-style: none !important; }
        ul.products.cppm-strict-grid::before, ul.products.cppm-strict-grid::after { display: none !important; }
        ul.products li.product { background: #ffffff; border-radius: 12px; padding: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between; border: 1px solid #f1f5f9; transition: 0.2s; font-family: system-ui, sans-serif;}
        ul.products li.product:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.08); transform: translateY(-3px); border-color: #cbd5e1; }
        
        /* THE BULLETPROOF IMAGE ISOLATION FIX */
        ul.products li.product .astra-shop-thumbnail-wrap { display: block !important; margin-bottom: 10px; position: relative; } 
        
        ul.products li.product .astra-shop-thumbnail-wrap > img,
        ul.products li.product .astra-shop-thumbnail-wrap > a > img,
        ul.products li.product .astra-shop-thumbnail-wrap > .onsale,
        ul.products li.product .astra-shop-thumbnail-wrap > .ast-onsale-card { display: none !important; }
        ul.products li.product .star-rating { display: none !important; }
        
        ul.products li.product .astra-shop-thumbnail-wrap .cppm-image-container { display: block !important; position: relative; width: 100%; border-radius: 8px; overflow: hidden; }
        ul.products li.product .astra-shop-thumbnail-wrap .cppm-image-container img { display: block !important; width: 100% !important; height: auto !important; border-radius: 8px; }

        /* Style the Global Badges inside our container */
        .cppm-abs-sale-badge { position: absolute !important; top: 6px !important; left: 6px !important; background-color: #0275d8 !important; color: #ffffff !important; font-size: 10px !important; font-weight: 800 !important; padding: 4px 6px !important; border-radius: 4px !important; z-index: 9 !important; box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important; display: inline-block !important; }
        
        .cppm-image-container .cppm-abs-rating { position: absolute !important; bottom: 24px !important; left: 6px !important; background: rgba(255, 255, 255, 0.95) !important; padding: 3px 6px !important; border-radius: 4px !important; display: flex !important; align-items: center !important; gap: 3px !important; z-index: 9 !important; box-shadow: 0 2px 5px rgba(0,0,0,0.2) !important; line-height: 1 !important; }
        .cppm-image-container .cppm-abs-rating * { display: inline-block !important; }
        .cppm-rating-val { font-size: 11px !important; font-weight: 700 !important; color: #1e293b !important; }
        .cppm-star-icon { font-size: 12px !important; color: #f1c40f !important; margin-top: -1px !important; }
        .cppm-rating-sep { font-size: 10px !important; color: #cbd5e1 !important; margin: 0 1px !important; }
        .cppm-rating-count { font-size: 11px !important; font-weight: 600 !important; color: #64748b !important; }

        /* Card Text */
        ul.products li.product a { text-decoration: none !important; }
        ul.products li.product a.button { color: #ffffff !important; border-radius: 8px !important; margin-top: 10px !important; display: block; text-align: center; }
        ul.products li.product .woocommerce-loop-product__title { margin: 0 0 8px 0 !important; font-size: 14px; font-weight: 700; color: #1e293b; line-height: 1.3; }
        ul.products li.product .price { color: #0f172a; font-weight: 800; font-size: 15px; display:block; }
        ul.products li.product .price del { color: #94a3b8; font-size: 12px; font-weight: 500; margin-right: 6px; }

        /* ==========================================
           LIST VIEW (DESKTOP)
        ========================================== */
        ul.products.cppm-strict-grid.cppm-list-view { grid-template-columns: 1fr !important; }
        
        ul.products.cppm-strict-grid.cppm-list-view li.product { 
            display: grid !important; 
            grid-template-columns: 180px 1fr auto !important; 
            align-items: center !important; 
            gap: 25px !important; 
            padding: 20px !important; 
        }
        
        ul.products.cppm-strict-grid.cppm-list-view li.product .astra-shop-thumbnail-wrap { width: 100% !important; margin: 0 !important; }
        
        ul.products.cppm-strict-grid.cppm-list-view li.product .astra-shop-summary-wrap { 
            display: flex !important; flex-direction: column !important; justify-content: center !important; text-align: left !important; gap: 6px !important; width: 100% !important; margin: 0 !important;
        }
        
        ul.products.cppm-strict-grid.cppm-list-view li.product .woocommerce-loop-product__title,
        ul.products.cppm-strict-grid.cppm-list-view li.product .ast-woo-product-category,
        ul.products.cppm-strict-grid.cppm-list-view li.product .price { margin: 0 !important; text-align: left !important; }
        ul.products.cppm-strict-grid.cppm-list-view li.product .woocommerce-loop-product__title { font-size: 18px !important; }
        
        ul.products.cppm-strict-grid.cppm-list-view li.product a.button { margin: 0 !important; padding: 12px 24px !important; white-space: nowrap !important; width: auto !important; height: fit-content !important; }

        /* Fallback unwrapper for themes wrapping everything in an A tag */
        ul.products.cppm-strict-grid.cppm-list-view li.product > a.woocommerce-LoopProduct-link { display: contents !important; }


        /* ==========================================
           RESPONSIVE BREAKPOINTS (THE ULTIMATE MOBILE FIX)
        ========================================== */
        @media (max-width: 768px) {
            .cppm-sticky-search { top: 0px; border-radius: 0 0 12px 12px; margin: -20px -20px 20px -20px; }
            .cppm-search-controls { justify-content: space-between; }
            #primary {margin: 2em 0 !important;}
            .cppm-abs-sale-badge { font-size: 9px !important; padding: 3px 5px !important; top: 4px !important; left: 4px !important; }
            .cppm-image-container .cppm-abs-rating { bottom: 24px !important; left: 4px !important; padding: 2px 4px !important; }
            .cppm-rating-val, .cppm-rating-count { font-size: 10px !important; }
            .cppm-star-icon { width: 10px !important; height: 10px !important; }

            /* MELT ASTRA'S WRAPPER ON MOBILE SO THE BUTTON IS FREE TO SPAN 100% */
            ul.products.cppm-strict-grid.cppm-list-view li.product .astra-shop-summary-wrap {
                display: contents !important;
            }

            /* Perfect Mobile Grid Array */
            ul.products.cppm-strict-grid.cppm-list-view li.product { 
                grid-template-columns: 110px 1fr !important; /* [Image] [Text] */
                gap: 8px 15px !important; 
                padding: 15px !important; 
                align-items: start !important;
            }
            
            ul.products.cppm-strict-grid.cppm-list-view li.product .astra-shop-thumbnail-wrap { 
                grid-column: 1 !important;
                grid-row: 1 / span 3 !important; /* Spans top 3 rows alongside text */
            }
            
            ul.products.cppm-strict-grid.cppm-list-view li.product .woocommerce-loop-product__title,
            ul.products.cppm-strict-grid.cppm-list-view li.product .ast-woo-product-category,
            ul.products.cppm-strict-grid.cppm-list-view li.product .price { 
                grid-column: 2 !important; 
                justify-self: start !important;
                text-align: left !important;
                margin: 0 !important;
            }
            
            ul.products.cppm-strict-grid.cppm-list-view li.product .woocommerce-loop-product__title { 
                font-size: 15px !important; 
            }
            
            /* FORCE BUTTON TO BREAK OUT AND SPAN 100% OF THE CARD */
            ul.products.cppm-strict-grid.cppm-list-view li.product a.button { 
                grid-column: 1 / -1 !important; /* Spans from far left to far right! */
                width: 100% !important; 
                text-align: center !important; 
                margin-top: 8px !important; 
                padding: 10px !important;
                grid-row: 4 !important; /* Drops to the very bottom row */
            }
        }

        @media (min-width: 768px) { ul.products.cppm-strict-grid:not(.cppm-list-view) { grid-template-columns: repeat(3, 1fr) !important; gap: 20px !important; } }
        @media (min-width: 1024px) { ul.products.cppm-strict-grid:not(.cppm-list-view) { grid-template-columns: repeat(4, 1fr) !important; } }
    </style>

    <div class="cppm-sticky-search">
        <h1 class="cppm-search-header-title">Showing results for: <span>"<?php echo esc_html($search_query); ?>"</span></h1>
        <div class="cppm-search-controls">
            <div class="cppm-search-input-fake cppm-trigger-search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <span><?php echo esc_html($search_query); ?></span>
            </div>
            <button type="button" class="cppm-filter-trigger" id="cppm-open-filters">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Filters
            </button>
            <div class="cppm-view-toggles">
                <button type="button" class="cppm-view-btn active" data-view="grid" title="Grid View">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </button>
                <button type="button" class="cppm-view-btn" data-view="list" title="List View">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                </button>
            </div>
        </div>
    </div>

    <div class="cppm-filter-overlay" id="cppm-search-filter-overlay">
        <div class="cppm-filter-modal">
            <div class="cppm-modal-header">
                <h3>Refine Search</h3>
                <button type="button" class="cppm-modal-close" id="cppm-close-filters">&times;</button>
            </div>
            <form method="GET" action="<?php echo esc_url(home_url('/')); ?>" class="cppm-modal-body">
                <input type="hidden" name="s" value="<?php echo esc_attr($search_query); ?>">
                <input type="hidden" name="post_type" value="product">
                
                <div class="cppm-filter-group">
                    <label>Category</label>
                    <select name="category" class="cppm-filter-select">
                        <option value="">All Categories</option>
                        <?php 
                        if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
                            foreach ( $categories as $cat ) {
                                if ( $cat->slug === 'uncategorized' ) continue;
                                echo '<option value="' . esc_attr($cat->slug) . '" ' . selected($selected_cat, $cat->slug, false) . '>' . esc_html($cat->name) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="cppm-filter-group">
                    <label>Sort By</label>
                    <select name="orderby" class="cppm-filter-select">
                        <option value="relevance" <?php selected($orderby, 'relevance'); ?>>Relevance</option>
                        <option value="popularity" <?php selected($orderby, 'popularity'); ?>>Popularity</option>
                        <option value="rating" <?php selected($orderby, 'rating'); ?>>Average Rating</option>
                        <option value="date" <?php selected($orderby, 'date'); ?>>Latest</option>
                        <option value="price" <?php selected($orderby, 'price'); ?>>Price: Low to High</option>
                        <option value="price-desc" <?php selected($orderby, 'price-desc'); ?>>Price: High to Low</option>
                    </select>
                </div>
                <div class="cppm-filter-group">
                    <label>Price Range (₹)</label>
                    <div class="cppm-price-row">
                        <input type="number" name="min_price" placeholder="Min" value="<?php echo $min_price; ?>">
                        <span style="color:#cbd5e1; font-weight:bold;">-</span>
                        <input type="number" name="max_price" placeholder="Max" value="<?php echo $max_price; ?>">
                    </div>
                </div>
                <div class="cppm-filter-group">
                    <label>Rating</label>
                    <select name="min_rating" class="cppm-filter-select">
                        <option value="">Any Rating</option>
                        <option value="4" <?php selected($min_rating, '4'); ?>>4★ & Above</option>
                        <option value="3" <?php selected($min_rating, '3'); ?>>3★ & Above</option>
                    </select>
                </div>
                <div class="cppm-filter-group">
                    <label class="cppm-check-row">
                        <input type="checkbox" name="on_sale" value="1" <?php echo $on_sale; ?>> Show only items on Sale
                    </label>
                </div>
                <button type="submit" class="cppm-apply-btn">Apply Filters</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('cppm-search-filter-overlay');
            const openBtn = document.getElementById('cppm-open-filters');
            const closeBtn = document.getElementById('cppm-close-filters');

            if(openBtn) { openBtn.addEventListener('click', () => { overlay.classList.add('open'); document.body.style.overflow = 'hidden'; }); }
            if(closeBtn) { closeBtn.addEventListener('click', () => { overlay.classList.remove('open'); document.body.style.overflow = ''; }); }
            if(overlay) { overlay.addEventListener('click', e => { if (e.target === overlay) { overlay.classList.remove('open'); document.body.style.overflow = ''; } }); }

            const viewBtns = document.querySelectorAll('.cppm-view-btn');
            const productGrid = document.querySelector('ul.products');
            const savedView = localStorage.getItem('cppm_shop_view') || 'grid';
            applyView(savedView);

            viewBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const view = this.getAttribute('data-view');
                    applyView(view);
                    localStorage.setItem('cppm_shop_view', view);
                });
            });

            function applyView(view) {
                if (!productGrid) return;
                viewBtns.forEach(b => b.classList.remove('active'));
                const activeBtn = document.querySelector('.cppm-view-btn[data-view="'+view+'"]');
                if (activeBtn) activeBtn.classList.add('active');
                if (view === 'list') { productGrid.classList.add('cppm-list-view'); } 
                else { productGrid.classList.remove('cppm-list-view'); }
            }
        });
    </script>
    <?php
}

add_filter( 'woocommerce_product_loop_start', 'cppm_global_strict_grid', 99 );
function cppm_global_strict_grid( $html ) {
    return '<ul class="products columns-4 cppm-strict-grid">';
}