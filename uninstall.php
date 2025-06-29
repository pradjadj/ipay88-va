<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete all plugin options
delete_option( 'ipay88_va_settings' );
delete_option( 'woocommerce_ipay88_va_bca_settings' );
delete_option( 'woocommerce_ipay88_va_bni_settings' );
delete_option( 'woocommerce_ipay88_va_bri_settings' );
delete_option( 'woocommerce_ipay88_va_mandiri_settings' );
delete_option( 'woocommerce_ipay88_va_cimb_settings' );
delete_option( 'woocommerce_ipay88_va_danamon_settings' );
delete_option( 'woocommerce_ipay88_va_maybank_settings' );
delete_option( 'woocommerce_ipay88_va_permata_settings' );

// Delete any other transients or options if needed
?>
