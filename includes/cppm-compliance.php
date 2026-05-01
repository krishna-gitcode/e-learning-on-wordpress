<?php
/**
 * Core: Security, API Integrations & Google Compliance
 * Architecture: Modular / Asset Separated
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. GOOGLE BRAND VERIFICATION SCHEMA (JSON-LD)
// ==========================================
add_action('wp_head', 'cppm_inject_brand_schema');
function cppm_inject_brand_schema() {
    // Only inject heavy schema on the front page for Google Crawlers
    if ( is_front_page() || is_home() ) {
        ?>
        <script type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@type": "Organization",
          "name": "Sarkari Musician",
          "url": "<?php echo esc_url( home_url() ); ?>",
          "logo": "https://sarkarimusician.store/wp-content/uploads/2026/04/logo-1.png",
          "description": "Premium LMS and E-commerce platform for military band, bugle, and musical instrument training.",
          "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "+91-9651140204", 
            "contactType": "customer service",
            "areaServed": "IN",
            "availableLanguage": ["en", "hi"]
          },
          "sameAs": [
            "https://www.youtube.com/@sarkarimusician"
          ]
        }
        </script>
        <?php
    }
}

// ==========================================
// 2. ENQUEUE ADMIN LOGIN ASSETS (For Instructors/Staff)
// ==========================================
add_action( 'login_enqueue_scripts', 'cppm_enqueue_admin_login_assets' );
function cppm_enqueue_admin_login_assets() {
    $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
    
    wp_enqueue_style( 'cppm-wp-login-css', $plugin_url . 'assets/css/wp-admin-login.css', array(), '1.0.0' );
    wp_enqueue_script( 'cppm-wp-login-js', $plugin_url . 'assets/js/wp-admin-login.js', array(), '1.0.0', true );
}

// ==========================================
// 3. WP-LOGIN UI FILTERS
// ==========================================
add_filter( 'login_headerurl', 'cppm_login_logo_url' );
function cppm_login_logo_url() {
    return home_url();
}

add_filter( 'gettext', 'cppm_change_login_text', 10, 3 );
function cppm_change_login_text( $translated_text, $text, $domain ) {
    if ( $text === 'Lost your password?' || $text === 'Lost your password' ) {
        $translated_text = 'Forget password?';
    }
    return $translated_text;
}

// ==========================================
// 4. REDIRECT WP-LOGIN TO WOOCOMMERCE FOR STUDENTS
// ==========================================
add_action('init', 'cppm_redirect_wp_login_to_woocommerce');
function cppm_redirect_wp_login_to_woocommerce() {
    global $pagenow;
    
    // If user is accessing wp-login.php directly and is NOT logged in
    if ( $pagenow == 'wp-login.php' && ! is_user_logged_in() ) {
        
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        // Let admins/instructors reset passwords or log out via normal WordPress channels
        if ( $action == 'logout' || $action == 'lostpassword' || $action == 'rp' || $action == 'resetpass' ) {
            return;
        }
        
        // Otherwise, bounce all normal traffic to the beautiful WooCommerce Login Page we built
        $login_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
        wp_safe_redirect( $login_url );
        exit;
    }
}