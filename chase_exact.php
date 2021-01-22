<?php
/*
 * Plugin Name: WooCommerce Chase E-xact Payment Gateway
 * Description: Process payments with Chase E-xact gateway.
 * Author: Nikolas North (WesternUSC)
 * Author URI: https://github.com/WesternUSC
 * Version: 1.0.0
 */

//if (!class_exists('WooCommerce'))
//    return;

add_filter('woocommerce_payment_gateways', 'add_chase_exact');
function add_chase_exact($methods) {
    $methods[] = 'WC_Chase_Exact_Gateway';
    return $methods;
}

add_action('plugins_loaded', 'init_chase_exact');
function init_chase_exact() {
    if (!class_exists('WC_Payment_Gateway'))
        return;
    class WC_Chase_Exact_Gateway extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'chase_exact';
            $this->icon = '';
            $this->has_fields = false;
            $this->method_title = __('Chase E-xact Checkout');
            $this->method_description = __('Redirects customers to Chase E-xact to enter their payment information.');

            // Define settings
            $this->init_form_fields();

            // Retrieve settings
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->login_id = $this->get_option('login_id');
            $this->transaction_key = $this->get_option('transaction_key');
            $this->success_msg = $this->get_option('success_msg');
            $this->fail_msg = $this->get_option('fail_msg');
            $this->api_mode = $this->get_option('api_mode');
            if ($this->api_mode == 'TRUE') {
                $this->api_url = 'https://rpm.demo.e-xact.com/payment';
            } else {
                $this->api_url = 'https://checkout.e-xact.com/payment';
            }

            // Save settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable'),
                    'label' => __('Enable Chase E-xact Checkout'),
                    'type' => 'checkbox',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.'),
                    'default' => __('Chase E-xact Checkout')
                ),
                'description' => array(
                    'title' => __('Description'),
                    'type' => 'text',
                    'description' => __('This controls the description which the user sees during checkout.'),
                    'default' => __('Pay securely with credit or debit card through Chase E-xact.')
                ),
                'login_id' => array(
                    'title' => __('API Login ID'),
                    'type' => 'text',
                ),
                'transaction_key' => array(
                    'title' => __('API Transaction Key'),
                    'type' => 'text'
                ),
                'api_mode' => array(
                    'title' => __('API Mode'),
                    'type' => 'select',
                    'options' => array(
                        'FALSE' => 'Live Mode',
                        'TRUE' => 'Test Mode',
                        'powerpay' => 'PowerPay Payment Gateway Emulator'
                    )
                ),
                'success_msg' => array(
                    'title' => __('Transaction Success Message'),
                    'type' => 'textarea',
                    'description' => __('Message to be displayed on successful transaction.'),
                    'default' => __('Your payment has been processed, thank you.')
                ),
                'fail_msg' => array(
                    'title' => __('Transaction Failed Message'),
                    'type' => 'textarea',
                    'description' => __('Message to be displayed on failed transaction.'),
                    'default' => __('Your payment has been declined.')
                )
            );
        }

        public function receipt_page($order_id) {
            echo "<p>" . __('Thank you for your order, please click the button below to pay with Chase E-xact.') . "</p>";
            echo $this->generate_exact_form($order_id);
        }

        public function generate_exact_form($order_id) {
            global $woocommerce;
            $order = wc_get_order($order_id);
            $x_fp_sequence = rand(1, 1000);
            $x_fp_timestamp = time();
            $args = array(
                'x_login' => $this->login_id,
                'x_amount' => $order->get_total(),
                'x_invoice_num' => $order_id,
                'x_relay_response' => 'TRUE',
                'x_relay_url' => get_site_url() . '/wc-api' . get_class($this),
                'x_fp_sequence' => $x_fp_sequence,
                'x_fp_hash' => hash('md5', $this->login_id . '^' . $x_fp_sequence . '^' . $x_fp_timestamp . '^' . $order->get_total() . '^' . $this->transaction_key, false),
                'x_show_form' => 'PAYMENT_FORM',
                'x_test_request' => 'FALSE',
                'x_fp_timestamp' => $x_fp_timestamp,
                'x_first_name' => $order->get_billing_first_name(),
                'x_last_name' => $order->get_billing_last_name(),
                'x_company' => $order->get_billing_company(),
                'x_address' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
                'x_country' => $order->get_billing_country(),
                'x_state' => $order->get_billing_state(),
                'x_city' => $order->get_billing_city(),
                'x_zip' => $order->get_billing_postcode(),
                'x_phone' => $order->get_billing_phone(),
                'x_email' => $order->get_billing_email(),
                'x_ship_to_first_name' => $order->get_shipping_first_name(),
                'x_ship_to_last_name' => $order->get_shipping_last_name(),
                'x_ship_to_company' => $order->get_shipping_company(),
                'x_ship_to_state' => $order->get_shipping_state(),
                'x_ship_to_city' => $order->get_shipping_city(),
                'x_ship_to_zip' => $order->get_shipping_postcode(),
                // 'x_cancel_url' => $woocommerce->cart->get_checkout_url(),
                // 'x_cancel_url_text' => 'Cancel Payment'
            );
            $args_input_fields = array();
            foreach ($args as $key => $value) {
                $args_input_fields[] = "<input type='hidden' name='$key' value='$value'/>";
            }
            $html_form = "<form action='$this->api_url' method='post' id='exact-payment-form'>"
                . implode('', $args_input_fields)
                . "<input type='submit' class='button' id='submit-exact-payment-form' value='" . __('Pay via Chase E-xact') . "'/>"
                . "<a class='button cancel' href='" . $order->get_cancel_order_url() . "'>" . __('Cancel order and restore cart') . "</a>"
                . "</form>";
            return $html_form;
        }
    }
}
