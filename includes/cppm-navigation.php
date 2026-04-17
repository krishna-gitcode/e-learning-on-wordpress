<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================
// 1. DYNAMICALLY HIDE "MY CLASSROOM" IN MENU
// ==========================================
add_filter( 'wp_nav_menu_objects', 'cppm_hide_classroom_menu_item', 10, 2 );
function cppm_hide_classroom_menu_item( $items, $args ) {
    if ( is_user_logged_in() ) {
        return $items;
    }

    foreach ( $items as $key => $item ) {
        if ( strtolower( trim( $item->title ) ) === 'my classroom' ) {
            unset( $items[$key] );
        }
    }

    return $items;
}

// ==========================================
// 2. SMART ICON-BASED DROPDOWN MENU (FLIPKART STYLE)
// ==========================================
add_shortcode( 'cppm_auth_icon', 'cppm_render_auth_icon' );
function cppm_render_auth_icon() {
    ob_start();
    
    $is_logged_in = is_user_logged_in();
    $ui_brand     = get_option('cppm_ui_btn_color', '#2874f0'); // Defaulting to Flipkart Blue
    
    // URLs
    $my_account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url( home_url() );
    $orders_url     = function_exists('wc_get_endpoint_url') ? wc_get_endpoint_url( 'orders', '', $my_account_url ) : $my_account_url;
    $addresses_url  = function_exists('wc_get_endpoint_url') ? wc_get_endpoint_url( 'edit-address', '', $my_account_url ) : $my_account_url;
    $classroom_url  = get_permalink( 43 ); // Your Classroom Page ID
    $logout_url     = wp_logout_url( home_url() );
    
    // User SVG
    $icon_user = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';

    $greeting = 'Login';
    
    if ( $is_logged_in ) {
        $current_user = wp_get_current_user();
        
        // 1. Get the first name, fallback to display name
        $raw_name = !empty($current_user->user_firstname) ? $current_user->user_firstname : $current_user->display_name;
        
        // 2. Extract just the first word
        $first_word = strtok($raw_name, ' ');
        
        // 3. We have more space! Truncate if it's longer than 10 characters now
        if ( mb_strlen( $first_word ) > 10 ) {
            // Slices it to 9 chars and adds the ...
            $greeting = mb_substr( $first_word, 0, 9 ) . '&hellip;';
        } else {
            $greeting = $first_word;
        }
    }

    ?>
    <style>
        .cppm-auth-wrap { position: relative; display: inline-block; font-family: system-ui, sans-serif; z-index: 10000; }
        
        /* The Trigger Button */
        .cppm-auth-trigger { display: flex; align-items: center; gap: 8px; cursor: pointer; color: #1e293b; font-weight: 600; font-size: 15px; text-decoration: none; padding: 8px 12px; border-radius: 8px; transition: 0.2s; white-space: nowrap; }
        .cppm-auth-trigger:hover { background: #f8fafc; color: var(--brand); }
        .cppm-auth-trigger svg { color: var(--brand); flex-shrink: 0; }
        
        /* The Name Text Box - Strictly prevents wrapping */
        .cppm-auth-name {
            max-width: 120px; /* Increased for desktop */
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            vertical-align: middle;
        }
        
        /* Dropdown styling */
        .cppm-auth-dropdown { visibility: hidden; opacity: 0; position: absolute; top: calc(100% + 5px); right: 0; background: #ffffff; min-width: 240px; border-radius: 4px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; display: flex; flex-direction: column; transition: all 0.2s ease; transform: translateY(-10px); padding: 8px 0; }
        
        .cppm-auth-wrap:hover .cppm-auth-dropdown, .cppm-auth-wrap:focus-within .cppm-auth-dropdown { visibility: visible; opacity: 1; transform: translateY(0); }
        .cppm-auth-wrap::after { content: ''; position: absolute; top: 100%; left: 0; width: 100%; height: 15px; }
        
        .cppm-dropdown-header { padding: 10px 20px 6px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; margin-bottom: 4px; }
        
        .cppm-auth-dropdown a { padding: 12px 20px; color: #334155; text-decoration: none; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 14px; transition: 0.2s; }
        .cppm-auth-dropdown a svg { width: 18px; height: 18px; color: #64748b; transition: 0.2s; }
        .cppm-auth-dropdown a:hover { background: #f8fafc; color: var(--brand); }
        .cppm-auth-dropdown a:hover svg { color: var(--brand); }

        /* MOBILE FIXES: Header Spacing & Cart Overlap */
        @media (max-width: 768px) {
            /* We have more space now! Increasing font size and padding */
            .cppm-auth-trigger { padding: 8px 10px; gap: 6px; font-size: 14px; }
            .cppm-auth-trigger svg { width: 20px; height: 20px; }
            
            /* Expanded from 65px to 100px to show more of the name */
            .cppm-auth-name { max-width: 100px; } 
            
            /* Pushes generic floating carts up above the bottom nav */
            .xoo-wsc-basket, .wpc-side-cart-trigger, .floating-cart-btn, .wc-block-mini-cart, #ast-site-header-cart {
                bottom: 80px !important;
            }
        }
    </style>

    <div class="cppm-auth-wrap" style="--brand: <?php echo esc_attr( $ui_brand ); ?>;" tabindex="0">
        <?php if ( ! $is_logged_in ) : ?>
            <a href="<?php echo esc_url( $my_account_url ); ?>" title="Log In" class="cppm-auth-trigger">
                <?php echo $icon_user; ?> <span class="cppm-auth-name">Login</span>
            </a>
        <?php else : ?>
            <div class="cppm-auth-trigger">
                <?php echo $icon_user; ?> <span class="cppm-auth-name"><?php echo esc_html($greeting); ?></span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#94a3b8; flex-shrink:0;"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </div>
            
            <div class="cppm-auth-dropdown">
                <div class="cppm-dropdown-header">Your Account</div>
                <a href="<?php echo esc_url( $my_account_url ); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"></circle><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path></svg> My Profile
                </a>
                <a href="<?php echo esc_url( $orders_url ); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg> Orders
                </a>
                <a href="<?php echo esc_url( $classroom_url ); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg> My Classroom
                </a>
                <a href="<?php echo esc_url( $addresses_url ); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> Saved Addresses
                </a>
                <a href="<?php echo esc_url( $logout_url ); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Logout
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ==========================================
// 3. MOBILE APP BOTTOM NAV & POPUP SEARCH
// ==========================================
add_action( 'wp_footer', 'cppm_mobile_nav_and_search_overlay' );
function cppm_mobile_nav_and_search_overlay() {
    $home_url      = home_url();
    $classroom_url = get_permalink( 43 );
    $profile_url   = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
    $ui_brand      = get_option('cppm_ui_btn_color', '#2874f0');
    ?>
    <style>
        /* Mobile Bottom Nav Styles */
        .cppm-bottom-nav { display: none; position: fixed; bottom: 0; left: 0; width: 100%; background: #ffffff; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); z-index: 9998; padding-bottom: env(safe-area-inset-bottom); border-top: 1px solid #e2e8f0; }
        @media (max-width: 768px) {
            .cppm-bottom-nav { display: flex; justify-content: space-around; align-items: center; }
            body { padding-bottom: 70px; /* Prevent footer overlap */ }
        }
        .cppm-nav-item { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 10px 0; color: #64748b; text-decoration: none; font-family: system-ui, sans-serif; transition: 0.2s; }
        .cppm-nav-item:hover, .cppm-nav-item.active { color: var(--brand); }
        .cppm-nav-item svg { width: 22px; height: 22px; margin-bottom: 4px; }
        .cppm-nav-item span { font-size: 10px; font-weight: 600; letter-spacing: 0.3px; }

        /* Fullscreen Search Modal Styles */
        .cppm-search-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #ffffff; z-index: 10005; display: none; flex-direction: column; opacity: 0; transition: opacity 0.3s ease; }
        .cppm-search-modal.open { display: flex; opacity: 1; }
        .cppm-search-header { display: flex; align-items: center; gap: 10px; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .cppm-close-search { background: transparent; border: none; color: #64748b; padding: 5px; cursor: pointer; }
        .cppm-live-search-input { flex: 1; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 15px; font-size: 16px; outline: none; transition: 0.2s; }
        .cppm-live-search-input:focus { border-color: var(--brand); box-shadow: 0 0 0 2px rgba(40,116,240,0.1); }
        
        /* Search Results Styles */
        .cppm-search-results-area { flex: 1; overflow-y: auto; padding: 0; margin: 0; list-style: none; background: #ffffff; }
        .cppm-search-result-item { display: flex; align-items: center; padding: 12px 20px; border-bottom: 1px solid #f1f5f9; text-decoration: none; transition: 0.2s; }
        .cppm-search-result-item:hover { background: #f8fafc; }
        .cppm-search-thumb { width: 50px; height: 50px; border-radius: 6px; object-fit: cover; margin-right: 15px; border: 1px solid #e2e8f0; }
        .cppm-search-info { flex: 1; }
        .cppm-search-title { color: #1e293b; font-size: 14px; font-weight: 600; margin: 0 0 4px 0; font-family: system-ui, sans-serif; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .cppm-search-price { color: var(--brand); font-size: 13px; font-weight: 700; margin: 0; }
        .cppm-search-loading { text-align: center; padding: 30px; color: #64748b; font-family: system-ui; display: none; }
    </style>

    <div class="cppm-bottom-nav" style="--brand: <?php echo esc_attr( $ui_brand ); ?>;">
        <a href="<?php echo esc_url($home_url); ?>" class="cppm-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span>Home</span>
        </a>
        <a href="#" class="cppm-nav-item cppm-trigger-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <span>Search</span>
        </a>
        <a href="<?php echo esc_url($classroom_url); ?>" class="cppm-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
            <span>Classroom</span>
        </a>
        <a href="<?php echo esc_url($profile_url); ?>" class="cppm-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            <span>Profile</span>
        </a>
        <a href="<?php echo esc_url( home_url('/contact-us') ); ?>" class="cppm-nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            <span>Help</span>
        </a>
    </div>

    <div class="cppm-search-modal" id="cppm-search-modal" style="--brand: <?php echo esc_attr( $ui_brand ); ?>;">
        <div class="cppm-search-header">
            <button class="cppm-close-search" id="cppm-close-search">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </button>
            <form action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" style="flex:1; margin:0; display:flex;">
                <input type="search" name="s" id="cppm-live-search-box" class="cppm-live-search-input" placeholder="Search for Courses, Books, Drums..." autocomplete="off" required>
                <input type="hidden" name="post_type" value="product" />
            </form>
        </div>
        <div class="cppm-search-loading" id="cppm-search-loader">Searching...</div>
        <ul class="cppm-search-results-area" id="cppm-search-results">
            </ul>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('cppm-search-modal');
        const triggers = document.querySelectorAll('.cppm-trigger-search');
        const closeBtn = document.getElementById('cppm-close-search');
        const searchBox = document.getElementById('cppm-live-search-box');
        const resultsArea = document.getElementById('cppm-search-results');
        const loader = document.getElementById('cppm-search-loader');
        
        let typingTimer;
        const doneTypingInterval = 400; // wait 400ms after user stops typing
        const ajaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";

        // Open Modal
        triggers.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                modal.classList.add('open');
                document.body.style.overflow = 'hidden'; // prevent background scrolling
                setTimeout(() => searchBox.focus(), 100);
            });
        });

        // Close Modal
        closeBtn.addEventListener('click', function() {
            modal.classList.remove('open');
            document.body.style.overflow = '';
        });

        // Live Search AJAX Logic
        searchBox.addEventListener('keyup', function() {
            clearTimeout(typingTimer);
            if (searchBox.value.length > 2) {
                typingTimer = setTimeout(performLiveSearch, doneTypingInterval);
            } else {
                resultsArea.innerHTML = '';
            }
        });

        function performLiveSearch() {
            resultsArea.innerHTML = '';
            loader.style.display = 'block';

            const formData = new FormData();
            formData.append('action', 'cppm_live_search');
            formData.append('keyword', searchBox.value);

            fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                loader.style.display = 'none';
                if(data.success) {
                    resultsArea.innerHTML = data.data;
                } else {
                    resultsArea.innerHTML = '<li style="padding:20px; text-align:center; color:#64748b;">No products found.</li>';
                }
            }).catch(err => {
                loader.style.display = 'none';
            });
        }
    });
    </script>
    <?php
}


// ==========================================
// 4. LIVE SEARCH AJAX HANDLER
// ==========================================
add_action( 'wp_ajax_cppm_live_search', 'cppm_ajax_live_search_handler' );
add_action( 'wp_ajax_nopriv_cppm_live_search', 'cppm_ajax_live_search_handler' );

function cppm_ajax_live_search_handler() {
    $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
    
    if ( empty($keyword) ) {
        wp_send_json_error();
    }

    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        's'              => $keyword,
        'posts_per_page' => 8, // Show top 8 results in the popup
    );

    $query = new WP_Query( $args );
    $html = '';

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            global $product;
            
            $title = get_the_title();
            $link  = get_permalink();
            $price = $product->get_price_html();
            
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src();

            $html .= '<a href="' . esc_url($link) . '" class="cppm-search-result-item">';
            $html .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '" class="cppm-search-thumb">';
            $html .= '<div class="cppm-search-info">';
            $html .= '<h4 class="cppm-search-title">' . esc_html($title) . '</h4>';
            $html .= '<p class="cppm-search-price">' . wp_kses_post($price) . '</p>';
            $html .= '</div></a>';
        }
        wp_send_json_success($html);
    } else {
        wp_send_json_error();
    }
    
    wp_die();
}

// ==========================================
// 5. MOBILE APP HEADER SLIM-DOWN FIX (LITESPEED BULLETPROOF)
// ==========================================
add_action('wp_head', 'cppm_slim_mobile_header_css', 9999);
function cppm_slim_mobile_header_css() {
    ?>
    <style>
        /* Forcefully shrink Astra's bulky header on mobile devices */
        @media (max-width: 921px) {
            
            /* 1. Nuke padding on all mobile header wrappers GLOBALLY */
            .ast-mobile-header-wrap .ast-primary-header-bar,
            .ast-mobile-header-wrap .ast-builder-grid-row,
            .ast-mobile-header-wrap .site-header-primary-section-left,
            .ast-mobile-header-wrap .site-header-primary-section-right { 
                min-height: 60px !important; 
                padding: 0 6px !important; 
                margin: 0 !important; 
                align-items: center !important;
            }
            
            /* 2. Strip hidden margins/padding around the Site Logo */
            .ast-mobile-header-wrap .site-branding,
            .ast-mobile-header-wrap .site-logo-img,
            .ast-mobile-header-wrap .ast-site-title-wrap,
            .ast-mobile-header-wrap .ast-builder-layout-element { 
                padding: 0 !important; 
                margin: 0 !important;
                line-height: 1 !important;
            }
            
            .ast-mobile-header-wrap .custom-logo-link img {
                max-height: 40px !important; /* Force logo to be sleek */
                width: auto !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            /* 3. Strip padding from the <aside> Widget wrapper */
            .ast-mobile-header-wrap .header-widget-area,
            .ast-mobile-header-wrap .widget_block {
                padding: 0 !important;
                margin: 0 !important;
                line-height: 1 !important;
            }
            
            /* 4. Kill WordPress auto-injected <p> tags completely */
            .ast-mobile-header-wrap .widget_block p {
                margin: 0 !important;
                padding: 0 !important;
                display: none !important; /* Destroys the empty <p> gaps */
            }

            .ast-mobile-header-wrap .widget_block .cppm-auth-wrap {
                margin: 0 !important;
                padding: 0 !important;
                display: inline-block !important;
            }
        }
    </style>
    <?php
}