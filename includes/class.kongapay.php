<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_KongaPay_Gateway extends WC_Payment_Gateway {

    private $testmode;

    private $private_key;

    private $public_key;

    private $merchant_id;

    private $enable_frame;

    /**
     * Class constructor
     */
    public function __construct() {

        $this->id = 'kongapay';
        $this->has_fields = true;
        $this->method_title = 'KongaPay Gateway';
        $this->method_description = 'Enable payment on your website with Cards, Bank Accounts, KongaPay Wallet, USSD, Visa QR and Pay Attitude.';

        $this->supports = array(
            'products'
        );

        // Method with all the options fields
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();
        $this->title = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled = $this->get_option( 'enabled' );
        $this->testmode = 'yes' === $this->get_option( 'testmode' );
        $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'live_private_key' );
        $this->public_key = $this->testmode ? $this->get_option( 'test_public_key' ) : $this->get_option( 'live_public_key' );
        $this->merchant_id = $this->get_option( 'merchant_id' );
        $this->enable_frame = 'yes' === $this->get_option('enable_frame');

        // This action hook saves the settings
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // We need custom JavaScript to obtain a token
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'payment_page' ) );

        // Payment listener/API hook
        add_action( 'woocommerce_api_verify_wc_kongapay_gateway', array( $this, 'verify_transaction' ) );

    }

    /**
     * Display KongaPay payment icon
     */
    public function get_icon() {

        $icon  = '<img
                        src="' . WC_HTTPS::force_https_url( plugins_url( '../assets/images/kongapay-payment-gw@2x.png' , __FILE__ ) ) . '"
                        alt="KongaPay For WooCommerce"
                        height="40px"
                        width="360px"
                        style="max-height: none; margin-top: 10px;"
                    />'
        ;

        return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );

    }

    /**
     * Plugin options
     */
    public function init_form_fields(){

        $this->form_fields = array(
            'enabled' => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable KongaPay Gateway',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'KongaPay Payment Gateway',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default'     => 'Pay with your Cards, Bank Accounts, KongaPay Wallet, USSD, Visa QR and Pay Attitude via KongaPay Payment Gateway.',
            ),
            'testmode' => array(
                'title'       => 'Test mode',
                'label'       => 'Enable Test Mode',
                'type'        => 'checkbox',
                'description' => 'Place the payment gateway in test mode using test API keys.',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'enable_frame' => array(
                'title'       => 'iframe',
                'label'       => 'Enable Iframe',
                'type'        => 'checkbox',
                'description' => 'Display payment gateway in an iframe or redirect.',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'merchant_id' => array(
                'title'       => 'Merchant Id',
                'type'        => 'text'
            ),
            'test_public_key' => array(
                'title'       => 'Test Public Key',
                'type'        => 'text'
            ),
            'test_private_key' => array(
                'title'       => 'Test Private Key',
                'type'        => 'password',
            ),
            'live_public_key' => array(
                'title'       => 'Live Public Key',
                'type'        => 'text'
            ),
            'live_private_key' => array(
                'title'       => 'Live Private Key',
                'type'        => 'password',
            ),
        );

    }


    public function payment_fields() {

        if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
            return;
        }

        // if our payment gateway is disabled
        if ( 'no' === $this->enabled ) {
            return;
        }

        if ( empty( $this->private_key ) || empty( $this->public_key ) ) {
            return;
        }

    }

    public function payment_scripts() {

        if ( ! is_checkout_pay_page() ) {
            return;
        }

        if ( $this->enabled === 'no' ) {
            return;
        }

        $order_key 		= urldecode( $_GET['key'] );
        $order_id  		= absint( get_query_var( 'order-pay' ) );

        $order  		= wc_get_order( $order_id );

        $payment_method = method_exists( $order, 'get_payment_method' ) ? $order->get_payment_method() : $order->payment_method;

        if( $this->id !== $payment_method ) {
            return;
        }

        $the_order_id 	= method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
        $the_order_key 	= method_exists( $order, 'get_order_key' ) ? $order->get_order_key() : $order->order_key;

        if ( $the_order_id != $order_id || $the_order_key != $order_key ) {
            return;
        }

        wp_enqueue_script( 'jquery' );

        wp_enqueue_script( 'kongapay_gateway', 'https://kongapay-pg.kongapay.com/js/v1/production/pg.js' );

        wp_enqueue_script( 'woocommerce_kongapay', plugins_url( '../assets/js/kongapay.js', __FILE__ ), array( 'jquery', 'kongapay_gateway' ) );

        $kongapay_params = array();

        $email  		= method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;

        $amount 		= $order->get_total() * 100;

        $reference		= $order_id . '_' .time();

        $hash           = hash('sha512', "$amount|$this->public_key|$reference");


        $kongapay_params['hash']                = $hash;
        $kongapay_params['email'] 				= $email;
        $kongapay_params['amount']  			= $amount;
        $kongapay_params['reference']  			= $reference;
        $kongapay_params['currency']  			= get_woocommerce_currency();
        $kongapay_params['merchant_id']         = $this->merchant_id;
        $kongapay_params['description']         = "Payment for {$order_id}";
        $kongapay_params['customer_id']         = $email;
        $kongapay_params['enable_frame']        = $this->enable_frame;
        $kongapay_params['callback_url']        = WC()->api_request_url( 'Verify_WC_Kongapay_Gateway' );
        $kongapay_params['first_name']  	    = method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name;
        $kongapay_params['last_name']  	        = method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name;
        $kongapay_params['phone']  	            = method_exists( $order, 'get_billing_phone' ) ? $order->get_billing_phone() : $order->billing_phone;

        update_post_meta( $order_id, '_kongapay_txn_ref', $reference );

        wp_localize_script( 'woocommerce_kongapay', 'kongapay_params', $kongapay_params);

    }

    public function process_payment( $order_id ) {

        $order = wc_get_order( $order_id );

        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url( true )
        );

    }

    public function payment_page( $order_id ) {

        $order = wc_get_order( $order_id );

        echo '<p>Thank you for your order, please click the Pay button below to pay.</p>';

        echo '<div id="kongapay_form"><form id="order_review" method="post" action="'. WC()->api_request_url( 'WC_KongaPay_Gateway' ) .'"></form><button class="button alt" id="kongapay-payment-button">Pay Now</button> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">Cancel order &amp; restore cart</a></div>
				';

    }

    public function verify_transaction()
    {
        @ob_clean();

        if ( !isset( $_REQUEST['merchant_reference'] ) ) {
            wp_redirect( wc_get_page_permalink( 'cart' ) );

            exit;
        }

        $reference = trim($_REQUEST['merchant_reference']);

        $order_details 	= explode( '_', $reference );

        $order_id 		= (int) $order_details[0];

        $order 			= wc_get_order( $order_id );

        if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {

            wp_redirect( $this->get_return_url( $order ) );

            exit;

        }

        $requeryUrl = "https://ignite.kongapay.com/v1/payment/requery/{$reference}";
        $headers = array(
            'Authorization' => 'Bearer ' . $this->private_key
        );

        $args = array(
            'headers'	=> $headers,
            'timeout'	=> 60
        );

        $request = wp_remote_get( $requeryUrl, $args );

        if ( is_wp_error( $request ) ) {
            wp_redirect( $this->get_return_url( $order ) );

            exit;
        }

        $response = json_decode( wp_remote_retrieve_body( $request ) );

        if ( 'success' != $response->status ) {
            $order->update_status( 'failed', "Payment failed on KongaPay Gateway. Error Message - {$response->message}" );
            wp_redirect( $this->get_return_url( $order ) );

            exit;
        }

        if ( $response->data->charge->successful == true) {

            $order_total        = $order->get_total();

            $amount_paid        = $response->data->amount / 100;

            $receipt_number       = $response->data->identifier;

            // check if the amount paid is equal to the order amount.
            if ( $amount_paid < $order_total ) {

                $order->update_status( 'on-hold', '' );

                add_post_meta( $order_id, '_transaction_id', $receipt_number, true );

                $notice = 'Thank you for shopping with us.<br />Your payment transaction was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
                $notice_type = 'notice';

                // Add Customer Order Note
                $order->add_order_note( $notice, 1 );

                // Add Admin Order Note
                $order->add_order_note( '<strong>Look into this order</strong><br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was <strong>&#8358;'.$amount_paid.'</strong> while the total order amount is <strong>&#8358;'.$order_total.'</strong><br />KongaPay Receipt Reference: '.$receipt_number );

                function_exists( 'wc_reduce_stock_levels' ) ? wc_reduce_stock_levels( $order_id ) : $order->reduce_order_stock();

                wc_add_notice( $notice, $notice_type );

            } else {

                $order->payment_complete( $receipt_number );

                $order->add_order_note( sprintf( 'Payment via KongaPay Gateway successful (Receipt Reference: %s)', $receipt_number ) );

            }

            wc_empty_cart();

        } else {

            $order 			= wc_get_order( $order_id );

            $order->update_status( 'failed', "Payment failed. Status: {$response->data->charge->status}." );

        }

        wp_redirect( $this->get_return_url( $order ) );

        exit;
    }

}
