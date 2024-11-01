<?php
/*
Plugin Name: WPos Lite Version
Plugin URI: http://wpos.app
Description: Quick POS system for woocommerce. This is Lite Version of OpenPOS
Author: anhvnit@gmail.com
Author URI: http://openswatch.com/
Version: 2.1
WC requires at least: 2.6
WC tested up to: 4.8.0
Text Domain: wpos-lite
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/


if(!function_exists('is_plugin_active'))
{
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

if(!is_plugin_active( 'woocommerce-openpos/woocommerce-openpos.php' ))
{
    define('WPOSL_DIR',plugin_dir_path(__FILE__));
    define('WPOSL_URL',plugins_url('wpos-lite'));
    global $OPENPOS_SETTING;
    global $OPENPOS_CORE;
    require(WPOSL_DIR.'vendor/autoload.php');
    require_once( WPOSL_DIR.'lib/class-tgm-plugin-activation.php' );
    require_once( WPOSL_DIR.'includes/admin/Settings.php' );
    require_once( WPOSL_DIR.'lib/class-op-woo.php' );
    require_once( WPOSL_DIR.'lib/class-op-woo-cart.php' );
    require_once( WPOSL_DIR.'lib/class-op-woo-order.php' );
    require_once( WPOSL_DIR.'lib/class-op-session.php' );
    require_once( WPOSL_DIR.'lib/class-op-register.php' );
    require_once( WPOSL_DIR.'lib/class-op-warehouse.php' );
    require_once( WPOSL_DIR.'lib/class-op-stock.php' ); 
    require_once( WPOSL_DIR.'includes/Core.php' );
    require_once( WPOSL_DIR.'includes/admin/Admin.php' );
    global $barcode_generator;
    global $op_session;
    global $op_warehouse;
    global $op_register;
    global $op_stock;
    global $op_woo;
    global $op_woo_cart;
    global $op_woo_order;
    
    
    //check woocommerce active
    if(is_plugin_active( 'woocommerce/woocommerce.php' ))
    {
        $barcode_generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        $op_session = new WPOSL_Session();
        $op_woo = new WPOSL_Woo();
        $op_woo->init();
        $op_woo_cart = new WPOSL_Woo_Cart();
        $op_woo_order = new WPOSL_Woo_Order();
        $op_warehouse = new WPOSL_Warehouse();
        $op_register = new WPOSL_Register();
        
        $op_stock = new WPOSL_Stock();
        
        

        if(class_exists('TGM_Plugin_Activation'))
        {
            add_action( 'tgmpa_register', 'openpos_register_required_plugins' );


            function openpos_register_required_plugins() {
                /*
                * Array of plugin arrays. Required keys are name and slug.
                * If the source is NOT from the .org repo, then source is also required.
                */
                $plugins = array(

                    array(
                        'name'      => 'WooCommerce',
                        'slug'      => 'woocommerce',
                        'required'  => true,
                        //'version'            => '3.3.5',
                        'force_activation'   => true,
                        'force_deactivation' => true,
                    )

                );

                $config = array(
                    'id'           => 'openpos',                 // Unique ID for hashing notices for multiple instances of TGMPA.
                    'default_path' => '',                      // Default absolute path to bundled plugins.
                    'menu'         => 'tgmpa-install-plugins', // Menu slug.
                    'parent_slug'  => 'plugins.php',            // Parent menu slug.
                    'capability'   => 'manage_options',    // Capability needed to view plugin install page, should be a capability associated with the parent menu used.
                    'has_notices'  => true,                    // Show admin notices or not.
                    'dismissable'  => true,                    // If false, a user cannot dismiss the nag message.
                    'dismiss_msg'  => '',                      // If 'dismissable' is false, this message will be output at top of nag.
                    'is_automatic' => false,                   // Automatically activate plugins after installation or not.
                    'message'      => '',                      // Message to output right before the plugins table.
                );

                tgmpa( $plugins, $config );
            }
        }

        $OPENPOS_SETTING = new Openpos_Settings();
        $OPENPOS_CORE = new Openpos_Core();
        $OPENPOS_CORE->init();
        $tmp = new Openpos_Admin();
        $tmp->init();

        if(!class_exists('Openpos_Front'))
        {

            if(!class_exists('WC_Discounts'))
            {
                require( dirname(WPOSL_DIR).'/woocommerce/includes/class-wc-discounts.php' );
            }
            require( WPOSL_DIR.'lib/class-op-discounts.php' );

            require_once( WPOSL_DIR.'includes/front/Front.php' );
        }
        $tmp = new Openpos_Front();
        $tmp->initScripts();
        //register action on active plugin
        if(!function_exists('openpos_activate'))
        {
            function openpos_activate() {
                update_option('_openpos_product_version_0',time());
                // Activation code here...
            }
        }
        load_plugin_textdomain( 'openpos', null,  'wpos-lite/languages' );
        register_activation_hook( __FILE__, 'openpos_activate' );
    }


}

