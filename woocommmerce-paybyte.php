<?php
/*
Plugin Name: PayByte for WooCommerce
Plugin URI: https://wordpress.org/plugins/paybyte-for-woocommerce
Description: Enable your WooCommerce store to accept Crypto payments using PayByte Crypto payments processor.
Version: 1.0.2
Author: PayByte
Text Domain: PayByte.io
Author URI: https://github.com/paybyte-payment
Tested up to: 5.0.3

* WC requires at least: 2.4
* WC tested up to: 3.5.2

License:           Copyright 2019 PayByte, MIT License
License URI:       https://github.com/paybyte-payment/paybyte-woocommerce-plugin/blob/master/LICENSE
GitHub Plugin URI: https://github.com/paybyte-payment/paybyte-woocommerce-plugin
*/

// Exit if accessed directly
if (false === defined('ABSPATH'))
{
    exit;
}

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'paybyte_init_gateway_class');
function paybyte_init_gateway_class(){

	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// If we made it this far, then include our Gateway Class
	include_once( 'woocommerce-paybyte-gateway.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'add_custom_gateway_class' );
	function add_custom_gateway_class( $methods ) {
	    $methods[] = 'WC_Gateway_PayByte'; 
	    return $methods;
	}
}
 
// function to create the Table 				
function paybyte_create_table_on_install() {
   	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE `paybyte_payment` (
		  `id` mediumint(9) NOT NULL AUTO_INCREMENT,
		  `user_id` int(10) NOT NULL,
		  `order_id` int(10) NOT NULL,
		  `payment_address` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
		  `payment_id` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
		  `status` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
		  `callback_guid` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
		  `amount` FLOAT(10) NOT NULL,
		  `coin` varchar(10) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
		  `amount_received` FLOAT(10) NOT NULL,
		  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  PRIMARY KEY  (id)
		) $charset_collate;";
 
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	// Set hold stock time to false.
	update_option( 'woocommerce_hold_stock_minutes',''); 
}
// run the install scripts upon plugin activation
register_activation_hook(__FILE__,'paybyte_create_table_on_install');


// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'paybyte_action_links' );
function paybyte_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">Settings</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );	
}

?>