<?php
/**
 * Core: Udemy-Style Student Dashboard & AJAX Routing
 * Architecture: Modular / Asset Separated
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. AJAX HANDLER (TAB SWITCHING ENGINE)
// ==========================================
add_action( 'wp_ajax_cppm_load_dashboard_tab', 'cppm_ajax_load_dashboard_tab' );
function cppm_ajax_load_dashboard_tab() {
    
    // Security verification
    check_ajax_referer( 'cppm_dashboard_ajax_nonce', 'security' );

    $view = isset( $_POST['view'] ) ? sanitize_text_field( $_POST['view'] ) : 'courses';

    // Route to the appropriate rendering function based on the requested view
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
// 2. THE MAIN CLASSROOM SHORTCODE & ASSET ENQUEUE
// ==========================================
add_shortcode( 'cppm_student_dashboard', 'cppm_render_udemy_classroom' );
function cppm_render_udemy_classroom() {
    
    // Auth Check
    if ( ! is_user_logged_in() ) {
        $login_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url();
        return '<div style="text-align:center; padding: 100px 20px;">
                    <h2>Your Classroom Awaits</h2>
                    <p>Please log in to access your learning materials.</p>
                    <a href="' . esc_url( $login_url ) . '" style="display:inline-block; margin-top:20px; background:#2874f0; color:#fff; padding:12px 24px; text-decoration:none; font-weight:bold; border-radius: 8px; box-shadow: 0 4px 10px rgba(40,116,240,0.2);">Log In</a>
                </div>';
    }

    // ENQUEUE ASSETS ONLY WHEN SHORTCODE IS EXECUTED
    $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
    wp_enqueue_style( 'cppm-student-dash-css', $plugin_url . 'assets/css/student-dashboard.css', array(), '1.0.0' );
    wp_enqueue_script( 'cppm-student-dash-js', $plugin_url . 'assets/js/student-dashboard.js', array(), '1.0.0', true );

    // Secure Data Passage to JS
    wp_localize_script( 'cppm-student-dash-js', 'cppmDashboardData', array(
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'ajaxNonce' => wp_create_nonce( 'cppm_dashboard_ajax_nonce' )
    ));

    // RENDER SKELETON
    ob_start();
    ?>
    <div class="cppm-dashboard-wrapper">
        
        <aside class="cppm-dash-sidebar">
            <ul class="cppm-dash-nav-list">
                <li class="cppm-dash-nav-item active" data-view="courses">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                    My Courses
                </li>
                <li class="cppm-dash-nav-item" data-view="ebooks">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                    My E-Books
                </li>
                <li class="cppm-dash-nav-item" data-view="certificates">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                    Certificates
                </li>
                <li class="cppm-dash-nav-item" data-view="purchases">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                    Purchase History
                </li>
            </ul>
        </aside>

        <main class="cppm-dash-main">
            <div id="cppm-dash-content-area">
                </div>
        </main>
        
    </div>
    <?php
    return ob_get_clean();
}

// ==========================================
// 3. TAB RENDERERS (CALLED BY AJAX)
// ==========================================

// Example Renderer for Purchase History
function cppm_render_custom_purchase_history() {
    $current_user = wp_get_current_user();
    $customer_orders = wc_get_orders( array(
        'customer' => $current_user->ID,
        'limit'    => 20,
    ) );

    echo '<div class="cppm-dash-header"><h2 class="cppm-dash-title">Purchase History</h2></div>';

    if ( empty( $customer_orders ) ) {
        echo '<div style="padding:40px; text-align:center; color:#64748b;">No purchases found.</div>';
        return;
    }

    echo '<div class="cppm-table-wrapper"><table class="cppm-data-table">';
    echo '<thead><tr><th>Order ID</th><th>Date</th><th>Status</th><th>Total</th></tr></thead><tbody>';
    
    foreach ( $customer_orders as $order ) {
        echo '<tr>';
        echo '<td>#' . esc_html( $order->get_order_number() ) . '</td>';
        echo '<td>' . esc_html( wc_format_datetime( $order->get_date_created() ) ) . '</td>';
        echo '<td>' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</td>';
        echo '<td>' . wp_kses_post( $order->get_formatted_order_total() ) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table></div>';
}

// Example Renderer for Certificates Tab
function cppm_render_certificates_tab() {
    echo '<div class="cppm-dash-header"><h2 class="cppm-dash-title">My Certificates</h2></div>';
    // Logic to loop through completed courses and show download buttons
    echo '<div style="padding:40px; text-align:center; color:#64748b;">Complete a course to earn your first certificate!</div>';
}

// Example Renderer for Courses/EBooks Grid
function cppm_render_purchased_grid( $type = 'course' ) {
    $title = $type === 'ebook' ? 'My E-Books' : 'My Courses';
    echo '<div class="cppm-dash-header"><h2 class="cppm-dash-title">' . esc_html($title) . '</h2></div>';
    
    // You would insert your existing logic here that grabs the user's purchased products 
    // and loops through them to display the grid/table layout from your previous code.
    
    // Placeholder for structural demonstration
    echo '<div style="padding:40px; text-align:center; color:#64748b;">Your ' . esc_html($title) . ' will appear here.</div>';
}