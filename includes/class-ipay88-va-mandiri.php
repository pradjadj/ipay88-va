<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once IPAY88_VA_PLUGIN_DIR . 'includes/class-ipay88-va-gateway.php';

class WC_iPay88_VA_Mandiri extends WC_iPay88_VA_Gateway {

    protected function get_bank_slug() {
        return 'mandiri';
    }

    protected function get_method_title() {
        return 'Mandiri Virtual Account';
    }

    protected function get_method_description() {
        return 'Pembayaran Virtual Account melalui Bank Mandiri.';
    }

    protected function get_payment_id() {
        return '119';
    }
}
?>
