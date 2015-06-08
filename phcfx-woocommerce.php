<?php
/*
 * Plugin Name: PHC FX WooCommerce
 * Description: Easy integration between WordPress and your PHC Business FX installation.
 * Version: 1.0
 * Author: PHC Software, S.A.
 * Author URI: http://en.phc.pt
 */

//Prevent direct access data leaks
if ( ! defined( 'ABSPATH' ) ) { 
    exit;
}

// some generic definitions
define('PHCFXWOOCOMMERCE_PLUGIN', plugin_basename(__FILE__));
define('PHCFXWOOCOMMERCE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PHCFXWOOCOMMERCE_PLUGIN_NAME', basename(__FILE__, '.php'));
define('PHCFXWOOCOMMERCE_PLUGIN_URL', plugin_dir_url(__FILE__));

// load plugin main class
require_once(PHCFXWOOCOMMERCE_PLUGIN_DIR . 'class.PhcFxWoocommerce.php' );

// make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
  echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
  exit;
}

//Check if WooCommerce is active.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	// start the plugin of PHC FX
	PhcFxWoocommerce::self();	
}


