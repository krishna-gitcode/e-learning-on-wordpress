<?php
/**
 * Core: Custom UPI Payment Gateway
 * Architecture: Modular / Asset Separated
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ==========================================
// 1. REGISTER THE CUSTOM UPI GATEWAY
// ==========================================
add_filter( 'woocommerce_payment_gateways', 'cppm_add_upi_gateway_class' );
function cppm_add_upi_gateway_class( $gateways ) {
    $gateways[] = 'WC_Gateway_Custom_UPI';
    return $gateways;
}

// ==========================================
// 2. ENQUEUE CHECKOUT ASSETS
// ==========================================
add_action( 'wp_enqueue_scripts', 'cppm_enqueue_upi_assets', 999 );
function cppm_enqueue_upi_assets() {
    // Only load these files on the checkout page
    if ( function_exists('is_checkout') && is_checkout() ) {
        $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
        wp_enqueue_style( 'cppm-upi-checkout-css', $plugin_url . 'assets/css/checkout-upi.css', array(), '1.0.0' );
        wp_enqueue_script( 'cppm-upi-checkout-js', $plugin_url . 'assets/js/checkout-upi.js', array('jquery'), '1.0.0', true );
    }
}

// ==========================================
// 3. BUILD THE GATEWAY CLASS
// ==========================================
add_action( 'plugins_loaded', 'cppm_init_upi_gateway' );
function cppm_init_upi_gateway() {
    
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    class WC_Gateway_Custom_UPI extends WC_Payment_Gateway {
        
        public function __construct() {
            $this->id                 = 'custom_upi';
            $this->has_fields         = true; // Required for our custom upload field
            $this->method_title       = 'UPI QR Code (Manual)';
            $this->method_description = 'Accept payments directly via GPay, PhonePe, Paytm, etc. Generates a dynamic QR code after checkout.';

            $this->init_form_fields();
            $this->init_settings();

            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->upi_id       = $this->get_option( 'upi_id' );
            $this->qr_code_url  = $this->get_option( 'qr_code_url' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => 'Enable/Disable',
                    'type'    => 'checkbox',
                    'label'   => 'Enable Custom UPI Gateway',
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Pay via UPI (GPay, PhonePe, Paytm)',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'Payment method description that the customer will see on your checkout.',
                    'default'     => 'Scan the QR code below and upload a screenshot of your successful transaction.',
                ),
                'upi_id' => array(
                    'title'       => 'Your UPI ID',
                    'type'        => 'text',
                    'description' => 'Example: yourname@oksbi',
                    'default'     => ''
                ),
                'qr_code_url' => array(
                    'title'       => 'QR Code Image URL',
                    'type'        => 'text',
                    'description' => 'Paste the URL of your UPI QR code image here.',
                    'default'     => ''
                )
            );
        }

        // ==========================================
        // 4. FRONTEND CHECKOUT HTML
        // ==========================================
        public function payment_fields() {
            if ( $this->description ) {
                echo '<p>' . esc_html( $this->description ) . '</p>';
            }
            
            // Clean, pure HTML structure. CSS/JS is handled externally.
            ?>
            <div class="cppm-upi-payment-box">
                <?php if ( $this->qr_code_url ) : ?>
                    <img src="<?php echo esc_url( $this->qr_code_url ); ?>" class="cppm-upi-qr" alt="UPI QR Code">
                <?php endif; ?>
                
                <?php if ( $this->upi_id ) : ?>
                    <p class="cppm-upi-instructions">Or pay directly to UPI ID: <strong><?php echo esc_html( $this->upi_id ); ?></strong></p>
                <?php endif; ?>

                <div class="cppm-upi-upload-wrap">
                    <label class="cppm-upi-upload-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        Click to Upload Screenshot
                    </label>
                    <input type="file" name="upi_screenshot" id="upi_screenshot" accept="image/*" required>
                    <div class="cppm-upi-file-name"></div>
                </div>
            </div>
            <?php
        }

        // ==========================================
        // 5. PROCESS PAYMENT & HANDLE FILE UPLOAD
        // ==========================================
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );

            // Handle the File Upload
            if ( isset( $_FILES['upi_screenshot'] ) && ! empty( $_FILES['upi_screenshot']['name'] ) ) {
                
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                require_once( ABSPATH . 'wp-admin/includes/media.php' );

                $attachment_id = media_handle_upload( 'upi_screenshot', $order_id );

                if ( is_wp_error( $attachment_id ) ) {
                    wc_add_notice( 'Error uploading file: ' . $attachment_id->get_error_message(), 'error' );
                    return; // Stop payment process if upload fails
                } else {
                    $img_url = wp_get_attachment_url( $attachment_id );
                    
                    // 1. Keep the hidden meta for the frontend UI logic
                    $order->update_meta_data( '_upi_screenshot_url', $img_url );
                    $order->update_meta_data( '_upi_screenshot_id', $attachment_id );
                    
                    // 2. THE APP FIX: Save as a public Custom Field (no underscore)
                    $order->update_meta_data( 'UPI Screenshot Link', esc_url($img_url) );
                    
                    // 3. THE NOTE FIX: The mobile app strips HTML buttons.
                    $note = "💳 Payment Screenshot Uploaded!\n\nTap the link below to view it:\n" . esc_url($img_url);
                    $order->add_order_note( $note );
                }
            } else {
                wc_add_notice( 'Payment screenshot is required to complete this order.', 'error' );
                return;
            }

            // Mark as On-Hold (awaiting manual verification)
            $order->update_status( 'on-hold', __( 'Awaiting manual UPI payment verification.', 'woocommerce' ) );

            // Reduce stock levels
            wc_reduce_stock_levels( $order_id );

            // Remove cart
            WC()->cart->empty_cart();

            // Return thank you redirect
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order )
            );
        }
    }
}