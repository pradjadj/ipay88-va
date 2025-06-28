<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    return;
}

abstract class WC_Gateway_iPay88_VA_Base extends WC_Payment_Gateway {

    protected $merchant_code;
    protected $merchant_key;
    protected $api_url;
    protected $payment_id;

    public function __construct() {
        $this->id                 = 'ipay88_va_' . strtolower( $this->payment_id );
        $this->has_fields         = false;
        $this->method_title       = 'iPay88 VA ' . $this->title;
        $this->method_description = 'iPay88 Virtual Account Payment Gateway for ' . $this->title;

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Load centralized settings
        $settings = iPay88_VA_Settings::get_settings();
        $this->merchant_code = $settings['merchant_code'] ?? '';
        $this->merchant_key  = $settings['merchant_key'] ?? '';
        $this->expiry_time   = $settings['expiry_time'] ?? 60;
        $this->order_status  = $settings['order_status'] ?? 'wc-processing';
        $this->environment   = $settings['environment'] ?? 'production';
        $this->debug_log     = $settings['debug_log'] ?? 'no';

        // Set API URL based on environment
        if ( $this->environment === 'sandbox' ) {
            $this->api_url = 'https://sandbox.ipay88.co.id/ePayment/WebService/PaymentAPI/Checkout';
        } else {
            $this->api_url = 'https://payment.ipay88.co.id/ePayment/WebService/PaymentAPI/Checkout';
        }

        // Hooks
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
        add_action( 'woocommerce_api_ipay88_va_callback', array( $this, 'handle_callback' ) );

        // Add scripts for checkout page
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // AJAX handler to get VA after place order
        add_action( 'wp_ajax_ipay88_va_get_va', array( $this, 'ajax_get_va' ) );
        add_action( 'wp_ajax_nopriv_ipay88_va_get_va', array( $this, 'ajax_get_va' ) );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __( 'Enable/Disable', 'ipay88-va' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable iPay88 VA ' . $this->title, 'ipay88-va' ),
                'default'     => 'no',
            ),
            'merchant_code' => array(
                'title'       => __( 'Merchant Code', 'ipay88-va' ),
                'type'        => 'text',
                'description' => __( 'Your iPay88 Merchant Code', 'ipay88-va' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'merchant_key' => array(
                'title'       => __( 'Merchant Key', 'ipay88-va' ),
                'type'        => 'password',
                'description' => __( 'Your iPay88 Merchant Key', 'ipay88-va' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'api_url' => array(
                'title'       => __( 'API URL', 'ipay88-va' ),
                'type'        => 'text',
                'description' => __( 'iPay88 API URL (use sandbox or production)', 'ipay88-va' ),
                'default'     => 'https://payment.ipay88.co.id/ePayment/WebService/PaymentAPI/Checkout',
                'desc_tip'    => true,
            ),
        );
    }

    public function enqueue_scripts() {
        if ( is_checkout() && $this->is_available() ) {
            wp_enqueue_script( 'ipay88-va-checkout', plugin_dir_url( __FILE__ ) . 'js/ipay88-va-checkout.js', array( 'jquery' ), '1.0', true );
            wp_localize_script( 'ipay88-va-checkout', 'ipay88_va_params', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'payment_id' => $this->payment_id,
                'nonce' => wp_create_nonce( 'ipay88_va_nonce' ),
            ) );
        }
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        // Prepare request data
        $amount = number_format( $order->get_total(), 2, '.', '' );
        $refno = date( 'Ymd' ) . '-' . $order_id;
        $currency = get_woocommerce_currency();

        $request_data = array(
            'APIVersion' => '2.0',
            'MerchantCode' => $this->merchant_code,
            'PaymentId' => (string) $this->payment_id,
            'Currency' => $currency,
            'RefNo' => $refno,
            'Amount' => $amount,
            'ProdDesc' => $this->get_product_description( $order ),
            'UserName' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'UserEmail' => $order->get_billing_email(),
            'UserContact' => $order->get_billing_phone(),
            'Remark' => '',
            'Lang' => 'iso-8859-1',
            'RequestType' => 'SEAMLESS',
            'ResponseURL' => home_url( '/?wc-api=ipay88_va_callback' ),
            'BackendURL' => home_url( '/?wc-api=ipay88_va_callback' ),
        );

        $request_data['Signature'] = $this->generate_signature( $request_data );

        // Send request to iPay88 API
        $response = $this->send_request( $request_data );

        if ( is_wp_error( $response ) ) {
            wc_add_notice( __( 'Payment error: ', 'ipay88-va' ) . $response->get_error_message(), 'error' );
            $this->log( 'Payment request error: ' . $response->get_error_message() );
            return;
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $response_body ) || ! isset( $response_body['Code'] ) || $response_body['Code'] !== '1' ) {
            $message = isset( $response_body['Message'] ) ? $response_body['Message'] : __( 'Unknown error', 'ipay88-va' );
            wc_add_notice( __( 'Payment error: ', 'ipay88-va' ) . $message, 'error' );
            $this->log( 'Payment request failed: ' . $message );
            return;
        }

        // Save VA number and other info in order meta
        if ( ! empty( $response_body['VirtualAccountAssigned'] ) ) {
            update_post_meta( $order_id, '_ipay88_va_number', sanitize_text_field( $response_body['VirtualAccountAssigned'] ) );
            update_post_meta( $order_id, '_ipay88_va_expiry', sanitize_text_field( $response_body['TransactionExpiryDate'] ) );
            update_post_meta( $order_id, '_ipay88_checkout_id', sanitize_text_field( $response_body['CheckoutID'] ) );
        }

        // Reduce stock levels
        wc_reduce_stock_levels( $order_id );

        // Remove cart
        WC()->cart->empty_cart();

        // Return success and redirect to order received page
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }

    protected function get_product_description( $order ) {
        $items = $order->get_items();
        $descriptions = array();
        foreach ( $items as $item ) {
            $product = $item->get_product();
            if ( $product ) {
                $descriptions[] = $product->get_name();
            }
        }
        return implode( ', ', $descriptions );
    }

    protected function generate_signature( $data ) {
        $string = '||' . $this->merchant_key . '||' . $data['MerchantCode'] . '||' . $data['RefNo'] . '||' . $data['Amount'] . '||' . $data['Currency'] . '||';
        return hash( 'sha256', $string );
    }

    protected function send_request( $data ) {
        $args = array(
            'body'        => json_encode( $data ),
            'headers'     => array(
                'Content-Type' => 'application/json',
            ),
            'timeout'     => 30,
            'data_format' => 'body',
        );

        $response = wp_remote_post( $this->api_url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return $response;
    }

    public function thankyou_page( $order_id ) {
        $order = wc_get_order( $order_id );
        $va_number = get_post_meta( $order_id, '_ipay88_va_number', true );
        $expiry = get_post_meta( $order_id, '_ipay88_va_expiry', true );

        if ( $va_number ) {
            echo '<h2>' . __( 'Virtual Account Number', 'ipay88-va' ) . '</h2>';
            echo '<p><strong>' . esc_html( $va_number ) . '</strong></p>';
            if ( $expiry ) {
                echo '<p>' . sprintf( __( 'Please complete your payment before %s.', 'ipay88-va' ), esc_html( $expiry ) ) . '</p>';
            }
        }
    }

    public function handle_callback() {
        $input = file_get_contents( 'php://input' );
        $data = json_decode( $input, true );

        if ( empty( $data ) ) {
            wp_send_json( array( 'Code' => '0', 'Message' => 'Invalid request' ) );
            exit;
        }

        $required_fields = array( 'MerchantCode', 'PaymentId', 'RefNo', 'Amount', 'Currency', 'TransactionStatus', 'Signature' );
        foreach ( $required_fields as $field ) {
            if ( ! isset( $data[ $field ] ) ) {
                wp_send_json( array( 'Code' => '0', 'Message' => 'Missing field: ' . $field ) );
                exit;
            }
        }

        // Verify signature
        $signature_string = '||' . $this->merchant_key . '||' . $data['MerchantCode'] . '||' . $data['PaymentId'] . '||' . $data['RefNo'] . '||' . $data['Amount'] . '||' . $data['Currency'] . '||' . $data['TransactionStatus'] . '||';
        $calculated_signature = hash( 'sha256', $signature_string );

        if ( $calculated_signature !== $data['Signature'] ) {
            $this->log( 'Invalid signature in callback for RefNo: ' . $data['RefNo'] );
            wp_send_json( array( 'Code' => '0', 'Message' => 'Invalid signature' ) );
            exit;
        }

        // Extract order ID from RefNo
        $refno = $data['RefNo'];
        $order_id = (int) substr( $refno, 9 ); // YYYYMMDD- is 9 chars

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            $this->log( 'Order not found for RefNo: ' . $refno );
            wp_send_json( array( 'Code' => '0', 'Message' => 'Order not found' ) );
            exit;
        }

        // Update order status based on TransactionStatus
        if ( $data['TransactionStatus'] === '1' ) {
            $order->payment_complete( $data['TransId'] ?? '' );
            $order->add_order_note( __( 'Payment successful via iPay88 VA.', 'ipay88-va' ) );
        } elseif ( $data['TransactionStatus'] === '0' ) {
            $order->update_status( 'failed', __( 'Payment failed via iPay88 VA.', 'ipay88-va' ) );
        } elseif ( $data['TransactionStatus'] === '6' ) {
            $order->update_status( 'on-hold', __( 'Payment pending via iPay88 VA.', 'ipay88-va' ) );
        }

        wp_send_json( array( 'Code' => '1', 'Message' => 'Status Received' ) );
        exit;
    }

    public function log( $message ) {
        if ( class_exists( 'WC_Logger' ) ) {
            $logger = wc_get_logger();
            $logger->info( $message, array( 'source' => 'ipay88-va' ) );
        }
    }

    public function ajax_get_va() {
        check_ajax_referer( 'ipay88_va_nonce', 'security' );

        $payment_id = isset( $_POST['payment_id'] ) ? sanitize_text_field( $_POST['payment_id'] ) : '';
        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'ipay88-va' ) ) );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( array( 'message' => __( 'Order not found.', 'ipay88-va' ) ) );
        }

        // Only process if payment method matches this gateway
        if ( 'ipay88_va_' . strtolower( $this->payment_id ) !== $payment_id ) {
            wp_send_json_error( array( 'message' => __( 'Payment method mismatch.', 'ipay88-va' ) ) );
        }

        // Prepare request data
        $amount = number_format( $order->get_total(), 2, '.', '' );
        $refno = date( 'Ymd' ) . '-' . $order_id;
        $currency = get_woocommerce_currency();

        $request_data = array(
            'APIVersion' => '2.0',
            'MerchantCode' => $this->merchant_code,
            'PaymentId' => (string) $this->payment_id,
            'Currency' => $currency,
            'RefNo' => $refno,
            'Amount' => $amount,
            'ProdDesc' => $this->get_product_description( $order ),
            'UserName' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'UserEmail' => $order->get_billing_email(),
            'UserContact' => $order->get_billing_phone(),
            'Remark' => '',
            'Lang' => 'iso-8859-1',
            'RequestType' => 'SEAMLESS',
            'ResponseURL' => home_url( '/?wc-api=ipay88_va_callback' ),
            'BackendURL' => home_url( '/?wc-api=ipay88_va_callback' ),
        );

        $request_data['Signature'] = $this->generate_signature( $request_data );

        // Send request to iPay88 API
        $response = $this->send_request( $request_data );

        if ( is_wp_error( $response ) ) {
            $this->log( 'AJAX payment request error: ' . $response->get_error_message() );
            wp_send_json_error( array( 'message' => __( 'Payment request error.', 'ipay88-va' ) ) );
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $response_body ) || ! isset( $response_body['Code'] ) || $response_body['Code'] !== '1' ) {
            $message = isset( $response_body['Message'] ) ? $response_body['Message'] : __( 'Unknown error', 'ipay88-va' );
            $this->log( 'AJAX payment request failed: ' . $message );
            wp_send_json_error( array( 'message' => $message ) );
        }

        // Save VA number and other info in order meta
        if ( ! empty( $response_body['VirtualAccountAssigned'] ) ) {
            update_post_meta( $order_id, '_ipay88_va_number', sanitize_text_field( $response_body['VirtualAccountAssigned'] ) );
            update_post_meta( $order_id, '_ipay88_va_expiry', sanitize_text_field( $response_body['TransactionExpiryDate'] ) );
            update_post_meta( $order_id, '_ipay88_checkout_id', sanitize_text_field( $response_body['CheckoutID'] ) );
        }

        wp_send_json_success( array(
            'va_number' => $response_body['VirtualAccountAssigned'],
            'expiry' => $response_body['TransactionExpiryDate'],
        ) );
    }
}
?>
