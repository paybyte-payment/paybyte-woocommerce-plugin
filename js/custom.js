jQuery(function(){

     //executes on change of payment method on checkout page
    jQuery( 'body' )
    .on( 'updated_checkout', function() {
          usingGateway();

        jQuery('input[name="payment_method"]').change(function(){
            //console.log("payment method changed");
            usingGateway();

        });
    });
    
});


function usingGateway(){
    if(jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() == 'custom'){
    	var btc_amt = jQuery("#btc_amt").val();
        jQuery('.woocommerce-checkout-review-order-table > tfoot:last').append('<tr id="btc_total"><th>BTC Total</th><td><b>'+btc_amt+'</b></td></tr>');
       
    }else{
         jQuery('#btc_total').remove();
    }
}   