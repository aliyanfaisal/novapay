<?php
/**
 * Plugin Name: WooCommerce Novapay Gateway
 * Description: Adds Novapay Payment Gateway to WooCommerce.
 * Version: 1.0.0
 * Author: Aliyan Faisal
 * Author URI: https://aliyanfaisal.com
 * Plugin URI: https://aliyanfaisal.com
 * Text Domain: wc-novapay
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


require_once plugin_dir_path(__FILE__)."includes/class-wc-novapay-payment-callback.php";

if(is_admin()){
	require_once plugin_dir_path(__FILE__)."admin/admin-order-functions.php";
}

// is wc activee
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    // Include the Novapay gateway class
    add_action( 'plugins_loaded', 'wc_novapay_init', 11 );

    function wc_novapay_init() {
        if ( class_exists( 'WC_Payment_Gateway' ) ) {
            include_once 'includes/class-wc-gateway-novapay.php';

            // Add the gateway to WooCommerce
            add_filter( 'woocommerce_payment_gateways', 'wc_add_novapay_gateway' );

            function wc_add_novapay_gateway( $methods ) {
                $methods[] = 'WC_Gateway_Novapay';
                return $methods;
            }
        }
    }

}





//show error on checkout
function show_checkout_error_from_url_param() { 
    if ( isset( $_GET['checkout_error'] ) ) { 
        $error_message = urldecode( sanitize_text_field( $_GET['checkout_error'] ) );

        wc_add_notice( $error_message, 'error' );
    }
}
add_action( 'woocommerce_before_checkout_form', 'show_checkout_error_from_url_param' );















//CURRENCY SWITCHER
//
add_action("wp_footer","afb_event_change_currency",9999);

function afb_event_change_currency(){
	
	if(  function_exists( 'is_woocommerce' ) && is_woocommerce()  ){
		
		?>
	<script>
		
		let lang_vs_curr = {
			"English" :  [ "USD" , "/uk/en" ],
			"Arabic"  : [ "USD" , "/uk/ar" ],
			"French"  : [ "EUR" , "/uk/fr"],
			"Ukrainian" : [ "UAH" , null]
		}
		
		var userCalledLangChange = false;
		var userCalledLangChangeCount = 0
		
// 		"/uk/en"
// 		"/uk/fr"
// 		"/uk/ar"
		
		jQuery(function($){
			let lang_afb_links = jQuery(".gt_option > .nturl:not(.gt_current)") 
			
			var lang_check_interval = setInterval(function(){
				
				if(lang_afb_links.length > 0){
					console.log("yeasssc", jQuery(".gt_option > .nturl:not(.gt_current)"))
					
					
					lang_afb_links.bind("click", change_lang_and_curr)
					
					
					
					for( let ll in lang_vs_curr){
						
						if( jQuery(".gt_selected a").html().includes( ll ) ){
							change_lang_and_curr(ll)
						}
							
							
					}
					
					
					
					
					
					
					
					clearInterval( lang_check_interval )
				}
				
				
			},1000)
			
			
			
			
			function change_lang_and_curr(langg){

				userCalledLangChangeCount++ 
				
				let selected_lang = jQuery(this).prop("title") ?? langg

						jQuery(".wcml-cs-submenu a[rel='"+ ( lang_vs_curr[selected_lang]) +"']")
						
				
						<?php
						if(is_product()){
							?>
							if( selected_lang && jQuery(".wcml-cs-submenu a[rel='"+ ( lang_vs_curr[selected_lang][0]) +"']").length > 0){
							<?php
						}
						else{
							?>
							if( selected_lang){
							<?php
						}
							
							?>
						
							
							
							console.log("CURRENXY ", lang_vs_curr[selected_lang][0])
								
							var curr_currency = getCookie('googtrans_');
								console.log("CURR CURRENCY", curr_currency, lang_vs_curr[selected_lang][1])
							if(   curr_currency == lang_vs_curr[selected_lang][1] || ( curr_currency=='null' && lang_vs_curr[selected_lang][1]==null ) ){
								
								if( lang_vs_curr[selected_lang][0] == "UAH" ){
								   if( jQuery("#primary .woocommerce-Price-currencySymbol").html() != "₴"){
									   
								   }
									else{
										return;
									}
								}
								else{
									return;
								}
								
							}
								
							if(lang_vs_curr[selected_lang][1] !=null){ 
// 								setCookie('googtrans',  ( lang_vs_curr[selected_lang][1] ), 30);
// 								setCookie('googtrans_',  ( lang_vs_curr[selected_lang][1] ), 30);
								document.cookie = "googtrans="+ ( lang_vs_curr[selected_lang][1] ) +"; path=/";
								document.cookie = "googtrans_="+ ( lang_vs_curr[selected_lang][1] ) +"; path=/";
								console.log("SETTING COOKIESSSS")
							}
							else{
								
								document.cookie = "googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/";
								document.cookie = "googtrans_=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/";
// 								setCookie('googtrans_', null, -1 );
// 								setCookie('googtrans', "", -1 );
							}
								
							wcml_load_currency( lang_vs_curr[selected_lang][0] )
							console.log("REFRESHING AGAIN")
							
							
							
						}
						


				console.log("Selected lang ", selected_lang , jQuery(".wcml-cs-submenu a[rel='"+ ( lang_vs_curr[selected_lang][0]) +"']") )

			}
		})
		
		
		
		function setCookie(name, value, days) {
			let expires = "";
			if (days) {
				const date = new Date();
				date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
				expires = "; expires=" + date.toUTCString();
			}
			document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/";
		}

		
	function getCookie(name) {
		const cookies = document.cookie.split('; ');
		let lastValue = null;

		for (let i = 0; i < cookies.length; i++) {
			const cookie = cookies[i].split('=');
			if (cookie[0] === name) {
				lastValue = decodeURIComponent(cookie[1]);
			}
		}

		return lastValue;
	}

 
	</script>
		<?php
		
	}
}

