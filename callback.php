<?php
	
global $wpdb; 

$order_id = $_GET['order_id'];

// make sure this is a numberic value.
if (is_numeric($order_id)) {    
  
    /* Check payment address exist in database */
    $get_data = $wpdb->get_row('SELECT * FROM wp_setgetgo_payment where order_id="'.$order_id.'"', OBJECT, 0);
    $get_data = json_decode(json_encode($get_data), True);
    $db_payment_address = $get_data['payment_address']; 

    $payment_address = $_GET['payment_address'];   
    $isTestnet = $_GET['testnet'];    

    /* If payment address exist in database get and update transaction status */
    if($db_payment_address != null && $db_payment_address !="" && $db_payment_address == $payment_address ){

        $order = new WC_Order( $order_id );
          
        $get_status_url = "https://setgetgo.com/api/get-payment-status?payment_addr=".$db_payment_address."testnet=".$isTestnet;
            
        // get latest transaction payment status
        $status_res = wp_remote_get( $get_status_url);
        $payment_res_body = wp_remote_retrieve_body( $status_res );
        $payment_json_response = json_decode($payment_res_body,true);

        $update_payment_status = $payment_json_response['transaction']['status'];
        $amount_received = $payment_json_response['transaction']['amount-received'];

        $db_update_status = $wpdb->update('wp_setgetgo_payment', array('status' => $update_payment_status,'amount_received'=>$amount_received), array('payment_address' => $db_payment_address)); 
 
        switch ($update_payment_status) {
            case 'payment_received':
                $order->update_status('on-hold', __('SetGetGo Payment received still on-hold.', 'woocommerce'));
                $order->add_order_note( __( 'SetGetGo payment received but still on-hold.' ) );
                break;
            case 'payment_sent_to_merchant':
                $order->update_status('pending', __('SetGetGo Payment confirmed.', 'woocommerce'));
                $order->add_order_note( __( 'SetGetGo payment confirmed and funds sent to merchant wallet.' ) );
                break;
            case 'payment_received_unconfirmed':
                $order->update_status('on-hold', __('SetGetGo payment on hold. Payment received but still unconfirmed.', 'woocommerce'));
                $order->add_order_note( __( 'SetGetgo payment on-hold. Funds received but still unconfirmed.' ) );
                break;
            case 'expired':
                $order->update_status('failed', __('SetGetGo Payment failed. Payment request expired.', 'woocommerce'));
                $order->add_order_note( __( 'SetGetGo payment failed. Transaction request expired.' ) );
                break; 
            case 'pending':
                $order->update_status('pending', __('SetGetGo Payment pending.', 'woocommerce'));
                $order->add_order_note( __( 'SetGetGo payment pending. Payment still pending.' ) );
                break;           
        }
    } 
}
	

?>

