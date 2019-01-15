<?php

/* PayByte Payment Gateway Class */
class WC_Gateway_PayByte extends WC_Payment_Gateway {

    public $domain;

    /**
     * Constructor for the gateway.
     */
    public function __construct() {

        $plugin_dir = plugin_dir_url(__FILE__);

        $this->domain = 'paybyte';

        $this->id                 = 'paybyte';
        $this->icon               = apply_filters('woocommerce_custom_gateway_icon',  $plugin_dir.'/img/paybyte_logo_2.png');
        $this->has_fields         = false;
        $this->order_button_text  = __('Proceed to PayByte', $this->domain );
        $this->method_title       = __( 'PayByte', $this->domain );
        $this->method_description = __( 'PayByte allows you to accept crypto payments on your WooCommerce Store.', $this->domain );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }

        // Define user set variables
        $this->title        = $this->get_option( 'title' );
        $this->description  = $this->get_option( 'description' );
        $this->api_key = $this->get_option( 'api_key');
        $this->order_status = $this->get_option( 'order_status', 'pending' );
        $this->coin  = $this->get_option( 'coin', 'BTC' );

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // Lets check for SSL
        add_action( 'admin_notices', array( $this,  'paybyte_do_ssl_check' ) );

        add_action('woocommerce_api_' . strtolower(get_class($this)), array(
            &$this,
            'paybyte_handle_callback'
        ));

        add_action('woocommerce_thankyou', function($order_id)
        {
            $coinAmount = get_post_meta( $order_id, 'paybyte_coin_total', true );
         
            if (strcmp($coinAmount, "true") == 0) {
                return; 
            }
            ?>
            <h2><?php echo 'Crypto payment (' . $this->coin . ')' ?></h2>
            <table class="woocommerce-table shop_table gift_info">
                <tbody>
                    <tr>
                        <th>Crypto coin amount</th>
                        <td><?php echo $this->coin . ' ' . $coinAmount ?></td>
                    </tr> 
                    <tr>
                        <th>Message</th>
                        <td>Your order has not been successfully processed yet! We received your payment, but we are waiting for a full confirmation from the Blockchain Network.</td>
                    </tr> 
                </tbody>
            </table>
        <?php 
        });
    }

    public function __destruct(){}
  
    /**
     * Build the administration fields for this specific Gateway
     */
    public function init_form_fields() {

        $this->form_fields = array(          
            'title' => array(
                'title'       => __( 'Title', $this->domain ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', $this->domain ),
                'default'     => __( 'PayByte Payment', $this->domain ),
                'desc_tip'    => true,
            ),
            'order_status' => array(
                'title'       => __( 'Order Status', $this->domain ),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __( 'Choose whether status you wish after checkout.', $this->domain ),
                'default'     => 'wc-pending',
                'desc_tip'    => true,
                'options'     => wc_get_order_statuses()
            ),
            'api_key' => array(
                'title'       => __( 'Merchant API key', $this->domain ),
                'type'        => 'text',
                'label'       => __( 'Merchant API key.', $this->domain ),
                'default'     => '',
                'description' => __( 'You need a Merchant API key in order to use PayByte. To request an API Key just register as a merchant on https://paybyte.io'),
                'desc_tip'    => false,
            ),
            'isTestnet' => array(
                'title'     => __( 'Testnet Mode', $this->domain  ),
                'label'     => __( 'Enable Testnet payments', $this->domain  ),
                'type'      => 'checkbox',
                'description' => __( 'Place the payment gateway in test mode.', $this->domain ),
                'default'   => 'no',
            ),
            'description' => array(
                'title'       => __( 'Description', $this->domain ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that the customer will see on your checkout.', $this->domain ),
                'default'     => __('Payment Information', $this->domain),
                'desc_tip'    => true,
            ),
            'coin' => array(
                'title'       => __( 'Crypto coin', $this->domain ),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __( 'Choose the crypto coin you want the payment to be made.', $this->domain ),
                'default'     => 'BTC',
                'desc_tip'    => true,
                'options'     => array(
                    'BTC'     => __( 'Bitcoin', 'woocommerce' ),
                    'BCH'     => __( 'Bitcoin Cash', 'woocommerce' ),
                    'BTG'     => __( 'Bitcoin Gold', 'woocommerce' ),
                    'BTX'     => __( 'BitCore', 'woocommerce' ),
                    'DGB'     => __( 'DigiByte', 'woocommerce' ),
                    'DASH'    => __( 'Dash', 'woocommerce' ),                    
                    'GRS'     => __( 'Groestlcoin', 'woocommerce'),
                    'LTC'     => __( 'Litecoin', 'woocommerce' )
                )
            ),
        );
    }

    
    /* function to display text and crypto attributes on checkout page*/
    public function payment_fields(){
        global $total_amt;

        if ( $description = $this->get_description() ) {
            echo wpautop( wptexturize( $description ) );
          
            $currency =  get_woocommerce_currency();

            $url = "https://paybyte.io/api/get-rate?currency=".$currency;

            // Send this payload to PayByte for processing
            $response = wp_remote_get( $url);
            
            // Retrieve the body's resopnse if no errors found
            $response_body = wp_remote_retrieve_body( $response );

            // Parse the json response into array
            $json_response = json_decode($response_body,true);

            $ratename = strtolower($this->coin) . '_rate';

            $coin_rate = $json_response['rate'][$ratename];
            $cart_total = WC()->cart->total;
            $total_amt = $coin_rate * $cart_total;
            $total_amt = number_format($total_amt, 6, '.', '');
            echo  "<p>Exchange rate (" . $currency . " -> " . $this->coin . "): <b>" . $this->coin . " " . $coin_rate . "</b>";
            echo "<input type='hidden' id='coin_amt' name='coin_amt' value='" . $total_amt . "' />";
            echo "<input type='hidden' id='coin_name' name='coin_name' value='" . $this->coin . "' />";
            return $total_amt;           
        }
    }

    /**
     * Prepares the create payment URL.
     *
     * @param WC_Order $customer_order
     * @param int $order_id
     * @param string $callaback_guid
     * 
     * @return string
     */
    public function paybyte_prepare_create_payment_url($customer_order, $order_id, $callaback_guid) {

        error_log("preparing create payment url..");
        $api_key = $this->api_key;
        $amount = $this->payment_fields();
        // get payment mode from settings saved in database
        $options =  get_option('woocommerce_custom_settings');
        $testnet = ($options['isTestnet']=="yes") ? "&testnet=true" : "";

        global $wp;
        $callback_url = urlencode(home_url('/') . "wc-api/WC_Gateway_PayByte/?order_id=" . $order_id . "&secret=" . $callaback_guid);
        $return_url = urlencode( WC_Payment_Gateway::get_return_url( $customer_order ));        
        $create_payment_url = "https://paybyte.io/api/create-payment?amount=".$amount."&api_key=".$api_key.$testnet."&callback=".$callback_url."&return_url=".$return_url."&coin=".$this->coin;
              
        error_log("preparing create payment url: ". $create_payment_url);

        return $create_payment_url;
    }

    /**
     * Create a callback secret.
     *
     * @param WooCommerceOrder $customer_order
     * @return UniqueIdentifier for this callback.
     */
    public function paybyte_create_callback_guid($customer_order) {
        return uniqid("", true);
    }

    /**
     * Starts the payment flow and redirects to PayByte.
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment( $order_id ) {

        global $woocommerce;

        // Get this Order's information so that we know
        // who to charge and how much
        $customer_order = new WC_Order( $order_id );

        $callback_guid =  $this->paybyte_create_callback_guid($customer_order);
 
        $create_payment_url = $this->paybyte_prepare_create_payment_url($customer_order, $order_id, $callback_guid);
       
        // Send this payload to PayByte for processing
        $response = wp_remote_get( $create_payment_url);

        if ( is_wp_error( $response ) ) 
            throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.' ) );

        if ( empty( $response['body'] ) )
            throw new Exception( __( 'PayByte\'s Response was empty.' ) );
            
        // Retrieve the body's resopnse if no errors found
        $response_body = wp_remote_retrieve_body( $response );

        // Parse the json response into array
        $json_response = json_decode($response_body,true);

        $payment_address = $json_response['transaction']['payment-address'];
		$payment_id = $json_response['transaction']['payment-id'];
        $tran_status = $json_response['transaction']['status'];
        $total_amt = $json_response['transaction']['amount'];
        $coin = $json_response['transaction']['coin'];
        $amount_received = $json_response['transaction']['amount-received'];
        $received_payment_url = $json_response['transaction']['payment-url'];
        $response_error = $json_response['error'];

        if ($response_error != "ok") {
            wc_add_notice( $response_error, 'error' );
            //Add note to the order for your reference
            $customer_order->add_order_note( 'Error from PayByte: '. $response_error);

            $customer_order->update_status('failed', __( 'PayByte payment failed', 'woocommerce' ));
        }
        else {

            global $wpdb;
            $insert = $wpdb->insert('paybyte_payment',
                    array(
                            'user_id' => get_current_user_id(),
                            'order_id' => $order_id,
                            'payment_address' => $payment_address,
							'payment_id' => $payment_id,
                            'status' => $tran_status,
                            'amount' => $total_amt,
                            'coin' => $coin,
                            'amount_received' => $amount_received,
                            'callback_guid' => $callback_guid,
                            'created' => date('Y-m-d H:i:s')
                    )
            );
                        
            if ($insert == 1) {

                // Payment has been successful
                $customer_order->add_order_note( __( 'PayByte payment initiated.' ) );

                /* Save payment url in meta field*/
                update_post_meta( $order_id, 'paybyte_payment_url', $received_payment_url ); 

                /* Save coin total in meta field*/
                update_post_meta( $order_id, 'paybyte_coin_total', $total_amt);

                /* Save coin total in meta field*/
                update_post_meta( $order_id, 'paybyte_coin_name', $this->coin);
               
                $customer_order->update_status('pending', __( 'Awaiting PayByte payment', 'woocommerce'));

                // Reduce stock levels
                $customer_order->reduce_order_stock();
                
                // Empty the cart
                $woocommerce->cart->empty_cart();

                // Redirect to thank you page
                return array(
                    'result'   => 'success',
                    'redirect' => $received_payment_url,
                );

            }
            else {
                $customer_order->add_order_note( 'Error: '. 'Unable to store payment request data. Payment aborted.');
                $customer_order->update_status('failed', __( 'PayByte payment failed', 'woocommerce' ));
            }
        }
    }

    // Check if we are forcing SSL on checkout pages
    // Custom function not required by the Gateway
    public function paybyte_do_ssl_check() {
        if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
            echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled but WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>", $this->domain ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";  
        }
    }

    /**
     * Handles the callback from PayByte.
     *
     * @param int $order_id
     * @return array
     */
    public function paybyte_handle_callback() {

        global $wpdb; 

        $order_id = sanitize_text_field($_GET['order_id']);
        $secret = sanitize_text_field($_GET['secret']);
        $payment_id = sanitize_text_field($_GET['payment_id']);   
        $isTestnet = sanitize_text_field($_GET['testnet']);
        
        error_log("callback received: " . $order_id . " , " . $payment_id . " , " .  $isTestnet);
        
        // make sure this is a numberic value.
        if (is_numeric($order_id)) {    
          
            /* Check payment address exist in database */
            $db_data = $wpdb->get_row('SELECT * FROM paybyte_payment where order_id="'.$order_id.'"', OBJECT, 0);
            $db_data = json_decode(json_encode($db_data), True);
            $db_payment_address = $db_data['payment_address']; 
            $db_payment_id = $db_data['payment_id']; 
            $db_callback_guid = $db_data['callback_guid'];
        
            /* Validity chack before updating the order state. */
            if( $db_callback_guid == $secret && 
                $db_payment_id == $payment_id ) {
        
                $order = new WC_Order( $order_id );
                  
                $get_status_url = "https://paybyte.io/api/get-payment-status?payment_id=".$db_payment_id."&testnet=".$isTestnet;
                    
                // get latest transaction payment status
                $status_res = wp_remote_get( $get_status_url);
                $payment_res_body = wp_remote_retrieve_body( $status_res );
                $payment_json_response = json_decode($payment_res_body,true);
        
                $update_payment_status = $payment_json_response['transaction']['status'];
                $amount_received = $payment_json_response['transaction']['amount-received'];
        
                $db_update_status = $wpdb->update('paybyte_payment', array('status' => $update_payment_status,'amount_received'=>$amount_received), array('payment_address' => $db_payment_address)); 
         
                switch ($update_payment_status) {
                    case 'payment_received':
                        $order->update_status('completed', __('Payment received and transaction completed.', 'woocommerce'));
                        $order->add_order_note( __( 'Payment received and transaction complete.' ) );
                        break;               
                    case 'payment_received_unconfirmed':
                        $order->update_status('on-hold', __('Payment on hold. Payment received but still unconfirmed.', 'woocommerce'));
                        $order->add_order_note( __( 'Payment on-hold. Funds received but still unconfirmed.' ) );
                        break;
                    case 'expired':
                        $order->update_status('failed', __('Payment failed. Payment request expired.', 'woocommerce'));
                        $order->add_order_note( __( 'Payment failed. Transaction request expired.' ) );
                        break; 
                    case 'pending':
                        $order->update_status('pending', __('Payment pending.', 'woocommerce'));
                        $order->add_order_note( __( 'Payment pending.' ) );
                        break;           
                }
            } 
        }
    }
}// end of WC_Gateway_PayByte class

/* 
* Enqueue js and css
*/
function paybyte_js_added_to_the_head() {
 
    wp_enqueue_script('jquery'); // Enqueue standard jquery file
    wp_register_script( 'add-bx-custom-js', plugins_url('js/custom.js', __FILE__), '', null,''  );
    wp_enqueue_script( 'add-bx-custom-js' );
    wp_register_style('my_stylesheet', plugins_url('css/sgg_style.css', __FILE__));
    wp_enqueue_style('my_stylesheet'); 
} 
add_action( 'wp_enqueue_scripts', 'paybyte_js_added_to_the_head' );

/*
* Add coin total row on thankyou page
*/
add_filter( 'woocommerce_get_order_item_totals', 'paybyte_add_custom_order_totals_row', 30, 3 );
function paybyte_add_custom_order_totals_row( $total_rows, $order) {

    $order_id = $order->get_order_number();
    $coinAmount = get_post_meta( $order_id, 'paybyte_coin_total', true );
    $coinName = get_post_meta( $order_id, 'paybyte_coin_name', true );
    
    $txt =  $coinName . ' Total:';

    // Insert a new row
    $total_rows['recurr_not'] = array(
        "label" => $txt,
        "value" => $coinAmount,
    );

    return $total_rows;
}
?>