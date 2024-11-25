<?php
    
function add_approve_payment_button($order) {
    $payment_status = $order->get_meta('payment_afb_status');
	$transaction_id = $order->get_meta('afb_payment_id');
	$processing_result = $order->get_meta('processing_result');
	$amount = $order->get_meta('amount');
	$afb_novapay_session_id = $order->get_meta('afb_novapay_session_id');
	$order->update_meta_data("payment_afb_status","complete");
	
		echo '<style> #approve-payment-button{
		width: 100%;
		margin-top: 30px;
		background: #2271B1;
		color: white;
		font-size: 15px;
		}
		.afb_det{
			border: 1px solid;
			float: left;
			margin-top: 20px;
			padding: 10px;
			background: #dddddd45
		}
		.afb_det > *{
		   float: left;
		   border-bottom: 1px solid #ddd;
		   padding: 10px 0px;
		   font-size: 16px
		}
		</style>';
	
		echo "<div class='afb_det'>";
		echo "<hr>";
		echo "<strong style='font-size: 24px;'>NovaPay Payment</strong>";
		echo "<div> <strong>Current Status:</strong> ".$payment_status." </div>";
		echo "<div> <strong>Transaction ID:</strong> ".$transaction_id." </div>";
		echo "<div> <strong>Proccessing Result:</strong> ".$processing_result." </div>";
// 		echo "<div> <strong>Session ID:</strong> ".$afb_novapay_session_id." </div>";
		echo "<div> <strong>Paid Amount:</strong> ".$amount." </div>";
		

    if ($payment_status === 'holded') {
			
        echo '<a  type="button" id="approve-payment-button" class="button" style="text-align:center">Approve Payment</a>';
        wp_nonce_field('approve_payment_nonce', 'approve_payment_nonce_field');
		
    }
	
	echo "</div>";
}
add_action('woocommerce_admin_order_data_after_order_details', 'add_approve_payment_button');




function add_approve_payment_action_filter($actions) {
    global $theorder;

    $payment_status = $theorder->get_meta('payment_afb_status');

    if ($payment_status === 'holded') {
        $actions['approve_payment'] = __('Approve Payment', 'woocommerce');
    }

    return $actions;
}
add_filter('woocommerce_order_actions', 'add_approve_payment_action_filter');




function enqueue_admin_scripts($hook) {


    global $post;

    if ( ( isset($_GET['page']) && $_GET['page'] == "wc-orders" ) && isset($_GET['id']) )  {

    wp_enqueue_script('approve-payment-script', plugin_dir_url(__DIR__) . '/js/approve-payment__.js', array('jquery'), null, true);

    wp_localize_script('approve-payment-script', 'approvePayment', array(
        'nonce' => wp_create_nonce('wp_rest'),
        'order_id' => $_GET['id'],
        'rest_url' => esc_url_raw(rest_url('afb-novapay/approve-payment')),
    ));
		 
    }
}
add_action('admin_enqueue_scripts', 'enqueue_admin_scripts',8);





function display_order_meta_data($order) {
    // Get all meta data for the order
    $order_meta_data = $order->get_meta_data();
	
    echo '<h3>Order Meta Data</h3>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Meta Key</th><th>Meta Value</th></tr></thead>';
    echo '<tbody>';
    
    if (!empty($order_meta_data)) {
        foreach ($order_meta_data as $meta) {
            echo '<tr>';
            echo '<td>' . esc_html($meta->key) . '</td>';
            echo '<td>' . esc_html(print_r($meta->value, true)) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="2">No meta data found for this order.</td></tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
}
// add_action('woocommerce_admin_order_data_after_order_details', 'display_order_meta_data');

