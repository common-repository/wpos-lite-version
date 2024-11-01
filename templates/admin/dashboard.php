<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<?php
/**
 * Created by PhpStorm.
 * User: anhvnit
 * Date: 12/4/16
 * Time: 23:40
 */

?>
<script type="text/javascript">
    (function($) {
        $('body').on('click','#reset-balance',function () {
            if(confirm('<?php echo __('This function to reset cash balance on your cash drawer to 0. Are you sure ?','wpos-lite'); ?>'))
            {
                $.ajax({
                    url: openpos_admin.ajax_url,
                    type: 'post',
                    dataType: 'json',
                    data:{action:'admin_openpos_reset_balance'},
                    success:function(data){
                        $('#openpos-cash-balance').text(0);
                    }
                })
            }
        })

    }(jQuery));
	window.onload = function(){
        <?php
             $label = array();
             $sale_data = array();
             $transaction_data = array();
             foreach($chart_data as $index =>  $c)
             {
                 if($index == 0)
                 {
                     continue;
                 }
                $label[] = $c[0];
                $sale_data[] = $c[1];
                $transaction_data[] = $c[2];
             }
        ?>
        var ctx = document.getElementById("myChart").getContext("2d");
        var   sale_data = <?php echo json_encode($sale_data) ?>;;
        var   transaction_data = <?php echo json_encode($transaction_data) ?>;;
        var labels =  <?php echo json_encode($label) ?>;
        
		var myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                 {
                    label: '<?php echo __('Sales','wpos-lite'); ?>',
                    data: sale_data,
                },
                /*
                {
                    label: '<?php echo __('Cash Transactions','wpos-lite'); ?>',
                    data: transaction_data,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                }
                */
            ]
            },
        });
	}
</script>

<div class="op-dashboard-content">
    <div class="row goto-pos-container">
        <div class="col-md-8 pull-right">
            <a href="<?php echo $pos_url; ?>"class="button-primary" target="_blank"><?php echo __('Goto POS','wpos-lite'); ?></a>
            <a href="https://codecanyon.net/item/openpos-a-complete-pos-plugins-for-woocomerce/22613341"class="button-primary" style="background-color:#d9534f;border-color:#d9534f; margin-right: 5px;text-shadow: none;text-transform:uppercase;" target="_blank"><?php echo __('Buy Now','wpos-lite'); ?></a>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <h2><?php echo __('How to start ?','wpos-lite'); ?></h2>
            <ol>
                <li><?php echo __('Markup user is a Cashier (admin / pos / Cashiers) and save','wpos-lite'); ?></li>
                <li><?php echo __('Click "Goto POS" button','wpos-lite'); ?></li>
                <li><?php echo __('Login with your cashier user info (marked cashier)','wpos-lite'); ?></li>
                <li><?php echo __('Enjoy!','wpos-lite'); ?></li>
            </ol> 

        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <canvas id="myChart" height="250" width="800"></canvas>
        </div>
    </div>
    <div class="real-content-container">
        <div class="last-orders" >
            <div class="title"><label><?php echo __('Last Orders','wpos-lite'); ?></label></div>
            <div id="table_div_latest_orders">
            <table class="table table-bordered" style="width: 100%;" id="lastest-order">
                <thead>
                    <tr>
                    <th><?php echo __('#','wpos-lite'); ?></th>
                    <th><?php echo __('Customer','wpos-lite'); ?></th>
                    <th><?php echo __('Grand Total','wpos-lite'); ?></th>
                    <th><?php echo __('Sale By','wpos-lite'); ?></th>
                    <th><?php echo __('Created At','wpos-lite'); ?></th>
                    <th><?php echo __('View','wpos-lite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($dashboard_data['order'] as $order): ?>
                    <tr>
                        <td><?php echo $order['order_id']; ?></td>
                        <td><?php echo $order['customer_name']; ?></td>
                        <th><?php echo $order['total']; ?></th>
                        <td><?php echo $order['cashier']; ?></td>
                        <td><?php echo $order['created_at']; ?></td>
                        <td><?php echo $order['view']; ?></td>
                    </tr>
                    <?php endforeach;   ?>
                </tbody>
            </table>
            </div>
        </div>
        <div class="total">
            <div class="title"><label><?php echo __('Cash Balance','wpos-lite'); ?></label></div>
            <ul id="total-details">

                <li>
                    <div class="field-title" style="text-align: center;">
                       <span id="openpos-cash-balance"><?php echo $dashboard_data['cash_balance']; ?></span>
                        <a href="javascript:void(0);" id="reset-balance" style="outline: none;display: block;border:none;" title="Reset Balance">
                            <img src="<?php echo WPOSL_URL; ?>/assets/images/reset.png" height="34px" />
                        </a>
                    </div>

                </li>
            </ul>

        </div>
    </div>
</div>
