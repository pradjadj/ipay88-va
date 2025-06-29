<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once IPAY88_VA_PLUGIN_DIR . 'includes/class-ipay88-va-gateway.php';

class WC_iPay88_VA_Permata extends WC_iPay88_VA_Gateway {

    protected function get_bank_slug() {
        return 'permata';
    }

    protected function get_method_title() {
        return 'Permata Bank Virtual Account';
    }

    protected function get_method_description() {
        return 'Pembayaran Virtual Account melalui Bank Permata.';
    }

    protected function get_payment_id() {
        return '112';
    }
}
?>
