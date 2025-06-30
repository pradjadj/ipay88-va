<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    return;
}

abstract class WC_Gateway_iPay88_VA extends WC_Payment_Gateway {

    protected $merchant_code;
    protected $merchant_key;
    protected $expiry_time;
    protected $post_payment_status;
    protected $environment;
    protected $debug_log;
    protected $logger;

    public function __construct() {
        $this->id                 = 'ipay88_va_' . $this->get_bank_id();
        $this->method_title       = 'iPay88 VA - ' . $this->get_bank_name();
        $this->has_fields         = false;
        $this->supports           = array( 'products' );

        $this->init_form_fields();
        $this->init_settings();

        $this->merchant_code      = get_option( 'ipay88_va_merchant_code' );
        $this->merchant_key       = get_option( 'ipay88_va_merchant_key' );
        $this->expiry_time        = get_option( 'ipay88_va_expiry_time', 60 );
        $this->post_payment_status= get_option( 'ipay88_va_post_payment_status', 'processing' );
        $this->environment        = get_option( 'ipay88_va_environment', 'sandbox' );
        $this->debug_log          = get_option( 'ipay88_va_debug_log', 'no' );

        $this->logger             = wc_get_logger();

        // Use saved title and description or fallback to defaults
        $this->title              = $this->get_option( 'title', $this->get_bank_name() );
        $this->description        = $this->get_option( 'description', 'Pay using iPay88 Virtual Account - ' . $this->get_bank_name() );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

        // Add webhook endpoint for backendpost
        add_action( 'woocommerce_api_webhook_' . $this->id, array( $this, 'handle_webhook' ) );
    }

    abstract protected function get_bank_id();
    abstract protected function get_bank_name();
    abstract protected function get_payment_id();

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'ipay88-va' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable this payment gateway', 'ipay88-va' ),
                'default' => 'yes',
            ),
            'title' => array(
                'title'       => __( 'Title', 'ipay88-va' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'ipay88-va' ),
                'default'     => $this->get_bank_name(),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'ipay88-va' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'ipay88-va' ),
                'default'     => 'Pay using iPay88 Virtual Account - ' . $this->get_bank_name(),
                'desc_tip'    => true,
            ),
        );
    }

    public function admin_options() {
        ?>
        <h2><?php echo esc_html( $this->get_bank_name() . ' iPay88 VA' ); ?></h2>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        <?php
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        // Check if VA number already exists to avoid duplicate RefNo
        $existing_va = get_post_meta( $order_id, '_ipay88_va_number', true );
        if ( $existing_va ) {
            // VA number exists, skip new payment request
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        }

        // Prepare request data for iPay88 API
        $request_data = $this->prepare_request_data( $order );

        // Send request to iPay88 API and get response
$response = $this->send_payment_request( $request_data );

if ( $response && isset( $response['Code'] ) && $response['Code'] === '1' ) {
            // Log full response JSON for debugging
            if ( $this->debug_log === 'yes' ) {
                $this->logger->info( 'iPay88 VA API full response: ' . wp_json_encode( $response ), array( 'source' => 'ipay88_va' ) );
            }
            // Save VA number in order meta
            if ( ! empty( $response['VirtualAccountAssigned'] ) ) {
                update_post_meta( $order_id, '_ipay88_va_number', sanitize_text_field( $response['VirtualAccountAssigned'] ) );
            }
            // Calculate expiry time based on setting and save in order meta
            $expiry_minutes = intval( $this->expiry_time );
            $expiry_timestamp = current_time( 'timestamp' ) + ( $expiry_minutes * 60 );
            $expiry_datetime = date( 'Y-m-d H:i:s', $expiry_timestamp );
            update_post_meta( $order_id, '_ipay88_va_expiry', $expiry_datetime );

            // Set order status to pending payment
            $order->update_status( 'pending', __( 'Waiting for iPay88 VA payment.', 'ipay88-va' ) );

            // Reduce stock levels
            wc_reduce_stock_levels( $order_id );

            // Remove cart
            WC()->cart->empty_cart();

            // Return success and redirect to order received page
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        } else {
            wc_add_notice( __( 'Payment error: Unable to get Virtual Account number from iPay88.', 'ipay88-va' ), 'error' );
            return;
        }
    }

    protected function prepare_request_data( $order ) {
        $amount = number_format($order->get_total(), 0, '', '');
        $refno = date( 'Ymd' ) . '-' . $order->get_id();
        $currency = 'IDR';
        $user_name = get_bloginfo( 'name' ); // Use site title as UserName
        $user_email = $order->get_billing_email();
        $user_contact = $order->get_billing_phone();

        $items = array();
        $product_names = array();
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $product_names[] = $item->get_name();
            $items[] = array(
                'Id'       => strval($item_id),
                'Name'     => $item->get_name(),
                'Quantity' => intval($item->get_quantity()),
                'Amount'   => intval($item->get_product()->get_price()),
                'ParentType' => 'ITEM',
                'ParentId' => 'ITEM' . $order->get_id(),
            );
        }

        $prod_desc = implode( ', ', $product_names );

        $signature_string = '||' . $this->merchant_key . '||' . $this->merchant_code . '||' . $refno . '||' . $amount . '||' . $currency . '||';
        $signature = hash( 'sha256', $signature_string );

        if ( $this->debug_log === 'yes' ) {
            $this->logger->info( 'iPay88 VA Signature String: ' . $signature_string, array( 'source' => 'ipay88_va' ) );
            $this->logger->info( 'iPay88 VA Signature: ' . $signature, array( 'source' => 'ipay88_va' ) );
            $this->logger->info( 'iPay88 VA Request Amount: ' . $amount, array( 'source' => 'ipay88_va' ) );
        }

        $request = array(
            'APIVersion'    => '2.0',
            'MerchantCode'  => $this->merchant_code,
            'PaymentId'     => $this->get_payment_id(),
            'Currency'      => $currency,
            'RefNo'         => $refno,
            'Amount'        => $amount,
            'ProdDesc'      => $prod_desc,
            'UserName'      => $user_name,
            'UserEmail'     => $user_email,
            'UserContact'   => $user_contact,
            'Remark'        => '',
            'Lang'          => 'UTF-8',
            'RequestType'   => 'SEAMLESS',
            'ResponseURL'   => esc_url_raw( add_query_arg( 'wc-api', 'webhook_' . $this->id, home_url( '/' ) ) ),
            'BackendURL'    => esc_url_raw( add_query_arg( 'wc-api', 'webhook_' . $this->id, home_url( '/' ) ) ),
            'Signature'     => $signature,
            //'ItemTransactions' => $items,
        );

        return $request;
    }

    protected function send_payment_request( $request_data ) {
        $url = ( $this->environment === 'production' ) ? 'https://payment.ipay88.co.id/ePayment/WebService/PaymentAPI/Checkout' : 'https://sandbox.ipay88.co.id/ePayment/WebService/PaymentAPI/Checkout';

        $args = array(
            'body'        => wp_json_encode( $request_data ),
            'headers'     => array(
                'Content-Type' => 'application/json',
            ),
            'timeout'     => 60,
            'data_format' => 'body',
        );

        $response = wp_remote_post( $url, $args );

        if ( $this->debug_log === 'yes' ) {
            $this->logger->info( 'iPay88 VA API request: ' . wp_json_encode( $request_data ), array( 'source' => 'ipay88_va' ) );
        }

        if ( is_wp_error( $response ) ) {
            if ( $this->debug_log === 'yes' ) {
                $this->logger->error( 'iPay88 VA API request error: ' . $response->get_error_message(), array( 'source' => 'ipay88_va' ) );
            }
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $result = json_decode( $body, true );

        if ( $this->debug_log === 'yes' ) {
            $this->logger->info( 'iPay88 VA API response: ' . print_r( $result, true ), array( 'source' => 'ipay88_va' ) );
            $this->logger->info( 'iPay88 VA API response format: ' . json_encode(array_keys($result)), array( 'source' => 'ipay88_va' ) );
        }

        return $result;
    }

    public function thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );
        $va_number = get_post_meta( $order_id, '_ipay88_va_number', true );
        $expiry = get_post_meta( $order_id, '_ipay88_va_expiry', true );
        $expiry_timestamp = strtotime( $expiry );
        $current_time = time();

        if ( $order->has_status( 'completed' ) ) {
            echo '<div class="ipay88-va-payment-status success">';
            echo '<h3>Pesanan Selesai!</h3>';
            echo '<p>Terima kasih, pesanan anda telah selesai diproses.</p>';
            echo '</div>';
        } elseif ( $order->has_status( 'processing' ) ) {
            echo '<div class="ipay88-va-payment-status success">';
            echo '<h3>Pembayaran Diterima!</h3>';
            echo '<p>Pembayaran Anda telah diterima dan sedang diproses. Terima kasih atas pesanan Anda.</p>';
            echo '</div>';
        } elseif ( $order->has_status( 'cancelled' ) ) {
            echo '<div class="ipay88-va-payment-status cancelled">';
            echo '<h3>Pesanan Gagal</h3>';
            echo '<p>Pesanan ini telah gagal / dibatalkan oleh sistem.</p>';
            echo '</div>';
        } elseif ( $expiry_timestamp < $current_time ) {
            echo '<div class="ipay88-va-payment-status cancelled">';
            echo '<h3>Pembayaran Kedaluwarsa</h3>';
            echo '<p>Waktu pembayaran telah habis. Silakan buat pesanan baru.</p>';
            echo '</div>';
        }

        if ( $va_number && $order->has_status( 'pending' ) ) {
            echo '<div class="ipay88-va-payment-info">';
            echo '<h2 style="text-align: center; font-weight: bold; margin-bottom: 20px;">' . esc_html__( 'Informasi Detail Pembayaran', 'ipay88-va' ) . '</h2>';
            echo '<p style="text-align: center; font-weight: bold; margin-bottom: 10px;">' . sprintf( esc_html__( 'Nomor Virtual Account %s:', 'ipay88-va' ), esc_html( $this->get_bank_name() ) ) . '</p>';
            echo '<div class="ipay88-va-number">' . esc_html( $va_number ) . '</div>';
            echo '<p style="text-align: center; font-weight: bold; margin-bottom: 10px;">' . esc_html__( 'Jumlah yang harus dibayar:', 'ipay88-va' ) . '</p>';
            echo '<div class="ipay88-va-amount">' . wp_kses_post( wc_price( $order->get_total() ) ) . '</div>';
            echo '<p style="text-align: center; margin-bottom: 20px;">' . sprintf( esc_html__( 'Bayar pesanan anda sebelum %s WIB', 'ipay88-va' ), esc_html( $expiry ) ) . '</p>';
            echo '<div class="ipay88-copy-buttons" style="text-align: center;">';
            echo '<button class="ipay88-copy-btn" data-copy-target="ipay88-va-number">' . esc_html__( 'COPY NOMOR VA', 'ipay88-va' ) . '</button>';
            echo '<button class="ipay88-copy-btn" data-copy-target="ipay88-va-amount">' . esc_html__( 'COPY NOMINAL', 'ipay88-va' ) . '</button>';
            echo '</div>';
            echo '</div>';
            echo "<script>
                document.addEventListener(\"DOMContentLoaded\", function() {
                    var buttons = document.querySelectorAll(\".ipay88-copy-btn\");
                    buttons.forEach(function(button) {
                        button.addEventListener(\"click\", function() {
                            var targetId = this.getAttribute(\"data-copy-target\");
                            var targetElement = document.querySelector(\".\" + targetId) || document.getElementById(targetId);
                            if (targetElement) {
                                var text = targetElement.innerText || targetElement.textContent;
                                if (targetId === 'ipay88-va-amount') {
                                    // Remove non-digit characters for nominal copy
                                    text = text.replace(/[^\\d]/g, '');
                                }
                                var btn = this;
                                navigator.clipboard.writeText(text).then(function() {
                                    var originalText = btn.innerText;
                                    btn.innerText = \"Berhasil Disalin\";
                                    setTimeout(function() {
                                        btn.innerText = originalText;
                                    }, 2000);
                                });
                            } else {
                                alert(\"Copy target not found.\");

                            }
                        });
                    });
                });
            </script>";
        }
    }

    public function receipt_page( $order_id ) {
        $this->thankyou_page( $order_id );
    }

    public function handle_webhook() {
        $posted = file_get_contents( 'php://input' );
        $data = json_decode( $posted, true );

        if ( ! $data ) {
            wp_send_json( array( 'Code' => '0', 'Message' => 'Invalid request' ) );
            exit;
        }

        $order_id = $this->extract_order_id_from_refno( $data['RefNo'] ?? '' );
        if ( ! $order_id ) {
            wp_send_json( array( 'Code' => '0', 'Message' => 'Order not found' ) );
            exit;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json( array( 'Code' => '0', 'Message' => 'Order not found' ) );
            exit;
        }

        // Verify signature
        $signature_string = '||' . $this->merchant_key . '||' . $this->merchant_code . '||' . $data['PaymentId'] . '||' . $data['RefNo'] . '||' . $data['Amount'] . '||' . $data['Currency'] . '||' . $data['TransactionStatus'] . '||';
        $signature = hash( 'sha256', $signature_string );

        if ( $signature !== ( $data['Signature'] ?? '' ) ) {
            wp_send_json( array( 'Code' => '0', 'Message' => 'Invalid signature' ) );
            exit;
        }

        // Update order status based on TransactionStatus
        switch ( $data['TransactionStatus'] ) {
            case '1': // Success
                $order->update_status( $this->post_payment_status, __( 'Payment received via iPay88 VA.', 'ipay88-va' ) );
                break;
            case '0': // Fail
                $order->update_status( 'failed', __( 'Payment failed via iPay88 VA.', 'ipay88-va' ) );
                break;
            case '6': // Pending
                $order->update_status( 'pending', __( 'Payment pending via iPay88 VA.', 'ipay88-va' ) );
                break;
            default:
                // Do nothing
                break;
        }

        // Save VA number and expiry date if available
        if ( ! empty( $data['VirtualAccountAssigned'] ) ) {
            update_post_meta( $order_id, '_ipay88_va_number', sanitize_text_field( $data['VirtualAccountAssigned'] ) );
        }
        if ( ! empty( $data['TransactionExpiryDate'] ) ) {
            update_post_meta( $order_id, '_ipay88_va_expiry', sanitize_text_field( $data['TransactionExpiryDate'] ) );
        }

        wp_send_json( array( 'Code' => '1', 'Message' => array( 'English' => 'Status Received', 'Indonesian' => 'Pembayaran diterima' ) ) );
        exit;
    }

    protected function extract_order_id_from_refno( $refno ) {
        $parts = explode( '-', $refno );
        if ( count( $parts ) === 2 ) {
            return intval( $parts[1] );
        }
        return 0;
    }
}

// Handle AJAX check order status
add_action('wp_ajax_check_order_status', 'handle_check_order_status');
add_action('wp_ajax_nopriv_check_order_status', 'handle_check_order_status');

function handle_check_order_status() {
    check_ajax_referer('check-order-status', 'security');
    
    if (!isset($_POST['order_id']) || !is_numeric($_POST['order_id'])) {
        wp_send_json_error('Invalid order ID');
    }
    
    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);
    
    if (!$order) {
        wp_send_json_error('Order not found');
    }
    
    wp_send_json_success(array(
        'status' => $order->get_status(),
        'order_id' => $order_id
    ));
}
?>
