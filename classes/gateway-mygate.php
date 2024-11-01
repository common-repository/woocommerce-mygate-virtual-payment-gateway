<?php

/**
 * Mygate Payment Gateway
 * 
 * Provides a Mygate Payment Gateway.
 *
 * @class 		woocommerce_mygate
 * @package		WooCommerce
 * @category	Payment Gateways
 */

class woocommerce_mygate extends WC_Payment_Gateway {

    var $merchant_ident;
    var $private_key;
    var $liveurl = 'https://www.mygate.co.za/virtual/8x0x0/dsp_ecommercepaymentparent.cfm';

    public function __construct() {
        global $woocommerce;
        
        $this->id = 'mygate';
        $this->method_title = __( 'MyGate', 'woothemes' );
        $this->icon = $this->plugin_url() . '/mygate.png';
        $this->has_fields = false;
        
        // Setup available countries.
        $this->available_countries = array('ZA');

        // Setup available currency codes.
        $this->available_currencies = array('ZAR');
        
        // Load the form fields
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Get setting values
        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->enabled = $this->settings['enabled'];
        $this->merchant_ident = $this->settings['merchant_ident'];
        $this->private_key = $this->settings['private_key'];

        // Hooks
        add_action( 'init', array( &$this, 'check_status_response' ) );
        add_action( 'valid-mygate-status-report', array(&$this, 'successful_request' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_receipt_mygate', array(&$this, 'receipt_page' ) );
        add_filter( 'woocommerce_currencies', array(&$this, 'add_currency' ) );
        add_filter( 'woocommerce_currency_symbol', array(&$this, 'add_currency_symbol' ) );
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    function init_form_fields() {

        $this->form_fields = array(
            'title' => array(
                'title' => __('Title', 'woothemes'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woothemes'),
                'default' => __('MyGate', 'woothemes')
            ),
            'enabled' => array(
                'title' => __('Enable/Disable', 'woothemes'),
                'label' => __('Enable MyGate', 'woothemes'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'yes'
            ),
            'description' => array(
                'title' => __('Description', 'woothemes'),
                'type' => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'woothemes'),
                'default' => 'Pay via MyGate - pay by Visa or Mastercard.'
            ),
            'merchant_ident' => array(
                'title' => __('Merchant ID', 'woothemes'),
                'type' => 'text',
                'description' => __('Please enter your MyGate Merchant ID - this is needed in order to take payment!', 'woothemes'),
                'default' => ''
            ),
            'private_key' => array(
                'title' => __('Application ID', 'woothemes'),
                'type' => 'text',
                'description' => __('Please enter your MyGate Application ID - this is needed in order to take payment!', 'woothemes'),
                'default' => ''
            ),
             'cancel_url' => array(
              'title' => __('Cancel URL', 'woothemes'),
              'type' => 'text',
              'description' => __('NB! Rather leave this blank, only use this if you want to create a page with a special error message.', 'woothemes'),
              'default' => ''
            )
        );
    }

	/**
	 * Get the plugin URL
	 *
	 * @since 1.0.0
	 */
	function plugin_url() { 
		if( isset( $this->plugin_url ) ) return $this->plugin_url;
		
		if ( is_ssl() ) {
			return $this->plugin_url = str_replace('http://', 'https://', WP_PLUGIN_URL) . "/" . plugin_basename( dirname(dirname(__FILE__))); 
		} else {
			return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename( dirname(dirname(__FILE__))); 
		}
	} // End plugin_url()

    /**
     * add_currency()
     *
     * Add the custom currencies to WooCommerce.
     *
     * @since 1.0.0
     */
    function add_currency($currencies) {
        $currencies['ZAR'] = __('South African Rand (R)', 'woothemes');
        return $currencies;
    }

    /**
     * add_currency_symbol()
     *
     * Add the custom currency symbols to WooCommerce.
     *
     * @since 1.0.0
     */
    function add_currency_symbol($symbol) {
        $currency = get_option('woocommerce_currency');
        switch ($currency) {
            case 'ZAR': $symbol = 'R';
                break;
        }
        return $symbol;
    }

    /**
     * Admin Panel Options 
     * - Options for bits like 'title' and availability on a country-by-country basis
     * */
    public function admin_options() {
        ?>
        <h3><?php _e( 'Mygate', 'woothemes' ); ?></h3>
        <p><?php _e( 'MyGate works by sending the user to <a href="https://www.mygate.co.za">Mygate</a> to enter their payment information.', 'woothemes' ); ?></p>
        <?php
    	if ( 'ZAR' == get_option( 'woocommerce_currency' ) ) {
    	?>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
            <tr valign="top">
            	<th scope="row" class="titledesc"><?php _e( 'Server Callback URL', 'woothemes' ); ?>:</th>
            	<td class="forminp"><?php echo home_url( '/?mygateListener=mygate_status' ); ?></td>
            </tr>
            <tr valign="top">
            	<th scope="row" class="titledesc"><?php _e( 'Successful Transaction URL', 'woothemes' ); ?>:</th>
            	<td class="forminp"><?php echo get_permalink( get_option( 'woocommerce_thanks_page_id' ) ); ?></td>
            </tr>
            <tr valign="top">
            	<th scope="row" class="titledesc"><?php _e( 'Cancel Transaction URL', 'woothemes' ); ?>:</th>
            	<td class="forminp"><?php echo home_url( '/' ); ?></td>
            </tr>
            <tr valign="top">
            	<th scope="row" class="titledesc"><strong><?php _e( 'Usage Instructions', 'woothemes' ); ?>:</strong></th>
            	<td class="forminp"><?php _e( 'In order to work you must have a Merchant ID and a Application ID.', 'woothemes' ); ?></td>
            </tr>
        </table><!--/.form-table-->
        <?php
		} else {
		?>
			<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woothemes' ); ?></strong> <?php echo sprintf( __( 'Choose South African Rands as your store currency in <a href="%s">Pricing Options</a> to enable the MyGate Gateway.', 'woocommerce' ), admin_url( '?page=woocommerce&tab=catalog' ) ); ?></p></div>
		<?php
		} // End check currency
		?>
        <?php
    }

    /**
     * There are no payment fields for mygate, but we want to show the description if set.
     * */
    function payment_fields() {
        if ( isset( $this->settings['description'] ) && ( '' != $this->settings['description'] ) ) {
    		echo wpautop( wptexturize( $this->settings['description'] ) );
    	}
    }

    /**
     * Admin Panel Options Processing
     * - Saves the options to the DB
     * */

    /**
     * Get the users country either from their order, or from their customer data
     */
    function get_country_code() {
        global $woocommerce;

        if ( isset( $_GET['order_id'] ) ) {

            $order = &new WC_Order( $_GET['order_id'] );

            return $order->billing_country;
        } elseif ( $woocommerce->customer->get_country() ) {

            return $woocommerce->customer->get_country();
        } else {
        	$base_country = $woocommerce->countries->get_base_country();
        	return $base_country;
        }

        return NULL;
    }

    /**
     * Check if this gateway is enabled and available in the user's country
     */
    function is_available() {

		$is_available = false;
		
        if ($this->enabled == 'yes' ) {

           $user_country = $this->get_country_code();

           $is_available = in_array( $user_country, $this->available_countries );

        }

        return $is_available;
    }

    /**
     * Validate plugin settings
     */
    function validate_settings() {
        $currency = get_option( 'woocommerce_currency' );

        if ( ! in_array( $currency, array( 'ZAR' ) ) ) {
            return false;
        }

        if ( ! $this->merchant_ident || ! $this->private_key ) {
            return false;
        }

        return true;
    }

    /**
     * Generate the mygate button link
     * */
    public function generate_mygate_form( $order_id ) {
    	global $woocommerce;
    	
        //Make sure valid country set
        $user_country = $this->get_country_code();
        if (empty($user_country)) :
            echo __('Select a country to see the payment form', 'woothemes');
            return;
        endif;
        if ( ! in_array( $user_country, $this->available_countries ) ) :
            echo __('MyGate is not available in your country.', 'woothemes');
            return;
        endif;

        // Validate plugin settings
        if (!$this->validate_settings()) :
            $cancelNote = __('Order was cancelled due to invalid settings (check your API credentials and make sure your currency is supported).', 'woothemes');
            $order->add_order_note($cancelNote);
            $woocommerce->add_error(__('Payment was rejected due to configuration error.', 'woothemes'));
            return false;
        endif;

        $order = &new WC_Order($order_id);

        $mygate_adr = $this->liveurl;

        $shipping_name = explode(' ', $order->shipping_method);

        $order_total = trim($order->order_total, 0);

        if (substr($order_total, -1) == '.')
            $order_total = str_replace('.', '', $order_total);

        //Cart Contents
        $description = '';
        $item_loop = 0;
        if (sizeof($order->items) > 0) : foreach ($order->items as $item) :
                //$_product = &new woocommerce_product($item['id']);
                $_product = &new WC_Product($item['id']);
                if ($_product->exists() && $item['qty']) :

                    $item_loop++;

                    $description .= $item['qty'] . 'x' . $_product->get_title() . ' ';

                endif;
            endforeach;
            $description = trim($description);
        endif;

		$description = get_bloginfo( 'name' ) .' Order No'. $order->id;
		$success_url = get_permalink( get_option( 'woocommerce_thanks_page_id' ) );
        $cancel_url = $this->settings['cancel_url'];
        
        if(!$cancel_url) { $cancel_url = $order->get_cancel_order_url(); }
        
         $_product = &new WC_Product($item['id']);

        if ( sizeof( $woocommerce->cart->get_cart() ) > 0 ) {
			foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values ) {
				$_product = $values['data'];
				if ( $_product->exists() && $values['quantity'] > 0 ) {
				$da_title .= $_product->get_title().'&nbsp;';
				} } };
				
		
		$mydetails = 'Paid By: '.$order->billing_first_name.' '.$order->billing_last_name.', '.$order->billing_email.', '.$order->billing_phone;
		$da_title = substr($da_title, 0,244);
		$mydetails = substr($mydetails , 0,250);
		$da_count = sizeof($woocommerce->cart->get_cart());

        return '
			<form id="mygateform" name="Checkout" method="post" action="'.$mygate_adr.'">
				<input type="hidden" name="Mode" value="1">
				<input type="hidden" name="txtMerchantID" value="'.$this->settings['merchant_ident'].'">
				<input type="hidden" name="txtApplicationID" value="'.$this->settings['private_key'].'">
				<input type="hidden" name="txtMerchantReference" value="Order'.$description.'">
				<input type="hidden" name="txtPrice" value="'.$order_total.'">
				<input type="hidden" name="txtCurrencyCode" value="ZAR">
				<input type="hidden" name="txtRedirectSuccessfulURL" value="'.$success_url.'">
				<input type="hidden" name="txtRedirectFailedURL" value="'.$cancel_url.'">
				<input type="hidden" name="Variable1" value="'.$order->id.'">
				<input type="hidden" name="Variable2" value="'.$da_title.'">
				<input type="hidden" name="txtQty1" value="'.$da_count.'">
				<input type="hidden" name="txtItemDescr1" value="'.$da_title.'">
				<input type="hidden" name="txtItemRef1" value="'.$mydetails.'">
				<input type="hidden" name="txtItemAmount1" value="'.$order_total.'">
				<input type="hidden" name="txtRecipient" value="'.$order->billing_first_name.' '.$order->billing_last_name.'">
				<input type="submit" class="button-alt button" id="submit_mygate_payment_form" value="' . __('Pay via MyGate', 'woothemes') . '" />
				<a class="button cancel" href="' .$cancel_url. '">' . __('Cancel order', 'woothemes') . '</a>
										
					<script type="text/javascript">
					jQuery( "#submit_mygate_payment_form" ).click();
					jQuery(function(){
						jQuery("body").block(
							{ 
								message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" />' . __( 'Thank you for your order. We are now redirecting you to MyGate to make payment.', 'woothemes' ) . '",
								overlayCSS:
								{ 
									background: "#fff", 
									opacity: 0.6 
								},
								css: { 
							        padding:        20,
							        textAlign:      "center",
							        color:          "#555",
							        border:         "3px solid #aaa",
							        backgroundColor:"#fff",
							        cursor:         "wait"
							    } 
							});

						});
				</script>
			</form>';
    }

    /**
     * Process the payment and return the result
     * */
    function process_payment($order_id) {

        global $woocommerce;
	
		$order = new WC_Order( $order_id );
        
        //$order = &new woocommerce_order($order_id);


        return array(
            'result' => 'success',
            'redirect' => add_query_arg( 'order', $order->id, add_query_arg( 'key', $order->order_key, get_permalink(get_option( 'woocommerce_pay_page_id' ) ) ) )
        );
    }

    /**
     * receipt_page
     * */
    function receipt_page( $order ) {
        echo '<p>' . __( 'Thank you for your order, please click the button below to pay with MyGate.', 'woothemes' ) . '</p>';
        echo $this->generate_mygate_form( $order );
    }

    /**
     * Check for MyGate Status Response
     * */
    function check_status_response() {
			
		if (isset($_REQUEST['VARIABLE1']) ) {
			
			$order_id = (int)$_SESSION['order_awaiting_payment'];
			
			if ( $order_id > 0 ) {
				// Check that we're on the correct order and it's legit.
				$order = new WC_Order( $order_id );
				
				$provided_order_key = trim( esc_attr( $_GET['t'] ) );
				
				// Cancel the order if the keys match. Redirect to order cancel screen.
				if ( $provided_order_key == $order->order_key ) {
					$cancel_url = $order->get_cancel_order_url();
					wp_redirect( $cancel_url );
				}
			}
        }

        if (isset($_POST['VARIABLE1']) && is_numeric($_POST['VARIABLE1']) && isset($_POST['_RESULT'])) {
            $_POST = stripslashes_deep($_POST);			
            do_action("valid-mygate-status-report", $_POST);
        }
    }

    /**
     * Successful Payment!
     * */
    function successful_request($posted) {
        // Custom holds post ID
        if (!empty($posted['VARIABLE1'])) {

            $order = new WC_Order((int) $posted['VARIABLE1']);

            if ($order->status !== 'completed') {
                // We are here so lets check status and do actions
               if ($posted['_RESULT'] >=0) {
                    $order->add_order_note(__('MyGate payment completed', 'woothemes'));
                    $order->payment_complete();
                } else {
                    $order->update_status('failed', sprintf(__('MyGate payment failed!', 'woothemes')));
                }
            }
        }
    }

}

/** Add the gateway to WooCommerce * */
function add_mygate_gateway($methods) {
    $methods[] = 'woocommerce_mygate';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_mygate_gateway');