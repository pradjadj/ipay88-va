<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class iPay88_VA_Settings {

    public static function output() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'ipay88-va' ) );
        }

        // Save settings if form submitted
        if ( isset( $_POST['ipay88_va_settings_nonce'] ) && wp_verify_nonce( $_POST['ipay88_va_settings_nonce'], 'ipay88_va_save_settings' ) ) {
            self::save_settings();
        }

        $settings = self::get_settings();
        ?>
        <div class="woocommerce">
            <h1><?php esc_html_e( 'iPay88 VA Settings', 'ipay88-va' ); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'ipay88_va_save_settings', 'ipay88_va_settings_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="ipay88_va_merchant_code"><?php esc_html_e( 'Merchant Code', 'ipay88-va' ); ?></label></th>
                            <td><input name="ipay88_va_merchant_code" type="text" id="ipay88_va_merchant_code" value="<?php echo esc_attr( $settings['merchant_code'] ?? '' ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ipay88_va_merchant_key"><?php esc_html_e( 'Merchant Key', 'ipay88-va' ); ?></label></th>
                            <td><input name="ipay88_va_merchant_key" type="password" id="ipay88_va_merchant_key" value="<?php echo esc_attr( $settings['merchant_key'] ?? '' ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ipay88_va_expiry_time"><?php esc_html_e( 'Waktu Kedaluwarsa (menit)', 'ipay88-va' ); ?></label></th>
                            <td><input name="ipay88_va_expiry_time" type="number" id="ipay88_va_expiry_time" value="<?php echo esc_attr( $settings['expiry_time'] ?? 60 ); ?>" class="small-text" min="1" max="1440"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ipay88_va_order_status"><?php esc_html_e( 'Status Setelah Pembayaran', 'ipay88-va' ); ?></label></th>
                            <td>
                                <select name="ipay88_va_order_status" id="ipay88_va_order_status">
                                    <?php
                                    $order_statuses = wc_get_order_statuses();
                                    foreach ( $order_statuses as $status_slug => $status_name ) {
                                        $selected = ( isset( $settings['order_status'] ) && $settings['order_status'] === $status_slug ) ? 'selected' : '';
                                        echo '<option value="' . esc_attr( $status_slug ) . '" ' . $selected . '>' . esc_html( $status_name ) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ipay88_va_environment"><?php esc_html_e( 'Environment', 'ipay88-va' ); ?></label></th>
                            <td>
                                <select name="ipay88_va_environment" id="ipay88_va_environment">
                                    <option value="production" <?php selected( $settings['environment'] ?? '', 'production' ); ?>><?php esc_html_e( 'Production', 'ipay88-va' ); ?></option>
                                    <option value="sandbox" <?php selected( $settings['environment'] ?? '', 'sandbox' ); ?>><?php esc_html_e( 'Sandbox', 'ipay88-va' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ipay88_va_debug_log"><?php esc_html_e( 'Debug Log', 'ipay88-va' ); ?></label></th>
                            <td>
                                <input type="checkbox" name="ipay88_va_debug_log" id="ipay88_va_debug_log" value="yes" <?php checked( $settings['debug_log'] ?? '', 'yes' ); ?>>
                                <label for="ipay88_va_debug_log"><?php esc_html_e( 'Enable debug logging', 'ipay88-va' ); ?></label>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
        <?php
    }

    public static function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $merchant_code = sanitize_text_field( $_POST['ipay88_va_merchant_code'] ?? '' );
        $merchant_key  = sanitize_text_field( $_POST['ipay88_va_merchant_key'] ?? '' );
        $expiry_time   = absint( $_POST['ipay88_va_expiry_time'] ?? 60 );
        $order_status  = sanitize_text_field( $_POST['ipay88_va_order_status'] ?? 'wc-processing' );
        $environment   = sanitize_text_field( $_POST['ipay88_va_environment'] ?? 'production' );
        $debug_log     = ( isset( $_POST['ipay88_va_debug_log'] ) && $_POST['ipay88_va_debug_log'] === 'yes' ) ? 'yes' : 'no';

        update_option( 'ipay88_va_merchant_code', $merchant_code );
        update_option( 'ipay88_va_merchant_key', $merchant_key );
        update_option( 'ipay88_va_expiry_time', $expiry_time );
        update_option( 'ipay88_va_order_status', $order_status );
        update_option( 'ipay88_va_environment', $environment );
        update_option( 'ipay88_va_debug_log', $debug_log );
    }

    public static function get_settings() {
        return array(
            'merchant_code' => get_option( 'ipay88_va_merchant_code', '' ),
            'merchant_key'  => get_option( 'ipay88_va_merchant_key', '' ),
            'expiry_time'   => get_option( 'ipay88_va_expiry_time', 60 ),
            'order_status'  => get_option( 'ipay88_va_order_status', 'wc-processing' ),
            'environment'   => get_option( 'ipay88_va_environment', 'production' ),
            'debug_log'     => get_option( 'ipay88_va_debug_log', 'no' ),
        );
    }
}
?>
