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

    // Include base class and bank VA classes here
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-gateway.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-bca.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-bni.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-bri.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-mandiri.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-cimb.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-danamon.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-maybank.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ipay88-va-permata.php';

    // Register the gateways
    add_filter( 'woocommerce_payment_gateways', 'ipay88_va_add_gateway_class' );
    // Add AJAX handler to display VA info on checkout page after place order
    add_action( 'wp_ajax_ipay88_va_get_payment_info', 'ipay88_va_get_payment_info' );
    add_action( 'wp_ajax_nopriv_ipay88_va_get_payment_info', 'ipay88_va_get_payment_info' );

    // Enqueue scripts for checkout page
    add_action( 'wp_enqueue_scripts', 'ipay88_va_enqueue_scripts' );

    // Add admin menu for settings dashboard
    add_action( 'admin_menu', 'ipay88_va_add_admin_menu' );

    // Register settings
    add_action( 'admin_init', 'ipay88_va_register_settings' );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ipay88_va_settings_link' );

function ipay88_va_settings_link( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=ipay88-va-settings' ) . '">' . __( 'Settings', 'ipay88-va' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
}

function ipay88_va_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_Gateway_iPay88_BCA';
    $gateways[] = 'WC_Gateway_iPay88_BNI';
    $gateways[] = 'WC_Gateway_iPay88_BRI';
    $gateways[] = 'WC_Gateway_iPay88_Mandiri';
    $gateways[] = 'WC_Gateway_iPay88_CIMB';
    $gateways[] = 'WC_Gateway_iPay88_Danamon';
    $gateways[] = 'WC_Gateway_iPay88_Maybank';
    $gateways[] = 'WC_Gateway_iPay88_Permata';
    return $gateways;
}

function ipay88_va_enqueue_scripts() {
    if ( is_checkout() ) {
        wp_enqueue_script( 'ipay88-va-checkout', plugin_dir_url( __FILE__ ) . 'assets/js/ipay88-va-checkout.js', array( 'jquery' ), '1.0', true );
        wp_localize_script( 'ipay88-va-checkout', 'ipay88_va_params', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'ipay88_va_nonce' ),
        ) );
        wp_enqueue_style( 'ipay88-va-style', plugin_dir_url( __FILE__ ) . 'assets/css/ipay88-va-style.css' );
    }
}

function ipay88_va_get_payment_info() {
    check_ajax_referer( 'ipay88_va_nonce', 'nonce' );

    $order_id = intval( $_POST['order_id'] ?? 0 );
    if ( ! $order_id ) {
        wp_send_json_error( 'Invalid order ID' );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( 'Order not found' );
    }

    $payment_method = $order->get_payment_method();
    $va_number = get_post_meta( $order_id, '_ipay88_va_number', true );
    $expiry = get_post_meta( $order_id, '_ipay88_va_expiry', true );

    if ( ! $va_number ) {
        wp_send_json_error( 'Virtual Account number not found' );
    }

    // Get bank name from payment method title
    $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
    $bank_name = isset( $available_gateways[ $payment_method ] ) ? $available_gateways[ $payment_method ]->get_title() : '';

    wp_send_json_success( array(
        'bank_name' => $bank_name,
        'va_number' => $va_number,
        'expiry'    => $expiry,
    ) );
}

// Add admin submenu under WooCommerce > Settings for iPay88 VA Settings
function ipay88_va_add_admin_menu() {
    add_submenu_page(
        'woocommerce',
        __( 'iPay88 VA Settings', 'ipay88-va' ),
        __( 'iPay88 VA Settings', 'ipay88-va' ),
        'manage_options',
        'ipay88-va-settings',
        'ipay88_va_settings_page'
    );
}

// Register settings for iPay88 VA Settings dashboard
function ipay88_va_register_settings() {
    register_setting( 'ipay88_va_settings_group', 'ipay88_va_merchant_code' );
    register_setting( 'ipay88_va_settings_group', 'ipay88_va_merchant_key' );
    register_setting( 'ipay88_va_settings_group', 'ipay88_va_expiry_time' );
    register_setting( 'ipay88_va_settings_group', 'ipay88_va_post_payment_status' );
    register_setting( 'ipay88_va_settings_group', 'ipay88_va_environment' );
    register_setting( 'ipay88_va_settings_group', 'ipay88_va_debug_log' );

    add_settings_section(
        'ipay88_va_settings_section',
        __( 'General Settings', 'ipay88-va' ),
        null,
        'ipay88-va-settings'
    );

    add_settings_field(
        'ipay88_va_merchant_code',
        __( 'Merchant Code', 'ipay88-va' ),
        'ipay88_va_merchant_code_callback',
        'ipay88-va-settings',
        'ipay88_va_settings_section'
    );

    add_settings_field(
        'ipay88_va_merchant_key',
        __( 'Merchant Key', 'ipay88-va' ),
        'ipay88_va_merchant_key_callback',
        'ipay88-va-settings',
        'ipay88_va_settings_section'
    );

    add_settings_field(
        'ipay88_va_expiry_time',
        __( 'Waktu Kedaluwarsa (menit)', 'ipay88-va' ),
        'ipay88_va_expiry_time_callback',
        'ipay88-va-settings',
        'ipay88_va_settings_section'
    );

    add_settings_field(
        'ipay88_va_post_payment_status',
        __( 'Status Setelah Pembayaran', 'ipay88-va' ),
        'ipay88_va_post_payment_status_callback',
        'ipay88-va-settings',
        'ipay88_va_settings_section'
    );

    add_settings_field(
        'ipay88_va_environment',
        __( 'Environment', 'ipay88-va' ),
        'ipay88_va_environment_callback',
        'ipay88-va-settings',
        'ipay88_va_settings_section'
    );

    add_settings_field(
        'ipay88_va_debug_log',
        __( 'Debug Log', 'ipay88-va' ),
        'ipay88_va_debug_log_callback',
        'ipay88-va-settings',
        'ipay88_va_settings_section'
    );
}

// Callbacks for settings fields
function ipay88_va_merchant_code_callback() {
    $value = get_option( 'ipay88_va_merchant_code', '' );
    echo '<input type="text" name="ipay88_va_merchant_code" value="' . esc_attr( $value ) . '" class="regular-text" />';
}

function ipay88_va_merchant_key_callback() {
    $value = get_option( 'ipay88_va_merchant_key', '' );
    echo '<input type="text" name="ipay88_va_merchant_key" value="' . esc_attr( $value ) . '" class="regular-text" />';
}

function ipay88_va_expiry_time_callback() {
    $value = get_option( 'ipay88_va_expiry_time', '60' );
    echo '<input type="number" name="ipay88_va_expiry_time" value="' . esc_attr( $value ) . '" class="small-text" min="1" />';
}

function ipay88_va_post_payment_status_callback() {
    $value = get_option( 'ipay88_va_post_payment_status', 'processing' );
    ?>
    <select name="ipay88_va_post_payment_status">
        <option value="processing" <?php selected( $value, 'processing' ); ?>>Processing</option>
        <option value="completed" <?php selected( $value, 'completed' ); ?>>Completed</option>
    </select>
    <?php
}

function ipay88_va_environment_callback() {
    $value = get_option( 'ipay88_va_environment', 'sandbox' );
    ?>
    <select name="ipay88_va_environment">
        <option value="sandbox" <?php selected( $value, 'sandbox' ); ?>>Sandbox</option>
        <option value="production" <?php selected( $value, 'production' ); ?>>Production</option>
    </select>
    <?php
}

function ipay88_va_debug_log_callback() {
    $value = get_option( 'ipay88_va_debug_log', 'no' );
    ?>
    <select name="ipay88_va_debug_log">
        <option value="yes" <?php selected( $value, 'yes' ); ?>>Yes</option>
        <option value="no" <?php selected( $value, 'no' ); ?>>No</option>
    </select>
    <?php
}

// Settings page callback (dashboard)
function ipay88_va_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'iPay88 VA Settings', 'ipay88-va' ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'ipay88_va_settings_group' );
            do_settings_sections( 'ipay88-va-settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
?>
