<?php
if(!class_exists('WPOSL_Register'))
{
    class WPOSL_Register{
        public $_post_type = '_op_register';
        public $_cashiers_meta_key = '_op_cashiers';
        public $_warehouse_meta_key = '_op_warehouse';
        public $_filesystem;
        public $_bill_data_path;
        public $_base_path;
        public function __construct()
        {
            if(!class_exists('WP_Filesystem_Direct'))
            {
                require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php');
                require_once(ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php');
            }
            $this->_filesystem = new WP_Filesystem_Direct(false);
            $this->_base_path =  WP_CONTENT_DIR.'/uploads/openpos';
            $this->_bill_data_path =  $this->_base_path.'/registers';
            $this->init();
        }
        function init(){
            // create openpos data directory
        }
        public function registers(){
            $result = array();
            $name = __('Default Register','wpos-lite');
            $cashiers = array();
            $result[] = array(
                'id' => 0,
                'name' => $name,
                'warehouse' => 0,
                'cashiers' => $cashiers,
                'balance' => $this->cash_balance(0),
                'register_mode' => 'cashier',
                'status' => 'publish'
            );
            return $result;
        }
        public function get($id = 0){
            $resgisters = $this->registers();
            return end($resgisters);
        }
        public function cash_balance($register_id = 0){
            $option_key = $this->get_transaction_balance_key($register_id);
            return get_option($option_key,0);
        }
        public function get_transaction_balance_key($register_id = 0){
            $option_key = '_pos_cash_balance_'.$register_id;
            return $option_key;
        }
        public function get_order_meta_key(){
            $option_key = '_pos_order_cashdrawer';
            return $option_key;
        }
        public function get_transaction_meta_key(){
            $option_key = '_pos_transaction_cashdrawer';
            return $option_key;
        }
        public function addCashBalance($register_id = 0 ,$amount = 0){
            $current_balance = $this->cash_balance($register_id);
            $new_blance = $current_balance + $amount;
            update_option($this->get_transaction_balance_key($register_id),$new_blance);
        }
  
        
    }
}
?>