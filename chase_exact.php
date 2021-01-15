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

            // Save settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
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
                ),
                'api_mode' => array(
                    'title' => __('API Mode'),
                    'type' => 'select',
                    'options' => array(
                        'false' => 'Live Mode',  // TODO: Does 'false' have to be a string or can I change to bool?
                        'true' => 'Test Mode',  // TODO: Does 'true' have to be a string or can I change to bool?
                        'powerpay' => 'PowerPay Payment Gateway Emulator'  // TODO: Why is this necessary?
                    )
                )
            );
        }
    }
}
