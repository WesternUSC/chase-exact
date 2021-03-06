<?php
/*
 * Plugin Name: Chase E-xact Payment Gateway for WooCommerce
 * Description: Extends WooCommerce to process payments with Chase E-xact gateway.
 * Author: WesternUSC
 * Author URI: https://github.com/WesternUSC
 * Version: 1.0.0
 * Requires at least: 5.6
 * Requires PHP: 7.0
 * WC requires at least: 4.9
 * WC tested up to: 5.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */


add_filter('woocommerce_payment_gateways', 'add_chase_exact');
function add_chase_exact($methods)
{
    $methods[] = 'WC_Chase_Exact_Gateway';
    return $methods;
}

add_action('plugins_loaded', 'init_chase_exact');
function init_chase_exact()
{
    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_Chase_Exact_Gateway extends WC_Payment_Gateway
    {
        public function __construct()
        {
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

            // Define webhooks
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            add_action('woocommerce_api_wc_chase_exact_gateway', array($this, 'process_exact_response'));
        }

        /** Provides options for plugin settings in admin panel. */
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable'),
                    'label' => __('Enable Chase E-xact Checkout for WooCommerce'),
                    'type' => 'checkbox',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title'),
                    'type' => 'text',
                    'description' => __('Controls the title presented to the customer during checkout.'),
                    'default' => __('Chase E-xact Checkout for WooCommerce')
                ),
                'description' => array(
                    'title' => __('Description'),
                    'type' => 'text',
                    'description' => __('Controls the description presented to the user during checkout.'),
                    'default' => __('Pay securely with credit or debit card through Chase E-xact.')
                ),
                'login_id' => array(
                    'title' => __('API Login ID'),
                    'type' => 'text',
                    'description' => __('Provided by Chase E-xact. Required for verification purposes on their server.')
                ),
                'transaction_key' => array(
                    'title' => __('API Transaction Key'),
                    'type' => 'text',
                    'description' => __('Provided by Chase E-xact. Required for verification purposes on their server.')
                ),
                'api_mode' => array(
                    'title' => __('API Mode'),
                    'type' => 'select',
                    'options' => array(
                        'FALSE' => 'Live Mode',
                        'TRUE' => 'Test Mode',
                    )
                ),
                'success_msg' => array(
                    'title' => __('Transaction Success Message'),
                    'type' => 'textarea',
                    'description' => __('Message displayed to customer for a successful transaction.'),
                    'default' => __('Success.')
                ),
                'fail_msg' => array(
                    'title' => __('Transaction Failed Message'),
                    'type' => 'textarea',
                    'description' => __('Message displayed to customer for a failed transaction.'),
                    'default' => __("Your payment has been declined. Please try again by clicking 'Pay' above, and verify payment details are correct.")
                )
            );
        }

        /** Sends customer to receipt page after placing order. */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        /** Generates HTML form with payment details to be sent to Chase E-xact */
        public function generate_exact_form($order_id)
        {
            global $woocommerce;
            $order = wc_get_order($order_id);
            $x_fp_sequence = rand(1, 1000);
            $x_fp_timestamp = time();
            $args = array(
                'x_login' => $this->login_id,
                'x_amount' => $order->get_total(),
                'x_invoice_num' => $order_id,
                'x_relay_response' => 'TRUE',
                'x_relay_url' => get_site_url() . '/wc-api/' . get_class($this),
                'x_fp_sequence' => $x_fp_sequence,
                'x_fp_hash' => hash_hmac('md5', $this->login_id . '^' . $x_fp_sequence . '^' . $x_fp_timestamp . '^' . $order->get_total() . '^', $this->transaction_key, false),
                'x_show_form' => 'PAYMENT_FORM',
                'x_test_request' => $this->api_mode,
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
            );
            $args_input_fields = array();
            foreach ($args as $key => $value) {
                $args_input_fields[] = "<input type='hidden' name='$key' value='$value'/>";
            }
            $html_form = "<form action='$this->api_url' method='post' id='exact-payment-form'>"
                . implode('', $args_input_fields)
                . "<input type='submit' style='margin: 1rem 1rem 1rem 0;' class='button' id='submit-exact-payment-form' value='" . __('Pay via Chase E-xact') . "'/>"
                . "<a class='button cancel' href='" . $order->get_cancel_order_url() . "'>" . __('Cancel order and restore cart') . "</a>"
                . "</form>";
            return $html_form;
        }

        /** Extends receipt page with custom details. */
        public function receipt_page($order_id)
        {
            echo "<h3>" . __('Please click below to pay or cancel your order.') . "</h3>";
            echo $this->generate_exact_form($order_id);
        }

        /** Handles response from Chase E-xact. */
        public function process_exact_response()
        {
            global $woocommerce;
            if (!empty($_POST['x_response_code']) && !empty($_POST['x_invoice_num'])) {
                try {
                    $order = wc_get_order($_POST['x_invoice_num']);
                    if ($_POST['x_response_code'] == 1) {
                        $order->payment_complete();
                        $order->add_order_note($this->success_msg . " Transaction ID: " . $_POST['x_trans_id']);
                    } else {
                        $order->update_status("failed");
                        $order->add_order_note($this->fail_msg);
                    }
                    $this->redirect(get_site_url() . "/checkout/order-received/" . $order->get_id() . "/?key=" . $order->get_order_key());
                } catch (Exception $exception) {
                    $this->redirect(get_site_url() . "/checkout/order-received?msg=Unknown_error_occured");
                    exit;
                }
            } else {
                $this->redirect(get_site_url() . "/checkout/order-received?msg=Unknown_error_occured");
            }
            exit;
        }

        public function redirect($url)
        {
            echo "<html><head><script>window.location='{$url}';</script></head></html>";
        }

        /** Extends thank you page with custom details. */
        public function thankyou_page($order_id)
        {
            $order = wc_get_order($order_id);
            if ($order->get_status() == 'completed' || $order->get_status() == 'processing') {
                echo "<h2 style='color: #46A93F;'>" . $this->success_msg . "</h2>";
            } else {
                echo "<h2 style='color: #FF0000;'>" . $this->fail_msg . "</h2>";
            }
        }
    }
}
