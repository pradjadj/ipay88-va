<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ipay88-va-gateway.php';

class WC_Gateway_iPay88_Maybank extends WC_Gateway_iPay88_VA {

    public function __construct() {
        parent::__construct();
    }

    protected function get_bank_id() {
        return 'maybank';
    }

    protected function get_bank_name() {
        return 'Maybank';
    }

    protected function get_payment_id() {
        return '9'; // Maybank VA PaymentId
    }
}
?>
