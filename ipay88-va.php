<?php
/**
 * Plugin Name: iPay88 VA Gateway
 * Plugin URI: https://sgnet.co.id
 * Description: iPay88 Payment Gateway with VA for WooCommerce - Display VA directly on checkout page
 * Version: 1.0
 * Author: Pradja DJ
 * Author URI: https://sgnet.co.id
 */

defined('ABSPATH') or exit;

// Add settings link
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ipay88_va_gateway_plugin_action_links');
function ipay88_va_gateway_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=ipay88_va') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Add payment gateway
add_filter('woocommerce_payment_gateways', 'ipay88_va_gateway_add_gateway_class');
function ipay88_va_gateway_add_gateway_class($gateways) {
    $gateways[] = 'WC_Gateway_IPay88_VA';
    return $gateways;
}

// Initialize gateway
add_action('woocommerce_loaded', 'ipay88_va_gateway_init_gateway_class');
function ipay88_va_gateway_init_gateway_class() {
    class WC_Gateway_IPay88_VA extends WC_Payment_Gateway {
        
        private $merchant_key;
        private $merchant_code;
        private $environment;
        private $expiry_hours;
        private $check_interval = 5; // Interval cek pembayaran dalam detik
        private $status_after_payment;
        
        // Supported VA banks
        private $supported_banks = array(
            'bca' => array('id' => '140', 'name' => 'BCA'),
            'bni' => array('id' => '83', 'name' => 'BNI'),
            'bri' => array('id' => '118', 'name' => 'BRI'),
            'mandiri' => array('id' => '119', 'name' => 'Mandiri'),
            'cimb' => array('id' => '135', 'name' => 'CIMB Niaga'),
            'danamon' => array('id' => '111', 'name' => 'Danamon'),
            'maybank' => array('id' => '9', 'name' => 'Maybank'),
            'permata' => array('id' => '112', 'name' => 'Permata Bank')
        );
        
        public function __construct() {
            $this->id = 'ipay88_va';
            $this->has_fields = true;
            $this->method_title = 'iPay88 Virtual Account';
            $this->method_description = 'Terima pembayaran via Virtual Account melalui iPay88 tanpa redirect dari halaman checkout.';
            
            $this->init_form_fields();
            $this->init_settings();
            
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->merchant_key = $this->get_option('merchant_key');
            $this->merchant_code = $this->get_option('merchant_code');
            $this->environment = $this->get_option('environment');
            $this->expiry_hours = $this->get_option('expiry_hours', 24);
            $this->status_after_payment = $this->get_option('status_after_payment', 'processing');
            
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            add_action('woocommerce_api_wc_gateway_ipay88_va', array($this, 'handle_ipay88_response'));
            
            // AJAX handler
            add_action('wp_ajax_ipay88_va_check_payment', array($this, 'check_payment_status'));
            add_action('wp_ajax_nopriv_ipay88_va_check_payment', array($this, 'check_payment_status'));
            
            if (!$this->is_valid_for_use()) {
                $this->enabled = 'no';
            }
        }
        
        public function is_valid_for_use() {
            return in_array(get_woocommerce_currency(), array('IDR'));
        }
        
        public function admin_options() {
            if ($this->is_valid_for_use()) {
                parent::admin_options();
            } else {
                echo '<div class="inline error"><p><strong>Gateway Disabled</strong>: iPay88 Virtual Account tidak mendukung mata uang toko Anda.</p></div>';
            }
        }
        
        public function init_form_fields() {
            $bank_options = array();
            foreach ($this->supported_banks as $code => $bank) {
                $bank_options[$code] = $bank['name'];
            }
            
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Aktif/Nonaktif',
                    'label'       => 'Aktifkan iPay88 Virtual Account',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Judul',
                    'type'        => 'text',
                    'description' => 'Judul metode pembayaran yang dilihat pelanggan.',
                    'default'     => 'Virtual Account via iPay88',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Deskripsi',
                    'type'        => 'textarea',
                    'description' => 'Deskripsi metode pembayaran yang dilihat pelanggan.',
                    'default'     => 'Bayar dengan Virtual Account melalui iPay88. Nomor VA akan muncul setelah klik Place Order.',
                ),
                'merchant_code' => array(
                    'title'       => 'Merchant Code',
                    'type'        => 'text',
                    'description' => 'Merchant Code diberikan oleh iPay88.',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'merchant_key' => array(
                    'title'       => 'Merchant Key',
                    'type'        => 'password',
                    'description' => 'Merchant Key diberikan oleh iPay88.',
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'environment' => array(
                    'title'       => 'Environment',
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => 'Pilih environment untuk transaksi.',
                    'default'     => 'sandbox',
                    'desc_tip'    => true,
                    'options'     => array(
                        'sandbox'    => 'Sandbox',
                        'production' => 'Production'
                    )
                ),
                'enabled_banks' => array(
                    'title'       => 'Bank Virtual Account',
                    'type'        => 'multiselect',
                    'class'       => 'wc-enhanced-select',
                    'description' => 'Pilih bank Virtual Account yang ingin diaktifkan',
                    'default'     => array_keys($this->supported_banks),
                    'options'     => $bank_options,
                    'desc_tip'    => true,
                ),
                'expiry_hours' => array(
                    'title'       => 'Waktu Kedaluwarsa (jam)',
                    'type'        => 'number',
                    'description' => 'Waktu kedaluwarsa transaksi dalam jam.',
                    'default'     => '24',
                    'desc_tip'    => true,
                    'custom_attributes' => array(
                        'min'  => '1',
                        'step' => '1'
                    )
                ),
                'status_after_payment' => array(
                    'title'       => 'Status Setelah Pembayaran',
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => 'Status order setelah pembayaran berhasil.',
                    'default'     => 'processing',
                    'options'     => array(
                        'processing' => 'Processing',
                        'completed'  => 'Completed'
                    )
                ),
                'debug' => array(
                    'title'       => 'Debug Log',
                    'type'        => 'checkbox',
                    'label'       => 'Aktifkan logging',
                    'default'     => 'no',
                    'description' => 'Log iPay88 VA events di <a href="' . esc_url(admin_url('admin.php?page=wc-status&tab=logs')) . '">System Status</a>',
                ),
            );
        }
        
        public function payment_fields() {
            if ($description = $this->get_description()) {
                echo wpautop(wptexturize($description));
            }
            
            $enabled_banks = $this->get_option('enabled_banks', array());
            
            echo '<fieldset id="' . esc_attr($this->id) . '-form" class="wc-payment-form" style="background:transparent;">';
            
            echo '<div class="form-row form-row-wide"><label>Pilih Bank <span class="required">*</span></label>';
            echo '<select name="' . esc_attr($this->id) . '_bank" id="' . esc_attr($this->id) . '_bank">';
            echo '<option value="">-- Pilih Bank --</option>';
            
            foreach ($this->supported_banks as $code => $bank) {
                if (in_array($code, $enabled_banks)) {
                    echo '<option value="' . esc_attr($code) . '">' . esc_html($bank['name']) . '</option>';
                }
            }
            
            echo '</select></div>';
            
            echo '</fieldset>';
        }
        
        public function validate_fields() {
            if (empty($_POST[$this->id . '_bank'])) {
                wc_add_notice('Silakan pilih bank Virtual Account', 'error');
                return false;
            }
            
            $enabled_banks = $this->get_option('enabled_banks', array());
            $selected_bank = sanitize_text_field($_POST[$this->id . '_bank']);
            
            if (!in_array($selected_bank, $enabled_banks)) {
                wc_add_notice('Bank Virtual Account yang dipilih tidak valid', 'error');
                return false;
            }
            
            return true;
        }
        
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $selected_bank = sanitize_text_field($_POST[$this->id . '_bank']);
            
            if (!isset($this->supported_banks[$selected_bank])) {
                wc_add_notice('Bank Virtual Account yang dipilih tidak valid', 'error');
                return false;
            }
            
            $bank = $this->supported_banks[$selected_bank];
            
            // Cek apakah sudah ada transaksi sebelumnya
            if (!$order->get_meta('_ipay88_ref_no')) {
                $order->update_status('pending', 'Menunggu pembayaran Virtual Account ' . $bank['name']);
                $order->update_meta_data('_ipay88_expiry', time() + ($this->expiry_hours * 3600));
                $order->update_meta_data('_ipay88_bank', $selected_bank);
                $order->save();
            }
            
            // Generate request ke iPay88
            $this->generate_ipay88_request($order, $bank);
            
            // Reduce stock levels
            wc_reduce_stock_levels($order_id);
            
            // Empty cart
            WC()->cart->empty_cart();
            
            // Return thankyou redirect
            return array(
                'result'    => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }
        
        private function generate_ipay88_request($order, $bank) {
            $merchant_key = $this->merchant_key;
            $merchant_code = $this->merchant_code;
            $ref_no = date('Ymd') . '-' . $order->get_id();
            $amount = number_format($order->get_total(), 2, '.', '');
            $currency = 'IDR';
            $expiry_date = date('Y-m-d H:i:s', time() + ($this->expiry_hours * 3600));
            
            $signature_string = '||' . $merchant_key . '||' . $merchant_code . '||' . $ref_no . '||' . $amount . '||' . $currency . '||';
            $signature = hash('sha256', $signature_string);
            
            $this->log('Signature Generation Details:');
            $this->log('MerchantKey: ' . $merchant_key);
            $this->log('MerchantCode: ' . $merchant_code);
            $this->log('RefNo: ' . $ref_no);
            $this->log('Amount: ' . $amount);
            $this->log('Currency: ' . $currency);
            $this->log('Signature String: ' . $signature_string);
            $this->log('Generated Signature: ' . $signature);
            
            $request_data = array(
                'APIVersion' => '2.0',
                'MerchantCode' => $merchant_code,
                'PaymentId' => $bank['id'],
                'Currency' => $currency,
                'RefNo' => $ref_no,
                'Amount' => $amount,
                'ProdDesc' => $this->generate_product_description($order),
                'RequestType' => 'SEAMLESS',
                'UserName' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'UserEmail' => $order->get_billing_email(),
                'UserContact' => $order->get_billing_phone(),
                'Remark' => '',
                'Lang' => 'UTF-8',
                'ResponseURL' => add_query_arg('wc-api', 'WC_Gateway_IPay88_VA', home_url('/')),
                'BackendURL' => add_query_arg('wc-api', 'WC_Gateway_IPay88_VA', home_url('/')),
                'TransactionExpiryDate' => $expiry_date,
                'Signature' => $signature
            );
            
            $endpoint = ($this->environment === 'production') 
                ? 'https://payment.ipay88.co.id/ePayment/WebService/PaymentAPI/Checkout'
                : 'https://sandbox.ipay88.co.id/ePayment/WebService/PaymentAPI/Checkout';
            
            $this->log('iPay88 Request: ' . print_r($request_data, true));
            
            $response = wp_remote_post($endpoint, array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode($request_data),
                'timeout' => 60
            ));
            
            if (is_wp_error($response)) {
                $this->log('iPay88 Request Error: ' . $response->get_error_message());
                wc_add_notice('Error processing payment. Please try again.', 'error');
                return false;
            }
            
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            $this->log('iPay88 Response: ' . print_r($response_data, true));
            
            if (isset($response_data['Code']) && $response_data['Code'] === '1') {
                $order->update_meta_data('_ipay88_checkout_id', $response_data['CheckoutID']);
                $order->update_meta_data('_ipay88_ref_no', $ref_no);
                $order->update_meta_data('_ipay88_va_number', $response_data['VirtualAccountAssigned']);
                $order->update_meta_data('_ipay88_expiry', strtotime($expiry_date));
                $order->update_meta_data('_ipay88_bank_name', $bank['name']);
                $order->save();
                
                return true;
            } else {
                $error_message = isset($response_data['Message']) ? $response_data['Message'] : 'Unknown error occurred';
                $this->log('iPay88 Error: ' . $error_message);
                wc_add_notice('Error processing payment: ' . $error_message, 'error');
                return false;
            }
        }
        
        private function generate_product_description($order) {
            $product_names = array();
            foreach ($order->get_items() as $item) {
                $product_names[] = $item->get_name();
            }
            return implode(', ', $product_names);
        }
        
        public function thankyou_page($order_id) {
            $order = wc_get_order($order_id);
            
            if ($order->get_payment_method() === $this->id) {
                if ($order->is_paid()) {
                    echo '<div class="ipay88-va-container" style="text-align: center; margin: 20px 0;">';
                    echo '<div class="woocommerce-message" style="font-size: 1.2em; padding: 15px; background-color: #f5f5f5; border-left: 4px solid #46b450;">';
                    echo 'Pembayaran Diterima';
                    echo '</div>';
                    echo '</div>';
                } elseif ($order->has_status('pending')) {
                    $va_number = $order->get_meta('_ipay88_va_number');
                    $bank_name = $order->get_meta('_ipay88_bank_name');
                    $expiry_timestamp = $order->get_meta('_ipay88_expiry');
                    
                    if ($va_number && $bank_name) {
                        $current_time = time();
                        $time_left = max(0, $expiry_timestamp - $current_time);
                        
                        echo '<div class="ipay88-va-container" style="text-align: center; margin: 20px 0;">';
                        echo '<div id="ipay88-va-content">';
                        echo '<h3>Virtual Account Pembayaran</h3>';
                        
                        echo '<div style="margin: 20px 0; padding: 15px; background-color: #f7f7f7; border: 1px solid #e5e5e5; display: inline-block;">';
                        echo '<div style="font-size: 1.2em; font-weight: bold; margin-bottom: 10px;">' . esc_html($bank_name) . '</div>';
                        echo '<div style="font-size: 1.5em; font-weight: bold; letter-spacing: 2px; color: #2e4453;">' . esc_html($va_number) . '</div>';
                        echo '</div>';
                        
                        echo '<div id="ipay88-countdown" style="margin: 15px 0; font-weight: bold; color: #d63638;">';
                        echo 'Selesaikan pembayaran dalam: <span id="ipay88-countdown-timer">' . $this->format_countdown($time_left) . '</span>';
                        echo '</div>';
                        
                        echo '<p>Silakan transfer sesuai nominal ke Virtual Account di atas sebelum waktu kedaluwarsa.</p>';
                        
                        echo '<button id="ipay88-refresh-page" class="button alt" style="margin: 10px 0; padding: 10px 20px; font-size: 1.2em;">';
                        echo 'Refresh Status Pembayaran';
                        echo '</button>';
                        echo '</div>';
                        
                        echo '<div id="ipay88-payment-status" style="margin-top: 20px;"></div>';
                        
                        $ajax_nonce = wp_create_nonce('ipay88_va_check_payment_nonce');
                        
                        wc_enqueue_js('
                            jQuery(document).ready(function($) {
                                // Countdown timer
                                var countdown = ' . $time_left . ';
                                var countdownElement = $("#ipay88-countdown-timer");
                                var countdownInterval = setInterval(function() {
                                    countdown--;
                                    if (countdown <= 0) {
                                        clearInterval(countdownInterval);
                                        $("#ipay88-va-content").hide();
                                        $("#ipay88-payment-status").html("<div class=\"woocommerce-error\" style=\"text-align: center;\">Waktu pembayaran telah habis. Silakan buat pesanan baru.</div>");
                                        return;
                                    }
                                    
                                    var hours = Math.floor(countdown / 3600);
                                    var minutes = Math.floor((countdown % 3600) / 60);
                                    var seconds = countdown % 60;
                                    countdownElement.text(
                                        (hours < 10 ? "0" + hours : hours) + ":" + 
                                        (minutes < 10 ? "0" + minutes : minutes) + ":" + 
                                        (seconds < 10 ? "0" + seconds : seconds)
                                    );
                                }, 1000);
                                
                                // Manual refresh button
                                $("#ipay88-refresh-page").on("click", function() {
                                    window.location.reload();
                                });
                                
                                // Payment status check
                                var checkInterval = ' . ($this->check_interval * 1000) . ';
                                var paymentCheck = function() {
                                    $.ajax({
                                        url: "' . admin_url('admin-ajax.php') . '",
                                        type: "POST",
                                        data: {
                                            action: "ipay88_va_check_payment",
                                            order_id: "' . $order->get_id() . '",
                                            security: "' . $ajax_nonce . '"
                                        },
                                        dataType: "json",
                                        success: function(response) {
                                            if (response.success) {
                                                if (response.data.paid) {
                                                    clearInterval(countdownInterval);
                                                    $("#ipay88-va-content").hide();
                                                    $("#ipay88-payment-status").html("<div class=\"woocommerce-message\" style=\"text-align: center;\">Pembayaran berhasil diterima! Halaman akan diperbarui...</div>");
                                                    setTimeout(function() {
                                                        window.location.reload();
                                                    }, 2000);
                                                } else if (response.data.expired) {
                                                    clearInterval(countdownInterval);
                                                    $("#ipay88-va-content").hide();
                                                    $("#ipay88-payment-status").html("<div class=\"woocommerce-error\" style=\"text-align: center;\">Waktu pembayaran telah habis. Silakan buat pesanan baru.</div>");
                                                }
                                            }
                                        },
                                        error: function(xhr, status, error) {
                                            console.error("Payment check error:", error);
                                        }
                                    });
                                };
                                
                                // Start checking immediately and set interval
                                paymentCheck();
                                var paymentCheckInterval = setInterval(paymentCheck, checkInterval);
                                
                                // Clear interval when payment is received
                                $(document).on("ipay88_payment_received", function() {
                                    clearInterval(paymentCheckInterval);
                                });
                            });
                        ');
                        
                        echo '</div>';
                    } else {
                        echo '<div class="woocommerce-error">Error: Informasi Virtual Account tidak ditemukan.</div>';
                    }
                }
            }
        }
        
        private function format_countdown($seconds) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $seconds = $seconds % 60;
            
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        public function handle_ipay88_response() {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $raw_post = file_get_contents('php://input');
                $response = json_decode($raw_post, true);
                
                $this->log('BackendPost Received: ' . print_r($response, true));
                
                if ($response && isset($response['RefNo'])) {
                    $ref_parts = explode('-', $response['RefNo']);
                    $order_id = end($ref_parts);
                    $order = wc_get_order($order_id);
                    
                    if ($order && $order->get_payment_method() === $this->id) {
                        $signature_string = '||' . $this->merchant_key . '||' . $response['MerchantCode'] . '||' . 
                                          $response['PaymentId'] . '||' . $response['RefNo'] . '||' . 
                                          $response['Amount'] . '||' . $response['Currency'] . '||' . 
                                          $response['TransactionStatus'] . '||';
                        $generated_signature = hash('sha256', $signature_string);
                        
                        $this->log('BackendPost Signature Verification:');
                        $this->log('Received Signature: ' . $response['Signature']);
                        $this->log('Generated Signature: ' . $generated_signature);
                        
                        if ($generated_signature === $response['Signature']) {
                            if ($response['TransactionStatus'] === '1') {
                                $new_status = $this->status_after_payment;
                                $order->update_status($new_status, 'Pembayaran berhasil via iPay88. TransID: ' . $response['TransId']);
                                $order->payment_complete($response['TransId']);
                                
                                // Trigger event for frontend
                                $this->log('Payment received for order #' . $order_id);
                                
                                header('Content-Type: application/json');
                                echo json_encode(array(
                                    'Code' => '1',
                                    'Message' => array(
                                        'English' => 'Status Received',
                                        'Indonesian' => 'Pembayaran diterima'
                                    )
                                ));
                                exit;
                            }
                        } else {
                            $this->log('BackendPost Signature Mismatch');
                            header('Content-Type: application/json');
                            echo json_encode(array(
                                'Code' => '0',
                                'Message' => array(
                                    'English' => 'Invalid Signature',
                                    'Indonesian' => 'Signature tidak valid'
                                )
                            ));
                            exit;
                        }
                    }
                }
            }
            
            header('Content-Type: application/json');
            echo json_encode(array(
                'Code' => '0',
                'Message' => array(
                    'English' => 'Invalid Request',
                    'Indonesian' => 'Permintaan tidak valid'
                )
            ));
            exit;
        }
        
        public function check_payment_status() {
            check_ajax_referer('ipay88_va_check_payment_nonce', 'security');
            
            $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
            $order = wc_get_order($order_id);
            
            if (!$order) {
                wp_send_json_error(array('message' => 'Order tidak ditemukan'));
                return;
            }
            
            $paid = $order->is_paid();
            $expired = false;
            
            if (!$paid) {
                $expiry = $order->get_meta('_ipay88_expiry');
                if ($expiry && time() > $expiry && $order->has_status('pending')) {
                    sleep(rand(1, 5));
                    wc_increase_stock_levels($order->get_id());
                    $order->update_status(
                        'cancelled', 
                        sprintf(
                            'Pembayaran Virtual Account kadaluarsa (waktu habis: %s)',
                            date('Y-m-d H:i:s', $expiry)
                        )
                    );
                    $this->log(sprintf(
                        'Order #%d cancelled due to VA payment expiry',
                        $order->get_id()
                    ));
                    $expired = true;
                }
            }
            
            wp_send_json_success(array(
                'paid' => $paid,
                'expired' => $expired,
                'message' => $paid ? 'Pembayaran telah diterima' : 
                          ($expired ? 'Waktu pembayaran telah habis' : 'Menunggu pembayaran')
            ));
        }
        
        private function log($message) {
            if ($this->get_option('debug') === 'yes') {
                $logger = wc_get_logger();
                $logger->debug($message, array('source' => 'ipay88-va-gateway'));
            }
        }
    }
}