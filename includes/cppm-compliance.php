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

// ==========================================
// 7. ZERO-WOOCOMMERCE TRACE NATIVE LOGIN/REGISTER UX
// ==========================================
add_action('wp_head', 'cppm_woo_login_styles', 999);
function cppm_woo_login_styles() {
    if ( is_account_page() && ! is_user_logged_in() ) {
        
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
        if ( ! $logo_url ) { $logo_url = 'https://sarkarimusician.store/wp-content/uploads/2026/04/logo-1.png'; }
        
        ?>
        <style>
            /* 1. NUKE WOOCOMMERCE & THEME CLUTTER ENTIRELY */
            header, footer, #masthead, #colophon, .site-header, .site-footer, .ast-main-header-wrap, .ast-archive-description, .entry-header { display: none !important; }
            body, html { margin: 0; padding: 0; background: #ffffff !important; height: 100vh; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important; -webkit-font-smoothing: antialiased; }
            .site-content, #primary, #main { padding: 0 !important; margin: 0 !important; max-width: 100% !important; width: 100% !important; }

            /* 2. BASE DESKTOP SPLIT SCREEN */
            .cppm-login-split-wrapper { display: flex; height: 100vh; width: 100vw; background: #ffffff; }
            
            .cppm-login-banner { flex: 1.2; background: linear-gradient(145deg, #020617 0%, #0f172a 100%); color: #ffffff; display: flex; align-items: center; justify-content: center; padding: 60px; position: relative; overflow: hidden; }
            .cppm-login-banner::before { content: ''; position: absolute; width: 200%; height: 200%; background: radial-gradient(circle at top left, rgba(40,116,240,0.15), transparent 50%); top: -50%; left: -50%; pointer-events: none; }
            .cppm-banner-content { max-width: 420px; position: relative; z-index: 2; }
            .cppm-banner-content h2 { font-size: 46px; font-weight: 800; margin-bottom: 24px; line-height: 1.1; letter-spacing: -1.5px; color: #f8fafc; }
            .cppm-banner-content p { font-size: 18px; color: #94a3b8; line-height: 1.6; margin-bottom: 40px; }
            .cppm-feature-list { list-style: none; padding: 0; margin: 0; }
            .cppm-feature-list li { font-size: 16px; color: #e2e8f0; margin-bottom: 20px; display: flex; align-items: center; gap: 14px; font-weight: 500; letter-spacing: -0.2px; }
            .cppm-feature-list li svg { width: 22px; height: 22px; color: #3b82f6; flex-shrink: 0; }

            /* 3. FORM PANEL (Right Side) */
            .cppm-login-form-panel { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; position: relative; overflow-y: auto; background: #ffffff; }
            .woocommerce { width: 100%; max-width: 380px; display: flex; flex-direction: column; align-items: center !important; }
            .cppm-form-center {align-items: center !important;}
            
            /* Form Override */
            #customer_login { width: 100% !important; max-width: 380px !important; margin: 0 !important; padding: 0 !important; background: transparent !important; display: block; }
            .u-column1, .u-column2 { width: 100% !important; float: none !important; padding: 0 !important; animation: cppmFadeIn 0.3s ease; }
            @keyframes cppmFadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
            
            /* Hide Column 2 by default, JS will toggle it */
            .u-column2 { display: none; }

            /* Auth Tabs styling */
            .cppm-auth-tabs { display: flex; background: #f1f5f9; border-radius: 12px; padding: 4px; margin-bottom: 25px; width: 100%; }
            .cppm-tab { flex: 1; text-align: center; padding: 12px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; border: none; background: transparent; color: #64748b; transition: all 0.2s ease; outline: none; }
            .cppm-tab.active { background: #ffffff; color: #0f172a; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

            /* Dynamic Elements injected by JS */
            .cppm-login-logo { background-image: url('<?php echo esc_url($logo_url); ?>'); height: 50px; width: 100%; background-size: contain; background-repeat: no-repeat; background-position: center center; margin-bottom: 35px; }
            .cppm-mobile-topbar, .cppm-mobile-welcome { display: none; } 

            /* Nuke Woo Headings & Labels */
            .u-column1 > h2, .u-column2 > h2, #customer_login > h2, .woocommerce > h2, h1.entry-title, .page-title, .woocommerce-form-login h2, .woocommerce-form-register h2 { display: none !important; }
            .woocommerce-form-login label, .woocommerce-form-register label { display: none !important; } 
            .woocommerce-form-login p.form-row, .woocommerce-form-register p.form-row { margin-bottom: 16px !important; padding: 0 !important; float: none !important; width: 100% !important; }
            
            /* Premium App Inputs (Applied to both Login and Register) */
            .woocommerce-form-login input.input-text, .woocommerce-form-register input.input-text {
                width: 100% !important; padding: 16px 18px !important; font-size: 16px !important; 
                color: #0f172a !important; background: #f8fafc !important; border: 1.5px solid transparent !important; border-radius: 12px !important;
                transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important; outline: none !important; box-shadow: none !important; box-sizing: border-box !important; height: 56px !important;
            }
            .woocommerce-form-login input.input-text:hover, .woocommerce-form-register input.input-text:hover { background: #f1f5f9 !important; }
            .woocommerce-form-login input.input-text:focus, .woocommerce-form-register input.input-text:focus { border-color: #2874f0 !important; background: #ffffff !important; box-shadow: 0 4px 12px rgba(40,116,240,0.08) !important; }
            .woocommerce-form-login input.input-text::placeholder, .woocommerce-form-register input.input-text::placeholder { color: #94a3b8 !important; font-weight: 500; }

            /* Modern Button Override */
            .woocommerce-form-login button.woocommerce-button, .woocommerce-form-register button.woocommerce-button {
                width: 100% !important; background: #2874f0 !important; color: #ffffff !important; font-size: 16px !important; font-weight: 600 !important; height: 56px !important;
                border-radius: 12px !important; border: none !important; transition: all 0.2s ease !important; cursor: pointer !important; letter-spacing: -0.2px !important; 
                box-shadow: 0 4px 12px rgba(40,116,240,0.25) !important; padding: 0 !important; display: flex !important; align-items: center !important; justify-content: center !important; margin-top: 10px;
            }
            .woocommerce-form-login button.woocommerce-button:hover, .woocommerce-form-register button.woocommerce-button:hover { background: #1d4ed8 !important; transform: translateY(-1px) !important; box-shadow: 0 6px 16px rgba(40,116,240,0.35) !important; }

            /* Desktop Links & Small text */
            .woocommerce-LostPassword { margin-top: 24px !important; display: flex !important; justify-content: space-between !important; align-items: center !important; width: 100% !important; }
            .woocommerce-LostPassword a, .cppm-return-site-bottom { font-size: 14px !important; font-weight: 600 !important; color: #3b82f6 !important; text-decoration: none !important; transition: 0.2s !important; display: inline-block !important;}
            .woocommerce-LostPassword a:hover, .cppm-return-site-bottom:hover { color: #1e3a8a !important; }
            .woocommerce-privacy-policy-text { font-size: 12px !important; color: #64748b !important; text-align: center; margin-bottom: 20px; line-height: 1.5; }
            .woocommerce-privacy-policy-text a { color: #2874f0; text-decoration: none; font-weight: 600; }

            /* 5. PURE SMARTPHONE NATIVE APP UX */
            @media (max-width: 768px) {
                body, html { overflow-y: auto; height: auto; min-height: 100vh; }
                .cppm-login-split-wrapper { display: block; height: auto; min-height: 100vh; background: #ffffff; }
                .cppm-login-banner { display: none !important; } 
                
                .cppm-login-form-panel { padding: 0 24px 40px 24px !important; justify-content: flex-start; align-items: center; height: auto; min-height: 100vh; }
                .woocommerce { max-width: 100%; padding-top: 100px !important; align-items: center !important;}
                #customer_login { max-width: 100% !important; width: 100% !important; }
                
                .cppm-mobile-topbar { position: fixed; top: 0; left: 0; width: 100%; height: 60px; display: flex; align-items: center; padding: 0 20px; box-sizing: border-box; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid rgba(0,0,0,0.05); z-index: 100; }
                .cppm-mobile-topbar a { display: flex; align-items: center; gap: 4px; color: #0f172a; text-decoration: none; font-weight: 600; font-size: 16px; letter-spacing: -0.3px; }
                .cppm-mobile-topbar a svg { width: 20px; height: 20px; }
                
                .cppm-login-logo { background-position: center; height: 50px; margin-bottom: 30px; }
                .cppm-mobile-welcome { display: block; font-size: 28px; font-weight: 800; color: #0f172a; text-align: center; margin: 0 0 25px 0; letter-spacing: -1px; }

                .woocommerce-LostPassword { flex-direction: column !important; justify-content: center !important; gap: 16px !important; margin-top: 24px !important; text-align: center !important; }
                .cppm-return-site-bottom { display: none !important; } 
            }
        </style>
        
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var customerLogin = document.getElementById('customer_login');
                var wooWrapper = document.querySelector('.woocommerce');
                if(!wooWrapper) return;

                // Build Base Wrapper & Banner
                wooWrapper.className = "woocommerce cppm-form-center";
                var wrapper = document.createElement('div');
                wrapper.className = 'cppm-login-split-wrapper';
                var banner = document.createElement('div');
                banner.className = 'cppm-login-banner';
                banner.innerHTML = '<div class="cppm-banner-content"><h2>Master Your Music Journey</h2><p>Log in or create an account to access premium courses, track your orders, and learn from the best.</p><ul class="cppm-feature-list"><li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> High-Quality Video Modules</li><li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Interactive Sheet Music</li><li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Mock Tests & Analytics</li></ul></div>';

                var formPanel = document.createElement('div');
                formPanel.className = 'cppm-login-form-panel';
                
                // Native Top Bar (Mobile)
                var mobileTopBar = document.createElement('div');
                mobileTopBar.className = 'cppm-mobile-topbar';
                mobileTopBar.innerHTML = '<a href="<?php echo home_url(); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg> Back</a>';
                formPanel.appendChild(mobileTopBar);

                // Logo & Welcome Header
                var logoDiv = document.createElement('div');
                logoDiv.className = 'cppm-login-logo';
                wooWrapper.insertBefore(logoDiv, wooWrapper.firstChild); 
                var mobileWelcome = document.createElement('h2');
                mobileWelcome.className = 'cppm-mobile-welcome';
                mobileWelcome.innerText = 'Welcome Back';
                wooWrapper.insertBefore(mobileWelcome, logoDiv.nextSibling);

                // Setup the Tab Logic if Registration is Enabled
                if(customerLogin) {
                    var col1 = customerLogin.querySelector('.u-column1');
                    var col2 = customerLogin.querySelector('.u-column2');
                    
                    if (col1 && col2) {
                        var tabsHTML = '<div class="cppm-auth-tabs"><button type="button" class="cppm-tab active" data-target="login">Log In</button><button type="button" class="cppm-tab" data-target="register">Sign Up</button></div>';
                        customerLogin.insertAdjacentHTML('afterbegin', tabsHTML);
                        
                        var tabs = document.querySelectorAll('.cppm-tab');
                        tabs.forEach(function(tab) {
                            tab.addEventListener('click', function(e) {
                                e.preventDefault();
                                tabs.forEach(t => t.classList.remove('active'));
                                this.classList.add('active');
                                
                                if (this.dataset.target === 'login') {
                                    col1.style.display = 'block';
                                    col2.style.display = 'none';
                                    mobileWelcome.innerText = 'Welcome Back';
                                } else {
                                    col1.style.display = 'none';
                                    col2.style.display = 'block';
                                    mobileWelcome.innerText = 'Create Account';
                                }
                            });
                        });
                    }
                }

                // Placeholders Setup (Login)
                var userField = document.getElementById('username');
                var passField = document.getElementById('password');
                if(userField) userField.placeholder = "Email Address or Username";
                if(passField) passField.placeholder = "Password";

                // Placeholders Setup (Register)
                var regEmail = document.getElementById('reg_email');
                var regUser = document.getElementById('reg_username');
                var regPass = document.getElementById('reg_password');
                if(regEmail) regEmail.placeholder = "Email Address";
                if(regUser) regUser.placeholder = "Choose a Username";
                if(regPass) regPass.placeholder = "Create a Password";

                // Text Fixes & Links
                var lostPwdLink = document.querySelector('.woocommerce-LostPassword a');
                var lostPwdContainer = document.querySelector('.woocommerce-LostPassword');
                if(lostPwdLink) { lostPwdLink.innerText = 'Forgot password?'; }
                
                if(lostPwdContainer && !document.querySelector('.cppm-return-site-bottom')) {
                    var returnLink = document.createElement('a');
                    returnLink.href = '<?php echo home_url(); ?>';
                    returnLink.className = 'cppm-return-site-bottom';
                    returnLink.innerHTML = '&larr; Back to website';
                    lostPwdContainer.appendChild(returnLink);
                }

                // Final Assembly
                wooWrapper.parentNode.insertBefore(wrapper, wooWrapper);
                formPanel.appendChild(wooWrapper);
                wrapper.appendChild(banner);
                wrapper.appendChild(formPanel);
            });
        </script>
        <?php
    }
}