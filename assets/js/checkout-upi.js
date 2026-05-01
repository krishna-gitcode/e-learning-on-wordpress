jQuery(document).ready(function($) {
    // 1. MUST HAVE: Force WooCommerce checkout form to accept file uploads
    $('form.checkout').attr('enctype', 'multipart/form-data');

    // 2. Dynamic File Name Display
    $(document).on('change', '#upi_screenshot', function() {
        var fileName = $(this).val().split('\\').pop();
        if (fileName) {
            $('.cppm-upi-file-name').text('Attached: ' + fileName);
            $('.cppm-upi-upload-wrap').css({'border-color': '#16a34a', 'background': '#f0fdf4'});
            $('.cppm-upi-upload-label svg').css('color', '#16a34a');
        } else {
            $('.cppm-upi-file-name').text('');
            $('.cppm-upi-upload-wrap').css({'border-color': '#cbd5e1', 'background': '#ffffff'});
            $('.cppm-upi-upload-label svg').css('color', '#64748b');
        }
    });

    // 3. Prevent Checkout if Screenshot is Missing
    $('form.checkout').on('checkout_place_order', function() {
        var paymentMethod = $('input[name="payment_method"]:checked').val();
        if (paymentMethod === 'custom_upi') {
            var screenshot = $('#upi_screenshot').val();
            if (!screenshot) {
                // Scroll to payment box and highlight it in red
                $('html, body').animate({ scrollTop: $('.cppm-upi-payment-box').offset().top - 100 }, 500);
                $('.cppm-upi-upload-wrap').css({'border-color': '#ef4444', 'background': '#fef2f2'});
                alert('Please upload your UPI payment screenshot to complete the order.');
                return false; // Stop the checkout process
            }
        }
        return true;
    });
});