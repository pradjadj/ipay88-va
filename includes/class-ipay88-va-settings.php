<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_iPay88_VA_Settings {

    public function __construct() {
        add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
        add_action( 'woocommerce_settings_tabs_ipay88_va_settings', array( $this, 'settings_tab' ) );
        add_action( 'woocommerce_update_options_ipay88_va_settings', array( $this, 'update_settings' ) );
    }

    public function add_settings_tab( $settings_tabs ) {
        $settings_tabs['ipay88_va_settings'] = 'iPay88 VA Settings';
        return $settings_tabs;
    }

    public function settings_tab() {
        woocommerce_admin_fields( $this->get_settings() );
    }

    public function update_settings() {
        woocommerce_update_options( $this->get_settings() );
    }

    public function get_settings() {
        return array(
            'section_title' => array(
                'name' => 'iPay88 VA Settings',
                'type' => 'title',
                'desc' => 'Centralized settings for all iPay88 VA payment gateways.',
                'id'   => 'ipay88_va_settings_section_title',
            ),
            'merchant_code' => array(
                'name'     => 'Merchant Code',
                'type'     => 'text',
                'desc'     => 'Your iPay88 Merchant Code',
                'id'       => 'ipay88_va_merchant_code',
                'default'  => '',
                'desc_tip' => true,
            ),
            'merchant_key' => array(
                'name'     => 'Merchant Key',
                'type'     => 'password',
                'desc'     => 'Your iPay88 Merchant Key',
                'id'       => 'ipay88_va_merchant_key',
                'default'  => '',
                'desc_tip' => true,
            ),
            'expiry_minutes' => array(
                'name'     => 'Waktu Kedaluwarsa (menit)',
                'type'     => 'number',
                'desc'     => 'Waktu kedaluwarsa pembayaran dalam menit',
                'id'       => 'ipay88_va_expiry_minutes',
                'default'  => 60,
                'desc_tip' => true,
                'custom_attributes' => array(
                    'min' => 1,
                    'step' => 1,
                ),
            ),
            'status_after_payment' => array(
                'name'     => 'Status Setelah Pembayaran',
                'type'     => 'select',
                'desc'     => 'Status order setelah pembayaran sukses',
                'id'       => 'ipay88_va_status_after_payment',
                'default'  => 'processing',
                'desc_tip' => true,
                'options'  => array(
                    'processing' => 'Processing',
                    'completed'  => 'Completed',
                ),
            ),
            'environment' => array(
                'name'     => 'Environment',
                'type'     => 'select',
                'desc'     => 'Select Sandbox or Production',
                'id'       => 'ipay88_va_environment',
                'default'  => 'sandbox',
                'desc_tip' => true,
                'options'  => array(
                    'sandbox'    => 'Sandbox',
                    'production' => 'Production',
                ),
            ),
            'debug_log' => array(
                'name'     => 'Debug Log',
                'type'     => 'checkbox',
                'desc'     => 'Enable debug logging',
                'id'       => 'ipay88_va_debug_log',
                'default'  => 'no',
                'desc_tip' => true,
            ),
            'section_end' => array(
                'type' => 'sectionend',
                'id'   => 'ipay88_va_settings_section_end',
            ),
        );
    }
}
?>
