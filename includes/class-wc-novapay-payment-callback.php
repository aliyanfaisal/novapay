<?php


add_action('rest_api_init', function () {
    register_rest_route('afb-novapay', '/payment-callback', array(
        'methods'  => 'POST',
        'callback' => 'handle_novapay_payment_callback',
        'permission_callback' => '__return_true',
    ));
	
	register_rest_route('afb-novapay', '/payment-success', array(
        'methods'  => 'POST',
        'callback' => 'handle_novapay_payment_success',
        'permission_callback' => '__return_true',
    ));
	
	register_rest_route('afb-novapay', '/payment-fail', array(
        'methods'  => 'POST',
        'callback' => 'handle_novapay_payment_fail',
        'permission_callback' => '__return_true',
    ));
	
 
});




function handle_novapay_payment_callback(WP_REST_Request $request) {
    $data = $request->get_json_params();
 
    if (!isset($data['id']) || !isset($data['status']) ) {
        return new WP_Error('missing_data', 'Missing required data', array('status' => 400));
    }
 
    $id = sanitize_text_field($data['id']);
    $status = sanitize_text_field($data['status']);
    $created_at = sanitize_text_field($data['created_at']);
    $metadata = isset($data['metadata']) ? $data['metadata'] : [];
    $client_first_name = sanitize_text_field($data['client_first_name']);
    $client_last_name = sanitize_text_field($data['client_last_name']);
    $client_patronymic = sanitize_text_field($data['client_patronymic']);
    $client_phone = sanitize_text_field($data['client_phone']);
    $external_id = sanitize_text_field($data['external_id']);
    $pan = sanitize_text_field($data['pan']);
    $processing_result = sanitize_text_field($data['processing_result']);
    $amount = floatval($data['amount']);
    $delivery = isset($data['delivery']) ? $data['delivery'] : array();
    $products = isset($data['products']) ? $data['products'] : array();
    $delivery_amount = sanitize_text_field($data['delivery_amount']);
    $delivery_status_code = sanitize_text_field($data['delivery_status_code']);
    $delivery_status_text = sanitize_text_field($data['delivery_status_text']);

	$order_id=  sanitize_text_field($data['external_id']);
 
	$user_id = isset( $metadata['user_id'] ) ? $metadata['user_id'] : 0;

	
	//change keys
	$data['payment_afb_status'] = $status ;
	unset($data['status']);
	
	$data['afb_payment_id'] = $id;
	unset($data['id']);
	
	
	
    // order id
    if ($order_id) {
        $order_id = intval($order_id);
        $order = wc_get_order($order_id);

		$order_status = $order->get_meta("payment_afb_status", true);
		if($order_status!=""){
			return;
		}
		
        if ($order && $processing_result=="Successful") {
            // Update order status based on webhook status
            if($status=="holded" ){
				$order->update_status('on-hold', __('Payment Holded, waiting for Admin Action.', 'wc-novapay'));
				$order->add_order_note('Payment Holded and can be accepted by admin now. Payment ID: ' . $id);
			}
			else{
				$order->update_status('processing', __('Payment received, processing order.', 'wc-novapay'));
				 $order->add_order_note('Payment successful via Novapay. Payment ID: ' . $id);
			}
            
 
            wc_reduce_stock_levels($order_id);
			
			if($user_id){
				// Clear the cart for the user
//     			clear_cart_for_user($user_id);
			}
			
  
        }
		elseif($order && $processing_result!="Successful"){
			$order->update_status('cancelled', __('Payment failed, Transaction ID: '.$id, 'wc-novapay'));
		}
		
		//udpatee order meta
		if($order){
			foreach($data as $key=>$dt){
				$order->update_meta_data($key,$dt);
			}
		}
		
		$order->save();
		
// 		$formatted_content = "Webhook Data Received:\n";
// 		foreach ($data as $key => $value) {
// 			$formatted_content .= ucfirst($key) . ": " . print_r($value, true) . "\n";
// 		}

// 		// Update the post content
// 		$updated_post = array(
// 			"post_title"=> "webhook received CALLBACK. ORDER ID". $order_id." & time: ".date("y-m-d h:i"),
// 			'post_content' => $formatted_content,
// 		);

// 		// Update the post in the database
// 		wp_insert_post($updated_post);
	
    }
 
	
    return new WP_REST_Response(array('message' => 'Payment callback processed successfully'), 200);
}




function handle_novapay_payment_success(WP_REST_Request $request){
	
	  $webhook_data = $request->get_json_params();

    // Check if the required data is present (adjust as needed based on the webhook schema)
    if (!isset($webhook_data['id']) || !isset($webhook_data['status'])) {
        return new WP_Error('missing_data', 'Required data is missing', array('status' => 400));
    }
 

    // Format the webhook data for the post content
    $formatted_content = "Webhook Data Received:\n";
    foreach ($webhook_data as $key => $value) {
        $formatted_content .= ucfirst($key) . ": " . print_r($value, true) . "\n";
    }

    // Update the post content
    $updated_post = array(
		"post_title"=> "webhook received success",
        'post_content' => $formatted_content,
    );

    // Update the post in the database
    wp_insert_post($updated_post);

    // Return a success response
    return new WP_REST_Response('Webhook data stored successfully', 200);
}



function handle_novapay_payment_fail(WP_REST_Request $request){
	$data = $request->get_json_params();
 
    if (!isset($data['id']) || !isset($data['status']) ) {
        return new WP_Error('missing_data', 'Missing required data', array('status' => 400));
    }
 
    $id = sanitize_text_field($data['id']);
    $status = sanitize_text_field($data['status']);

	$order_id=  sanitize_text_field($data['external_id']);
 
	$user_id = isset( $metadata['user_id'] ) ? $metadata['user_id'] : 0;
	
	//change keys
	$data['payment_afb_status'] = $status ;
	unset($data['status']);
	
	$data['afb_payment_id'] = $id;
	unset($data['id']);
	
	
	if($order_id){
		$order = wc_get_order($order_id);
		$order_status = $order->get_meta("payment_afb_status", true);
		if($order_status!=""){
			return;
		}
		
		
		$order->update_status('cancelled', __('Payment failed, Transaction ID: '.$id, 'wc-novapay'));
		$order->add_order_note('Payment Failed. Payment ID: ' . $id);
	}
	
	
	
}














function clear_cart_for_user($user_id) {
    if (!$user_id) {
        return false;
    }

    // Load the WooCommerce session handler
    WC()->session = new WC_Session_Handler();
    WC()->session->init();
    
    // Get the user's session key
    $session_key = WC()->session->get_session_key($user_id);

    // Check if the user has a session
    if ($session_key) {
        // Set the session handler to the user's session
        WC()->session->set_customer_session_cookie(true);
        WC()->session->set_customer_session_cookie($session_key);

        // Clear the cart for the user
        WC()->cart = new WC_Cart();
        WC()->cart->empty_cart();
    }

    return true;
}
