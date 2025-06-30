jQuery(document).ready(function($) {
    // After place order, WooCommerce triggers updated_checkout event
    $('form.checkout').on('checkout_place_order_success', function(event, orderId) {
        fetchPaymentInfo(orderId);
    });

    // WooCommerce 3.0+ triggers updated_checkout event on checkout update
    $(document.body).on('updated_checkout', function() {
        var orderId = $('input[name="order_id"]').val();
        if (orderId) {
            fetchPaymentInfo(orderId);
        }
    });

    function fetchPaymentInfo(orderId) {
        if (!orderId) return;

        $.ajax({
            url: ipay88_va_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'ipay88_va_get_payment_info',
                nonce: ipay88_va_params.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var html = '<div class="ipay88-va-payment-info">';
                    html += '<h3>Informasi Detail Pembayaran</h3>';
                    html += '<p><strong>Nama Bank:</strong> ' + data.bank_name + '</p>';
                    html += '<p><strong>Nomor Virtual Account:</strong> <span id="ipay88-va-number">' + data.va_number + '</span> <button class="copy-btn" data-copy-target="ipay88-va-number">Copy Nomor VA</button></p>';
                    html += '<p><strong>Batas Pembayaran:</strong> ' + data.expiry + ' WIB</p>';
                    html += '</div>';

                    if ($('#ipay88-va-payment-info').length) {
                        $('#ipay88-va-payment-info').html(html);
                    } else {
                        $('form.checkout').append('<div id="ipay88-va-payment-info">' + html + '</div>');
                    }

                    // Bind copy button click
                    $('.copy-btn').off('click').on('click', function() {
                        var targetId = $(this).data('copy-target');
                        var text = $('#' + targetId).text();
                        navigator.clipboard.writeText(text).then(function() {
                            alert('Copied: ' + text);
                        });
                    });
                }
            }
        });
    }
});
