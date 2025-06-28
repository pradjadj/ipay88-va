<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'class-ipay88-va-gateway.php';

class WC_Gateway_iPay88_VA_BRI extends WC_Gateway_iPay88_VA_Base {

    public $payment_id = 118;
    public $title = 'BRI Virtual Account';

    public function __construct() {
        parent::__construct();

        $this->id                 = 'ipay88_va_bri';
        $this->method_title       = __( 'iPay88 BRI VA', 'ipay88-va' );
        $this->method_description = __( 'Pay using iPay88 BRI Virtual Account.', 'ipay88-va' );

        $this->title              = $this->get_option( 'title', 'BRI Virtual Account' );
        $this->description        = $this->get_option( 'description', '' );

        $this->supports           = array(
            'products',
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->merchant_code = $this->get_option( 'merchant_code' );
        $this->merchant_key  = $this->get_option( 'merchant_key' );
        $this->api_url       = $this->get_option( 'api_url', 'https://payment.ipay88.co.id/ePayment/WebService/PaymentAPI/Checkout' );

        // Hooks
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __( 'Enable/Disable', 'ipay88-va' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable iPay88 BRI VA', 'ipay88-va' ),
                'default'     => 'no',
            ),
            'title' => array(
                'title'       => __( 'Title', 'ipay88-va' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'ipay88-va' ),
                'default'     => 'BRI Virtual Account',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'ipay88-va' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that the customer will see on your checkout.', 'ipay88-va' ),
                'default'     => 'Pay using BRI Virtual Account via iPay88.',
                'desc_tip'    => true,
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
}
?>
