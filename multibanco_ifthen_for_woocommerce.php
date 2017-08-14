<?php
/**
 * Plugin Name: Multibanco (IfthenPay gateway) for WooCommerce
 * Plugin URI: http://www.webdados.pt/produtos-e-servicos/internet/desenvolvimento-wordpress/multibanco-ifthen-software-gateway-woocommerce-wordpress/
 * Description: This plugin allows Portuguese customers to pay WooCommerce orders with Multibanco (Pag. Serviços), using the IfthenPay gateway.
 * Version: 1.9.2
 * Author: Webdados
 * Author URI: http://www.webdados.pt
 * Text Domain: multibanco_ifthen_for_woocommerce
 * Domain Path: /lang
**/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Check if WooCommerce is active
 **/
// Get active network plugins - "Stolen" from Novalnet Payment Gateway
function mbifthen_active_nw_plugins() {
	if ( !is_multisite() )
		return false;
	$mbifthen_activePlugins = ( get_site_option('active_sitewide_plugins') ) ? array_keys( get_site_option('active_sitewide_plugins') ) : array();
	return $mbifthen_activePlugins;
}
if ( in_array('woocommerce/woocommerce.php', (array) get_option('active_plugins')) || in_array('woocommerce/woocommerce.php', (array) mbifthen_active_nw_plugins()) ) {

	//Our own order class adn the main class
	add_action( 'plugins_loaded', 'mbifthen_init', 0 );
	function mbifthen_init() {
		require_once( dirname(__FILE__).'/class-wc-order-mb-ifthen.php' );
		require_once( dirname(__FILE__).'/class-wc-multibanco-ifthen-webdados.php' );
	}

	/* The next functions are outside the main class because sometimes WooCommere won't load the payment gateways */

	//Languages
	add_action( 'plugins_loaded', 'mbifthen_lang' );
	function mbifthen_lang() {
		//If WPML is present and we're loading via ajax, let's try to fix the locale
		if ( function_exists('icl_object_id') && function_exists('wpml_is_ajax') ) {
			if ( wpml_is_ajax() ) {
				if ( ICL_LANGUAGE_CODE!='en' ) {
					add_filter( 'plugin_locale', 'mbifthen_lang_fix_wpml_ajax', 1, 2 );
				}
			}
		}
		load_plugin_textdomain( 'multibanco_ifthen_for_woocommerce', false, dirname( plugin_basename(__FILE__) ).'/lang/' );
	}
	//Languages on Notes emails
	add_action( 'woocommerce_new_customer_note', 'mbifthen_lang_notes', 1 );
	function mbifthen_lang_notes( $order_id ) {
		if ( is_array($order_id) ){
			if ( isset($order_id['order_id']) ){
				$order_id = $order_id['order_id'];
			} else {
				return;
			}
		}
		if ( function_exists('icl_object_id') ) {
			global $sitepress;
			$order = new WC_Order_MB_Ifthen($order_id);
			$lang = $order->mb_get_wpml_language();
			if( !empty($lang) && $lang != $sitepress->get_default_language() ){
				$GLOBALS['mb_ifthen_locale'] = $sitepress->get_locale($lang); //Set global to be used on mbifthen_lang_fix_wpml_ajax below
				add_filter( 'plugin_locale', 'mbifthen_lang_fix_wpml_ajax', 1, 2 );
				load_plugin_textdomain( 'multibanco_ifthen_for_woocommerce', false, dirname( plugin_basename(__FILE__) ).'/lang/' );
			}
		}
	}
	//This should NOT be needed! - Check with WooCommerce Multilingual team
	function mbifthen_lang_fix_wpml_ajax( $locale, $domain ) {
		if ( $domain == 'multibanco_ifthen_for_woocommerce' ) {
			global $sitepress;
			$locales = icl_get_languages_locales();
			if ( isset($locales[ICL_LANGUAGE_CODE]) ) $locale = $locales[ICL_LANGUAGE_CODE];
			//But if it's notes
			if ( isset($GLOBALS['mb_ifthen_locale']) ) $locale = $GLOBALS['mb_ifthen_locale'];
		}
		return $locale;
	}

	/* WooCommerce 3.0 is not allowing payment gateways to add information to transactional emails - Let's fix it for everybody, shall we? */
	/* https://github.com/woocommerce/woocommerce/issues/13966 */
	add_action( 'woocommerce_send_queued_transactional_email', 'mbifthen_woocommerce_send_queued_transactional_email', 1, 2 );
	function mbifthen_woocommerce_send_queued_transactional_email( $filter = '', $args = array() ) {
		//Only in 3.0.0 - It should be fixed on other versions (we hope)
		if ( version_compare( WC_VERSION, '3.0.0', '==' ) ) {
			WC()->payment_gateways();
			WC()->shipping();
		}
	}

	/* Add to WooCommerce */
	add_filter( 'woocommerce_payment_gateways', 'mbifthen_add' );
	function mbifthen_add( $methods ) {
		$methods[] = 'WC_Multibanco_IfThen_Webdados'; 
		return $methods;
	}

	/* Add settings link to plugin actions */
	add_filter( 'plugin_action_links_'.plugin_basename( __FILE__ ), 'mbifthen_add_settings_link' );
	function mbifthen_add_settings_link( $links ) {
		$action_links = array(
			'settings' => '<a href="admin.php?page=wc-settings&amp;tab=checkout&amp;section=multibanco_ifthen_for_woocommerce">' . __( 'Settings', 'woocommerce' ) . '</a>',
		);
		return array_merge( $action_links, $links );
	}

	/* Format MB reference */
	function mbifthen_format_ref( $ref ) {
		return apply_filters( 'multibanco_ifthen_format_ref', trim( chunk_split( trim( $ref ), 3, ' ' ) ) );
	}

	/* Order metabox to show Multibanco payment details */
	//This will need to change when the order is no longer a WP post
	add_action( 'add_meta_boxes', 'mbifthen_order_add_meta_box' );
	function mbifthen_order_add_meta_box() {
		add_meta_box( 'multibanco_ifthen_for_woocommerce', __('Multibanco payment details', 'multibanco_ifthen_for_woocommerce'), 'mbifthen_order_meta_box_html', 'shop_order', 'side', 'core' );
	}
	function mbifthen_order_meta_box_html( $post ) {
		$order = new WC_Order_MB_Ifthen($post->ID);
		$mb = new WC_Multibanco_IfThen_Webdados;
		if (
			$order_mb_details = $mb->get_order_mb_details( $order->mb_get_id() )
		) {
			echo '<p>'.__('Entity', 'multibanco_ifthen_for_woocommerce').': '.trim( $order_mb_details['ent'] ).'<br/>';
			echo __('Reference', 'multibanco_ifthen_for_woocommerce').': '.mbifthen_format_ref( $order_mb_details['ref'] ).'<br/>';
			echo __('Value', 'multibanco_ifthen_for_woocommerce').': '.wc_price( $order->mb_get_total() ).'</p>';
			if ( WP_DEBUG && $order->mb_has_status(array('on-hold', 'pending')) ) {
				$callback_url = $mb->notify_url;
				$callback_url = str_replace('[CHAVE_ANTI_PHISHING]', $mb->secret_key, $callback_url);
				$callback_url = str_replace('[ENTIDADE]', trim($order_mb_details['ent']), $callback_url);
				$callback_url = str_replace('[REFERENCIA]', trim($order_mb_details['ref']), $callback_url);
				$callback_url = str_replace('[VALOR]', $order->mb_get_total(), $callback_url);
				$callback_url = str_replace('[DATA_HORA_PAGAMENTO]', '', $callback_url);
				$callback_url = str_replace('[TERMINAL]', 'Testing', $callback_url);
				?>
				<hr/>
				<p>
					<?php _e('Callback URL', 'multibanco_ifthen_for_woocommerce'); ?>:<br/>
					<textarea readonly type="text" class="input-text" cols="20" rows="5" style="width: 100%; height: 50%; font-size: 10px;"><?php echo $callback_url; ?></textarea>
				</p>
				<script type="text/javascript">
				jQuery(document).ready(function(){
					jQuery('#multibanco_ifthen_for_woocommerce_simulate_callback').click(function() {
						if (confirm('<?php _e('This is a testing tool and will set the order as paid. Are you sure you want to proceed?', 'multibanco_ifthen_for_woocommerce'); ?>')) {
							jQuery.get('<?php echo $callback_url; ?>', '', function(response) {
								alert('<?php _e('This page will now reload. If the order is not set as paid and processing (or completed, if it only contains virtual and downloadable products) please check the debug logs.', 'multibanco_ifthen_for_woocommerce'); ?>');
								window.location.reload();
							}).fail(function() {
								alert('<?php _e('Error: Could not set the order as paid', 'multibanco_ifthen_for_woocommerce'); ?>');
							});
						}
					});
				});
				</script>
				<p align="center">
					<input type="button" class="button" id="multibanco_ifthen_for_woocommerce_simulate_callback" value="<?php echo esc_attr(__('Simulate callback payment', 'multibanco_ifthen_for_woocommerce')); ?>"/>
				</p>
				<?php
			}
		} else {
			$mb = new WC_Multibanco_IfThen_Webdados;
			if ( $order->mb_get_payment_method() == $mb->id ) {
				echo '<p>'.__('No details available', 'multibanco_ifthen_for_woocommerce').'.</p><p>'.__('This must be an error because the payment method of this order is Multibanco', 'multibanco_ifthen_for_woocommerce').'.</p>';
			} else {
				echo '<p>'.__('No details available', 'multibanco_ifthen_for_woocommerce').'.</p><p>'.__('The payment method of this order is not Multibanco', 'multibanco_ifthen_for_woocommerce').'.</p>';
			}
		}
	}

	add_filter( 'woocommerce_shop_order_search_fields', 'mbifthen_shop_order_search' );
	/* Allow searching orders by reference */
	function mbifthen_shop_order_search( $search_fields ) {
		$mb = new WC_Multibanco_IfThen_Webdados;
		$search_fields[] = '_'.$mb->id.'_ref';
		return $search_fields;
	}

	/* Dismiss callback notice */
	add_action( 'wp_ajax_multibanco_ifthen_dismiss_callback_notice', 'multibanco_ifthen_dismiss_callback_notice' );
	function multibanco_ifthen_dismiss_callback_notice() {
		$id = 'multibanco_ifthen_for_woocommerce';
		update_option( $id.'_callback_notice_dismiss', 'yes' );
		die( '1' );
	}

	/* Force Reference creation on New Order (not the British Synthpop band) */
	add_action( 'woocommerce_checkout_update_order_meta', 'mbifthen_woocommerce_checkout_update_order_meta' ); 	//Frontend
	add_action( 'plugins_loaded', 'woocommerce_process_shop_order_meta_backend' );
	function woocommerce_process_shop_order_meta_backend() {
		//Workaround for https://wordpress.org/support/topic/referencia-nao-criada-em-encomendas-feitas-manualmente/
		//Backend - This should not be needed when this commit is applied to production https://github.com/woothemes/woocommerce/commit/7dadae7bc80a842e10e78a972334937ed5c4416a
		if ( version_compare( WC_VERSION, '2.6', '<' ) )
			add_action( 'woocommerce_process_shop_order_meta', 'mbifthen_woocommerce_checkout_update_order_meta', 999 );
	}
	function mbifthen_woocommerce_checkout_update_order_meta($order_id) {
		$order = new WC_Order_MB_Ifthen($order_id);
		$mb = new WC_Multibanco_IfThen_Webdados;
		//Avoid duplicate instructions on the email...
		//remove_action('woocommerce_email_before_order_table', array($mb, 'email_instructions'), 10, 2); //"Hyyan WooCommerce Polylang Integration"
		remove_action( 'woocommerce_email_before_order_table', array($mb, 'email_instructions_1'), 10, 2 );
		if ( $order->mb_get_payment_method() == $mb->id ) {
			$ref = $mb->get_ref( $order_id );
			//That should do it...
		}
	}

	/* Change Ref if order total is changed on wp-admin */
	add_action( 'woocommerce_saved_order_items', 'mbifthen_woocommerce_saved_order_items' );
	function mbifthen_woocommerce_saved_order_items( $order_id ) {
		if ( is_admin() ) { //Admin?
			$order = new WC_Order_MB_Ifthen($order_id);
			$mb = new WC_Multibanco_IfThen_Webdados;
			if ( $order->mb_get_payment_method() == $mb->id ) { //Multibanco?
				if ( $mb->version >= '1.7.9.2' ) {
					//Details already existed - Let's check if value has changed
					if (
						( !$order_mb_details = $mb->get_order_mb_details($order_id) )
						||
						( floatval($order->mb_get_total()) != floatval($order_mb_details['val']) )
					) {
						$ref = $mb->get_ref($order_id, true );
						$mb->debug_log( 'Order '.$order->mb_get_id().' value changed' );
						if ( is_array($ref) ) {
							$order->add_order_note(
								sprintf(__('The Multibanco payment details have changed', 'multibanco_ifthen_for_woocommerce').':
– – – – – – – – – – – – – – – – – - - - -
'.__('Previous entity', 'multibanco_ifthen_for_woocommerce').': %s
'.__('Previous reference', 'multibanco_ifthen_for_woocommerce').': %s
'.__('Previous value', 'multibanco_ifthen_for_woocommerce').': %s
– – – – – – – – – – – – – – – – – - - - -
'.__('New entity', 'multibanco_ifthen_for_woocommerce').': %s
'.__('New reference', 'multibanco_ifthen_for_woocommerce').': %s
'.__('New value', 'multibanco_ifthen_for_woocommerce').': %s
– – – – – – – – – – – – – – – – – - - - -
'.__('If the customer pays using the previous details, the payment will be accepted by the Multibanco system, but the order will not be updated via callback.', 'multibanco_ifthen_for_woocommerce'),
isset( $order_mb_details['ent'] ) ? trim( $order_mb_details['ent'] ) : '',
isset( $order_mb_details['ref'] ) ? mbifthen_format_ref( $order_mb_details['ref'] ) : '',
isset( $order_mb_details['val'] ) ? wc_price( $order_mb_details['val'] ) : '',
trim( $ref['ent'] ),
mbifthen_format_ref( $ref['ref'] ),
wc_price( $order->mb_get_total() )
									)
							);
							//Notify client?
							if ( $mb->update_ref_client ) {
								$order->add_order_note(
									sprintf(__('The Multibanco payment details have changed', 'multibanco_ifthen_for_woocommerce').':
'.__('New entity', 'multibanco_ifthen_for_woocommerce').': %s
'.__('New reference', 'multibanco_ifthen_for_woocommerce').': %s
'.__('New value', 'multibanco_ifthen_for_woocommerce').': %s',
trim( $ref['ent'] ),
mbifthen_format_ref( $ref['ref'] ),
wc_price( $order->mb_get_total() )
									)
									,
									1
								);
							}
							?>
							<script type="text/javascript">
								alert('<?php _e('The Multibanco payment details have changed', 'multibanco_ifthen_for_woocommerce'); ?>. <?php echo ( $mb->update_ref_client ? __('The customer will be notified' , 'multibanco_ifthen_for_woocommerce') : __('You should notify the customer' , 'multibanco_ifthen_for_woocommerce') ); ?>. <?php _e('The page will now reload.' , 'multibanco_ifthen_for_woocommerce'); ?>');
								location.reload();
							</script>
							<?php
						}
					};
				}
			}
		}
	}
	
} else {

	add_action( 'admin_notices', 'admin_notices_mbifthen_woocommerce_not_active' );
	function admin_notices_mbifthen_woocommerce_not_active() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php _e( '<b>Multibanco (IfthenPay gateway) for WooCommerce</b> is installed and active but <b>WooCommerce</b> is not.', 'multibanco_ifthen_for_woocommerce' ); ?></p>
		</div>
		<?php
	}

}

/* If you're reading this you must know what you're doing ;-) Greetings from sunny Portugal! */

