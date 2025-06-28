jQuery(function($){
    $('form.checkout').on('checkout_place_order', function(){
        var payment_method = $('input[name="payment_method"]:checked').val();
        if ( payment_method && payment_method.indexOf('ipay88_va_') === 0 ) {
            // Show loading
            $('.woocommerce-checkout-payment').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            var data = {
                action: 'ipay88_va_get_va',
                security: ipay88_va_params.nonce,
                payment_id: ipay88_va_params.payment_id,
                order_id: $('input[name="order_id"]').val() || 0
            };

            $.post(ipay88_va_params.ajax_url, data, function(response){
                $('.woocommerce-checkout-payment').unblock();
                if ( response.success ) {
                    // Display VA number
                    if ( response.data.va_number ) {
                        if ( $('#ipay88-va-number').length === 0 ) {
                            $('<div id="ipay88-va-number" class="woocommerce-message" style="margin-top:20px;"></div>').insertAfter('.woocommerce-checkout-payment');
                        }
                        $('#ipay88-va-number').html('<strong>Virtual Account Number:</strong> ' + response.data.va_number + '<br><small>Please complete your payment before ' + response.data.expiry + '</small>');
                    }
                } else {
                    if ( response.data && response.data.message ) {
                        alert( response.data.message );
                    } else {
                        alert( 'Failed to get Virtual Account number.' );
                    }
                }
            });

            // Prevent default redirect
            return false;
        }
        return true;
    });
});
