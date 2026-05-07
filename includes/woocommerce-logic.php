<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================================
// 1. PROGRESS & MEMORY TRACKING AJAX
// ==========================================
add_action( 'wp_ajax_cppm_mark_completed', 'cppm_ajax_done' );
function cppm_ajax_done() {
    if(!is_user_logged_in()) wp_send_json_error();
    $uid = get_current_user_id();
    $pid = intval($_POST['playlist_id']);
    $idx = intval($_POST['video_index']);
    $data = get_user_meta($uid, '_cppm_completed_videos', true);
    if(!is_array($data)) $data = array();
    if(!isset($data[$pid])) $data[$pid] = array();
    if(!in_array($idx, $data[$pid])) $data[$pid][] = $idx;
    update_user_meta($uid, '_cppm_completed_videos', $data);
    wp_send_json_success();
}

add_action( 'wp_ajax_cppm_save_last_video', 'cppm_ajax_save_last_video' );
function cppm_ajax_save_last_video() {
    if(!is_user_logged_in()) wp_send_json_error();
    $uid = get_current_user_id();
    $pid = intval($_POST['playlist_id']);
    $idx = intval($_POST['video_index']);
    update_user_meta($uid, '_cppm_last_vid_' . $pid, $idx);
    wp_send_json_success();
}

add_action( 'wp_ajax_cppm_save_notes', 'cppm_ajax_save_notes' );
function cppm_ajax_save_notes() {
    if(!is_user_logged_in()) wp_send_json_error();
    $uid = get_current_user_id();
    $pid = intval($_POST['playlist_id']);
    $notes = sanitize_textarea_field($_POST['notes']);
    update_user_meta($uid, '_cppm_notes_' . $pid, $notes);
    wp_send_json_success();
}

add_action( 'wp_ajax_cppm_load_video', 'cppm_ajax_load_video' );
function cppm_ajax_load_video() {
    if(!is_user_logged_in()) wp_send_json_error("Auth missing");
    $pid = intval($_POST['playlist_id']);
    $idx = intval($_POST['video_index']);
    $videos = get_post_meta($pid, '_cppm_videos_array', true);
    if (isset($videos[$idx])) {
        $html = do_shortcode('[presto_player src="' . esc_attr($videos[$idx]['url']) . '"]');
        wp_send_json_success(array('html' => $html));
    }
    wp_send_json_error("Video not found");
}

// ==========================================
// 2. YOUTUBE-STYLE AJAX COMMENTS
// ==========================================
add_action('wp_ajax_cppm_submit_comment', 'cppm_handle_ajax_comment');
function cppm_handle_ajax_comment() {
    if ( ! isset($_POST['comment_post_ID']) ) wp_send_json_error("Missing Post ID");
    $comment_data = wp_handle_comment_submission( wp_unslash( $_POST ) );
    if ( is_wp_error( $comment_data ) ) wp_send_json_error( $comment_data->get_error_message() );
    
    $vid_index = isset( $_POST['cppm_vid_index'] ) ? intval($_POST['cppm_vid_index']) : 0;
    $vid_title = isset( $_POST['cppm_vid_title'] ) ? sanitize_text_field($_POST['cppm_vid_title']) : '';
    add_comment_meta( $comment_data->comment_ID, '_cppm_vid_index', $vid_index );
    add_comment_meta( $comment_data->comment_ID, '_cppm_vid_title', $vid_title );

    ob_start();
    $is_instructor = user_can($comment_data->user_id, 'manage_options');
    $instructor_badge = $is_instructor ? '<span style="background:#111827; color:#fff; font-size:10px; padding:2px 6px; border-radius:12px; margin-left:8px; font-weight:600;">Instructor</span>' : '';
    
    echo '<div class="cppm-yt-comment cppm-new-comment-anim" data-video-index="' . esc_attr($vid_index) . '">';
    echo '<div class="cppm-yt-avatar">' . get_avatar( $comment_data, 40, '', '', array('style' => 'border-radius:50%;') ) . '</div>';
    echo '<div class="cppm-yt-comment-body">';
    echo '<div class="cppm-yt-comment-header"><span class="cppm-yt-author">' . esc_html($comment_data->comment_author) . $instructor_badge . '</span><span class="cppm-yt-time">Just now</span></div>';
    
    if ($vid_title) { echo '<div class="cppm-yt-badge">📍 ' . esc_html($vid_title) . '</div>'; }
    
    $content = apply_filters('comment_text', $comment_data->comment_content, $comment_data);
    echo '<div class="cppm-yt-text">' . wp_kses_post($content) . '</div>';
    
    echo '<div class="cppm-yt-actions-row">
            <span style="cursor:pointer;">👍</span>
            <span style="cursor:pointer; margin-left:8px;">👎</span>
            <span style="cursor:pointer; margin-left:16px; font-weight:600;">Reply</span>
          </div>';
    echo '</div></div>';
    
    wp_send_json_success( ob_get_clean() );
}

// ==========================================
// 3. WOOCOMMERCE DASHBOARD
// ==========================================
add_action( 'init', 'cppm_add_endpoint' );
function cppm_add_endpoint() { add_rewrite_endpoint( 'my-courses', EP_ROOT | EP_PAGES ); }

add_filter( 'woocommerce_account_menu_items', 'cppm_menu' );
function cppm_menu( $items ) { return array_merge( array('my-courses' => 'My Courses'), $items ); }

add_action( 'woocommerce_account_my-courses_endpoint', 'cppm_content' );
function cppm_content() {
    echo '<h3 style="margin-bottom:20px;">Your Enrolled Modules</h3>';
    $orders = wc_get_orders( array('customer_id' => get_current_user_id(), 'status' => 'completed') );
    $ids = array();
    foreach ($orders as $o) { foreach ($o->get_items() as $item) { $ids[] = $item->get_product_id(); } }
    $ids = array_unique($ids);
    if(empty($ids)) { echo 'No active enrollments found.'; } else {
        echo '<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:25px;">';
        foreach ($ids as $pid) {
            $product = wc_get_product($pid);
            if (!$product) continue;
            $page_id = get_post_meta($pid, '_course_page_id', true);
            $url = $page_id ? get_permalink($page_id) : get_permalink($pid);
            echo '<div style="background:#fff; border:1px solid #e5e7eb; padding:25px; border-radius:15px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); display:flex; flex-direction:column; justify-content:space-between;">';
            echo '<div><h4 style="margin:0 0 10px 0; color:#111827; font-size:1.1rem;">' . esc_html($product->get_name()) . '</h4></div>';
            echo '<a href="' . esc_url($url) . '" style="background:#2563eb; color:#fff; padding:12px; text-align:center; text-decoration:none; border-radius:10px; font-weight:600;">Open Classroom</a>';
            echo '</div>';
        }
        echo '</div>';
    }
}

add_action( 'woocommerce_product_options_general_product_data', 'cppm_wc_fields' );
function cppm_wc_fields() {
    echo '<div class="options_group">';
    $pages = get_posts( array('post_type' => 'page', 'posts_per_page' => -1) );
    $opts = array('' => '-- Link to Classroom Page --');
    foreach ($pages as $p) { $opts[$p->ID] = $p->post_title; }
    woocommerce_wp_select( array('id' => '_course_page_id', 'label' => 'Learning Dashboard Page', 'options' => $opts) );
    echo '</div>';
}
add_action( 'woocommerce_process_product_meta', 'cppm_save_wc' );
function cppm_save_wc( $post_id ) {
    if ( isset($_POST['_course_page_id']) ) update_post_meta($post_id, '_course_page_id', sanitize_text_field($_POST['_course_page_id']));
}

// ==========================================
// 4. CERTIFICATE GENERATOR ENGINE
// ==========================================
add_action( 'template_redirect', 'cppm_generate_certificate' );
function cppm_generate_certificate() {
    if ( isset($_GET['cppm_certificate']) && is_user_logged_in() ) {
        $pid = intval($_GET['cppm_certificate']);
        $uid = get_current_user_id();

        // 1. Verify Completion
        $videos = get_post_meta($pid, '_cppm_videos_array', true);
        $total_vids = is_array($videos) ? count($videos) : 0;
        $completed_data = get_user_meta($uid, '_cppm_completed_videos', true);
        $completed_vids = isset($completed_data[$pid]) ? count($completed_data[$pid]) : 0;

        if ( $total_vids === 0 || $completed_vids < $total_vids ) {
            wp_die('<h3>Certificate Locked</h3><p>You must reach 100% completion before downloading your certificate.</p>', 'Access Denied', array('response' => 403));
        }

        // 2. Fetch Data
        $user_info = wp_get_current_user();
        $student_name = $user_info->display_name;
        $course_name = get_the_title($pid);
        $date = date('F j, Y');

        // 3. Render the Certificate Template
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Certificate - <?php echo esc_attr($student_name); ?></title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@400;600&display=swap');
                body { background: #e5e7eb; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; font-family: 'Inter', sans-serif; }
                .cert-container { background: #fff; width: 1050px; height: 740px; padding: 40px; box-sizing: border-box; border: 15px solid #111827; position: relative; text-align: center; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
                .cert-border { border: 3px solid #d1d5db; height: 100%; padding: 50px; box-sizing: border-box; position: relative; }
                
                h1 { font-family: 'Playfair Display', serif; font-size: 50px; color: #111827; margin: 0 0 15px; letter-spacing: 3px; text-transform: uppercase; }
                h2 { font-family: 'Playfair Display', serif; font-size: 26px; color: #6b7280; margin: 0 0 45px; font-style: italic; font-weight: normal; }
                
                .student-name { font-family: 'Playfair Display', serif; font-size: 55px; color: #2563eb; margin: 10px 0; border-bottom: 2px solid #e5e7eb; display: inline-block; padding: 0 50px 10px; font-weight: bold; }
                
                p { font-size: 18px; color: #4b5563; margin: 25px 0 10px; }
                .course-name { font-size: 32px; color: #111827; font-weight: 600; margin: 15px 0; padding: 0 40px;}
                
                .footer { display: flex; justify-content: space-between; margin-top: 80px; padding: 0 40px; }
                .signature { border-top: 2px solid #9ca3af; width: 250px; padding-top: 15px; font-weight: bold; color: #111827; font-size:14px; text-transform:uppercase; letter-spacing:1px; }
                .signature span { font-weight: normal; font-size: 16px; display: block; margin-top: 8px; color: #4b5563; text-transform:none; letter-spacing:0; }
                
                .seal { position: absolute; bottom: 65px; left: 50%; transform: translateX(-50%); width: 110px; height: 110px; background: #fbbf24; border-radius: 50%; display: flex; justify-content: center; align-items: center; border: 4px dashed #fff; box-shadow: 0 0 0 5px #fbbf24; color: #fff; font-weight: bold; text-align: center; font-size: 13px; text-transform: uppercase; letter-spacing:1px; line-height:1.4; }
                
                .print-btn { position: fixed; top: 20px; right: 20px; background: #2563eb; color: #fff; border: none; padding: 14px 28px; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: 0.2s; z-index: 1000; display: flex; align-items: center; gap: 8px;}
                .print-btn:hover { background: #1d4ed8; transform: translateY(-2px); }
                
                /* Rules to ensure it looks perfect when printing to PDF */
                @media print {
                    @page { margin: 0; size: landscape; }
                    body { background: #fff; display: block; height: auto; }
                    .cert-container { box-shadow: none; width: 100%; height: 100vh; border: 15px solid #111827; }
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            <button class="print-btn no-print" onclick="window.print()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Save as PDF / Print
            </button>
            <div class="cert-container">
                <div class="cert-border">
                    <h1>Certificate of Completion</h1>
                    <h2>This is to certify that</h2>
                    
                    <div class="student-name"><?php echo esc_html($student_name); ?></div>
                    
                    <p>has successfully completed the comprehensive training program for</p>
                    
                    <div class="course-name"><?php echo esc_html($course_name); ?></div>
                    
                    <div class="seal">Official<br>Certified</div>

                    <div class="footer">
                        <div class="signature">
                            Date Completed
                            <span><?php echo esc_html($date); ?></span>
                        </div>
                        <div class="signature">
                            Platform Administrator
                            <span>Verified Issue</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                // Auto-trigger print dialogue after images load
                window.onload = function() { setTimeout(function(){ window.print(); }, 500); }
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

// ==========================================
// 5. COMMERCE LOGIC & PROTECTIONS (PHASE 1)
// ==========================================

// Fix Issue #6: Restrict Courses and E-Books to a maximum quantity of 1
add_filter( 'woocommerce_is_sold_individually', 'cppm_restrict_virtual_qty', 10, 2 );
function cppm_restrict_virtual_qty( $return, $product ) {
    // If it's a virtual or downloadable product, it should only be bought once per order
    if ( $product->is_virtual() || $product->is_downloadable() ) {
        return true;
    }

    // Fallback: Check categories just in case
    $terms = get_the_terms( $product->get_id(), 'product_cat' );
    if ( $terms && ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            $slug = strtolower( $term->slug );
            if ( strpos($slug, 'ebook') !== false || strpos($slug, 'e-book') !== false || $slug === 'music-course' || $slug === 'course' ) {
                return true; 
            }
        }
    }
    
    return $return;
}

// Fix Issue #17: Redirect Guest Users to Login Page when attempting to Checkout
add_action( 'template_redirect', 'cppm_guest_checkout_redirect' );
function cppm_guest_checkout_redirect() {
    // If user is not logged in, and is trying to access the checkout page
    if ( ! is_user_logged_in() && is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) && ! is_wc_endpoint_url( 'order-received' ) ) {
        
        // Add a friendly notice
        wc_add_notice( 'Please log in or register to complete your purchase and access your classroom.', 'notice' );
        
        // Redirect to the WooCommerce "My Account" page (which acts as the login/registration page)
        wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
        exit;
    }
}

// ==========================================
// 5. SECURE CHECKOUT: LOCK BILLING EMAIL
// ==========================================

// Force the billing email field to be Read-Only and add an explanation note
add_filter( 'woocommerce_checkout_fields', 'cppm_lock_billing_email_field', 9999 );
function cppm_lock_billing_email_field( $fields ) {
    if ( is_user_logged_in() ) {
        // Lock the field so it cannot be typed in
        $fields['billing']['billing_email']['custom_attributes'] = array( 
            'readonly' => 'readonly',
            'style'    => 'background-color: #f1f5f9; cursor: not-allowed; color: #64748b;' 
        );
        
        // Add a helpful note explaining why it's locked
        $fields['billing']['billing_email']['description'] = '<span style="color:#2874f0; font-size:12px; font-weight:600;">🔒 Email field is Read-Only.</span>';
    }
    return $fields;
}

// Guarantee the field is auto-filled with the exact logged-in user's email
add_filter( 'default_checkout_billing_email', 'cppm_force_user_checkout_email' );
function cppm_force_user_checkout_email( $value ) {
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        return $current_user->user_email;
    }
    return $value;
}

// ==========================================
// 6. CHECKOUT UX: AUTO-FILL VIA PINCODE API
// ==========================================
add_action( 'wp_footer', 'cppm_pincode_autofill_script' );
function cppm_pincode_autofill_script() {
    if ( ! function_exists('is_checkout') || ! is_checkout() ) return;
    ?>
    <style>
        /* Forcefully freeze the WooCommerce Javascript State Dropdown */
        .cppm-locked-state-field .select2-selection {
            background-color: #f1f5f9 !important;
            cursor: not-allowed !important;
            pointer-events: none !important; /* This makes it completely unclickable */
        }
        .cppm-locked-state-field .select2-selection__arrow {
            display: none !important; /* Hides the little dropdown arrow */
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const types = ['billing', 'shipping'];

        const stateMap = {
            "Andaman and Nicobar Islands": "AN", "Andhra Pradesh": "AP", "Arunachal Pradesh": "AR",
            "Assam": "AS", "Bihar": "BR", "Chandigarh": "CH", "Chhattisgarh": "CT",
            "Dadra and Nagar Haveli": "DN", "Daman and Diu": "DD", "Delhi": "DL",
            "Goa": "GA", "Gujarat": "GJ", "Haryana": "HR", "Himachal Pradesh": "HP",
            "Jammu and Kashmir": "JK", "Jharkhand": "JH", "Karnataka": "KA",
            "Kerala": "KL", "Lakshadweep": "LD", "Madhya Pradesh": "MP", "Maharashtra": "MH",
            "Manipur": "MN", "Meghalaya": "ML", "Mizoram": "MZ", "Nagaland": "NL",
            "Odisha": "OR", "Puducherry": "PY", "Punjab": "PB", "Rajasthan": "RJ",
            "Sikkim": "SK", "Tamil Nadu": "TN", "Telangana": "TG", "Tripura": "TR",
            "Uttar Pradesh": "UP", "Uttarakhand": "UT", "West Bengal": "WB"
        };

        types.forEach(type => {
            const pinInput = document.getElementById(type + '_postcode');
            if (!pinInput) return;

            // 1. Create the Area Dropdown UI
            const areaContainer = document.createElement('p');
            areaContainer.className = 'form-row form-row-wide cppm-area-wrapper';
            areaContainer.id = type + '_area_container';
            // CHANGED: Now set to 'block' so it is ALWAYS visible!
            areaContainer.style.display = 'block'; 
            areaContainer.innerHTML = `
                <label for="${type}_area_select">Area / Post Office <abbr class="required" title="required">*</abbr></label>
                <select id="${type}_area_select" class="select" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px; outline:none; background:#f8fafc; font-size:14px;">
                    <option value="">Select your area...</option>
                </select>
                <span class="cppm-pin-loader" style="display:none; font-size:12px; color:#2874f0; margin-top:5px;">Fetching areas...</span>
            `;

            pinInput.closest('.form-row').parentNode.insertBefore(areaContainer, pinInput.closest('.form-row').nextSibling);

            const areaSelect = document.getElementById(type + '_area_select');
            const address2 = document.getElementById(type + '_address_2');
            const loader = areaContainer.querySelector('.cppm-pin-loader');

            if (address2 && address2.closest('.form-row')) {
                address2.closest('.form-row').style.display = 'none';
            }

            // 2. Listen for PIN Code typing
            pinInput.addEventListener('keyup', function() {
                const pincode = this.value.trim();
                
                if (pincode.length === 6 && !isNaN(pincode)) {
                    areaSelect.style.display = 'none';
                    loader.style.display = 'block';

                    fetch(`https://api.postalpincode.in/pincode/${pincode}`)
                        .then(res => res.json())
                        .then(data => {
                            loader.style.display = 'none';
                            areaSelect.style.display = 'block';

                            if (data[0].Status === 'Success') {
                                const postOffices = data[0].PostOffice;
                                const firstPO = postOffices[0];

                                const cityInput = document.getElementById(type + '_city');
                                if (cityInput) {
                                    cityInput.value = firstPO.District;
                                    if(typeof jQuery !== 'undefined') jQuery(cityInput).trigger('change');
                                }

                                const stateSelect = document.getElementById(type + '_state');
                                if (stateSelect && stateMap[firstPO.State]) {
                                    if(typeof jQuery !== 'undefined') {
                                        jQuery(stateSelect).val(stateMap[firstPO.State]).trigger('change');
                                    } else {
                                        stateSelect.value = stateMap[firstPO.State];
                                    }
                                }

                                areaSelect.innerHTML = '<option value="">Select your area...</option>';
                                postOffices.forEach(po => {
                                    const opt = document.createElement('option');
                                    opt.value = po.Name;
                                    opt.textContent = po.Name;
                                    areaSelect.appendChild(opt);
                                });
                                
                                if (address2) address2.value = '';

                            } else {
                                areaSelect.innerHTML = '<option value="">Invalid PIN Code</option>';
                            }
                        })
                        .catch(err => {
                            loader.style.display = 'none';
                            areaSelect.style.display = 'block';
                            console.error("PIN API Error: ", err);
                        });
                } else {
                    // CHANGED: If the user deletes the PIN, keep the box visible but reset it.
                    areaSelect.innerHTML = '<option value="">Select your area...</option>';
                    areaSelect.style.display = 'block';
                    loader.style.display = 'none';
                }
            });

            // 3. Save selected Area to native Address 2 field
            areaSelect.addEventListener('change', function() {
                if (address2) {
                    address2.value = this.value;
                    if(typeof jQuery !== 'undefined') jQuery(address2).trigger('change');
                }
            });
        });
    });
    </script>
    <?php
}

// ==========================================
// 7. CHECKOUT UX: REORDER & LOCK CITY/STATE
// ==========================================
add_filter( 'woocommerce_default_address_fields', 'cppm_reorder_and_lock_checkout_fields', 999 );
function cppm_reorder_and_lock_checkout_fields( $fields ) {
    
    // 1. Shift Pincode above City and State
    if ( isset($fields['postcode']) ) {
        $fields['postcode']['priority'] = 65;
    }

    // 2. Lock the City field
    if ( isset($fields['city']) ) {
        $fields['city']['custom_attributes'] = array( 
            'readonly' => 'readonly',
            'style'    => 'background-color: #f1f5f9; cursor: not-allowed; color: #64748b;' 
        );
    }

    // 3. Prepare the State field for the Javascript Lock
    if ( isset($fields['state']) ) {
        // We add a custom class here so our CSS can find and freeze the Select2 Javascript element
        $fields['state']['class'][] = 'cppm-locked-state-field';
        $fields['state']['custom_attributes'] = array( 
            'readonly' => 'readonly',
            'onmousedown' => 'return false;', // Stops native clicks
        );
    }

    return $fields;
}

// ==========================================
// 8. CHECKOUT UX: REORDER EMAIL & PHONE
// ==========================================
add_filter( 'woocommerce_checkout_fields', 'cppm_reorder_checkout_email_phone', 999 );
function cppm_reorder_checkout_email_phone( $fields ) {
    
    // 1. Move Phone directly below Last Name (Priority 20)
    if ( isset($fields['billing']['billing_phone']) ) {
        $fields['billing']['billing_phone']['priority'] = 25;
        // Make it take up the left 50% of the screen
        $fields['billing']['billing_phone']['class'] = array('form-row-first');
        $fields['billing']['billing_phone']['clear'] = true; // Ensure it drops to a new line below Name
    }

    // 2. Move Email right next to Phone
    if ( isset($fields['billing']['billing_email']) ) {
        $fields['billing']['billing_email']['priority'] = 26;
        // Make it take up the right 50% of the screen
        $fields['billing']['billing_email']['class'] = array('form-row-last');
        $fields['billing']['billing_email']['clear'] = false;
    }

    // 3. Ensure the next field (usually Country or Address) drops cleanly below them
    if ( isset($fields['billing']['billing_country']) ) {
        $fields['billing']['billing_country']['clear'] = true;
    } elseif ( isset($fields['billing']['billing_address_1']) ) {
        $fields['billing']['billing_address_1']['clear'] = true;
    }
    
    // Drop Billing Country to the bottom
    if ( isset($fields['billing']['billing_country']) ) {
        $fields['billing']['billing_country']['priority'] = 999;
        $fields['billing']['billing_country']['class'] = array('form-row-wide');
        $fields['billing']['billing_country']['clear'] = true;
    }
    // Drop Shipping Country to the bottom (if enabled)
    if ( isset($fields['shipping']['shipping_country']) ) {
        $fields['shipping']['shipping_country']['priority'] = 999;
        $fields['shipping']['shipping_country']['class'] = array('form-row-wide');
        $fields['shipping']['shipping_country']['clear'] = true;
    }

    return $fields;
}

// ==========================================
// 9. CHECKOUT UX: COUPON
// ==========================================

// --- A. Proxy Coupon UI in "Your Order" Section ---

// 1. Hide the default top coupon form securely via CSS
add_action( 'wp_head', 'cppm_hide_default_coupon_banner' );
function cppm_hide_default_coupon_banner() {
    if ( function_exists('is_checkout') && is_checkout() ) {
        echo '<style>
            .woocommerce-form-coupon-toggle { display: none !important; }
            form.checkout_coupon { display: none !important; }
        </style>';
    }
}

// 2. Inject the beautiful Proxy Coupon Input right above Payment Methods
add_action( 'woocommerce_review_order_before_payment', 'cppm_inject_order_review_coupon' );
function cppm_inject_order_review_coupon() {
    ?>
    <div class="cppm-proxy-coupon-wrap" style="display:flex; gap:10px; margin-bottom:20px; padding:15px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px;">
        <input type="text" id="cppm-proxy-coupon-input" class="input-text" placeholder="Have a coupon? Enter here" style="flex:1; border:1px solid #e2e8f0; border-radius:6px; padding:10px; outline:none; font-size:14px;">
        <button type="button" id="cppm-proxy-coupon-btn" class="button" style="background:#1c1d1f; color:#fff; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:600; transition:0.2s;">Apply</button>
    </div>
    <?php
}

// 3. Connect the Proxy Input to the real hidden WooCommerce form
add_action( 'wp_footer', 'cppm_proxy_coupon_script' );
function cppm_proxy_coupon_script() {
    if ( ! function_exists('is_checkout') || ! is_checkout() ) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.body.addEventListener('click', function(e) {
            // If they click our new Apply button...
            if (e.target && e.target.id === 'cppm-proxy-coupon-btn') {
                e.preventDefault();
                
                var newCode = document.getElementById('cppm-proxy-coupon-input').value;
                var realInput = document.getElementById('coupon_code');
                var realBtn = document.querySelector('button[name="apply_coupon"]');
                
                if (newCode.trim() === '') {
                    alert('Please enter a coupon code.');
                    return;
                }

                // Copy the text to the hidden real input and force a click on the real button
                if (realInput && realBtn) {
                    var originalText = e.target.innerText;
                    e.target.innerText = 'Applying...';
                    e.target.style.opacity = '0.7';
                    
                    realInput.value = newCode;
                    realBtn.click();
                    
                    // Reset our proxy button after WooCommerce AJAX reloads the order review
                    setTimeout(function(){
                        e.target.innerText = originalText;
                        e.target.style.opacity = '1';
                    }, 2500);
                }
            }
        });
    });
    </script>
    <?php
}

// ==========================================
// 10. MEMBERSHIP ENGINE: ORDER COMPLETION TRIGGER
// ==========================================
add_action( 'woocommerce_order_status_completed', 'cppm_grant_pro_membership_on_purchase' );
function cppm_grant_pro_membership_on_purchase( $order_id ) {
    $order = wc_get_order( $order_id );
    $user_id = $order->get_user_id();

    if ( ! $user_id ) return;

    // IMPORTANT: Change 9999 to the actual Product ID of your "Pro Pass"
    $pro_pass_id = 9999; 
    
    $has_pro_pass = false;
    foreach ( $order->get_items() as $item ) {
        if ( $item->get_product_id() == $pro_pass_id ) {
            $has_pro_pass = true;
            break;
        }
    }

    if ( $has_pro_pass ) {
        $current_time = time();
        
        // Let's grant 1 Year of Access (365 days)
        $duration_in_seconds = YEAR_IN_SECONDS; 
        
        // Fetch existing expiry just in case they are renewing early
        $existing_expiry = get_user_meta( $user_id, '_cppm_pro_member_expiry', true );
        
        if ( $existing_expiry && $existing_expiry > $current_time ) {
            // They are renewing! Add 1 year to their existing remaining time.
            $new_expiry = $existing_expiry + $duration_in_seconds;
        } else {
            // Brand new member! Start the 1-year clock right now.
            $new_expiry = $current_time + $duration_in_seconds;
        }

        // Save the futuristic timestamp to their user profile
        update_user_meta( $user_id, '_cppm_pro_member_expiry', $new_expiry );
        
        // Add a secure order note for your records
        $order->add_order_note( 'Sarkari Musician Pro Pass activated. Expiry set to: ' . wp_date( 'd M Y', $new_expiry ) );
    }
}

// ==========================================
// 11. MOCK TEST LEDGER: WOOCOMMERCE BACKEND UI
// ==========================================

// --- A. Add fields to SIMPLE Products (General Tab) ---
add_action( 'woocommerce_product_options_general_product_data', 'cppm_mock_test_simple_fields' );
function cppm_mock_test_simple_fields() {
    echo '<div class="options_group">';
    
    // 1. Target Mock Test ID
    woocommerce_wp_text_input( array(
        'id'          => '_cppm_mock_test_id',
        'label'       => __( 'Target Mock Test ID', 'cppm' ),
        'description' => __( 'Enter the Post ID of the Mock Test this unlocks.', 'cppm' ),
        'desc_tip'    => true,
        'type'        => 'number',
        'custom_attributes' => array('step' => '1', 'min' => '1')
    ) );

    // 2. Attempts Granted
    woocommerce_wp_text_input( array(
        'id'          => '_cppm_mock_test_attempts',
        'label'       => __( 'Attempts Granted', 'cppm' ),
        'description' => __( 'How many attempts does this grant? (e.g., 1, 3, 5)', 'cppm' ),
        'desc_tip'    => true,
        'type'        => 'number',
        'custom_attributes' => array('step' => '1', 'min' => '1')
    ) );
    
    echo '</div>';
}

// Save Simple Product Fields
add_action( 'woocommerce_process_product_meta', 'cppm_save_mock_test_simple_fields' );
function cppm_save_mock_test_simple_fields( $post_id ) {
    $test_id = isset( $_POST['_cppm_mock_test_id'] ) ? sanitize_text_field( $_POST['_cppm_mock_test_id'] ) : '';
    $attempts = isset( $_POST['_cppm_mock_test_attempts'] ) ? sanitize_text_field( $_POST['_cppm_mock_test_attempts'] ) : '';
    update_post_meta( $post_id, '_cppm_mock_test_id', $test_id );
    update_post_meta( $post_id, '_cppm_mock_test_attempts', $attempts );
}

// --- B. Add fields to VARIABLE Products (Variations Tab) ---
add_action( 'woocommerce_product_after_variable_attributes', 'cppm_mock_test_variation_fields', 10, 3 );
function cppm_mock_test_variation_fields( $loop, $variation_data, $variation ) {
    echo '<div class="options_group form-row form-row-full">';
    
    // 1. Target Mock Test ID (Variation)
    woocommerce_wp_text_input( array(
        'id'          => '_cppm_mock_test_id[' . $loop . ']',
        'label'       => __( 'Target Mock Test ID', 'cppm' ),
        'wrapper_class' => 'form-row form-row-first',
        'value'       => get_post_meta( $variation->ID, '_cppm_mock_test_id', true )
    ) );

    // 2. Attempts Granted (Variation)
    woocommerce_wp_text_input( array(
        'id'          => '_cppm_mock_test_attempts[' . $loop . ']',
        'label'       => __( 'Attempts Granted', 'cppm' ),
        'wrapper_class' => 'form-row form-row-last',
        'value'       => get_post_meta( $variation->ID, '_cppm_mock_test_attempts', true )
    ) );
    
    echo '</div>';
}

// Save Variable Product Fields
add_action( 'woocommerce_save_product_variation', 'cppm_save_mock_test_variation_fields', 10, 2 );
function cppm_save_mock_test_variation_fields( $variation_id, $i ) {
    $test_id = isset( $_POST['_cppm_mock_test_id'][$i] ) ? sanitize_text_field( $_POST['_cppm_mock_test_id'][$i] ) : '';
    $attempts = isset( $_POST['_cppm_mock_test_attempts'][$i] ) ? sanitize_text_field( $_POST['_cppm_mock_test_attempts'][$i] ) : '';
    update_post_meta( $variation_id, '_cppm_mock_test_id', $test_id );
    update_post_meta( $variation_id, '_cppm_mock_test_attempts', $attempts );
}


// ==========================================
// 12. MOCK TEST LEDGER: CHECKOUT DEPOSIT ENGINE
// ==========================================
add_action( 'woocommerce_order_status_completed', 'cppm_deposit_mock_attempts_on_purchase' );
function cppm_deposit_mock_attempts_on_purchase( $order_id ) {
    $order = wc_get_order( $order_id );
    $user_id = $order->get_user_id();

    // The user must be logged in to receive mock tests
    if ( ! $user_id ) return;

    // Fetch the user's existing ledger (returns an empty array if they are a new student)
    $ledger = get_user_meta( $user_id, '_cppm_mock_balances', true );
    if ( ! is_array( $ledger ) ) {
        $ledger = array();
    }

    $ledger_updated = false;

    // Loop through every item in their cart
    foreach ( $order->get_items() as $item ) {
        
        // Target the exact variation ID if it's a variable product, otherwise use the standard product ID
        $product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();

        // Check if this specific item has our custom Mock Test meta attached
        $target_test_id   = get_post_meta( $product_id, '_cppm_mock_test_id', true );
        $attempts_granted = get_post_meta( $product_id, '_cppm_mock_test_attempts', true );

        // If it is a Mock Test product, process the deposit!
        if ( ! empty( $target_test_id ) && ! empty( $attempts_granted ) ) {
            
            // Format the key cleanly (e.g., "test_id_105")
            $test_key = 'test_id_' . intval( $target_test_id );
            
            // Multiply attempts by quantity! (If they buy two "3-Attempt" packs, they get 6 attempts)
            $total_new_attempts = intval( $attempts_granted ) * $item->get_quantity(); 

            // Find out how many attempts they currently have for this test (defaults to 0)
            $current_balance = isset( $ledger[ $test_key ] ) ? intval( $ledger[ $test_key ] ) : 0;

            // The Math: Add their newly purchased attempts to their current balance
            $ledger[ $test_key ] = $current_balance + $total_new_attempts;
            $ledger_updated = true;

            // Security/Auditing: Add a permanent green note to the WooCommerce order for the Admin to see
            $order->add_order_note( sprintf( 'Mock Test Ledger: Deposited %d attempts for Test ID %s. Student now has %d total attempts available.', $total_new_attempts, intval($target_test_id), $ledger[ $test_key ] ) );
        }
    }

    // If changes were made, securely save the new ledger array back to the user's profile
    if ( $ledger_updated ) {
        update_user_meta( $user_id, '_cppm_mock_balances', $ledger );
    }
}