<?php
/*
 * Plugin Name: WooCommerce Chase E-xact Payment Gateway
 * Description: Process payments with Chase E-xact gateway.
 * Author: Nikolas North (WesternUSC)
 * Author URI: https://github.com/WesternUSC
 * Version: 1.0.0
 */

// Register Chase_Exact class as WooCommerce payment gateway
add_filter('woocommerce_payment_gateways', 'add_chase_exact');
function add_chase_exact($methods) {
    $methods[] = 'WC_Chase_Exact';
    return $methods;
}

add_action('plugins_loaded', 'init_chase_exact');
function init_chase_exact() {
    class WC_Chase_Exact_Gateway extends WC_Payment_Gateway {
        // TODO
    }
}
