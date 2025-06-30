<?php
/**
 * Uninstall script for iPay88 VA Gateway plugin
 *
 * This script deletes all plugin settings from the database.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// List of options to delete
$options = array(
    'ipay88_va_merchant_code',
    'ipay88_va_merchant_key',
    'ipay88_va_expiry_time',
    'ipay88_va_post_payment_status',
    'ipay88_va_environment',
    'ipay88_va_debug_log',
);

foreach ( $options as $option ) {
    delete_option( $option );
}
