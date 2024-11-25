<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Novapay Payment Gateway Class
 */
class WC_Gateway_Novapay extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'novapay';
        $this->icon               = ''; // URL of the icon that will be displayed
        $this->has_fields         = false;
        $this->method_title       = __( 'Novapay', 'wc-novapay' );
        $this->method_description = __( 'Allows payments with Novapay.', 'wc-novapay' );

		
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user settings
        $this->title        = $this->get_option( 'title' );
        $this->description  = $this->get_option( 'description' );
        $this->api_key      = $this->get_option( 'api_key' );
		$this->private_key      = $this->get_option( 'private_key' );
        $this->contragent_id   = $this->get_option( 'contragent_id' );
        $this->afb_passphrase   = $this->get_option( 'afb_passphrase' );
		
		
		// properties for payment
		$this->session_id = "";
		$this->x_sign = "";
		

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
        add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'check_ipn_response' ) );

        // Payment listener/API hook
        add_action( 'woocommerce_api_wc_gateway_novapay', array( $this, 'check_ipn_response' ) );
		
		 // Support WooCommerce Blocks
        add_filter( 'woocommerce_payment_gateway_supports', array( $this, 'add_woocommerce_blocks_support' ), 10, 3 );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'add_woocommerce_blocks_support' ) );
		
		
		add_action('rest_api_init', function () {

			register_rest_route('afb-novapay', '/approve-payment', array(
				'methods' => 'POST',
				'callback' => array($this , 'handle_approve_payment'),
				'permission_callback' => function() {
					return current_user_can('edit_shop_orders');
				},
			));
			
		});

          
    }
	
	

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __( 'Enable/Disable', 'wc-novapay' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Novapay Payment', 'wc-novapay' ),
                'default'     => 'yes',
            ),
            'title' => array(
                'title'       => __( 'Title', 'wc-novapay' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'wc-novapay' ),
                'default'     => __( 'Novapay Payment', 'wc-novapay' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'wc-novapay' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'wc-novapay' ),
                'default'     => __( 'Pay securely using Novapay.', 'wc-novapay' ),
                'desc_tip'    => true,
            ),
            'api_key' => array(
                'title'       => __( 'RSA public key', 'wc-novapay' ),
                'type'        => 'textarea',
                'description' => __( 'Get your RSA public key from your Novapay account.', 'wc-novapay' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
			'private_key' => array(
                'title'       => __( 'RSA Private key', 'wc-novapay' ),
                'type'        => 'textarea',
                'description' => __( 'Get your RSA Private key from your Novapay account.', 'wc-novapay' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'contragent_id' => array(
                'title'       => __( 'Merchant ID ', 'wc-novapay' ),
                'type'        => 'text',
                'description' => __( 'Get your Merchant ID from your Novapay account.', 'wc-novapay' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
			
			 'afb_passphrase' => array(
                'title'       => __( 'Private Key Paraphrase', 'wc-novapay' ),
                'type'        => 'text',
                'description' => __( 'Your Private Key Paraphrase.', 'wc-novapay' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
			
			
			
        );
    }
	
	
	/**
	 * Adds support for WooCommerce blocks.
	 */
	function add_woocommerce_blocks_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		
		    include_once 'class-wc-novapay-block-support.php';

			add_filter(
				'woocommerce_payment_method_type_registration',
				function( $payment_method_types ) {
					$payment_method_types['novapay'] = WC_Gateway_Novapay_Blocks_Support::class;
					return $payment_method_types;
				}
			);
		}
	}

	


    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        //  1: Authenticate 
        $session_id = $this->authenticate_novapay($order);

// 		
		if(isset($session_id['errors'])){
				
			foreach( $session_id['errors'] as $error ){
				wc_add_notice( 'Validation error: '.ucfirst($error->message), 'error' );
			}
			return;
		} 
		
        if ( ! $session_id ) {
            wc_add_notice( 'Payment error: Could not authenticate with Novapay.', 'error' );
            return;
        }
		
		$this->session_id = $session_id;
		
		$order->update_meta_data("afb_novapay_session_id", $session_id);

        //  2: Create payment with Novapay
        $payment_response = $this->create_novapay_payment( $order );


		
		 if ( ! isset( $payment_response->url ) ) {
			wc_add_notice( 'Payment error: No redirect URL provided by Novapay.', 'error' );
			return;
		}

		// Mark as pending payment until the user completes the payment on the gateway
		$order->update_status( 'pending', __( 'Awaiting Novapay payment', 'wc-novapay' ) );

 
		return array(
			'result'   => 'success',
			'redirect' => $payment_response->url,
		);

		
    }

    private function authenticate_novapay($order) {
 
		$url = 'https://api-ecom.novapay.ua/v1/session';
		$user_id =  get_current_user_id();
	 
		$body = [
			'merchant_id' =>  $this->contragent_id ?? 1,
			'client_first_name' => $_POST['billing_first_name'],
			'client_last_name' => $_POST['billing_last_name'],
			'client_patronymic' => '',
			'client_phone' => $_POST['billing_phone'],
			'client_email' => $_POST['billing_email'],
			'callback_url' => get_rest_url().'afb-novapay/payment-callback',
			'success_url' =>  $order->get_checkout_order_received_url(), 
			'fail_url' =>  wc_get_checkout_url(). ("?checkout_error=".urlencode("Payment Failed, please try again!")),
			'metadata' => [
				"user_id" => $user_id
			]
		];
		
		
		$x_sign = $this->generateSign($body);
		$this->x_sign = $x_sign;
 
		$headers = [
			'Accept' => 'application/json',
			'Content-Type' => 'application/json',
			'x-sign' => $this->x_sign
		];
		
		 

 
		$response = wp_remote_post( 
			$url, 
			[
				'method'    => 'POST',
				'body'      => wp_json_encode($body),
				'headers'   => $headers,
				'timeout'   => 30,
				'redirection' => 10,
				'httpversion' => '1.1',
				'sslverify' => true,
			]
		); 

		
		$response_body = wp_remote_retrieve_body($response);
			
		$response_body= json_decode($response_body);
		
		if(isset($response_body->type) && $response_body->type=="validation"){
			return ["errors"=>$response_body->errors];
		}

		
		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			return false;
		} else {
			return isset( $response_body->id ) ? $response_body->id : false;
		}

	 
    }

    private function create_novapay_payment( $order ) {
// 		https://api-qecom.novapay.ua/v1/session

		$total_quantity = 0;
		foreach ( $order->get_items() as $item_id => $item ) {
			$product_quantity = $item->get_quantity();
			$total_quantity += $product_quantity;
		}
		
		
		$body = [
				'merchant_id'   => $this->contragent_id ?? 1,
				'session_id'    => $this->session_id,
				'amount'        => $order->get_total(),
				'external_id'   => $order->get_id(),
				'use_hold'      => true,
// 				'identifier'    =>  $this->contragent_id, //37193071
				'products' => [
					[
							'description' => 'Order ' . $order->get_order_number(),
							'count' => $total_quantity,
							'price' => $order->get_total()
					]
				]
			];
		
		
		$x_sign = $this->generateSign($body);
		$this->x_sign = $x_sign;
		
		$args = [
			'method'    => 'POST',
			'headers'   => [
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'x-sign'        =>  $this->x_sign
			],
			'body'      => json_encode($body),
			'timeout'   => 300
		];
		
		$response = wp_remote_post('https://api-ecom.novapay.ua/v1/payment', $args);
		
	 

		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			return false;
		} else {
			return ( json_decode( wp_remote_retrieve_body($response) ) );
		}

        
    }

    public function receipt_page( $order ) {
        echo '<p>' . __( 'Thank you for your order, please click the button below to pay with Novapay.', 'wc-novapay' ) . '</p>';
 
    }

    public function check_ipn_response() {
        @ob_clean();

        if ( $_SERVER['REQUEST_METHOD'] != 'POST' ) {
            wp_die( 'Novapay IPN Request Failure', 'Novapay IPN', array( 'response' => 500 ) );
        }

        // Read POST data
        $post_data = file_get_contents( 'php://input' );
        $ipn_data = json_decode( $post_data, true );

        if ( empty( $ipn_data ) ) {
            wp_die( 'Novapay IPN Request Failure: No data received', 'Novapay IPN', array( 'response' => 500 ) );
        }

        // Validate IPN
        if ( ! $this->validate_ipn( $ipn_data ) ) {
            wp_die( 'Novapay IPN Request Failure: Validation failed', 'Novapay IPN', array( 'response' => 500 ) );
        }

        // Process IPN
        $this->process_ipn( $ipn_data );

        wp_die( 'Novapay IPN Request Successful', 'Novapay IPN', array( 'response' => 200 ) );
    }

    protected function validate_ipn( $ipn_data ) {
        // Implement your IPN validation logic here
        // You might need to verify the data with Novapay to ensure it's legitimate
        // For example, you could send a request to Novapay's API to verify the IPN data

        // Example validation (replace with actual validation logic)
        if ( isset( $ipn_data['transaction_id'] ) && isset( $ipn_data['status'] ) ) {
            return true;
        }

        return false;
    }

    protected function process_ipn( $ipn_data ) {
        // Get the order ID
        $order_id = isset( $ipn_data['order_id'] ) ? (int) $ipn_data['order_id'] : 0;
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        // Check the payment status
        if ( $ipn_data['status'] == 'completed' ) {
            // Payment completed
            $order->payment_complete();
            $order->add_order_note( __( 'Novapay payment completed.', 'wc-novapay' ) );
        } elseif ( $ipn_data['status'] == 'failed' ) {
            // Payment failed
            $order->update_status( 'failed', __( 'Novapay payment failed.', 'wc-novapay' ) );
        } elseif ( $ipn_data['status'] == 'pending' ) {
            // Payment pending
            $order->update_status( 'on-hold', __( 'Novapay payment pending.', 'wc-novapay' ) );
        }
    }
	
	
	protected function generateSign($data){

		if(is_array($data)){
			$data = wp_json_encode( $data , JSON_UNESCAPED_UNICODE );
		}

		// You can get a simple private/public key pair using:
		// openssl genrsa 512 >private_key.txt
		// openssl rsa -pubout <private_key.txt >public_key.txt

		// IMPORTANT: The key pair below is provided for testing only.
		// For security reasons you must get a new key pair
		// for production use, obviously.

		// IMPORTANT: When converting data into JSON in PHP,
		// pay attention to the use of JSON_UNESCAPED_UNICODE flag.

		$private_key = $this->private_key;
		$public_key = $this->api_key;

		
		$jsonData = ($data);
		
		$privateKey = $private_key;
		 
		$privateKey = nl2br($privateKey);
		  
    	$privateKey = trim($privateKey);
		
		$privateKey = <<<EOD
		-----BEGIN RSA PRIVATE KEY-----
		Proc-Type: 4,ENCRYPTED
		DEK-Info: DES-EDE3-CBC,91A2CE5AC47DBDCE

		fYxbLHTP2DKEXb5BnRcqhLzyiY80BEAoKiHKNFMzM7JzfAKrh0Uot3syAP6z//75
		arRvIMSe3HRWeD9COjOqEenza5srAh3A3s5KlktutkHwHc8XHWFpFzfp+2XztqIw
		xvSqzeKceABqtP/wDb76tPU4+D4sHn8sKUeJ/87UNxfa7UD0omJ8s6pXOevCTm8J
		ndLzJZdrA3HqkwbyUFUZrkF3QIVqlhXW3mtFyGWILxIHULQt5dOF3hJyhjavXuHF
		qLv37uzmAtHftFpfClpNG/MKbqY+Bt0cP622TQaUdVTi9M7cpgpwOthRhharvmvM
		LxfpeMP/zi+z/mYJhjhcHkpFj3RDEfH0t5u+5/tb8JMI/lHLjrScshr+qwdjt/XZ
		gYLxKTYP0wQ9B+SNR+6nAcvAe8FviqzvJWVqrzTyQJR8sC8EJcoDjf096MACuESL
		eaqUtwa/BjwP9CIUgurxbzmj5Jjn96HI4fnEGdK6zvXfrz0V0WjFf3wdYiHTwQ+D
		L3uUo9uZ9Wn6I6NXitUE7aUqx6m9MVI7WoAWAnrOnynOzHg5yC7ckdiiW8xB3KiR
		ko3dvl7JMtZFIb8lWcs2Re54w9COLRNE3Bak1qa/Zpd1BkQ3sn1LXPy8ivb7jSrj
		OyQlZoCesH1m/EZ5z7MSrGFmMWI2/A7suR1sI8z2tE+ksjU+PZ4c/LY1PjixWaEO
		SqNCjVSCorq2qHBtxWwaLt+XECtBqwvAn+a0W2Ary9yiNEHuZxYmQ2S3F1KR0Pr7
		9QTnOa0JTfolcadMFqlv88xsLTZYDhY44UWk4u+H390RekK40Puq6zasZNOxRaq6
		Ux1g1kSuPSSJpRg/DfDBqKQN4gC+Oa0DJVdficGz91gY3Bi4LaBdS3OjZUywaPzT
		7NjKCt5F/aOPVP4XuiZektqwB68LGZ7/XD1CGuRGdNxYcnOOlSRdWT99lIyrprs3
		3W94PDRc1J3AVxPVU1t5+cbmPv+RDs14aapPt8lgiMZeuhgaTJWNiKplbNOphjNn
		STvSd71vm8L/pjtxHSjTmeaOUcMd4UX8/toS4B1OY3lgbaD3OSyoWT6fa16Q0cXM
		d4suBou+pxtaWXChMt8sk4cGNa0xY7avZXtpqn/O3paHoab2U2hEHyw+MAcODO3j
		S7doDQKZqIFU8CaAJcAmDAU4LVt0q5Cmu67flRiwok+HPNdom6ElJw3AFGjGM1tX
		2xxI2qV3h2AqKFBKEFuw4dFFGeiVCumc69OLXbTjDG4P2hdgVjdlQ0FwQpE0IjC1
		eGu4BRHn3ZZabjDiZyQR+KHvFERbJ+RCDlF1cu2d+29r0YX4Ysq+F6QtID0Uk2lj
		sBP04xrIb8fjcZaRl3IFGSgTg5EpHUSOOyxaoLkEjC7SXGmn1YzJagWRLaqgQPhI
		+tnXG9/gUz8R0R0/KBY1Mdgr+lmQ+EEavt5argcP+311/UpdCsJuzPhk9p3ZbD2Y
		U0Yczye1XA+9DueGdtY3l2Vx32NZ0hCkzff2TTNPGrEV1RiFVtXYRN87tbpDPhwu
		Qj/WEwBab1P3srZ/vtrRVnF85ZhA3ngfJFhaBDi6iENFwJs5xSUhetsjc2mQ56J4
		-----END RSA PRIVATE KEY-----
		EOD;
		
		
		$public_key = <<<EOD
		-----BEGIN PUBLIC KEY-----
		MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAu5uu7hQNQ9GQDyvGaeY/
		E/7e1/4vVdxPgFVXJh1g9b0kS2HPoA2deOsem+tfgBpENQXHKzSFagr/fzuMXIrO
		m3KP/ZIYP3Iumvq5XrM9uxcdvwUG1uL3jS3P8d1ceKlXI00FkDrlWUvhswY8cAi6
		bvwLzfIRLk4YbxHtpr0KezPzReHD1Ib+1vxDZrfiy5rtrC87GoNEO6IMJdBaxyAS
		R7+nllx5zdCvIz1g5bQwuyO8feTNmi/G6nFCHfQqZ4C/wZUqKZQQxsr3fCuHFmBy
		9CaFX6emEW/e2vkeOF8MXskK+mJ0zGzkwiy1zyUXF0EMOAMFbQt9IiFjENrv0o9a
		NwIDAQAB
		-----END PUBLIC KEY-----
		EOD;
 
		
		$passphrase = $this->afb_passphrase;

		// Load your private key
		$privateKeyResource = openssl_pkey_get_private($privateKey, $passphrase);

		if ($privateKeyResource === false) {
			die('Failed to load private key');
		}
		
		
		$binary_signature = "";
 
		openssl_sign($jsonData, $binary_signature, $privateKeyResource, OPENSSL_ALGO_SHA1);
		
		
		$public_key = openssl_pkey_get_public ($public_key);
		
// // Check signature
// $ok = openssl_verify($jsonData, $binary_signature, $privateKeyResource, OPENSSL_ALGO_SHA1);
// echo "check #1: ";
// if ($ok == 1) {
//     echo "signature ok (as it should be)\n";
// } elseif ($ok == 0) {
//     echo "bad (there's something wrong)\n";
// } else {
//     echo "ugly, error checking signature\n";
// }

// $ok = openssl_verify($jsonData, $binary_signature, $public_key, OPENSSL_ALGO_SHA1);
// echo "check #2: ";
// if ($ok == 1) {
//     echo "ERROR: Data has been tampered, but signature is still valid! Argh!\n";
// } elseif ($ok == 0) {
//     echo "bad signature (as it should be, since data has beent tampered)\n";
// } else {
//     echo "ugly, error checking signature\n";
// }

		
		
		$binary_signature = base64_encode($binary_signature);
		
		
		return $binary_signature;

	}
	
	
	
	

	public function handle_approve_payment(WP_REST_Request $request) {
		$order_id = $request->get_param('order_id');

		if (!$order_id) {
			return new WP_REST_Response('Order ID is required.', 400);
		}

		$order = wc_get_order($order_id);

		$afb_novapay_session_id = $order->get_meta("afb_novapay_session_id", true);

		if (!$order) {
			return new WP_REST_Response('Order not found.', 400);
		}

		if (!$afb_novapay_session_id) {
			return new WP_REST_Response('Session ID not found.', 400);
		}


		$order_total = (float) $order->get_total();
		$novapay_options = get_option('woocommerce_novapay_settings');

		if ($novapay_options && isset($novapay_options['api_key'])) {
			$api_key = $novapay_options['api_key'];
		}
		else{
			return new WP_REST_Response('Please add API KEY in NovaPay Payment Settings.', 400);
		}


		$body = array(
				'merchant_id' => $novapay_options['contragent_id'],
				'session_id' => $afb_novapay_session_id,
				'amount' => $order_total
			);


		$x_sign = $this->generateSign($body);
		$this->x_sign = $x_sign;


		$url = 'https://api-ecom.novapay.ua/v1/complete-hold';
		$args = array(
			'body' => json_encode($body),
			'headers'   => [
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
					'x-sign'        =>  $x_sign
			],
			'timeout' => 30,
			'sslverify' => false, 
		);


		$response = wp_remote_post($url, $args);

		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			echo "Error: $error_message";
		} else {

			// {"uuid":"02b1208b-9f72-4a65-aec3-b0fa5565137b","type":"processing","error":"session not found","code":"SessionNotFoundError"}

			 $response_code = wp_remote_retrieve_response_code($response);
			$body = json_decode(wp_remote_retrieve_body($response));

			if($response_code!=200){
				echo "Error: ".$body->error;
				exit;
			}

			$order->update_status('completed', 'Payment Completed after Approving from Hold Status.');
			$order->update_meta_data("payment_afb_status","complete");
			$order->save();

			echo json_encode(['success'=>"Complete hold Successfully "]);

		}
		exit;

	}





}
