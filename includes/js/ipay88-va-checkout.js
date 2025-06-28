jQuery(function($){
    $(document.body).on('click', '#place_order', function(e) {
        var payment_method = $('input[name="payment_method"]:checked').val();
        
        if (payment_method && payment_method.indexOf('ipay88_va_') === 0) {
            e.preventDefault();
            
            var $form = $('form.checkout');
            var $payment_area = $('.woocommerce-checkout-payment');
            
            // Show loading
            $payment_area.block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            // Get order ID from hidden input or data attribute
            var order_id = $form.find('input[name="order_id"]').val() || 
                          $form.data('order_id') || 
                          0;

            $.ajax({
                type: 'POST',
                url: ipay88_va_params.ajax_url,
                data: {
                    action: 'ipay88_va_get_va',
                    security: ipay88_va_params.nonce,
                    payment_id: payment_method.replace('ipay88_va_', ''),
                    order_id: order_id
                },
                success: function(response) {
                    $payment_area.unblock();

                    if (response.success && response.data.va_number) {
                        // Show VA number
                        var $va_display = $('#ipay88-va-number');
                        if ($va_display.length === 0) {
                            $va_display = $('<div id="ipay88-va-number" class="woocommerce-message"></div>');
                            $payment_area.after($va_display);
                        }
                        $va_display.html('<strong>Virtual Account Number:</strong> ' + 
                            response.data.va_number + '<br><small>Please complete your payment before ' + 
                            response.data.expiry + '</small>');
                            
                        // Re-enable place order button
                        $('#place_order').prop('disabled', false);
                    } else {
                        var errorMsg = response.data && response.data.message 
                            ? response.data.message 
                            : 'Failed to get Virtual Account number.';
                        alert(errorMsg);
                        $('#place_order').prop('disabled', false);
                    }
                },
                error: function() {
                    $payment_area.unblock();
                    alert('Connection error. Please try again.');
                    $('#place_order').prop('disabled', false);
                }
            });
        }

    });
});
