<?php
/**
 * Plugin Name: Custom Presto Playlist Manager
 * Description: Version 24. Features: Builder-Proof Footer Injection, Premium UX, and Modular Architecture.
 * Version: 1.0
 * Author: Krishna Kumar
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Define plugin paths for easy requiring
define( 'CPPM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// 1. Load Admin Settings & Custom Post Types
require_once CPPM_PLUGIN_DIR . 'includes/admin-settings.php';

// 2. Load the Frontend Player, Shortcode & Footer JS
require_once CPPM_PLUGIN_DIR . 'includes/frontend-player.php';

// 3. Load WooCommerce Integration & AJAX Handlers
require_once CPPM_PLUGIN_DIR . 'includes/woocommerce-logic.php';

// 4. Load Custom Shortcodes (Top Products)
require_once CPPM_PLUGIN_DIR . 'includes/top-products.php';

// 5. Load Enrolled Courses Shortcode
require_once CPPM_PLUGIN_DIR . 'includes/enrolled-courses.php';

// --- E-Book Progress AJAX ---
add_action('wp_ajax_cppm_save_multi_ebook_progress', 'cppm_save_multi_ebook_progress_callback');
function cppm_save_multi_ebook_progress_callback() {
    $user_id = get_current_user_id();
    $ebook_id = isset($_POST['ebook_id']) ? intval($_POST['ebook_id']) : 0;
    
    if ($user_id && $ebook_id && isset($_POST['progress_json'])) {
        // Decode the incoming JSON (e.g., {"0": 12, "1": 5} meaning Doc 0 is on Page 12, Doc 1 is on Page 5)
        $progress_array = json_decode(stripslashes($_POST['progress_json']), true);
        if (is_array($progress_array)) {
            update_user_meta($user_id, '_cppm_ebook_progress_' . $ebook_id, $progress_array);
        }
    }
    wp_send_json_success();
}
// 6. E-book
require_once CPPM_PLUGIN_DIR . 'includes/frontend-ebook-reader.php';

// Load Custom UPI Payment Gateway
require_once CPPM_PLUGIN_DIR . 'includes/class-wc-gateway-custom-upi.php';

// Prevent Duplicate Course Purchases
require_once CPPM_PLUGIN_DIR . 'includes/cppm-duplicate-prevention.php';

// Daily Database Cleanup for Abandoned Orders
require_once CPPM_PLUGIN_DIR . 'includes/cppm-order-cleanup.php';

// Dynamic Navigation and Auth Icons
require_once CPPM_PLUGIN_DIR . 'includes/cppm-navigation.php';

// Unified Student Dashboard
require_once CPPM_PLUGIN_DIR . 'includes/cppm-student-dashboard.php';

// Custom Storefront
require_once CPPM_PLUGIN_DIR . 'includes/cppm-storefront.php';

// Phase 3: The Great Page Overhaul (Split Architecture)
require_once CPPM_PLUGIN_DIR . 'includes/cppm-search-results.php';
require_once CPPM_PLUGIN_DIR . 'includes/cppm-single-product.php';
require_once CPPM_PLUGIN_DIR . 'includes/cppm-my-account.php';