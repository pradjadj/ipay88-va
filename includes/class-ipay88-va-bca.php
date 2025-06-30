<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ipay88-va-gateway.php';

class WC_Gateway_iPay88_BCA extends WC_Gateway_iPay88_VA {

    public function __construct() {
        parent::__construct();
    }

    protected function get_bank_id() {
        return 'bca';
    }

    protected function get_bank_name() {
        return 'BCA';
    }

    protected function get_payment_id() {
        return '140'; // BCA VA PaymentId
    }
}
?>
