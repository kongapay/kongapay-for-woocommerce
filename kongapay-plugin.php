<?php
/*
 * Plugin Name: KongaPay
 * Plugin URI: https://www.kongapay.com/
 * Description: KongaPay Payment Gateway makes it easy for you to accept payment from your customers. Enable payment on your website with Cards, Bank Accounts, KongaPay Wallet, USSD, Visa QR and Pay Attitude.
 * Author: KongaPay Developers
 * Author URI: https://www.kongapay.com
 * Author Email: developers@kongapay.com
 * Version: 1.0.0
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * WC requires at least: 3.0.0
 * WC tested up to: 3.4.4
 */

define( 'WC_KONGAPAY_VERSION', '1.0.0' );

add_filter( 'woocommerce_payment_gateways', 'kongapay_add_gateway_class' );

function kongapay_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_KongaPay_Gateway';
    return $gateways;
}


add_action( 'plugins_loaded', 'kongapay_init_gateway_class' );

function kongapay_init_gateway_class() {

    require_once dirname( __FILE__ ) . "/includes/class.kongapay.php";

}
