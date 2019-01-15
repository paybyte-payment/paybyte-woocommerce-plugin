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
    if(jQuery('form[name="checkout"] input[name="payment_method"]:checked').val() == 'paybyte'){
        var coin_amt = jQuery("#coin_amt").val();
        var coin = jQuery("#coin_name").val();
        jQuery('.woocommerce-checkout-review-order-table > tfoot:last').append('<tr id="coin_total"><th>' + coin + ' Total</th><td><b>'+coin_amt+'</b></td></tr>');
       
    }else{
         jQuery('#coin_total').remove();
    }
}   