<?php
/**
 * Plugin Name: iPay88 VA Gateway
 * Plugin URI: https://sgnet.co.id
 * Description: iPay88 Payment Gateway with VA for WooCommerce - Display VA directly on checkout page
 * Version: 1.0
 * Author: Pradja DJ
 * Author URI: https://sgnet.co.id
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Check if WooCommerce is active
add_action( 'plugins_loaded', 'ipay88_va_gateway_init', 11 );

function ipay88_va_gateway_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    // Include base class and payment method classes
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-gateway.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-bca.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-bni.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-bri.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-mandiri.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-cimb.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-danamon.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-maybank.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-permata.php';

    // Register payment gateways
    add_filter( 'woocommerce_payment_gateways', 'ipay88_va_add_gateways' );
    function ipay88_va_add_gateways( $gateways ) {
        $gateways[] = 'WC_Gateway_iPay88_VA_BCA';
        $gateways[] = 'WC_Gateway_iPay88_VA_BNI';
        $gateways[] = 'WC_Gateway_iPay88_VA_BRI';
        $gateways[] = 'WC_Gateway_iPay88_VA_Mandiri';
        $gateways[] = 'WC_Gateway_iPay88_VA_CIMB';
        $gateways[] = 'WC_Gateway_iPay88_VA_Danamon';
        $gateways[] = 'WC_Gateway_iPay88_VA_Maybank';
        $gateways[] = 'WC_Gateway_iPay88_VA_Permata';
        return $gateways;
    }

    // Add settings link next to deactivate button
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ipay88_va_plugin_action_links' );
    function ipay88_va_plugin_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=ipay88_va_settings' ) . '">' . __( 'Settings', 'ipay88-va' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    // Include settings page class
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-settings.php';

    // Add iPay88 VA tab to WooCommerce settings
    add_filter( 'woocommerce_settings_tabs_array', 'ipay88_va_add_settings_tab', 50 );
    function ipay88_va_add_settings_tab( $settings_tabs ) {
        $settings_tabs['ipay88_va'] = __( 'iPay88 VA Settings', 'ipay88-va' );
        return $settings_tabs;
    }

    add_action( 'woocommerce_settings_ipay88_va', 'ipay88_va_settings_tab_content' );
    function ipay88_va_settings_tab_content() {
        if ( class_exists( 'iPay88_VA_Settings' ) ) {
            iPay88_VA_Settings::output();
        }
    }

    // Add WooCommerce System Status tab for error logs
    add_filter( 'woocommerce_system_status_report_tabs', 'ipay88_va_add_system_status_tab' );
    function ipay88_va_add_system_status_tab( $tabs ) {
        $tabs['ipay88_va_logs'] = __( 'iPay88 VA Logs', 'ipay88-va' );
        return $tabs;
    }

    add_action( 'woocommerce_system_status_report_ipay88_va_logs', 'ipay88_va_system_status_logs' );
    function ipay88_va_system_status_logs() {
        $log_file = wc_get_log_file_path( 'ipay88-va' );
        if ( file_exists( $log_file ) ) {
            echo '<pre style="max-height:400px; overflow:auto;">' . esc_html( file_get_contents( $log_file ) ) . '</pre>';
        } else {
            echo '<p>' . __( 'No iPay88 VA logs found.', 'ipay88-va' ) . '</p>';
        }
    }
}

// Load plugin textdomain for translations
add_action( 'plugins_loaded', 'ipay88_va_load_textdomain' );
function ipay88_va_load_textdomain() {
    load_plugin_textdomain( 'ipay88-va', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
?>
