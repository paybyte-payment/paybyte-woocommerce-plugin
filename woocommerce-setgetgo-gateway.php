<?php

/* SetGetGo Payment Gateway Class */
class WC_Gateway_Custom extends WC_Payment_Gateway {

    public $domain;

    /**
     * Constructor for the gateway.
     */
    public function __construct() {

        $plugin_dir = plugin_dir_url(__FILE__);

        $this->domain = 'custom_payment';

        $this->id                 = 'custom';
        $this->icon               = apply_filters('woocommerce_custom_gateway_icon',  $plugin_dir.'/img/ssg_logo.png');
        $this->has_fields         = false;
        $this->method_title       = __( 'SetGetGo Payment', $this->domain );
        $this->method_description = __( 'Allows payments with SetGetGo gateway.', $this->domain );

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
        $this->merch_addr = $this->get_option( 'merch_addr');
        $this->order_status = $this->get_option( 'order_status', 'pending' );

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_thankyou_custom', array( $this, 'thankyou_page' ) );

        // Lets check for SSL
        add_action( 'admin_notices', array( $this,  'do_ssl_check' ) );
    }

    /**
     * Build the administration fields for this specific Gateway
     */
    public function init_form_fields() {

        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', $this->domain ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable SetGetgo Payment', $this->domain ),
                'default' => 'no'
            ),
            'title' => array(
                'title'       => __( 'Title', $this->domain ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', $this->domain ),
                'default'     => __( 'SetGetgo Payment', $this->domain ),
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
            'merch_addr' => array(
                'title'       => __( 'Merchant address', $this->domain ),
                'type'        => 'text',
                'description' => __( 'Bitcoin merchant address.', $this->domain ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'isTestnet' => array(
                'title'     => __( 'SetGetGo Testnet' ),
                'label'     => __( 'Enable Testnet payments' ),
                'type'      => 'checkbox',
                'description' => __( 'Place the payment gateway in test mode.'),
                'default'   => 'no',
            ),
            'description' => array(
                'title'       => __( 'Description', $this->domain ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that the customer will see on your checkout.', $this->domain ),
                'default'     => __('Payment Information', $this->domain),
                'desc_tip'    => true,
            )
        );
    }

    
    /* function to display text and BTC attributes on checkout page*/
    public function payment_fields(){
        global $total_amt;

        if ( $description = $this->get_description() ) {
            echo wpautop( wptexturize( $description ) );
          
            $currency =  get_woocommerce_currency();

            $url = "https://setgetgo.com/api/get-rate?currency=".$currency;

           // Send this payload to SetGetGo for processing
            $response = wp_remote_get( $url);
            
            // Retrieve the body's resopnse if no errors found
            $response_body = wp_remote_retrieve_body( $response );

            // Parse the json response into array
            $json_response = json_decode($response_body,true);

            $btc_rate = $json_response['rate']['btc_rate'];
            $cart_total = WC()->cart->total;
            $total_amt = $btc_rate * $cart_total;
            $total_amt = number_format($total_amt, 4, '.', '');
            $btc_rate_dec = number_format($btc_rate, 5, '.', '');
            echo "<p> BTC exchange rate is <b>BTC ".$btc_rate_dec."</b>";
            echo "<input type='hidden' id='btc_amt' name='btc_amt' value='".$total_amt."' />";
            return $total_amt;
           
        }
    }

    /**
     * Prepares the create payment URL.
     *
     * @param WC_Order $customer_order
     * @return string
     */
    public function prepare_create_payment_url($customer_order, $order_id) {

        error_log("preparing create payment url..");
        $merchant_address = $this->merch_addr;
        $amount = $this->payment_fields();
        // get payment mode from settings saved in database
        $options =  get_option('woocommerce_custom_settings');
        $testnet = ($options['isTestnet']=="yes") ? "&testnet=true" : "";

        global $wp;
        $callback_url = urlencode( home_url( $wp->request ).plugins_url( 'callback.php', __FILE__ )."?order_id=".$order_id);
        $return_url = urlencode( WC_Payment_Gateway::get_return_url( $customer_order ));        
        $create_payment_url = "https://setgetgo.com/api/create-payment?amount=".$amount."&merch_addr=".$merchant_address.$testnet."&callback=".$callback_url."&returnUrl=".$return_url;
              
        error_log("preparing create payment url: ". $create_payment_url);

        return $create_payment_url;
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment( $order_id ) {

        global $woocommerce;

        // Get this Order's information so that we know
        // who to charge and how much
        $customer_order = new WC_Order( $order_id );
 
        $create_payment_url = $this->prepare_create_payment_url($customer_order, $order_id);
       
        // Send this payload to SetGetGo for processing
        $response = wp_remote_get( $create_payment_url);

        if ( is_wp_error( $response ) ) 
            throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.' ) );

        if ( empty( $response['body'] ) )
            throw new Exception( __( 'SetGetGo\'s Response was empty.' ) );
            
        // Retrieve the body's resopnse if no errors found
        $response_body = wp_remote_retrieve_body( $response );

        // Parse the json response into array
        $json_response = json_decode($response_body,true);

        echo($json_response);

        $payment_address = $json_response['transaction']['payment-address'];
        $res_merchant_address = $json_response['transaction']['merchant-address'];
        $tran_status = $json_response['transaction']['status'];
        $total_amt = $json_response['transaction']['amount'];
        $amount_received = $json_response['transaction']['amount-received'];
        $received_payment_url = $json_response['transaction']['payment-url'];
        $response_error = $json_response['error'];

        if ($response_error != "ok") {
            wc_add_notice( $response_error, 'error' );
            //Add note to the order for your reference
            $customer_order->add_order_note( 'Error: '. $response_error);

            $customer_order->update_status('failed', __( 'SetGetgo payment failed', 'woocommerce' ));
        }
        else {

            global $wpdb;
            $insert = $wpdb->insert('wp_setgetgo_payment',
                    array(
                            'user_id' => get_current_user_id(),
                            'order_id' => $order_id,
                            'payment_address' => $payment_address,
                            'merchant_address' => $res_merchant_address,
                            'status' => $tran_status,
                            'amount' => $total_amt,
                            'amount_received' => $amount_received,
                            'created' => date('Y-m-d H:i:s')
                    )
            );
                        
            if ($insert == 1) {

                // Payment has been successful
                $customer_order->add_order_note( __( 'SetGetgo payment initiated.' ) );

                /* Save payment url in meta field*/
                update_post_meta( $order_id, 'payment_url', $received_payment_url ); 

                /* Save BTC Total in meta field*/
                update_post_meta( $order_id, 'btc_total', $amount);
               
                $customer_order->update_status('pending', __( 'Awaiting SetGetgo payment', 'woocommerce' ));

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
                $customer_order->update_status('failed', __( 'SetGetgo payment failed', 'woocommerce' ));
            }
        }
    }


    // Check if we are forcing SSL on checkout pages
    // Custom function not required by the Gateway
    public function do_ssl_check() {
        if( $this->enabled == "yes" ) {
            if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
                echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";  
            }
        }       
    }

}// end of WC_Gateway_Custom class

/* 
* Enqueue js and css
*/
 function js_added_to_the_head() {
 
    wp_enqueue_script('jquery'); // Enqueue standard jquery file
    wp_register_script( 'add-bx-custom-js', plugins_url('js/custom.js', __FILE__), '', null,''  );
    wp_enqueue_script( 'add-bx-custom-js' );
    wp_register_style('my_stylesheet', plugins_url('css/sgg_style.css', __FILE__));
    wp_enqueue_style('my_stylesheet'); 
} 
add_action( 'wp_enqueue_scripts', 'js_added_to_the_head' );
/* Ends here */


/*
* Add BTC total row on thankyou page
*/
add_filter( 'woocommerce_get_order_item_totals', 'add_custom_order_totals_row', 30, 3 );
function add_custom_order_totals_row( $total_rows, $order) {

    $order_id = $order->get_order_number();
    $get_btc_total= get_post_meta($order_id);
    $btc_total = $get_btc_total['btc_total'][0];
    
    // Insert a new row
    $total_rows['recurr_not'] = array(
        "label" => __( 'BTC Total :', 'woocommerce' ),
        "value" => $btc_total,
    );

    return $total_rows;
}


?>