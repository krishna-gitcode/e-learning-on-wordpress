<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================
// 1. REGISTER THE CUSTOM UPI GATEWAY
// ==========================================
add_filter( 'woocommerce_payment_gateways', 'cppm_add_upi_gateway_class' );
function cppm_add_upi_gateway_class( $gateways ) {
    $gateways[] = 'WC_Gateway_Custom_UPI';
    return $gateways;
}

// ==========================================
// 2. BUILD THE GATEWAY CLASS
// ==========================================
add_action( 'plugins_loaded', 'cppm_init_upi_gateway' );
function cppm_init_upi_gateway() {
    
    // Ensure WooCommerce is active before declaring the class
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    class WC_Gateway_Custom_UPI extends WC_Payment_Gateway {
        
        public function __construct() {
            $this->id                 = 'custom_upi';
            $this->has_fields         = false;
            $this->method_title       = 'UPI QR Code (Manual)';
            $this->method_description = 'Accept payments directly via GPay, PhonePe, Paytm, etc. Generates a dynamic QR code after checkout.';

            $this->init_form_fields();
            $this->init_settings();

            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->upi_id       = $this->get_option( 'upi_id' );
            $this->payee_name   = $this->get_option( 'payee_name' );

            // Save Settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            
            // Display QR & Upload Form on Thank You & Order View Pages
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'display_qr_and_upload_form' ) );
            add_action( 'woocommerce_view_order', array( $this, 'display_qr_and_upload_form' ) );
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array( 'title' => 'Enable/Disable', 'type' => 'checkbox', 'label' => 'Enable UPI QR Payment', 'default' => 'yes' ),
                'title' => array( 'title' => 'Title', 'type' => 'text', 'default' => 'UPI (GPay, PhonePe, Paytm)' ),
                'description' => array( 'title' => 'Description', 'type' => 'textarea', 'default' => 'Place your order to generate the QR code. You will upload the payment screenshot on the next screen.' ),
                'upi_id' => array( 'title' => 'Your UPI ID (VPA)', 'type' => 'text', 'default' => 'yourname@bank', 'description' => 'The UPI ID where you want to receive money.' ),
                'payee_name' => array( 'title' => 'Payee / Business Name', 'type' => 'text', 'default' => 'My Academy' ),
            );
        }

        // Process the checkout, set to On-Hold, redirect to Thank You page
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            $order->update_status( 'on-hold', 'Awaiting manual UPI payment screenshot.' );
            WC()->cart->empty_cart();
            
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order )
            );
        }

        // Generate Dynamic QR and Screenshot Uploader
        public function display_qr_and_upload_form( $order_id ) {
            $order = wc_get_order( $order_id );
            
            if ( $order->get_payment_method() !== $this->id || $order->has_status( 'completed' ) ) return;

            $uploaded_img = $order->get_meta( '_upi_screenshot_url' );
            if ( $uploaded_img ) {
                echo '<div style="background:#ecfdf5; border:1px solid #10b981; padding:20px; border-radius:8px; margin-bottom:30px;">';
                echo '<h3 style="color:#047857; margin-top:0;">✅ Payment Screenshot Received</h3>';
                echo '<p style="color:#065f46; margin-bottom:0;">We are currently verifying your transaction. Your course will be unlocked shortly.</p>';
                echo '</div>';
                return;
            }

            $total = $order->get_total();
            $order_num = $order->get_order_number();
            
            $upi_string = sprintf( 'upi://pay?pa=%s&pn=%s&am=%s&cu=INR&tn=Order-%s',
                urlencode( $this->upi_id ),
                urlencode( $this->payee_name ),
                $total,
                urlencode( $order_num )
            );

            $qr_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=10&data=' . urlencode( $upi_string );

            ?>
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:30px; text-align:center; margin-bottom:30px; display:flex; flex-direction:column; align-items:center;">
                <h2 style="margin-top:0; color:#0f172a;">Scan to Pay</h2>
                <p style="color:#64748b; font-size:15px; max-width:400px;">Open any UPI app (Google Pay, PhonePe, Paytm) and scan this code. The exact amount and your Order ID (<strong><?php echo $order_num; ?></strong>) will be pre-filled.</p>
                
                <img src="<?php echo esc_url($qr_image_url); ?>" alt="UPI QR Code" style="background:#fff; border:4px solid #fff; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,0.1); margin: 20px 0; width: 250px; height: 250px;" />
                
                <div style="width:100%; max-width:400px; background:#fff; padding:20px; border:1px dashed #cbd5e1; border-radius:8px; text-align:left;">
                    <h3 style="margin-top:0; font-size:16px;">Upload Payment Proof</h3>
                    <form method="post" enctype="multipart/form-data" style="margin:0;">
                        <?php wp_nonce_field( 'upload_upi_screenshot', 'upi_nonce' ); ?>
                        <input type="hidden" name="upi_order_id" value="<?php echo esc_attr($order_id); ?>">
                        <input type="file" name="upi_screenshot" accept="image/png, image/jpeg, image/jpg" required style="display:block; width:100%; margin-bottom:15px; padding:10px; border:1px solid #e2e8f0; border-radius:6px;">
                        <button type="submit" name="submit_upi_screenshot" style="width:100%; background:#2563eb; color:#fff; border:none; padding:12px; border-radius:6px; font-weight:bold; cursor:pointer;">Submit Screenshot</button>
                    </form>
                </div>
            </div>
            <?php
        }
    }
}

// ==========================================
// 3. PROCESS THE FILE UPLOAD
// ==========================================
add_action( 'template_redirect', 'cppm_handle_upi_screenshot_upload' );
function cppm_handle_upi_screenshot_upload() {
    if ( isset($_POST['submit_upi_screenshot']) && isset($_POST['upi_order_id']) && isset($_FILES['upi_screenshot']) ) {
        
        if ( ! wp_verify_nonce( $_POST['upi_nonce'], 'upload_upi_screenshot' ) ) {
            wc_add_notice( 'Security check failed. Please try again.', 'error' );
            return;
        }

        $order_id = intval( $_POST['upi_order_id'] );
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        $attachment_id = media_handle_upload( 'upi_screenshot', $order_id );

        if ( is_wp_error( $attachment_id ) ) {
            wc_add_notice( 'Error uploading file: ' . $attachment_id->get_error_message(), 'error' );
        } else {
            $img_url = wp_get_attachment_url( $attachment_id );
            
            // 1. Keep the hidden meta for the frontend UI logic
            $order->update_meta_data( '_upi_screenshot_url', $img_url );
            $order->update_meta_data( '_upi_screenshot_id', $attachment_id );
            
            // 2. THE APP FIX: Save as a public Custom Field (no underscore)
            // This forces the URL to appear in the WooCommerce Mobile App under "Custom Fields"
            $order->update_meta_data( 'UPI Screenshot Link', esc_url($img_url) );
            
            // 3. THE NOTE FIX: The mobile app strips HTML buttons. 
            // We use a raw URL here so iOS/Android OS natively turns it into a clickable link.
            $note = "💳 Payment Screenshot Uploaded!\n\nTap the link below to view it:\n" . esc_url($img_url);
            $order->add_order_note( $note );
            
            $order->save();
            
            wc_add_notice( 'Your payment screenshot has been uploaded successfully! We will verify it and unlock your course shortly.', 'success' );
        }
        
        wp_safe_redirect( $order->get_view_order_url() );
        exit;
    }
}