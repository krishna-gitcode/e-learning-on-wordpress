<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================
// 0. AJAX HANDLER
// ==========================================
add_action( 'wp_ajax_cppm_load_dashboard_tab', 'cppm_ajax_load_dashboard_tab' );
function cppm_ajax_load_dashboard_tab() {
    check_ajax_referer( 'cppm_dashboard_ajax_nonce', 'security' );

    $view = isset( $_POST['view'] ) ? sanitize_text_field( $_POST['view'] ) : 'courses';

    if ( $view === 'purchases' ) {
        cppm_render_custom_purchase_history();
    } elseif ( $view === 'certificates' ) {
        cppm_render_certificates_tab();
    } elseif ( $view === 'ebooks' ) {
        cppm_render_purchased_grid( 'ebook' );
    } else {
        cppm_render_purchased_grid( 'course' );
    }

    wp_die();
}

// ==========================================
// 1. UDEMY-STYLE CLASSROOM SHORTCODE
// ==========================================
add_shortcode( 'cppm_student_dashboard', 'cppm_render_udemy_classroom' );
function cppm_render_udemy_classroom() {
    if ( ! is_user_logged_in() ) {
        return '<div style="text-align:center; padding: 100px 20px;"><h2>Your Classroom Awaits</h2><p>Please log in to access your learning materials.</p><a href="' . wp_login_url( get_permalink() ) . '" style="display:inline-block; margin-top:20px; background:#1c1d1f; color:#fff; padding:12px 24px; text-decoration:none; font-weight:bold; border-radius: 8px;">Log In</a></div>';
    }

    ob_start();
    $current_view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'courses';
    $ajax_nonce   = wp_create_nonce( 'cppm_dashboard_ajax_nonce' );
    $base_url     = get_permalink();
    ?>
    
    <style>
        /* ==========================================
           CLASSROOM APP UI CSS
        ========================================== */
        .cppm-udemy-wrapper { margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }

        .cppm-udemy-header { background-color: #1c1d1f; padding: 40px 0 0 0; color: #ffffff; }
        .cppm-udemy-container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .cppm-udemy-header h1 { font-size: 32px; font-weight: 700; margin: 0 0 30px 0; color: #ffffff; }

        .cppm-udemy-tabs { display: flex; gap: 20px; list-style: none; margin: 0; padding: 0; overflow-x: auto; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
        .cppm-udemy-tabs::-webkit-scrollbar { display: none; }
        .cppm-udemy-tabs li { margin: 0; padding: 0; }
        .cppm-udemy-tabs li a { color: #d1d7dc !important; text-decoration: none !important; box-shadow: none !important; font-weight: 700; font-size: 16px; padding-bottom: 10px; display: block; border-bottom: 4px solid transparent !important; transition: color 0.2s; white-space: nowrap; cursor: pointer; }
        .cppm-udemy-tabs li a:hover { color: #ffffff !important; }
        .cppm-udemy-tabs li a.active { color: #ffffff !important; border-bottom: 4px solid #ffffff !important; }

        .cppm-udemy-content { max-width: 1200px; margin: 40px auto; padding: 0 24px; min-height: 50vh; position: relative; }

        .cppm-ajax-loader { display: flex; justify-content: center; align-items: center; min-height: 300px; width: 100%; }
        .cppm-spinner { width: 40px; height: 40px; border: 4px solid #f1f5f9; border-top: 4px solid #1c1d1f; border-radius: 50%; animation: cppm-spin 1s linear infinite; }
        @keyframes cppm-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .cppm-course-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
        .cppm-course-card { border: 1px solid #d1d7dc; background: #ffffff; transition: all 0.2s ease; display: flex; flex-direction: column; text-decoration: none !important; color: #1c1d1f !important; box-shadow: none !important; border-radius: 8px; overflow: hidden; }
        .cppm-course-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important; }
        
        /* Dashboard Card Image (16:9 ratio) */
        .cppm-card-image-wrapper { width: 100%; position: relative; background: #f7f9fa; }
        .cppm-card-image-wrapper img { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; display: block; border-bottom: 1px solid #d1d7dc; }
        
        .cppm-card-content { padding: 16px; display: flex; flex-direction: column; flex-grow: 1; }
        .cppm-card-title { font-size: 16px; font-weight: 700; line-height: 1.2; margin: 0 0 8px 0; color: #1c1d1f; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 38px; }
        .cppm-card-instructor { font-size: 12px; color: #6a6f73; margin: 0 0 16px 0; }
        .cppm-card-action { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #d1d7dc; padding-top: 12px; font-size: 12px; font-weight: 700; color: #1c1d1f; margin-top: auto; }
        .cppm-start-text { color: #1c1d1f; text-transform: uppercase; }

        .cppm-table-wrapper { overflow-x: auto; border: 1px solid #d1d7dc; border-radius: 8px; }
        .cppm-data-table { width: 100%; border-collapse: collapse; text-align: left; margin: 0 !important; font-size: 14px; }
        .cppm-data-table th { background: #f7f9fa; color: #1c1d1f; font-weight: 700; padding: 16px; border-bottom: 2px solid #d1d7dc; white-space: nowrap; }
        .cppm-data-table td { padding: 16px; border-bottom: 1px solid #d1d7dc; color: #1c1d1f; vertical-align: middle; }
        .cppm-btn-dark { background: #1c1d1f; color: #ffffff !important; text-decoration: none !important; padding: 8px 16px; border-radius: 4px; font-weight: 700; font-size: 13px; display: inline-block; transition: opacity 0.2s; white-space: nowrap; border: none; cursor: pointer; }
        .cppm-btn-dark:hover { opacity: 0.8; }
        .cppm-btn-disabled { background: #e2e8f0; color: #94a3b8 !important; padding: 8px 16px; border-radius: 4px; font-weight: 700; font-size: 13px; display: inline-block; cursor: not-allowed; border: none; }

        /* ==========================================
           RECOMMENDATION CAROUSEL CSS
        ========================================== */
        .cppm-rec-section { margin-top: 60px; padding-top: 30px; border-top: 1px solid #e2e8f0; }
        .cppm-rec-section h3 { font-size: 20px; font-weight: 800; color: #1e293b; margin-bottom: 20px; }
        
        .cppm-rec-carousel {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            padding-bottom: 16px;
            scrollbar-width: none; 
            -webkit-overflow-scrolling: touch;
            scroll-snap-type: x mandatory;
        }
        .cppm-rec-carousel::-webkit-scrollbar { display: none; }
        
        .cppm-rec-card {
            min-width: 160px; 
            max-width: 160px;
            flex-shrink: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none !important;
            color: #1e293b !important;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            scroll-snap-align: start;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        @media (min-width: 768px) {
            .cppm-rec-card { min-width: 200px; max-width: 200px; }
        }
        .cppm-rec-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        
        /* BULLETPROOF SQUARE THUMBNAIL */
        .cppm-rec-img { 
            width: 100%; 
            padding-top: 100%; /* Forces a perfect 1:1 aspect ratio */
            background: #f8fafc; 
            position: relative;
            overflow: hidden;
        }
        .cppm-rec-img img { 
            position: absolute;
            top: 0;
            left: 0;
            width: 100%; 
            height: 100%; 
            object-fit: cover !important; 
            display: block; 
        }
        
        .cppm-rec-details { padding: 12px; display: flex; flex-direction: column; flex-grow: 1; }
        .cppm-rec-title { font-size: 13px; font-weight: 700; line-height: 1.3; margin: 0 0 6px 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .cppm-rec-price { font-size: 14px; font-weight: 800; color: #1c1d1f; margin-top: auto; }
        .cppm-rec-price del { color: #94a3b8; font-size: 11px; font-weight: 500; margin-right: 4px; }
        .cppm-rec-price ins { text-decoration: none; }
    </style>

    <div class="cppm-udemy-wrapper">
        <header class="cppm-udemy-header">
            <div class="cppm-udemy-container">
                <h1>My Classroom</h1>
                <ul class="cppm-udemy-tabs">
                    <li><a data-view="courses" class="cppm-ajax-tab <?php echo $current_view === 'courses' ? 'active' : ''; ?>">Courses</a></li>
                    <li><a data-view="ebooks" class="cppm-ajax-tab <?php echo $current_view === 'ebooks' ? 'active' : ''; ?>">E-Books</a></li>
                    <li><a data-view="certificates" class="cppm-ajax-tab <?php echo $current_view === 'certificates' ? 'active' : ''; ?>">Certificates</a></li>
                    <li><a data-view="purchases" class="cppm-ajax-tab <?php echo $current_view === 'purchases' ? 'active' : ''; ?>">Purchase History</a></li>
                </ul>
            </div>
        </header>

        <main class="cppm-udemy-content" id="cppm-dynamic-content">
            <?php
            if ( $current_view === 'purchases' ) {
                cppm_render_custom_purchase_history();
            } elseif ( $current_view === 'certificates' ) {
                cppm_render_certificates_tab();
            } elseif ( $current_view === 'ebooks' ) {
                cppm_render_purchased_grid( 'ebook' );
            } else {
                cppm_render_purchased_grid( 'course' );
            }
            ?>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.cppm-ajax-tab');
        const contentArea = document.getElementById('cppm-dynamic-content');
        const ajaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
        const ajaxNonce = "<?php echo $ajax_nonce; ?>";

        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                if (this.classList.contains('active')) return;

                const view = this.getAttribute('data-view');

                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                contentArea.innerHTML = '<div class="cppm-ajax-loader"><div class="cppm-spinner"></div></div>';

                const url = new URL(window.location);
                url.searchParams.set('view', view);
                window.history.pushState({}, '', url);

                const formData = new FormData();
                formData.append('action', 'cppm_load_dashboard_tab');
                formData.append('view', view);
                formData.append('security', ajaxNonce);

                fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(response => response.text())
                .then(html => { contentArea.innerHTML = html; })
                .catch(error => { contentArea.innerHTML = '<div style="text-align:center; padding:50px; color:red;">An error occurred. Please refresh the page.</div>'; });
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// ==========================================
// 2. MAIN GRID RENDERING
// ==========================================
function cppm_render_purchased_grid( $filter_type = 'course' ) {
    $current_user = wp_get_current_user();
    
    $customer_orders = wc_get_orders( array(
        'customer' => $current_user->user_email, 
        'status'   => array( 'completed', 'processing', 'on-hold' ), 
        'limit'    => -1,
    ) );

    $filtered_products = array();

    foreach ( $customer_orders as $order ) {
        $order_status = $order->get_status(); 
        
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $terms = get_the_terms( $product_id, 'product_cat' );
            $is_match = false;
            
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $slug = strtolower( $term->slug );
                    if ( $filter_type === 'ebook' && ( strpos($slug, 'ebook') !== false || strpos($slug, 'e-book') !== false ) ) {
                        $is_match = true;
                    } elseif ( $filter_type === 'course' && $slug === 'music-course' ) { 
                        $is_match = true;
                    }
                }
            }

            if ( $is_match ) {
                if ( ! isset($filtered_products[$product_id]) || $order_status === 'completed' || $order_status === 'processing' ) {
                    $filtered_products[$product_id] = $order_status;
                }
            }
        }
    }

    if ( empty( $filtered_products ) ) {
        $label = $filter_type === 'ebook' ? 'E-Books' : 'Courses';
        echo '<div style="text-align:center; padding: 50px; border: 1px solid #d1d7dc; border-radius: 8px;"><h3 style="margin-bottom:10px;">No ' . $label . ' found</h3><p style="color:#6a6f73; margin-bottom:20px;">You haven\'t enrolled in any ' . strtolower($label) . ' yet.</p><a href="'.esc_url(home_url('/store/')).'" class="cppm-btn-dark">Browse Store</a></div>';
    } else {
        echo '<div class="cppm-course-grid">';
        foreach ( $filtered_products as $product_id => $status ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) continue;
            
            $title = $product->get_name();
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium_large' ) : wc_placeholder_img_src();
            
            $course_page_id = get_post_meta( $product_id, '_course_page_id', true );
            if ( ! empty( $course_page_id ) ) {
                $course_link = get_permalink( $course_page_id );
            } else {
                $course_slug = $product->get_slug();
                $course_link = home_url( '/' . $course_slug . '/' );
            }
            
            $is_pending = ( $status === 'on-hold' );
            if ( $is_pending ) {
                $action_text = 'PAYMENT PENDING';
                $button_color = '#d97706'; 
                $link = '#'; 
            } else {
                $action_text = $filter_type === 'ebook' ? 'READ E-BOOK' : 'START COURSE';
                $button_color = '#1c1d1f'; 
                $link = $course_link;
            }
            ?>
            <a href="<?php echo esc_url( $link ); ?>" class="cppm-course-card" <?php if($is_pending) echo 'style="opacity: 0.8; cursor: not-allowed;" title="Waiting for Verification"'; ?>>
                <div class="cppm-card-image-wrapper">
                    <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $title ); ?>">
                </div>
                <div class="cppm-card-content">
                    <h3 class="cppm-card-title"><?php echo esc_html( $title ); ?></h3>
                    <p class="cppm-card-instructor">Sarkari Musician</p>
                    <div class="cppm-card-action">
                        <span class="cppm-start-text" style="color: <?php echo $button_color; ?>;"><?php echo $action_text; ?></span>
                    </div>
                </div>
            </a>
            <?php
        }
        echo '</div>';
    }

    // Append the Recommendation Carousel below the grid
    cppm_render_dashboard_recommendations();
}

// ==========================================
// 3. RECOMMENDATION ENGINE (CAROUSEL)
// ==========================================
function cppm_render_dashboard_recommendations() {
    $current_user = wp_get_current_user();
    $purchased_ids = array();
    $purchased_cats = array();

    // 1. Gather what they already own
    $customer_orders = wc_get_orders( array(
        'customer' => $current_user->user_email,
        'status'   => array( 'completed', 'processing', 'on-hold' ),
        'limit'    => -1,
    ) );

    foreach ( $customer_orders as $order ) {
        foreach ( $order->get_items() as $item ) {
            $pid = $item->get_product_id();
            $purchased_ids[] = $pid;
            $terms = get_the_terms( $pid, 'product_cat' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $purchased_cats[] = $term->term_id;
                }
            }
        }
    }

    $purchased_ids = array_unique($purchased_ids);
    $purchased_cats = array_unique($purchased_cats);

    // 2. Query for new products to recommend
    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 8, // Fetch up to 8 items to swipe through
        'post__not_in'   => $purchased_ids, // Exclude owned items!
        'meta_key'       => 'total_sales',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC'
    );

    // If they have history, suggest similar items. Otherwise, show global best sellers.
    if ( ! empty( $purchased_cats ) ) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $purchased_cats,
                'operator' => 'IN'
            )
        );
    }

    $recommendations = new WP_Query( $args );

    if ( $recommendations->have_posts() ) {
        echo '<div class="cppm-rec-section">';
        echo '<h3>Recommended for You</h3>';
        echo '<div class="cppm-rec-carousel">';
        
        while ( $recommendations->have_posts() ) {
            $recommendations->the_post();
            global $product;
            
            $title = $product->get_name();
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : wc_placeholder_img_src();
            $link = $product->get_permalink();
            $price_html = $product->get_price_html();
            
            echo '<a href="'.esc_url($link).'" class="cppm-rec-card">';
            // BULLETPROOF SQUARE IMAGE IMPLEMENTATION
            echo '<div class="cppm-rec-img"><img src="'.esc_url($image_url).'" alt="'.esc_attr($title).'"></div>';
            echo '<div class="cppm-rec-details">';
            echo '<h4 class="cppm-rec-title">'.esc_html($title).'</h4>';
            echo '<div class="cppm-rec-price">'.$price_html.'</div>';
            echo '</div>';
            echo '</a>';
        }
        
        echo '</div>'; // End carousel
        echo '</div>'; // End section
    }
    
    wp_reset_postdata();
}

// ==========================================
// 4. OTHER TABS (PURCHASES / CERTIFICATES)
// ==========================================
function cppm_render_custom_purchase_history() {
    $current_user = wp_get_current_user();
    
    $orders_by_id = wc_get_orders( array( 'customer_id' => $current_user->ID, 'status' => 'any', 'limit' => -1 ) );
    $orders_by_email = wc_get_orders( array( 'billing_email' => $current_user->user_email, 'status' => 'any', 'limit' => -1 ) );

    $all_orders = array_merge( $orders_by_id, $orders_by_email );
    $unique_orders = array();
    
    foreach ( $all_orders as $order ) {
        $unique_orders[ $order->get_id() ] = $order;
    }

    if ( empty( $unique_orders ) ) {
        echo '<div style="text-align:center; padding: 50px; border: 1px solid #d1d7dc; border-radius: 8px;"><h3 style="margin-bottom:10px;">No Purchase History</h3><p style="color:#6a6f73;">We could not find any orders under your account.</p></div>';
        return;
    }

    echo '<div class="cppm-table-wrapper">';
    echo '<table class="cppm-data-table">';
    echo '<thead><tr><th>Sl No.</th><th>Order ID</th><th>Title</th><th>Date</th><th>Status</th><th>Total Price</th><th>Action</th></tr></thead>';
    echo '<tbody>';
    
    $sl = 1;
    foreach ( $unique_orders as $order ) {
        $order_id = $order->get_id();
        $date = $order->get_date_created() ? $order->get_date_created()->date( 'F j, Y' ) : '';
        $total = $order->get_formatted_order_total();
        $view_url = $order->get_view_order_url();
        $status_name = wc_get_order_status_name( $order->get_status() );

        $items = $order->get_items();
        $titles = array();
        foreach ( $items as $item ) {
            $titles[] = $item->get_name();
        }
        $titles_str = implode( '<br>', $titles );

        echo '<tr>';
        echo '<td>' . $sl . '</td>';
        echo '<td>#' . $order_id . '</td>';
        echo '<td><strong>' . wp_kses_post( $titles_str ) . '</strong></td>';
        echo '<td>' . esc_html( $date ) . '</td>';
        echo '<td><span style="background:#f1f5f9; border:1px solid #cbd5e1; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold; color:#475569;">' . esc_html( $status_name ) . '</span></td>';
        echo '<td>' . wp_kses_post( $total ) . '</td>';
        echo '<td><a href="' . esc_url( $view_url ) . '" class="cppm-btn-dark">View Details</a></td>';
        echo '</tr>';
        
        $sl++;
    }
    
    echo '</tbody></table></div>';
}

function cppm_render_certificates_tab() {
    $current_user = wp_get_current_user();
    
    $customer_orders = wc_get_orders( array(
        'customer' => $current_user->user_email, 
        'status'   => array( 'completed', 'processing' ), 
        'limit'    => -1,
    ) );

    $enrolled_courses = array();

    foreach ( $customer_orders as $order ) {
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $terms = get_the_terms( $product_id, 'product_cat' );
            
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    if ( strtolower( $term->slug ) === 'music-course' ) {
                        $enrolled_courses[$product_id] = true;
                    }
                }
            }
        }
    }

    if ( empty( $enrolled_courses ) ) {
        echo '<div style="text-align:center; padding: 50px; border: 1px solid #d1d7dc; border-radius: 8px;"><h3 style="margin-bottom:10px;">No Certificates Available</h3><p style="color:#6a6f73;">You have not enrolled in any courses yet.</p></div>';
        return;
    }

    echo '<div class="cppm-table-wrapper">';
    echo '<table class="cppm-data-table">';
    echo '<thead><tr><th>Course Title</th><th>Status</th><th>Action</th></tr></thead>';
    echo '<tbody>';
    
    foreach ( array_keys($enrolled_courses) as $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) continue;

        $title = $product->get_name();
        $is_completed = get_user_meta( $current_user->ID, '_course_completed_' . $product_id, true );

        echo '<tr>';
        echo '<td><strong>' . esc_html( $title ) . '</strong></td>';

        if ( $is_completed ) {
            echo '<td><span style="background:#dcfce7; border:1px solid #86efac; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold; color:#166534;">Completed</span></td>';
            $certificate_url = '#'; 
            echo '<td><a href="' . esc_url( $certificate_url ) . '" class="cppm-btn-dark" style="background:#5624d0;">Download Certificate</a></td>';
        } else {
            echo '<td><span style="background:#fef9c3; border:1px solid #fde047; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold; color:#854d0e;">In Progress</span></td>';
            echo '<td><button class="cppm-btn-disabled" disabled>Not Available</button></td>';
        }
        
        echo '</tr>';
    }
    
    echo '</tbody></table></div>';
}