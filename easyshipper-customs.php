<?php
/*
Plugin Name: EasyShipper Customs
Plugin URI: http://github.com/raafirivero/easyshipper
Description: Provides an integration for EasyPost for WooCommerece, now with support for Customs forms.
Version: 0.2.1
Author: Sean Voss and Raafi Rivero
Author URI: http://raafirivero.com

*/

/*
 * Title   : EasyPost Shipping with Customs for WooCommerce
 * Author  : Sean Voss + Raafi Rivero
 * Url     : http://raafirivero.com
 * License : http://opensource.org/licenses/MIT
 */

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    // Put your plugin code here

   // Order of Plugin Loading Requires this line, should not be necessary
   require_once (dirname(__FILE__) .'/../woocommerce/woocommerce.php');

    if (class_exists('WC_Shipping_Method'))
    {
      include_once('easypost_shipping.php');
      
      // Create boxes for Customs info on product pages
      include_once('customs-meta.php');

    }



add_action( 'add_meta_boxes', 'es_add_boxes');

function es_add_boxes(){

 add_meta_box( 'easypost_data', __( 'EasyPost', 'woocommerce' ), 'woocommerce_easypost_meta_box', 'shop_order', 'normal', 'low' );

}

function woocommerce_easypost_meta_box($post)
{


  print sprintf("<a href='%2\$s' style='text-align:center;display:block;'><img style='max-width:%1\$s' src='%2\$s' ></a>",'450px', get_post_meta( $post->ID, 'easypost_shipping_label', true));

}

}

