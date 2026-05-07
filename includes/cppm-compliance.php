<?php
/**
 * Phase 5: Security, API Integrations & Google Compliance
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. GOOGLE BRAND VERIFICATION SCHEMA (JSON-LD)
// ==========================================
add_action('wp_head', 'cppm_inject_brand_schema');
function cppm_inject_brand_schema() {
    // We only need to inject this heavy schema on the front page
    if ( is_front_page() || is_home() ) {
        ?>
        <script type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@type": "Organization",
          "name": "Sarkari Musician",
          "url": "https://sarkarimusician.store",
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
            "https://www.youtube.com/@sarkarimusician",
          ]
        }
        </script>
        
        <script type="application/ld+json">
        {
          "@context": "https://schema.org/",
          "@type": "WebSite",
          "name": "Sarkari Musician",
          "url": "https://sarkarimusician.store",
          "potentialAction": {
            "@type": "SearchAction",
            "target": "https://sarkarimusician.store/?s={search_term_string}&post_type=product",
            "query-input": "required name=search_term_string"
          }
        }
        </script>
        <?php
    }
}

// ==========================================
// 3. META / WHATSAPP DOMAIN VERIFICATION
// ==========================================
add_action('wp_head', 'cppm_meta_domain_verification', 2);
function cppm_meta_domain_verification() {
    // PASTE YOUR META HTML TAG EXACTLY AS IT APPEARS ON THE NEXT LINE:
    echo '<meta name="facebook-domain-verification" content="2ly650wlwy3ud2u65l7q7mpijtlz91" />' . "\n";
}

// ==========================================
// 4. WHITE-LABEL WORDPRESS LOGIN PAGE
// ==========================================

// 4.1 Replace WordPress Logo with Site Logo & Modern UI
add_action( 'login_enqueue_scripts', 'cppm_custom_login_branding' );
function cppm_custom_login_branding() {
    $custom_logo_id = get_theme_mod( 'custom_logo' );
    $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
    if ( ! $logo_url ) {
        $logo_url = 'https://sarkarimusician.store/wp-content/uploads/2026/04/logo-1.png'; 
    }

    ?>
    <style type="text/css">
        /* Beautiful Gradient Background */
        body.login {
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        /* Modern Glassmorphism Form Container */
        #login {
            width: 400px;
            padding: 0;
            margin: 0;
        }
        #loginbox {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid rgba(255,255,255,0.5);
        }
        
        /* The Logo */
        #login h1 a, .login h1 a {
            background-image: url(<?php echo esc_url( $logo_url ); ?>);
            height: 100px;
            width: 100%;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center center;
            margin-bottom: 20px;
        }

        /* Form Elements Modernization */
        .login form {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .login label {
            font-weight: 600;
            color: #334155;
            font-size: 14px;
            margin-bottom: 8px;
            display: block;
        }
        .login input[type="text"], 
        .login input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
            font-size: 15px;
            color: #1e293b;
            transition: all 0.3s ease;
            box-shadow: none;
        }
        .login input[type="text"]:focus, 
        .login input[type="password"]:focus {
            border-color: #2874f0;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(40,116,240,0.1) !important;
            outline: none;
        }

        /* The Flipkart Blue Button */
        .login .button-primary {
            width: 100%;
            background: #2874f0 !important;
            border: none !important;
            color: #ffffff !important;
            font-size: 16px !important;
            font-weight: 700 !important;
            padding: 12px !important;
            border-radius: 10px !important;
            text-shadow: none !important;
            box-shadow: 0 10px 20px rgba(40,116,240,0.2) !important;
            transition: all 0.3s ease;
            height: auto !important;
            line-height: normal !important;
            margin-top: 10px;
        }
        .login .button-primary:hover {
            background: #1e40af !important;
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(40,116,240,0.3) !important;
        }

        /* Links at the bottom */
        #nav, #backtoblog {
            text-align: center;
            margin-top: 20px;
            padding: 0;
        }
        .login #nav a, .login #backtoblog a {
            color: #64748b !important;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }
        .login #nav a:hover, .login #backtoblog a:hover {
            color: #2874f0 !important;
        }
        
        /* Language Switcher cleanup */
        .login .language-switcher { display: none; }
        
        /* Messages / Errors */
        .login .message, .login .success, .login .notice {
            border-left: 4px solid #2874f0;
            background: #eff6ff;
            box-shadow: none;
            border-radius: 6px;
        }
        .login #login_error {
            border-left: 4px solid #ef4444;
            background: #fef2f2;
            box-shadow: none;
            border-radius: 6px;
        }
    </style>
    <script>
        // Wrap the form elements inside the new Glassmorphism box
        document.addEventListener("DOMContentLoaded", function() {
            var loginElement = document.getElementById('login');
            var form = document.getElementById('loginform') || document.getElementById('registerform') || document.getElementById('lostpasswordform');
            var h1 = document.querySelector('.login h1');
            
            if(loginElement && form && h1) {
                var wrapper = document.createElement('div');
                wrapper.id = 'loginbox';
                loginElement.insertBefore(wrapper, h1);
                wrapper.appendChild(h1);
                wrapper.appendChild(form);
            }
        });
    </script>
    <?php
}

// 4.2 Change Logo Link from WordPress.org to your Site Homepage
add_filter( 'login_headerurl', 'cppm_login_logo_url' );
function cppm_login_logo_url() {
    return home_url();
}

// 4.3 Change "Lost your password?" text to "Forget password"
add_filter( 'gettext', 'cppm_change_login_text', 10, 3 );
function cppm_change_login_text( $translated_text, $text, $domain ) {
    // Intercept the specific WordPress translation strings
    if ( $text === 'Lost your password?' || $text === 'Lost your password' ) {
        $translated_text = 'Forget password';
    }
    return $translated_text;
}

// ==========================================
// 5. REDIRECT WP-LOGIN TO WOOCOMMERCE
// ==========================================
add_action('init', 'cppm_redirect_wp_login_to_woocommerce');
function cppm_redirect_wp_login_to_woocommerce() {
    global $pagenow;
    
    // Check if the user is on the default login page and NOT logged in
    if ( $pagenow == 'wp-login.php' && ! is_user_logged_in() ) {
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        // We MUST allow 'logout' and 'resetpass' actions to pass through so nothing breaks
        if ( ! in_array( $action, array( 'logout', 'postpass', 'rp', 'resetpass' ) ) ) {
            wp_redirect( wc_get_page_permalink( 'myaccount' ) );
            exit;
        }
    }
}

