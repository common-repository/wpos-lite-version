<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="wrap">
    <h1><?php echo __( 'POS Products', 'wpos-lite'); ?></h1>
    <form id="op-product-list"  onsubmit="return false;">
        <input type="hidden" name="action" value="admin_openpos_update_product_grid">
        <table id="grid-selection" class="table table-condensed table-hover table-striped op-product-grid">
            <thead>
            <tr>
                <th data-column-id="id" data-identifier="true" data-type="numeric"><?php echo __( 'ID', 'wpos-lite'); ?></th>
                <th data-column-id="barcode" data-identifier="true" data-type="numeric"><?php echo __( 'Barcode', 'wpos-lite'); ?></th>
                <th data-column-id="product_thumb" data-sortable="false"><?php echo __( 'Thumbnail', 'wpos-lite'); ?></th>
                <th data-column-id="post_title" data-sortable="false"><?php echo __( 'Product Name', 'wpos-lite'); ?></th>
                <th data-column-id="formatted_price" data-sortable="false"><?php echo __( 'Price', 'wpos-lite'); ?></th>
                <th data-column-id="action"  data-sortable="false"><?php echo __( 'Action', 'wpos-lite'); ?></th>
            </tr>
            </thead>
        </table>
    </form>
    <br class="clear">
</div>


<script type="text/javascript">
    (function($) {
        "use strict";
       var grid = $("#grid-selection").bootgrid({
            ajax: true,
            post: function ()
            {
                /* To accumulate custom parameter with the request object */
                return {
                    action: "op_products"
                };
            },
            url: "<?php echo admin_url( 'admin-ajax.php' ); ?>",
            selection: true,
            multiSelect: true,
            formatters: {
                "link": function(column, row)
                {
                    return "<a href=\"#\">" + column.id + ": " + row.id + "</a>";
                },
                "price": function(column,row){

                    return row.formatted_price;
                }
            },
            templates: {
                header: "<div id=\"{{ctx.id}}\" class=\"{{css.header}}\"><div class=\"row\"><div class=\"col-sm-12 actionBar\"><p class=\"{{css.search}}\"></p><p class=\"{{css.actions}}\"></p><button type=\"button\" class=\"btn vna-action btn-default\" data-action=\"save\"><span class=\" icon glyphicon glyphicon-floppy-save\"></span></button><button type=\"button\" class=\"btn vna-action btn-default\" data-action=\"print\"><span class=\" icon glyphicon glyphicon-barcode\"></span></button></div></div></div>"
            }
        }).on("initialized.rs.jquery.bootgrid",function(){

        }).on("selected.rs.jquery.bootgrid", function(e, rows)
        {
            var rowIds = [];
            for (var i = 0; i < rows.length; i++)
            {
                rowIds.push(rows[i].id);
                if($('input[name="barcode['+rows[i].id+']"]'))
                {
                    $('input[name="barcode['+rows[i].id+']"]').prop('disabled',false);
                }
                if($('input[name="qty['+rows[i].id+']"]'))
                {
                    $('input[name="qty['+rows[i].id+']"]').prop('disabled',false);
                }
            }
        
           // alert("xxSelect: " + rowIds.join(","));
        }).on("deselected.rs.jquery.bootgrid", function(e, rows)
        {
            var rowIds = [];
            for (var i = 0; i < rows.length; i++)
            {
                rowIds.push(rows[i].id);
                if($('input[name="barcode['+rows[i].id+']"]'))
                {
                    $('input[name="barcode['+rows[i].id+']"]').prop('disabled',true);
                }
                if($('input[name="qty['+rows[i].id+']"]'))
                {
                    $('input[name="qty['+rows[i].id+']"]').prop('disabled',true);
                }
            }
            //alert("Deselect: " + rowIds.join(","));
        });
        $('.vna-action').click(function(){
            var selected = $("#grid-selection").find('input[type="checkbox"]:checked');
            var action = $(this).data('action');
            if(selected.length == 0)
            {
                alert('Please choose row to continue.');
            }else{
                if(action == 'print')
                {
                   var rows = new Array();
                   for(var i =0; i < selected.length; i++)
                   {
                       var row = selected[i];

                       var row_value = $(row).val();
                       if(row_value && row_value != 'all')
                       {
                           rows.push(row_value);
                       }

                   }

                   var url = "<?php echo admin_url( 'admin-ajax.php?action=print_barcode&id=' ); ?>"+rows.join(',');
                   window.location = url;
                }else {
                    $.ajax({
                        url: openpos_admin.ajax_url,
                        type: 'post',
                        dataType: 'json',
                        //data:$('form#op-product-list').serialize(),
                        data: {action: 'admin_openpos_update_product_grid',data:$('form#op-product-list').serialize()},
                        success:function(data){
                            alert('Saved');
                        }
                    })
                }


            }

        });
    })( jQuery );
</script>

<style>
    .action-row a{
        display: block;
        padding: 3px 4px;
        text-decoration: none;
        border: solid 1px #ccc;
        text-align: center;
        margin: 5px;
    }
    .op-product-grid td{
        vertical-align: middle!important;
    }
</style>