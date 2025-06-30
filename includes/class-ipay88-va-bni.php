<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ipay88-va-gateway.php';

class WC_Gateway_iPay88_BNI extends WC_Gateway_iPay88_VA {

    public function __construct() {
        parent::__construct();
    }

    protected function get_bank_id() {
        return 'bni';
    }

    protected function get_bank_name() {
        return 'BNI';
    }

    protected function get_payment_id() {
        return '83'; // BNI VA PaymentId
    }
}
?>
