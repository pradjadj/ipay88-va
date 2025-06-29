<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    return;
}

abstract class WC_iPay88_VA_Gateway extends WC_Payment_Gateway {

    protected $merchant_code;
    protected $merchant_key;
    protected $environment;
    protected $expiry_minutes;
    protected $status_after_payment;
    protected $debug_log;

    public function __construct() {
        $this->id                 = 'ipay88_va_' . strtolower( $this->get_bank_slug() );
        $this->has_fields         = false;
        $this->method_title       = $this->get_method_title();
        $this->method_description = $this->get_method_description();

        $this->supports = array(
            'products',
            'refunds',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'multiple_subscriptions',
            'pre-orders',
            'tokenization',
            'add_payment_method',
            'high_performance_order_storage',
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->merchant_code       = $this->get_option( 'merchant_code' );
        $this->merchant_key        = $this->get_option( 'merchant_key' );
        $this->environment         = $this->get_option( 'environment', 'sandbox' );
        $this->expiry_minutes      = intval( $this->get_option( 'expiry_minutes', 60 ) );
        $this->status_after_payment = $this->get_option( 'status_after_payment', 'processing' );
        $this->debug_log           = 'yes' === $this->get_option( 'debug_log', 'no' );

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // Hook for processing payment
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

        // Add VA number after place order on checkout page
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_payment_order' ), 10, 1 );

        // Add virtual account info on order received page
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_payment_info_order_received' ) );

        // Handle backend callback
        add_action( 'woocommerce_api_ipay88_va_callback', array( $this, 'handle_backend_callback' ) );

        // Add scripts for copy buttons
        add_action( 'wp_footer', array( $this, 'enqueue_copy_scripts' ) );
    }

    abstract protected function get_bank_slug();
    abstract protected function get_method_title();
    abstract protected function get_method_description();
    abstract protected function get_payment_id();

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable iPay88 VA ' . $this->get_method_title(),
                'default' => 'no',
            ),
            'merchant_code' => array(
                'title'       => 'Merchant Code',
                'type'        => 'text',
                'description' => 'Your iPay88 Merchant Code',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'merchant_key' => array(
                'title'       => 'Merchant Key',
                'type'        => 'password',
                'description' => 'Your iPay88 Merchant Key',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'environment' => array(
                'title'       => 'Environment',
                'type'        => 'select',
                'description' => 'Select Sandbox or Production',
                'default'     => 'sandbox',
                'desc_tip'    => true,
                'options'     => array(
                    'sandbox'    => 'Sandbox',
                    'production' => 'Production',
                ),
            ),
            'expiry_minutes' => array(
                'title'       => 'Waktu Kedaluwarsa (menit)',
                'type'        => 'number',
                'description' => 'Waktu kedaluwarsa pembayaran dalam menit',
                'default'     => 60,
                'desc_tip'    => true,
                'custom_attributes' => array(
                    'min' => 1,
                    'step' => 1,
                ),
            ),
            'status_after_payment' => array(
                'title'       => 'Status Setelah Pembayaran',
                'type'        => 'select',
                'description' => 'Status order setelah pembayaran sukses',
                'default'     => 'processing',
                'desc_tip'    => true,
                'options'     => array(
                    'processing' => 'Processing',
                    'completed'  => 'Completed',
                ),
            ),
            'debug_log' => array(
                'title'       => 'Debug Log',
                'type'        => 'checkbox',
                'label'       => 'Enable debug logging',
                'default'     => 'no',
                'desc_tip'    => true,
            ),
        );
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( 'Order not found', 'error' );
            return;
        }

        // Prepare request data
        $request_data = $this->prepare_request_data( $order );

        // Send payment request to iPay88
        $response = $this->send_payment_request( $request_data );

        if ( is_wp_error( $response ) ) {
            wc_add_notice( 'Payment error: ' . $response->get_error_message(), 'error' );
            $this->log( 'Payment request error: ' . $response->get_error_message() );
            return;
        }

        $body = wp_remote_retrieve_body( $response );
        $result = json_decode( $body, true );

        if ( empty( $result ) || ! isset( $result['Code'] ) || $result['Code'] !== '1' ) {
            wc_add_notice( 'Payment gateway error: ' . ( $result['Message'] ?? 'Unknown error' ), 'error' );
            $this->log( 'Payment gateway error: ' . print_r( $result, true ) );
            return;
        }

        // Save VA number and expiry date to order meta
        if ( ! empty( $result['VirtualAccountAssigned'] ) ) {
            update_post_meta( $order_id, '_ipay88_va_number', sanitize_text_field( $result['VirtualAccountAssigned'] ) );
        }
        if ( ! empty( $result['TransactionExpiryDate'] ) ) {
            update_post_meta( $order_id, '_ipay88_va_expiry', sanitize_text_field( $result['TransactionExpiryDate'] ) );
        }

        // Set order status to pending payment
        $order->update_status( 'pending', 'Waiting for iPay88 VA payment.' );

        // Reduce stock
        wc_reduce_stock_levels( $order_id );

        // Remove cart
        WC()->cart->empty_cart();

        // Return success and redirect to order received page
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }

    protected function prepare_request_data( $order ) {
        $site_title = get_bloginfo( 'name' );
        $billing_email = $order->get_billing_email();
        $billing_phone = $order->get_billing_phone();

        $amount = $order->get_total();
        $amount_formatted = number_format( $amount, 2, '.', '' );

        $ref_no = date( 'Ymd' ) . '-' . $order->get_id();

        $items = array();
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $items[] = array(
                'Id'         => $item_id,
                'Name'       => $item->get_name(),
                'Quantity'   => $item->get_quantity(),
                'Amount'     => number_format( $item->get_total(), 2, '.', '' ),
                'ParentType' => 'SELLER',
                'ParentId'   => 'SELLER88',
            );
        }

        $billing_address = array(
            'FirstName'   => $order->get_billing_first_name(),
            'LastName'    => $order->get_billing_last_name(),
            'Address'     => $order->get_billing_address_1(),
            'City'        => $order->get_billing_city(),
            'State'       => $order->get_billing_state(),
            'PostalCode'  => $order->get_billing_postcode(),
            'Phone'       => $billing_phone,
            'CountryCode' => $order->get_billing_country(),
        );

        $sellers = array(
            array(
                'Id'             => 'SELLER88',
                'Name'           => $site_title,
                'SellerIdNumber' => '',
                'Email'          => $billing_email,
                'Url'            => home_url(),
                'Address'        => array(
                    'FirstName'  => $order->get_billing_first_name(),
                    'LastName'   => $order->get_billing_last_name(),
                    'Address'    => $order->get_billing_address_1(),
                    'City'       => $order->get_billing_city(),
                    'State'      => $order->get_billing_state(),
                    'PostalCode' => $order->get_billing_postcode(),
                    'Phone'      => $billing_phone,
                    'CountryCode'=> $order->get_billing_country(),
                ),
            ),
        );

        $request = array(
            'ApiVersion'       => '2.0',
            'MerchantCode'     => $this->merchant_code,
            'PaymentId'        => $this->get_payment_id(),
            'Currency'         => 'IDR',
            'RefNo'            => $ref_no,
            'Amount'           => $amount_formatted,
            'ProdDesc'         => 'Order #' . $order->get_id(),
            'UserName'         => $site_title,
            'UserEmail'        => $billing_email,
            'UserContact'      => $billing_phone,
            'Remark'           => '',
            'Lang'             => 'iso-8859-1',
            'RequestType'      => 'SEAMLESS',
            'ResponseURL'      => esc_url_raw( home_url( '/?wc-api=ipay88_va_callback' ) ),
            'BackendURL'       => esc_url_raw( home_url( '/?wc-api=ipay88_va_callback' ) ),
            'ItemTransactions' => $items,
            'BillingAddress'   => $billing_address,
            'Sellers'          => $sellers,
            'Signature'        => $this->generate_signature( $ref_no, $amount_formatted ),
        );

        return $request;
    }

    protected function generate_signature( $ref_no, $amount ) {
        $string = '||' . $this->merchant_key . '||' . $this->merchant_code . '||' . $ref_no . '||' . $amount . '||IDR||';
        return hash( 'sha256', $string );
    }

    protected function send_payment_request( $request_data ) {
        $url = ( 'production' === $this->environment ) ?
            'https://payment.ipay88.co.id/ePayment/WebService/PaymentAPI/Checkout' :
            'https://sandbox.ipay88.co.id/ePayment/WebService/PaymentAPI/Checkout';

        $args = array(
            'body'        => wp_json_encode( $request_data ),
            'headers'     => array(
                'Content-Type' => 'application/json',
            ),
            'timeout'     => 30,
            'data_format' => 'body',
        );

        return wp_remote_post( $url, $args );
    }

    public function thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        $va_number = get_post_meta( $order_id, '_ipay88_va_number', true );
        $expiry    = get_post_meta( $order_id, '_ipay88_va_expiry', true );

        if ( $va_number ) {
            echo '<h2>Informasi Detail Pembayaran</h2>';
            echo '<p><strong>Nomor Virtual Account:</strong> <span id="ipay88-va-number">' . esc_html( $va_number ) . '</span> <button class="copy-btn" data-copy-target="ipay88-va-number">Copy Nomor VA</button></p>';
            echo '<p><strong>Nominal Pembayaran:</strong> <span id="ipay88-va-amount">' . esc_html( wc_price( $order->get_total() ) ) . '</span> <button class="copy-btn" data-copy-target="ipay88-va-amount">Copy Nominal</button></p>';
            if ( $expiry ) {
                echo '<p><strong>Batas Pembayaran:</strong> ' . esc_html( $expiry ) . '</p>';
            }
        }
    }

    public function display_payment_info_order_received( $order ) {
        if ( ! is_a( $order, 'WC_Order' ) ) {
            return;
        }
        $order_id = $order->get_id();
        $va_number = get_post_meta( $order_id, '_ipay88_va_number', true );
        $expiry    = get_post_meta( $order_id, '_ipay88_va_expiry', true );

        if ( $va_number ) {
            echo '<h2>Informasi Detail Pembayaran</h2>';
            echo '<p><strong>Nomor Virtual Account:</strong> <span id="ipay88-va-number">' . esc_html( $va_number ) . '</span> <button class="copy-btn" data-copy-target="ipay88-va-number">Copy Nomor VA</button></p>';
            echo '<p><strong>Nominal Pembayaran:</strong> <span id="ipay88-va-amount">' . esc_html( wc_price( $order->get_total() ) ) . '</span> <button class="copy-btn" data-copy-target="ipay88-va-amount">Copy Nominal</button></p>';
            if ( $expiry ) {
                echo '<p><strong>Batas Pembayaran:</strong> ' . esc_html( $expiry ) . '</p>';
            }
        }
    }

    public function enqueue_copy_scripts() {
        if ( ! is_order_received_page() && ! is_checkout() ) {
            return;
        }
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.copy-btn');
            buttons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-copy-target');
                    const text = document.getElementById(targetId).textContent;
                    navigator.clipboard.writeText(text).then(function() {
                        alert('Copied: ' + text);
                    });
                });
            });
        });
        </script>
        <?php
    }

    public function handle_backend_callback() {
        $input = file_get_contents( 'php://input' );
        $data = json_decode( $input, true );

        if ( empty( $data ) ) {
            wp_send_json( array( 'Code' => '0', 'Message' => 'Invalid data' ) );
            exit;
        }

        $merchant_code = sanitize_text_field( $data['MerchantCode'] ?? '' );
        $payment_id    = sanitize_text_field( $data['PaymentId'] ?? '' );
        $ref_no        = sanitize_text_field( $data['RefNo'] ?? '' );
        $amount        = sanitize_text_field( $data['Amount'] ?? '' );
        $currency      = sanitize_text_field( $data['Currency'] ?? '' );
        $transaction_status = sanitize_text_field( $data['TransactionStatus'] ?? '' );
        $signature     = sanitize_text_field( $data['Signature'] ?? '' );

        // Verify signature
        $expected_signature = hash( 'sha256', '||' . $this->merchant_key . '||' . $merchant_code . '||' . $payment_id . '||' . $ref_no . '||' . $amount . '||' . $currency . '||' . $transaction_status . '||' );

        if ( $signature !== $expected_signature ) {
            $this->log( 'Invalid signature in backend callback' );
            wp_send_json( array( 'Code' => '0', 'Message' => 'Invalid signature' ) );
            exit;
        }

        // Extract order ID from RefNo (format YYYYMMDD-order_id)
        $order_id = intval( substr( $ref_no, 9 ) );
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            $this->log( 'Order not found for RefNo: ' . $ref_no );
            wp_send_json( array( 'Code' => '0', 'Message' => 'Order not found' ) );
            exit;
        }

        // Update order status based on transaction status
        if ( $transaction_status === '1' ) {
            $order->update_status( $this->status_after_payment, 'Payment received via iPay88 VA.' );
        } elseif ( $transaction_status === '6' ) {
            $order->update_status( 'pending', 'Payment pending via iPay88 VA.' );
        } else {
            $order->update_status( 'failed', 'Payment failed via iPay88 VA.' );
        }

        // Save VA number and payment date
        if ( ! empty( $data['VirtualAccountAssigned'] ) ) {
            update_post_meta( $order_id, '_ipay88_va_number', sanitize_text_field( $data['VirtualAccountAssigned'] ) );
        }
        if ( ! empty( $data['PaymentDate'] ) ) {
            update_post_meta( $order_id, '_ipay88_va_payment_date', sanitize_text_field( $data['PaymentDate'] ) );
        }

        $this->log( 'Backend callback processed for order ' . $order_id . ' with status ' . $transaction_status );

        wp_send_json( array(
            'Code'    => '1',
            'Message' => array(
                'English'   => 'Status Received',
                'Indonesian'=> 'Pembayaran diterima',
            ),
        ) );
        exit;
    }

    protected function log( $message ) {
        if ( $this->debug_log ) {
            if ( function_exists( 'wc_get_logger' ) ) {
                $logger = wc_get_logger();
                $logger->info( '[iPay88 VA] ' . $message, array( 'source' => 'ipay88-va' ) );
            } else {
                error_log( '[iPay88 VA] ' . $message );
            }
        }
    }
}
?>
