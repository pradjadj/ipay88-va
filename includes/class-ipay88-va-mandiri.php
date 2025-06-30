<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ipay88-va-gateway.php';

class WC_Gateway_iPay88_Mandiri extends WC_Gateway_iPay88_VA {

    public function __construct() {
        parent::__construct();
    }

    protected function get_bank_id() {
        return 'mandiri';
    }

    protected function get_bank_name() {
        return 'Mandiri';
    }

    protected function get_payment_id() {
        return '119'; // Mandiri VA PaymentId
    }
}
?>
