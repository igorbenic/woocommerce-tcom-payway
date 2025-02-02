<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/*
Date: October 2015
Plugin Name: T-Com PayWay
Plugin URI: https://github.com/marinsagovac/woocommerce-tcom-payway
Description: T-Com PayWay payment gateway
Version: 0.3
Author: Marin Sagovac (Marin Šagovac)
* 
* Changed from old payway to new pgw gateway T-Com Croatia Payway service
* SHA512 check and get customs data
* Autoform submit on review payment (skip reviewing)
* T-Com PayWay logotype and card types
* Added autoform submit on checkout
* Added URL for EUR (en_US in URI) currency with conversion from HRK
* Clean and fix forms
*/

add_action('plugins_loaded', 'woocommerce_tpayway_gateway', 0);

function woocommerce_tpayway_gateway(){
  if(!class_exists('WC_Payment_Gateway')) return;

  class WC_TPAYWAY extends WC_Payment_Gateway{
    public function __construct(){
	  $plugin_dir = plugin_dir_url(__FILE__);
      $this->id = 'TPAYWAY';	  
	  $this->icon = apply_filters('woocommerce_Paysecure_icon', ''.$plugin_dir.'payway.png');
      $this->method_title = 'T-Com PayWay';
      $this->has_fields = false;
 
      $this->init_form_fields();
      $this->init_settings(); 
	  
      $this->title 				    	 = $this -> settings['title'];
      $this->description 		     	 = $this -> settings['description'];      
      $this->CurrencyEn 			     = $this -> settings['CurrencyEn'];
      $this->ShopID 				 	 = $this -> settings['MerID'];
      $this->AcqID 				    	 = $this -> settings['AcqID'];
      $this->ResponseCode 			     = $this -> settings['ResponseCode'];
      $this->pg_domain 			         = $this-> settings['pg_domain'];      	  
	  $this->responce_url_sucess		 = $this-> settings['responce_url_sucess'];
	  $this->responce_url_fail		     = $this-> settings['responce_url_fail'];	  
      $this->checkout_msg			     = $this-> settings['checkout_msg'];
      
      $this->msg['message'] 	= "";
      $this->msg['class'] 		= "";
 
      add_action('init', array(&$this, 'check_TPAYWAY_response'));	  
	  	  
		if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
        	add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( &$this, 'process_admin_options' ) );
		} else {
            add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
        }
      add_action('woocommerce_receipt_TPAYWAY', array(&$this, 'receipt_page'));
	 
   }
	
    function init_form_fields(){
 
       $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'ogn'),
                    'type' => 'checkbox',
                    'label' => __('Enable T-Com PayWay Module.', 'ognro'),
                    'default' => 'no'),
					
                'title' => array(
                    'title' => __('Title:', 'ognro'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'ognro'),
                    'default' => __('T-Com PayWay', 'ognro')),
				
				'description' => array(
                    'title' => __('Description:', 'ognro'),
                    'type'=> 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'ognro'),
                    'default' => __('T-Com Payway is secure payment gateway in Croatia and you can pay using this payment in other currency.', 'ognro')),	
           
				'pg_domain' => array(
                    'title' => __('Authorize URL:', 'ognro'),
                    'type'=> 'text',
                    'description' => __('T-Com PayWay data submiting to this URL', 'ognro'),
                    //'default' => __('https://pgw.t-com.hr/payment.aspx', 'ognro')),   // old
                    'default' => __('https://pgw.ht.hr/services/payment/api/authorize-form', 'ognro')),  
           
				'MerID' => array(
                    'title' => __('Shop ID:', 'ognro'),
                    'type'=> 'text',
                    'description' => __('Unique id for the merchant acc, given by bank.', 'ognro'),
                    'default' => __('', 'ognro')),
				
				'AcqID' => array(
                    'title' => __('Secret Key:', 'ognro'),
                    'type'=> 'text',
                    'description' => __('', 'ognro'),
                    'default' => __('', 'ognro')),
           
					
				'CurrencyEn' => array(
                    'title' => __('Valuta (EUR -> HRK):', 'ognro'),
                    'type'=> 'text',
                    'description' => __('Change your default currency from EUR', 'ognro'),
                    'default' => __('7.4', 'ognro')),
                                       
                 'responce_url_sucess' => array(
                    'title' => __('Response URL success', 'ognro'),
                    'type'=> 'text',
                    'description' => __('', 'ognro'),
                    'default' => __('', 'ognro')),
                 
                 'responce_url_fail' => array(
                    'title' => __('Response URL fail:', 'ognro'),
                    'type'=> 'text',
                    'description' => __('', 'ognro'),
                    'default' => __('', 'ognro')),
                    
                 'checkout_msg' => array(
                    'title' => __('Message redirect:', 'ognro'),
                    'type'=> 'textarea',
                    'description' => __('Message to client when redirecting to PayWay page', 'ognro'),
                    'default' => __('', 'ognro'))
                                           	
            );
    }
 
	public function admin_options(){
    	echo '<h3>'.__('T-Com PayWay payment gateway', 'ognro').'</h3>';
        echo '<p>'.__('<a target="_blank" href="http://pgw.t-com.hr/">T-Com PayWay</a> is payment gateway from telecom T-Com who provides payment gateway services as dedicated services to clients in Croatia.').'</p>';
        echo '<table class="form-table">';        
        $this->generate_settings_html();
        echo '</table>'; 
    }
	

    function payment_fields(){
        if($this -> description) echo wpautop(wptexturize($this -> description));
    }

    function receipt_page($order){        		
		global $woocommerce;
                
        $order_details = new WC_Order($order);
        
        echo $this->generate_ipg_form($order);		
		echo '<br>'.$this->checkout_msg.'</b>';        
    }
    	
    public function generate_ipg_form($order_id){
        
        global $wpdb;
        global $woocommerce;
 
        $order = new WC_Order($order_id);
		$productinfo = "Order $order_id"; 
		
		
		$curr_symbole 	= get_woocommerce_currency();		
								
		$table_name = $wpdb->prefix . 'tpayway_ipg';		
		$check_oder = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE transaction_id = '".$order_id."'" );		
		if($check_oder > 0){
			$wpdb->update( 
				$table_name, 
				array(					
					'response_code' => '',
					'response_code_desc' => '',					
					'reason_code' => '',
                    'amount' => ($order -> order_total),
                    'or_date' => date('Y-m-d'),
					'status' => ''					
				), 
				array( 'transaction_id' => $order_id ));                
		}else{
			
			$wpdb->insert($table_name, array( 'transaction_id'=>$order_id, 'response_code'=>'', 'response_code_desc'=>'', 'reason_code'=>'', 'amount'=>$order -> order_total, 'or_date' => date('Y-m-d'),'status'=>''), array( '%s', '%d' ) );					
		}	
		                
		if ((bool)stristr($_SERVER["REQUEST_URI"], "en_US"))
		{
			if (is_null($this->CurrencyEn))
			{
				$order -> order_total  = $order -> order_total;
			}
			else
			{
				$order -> order_total  = $order -> order_total * $this->CurrencyEn;
			}
		}
		                
        $order_format_value = str_pad(($order -> order_total * 100), 12, '0', STR_PAD_LEFT);        											
        //$enc = base64_encode(pack('H*', sha1($pass)));        
        
        $totalAmount = number_format($order->order_total, 2, '', '');
        
        // http://docs.woothemes.com/wc-apidocs/class-WC_Customer.html
        
        $method = 'authorize-form'; // method type
        $pgwInstallments = '1'; // broj rata
        $pgw_card_type_id = '1'; // tip kartice
        
        
        $secret_key = $this->AcqID; // Secret key
        $pgw_authorization_type = '0';
        $pgw_language = '';
        
        $pgw_shop_id = $this -> ShopID;
        $pgw_order_id = $order_id;
        $pgw_amount = $totalAmount;
        
        $pgw_success_url = $this -> responce_url_sucess;
        $pgw_failure_url = $this -> responce_url_fail;
        
        // Customs data
        $pgw_street = $woocommerce->customer->address;
        $pgw_city = $woocommerce->customer->city;
        $pgw_post_code = $woocommerce->customer->postcode;
        $pgw_country = $woocommerce->customer->country;
        
        $pgw_signature = hash('sha512', 
			$method.$secret_key.
			$pgw_shop_id.$secret_key.
			$pgw_order_id.$secret_key.
			$pgw_amount.$secret_key.
			$pgw_authorization_type.$secret_key.
			$pgw_language.$secret_key.
			$pgw_success_url.$secret_key.
			$pgw_failure_url.$secret_key.
			$pgw_street.$secret_key.
			$pgw_city.$secret_key.
			$pgw_post_code.$secret_key.
			$pgw_country.$secret_key
		);
        
        $form_args = array(
          'Version' => $this -> Version,
          'pgw_shop_id'   => $pgw_shop_id,
          'pgw_order_id' => $pgw_order_id,
          'pgw_amount' => $pgw_amount,
          'pgw_authorization_type' => $pgw_authorization_type,
            
          'pgw_success_url' => $this->responce_url_sucess,
          'pgw_failure_url' => $this->responce_url_fail,
          
          'pgw_language' => $pgw_language,
            
          'pgw_signature' => $pgw_signature,
          
          'pgw_street' => $pgw_street,
          'pgw_city' => $pgw_city,
          'pgw_post_code' => $pgw_post_code,
          'pgw_country' => $pgw_country,
								
		  //old payway, deprecated
			  //md5($this->ShopID.$this->AcqID.$order_id.$this->AcqID.$totalAmount.$this->AcqID),   
				
			  //'pgw_first_name' => '', // $order
			  //'pgw_last_name' => '',
			  //'pgw_street' => $woocommerce->customer->address,
			  //'pgw_city' => $woocommerce->customer->city,
			  //'pgw_postal_code' => $woocommerce->customer->postcode,
			  //'pgw_country' => $woocommerce->customer->country,
			  //'pgw_telephone' => '',
			  //'pgw_email' => '',
				
			  //'PaymentType' => 'manual', // dodati u option
			  //'pgw_disable_installments' => 'N',
			  
			   //'Curr' => '',
			   //'Lang' => 'EN', 'curr' => 'y',
		   // old payway, deprecated END
            
          'AcqID'   => $this >AcqID, // secret key
          
          'PurchaseAmt' => $order_format_value,
           
           
		);
		  
        $form_args_array = array();
        $form_args_joins = null;
        foreach($form_args as $key => $value){
          $form_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
          $form_args_joins = $key.'='.$value.'&';
        }
        
        return '<p>'.$percentage_msg.'</p>
		<p>Total amount will be <b>'.number_format(($order->order_total)).' '.$curr_symbole.'</b></p>
		<form action="'.$this->pg_domain.'" method="post" name="payway-authorize-form" id="payway-authorize-form" type="application/x-www-form-urlencoded">
            ' . implode('', $form_args_array) . '
            <input type="submit" class="button-alt" id="submit_ipg_payment_form" value="'.__('Pay via PayWay', 'ognro').'" /> 
			<a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'ognro').'</a>            
            </form>
            <!-- autoform submit -->
            <script type="text/javascript">
				jQuery("#submit_ipg_payment_form").trigger("click");
            </script>
            ';
             
    }
    	
    function process_payment($order_id){
        $order = new WC_Order($order_id);
        return array('result' => 'success', 'redirect' => add_query_arg('order',           
		   $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
        );
    }
 
   	 
    function check_TPAYWAY_response(){				
        global $woocommerce;
        
		if(isset($_POST['ResponseCode']) && isset($_POST['OrderID']) && isset($_POST['ReasonCode'])){			
			$order_id = $_POST['OrderID'];
			
			if($order_id != ''){
				$order 	= new WC_Order($order_id);				
				$amount = $_POST['amount'];
				$status = $_POST['status'];
				if($this->sucess_responce_code == $_POST['ResponseCode']){

				global $wpdb;		
				$table_name = $wpdb->prefix . 'tpayway_ipg';	
				$wpdb->update( 
				$table_name, 
				array( 
					'response_code' => $_POST['ResponseCode'],
				    'response_code_desc' => '',					
				    'reason_code' => $_POST['ReasonCode'],                
				    'status' => ''
				), 
				array( 'merchant_reference_no' => $_POST["OrderID"] ));             
                                	
                    $order->add_order_note('T-Com PAYWAY payment successful<br/>Unnique Id: '.$_POST['transaction_id']);
                    $order->add_order_note($this->msg['message']);
                    $woocommerce->cart->empty_cart();
					
					$mailer = $woocommerce->mailer();

					$admin_email = get_option( 'admin_email', '' );

$message = $mailer->wrap_message(__( 'Order confirmed','woocommerce'),sprintf(__('Order %s has been marked on-hold due to a reversal - Reason code: %s', 'woocommerce' ), $order->get_order_number(), $posted['reason_code']));	
$mailer->send( $admin_email, sprintf( __( 'Payment for order %s confirmed', 'woocommerce' ), $order->get_order_number() ), $message );					
					
					
$message = $mailer->wrap_message(__( 'Order confirmed','woocommerce'),sprintf(__('Order %s has been marked on-hold due to a reversal - Reason code: %s', 'woocommerce' ), $order->get_order_number(), $posted['reason_code']));	
$mailer->send( $order->billing_email, sprintf( __( 'Payment for order %s confirmed', 'woocommerce' ), $order->get_order_number() ), $message );

					$order->payment_complete();							

					wp_redirect( $this->responce_url_sucess, 200 ); exit;
					
				}else{
					$order->update_status('failed');
                    $order->add_order_note('Failed - Code'.$_POST['ReasonCodeDesc']);
                    $order->add_order_note($this->msg['message']);
					
					global $wpdb;		
					$table_name = $wpdb->prefix . 'tpayway_ipg';	
					$wpdb->update( 
					$table_name, 
					array( 
						'response_code' => $_POST['ResponseCode'],
				        'response_code_desc' => '',					
				        'reason_code' => $_POST['ReasonCode'],                
				        'status' => ''
					), 
					array( 'merchant_reference_no' => $_POST["OrderID"] ));
					
					wp_redirect( $this->responce_url_fail, 200 ); exit;
				}
			}
			
		}
    }
    
    function get_pages($title = false, $indent = true) {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';            
            if ($indent) {
                $has_parent = $page->post_parent;
                while($has_parent) {
                    $prefix .=  ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }            
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }
}


if(isset($_POST['ResponseCode']) && isset($_POST['ReasonCode']) && isset($_POST['OrderID'])){
	$WC = new WC_TPAYWAY();
}

   
   function woocommerce_add_tpayway_gateway($methods) {
       $methods[] = 'WC_TPAYWAY';
       return $methods;
   }
	 	
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_tpayway_gateway' );
}

	global $jal_db_version;
	$jal_db_version = '0.1';
	
	function jal_install_tpayway() {		
		global $wpdb;
		global $jal_db_version;
	
		$table_name = $wpdb->prefix . 'tpayway_ipg';
		$charset_collate = '';
	
		if ( ! empty( $wpdb->charset ) ) {
		  $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		}
	
		if ( ! empty( $wpdb->collate ) ) {
		  $charset_collate .= " COLLATE {$wpdb->collate}";
		}
	
		$sql = "CREATE TABLE $table_name (
					id int(9) NOT NULL AUTO_INCREMENT,
					transaction_id int(9) NOT NULL,					
					response_code VARCHAR(20) NOT NULL,
					response_code_desc int(6) NOT NULL,										
					reason_code VARCHAR(20) NOT NULL,
                    amount VARCHAR(20) NOT NULL,
                    or_date DATE NOT NULL,                    
                    status int(6) NOT NULL,					
					UNIQUE KEY id (id)
				) $charset_collate;";
				
	
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	
		add_option( 'jal_db_version', $jal_db_version );
	}
	
	function jal_install_data_tpayway() {
		global $wpdb;
		
		$welcome_name = 'T-Com PayWay';
		$welcome_text = 'Congratulations, you just completed the installation!';
		
		$table_name = $wpdb->prefix . 'tpayway_ipg';
		
		$wpdb->insert( 
			$table_name, 
			array( 
				'time' => current_time( 'mysql' ), 
				'name' => $welcome_name, 
				'text' => $welcome_text, 
			) 
		);
	}
	
	register_activation_hook( __FILE__, 'jal_install_tpayway' );
	register_activation_hook( __FILE__, 'jal_install_data_tpayway' );
