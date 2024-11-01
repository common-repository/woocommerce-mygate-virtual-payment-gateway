<?php
/*
	Plugin Name: Mygate Payment Gateway
	Plugin URI: http://responsive.co.za
	Description: A payment gateway for South African payment system, MyGate. For WooCommerce V2+
	Version: 1.5
	Author: Andrew McElroy
	Author URI: http://responsive.co.za
	Requires at least: 3.1
	Tested up to: 3.2.1
*/

if ( ! function_exists( 'is_woocommerce_active' ) ) require_once( 'woo-includes/woo-functions.php' );

add_action( 'plugins_loaded', 'woocommerce_mygate_init', 0 );

function woocommerce_mygate_init () {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	require_once( plugin_basename( 'classes/gateway-mygate.php' ) );
} // End woocommerce_mygate_init()
?>
