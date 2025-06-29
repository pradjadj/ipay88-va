<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once IPAY88_VA_PLUGIN_DIR . 'includes/class-ipay88-va-gateway.php';

class WC_iPay88_VA_CIMB extends WC_iPay88_VA_Gateway {

    protected function get_bank_slug() {
        return 'cimb';
    }

    protected function get_method_title() {
        return 'CIMB Niaga Virtual Account';
    }

    protected function get_method_description() {
        return 'Pembayaran Virtual Account melalui Bank CIMB Niaga.';
    }

    protected function get_payment_id() {
        return '135';
    }
}
?>
