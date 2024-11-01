<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * Created by PhpStorm.
 * User: anhvnit
 * Date: 7/26/16
 * Time: 23:32
 */
use Carbon\Carbon;
class Openpos_Admin{
    private $settings_api;
    public $core;
    public $_filesystem;
    private $_session;
    public function __construct()
    {
        global $OPENPOS_SETTING;
        global $OPENPOS_CORE;
        global $op_session;
        $this->_session = $op_session;
        $this->settings_api = $OPENPOS_SETTING;
        $this->core = $OPENPOS_CORE;
        if(!class_exists('WP_Filesystem_Direct'))
        {
            require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
            require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
        }
        $this->_filesystem = new WP_Filesystem_Direct(false);

    }

    public function init()
    {
        add_action( 'admin_notices',array($this, 'admin_notice') );
        add_action( 'admin_init', array($this, 'admin_init') );
        add_action( 'init', array($this, '_short_code') );
        add_action('admin_enqueue_scripts', array($this,'admin_global_style'));
        //add_action( 'init', array($this,'create_store_taxonomies'), 0 );

        add_filter( "manage_edit-store_columns", array($this,'store_setting_column_header'), 10);
        add_action( "manage_store_custom_column",array($this,'store_setting_column_content'), 10, 3);
        add_action( 'admin_menu', array($this,'pos_admin_menu'),1 );


        

        //ajax

        add_action( 'wp_ajax_op_products', array($this,'products') );
       

        add_action( 'wp_ajax_op_transactions', array($this,'transactions') );
        add_action( 'wp_ajax_op_orders', array($this,'orders') );

        // Admin bar menus
        if ( apply_filters( 'woocommerce_show_admin_bar_visit_store', true ) ) {
            add_action( 'admin_bar_menu', array( $this, 'admin_bar_menus' ), 31 );
        }

        add_action( 'wp_ajax_op_cashier', array($this,'getUsers') );
        add_action( 'wp_ajax_save_cashier', array($this,'save_cashier') );

        add_action( 'wp_ajax_print_barcode', array($this,'print_bacode') );
        add_action( 'wp_ajax_print_receipt', array($this,'print_receipt') );
        add_action( 'wp_ajax_admin_openpos_data', array($this,'dashboard_data') );
        add_action( 'wp_ajax_admin_openpos_reset_balance', array($this,'reset_balance') );

        add_action( 'wp_ajax_admin_openpos_update_product_grid', array($this,'update_product_grid') );
        add_action( 'wp_ajax_admin_openpos_update_transaction_grid', array($this,'update_transaction_grid') );

        add_action( 'wp_ajax_admin_openpos_session_unlink', array($this,'session_unlink') );

        
        add_action( 'wp_ajax_op_ajax_category', array($this,'op_ajax_category') );
        add_action( 'wp_ajax_op_ajax_order_status', array($this,'op_ajax_order_statuses') );
       
        add_filter('pre_update_option_openpos_general',array($this,'pre_update_option_openpos_general'),10,3);

    }
    function pre_update_option_openpos_general($value, $old_value, $option){
        $se_number = isset($value['pos_sequential_number']) ? (int)$value['pos_sequential_number'] : 0;
        $current_order_number = get_option('_op_wc_custom_order_number',0);
        if($se_number && $current_order_number)
        {
            if($se_number > $current_order_number)
            {
                update_option('_op_wc_custom_order_number',$se_number);
            }else{
                //$value['pos_sequential_number'] = $current_order_number;
            }
        }
        return $value;
    }
    function admin_init() {

        add_filter('plugin_row_meta',array($this,'plugin_row_meta'),100,3);
        //set the settings
        $this->settings_api->set_sections( $this->get_settings_sections() );
        $this->settings_api->set_fields( $this->get_settings_fields() );
        //initialize settings
        $this->settings_api->admin_init();

        $this->admin_notice_init();
    }
    function plugin_row_meta($plugin_meta, $plugin_file, $plugin_data){
        $plugin = isset($plugin_data['TextDomain']) ? $plugin_data['TextDomain']:'';

        if($plugin == 'wpos-lite')
        {
           $plugin_meta[] = '<a target="_blank" href="'.esc_url('https://codecanyon.net/item/openpos-a-complete-pos-plugins-for-woocomerce/22613341').'">'.__('Buy Premium Version','wpos-lite').'</a>';
        }
        return $plugin_meta;
    }

    function get_default_value($key)
    {
        $file_name = $key.'.txt';
        $file_path = rtrim(WPOSL_DIR,'/').'/default/'.$file_name;
        if($this->_filesystem->is_file($file_path))
        {
            return $this->_filesystem->get_contents($file_path);
        }else{
            return '';
        }
    }
    function get_settings_sections() {
        $sections = array(
            array(
                'id'    => 'openpos_general',
                'title' => __( 'General Settings', 'wpos-lite')
            ),
            array(
                'id'    => 'openpos_payment',
                'title' => __( 'Payment Settings', 'wpos-lite')
            ),
            array(
                'id'    => 'openpos_shipment',
                'title' => __( 'Shipping Settings', 'wpos-lite')
            ),
            array(
                'id'    => 'openpos_label',
                'title' => __( 'Barcode Label Sheet Settings', 'wpos-lite')
            ),
            array(
                'id'    => 'openpos_receipt',
                'title' => __( 'Print Receipt Settings', 'wpos-lite')
            ),
            array(
                'id'    => 'openpos_pos',
                'title' => __( 'POS Layout Setting', 'wpos-lite')
            )
        );
        return $sections;
    }

    function get_settings_fields() {
        global $op_woo;
        $payment_gateways = WC()->payment_gateways->payment_gateways();
        $payment_options = array();

        $openpos_type = $this->settings_api->get_option('openpos_type','openpos_pos');

        foreach ($payment_gateways as $code => $p)
        {
            $payment_options[$code] = $p->title;
        }
        $shipping_options = array();
        $shipping_methods = WC()->shipping()->get_shipping_methods();
        foreach ($shipping_methods as $shipping_method)
        {
            $code = $shipping_method->id;
            $title = $shipping_method->method_title;
            if(!$title)
            {
                $title = $code;
            }
            $shipping_options[$code] = $title;
        }
        

        $setting_pos_discount_tax_class = $this->settings_api->get_option('pos_discount_tax_class','openpos_general');

        $addition_general_setting = array();
        $wc_order_status = wc_get_order_statuses();
        

       
       
        $pos_tax_class = $this->settings_api->get_option('pos_tax_class','openpos_general');

        $pos_custom_item = $this->settings_api->get_option('pos_allow_custom_item','openpos_pos');

       

        if($pos_custom_item == 'yes' && $pos_tax_class == 'op_productax')
        {
            $pos_custom_item_tax_class = $this->settings_api->get_option('pos_custom_item_tax_class','openpos_pos');

            $all_tax_classes = wc_get_product_tax_class_options();

            
            if( $pos_custom_item_tax_class != 'op_notax')
            {

                $rates = $op_woo->getTaxRates($pos_custom_item_tax_class);

                $rate_options = array();
                foreach($rates as $rate_id => $rate)
                {
                    $rate_options[''.$rate_id] = $rate['label'].' ('.$rate['rate'].'%)';
                }

                if( !empty($rate_options))
                {
                    $rate_options[0] = __( 'Choose tax rate', 'wpos-lite');
                    
                }

            }


        }





        if($pos_tax_class != 'op_productax')
        {


            if($pos_tax_class != 'op_notax')
            {
                $rates = $op_woo->getTaxRates($pos_tax_class);

                $rate_options = array();
                $default_rate_option = 0;
                foreach($rates as $rate_id => $rate)
                {
                    $rate_options[$rate_id] = $rate['label'].' ('.$rate['rate'].'%)';
                }
                if(!empty($rate_options))
                {
                    $default_rate_option = max(array_keys($rate_options));

                    
                }


            }

        }else{





            if($setting_pos_discount_tax_class != 'op_notax')
            {
                $rates = $op_woo->getTaxRates($setting_pos_discount_tax_class);

                $rate_options = array();
                foreach($rates as $rate_id => $rate)
                {
                    $rate_options[''.$rate_id] = $rate['label'].' ('.$rate['rate'].'%)';
                }

                if( !empty($rate_options))
                {
                    $rate_options[0] = __( 'Choose tax rate', 'wpos-lite');
                    
                }
            }

        }

        $dashboard_display_options = array(
            'board' => __('New DashBoard','wpos-lite'),
            'product' => __('Products','wpos-lite'),
            'category' => __('Categories','wpos-lite'),
        );

        if($openpos_type =='restaurant')
        {
            $dashboard_display_options['table'] = __('Tables','wpos-lite');
        }

       

        $barcode_key_options  = apply_filters('op_barcode_key_setting',array(
            
            'post_id' => __('Product Id','wpos-lite'),
            '_sku' => __('Product Sku','wpos-lite')
        ));


        $settings_fields = array(
            'openpos_general' => array(
            
                array(
                    'name'    => 'pos_order_status',
                    'label'   => __( 'POS Order Status', 'wpos-lite'),
                    'desc'    => __( 'status for those order created by POS', 'wpos-lite'),
                    'type'    => 'select',
                    'default' => 'wc-completed',
                    'options' =>  $wc_order_status
                ),
                array(
                    'name'    => 'pos_tax_class',
                    'label'   => __( 'Pos Tax Class', 'wpos-lite'),
                    'desc'    => __( 'Tax Class assign for POS system. Require refresh product list to take effect.', 'wpos-lite'),
                    'type'    => 'select',
                    'default' => 'op_notax',
                    'options' =>  array(
                        'op_productax' => 'Use Product Tax Class',
                        'op_notax'  => 'No Tax'
                    )
                )
            ),
            'openpos_payment' => array(
                array(
                    'name'    => 'payment_methods',
                    'label'   => __( 'POS Addition Payment Methods', 'wpos-lite'),
                    'desc'    => __( 'Payment methods for POS beside cash(default)', 'wpos-lite'),
                    'type'    => 'multicheck',
                    'default' => 'op_notax',
                    'options' => $payment_options
                ),
            ),
            'openpos_shipment' => array(
                array(
                    'name'    => 'shipping_methods',
                    'label'   => __( 'POS Addition Shipping Methods', 'wpos-lite'),
                    'desc'    => __( 'Shipping methods for POS ', 'wpos-lite'),
                    'type'    => 'multicheck',
                    'default' => '',
                    'options' => $shipping_options
                ),
            ),
            'openpos_label' => array(
                array(
                    'name'              => 'barcode_meta_key',
                    'label'             => __( 'Barcode Meta Key', 'wpos-lite'),
                    'desc'    => __( 'Barcode field . Make sure the data is unique on meta key you are selected', 'wpos-lite'),
                    'type'              => 'select',
                    'default' => '_sku',
                    'options' => $barcode_key_options
                ),
                array(
                    'name'              => 'unit',
                    'label'             => __( 'Unit', 'wpos-lite'),
                    'type'              => 'select',
                    'default' => 'in',
                    'options' => array(
                        'in' => 'Inch',
                        'mm' => 'Millimeter'
                    )
                ),
                array(
                    'name'              => 'heading-s',
                    'desc'              => __( '<h2>Sheet Setting</h2>', 'wpos-lite'),
                    'type'              => 'html'
                ),

                array(
                    'name'              => 'sheet_width',
                    'label'             => __( 'Sheet Width', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '8.5',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'sheet_height',
                    'label'             => __( 'Sheet Height', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '11',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'sheet_vertical_space',
                    'label'             => __( 'Vertical Space', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '0',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'sheet_horizontal_space',
                    'label'             => __( 'Horizontal Space', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '0.125',
                    'sanitize_callback' => 'sanitize_text_field'
                ),

                array(
                    'name'              => 'sheet_margin_top',
                    'label'             => __( 'Margin Top', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '0.5',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'sheet_margin_right',
                    'label'             => __( 'Margin Right', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '0.188',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'sheet_margin_bottom',
                    'label'             => __( 'Margin Bottom', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '0.5',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'sheet_margin_left',
                    'label'             => __( 'Margin Left', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '0.188',
                    'sanitize_callback' => 'sanitize_text_field'
                ),


                array(
                    'name'              => 'barcode_label_width',
                    'label'             => __( 'Label Width', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '2.625',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'barcode_label_height',
                    'label'             => __( 'Label Height', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '1',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'barcode_label_padding_top',
                    'label'             => __( 'Padding Top', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '0.1',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'barcode_label_padding_right',
                    'label'             => __( 'Padding Right', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '0.1',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'barcode_label_padding_bottom',
                    'label'             => __( 'Padding Bottom', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '0.1',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'barcode_label_padding_left',
                    'label'             => __( 'Padding Left', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '0.1',
                    'sanitize_callback' => 'sanitize_text_field'
                ),

                array(
                    'name'              => 'barcode_label_template',
                    'label'             => __( 'Label Template', 'wpos-lite'),
                    'desc'              => __( 'use [barcode with="" height=""] to adjust barcode image, [op_product attribute="attribute_name"] with attribute name: <b>name, price ,regular_price, sale_price, width, height,length,weight</b> and accept html,inline style css string', 'wpos-lite'),
                    'default'           => '[op_product attribute="name"][barcode][op_product attribute="barcode"]',
                    'type'              => 'wysiwyg'
                ),

                array(
                    'name'              => 'heading',
                    'desc'              => __( '<h2>Barcode Setting</h2>', 'wpos-lite'),
                    'type'              => 'html'
                ),


                array(
                    'name'              => 'barcode_mode',
                    'label'             => __( 'Mode', 'wpos-lite'),
                    'type'              => 'select',
                    'default' => 'code_128',
                    'options' => array(
                        'code_128' => 'Code 128',
                        'ean_13' => 'EAN-13',
                        'code_39' => 'Code-39',
                        'qrcode' => __( 'QRCode', 'wpos-lite'),
                    )
                ),
                array(
                    'name'              => 'barcode_width',
                    'label'             => __( 'Width', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '2.625',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'barcode_height',
                    'label'             => __( 'Height', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '1',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
            ),

            'openpos_receipt' => array(

                array(
                    'name'              => 'receipt_width',
                    'label'             => __( 'Receipt Width', 'wpos-lite'),
                    'desc'              => __( 'inch', 'wpos-lite'),
                    'type'              => 'text',
                    'default'           => '2.28',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'receipt_padding_top',
                    'label'             => __( 'Padding Top', 'wpos-lite'),
                    'desc'              => __( 'inch', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '0',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'receipt_padding_right',
                    'label'             => __( 'Padding Right', 'wpos-lite'),
                    'desc'              => __( 'inch', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '0',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'receipt_padding_bottom',
                    'label'             => __( 'Padding Bottom', 'wpos-lite'),
                    'desc'              => __( 'inch', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '0',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                array(
                    'name'              => 'receipt_padding_left',
                    'label'             => __( 'Padding Left', 'wpos-lite'),
                    'desc'              => __( 'inch', 'wpos-lite'),
                    'type'              => 'number',
                    'default'           => '0',
                    'sanitize_callback' => 'sanitize_text_field'
                ),

                array(
                    'name'              => 'receipt_template_header',
                    'label'             => __( 'Receipt Template Header', 'wpos-lite'),
                    'desc'              => __( 'use [payment_method], [customer_name], [customer_phone],[sale_person], [created_at], [order_number],[order_number_format],[order_note],[order_qrcode width="_number_" height="_number_"],[order_barcode  width="_number_" height="_number_"], [customer_email],[op_warehouse field="_fiel_name"] - (_fiel_name : name, address, city, postal_code,country,phone,email), [op_register field="name"] shortcode to adjust receipt information, accept html string', 'wpos-lite'),
                    'type'              => 'wysiwyg',
                    'default'           => $this->get_default_value('receipt_template_header')
                ),
                array(
                    'name'              => 'receipt_template_footer',
                    'label'             => __( 'Receipt Template Footer', 'wpos-lite'),
                    'desc'              => __( 'use [payment_method],[customer_name], [customer_phone], [sale_person], [created_at], [order_number],[order_number_format],[order_qrcode width="_number_" height="_number_"],[order_barcode  width="_number_" height="_number_"],[order_note], [customer_email], [op_warehouse field="_fiel_name"] - (_fiel_name : name, address, city, postal_code,country,phone,email), [op_register field="name"] shortcode to adjust receipt information, accept html string', 'wpos-lite'),
                    'type'              => 'wysiwyg',
                    'default'           => $this->get_default_value('receipt_template_footer')
                ),
                array(
                    'name'              => 'receipt_css',
                    'label'             => __( 'Receipt Style', 'wpos-lite'),
                    'desc'              => sprintf('<a  target="_blank" href="'.admin_url('admin-ajax.php?action=print_receipt').'">%s</a>',__( 'click here to preview receipt', 'wpos-lite')),
                    'type'              => 'textarea_code',
                    'default'           => $this->get_default_value('receipt_css'),
                ),
            ),
            'openpos_pos' => array(
                
                
                array(
                    'name'              => 'pos_categories',
                    'label'             => __( 'POS Category', 'wpos-lite'),
                    'desc'              => __( 'List of Categories display on POS panel. Enter keyword to search, this field is autocomplete', 'wpos-lite'),
                    'default'           => '',
                    'type'              => 'category_tags'
                ),
                array(
                    'name'              => 'pos_money',
                    'label'             => __( 'POS Money List', 'wpos-lite'),
                    'desc'              => __( 'List of money values in your pos. Separate by "|" character. Example: 10|20|30', 'wpos-lite'),
                    'default'           => '',
                    'type'              => 'text'
                ),
                array(
                    'name'              => 'pos_default_open_cash',
                    'label'             => __( 'Open Cash When Login', 'wpos-lite'),
                    'desc'              => __( 'Open Cash Adjustment Popup when login to POS','wpos-lite'),
                    'default'           => 'no',
                    'type'              => 'select',
                    'options' => array(
                        'yes' => __('Yes','wpos-lite'),
                        'no' => __('No','wpos-lite'),
                    )
                ),
                array(
                    'name'              => 'search_type',
                    'label'             => __( 'Search Display Type', 'wpos-lite'),
                    'desc'              => __( 'Layout of result return by search product input ','wpos-lite'),
                    'default'           => 'suggestion',
                    'type'              => 'select',
                    'options' => array(
                        'suggestion' => __('Auto Suggestion','wpos-lite'),
                        'grid' => __('Product Grid Display','wpos-lite'),
                    )
                )


            )
        );
        $addition_general_setting = apply_filters('op_addition_general_setting',$addition_general_setting);
        $settings_fields['openpos_payment'] = array_merge($settings_fields['openpos_payment'],$addition_general_setting);
        return $settings_fields;
    }

    public function products()
    {
        $rows = array();
        $current = isset($_REQUEST['current']) ? intval($_REQUEST['current']) : 1;
        $sort  = isset($_REQUEST['sort']) ? sanitize_text_field($_REQUEST['sort']) : false;
        $searchPhrase  = $_REQUEST['searchPhrase'] ? sanitize_text_field($_REQUEST['searchPhrase']) : false;
        $sortBy = 'date';
        $order = 'DESC';
        if($sort && is_array($sort))
        {
            $key = array_keys($sort);

            $sortBy = end($key);
            if($sortBy == 'id')
            {
                $sortBy = 'ID';
            }
            $order = end($sort);
        }

        $rowCount = $_REQUEST['rowCount'] ? intval($_REQUEST['rowCount']) : get_option( 'posts_per_page' );
        $offet = ($current -1) * $rowCount;
        $ignores = array();
        $args = array(
            'posts_per_page'   => $rowCount,
            'offset'           => $offet,
            'current_page'           => $current,
            'category'         => '',
            'category_name'    => '',
            'orderby'          => $sortBy,
            'order'            => $order,
            'exclude'          => $ignores,
            'post_type'        => $this->core->getPosPostType(),
            'post_status'      => 'publish',
            'suppress_filters' => false
        );
        if($searchPhrase)
        {
            $args['s'] = $searchPhrase;
        }
        $posts = $this->core->getProducts($args);
        $posts_array = $posts['posts'];
        $total = $posts['total'];
        $fields = array('post_title');

        foreach($posts_array as $post)
        {
            if(is_a($post, 'WP_Post'))
            {
                $product_id = $post->ID;
            }else{
                $product_id = $post->get_id();
                $post = get_post($product_id);
            }
            $_product = wc_get_product($product_id);
            if(!$_product)
            {
                continue;
            }
            $type = $_product->get_type();
            $allow_types = $this->core->getPosProductTypes();
            if(in_array($type,$allow_types))
            {
                $tmp = array();
                $thumb = '';
                if( wc_placeholder_img_src() ) {
                    $thumb = wc_placeholder_img();
                }
                $parent_product = false;
                foreach($fields as $field)
                {
                    $tmp[$field] = $post->$field;
                }
                if($tid = get_post_thumbnail_id($post->ID))
                {
                    $props = wc_get_product_attachment_props( get_post_thumbnail_id($product_id), $post );
                    $thumb = get_the_post_thumbnail( $post->ID, 'shop_thumbnail', array(
                        'title'  => $props['title'],
                        'alt'    => $props['alt'],
                    ) );
                }
                $tmp['action'] = '<a href="'.get_edit_post_link($product_id).'">'.__('edit','wpos-lite').'</a>';

                if($type == 'variation')
                {
                   $parent_id = $post->post_parent;
                    $parent_product = wc_get_product($parent_id);
                    if($tid = get_post_thumbnail_id($parent_id))
                    {
                        $props = wc_get_product_attachment_props( get_post_thumbnail_id($parent_id), $parent_product );
                        $thumb = get_the_post_thumbnail( $parent_id, 'shop_thumbnail', array(
                            'title'  => $props['title'],
                            'alt'    => $props['alt'],
                        ) );
                    }
                    $tmp['action'] = '<a href="'.get_edit_post_link($parent_id).'">'.__('edit','wpos-lite').'</a>';

                }
                $tmp['action'] .= '<a href="'.admin_url( 'admin-ajax.php?action=print_barcode&id='.$product_id ).'" target="_blank" class="print-barcode-product-btn">Print Barcode</a>';
                $tmp['action'] = '<div class="action-row">'.$tmp['action'].'</div>';
                $tmp['regular_price'] = $_product->get_regular_price();
                $tmp['sale_price'] = $_product->get_sale_price();
                $price = $_product->get_price();
                $tmp['price'] = $price;
                $barcode = $this->core->getBarcode($product_id);
                $tmp['barcode'] = '<input type="text" name="barcode['.$product_id.']" class="form-control" disabled value="'.$barcode.'">';


                $sub_title = '';
                if($_product->get_type() == 'variation')
                {
                    $variation_attributes = $_product->get_attributes();
                    $sub_title = '<p>'.implode(',',$variation_attributes).'</p>';
                }
                $tmp['post_title'] .= $sub_title;

                if(!$price)
                {
                    $price = 0;
                }

                $tmp['formatted_price'] = wc_price($price);
                $qty = $_product->get_stock_quantity();
                $manage_stock = $_product->get_manage_stock();
                if($manage_stock)
                {
                    $tmp['qty'] = '<div class="col-xs-6 pull-left"><input class="form-control"  disabled name="qty['.$product_id.']" type="number" value="'.$qty.'" /></div>';

                }else{
                    $tmp['qty'] = 'Unlimited';
                }
                $tmp['id'] = $product_id;

                $tmp['product_thumb'] = $thumb;

                $rows[] = $tmp;

            }


        }


        $result = array(
            'current' => $current,
            'rowCount' => $rowCount,
            'rows' => $rows,
            'total' => $total

        );
        echo json_encode($result);
        exit;
    }

  

    public function transactions(){
        global $op_register;
        global $op_warehouse;

        $rows = array();

        $current = isset($_REQUEST['current']) ? intval($_REQUEST['current']) : 1;

        $sort  = isset($_REQUEST['sort']) ? sanitize_text_field($_REQUEST['sort']) : false;
        $warehouse_id  = isset($_REQUEST['warehouse']) ? intval($_REQUEST['warehouse']) : 0;
        $register_id  = isset($_REQUEST['register']) ? intval($_REQUEST['register']) : 0;

        $searchPhrase  = $_REQUEST['searchPhrase'] ? sanitize_text_field($_REQUEST['searchPhrase']) : false;
        $sortBy = 'date';
        $order = 'DESC';
        if($sort && is_array($sort))
        {
            $key = array_keys($sort);

            $sortBy = end($key);
            if($sortBy == 'id')
            {
                $sortBy = 'ID';
            }
            $order = end($sort);
        }


        $rowCount = $_REQUEST['rowCount'] ? intval($_REQUEST['rowCount']) : get_option( 'posts_per_page' );

        $offet = ($current -1) * $rowCount;


        $args = array(
            'posts_per_page'   => $rowCount,
            'offset'           => $offet,
            'category'         => '',
            'category_name'    => '',
            'orderby'          => $sortBy,
            'order'            => $order,
            'post_type'        => array('op_transaction'),
            'post_status'      => 'any',
            'suppress_filters' => false
        );
        $meta_query = array();

        if($register_id)
        {
            $register_meta_key = $op_register->get_transaction_meta_key();
            $meta_query[] = array(
                    'key'     => $register_meta_key,
                    'value'   => $register_id,
                    'compare' => '=',
                );
        }
        if($warehouse_id)
        {
            $warehouse_meta_key = $op_warehouse->get_transaction_meta_key();
            $meta_query[] = array(
                'key'     => $warehouse_meta_key,
                'value'   => $warehouse_id,
                'compare' => '=',
            );
        }
        if(!empty($meta_query))
        {
            $args['meta_query'] = $meta_query;
        }

        if($searchPhrase)
        {
            $args['s'] = $searchPhrase;
        }
        $get_posts = new WP_Query($args);
        $posts = array('total'=>$get_posts->found_posts,'posts' => $get_posts->get_posts());

        $posts_array = $posts['posts'];
        $total = $posts['total'];

        $cashdrawer_key = $op_register->get_transaction_meta_key();
        foreach($posts_array as $post)
        {
            $id = $post->ID;
            $user_id = get_post_meta($id,'_user_id',true);
            $register = 'Default';
            $name = 'Unknown';
            if($register_id = get_post_meta($id,$cashdrawer_key,true))
            {
                $register_details = $op_register->get($register_id);
                if($register_details && isset($register_details['name']))
                {
                    $register = $register_details['name'];
                }

            }
            if($user_id)
            {
                $user = get_user_by('ID',$user_id);
                $name = $user->display_name;
            }
            $method_code = get_post_meta($id,'_payment_code',true);
            $method_name = get_post_meta($id,'_payment_name',true);
            if(!$name)
            {
                $method_name = $method_code;
            }
            if(!$method_name)
            {
                $method_name = __('Cash','wpos-lite');
            }
            $created_at_time =  get_post_meta($id,'_created_at',true);


            $created_at = $this->core->render_ago_date_by_time_stamp($created_at_time);
            $tmp = array(
                'id' => $id,
                'title' => $post->post_title,
                'in_amount' => wc_price(get_post_meta($id,'_in_amount',true)),
                'out_amount'=> wc_price(get_post_meta($id,'_out_amount',true)),
                'payment_name'=> $method_name,
                'created_at'=> $created_at,
                'register' => $register,
                'created_by' => $name
            );
            $rows[] = $tmp;


        }


        $result = array(
            'current' => $current,
            'rowCount' => $rowCount,
            'rows' => $rows,
            'total' => $total

        );
        echo json_encode($result);
        exit;
    }

    public function orders(){
        global $op_register;
        global $op_warehouse;

        $rows = array();

        $current = isset($_REQUEST['current']) ? intval($_REQUEST['current']) : 1;

        $sort  = isset($_REQUEST['sort']) ? sanitize_text_field($_REQUEST['sort']) : false;
        $warehouse_id  = isset($_REQUEST['warehouse']) ? intval($_REQUEST['warehouse']) : 0;
        $register_id  = isset($_REQUEST['register']) ? intval($_REQUEST['register']) : 0;

        $searchPhrase  = $_REQUEST['searchPhrase'] ? sanitize_text_field($_REQUEST['searchPhrase']) : false;
        $sortBy = 'date';
        $order = 'DESC';
        if($sort && is_array($sort))
        {
            $key = array_keys($sort);

            $sortBy = end($key);
            if($sortBy == 'id')
            {
                $sortBy = 'ID';
            }
            $order = end($sort);
        }


        $rowCount = $_REQUEST['rowCount'] ? intval($_REQUEST['rowCount']) : get_option( 'posts_per_page' );

        $offet = ($current -1) * $rowCount;


        $args = array(
            'posts_per_page'   => $rowCount,
            'offset'           => $offet,
            'category'         => '',
            'category_name'    => '',
            'orderby'          => $sortBy,
            'order'            => $order,
            'post_type'        => array('shop_order'),
            'post_status'      => 'any',
            'suppress_filters' => false
        );
        $meta_query = array();

        if($register_id)
        {
            $register_meta_key = $op_register->get_transaction_meta_key();
            $meta_query[] = array(
                'key'     => $register_meta_key,
                'value'   => $register_id,
                'compare' => '=',
            );
        }
        if($warehouse_id)
        {
            $warehouse_meta_key = $op_warehouse->get_transaction_meta_key();
            $meta_query[] = array(
                'key'     => $warehouse_meta_key,
                'value'   => $warehouse_id,
                'compare' => '=',
            );
        }

        $meta_query[] =  array(
            'key' => '_op_order_source',
            'value' => 'openpos',
            'compare' => '=',
        );

        if(!empty($meta_query))
        {
            $args['meta_query'] = $meta_query;
        }


        if($searchPhrase)
        {
            $args['post__in'] = [$searchPhrase];
        }

        $get_posts = new WP_Query($args);
        $posts = array('total'=>$get_posts->found_posts,'posts' => $get_posts->get_posts());

        $posts_array = $posts['posts'];
        $total = $posts['total'];


        foreach($posts_array as $post)
        {
            $id = $post->ID;
            $order = wc_get_order($id);

            $register_id = get_post_meta($id,'_pos_order_cashdrawer',true);

            $register_name = __('Unknown' , 'wpos-lite');
            $register = $op_register->get($register_id);
            if(!empty($register))
            {
                $register_name = $register['name'];
            }

            $cashier_id = get_post_field( 'post_author', $id);
            $cashier = get_user_by('ID',$cashier_id);
            $cashier_name = 'unknown';

            if($cashier)
            {
                $cashier_name = $cashier->display_name;
            }
            $seller_name = $cashier_name;
            $_op_sale_by_person_id = get_post_meta($id,'_op_sale_by_person_id',true);
            if($_op_sale_by_person_id)
            {
                $seller = get_user_by('ID',$_op_sale_by_person_id);
                if($seller)
                {
                    $seller_name = $seller->display_name;
                }
            }


            $by_html = '<p><b>C:</b> '.$cashier_name.'</p>';
            $by_html .= '<p><b>S:</b> '.$seller_name.'</p>';
            $created_at = $this->core->render_order_date_column($order);
            $view_url = get_edit_post_link($id);
            $order_number_str = '<a class="op-order-number" href="'.esc_url($view_url).'">#'.$order->get_order_number().'</a>';
            $tmp = array(
                'id' => $id,
                'order_number' => $order_number_str,
                'source' => $register_name,
                'created_at' => $created_at,
                'total'=> wc_price($order->get_total()),
                'view_url'=> '<a href="'.esc_url($view_url).'" class="order-preview" data-order-id="666" title="Preview">Preview</a>',
                'created_by' => $by_html,
                'status' => $order->get_status()

            );
            $rows[] = $tmp;


        }


        $result = array(
            'current' => $current,
            'rowCount' => $rowCount,
            'rows' => $rows,
            'total' => $total

        );
        echo json_encode($result);
        exit;
    }

    public function admin_style() {
        $info = $this->core->getPluginInfo();
        $allow_bootstrap = array('op-products','op-cashiers','op-transactions','op-orders','op-reports','op-sessions','op-registers','op-warehouses','op-stock','op-setting','op-tables');
        $all_pos_page = array('op-products','op-cashiers','op-transactions','op-orders','op-reports','op-sessions','op-registers','op-warehouses','op-stock','op-setting','openpos-dasboard','op-tables');

        $current_page = isset( $_REQUEST['page'])  ?  intval($_REQUEST['page']): false;


        if(in_array($current_page,$all_pos_page))
        {

            if(in_array($current_page,$allow_bootstrap))
            {
                wp_enqueue_style('openpos.bootstrap', WPOSL_URL.'/assets/css/bootstrap.min.css','',$info['Version']);
                wp_enqueue_script('openpos.bootstrap', WPOSL_URL.'/assets/js/bootstrap.min.js','jquery',$info['Version']);

            }
            if($current_page == 'openpos-dasboard' )
            {
                wp_enqueue_script('chart.js', WPOSL_URL.'/assets/js/Chart.min.js',$info['Version']);
              
            }
            

            wp_enqueue_style('openpos.admin-jquery.bootgrid', WPOSL_URL.'/assets/css/jquery.bootgrid.min.css','',$info['Version']);
            wp_enqueue_script('openpos.admin-jquery.bootgrid', WPOSL_URL.'/assets/js/jquery.bootgrid.min.js','jquery',$info['Version']);
            


            wp_enqueue_script('openpos.admin.js', WPOSL_URL.'/assets/js/admin.js','jquery',$info['Version']);
            $vars['ajax_url'] = admin_url('admin-ajax.php');
            wp_localize_script('openpos.admin.js', 'openpos_admin', $vars);
        }
        wp_enqueue_style('openpos.admin', WPOSL_URL.'/assets/css/admin.css','',$info['Version']);
        

    }

    function add_store_setting_column($content,$column_name,$term_id){

        return $content;
    }

    function store_setting_column_header( $columns ){
        $columns['header_name'] = __( 'Action','wpos-lite');
        return $columns;
    }

    function store_setting_column_content( $value, $column_name, $tax_id ){
        $href = '';
        return '<a href="'.esc_url($href).'">'.__('Setting','wpos-lite').'</a>';
    }

    function register_post_types()
    {
        register_post_type( 'op_transaction',
                array(
                    'labels'              => array(
                        'name'                  => __( 'Transactions', 'wpos-lite'),
                        'singular_name'         => __( 'Transaction', 'wpos-lite')
                    ),
                    'description'         => __( 'This is where you can add new transaction that customers can use in your store.', 'wpos-lite'),
                    'public'              => false,
                    'show_ui'             => false,
                    'capability_type'     => 'op_transaction',
                    'map_meta_cap'        => true,
                    'publicly_queryable'  => false,
                    'exclude_from_search' => true,
                    'show_in_menu'        => false,
                    'hierarchical'        => false,
                    'rewrite'             => false,
                    'query_var'           => false,
                    'supports'            => array( 'title','author' ),
                    'show_in_nav_menus'   => false,
                    'show_in_admin_bar'   => false
                )

        );
       
        

    }

    public function get_pos_url(){
        $pos_url = 'https://pos.wpos.app';
        return  apply_filters('op_pos_url',$pos_url);
    }

    public function admin_bar_menus( $wp_admin_bar ) {
        if ( ! is_admin() || ! is_user_logged_in() ) {
            return;
        }
        // Show only when the user is a member of this site, or they're a super admin.
        if ( ! is_user_member_of_blog() && ! is_super_admin() ) {
            return;
        }
        $pos_url = $this->get_pos_url();
        // Add an option to visit the store.
        $wp_admin_bar->add_node( array(
            'parent' => 'site-name',
            'id'     => 'view-pos',
            'target'     => '_blank',
            'title'  => __( 'Visit POS', 'woocommerce' ),
            'href'   => $pos_url,
        ) );
    }

    function pos_admin_menu() {
        $openpos_type = $this->settings_api->get_option('openpos_type','openpos_pos');
        $page = add_menu_page( __( 'Open POS', 'wpos-lite'), __( 'POS - Lite', 'wpos-lite'),'manage_woocommerce','openpos-dasboard',array($this,'dashboard'),plugins_url('wpos-lite/assets/images/pos.png'),58 );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );

        $page = add_submenu_page( 'openpos-dasboard', __( 'POS - Orders', 'wpos-lite'),  __( 'Orders', 'wpos-lite') , 'manage_woocommerce', 'op-orders', array( $this, 'orders_page' ) );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );

        $page = add_submenu_page( 'openpos-dasboard', __( 'POS - Transactions', 'wpos-lite'),  __( 'Transactions', 'wpos-lite') , 'manage_woocommerce', 'op-transactions', array( $this, 'transactions_page' ) );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );

        $page = add_submenu_page( 'openpos-dasboard', __( 'POS - Products', 'wpos-lite'),  __( 'Products Barcode', 'wpos-lite') , 'manage_woocommerce', 'op-products', array( $this, 'products_page' ) );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );

        $page = add_submenu_page( 'openpos-dasboard', __( 'POS - Staffs', 'wpos-lite'),  __( 'Cashiers', 'wpos-lite') , 'manage_options', 'op-cashiers', array( $this, 'cashier_page' ) );
        add_action( 'admin_print_styles-'. $page, array( &$this, 'admin_enqueue' ) );

        $setting_page = add_submenu_page( 'openpos-dasboard', __( 'POS - Setting', 'wpos-lite'),  __( 'Setting', 'wpos-lite') , 'manage_options', 'op-setting', array( $this, 'setting_page' ) );
        add_action( 'admin_print_styles-'. $setting_page, array( $this, 'admin_enqueue_setting' ) );
    }
    

    function products_page() {
        require(WPOSL_DIR.'templates/admin/products.php');
    }
    

    public function dashboard()
    {
        $ranges = $this->core->getReportRanges('last_7_days');
        $chart_data = array();
        $pos_url = $this->get_pos_url();
        $chart_data[] = array(
            __('Date','wpos-lite'),
            __('Sales','wpos-lite'),
            __('Transactions','wpos-lite')
        );

        foreach($ranges['ranges'] as $r)
        {
            $sales = $this->core->getPosOrderByDate($r['from'],$r['to']);
            $total_sales = 0;
            foreach($sales as $s)
            {
                $order = new WC_Order($s->ID);
                $total_sales += $order->get_total();
            }

            $total_transaction = 0;
            $transactions = $this->core->getPosTransactionsByDate($r['from'],$r['to']);
            foreach($transactions as $s)
            {

                $in = get_post_meta($s->ID,'_in_amount',true);
                $out = get_post_meta($s->ID,'_out_amount',true);
                $total_transaction += ($in - $out);
            }

            $chart_data[] = array(
                $r['label'],
                $total_sales,
                $total_transaction
            );
        }
        $dashboard_data = $this->dashboard_data(true);
        
        require(WPOSL_DIR.'templates/admin/dashboard.php');
    }
    public function cashier_page()
    {
        require(WPOSL_DIR.'templates/admin/cashier.php');
    }
    public function transactions_page()
    {
        require(WPOSL_DIR.'templates/admin/transactions.php');
    }
    public function orders_page(){
        require(WPOSL_DIR.'templates/admin/orders.php');
    }

    public function setting_page()
    {
        echo '<div class="op-wrap">';
        $this->settings_api->show_navigation();
        $this->settings_api->show_forms();
        $this->settings_api->category_widget();
        echo '</div>';
    }

    public function admin_enqueue_setting() {
        global $OPENPOS_SETTING;
        $OPENPOS_SETTING->admin_enqueue_scripts();
       
        $this->admin_style();

    }
    public function admin_enqueue() {
        $this->admin_style();
    }

    public function getUsers(){

        $rows = array();
        $current = isset($_REQUEST['current']) ? intval($_REQUEST['current']) : 1;
        $sort  = isset($_REQUEST['sort']) ? sanitize_text_field($_REQUEST['sort']) : false;
        $searchPhrase  = $_REQUEST['searchPhrase'] ? sanitize_text_field($_REQUEST['searchPhrase']) : false;
        $sortBy = 'date';
        $order = 'DESC';
        if($sort)
        {
            if(is_array($sort))
            {
                $sortBy = end(array_keys($sort));
            }
            if($sortBy == 'id')
            {
                $sortBy = 'ID';
            }
            $order = end($sort);
        }


        $rowCount = $_REQUEST['rowCount'] ? intval($_REQUEST['rowCount']) : get_option( 'posts_per_page' );
        $offet = ($current -1) * $rowCount;

        $roles =  array('administrator','shop_manager');
        $final_roles = apply_filters('op_allow_user_roles',$roles);
        $args = array(
            'count_total' => true,
            'number'   => $rowCount,
            'offset'           => $offet,
            'orderby'          => $sortBy,
            'order'            => $order,
            'role__in' => $final_roles,
            'fields' => array('ID', 'display_name','user_email','user_login','user_status')
        );
        if($searchPhrase)
        {
            $args['search'] = $searchPhrase;
        }

        $user_query = new WP_User_Query( $args );


        $users = get_users( $args);
        $total = $user_query->total_users;

        foreach($users as $user)
        {
            $tmp = (array)$user;
            
            $allow_pos = get_user_meta($tmp['ID'],'_op_allow_pos',true);
            if(!$allow_pos)
            {
                $allow_pos = 0;
            }else{
                $allow_pos = 1;
            }
            //$tmp['allow_post'] = $allow_pos;
            $tmp['id'] = (int)$tmp['ID'];
            unset($tmp['ID']);
            if($allow_pos)
            {
                $tmp['allow_pos'] = '<select type="text" name="_op_allow_pos['.$tmp['id'].']" class="form-control _op_allow_pos" disabled><option value="0">No</option><option value="1" selected>Yes</option></select>';
            }else{
                $tmp['allow_pos'] = '<select  type="text" name="_op_allow_pos['.$tmp['id'].']" class="form-control _op_allow_pos" disabled><option value="0" selected>No</option><option value="1">Yes</option></select>';
            }
            $rows[] = $tmp;
        }
        $result = array(
            'current' => $current,
            'rowCount' => $rowCount,
            'rows' => $rows,
            'total' => $total
        );
        echo json_encode($result);
        exit;
    }

    public function dashboard_data($return = false){
        global $op_register;
        $result = array('order' => array());

        $args = array(
            "post_type" => "shop_order",
            'posts_per_page' => 10,
            'orderby' => 'publish_date',
            'order' => 'DESC',
            'post_status'      => 'any',
            'meta_query' => array(
                array(
                    'key' => '_op_order_source',
                    'value' => 'openpos',
                    'compare' => '=',
                )
            )
        );
        $query = new WP_Query($args);
        $orders = $query->get_posts();
        foreach($orders as $order)
        {
            $id = $order->ID;
            $_order = wc_get_order($id);
            $customer_name = __('Guest','wpos-lite');
            if( $_order->get_billing_first_name() || $_order->get_billing_last_name())
            {
                $customer_name = $_order->get_billing_first_name().' '.$_order->get_billing_last_name();
            }

            $grand_total = $_order->get_total();
            $cashier_id = get_post_field( 'post_author', $id);
            $cashier = get_user_by('ID',$cashier_id);
            $cashier_name = 'unknown';
            if($cashier)
            {
                $cashier_name = $cashier->display_name;
            }

            $tmp = array(
                'order_id' => "#".$_order->get_order_number(),
                'customer_name' => $customer_name,
                'total' => wc_price($grand_total),
                'cashier' => $cashier_name,
                'created_at' =>  $this->core->render_order_date_column($_order),
                'view' => '<a target="_blank" href="'.get_edit_post_link($id).'">'.__( 'View', 'wpos-lite').'</a>'
            );
            $result['order'][] = $tmp;
        }
        $balance = 0;
        $registers = $op_register->registers();
        foreach($registers as $register)
        {
            if($register['status'] == 'publish')
            {
                $balance +=  $op_register->cash_balance($register['id']);
            }

        }
        $result['cash_balance'] = wc_price($balance);
        if($return)
        {
            return $result;
        }else{
            echo json_encode($result);
            exit;
        }
       
    }

    public function save_cashier(){
        $data = inval($_REQUEST['_op_allow_pos']);
        foreach($data as $user_id => $value)
        {
            update_user_meta($user_id,'_op_allow_pos',intval($value));
        }
        exit;
    }

    public function update_product_grid(){
        
        exit;
    }
    

    public function update_transaction_grid(){

        if(isset($_REQUEST['data']))
        {
            $data = array_map( 'sanitize_text_field',(array)$_REQUEST['data']);
            if(is_array($data))
            {
                foreach($data as $post_id)
                {
                    $post_type = get_post_type($post_id);
                    if($post_type == 'op_transaction')
                    {
                        $in = get_post_meta($post_id,'_in_amount',true);
                        $out = get_post_meta($post_id,'_out_amount',true);
                        $total_transaction = ($in - $out);
                        $balance = get_option('_pos_cash_balance',0);
                        $balance += $total_transaction;
                        update_option('_pos_cash_balance',$balance);
                        wp_delete_post($post_id);
                    }

                }
            }
        }
        exit;
    }

    

    public function _short_code()
    {
        $is_pos = false;
        if(isset($_REQUEST['action']) && sanitize_text_field($_REQUEST['action']) == 'wpos-lite')
        {
            $is_pos = true;
        }
        add_shortcode( 'barcode', array($this,'_barcode_img_func'));
        add_shortcode( 'op_product', array($this,'_product_barcode_func'));
        if(!$is_pos)
        {
            add_shortcode( 'order_barcode', array($this,'_order_barcode_func'));

        }
        

        $this->register_post_types();
    }
    public function _order_barcode_func($atts)
    {
        global $barcode_generator;
        global $OPENPOS_SETTING;
        $barcode_mode = $OPENPOS_SETTING->get_option('barcode_mode','openpos_label');

        switch ($barcode_mode)
        {
            case 'code_128':
                $mode = $barcode_generator::TYPE_CODE_128;
                break;
            case 'ean_13':
                $mode = $barcode_generator::TYPE_EAN_13;
                break;
            case 'ean_8':
                $mode = $barcode_generator::TYPE_EAN_8;
                break;
            case 'code_39':
                $mode = $barcode_generator::TYPE_CODE_39;
                break;
            case 'upc_a':
                $mode = $barcode_generator::TYPE_UPC_A;
                break;
            case 'upc_e':
                $mode = $barcode_generator::TYPE_UPC_E;
                break;
            default:
                $mode = $barcode_generator::TYPE_CODE_128;
        }

        $atts = shortcode_atts( array(
            'width' => 2.7,
            'height' => 0.5
        ), $atts, 'barcode' );
        $barcode = '100';
        $unit = 'inch';
        $img_data = $barcode_generator->getBarcode($barcode, $mode);
        return '<img src="data:image/png;base64, '.base64_encode($img_data).'" style="width: '.$atts['width'].$unit.' ;max-width:'.$atts['width'].$unit.';max-height:'.$atts['height'].$unit.';height:'.$atts['height'].$unit.'">';
    }
    public function _barcode_img_func($atts)
    {
        global $product;
        global $_op_product;
        global $barcode_generator;
        global $unit;
        global $OPENPOS_SETTING;
        global $_barcode_width;
        global $_barcode_height;
        global $_unit;
        $barcode_mode = $OPENPOS_SETTING->get_option('barcode_mode','openpos_label');

        if(!$_op_product && $product)
        {
            $_op_product = $product;
        }
        if($_op_product)
        {
            switch ($barcode_mode)
            {
                case 'code_128':
                    $mode = $barcode_generator::TYPE_CODE_128;
                    break;
                case 'ean_13':
                    $mode = $barcode_generator::TYPE_EAN_13;
                    break;
                case 'ean_8':
                    $mode = $barcode_generator::TYPE_EAN_8;
                    break;
                case 'code_39':
                    $mode = $barcode_generator::TYPE_CODE_39;
                    break;
                case 'upc_a':
                    $mode = $barcode_generator::TYPE_UPC_A;
                    break;
                case 'upc_e':
                    $mode = $barcode_generator::TYPE_UPC_E;
                    break;
                default:
                    $mode = $barcode_generator::TYPE_CODE_128;
            }

            $atts = shortcode_atts( array(
                'width' => 2.7,
                'height' => 0.5
            ), $atts, 'barcode' );

            if($_barcode_width)
            {
                $atts['width'] = $_barcode_width;
            }
            if($_barcode_height)
            {
                $atts['height'] = $_barcode_height;
            }

            $barcode = $this->core->getBarcode($_op_product->get_id());
            $unit = sanitize_text_field($_REQUEST['unit']);
            if($barcode_mode != 'qrcode')
            {
                $img_data = $barcode_generator->getBarcode($barcode, $mode);
                return '<img src="data:image/png;base64, '.base64_encode($img_data).'" style="width: '.$atts['width'].$unit.' ;max-width:'.$atts['width'].$unit.';max-height:'.$atts['height'].$unit.';height:'.$atts['height'].$unit.'">';

            }else{
                $chs = '100x100';
                if($unit == 'in')
                {
                    $barcode_w = round($atts['width'] * 96);
                    $barcode_h = round($atts['height'] * 96);
                    $chs = implode('x',array($barcode_w,$barcode_h));
                }
                if($unit == 'mm')
                {
                    $barcode_w = round($atts['width'] * 3.7795275591);
                    $barcode_h = round($atts['height'] * 3.7795275591);
                    $chs = implode('x',array($barcode_w,$barcode_h));
                }
                $img_url = 'https://chart.googleapis.com/chart?chs='.$chs.'&cht=qr&chl='.urlencode($barcode).'&choe=UTF-8';//$barcode_generator->getBarcode($barcode, $mode);
                return '<img src="'.esc_url($img_url).'" style="width: '.$atts['width'].$unit.' ;max-width:'.$atts['width'].$unit.';max-height:'.$atts['height'].$unit.';height:'.$atts['height'].$unit.'">';

            }
        }
        return '';

    }
    public function _product_barcode_func($atts)
    {
        global $_op_product;
        $atts = shortcode_atts( array(
            'attribute' => 'name',
            'format' => false
        ), $atts, 'op_product' );
        $result = '';
        switch ($atts['attribute'])
        {
            case 'barcode':
                $result = $this->core->getBarcode($_op_product->get_id());
                break;
            case 'price':
                $result = $this->core->getProductPrice($_op_product,$atts['format']);
                break;
            case 'name':
                $result = $_op_product->get_name();
                break;
            default:
                $methods = get_class_methods($_op_product);
                if(in_array('get_'.esc_attr($atts['attribute']),$methods))
                {
                    $result = $_op_product->{'get_'.esc_attr($atts['attribute'])}();
                }
                break;
        }
        if(strpos($atts['attribute'],'price') !== false)
        {
            $result = wc_price($result);
        }
        $result = apply_filters('op_product_info_label',$result,$_op_product,$atts );
        return $result;
    }

    

    public function print_bacode(){
        $is_preview = isset($_REQUEST['is_preview']) && $_REQUEST['is_preview'] == 1 ? true : false;
        $is_print = isset($_REQUEST['is_print']) && $_REQUEST['is_print'] == 1 ? true : false;
        if($is_preview)
        {
            global $_op_product;
            require(WPOSL_DIR.'templates/admin/print_barcode_paper.php');
        }else{
            if(!isset($_POST['product_id']) && !$is_print)
            {
                require(WPOSL_DIR.'templates/admin/print_barcode.php');
            }else{
                global $_op_product;
                require(WPOSL_DIR.'templates/admin/print_barcode_paper.php');
            }
        }
        
        
        exit;
    }
    public function reset_balance(){
        global $op_register;
        $registers = $op_register->registers();
        foreach($registers as $register)
        {
            $balance_key = $op_register->get_transaction_balance_key($register['id']);
            update_option($balance_key,0);
        }

    }
    public function print_receipt(){

        wp_register_script('openpos.admin.receipt.ejs', WPOSL_URL.'/assets/js/ejs.js',array('jquery'));
        $sections = $this->settings_api->get_fields();
        $setting = array();
        foreach($sections as $section => $fields)
        {
            foreach($fields as $field)
            {
                if(isset($field['name']))
                {
                    $option = $field['name'];

                    $setting[$option] = $this->settings_api->get_option($option,$section);
                    if($option == 'receipt_template_header' || $option == 'receipt_template_footer')
                    {
                        $setting[$option] = balanceTags($setting[$option],true);
                    }
                }

            }
        }
        $setting = $this->core->formatReceiptSetting($setting);

        $receipt_padding_top = $setting['receipt_padding_top'];
        $unit = 'in';
        $receipt_padding_right = $setting['receipt_padding_right'];
        $receipt_padding_bottom = $setting['receipt_padding_bottom'];
        $receipt_padding_left = $setting['receipt_padding_left'];
        $receipt_width = $setting['receipt_width'];
        $receipt_css = $setting['receipt_css'];
        $receipt_template_header = $setting['receipt_template_header'];
        $receipt_template = $setting['receipt_template'];
        $receipt_template_footer = $setting['receipt_template_footer'];

        $receipt_template_footer = do_shortcode($receipt_template_footer);

        $html_header = '<style type="text/css" media="print,screen">';

        $html_header .= '#invoice-POS { ';
        $html_header .= 'padding:  '.$receipt_padding_top.$unit. ' ' . $receipt_padding_right.$unit .' '.$receipt_padding_bottom.$unit.' '.$receipt_padding_left.$unit.';';
        $html_header .= 'margin: 0 auto;';
        $html_header .= 'width: '.$receipt_width.$unit.' ;';
        $html_header .=  '}';

        $html_header .= $receipt_css;
        $html_header .= '</style>';
        $html_header .= '<body>';


        $html = '<div id="invoice-POS">';
        $html .= '<div id="invoce-header">';
        $html .= $receipt_template_header;


        $html .= '</div>';
        $html .= '<div id="bot">';

        $html .= '<div id="table">';
        $html .= $receipt_template;

        $html .= '</div><!--End Table-->';

        $html .= '<div id="invoce-footer">';
        $html .= $receipt_template_footer;
        $html .= '</div>';

        $html .= '</div><!--End InvoiceBot-->';
        $html .= '</div><!--End Invoice-->';

        $html = trim(preg_replace('/\s+/', ' ', $html));
        $order_id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        $order_json = '';
        if($order_id)
        {
            $order_data = get_post_meta($order_id,'_op_order',true);
            if($order_data)
            {
                $order_json = json_encode($order_data);
            }

        }

        $data = array(
            'setting' => $setting,
            'html_header' =>$html_header,
            'html_body' =>  addslashes(html_entity_decode($html)),
            'order_json' =>  $order_json

        );


        require(WPOSL_DIR.'templates/admin/print_receipt.php');
        exit;
    }
    function op_ajax_category(){
        $query = sanitize_text_field($_REQUEST['search']);

        $args = array(
            'taxonomy'   => "product_cat",
            'hide_empty' => false,
            'name__like' => $query
        );
        $product_categories = get_terms($args);

        $result = array();
        foreach($product_categories as $cat)
        {

            $id = $cat->term_id;
            $text = $cat->name;
            $result[] = array(
                'value' => $id,
                'text' => $text
            );
        }

        echo json_encode($result);
        exit;
    }
    function op_ajax_order_statuses(){
        $result = array();
        $wc_order_status = wc_get_order_statuses();
        
        foreach($wc_order_status as $key =>$status)
        {
            $result[] = array(
                'value' => $key,
                'text' => $status
            );
        }
        
        echo json_encode($result);
        exit;
    }
    
    function admin_global_style(){
        wp_enqueue_style('openpos.admin.global', WPOSL_URL.'/assets/css/admin_global.css');
    }

    function admin_notice_init(){
        $option_page = isset($_REQUEST['option_page']) ? sanitize_text_field($_REQUEST['option_page']) : '';
        $action = isset($_REQUEST['action']) ? sanitize_text_field($_REQUEST['action']) : '';
        if(strpos($option_page,'openpos_') !== false && $action == 'update')
        {
            update_option('_admin_op_setting_msg',__( 'Your setting has been update succes. Don\'t forget Logout and Login POS again to take effect on POS panel !', 'wpos-lite'));
        }
    }

    function admin_notice() {

        $msg = get_option('_admin_op_setting_msg',false);

        if($msg)
        {
            ?>
            <div class="notice">
                <p style="color: green;"><?php echo $msg; ?></p>
            </div>
            <?php
            update_option('_admin_op_setting_msg','');
        }
    }
    function woocommerce_product_options_stock_fields(){
        global $post;
        global $op_warehouse;
        $warehouses = $op_warehouse->warehouses();
        if($post && count($warehouses) > 1)
        {
            $product = wc_get_product($post->ID);
            $product_type = $product->get_type();
            if($product_type != 'variable')
            {
                ?>
                <div class="op-product-outlet-stock hide_if_variable">
                    <p class="op-stock-label"><?php echo __('Other Outlet Stock quantity'); ?></p>
                    <table border="1">
                        <?php foreach($warehouses as $warehouse): ?>
                            <?php if($warehouse['id'] > 0): $warehouse_id  = $warehouse['id']; ?>
                            <tr>
                                <th><?php echo sprintf(__( '<strong>%s</strong>', 'wpos-lite'),$warehouse['name']); ?></th>
                                <td>
                                    <?php
                                        $product_id = $post->ID;
                                        $qty = '';
                                        if($op_warehouse->is_instore($warehouse_id,$product_id))
                                        {
                                            $qty = $op_warehouse->get_qty($warehouse_id,$product_id);
                                            
                                        }
                                        woocommerce_wp_text_input(
                                            array(
                                                'id'                => '_op_stock',
                                                'name'                => '_op_stock['.$warehouse_id.']',
                                                'value'             => $qty,
                                                'label'             => '',
                                                'type'              => 'text'
                                    
                                            )
                                        );
                                    ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php
            }
        }
    }
   
    


}