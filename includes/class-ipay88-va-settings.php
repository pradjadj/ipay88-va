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
            echo '<div class="updated"><p>' . __( 'Settings saved.', 'ipay88-va' ) . '</p></div>';
        }

        $settings = self::get_settings();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'iPay88 Settings', 'ipay88-va' ); ?></h1>
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
                            <th scope="row"><label for="ipay88_va_api_url"><?php esc_html_e( 'API URL', 'ipay88-va' ); ?></label></th>
                            <td><input name="ipay88_va_api_url" type="text" id="ipay88_va_api_url" value="<?php echo esc_attr( $settings['api_url'] ?? 'https://payment.ipay88.co.id/ePayment/WebService/PaymentAPI/Checkout' ); ?>" class="regular-text"></td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( __( 'Save Settings', 'ipay88-va' ) ); ?>
            </form>
        </div>
        <?php
    }

    public static function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $merchant_code = sanitize_text_field( $_POST['ipay88_va_merchant_code'] ?? '' );
        $merchant_key  = sanitize_text_field( $_POST['ipay88_va_merchant_key'] ?? '' );
        $api_url       = esc_url_raw( $_POST['ipay88_va_api_url'] ?? '' );

        update_option( 'ipay88_va_merchant_code', $merchant_code );
        update_option( 'ipay88_va_merchant_key', $merchant_key );
        update_option( 'ipay88_va_api_url', $api_url );
    }

    public static function get_settings() {
        return array(
            'merchant_code' => get_option( 'ipay88_va_merchant_code', '' ),
            'merchant_key'  => get_option( 'ipay88_va_merchant_key', '' ),
            'api_url'       => get_option( 'ipay88_va_api_url', 'https://payment.ipay88.co.id/ePayment/WebService/PaymentAPI/Checkout' ),
        );
    }
}
?>
