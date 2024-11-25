<?php


	class WC_Gateway_Novapay_Blocks_Support extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
				public function initialize() {
					$this->settings = get_option( 'woocommerce_novapay_settings', array() );
				}

				public function is_active() {
					return 'yes' === $this->settings['enabled'];
				}

				public function get_payment_method_script_handles() {
					return array();
				}

				public function get_payment_method_data() {
					return array(
						'title'       => $this->settings['title'],
						'description' => $this->settings['description'],
					);
				}
			}