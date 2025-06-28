<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'class-ipay88-va-gateway.php';

class WC_Gateway_iPay88_VA_Permata extends WC_Gateway_iPay88_VA_Base {

    public $payment_id = 112;
    public $title = 'Permata Virtual Account';

    public function __construct() {
        parent::__construct();

        $this->id                 = 'ipay88_va_permata';
        $this->method_title       = __( 'iPay88 Permata VA', 'ipay88-va' );
        $this->method_description = __( 'Pay using iPay88 Permata Virtual Account.', 'ipay88-va' );

        $this->title              = $this->get_option( 'title', 'Permata Virtual Account' );
        $this->description        = $this->get_option( 'description', '' );

        $this->supports           = array(
            'products',
        );

        $this->init_form_fields();
        $this->init_settings();

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
            'title' => array(
                'title'       => __( 'Title', 'ipay88-va' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'ipay88-va' ),
                'default'     => 'Permata Virtual Account',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'ipay88-va' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that the customer will see on your checkout.', 'ipay88-va' ),
                'default'     => 'Pay using Permata Virtual Account via iPay88.',
                'desc_tip'    => true,
            ),
        );
    }
}
?>
