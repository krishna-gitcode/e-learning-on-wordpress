<?php
/**
 * Phase 3: Modern Single Product Overhaul (Astra Bulletproof)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. CLEAN UP DEFAULT WOOCOMMERCE & ASTRA CLUTTER
// ==========================================
add_action( 'wp', 'cppm_clean_single_product_layout' );
function cppm_clean_single_product_layout() {
    if ( is_product() ) {
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
        // Nuke Astra's category hooks across all possible priorities
        remove_action( 'woocommerce_single_product_summary', 'astra_woo_single_product_category', 5 );
        remove_action( 'woocommerce_single_product_summary', 'astra_woo_single_product_category', 10 );
        remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
    }
}

// ==========================================
// 2. INJECT MODERN UI ELEMENTS
// ==========================================

add_action( 'woocommerce_single_product_summary', 'cppm_custom_single_categories', 4 );
function cppm_custom_single_categories() {
    global $product;
    $cats = wc_get_product_category_list( $product->get_id(), '', '<div class="cppm-custom-categories">', '</div>' );
    if ( $cats ) {
        echo $cats;
    }
}

add_action( 'woocommerce_product_thumbnails', 'cppm_custom_single_sale_badge', 9 );
function cppm_custom_single_sale_badge() {
    global $product;
    if ( $product->is_on_sale() ) {
        echo '<span class="cppm-single-sale-badge">SALE!</span>';
    }
}


add_action( 'woocommerce_single_product_summary', 'cppm_add_product_highlights', 15 );
function cppm_add_product_highlights() {
    global $product;
    
    // 1. PHYSICAL PRODUCT CHECK: 
    // If the product is NOT virtual (meaning it requires shipping, like a Bugle), show NO badges.
    if ( ! $product->is_virtual() ) {
        return;
    }

    // 2. E-BOOK CHECK: 
    // Check if this product belongs to your e-books category (Checks for common slugs).
    $is_ebook = has_term( array( 'e-books', 'ebooks', 'e-book', 'ebook' ), 'product_cat', $product->get_id() );

    // 3. DURATION LOGIC:
    // Check for a custom duration meta key, otherwise default to Lifetime Access.
    $duration = get_post_meta( $product->get_id(), '_course_duration', true );
    $access_text = !empty($duration) ? esc_html($duration) : 'Lifetime Access';
    
    ?>
    <div class="cppm-product-highlights">
        <div class="cppm-highlight-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
            <?php echo $access_text; ?>
        </div>

        <?php 
        // 4. CERTIFICATE CHECK:
        // Only show the certificate badge if the product is NOT an e-book.
        if ( ! $is_ebook ) : 
        ?>
            <div class="cppm-highlight-item">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Certificate Included
            </div>
        <?php endif; ?>
    </div>
    <?php
}
// ==========================================
// 3. GLOBAL CSS FOR THE "PRO APP" LOOK
// ==========================================
add_action( 'wp_head', 'cppm_single_product_styles', 9999 );
function cppm_single_product_styles() {
    if ( ! is_product() ) return;
    ?>
    <style>
        /* 1. BREADCRUMBS MODERNIZATION */
        body.single-product .woocommerce-breadcrumb { font-size: 13px !important; color: #94a3b8 !important; margin-bottom: 20px !important; background: transparent !important; padding: 0 !important; font-weight: 500; }
        body.single-product .woocommerce-breadcrumb a { color: #2874f0 !important; text-decoration: none !important; font-weight: 600; transition: 0.2s; }
        body.single-product .woocommerce-breadcrumb a:hover { color: #1e293b !important; }

        /* 2. HEADER & CATEGORY UI (ABSOLUTE KILL FOR DUPLICATES) */
        body.single-product div.product_meta, 
        body.single-product span.posted_in, 
        body.single-product .ast-woo-product-category,
        body.single-product .single-product-category { 
            display: none !important; 
            visibility: hidden !important; 
            position: absolute !important; 
            top: -9999px !important; 
            height: 0 !important; 
            width: 0 !important; 
        } 
        
        body.single-product .cppm-custom-categories { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        body.single-product .cppm-custom-categories a { background: #e0e7ff; color: #3730a3; padding: 6px 12px; border-radius: 50px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; text-decoration: none; transition: 0.2s; border: 1px solid #c7d2fe; }
        body.single-product .cppm-custom-categories a:hover { background: #3730a3; color: #ffffff; }

        body.single-product .product_title { font-size: 32px !important; font-weight: 800 !important; color: #0f172a; line-height: 1.2; margin-bottom: 15px !important; font-family: system-ui, sans-serif !important; letter-spacing: -0.5px; }
        body.single-product .summary .price .woocommerce-Price-amount { color: #2874f0; font-weight: 800; font-size: 26px; }
        body.single-product .summary .price del .woocommerce-Price-amount { color: #94a3b8 !important; font-size: 16px !important; font-weight: 500 !important; margin-right: 8px; }

        /* 3. MAIN PRODUCT IMAGE MODERNIZATION */
        body.single-product .woocommerce-product-gallery { max-width: 500px !important; margin: 0 auto 30px auto !important; position: relative; }
        body.single-product .woocommerce-product-gallery__image { border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid #f1f5f9; }
        body.single-product .woocommerce-product-gallery__image img { border-radius: 16px; width: 100% !important; height: auto !important; display: block; }
        
        body.single-product .cppm-single-sale-badge { position: absolute; top: 15px; left: 15px; background: linear-gradient(135deg, #2874f0 0%, #1e40af 100%); color: #ffffff; font-size: 12px; font-weight: 800; padding: 6px 12px; border-radius: 6px; z-index: 100; box-shadow: 0 4px 10px rgba(40,116,240,0.3); text-transform: uppercase; letter-spacing: 1px; }
        body.single-product .ast-onsale-card, body.single-product span.onsale { display: none !important; }

        /* 4. DIGITAL HIGHLIGHTS */
        body.single-product .cppm-product-highlights { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin: 25px 0; padding-top: 20px; border-top: 1px solid #f1f5f9; }
        body.single-product .cppm-highlight-item { background: #ffffff; padding: 12px 15px; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 700; color: #334155; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        body.single-product .cppm-highlight-item svg { color: #2874f0; flex-shrink: 0; }

        /* 5. REVIEWS SECTION MODERNIZATION (ASTRA TARGETED) */
        body.single-product #reviews { background: #f8fafc; padding: 30px; border-radius: 16px; border: 1px solid #e2e8f0; margin-top: 40px; }
        body.single-product #reviews #comments h2 { font-size: 22px; font-weight: 800; margin-bottom: 20px; color: #0f172a; }
        body.single-product .woocommerce-Reviews .commentlist { padding: 0 !important; margin: 0 !important; list-style: none !important; }
        body.single-product .woocommerce-Reviews .commentlist li { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); transition: 0.2s; }
        body.single-product .woocommerce-Reviews .commentlist li img.avatar { width: 45px; height: 45px; border-radius: 50%; padding: 0; border: none; background: #f1f5f9; }
        body.single-product .woocommerce-Reviews .commentlist li .comment-text { margin-left: 60px; border: none; padding: 0; }
        body.single-product .woocommerce-Reviews .commentlist li .meta { font-size: 12px; color: #64748b; margin-bottom: 8px; display: flex; flex-direction: column; gap: 2px;}
        body.single-product .woocommerce-Reviews .commentlist li .meta strong { color: #0f172a; font-size: 15px; font-weight: 700; }
        body.single-product .woocommerce-Reviews .commentlist li .description { color: #334155; font-size: 14px; line-height: 1.6; margin-top: 10px;}
        
        /* Modern Review Submission Form - Borders & Margins Tweaked! */
        .woocommerce-js div.product #reviews #review_form_wrapper,
        body.single-product #respond.comment-respond, 
        body.single-product #review_form_wrapper { 
            margin-top: 0 !important; 
            background: #ffffff !important; 
            padding: 12px !important; 
            border-radius: 12px !important; 
            border: none !important; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.03) !important; 
        }

        /* STRIP INNER FORM BORDER/PADDING */
        body.single-product .woocommerce-js #reviews #review_form,
        body.single-product #reviews #review_form {
            padding: 0 !important;
            border: none !important;
            margin: 0 !important;
        }
        
        body.single-product #respond .comment-reply-title { font-size: 18px !important; font-weight: 800 !important; color: #0f172a !important; display: block !important; margin-bottom: 15px !important; border: none !important; }
        body.single-product .comment-form-rating label { font-weight: 700 !important; color: #475569 !important; margin-bottom: 8px !important; display: block !important; }
        body.single-product .comment-form label { font-weight: 600 !important; color: #475569 !important; font-size: 13px !important; margin-bottom: 6px !important; display: block !important; }
        body.single-product .comment-form input[type="text"], body.single-product .comment-form input[type="email"], body.single-product .comment-form textarea { width: 100% !important; padding: 12px 15px !important; border: 1px solid #cbd5e1 !important; border-radius: 8px !important; background: #f8fafc !important; font-size: 14px !important; color: #1e293b !important; box-sizing: border-box !important; }
        body.single-product .comment-form input[type="text"]:focus, body.single-product .comment-form input[type="email"]:focus, body.single-product .comment-form textarea:focus { outline: none !important; border-color: #2874f0 !important; background: #ffffff !important; }
        body.single-product .comment-form .submit { background: #2874f0 !important; color: #ffffff !important; padding: 14px 24px !important; border-radius: 8px !important; font-weight: 700 !important; font-size: 15px !important; border: none !important; cursor: pointer !important; text-transform: uppercase !important; width: 100% !important; margin-top: 10px !important;}

        /* 6. RELATED PRODUCTS HORIZONTAL CAROUSEL */
        body.single-product .related.products { margin-top: 60px; border-top: 1px solid #e2e8f0; padding-top: 40px; }
        body.single-product .related.products > h2 { font-size: 26px; font-weight: 800; margin-bottom: 25px; color: #0f172a; letter-spacing: -0.5px; }
        
        body.single-product .related.products ul.products { 
            display: flex !important; flex-wrap: nowrap !important; overflow-x: auto !important; overflow-y: hidden !important; gap: 20px !important; padding-bottom: 30px !important; margin-left: -15px; padding-left: 15px; scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; scroll-snap-type: x mandatory; scroll-behavior: smooth;
        }
        body.single-product .related.products ul.products::-webkit-scrollbar { height: 6px; }
        body.single-product .related.products ul.products::-webkit-scrollbar-track { background: transparent; }
        body.single-product .related.products ul.products::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
        
        body.single-product .related.products ul.products li.product { 
            flex: 0 0 200px !important; max-width: 200px !important; margin: 0 !important; scroll-snap-align: start; background: #ffffff; border-radius: 14px; padding: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between; border: 1px solid #e2e8f0; transition: 0.2s;
        }
        
        body.single-product .related.products ul.products li.product .astra-shop-thumbnail-wrap { display: block !important; margin-bottom: 12px; position: relative; } 
        body.single-product .related.products ul.products li.product .astra-shop-thumbnail-wrap > img, 
        body.single-product .related.products ul.products li.product .astra-shop-thumbnail-wrap > a > img, 
        body.single-product .related.products ul.products li.product .astra-shop-thumbnail-wrap > .onsale, 
        body.single-product .related.products ul.products li.product .astra-shop-thumbnail-wrap > .ast-onsale-card { display: none !important; }
        body.single-product .related.products ul.products li.product .star-rating { display: none !important; }
        
        body.single-product .related.products ul.products li.product .astra-shop-thumbnail-wrap .cppm-image-container { display: block !important; position: relative; width: 100%; border-radius: 8px; overflow: hidden; }
        body.single-product .related.products ul.products li.product .astra-shop-thumbnail-wrap .cppm-image-container img { display: block !important; width: 100% !important; height: auto !important; border-radius: 8px; }

        body.single-product .cppm-abs-sale-badge { position: absolute !important; top: 6px !important; left: 6px !important; background-color: #0275d8 !important; color: #ffffff !important; font-size: 10px !important; font-weight: 800 !important; padding: 4px 6px !important; border-radius: 4px !important; z-index: 9 !important; box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important; display: inline-block !important; }
        body.single-product .cppm-image-container .cppm-abs-rating { position: absolute !important; bottom: 26px !important; left: 6px !important; background: rgba(255, 255, 255, 0.95) !important; padding: 3px 6px !important; border-radius: 4px !important; display: flex !important; align-items: center !important; gap: 3px !important; z-index: 9 !important; line-height: 1 !important; }
        body.single-product .cppm-rating-val { font-size: 11px !important; font-weight: 700 !important; color: #1e293b !important; }
        body.single-product .cppm-star-icon { font-size: 12px !important; color: #f1c40f !important; margin-top: -1px !important; }
        body.single-product .cppm-rating-count { font-size: 11px !important; font-weight: 600 !important; color: #64748b !important; }

        body.single-product .related.products ul.products li.product a { text-decoration: none !important; display: flex; flex-direction: column; height: auto !important; }
        body.single-product .related.products ul.products li.product a.button { color: #ffffff !important; border-radius: 8px !important; margin-top: 12px !important; display: block; text-align: center; font-weight: 700; padding: 10px !important; transition: 0.2s; }
        body.single-product .related.products ul.products li.product .woocommerce-loop-product__title { margin: 0 0 8px 0 !important; font-size: 14px !important; font-weight: 700; color: #0f172a; line-height: 1.3; flex-grow: 1;}
        
        /* 7. FIX: ABSOLUTE PRICE STACKING FOR RELATED PRODUCTS */
        body.single-product .related.products ul.products li.product .price { 
            width: 100% !important; 
            display: block !important; 
            text-align: left !important; 
            margin: 0 !important; 
        }
        body.single-product .related.products ul.products li.product .price del,
        body.single-product .related.products ul.products li.product .price ins,
        body.single-product .related.products ul.products li.product .price > .woocommerce-Price-amount { 
            display: block !important; /* Forces them onto separate lines */
            width: 100% !important; 
            text-align: left !important; 
            line-height: 1.3 !important; 
        }
        body.single-product .related.products ul.products li.product .price del { color: #94a3b8 !important; font-size: 12px !important; font-weight: 500 !important; margin-bottom: 2px !important; }
        body.single-product .related.products ul.products li.product .price ins { text-decoration: none !important; color: #2874f0 !important; font-weight: 800 !important; font-size: 16px !important; }

        /* 8. RESPONSIVE MOBILE TWEAKS */
        @media (max-width: 768px) {
            body.single-product .product_title { font-size: 26px !important; }
            body.single-product .woocommerce-product-gallery { margin-top: 20px !important; margin-bottom: 20px !important; }
            
            body.single-product .related.products ul.products li.product { flex: 0 0 150px !important; max-width: 150px !important; padding: 10px !important;}
            body.single-product .related.products ul.products li.product .woocommerce-loop-product__title { font-size: 13px !important; }
            
            body.single-product .cppm-mobile-sticky-buy { position: fixed; bottom: 0; left: 0; width: 100%; background: #ffffff; padding: 12px 20px; box-shadow: 0 -4px 15px rgba(0,0,0,0.1); z-index: 9999; display: flex; align-items: center; justify-content: space-between; gap: 15px; }
            body.single-product .cppm-mobile-sticky-buy .price { margin: 0 !important; font-size: 18px !important; }
            body.single-product .cppm-mobile-sticky-buy .button { flex: 1; margin: 0 !important; padding: 12px !important; border-radius: 8px !important; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; }
            body.single-product { padding-bottom: 140px !important; } 
        }
    </style>
    <?php
}