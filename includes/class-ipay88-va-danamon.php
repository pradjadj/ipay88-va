<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'class-ipay88-va-gateway.php';

class WC_Gateway_iPay88_VA_Danamon extends WC_Gateway_iPay88_VA_Base {

    public $payment_id = 111;
    public $title = 'Danamon Virtual Account';

    public function __construct() {
        parent::__construct();
        
        $this->id                 = 'ipay88_va_danamon';
        $this->method_title       = __( 'iPay88 Danamon VA', 'ipay88-va' );
        $this->method_description = __( 'Pay using iPay88 Danamon Virtual Account.', 'ipay88-va' );

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title', $this->title);
        $this->description = $this->get_option('description', 'Pay using Danamon Virtual Account via iPay88.');

        $this->supports = array(
            'products',
        );


        $settings = iPay88_VA_Settings::get_settings();
        $this->merchant_code = $settings['merchant_code'] ?? '';
        $this->merchant_key  = $settings['merchant_key'] ?? '';
        if ( isset( $settings['environment'] ) && $settings['environment'] === 'sandbox' ) {
            $this->api_url = 'https://sandbox.ipay88.co.id/ePayment/WebService/PaymentAPI/Checkout';
        } else {
            $this->api_url = 'https://payment.ipay88.co.id/ePayment/WebService/PaymentAPI/Checkout';
        }

        // Hooks
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __( 'Enable/Disable', 'ipay88-va' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable iPay88 Danamon VA', 'ipay88-va' ),
                'default'     => 'no',
            ),
            'title' => array(
                'title'       => __( 'Title', 'ipay88-va' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'ipay88-va' ),
                'default'     => 'Danamon Virtual Account',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'ipay88-va' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that the customer will see on your checkout.', 'ipay88-va' ),
                'default'     => 'Pay using Danamon Virtual Account via iPay88.',
                'desc_tip'    => true,
            ),
        );
    }
}
?>
