<?php
if(!class_exists('WPOSL_Warehouse'))
{
    class WPOSL_Warehouse{
        public $_post_type = '_op_warehouse';
        public $_meta_field = array();
        public $_meta_product_qty = '_op_qty_warehouse';

        public function __construct()
        {
            $this->_meta_field = array(
                'address' => '_op_address',
                'address_2' => '_op_address_2',
                'city' => '_op_city',
                'postal_code' => '_op_postal_code',
                'country' => '_op_country',
                'phone' => '_op_phone',
                'email' => '_op_email',
                'facebook' => '_op_facebook'
            );
        }
        
        public function warehouses(){
            $result = array();
            $default_store = $this->get(0);

            $default = array(
                'id' => 0,
                'name' => __('Default online store','wpos-lite'),
                'address' => '',
                'address_2' => '',
                'city' => '',
                'postal_code' => '',
                'country' => '',
                'phone' => '',
                'email' => '',
                'facebook' => '',
                'status' => 'publish',
                'total_qty' => ''
            );
            $result[] = array_merge($default,$default_store);
           
            return apply_filters('op_warehouse_list',$result,$this);
        }
        public function get($id = 0){
           
                return array(
                    'id' => 0,
                    'name' => __('Default online store','wpos-lite'),
                    'address' =>  WC()->countries->get_base_address(),
                    'address_2' => WC()->countries->get_base_address_2(),
                    'city' => WC()->countries->get_base_city(),
                    'postal_code' => WC()->countries->get_base_postcode(),
                    'country' => implode(':',array(WC()->countries->get_base_country(),WC()->countries->get_base_state())),
                    'phone' => '',
                    'email' => '',
                    'facebook' => '',
                    'status' => 'publish',
                    'total_qty' => 0
                );
        }
        
        public function is_instore($warehouse_id = 0,$product_id){
            return true;
        }
       
        public function get_qty($warehouse_id = 0,$product_id){
            $product = wc_get_product($product_id);
            $qty = $product->get_stock_quantity();
            return 1*$qty;
        }
        public function get_order_meta_key(){
            $option_key = '_pos_order_warehouse';
            return $option_key;
        }
        public function get_transaction_meta_key(){
            $option_key = '_pos_transaction_warehouse';
            return $option_key;
        }
        public function getStorePickupAddress($warehouse_id = 0){
            $details = $this->get($warehouse_id);
            $result['address_1'] = isset($details['address']) ? $details['address']:'';
            $result['address_2'] = isset($details['address_2']) ? $details['address_2']:'';
            $result['city'] = isset($details['city']) ? $details['city']:'';
            $result['postcode'] = isset($details['postal_code']) ? $details['postal_code']:'';
            $country_state = isset($details['country']) ? $details['country']:'';
            $country = '';
            $state = '';
            if($country_state)
            {
                $location = wc_format_country_state_string($country_state);

                $country = $location['country'];
                $state = $location['state'];
            }
            $result['country'] = $country;
            $result['state'] = $state;
            return $result;
        }
    }
}
?>