<?php
/**
 * Plugin Name: iPay88 VA Gateway
 * Plugin URI: https://sgnet.co.id
 * Description: iPay88 Payment Gateway with VA for WooCommerce - Display VA directly on checkout page
 * Version: 1.0
 * Author: Pradja DJ
 * Author URI: https://sgnet.co.id
 * Requires at least: 6.8
 * Tested up to: 6.8
 * WC requires at least: 7.0
 * WC tested up to: 9.8
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define( 'IPAY88_VA_PLUGIN_FILE', __FILE__ );
define( 'IPAY88_VA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Include required files
require_once IPAY88_VA_PLUGIN_DIR . 'includes/class-ipay88-va-gateway.php';
require_once IPAY88_VA_PLUGIN_DIR . 'includes/class-ipay88-va-bca.php';
require_once IPAY88_VA_PLUGIN_DIR . 'includes/class-ipay88-va-bni.php';
require_once IPAY88_VA_PLUGIN_DIR . 'includes/class-ipay88-va-bri.php';
require_once IPAY88_VA_PLUGIN_DIR . 'includes/class-ipay88-va-mandiri.php';
require_once IPAY88_VA_PLUGIN_DIR . 'includes/class-ipay88-va-cimb.php';
require_once IPAY88_VA_PLUGIN_DIR . 'includes/class-ipay88-va-danamon.php';
require_once IPAY88_VA_PLUGIN_DIR . 'includes/class-ipay88-va-maybank.php';
require_once IPAY88_VA_PLUGIN_DIR . 'includes/class-ipay88-va-permata.php';
require_once IPAY88_VA_PLUGIN_DIR . 'includes/class-ipay88-va-settings.php';

// Register payment gateways
function ipay88_va_register_gateways( $gateways ) {
    $gateways[] = 'WC_iPay88_VA_BCA';
    $gateways[] = 'WC_iPay88_VA_BNI';
    $gateways[] = 'WC_iPay88_VA_BRI';
    $gateways[] = 'WC_iPay88_VA_Mandiri';
    $gateways[] = 'WC_iPay88_VA_CIMB';
    $gateways[] = 'WC_iPay88_VA_Danamon';
    $gateways[] = 'WC_iPay88_VA_Maybank';
    $gateways[] = 'WC_iPay88_VA_Permata';
    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'ipay88_va_register_gateways' );

// Add settings link on plugin page
function ipay88_va_plugin_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=ipay88_va_settings' ) . '">' . __( 'Settings', 'ipay88-va' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ipay88_va_plugin_action_links' );

// Initialize settings page
function ipay88_va_init_settings() {
    if ( class_exists( 'WC_iPay88_VA_Settings' ) ) {
        new WC_iPay88_VA_Settings();
    }
}
add_action( 'plugins_loaded', 'ipay88_va_init_settings' );

// Register uninstall hook
register_uninstall_hook( __FILE__, 'ipay88_va_uninstall' );
function ipay88_va_uninstall() {
    // Delete all plugin options
    delete_option( 'ipay88_va_settings' );
    // Delete any other options or transients if needed
}
?>
