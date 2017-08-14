<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order Class.
 *
 * These are our Orders, in order to abstract properties access, which extend the regular WooCommerce orders
 *
 */
if ( ! class_exists( 'WC_Multibanco_IfThen_Webdados' ) ) {
	class WC_Multibanco_IfThen_Webdados extends WC_Payment_Gateway {
		
		/**
		 * Constructor for your payment class
		 *
		 * @access public
		 * @return void
		 */
		public function __construct() {

			$this->id = 'multibanco_ifthen_for_woocommerce';

			//Check version and upgrade

			// Logs
			$this->debug = ( $this->get_option('debug')=='yes' ? true : false );
			if ($this->debug) $this->log = version_compare( WC_VERSION, '3.0', '>=' ) ? wc_get_logger() : new WC_Logger();
			$this->debug_email = $this->get_option('debug_email');
			
			$this->version = '1.9.1';
			$this->upgrade();

			load_plugin_textdomain('multibanco_ifthen_for_woocommerce', false, dirname(plugin_basename(__FILE__)) . '/lang/');
			//$this->icon = WP_PLUGIN_URL."/".plugin_basename( dirname(__FILE__)) . '/images/icon.png';
			$this->icon = plugins_url('images/icon.png', __FILE__);
			$this->has_fields = false;
			$this->method_title = __('Pagamento de Serviços no Multibanco (IfthenPay)', 'multibanco_ifthen_for_woocommerce');
			$this->secret_key = $this->get_option('secret_key');
			if (trim($this->secret_key)=='') {
				//First load?
				$this->secret_key=md5(home_url().time().rand(0,999));
				//Let's set the callback activation email as NOT sent
				update_option($this->id . '_callback_email_sent', 'no');
			}
			$this->notify_url = (
									get_option('permalink_structure')==''
									?
									home_url( '/?wc-api=WC_Multibanco_IfThen_Webdados&chave=[CHAVE_ANTI_PHISHING]&entidade=[ENTIDADE]&referencia=[REFERENCIA]&valor=[VALOR]&datahorapag=[DATA_HORA_PAGAMENTO]&terminal=[TERMINAL]' )
									:
									home_url( '/wc-api/WC_Multibanco_IfThen_Webdados/?chave=[CHAVE_ANTI_PHISHING]&entidade=[ENTIDADE]&referencia=[REFERENCIA]&valor=[VALOR]&datahorapag=[DATA_HORA_PAGAMENTO]&terminal=[TERMINAL]' )
								);
			$this->out_link_utm='?utm_source='.rawurlencode(esc_url(home_url('/'))).'&amp;utm_medium=link&amp;utm_campaign=mb_ifthen_plugin';

			//WPML?
			$this->wpml = function_exists('icl_object_id') && function_exists('icl_register_string');

			//Plugin options and settings
			$this->init_form_fields();
			$this->init_settings();

			//User settings
			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->extra_instructions = $this->get_option('extra_instructions');
			$this->ent = $this->get_option('ent');
			$this->subent = $this->get_option('subent');
			$this->settings_saved = $this->get_option('settings_saved');
			$this->send_to_admin = ( $this->get_option('send_to_admin')=='yes' ? true : false );
			$this->only_portugal = ( $this->get_option('only_portugal')=='yes' ? true : false );
			$this->only_above = $this->get_option('only_above');
			$this->only_bellow = $this->get_option('only_bellow');
			$this->stock_when = $this->get_option('stock_when');
			$this->update_ref_client = ( $this->get_option('update_ref_client')=='yes' ? true : false );
	 
			// Actions and filters
			add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'send_callback_email'));
			if ($this->wpml) add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'register_wpml_strings'));
			add_action('woocommerce_thankyou_'.$this->id, array($this, 'thankyou'));
			add_filter('woocommerce_available_payment_gateways', array($this, 'disable_if_currency_not_euro'));
			add_filter('woocommerce_available_payment_gateways', array($this, 'disable_unless_portugal'));
			add_filter('woocommerce_available_payment_gateways', array($this, 'disable_only_above_or_bellow'));

			// APG SMS Notifications Integration
			// https://wordpress.org/plugins/woocommerce-apg-sms-notifications/
			add_filter('apg_sms_message', array($this, 'sms_instructions_apg'), 10, 2);
		 
			// Customer Emails
			//add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 2); - "Hyyan WooCommerce Polylang Integration" removes this action
			add_action('woocommerce_email_before_order_table', array($this, 'email_instructions_1'), 10, 2); //Avoid "Hyyan WooCommerce Polylang Integration" remove_action

			// Payment listener/API hook
			add_action('woocommerce_api_wc_multibanco_ifthen_webdados', array($this, 'callback'));

			// Filter to decide if payment_complete reduces stock, or not
			add_filter('woocommerce_payment_complete_reduce_order_stock', array($this, 'woocommerce_payment_complete_reduce_order_stock'), 10, 2);

			// Admin notice if callback activation email is still not sent
			add_action('admin_notices', array($this, 'admin_notices'));
			
		}

		/**
		 * Upgrades (if needed)
		 */
		function upgrade() {
			if ($this->get_option('version') < $this->version) {
				//Upgrade
				$this->debug_log( 'Upgrade to '.$this->version.' started' );
				if ($this->version=='1.0.1') {
					//Only change is to set the version on the database. It's done below
				}
				if ($this->get_option('version')<'1.7.9.2' && $this->version>='1.7.9.2') {
					/* THIS SHOULD BE ABSTRACTED FROM POST / POST META - START */
					//Update all order totals
					$args = array(
						'post_type' => 'shop_order',
						'post_status' => array_keys( wc_get_order_statuses() ),
						'posts_per_page' => -1,
						'meta_query' => array(
							array(
								'key'=>'_payment_method',
								'value'=>$this->id,
								'compare'=>'LIKE'
							)
						)
					);
					//This will need to change when the order is no longer a WP post
					$orders = get_posts( $args );
					/* THIS SHOULD BE ABSTRACTED FROM POST / POST META - END */
					foreach ( $orders as $orderpost ) {
						$order = new WC_Order_MB_Ifthen($orderpost->ID);
						$order->mb_update_meta_data( '_'.$this->id.'_val', $order->mb_get_total() );
					}
				}
				//Upgrade on the database - Risky?
				$temp=get_option('woocommerce_multibanco_ifthen_for_woocommerce_settings','');
				$temp['version']=$this->version;
				update_option('woocommerce_multibanco_ifthen_for_woocommerce_settings', $temp);
				$this->debug_log( 'Upgrade to '.$this->version.' finished' );
			}
		}

		/**
		 * WPML compatibility
		 */
		function register_wpml_strings() {
			//These are already registered by WooCommerce Multilingual
			/*$to_register=array(
				'title',
				'description',
			);*/
			$to_register=array(
				'extra_instructions'
			);
			foreach($to_register as $string) {
				icl_register_string($this->id, $this->id.'_'.$string, $this->settings[$string]);
			}
		}


		/* Set email correct language - Stolen from WCML emails.class.php */
		function change_email_language($lang) {
			global $sitepress;
			//Unload
			unload_textdomain('multibanco_ifthen_for_woocommerce');
			if ($lang=='en') {
				//English? Just use plugin default strings
			} else {
				$this->locale = $sitepress->get_locale( $lang );
				add_filter( 'plugin_locale', array( $this, 'set_locale_for_emails' ), 10, 2 );
				load_plugin_textdomain('multibanco_ifthen_for_woocommerce', false, dirname(plugin_basename(__FILE__)).'/lang/');
				remove_filter( 'plugin_locale', array( $this, 'set_locale_for_emails' ), 10, 2 );
				//global $wp_locale;
				//$wp_locale = new WP_Locale();
			}
		}
		// set correct locale code for emails
		function set_locale_for_emails($locale, $domain) {
			if( $domain == 'multibanco_ifthen_for_woocommerce' && $this->locale ){
				$locale = $this->locale;
			}
			return $locale;
		}

		/**
		 * Initialise Gateway Settings Form Fields
		 * 'setting-name' => array(
		 *		'title' => __( 'Title for setting', 'woothemes' ),
		 *		'type' => 'checkbox|text|textarea',
		 *		'label' => __( 'Label for checkbox setting', 'woothemes' ),
		 *		'description' => __( 'Description for setting' ),
		 *		'default' => 'default value'
		 *	),
		 */
		function init_form_fields() {
		
			$this->form_fields = array(
				'enabled' => array(
								'title' => __('Enable/Disable', 'woocommerce'), 
								'type' => 'checkbox', 
								'label' => __( 'Enable "Pagamento de Serviços no Multibanco" (using IfthenPay)', 'multibanco_ifthen_for_woocommerce'), 
								'default' => 'no'
							),
				'ent' => array(
								'title' => __('Entity', 'multibanco_ifthen_for_woocommerce'), 
								'type' => 'number',
								'description' => __( 'Entity provided by IfthenPay when signing the contract. (E.g.: 10559, 11202, 11473, 11604)', 'multibanco_ifthen_for_woocommerce'), 
								'default' => '',
								'css' => 'width: 90px;',
								'custom_attributes' => array(
									'maxlength'	=> 5,
									'size' => 5,
									'max' => 99999
								)
							),
				'subent' => array(
								'title' => __('Subentity', 'multibanco_ifthen_for_woocommerce'), 
								'type' => 'number', 
								'description' => __('Subentity provided by IfthenPay when signing the contract. (E.g.: 999)', 'multibanco_ifthen_for_woocommerce'), 
								'default' => '',
								'css' => 'width: 50px;',
								'custom_attributes' => array(
									'maxlength'	=> 3,
									'size' => 3,
									'max' => 999
								)   
							),
			);
			//if(trim(strlen($this->get_option('ent')))==5 && trim(strlen($this->get_option('subent')))<=3 && intval($this->get_option('ent'))>0 && intval($this->get_option('subent'))>0 && trim($this->secret_key)!='') {
				$this->form_fields = array_merge($this->form_fields ,array(
					'secret_key' => array(
									'title' => __('Anti-phishing key', 'multibanco_ifthen_for_woocommerce'), 
									'type' => 'hidden', 
									'description' => '<b id="woocommerce_multibanco_ifthen_for_woocommerce_secret_key_label">'.$this->secret_key.'</b><br/>'.__('To ensure callback security, generated by the system and that must be provided to IfthenPay when asking for the callback activation.', 'multibanco_ifthen_for_woocommerce'), 
									'default' => $this->secret_key 
								),
					'title' => array(
									'title' => __('Title', 'woocommerce' ), 
									'type' => 'text', 
									'description' => __('This controls the title which the user sees during checkout.', 'woocommerce')
													.($this->wpml ? ' '.__('You should translate this string in <a href="admin.php?page=wpml-string-translation%2Fmenu%2Fstring-translation.php">WPML - String Translation</a> after saving the settings', 'multibanco_ifthen_for_woocommerce') : ''), 
									'default' => __('Pagamento de Serviços no Multibanco', 'multibanco_ifthen_for_woocommerce')
								),
					'description' => array(
									'title' => __('Description', 'woocommerce' ), 
									'type' => 'textarea',
									'description' => __('This controls the description which the user sees during checkout.', 'woocommerce' )
													.($this->wpml ? ' '.__('You should translate this string in <a href="admin.php?page=wpml-string-translation%2Fmenu%2Fstring-translation.php">WPML - String Translation</a> after saving the settings', 'multibanco_ifthen_for_woocommerce') : ''), 
									'default' => __('Easy and simple payment using "Pagamento de Serviços" at any "Multibanco" ATM terminal or your Home Banking service. (Only available to customers of Portuguese banks. Payment service provided by IfthenPay.)', 'multibanco_ifthen_for_woocommerce')
								),
					'only_portugal' => array(
									'title' => __('Only for Portuguese customers?', 'multibanco_ifthen_for_woocommerce'), 
									'type' => 'checkbox', 
									'label' => __( 'Enable only for customers whose address is in Portugal', 'multibanco_ifthen_for_woocommerce'), 
									'default' => 'no'
								),
					'only_above' => array(
									'title' => __('Only for orders above', 'multibanco_ifthen_for_woocommerce'), 
									'type' => 'number', 
									'description' => __( 'Enable only for orders above x &euro; (exclusive). Leave blank (or zero) to allow for any order value.', 'multibanco_ifthen_for_woocommerce').' <br/> '.__( 'By design, Multibanco only allows payments from 1 to 999999 &euro; (inclusive). You can use this option to further limit this range.', 'multibanco_ifthen_for_woocommerce'), 
									'default' => ''
								),
					'only_bellow' => array(
									'title' => __('Only for orders below', 'multibanco_ifthen_for_woocommerce'), 
									'type' => 'number', 
									'description' => __( 'Enable only for orders below x &euro; (exclusive). Leave blank (or zero) to allow for any order value.', 'multibanco_ifthen_for_woocommerce').' <br/> '.__( 'By design, Multibanco only allows payments from 1 to 999999 &euro; (inclusive). You can use this option to further limit this range.', 'multibanco_ifthen_for_woocommerce'), 
									'default' => ''
								),
					'stock_when' => array(
									'title' => __('Reduce stock', 'multibanco_ifthen_for_woocommerce'), 
									'type' => 'select', 
									'description' => __( 'Choose when to reduce stock.', 'multibanco_ifthen_for_woocommerce'), 
									'default' => '',
									'options'	=> array(
										''		=> __('when order is paid (requires active callback)', 'multibanco_ifthen_for_woocommerce'),
										'order'	=> __('when order is placed (before payment)', 'multibanco_ifthen_for_woocommerce'),
									),
								),
					'extra_instructions' => array(
									'title' => __('Extra instructions', 'multibanco_ifthen_for_woocommerce' ), 
									'type' => 'textarea',
									'description' => __('This controls the text which the user sees below the payment details on the "Thank you" page and "New order" email.', 'multibanco_ifthen_for_woocommerce' )
													.($this->wpml ? ' '.__('You should translate this string in <a href="admin.php?page=wpml-string-translation%2Fmenu%2Fstring-translation.php">WPML - String Translation</a> after saving the settings', 'multibanco_ifthen_for_woocommerce') : ''), 
									'default' => __('The receipt issued by the ATM machine is a proof of payment. Keep it.', 'multibanco_ifthen_for_woocommerce')
								),
					'send_to_admin' => array(
									'title' => __('Send instructions to admin?', 'multibanco_ifthen_for_woocommerce'), 
									'type' => 'checkbox', 
									'label' => __( 'Should the payment details also be sent to admin?', 'multibanco_ifthen_for_woocommerce'), 
									'default' => 'yes'
								),
					'update_ref_client' => array(
									'title' => __('Email reference update to client?', 'multibanco_ifthen_for_woocommerce'), 
									'type' => 'checkbox', 
									'label' => __( 'If the payment details change because of an update on the backend, should the client be notified?', 'multibanco_ifthen_for_woocommerce'), 
									'default' => 'no'
								),
					'debug' => array(
									'title' => __( 'Debug Log', 'woocommerce' ),
									'type' => 'checkbox',
									'label' => __( 'Enable logging', 'woocommerce' ),
									'default' => 'no',
									'description' => sprintf( __( 'Log plugin events, such as callback requests, inside <code>%s</code>', 'multibanco_ifthen_for_woocommerce' ), wc_get_log_file_path($this->id) ),
								),
					'debug_email' => array(
									'title' => __( 'Debug to email', 'multibanco_ifthen_for_woocommerce' ),
									'type' => 'email',
									'label' => __( 'Enable email logging', 'multibanco_ifthen_for_woocommerce' ),
									'default' => '',
									'description' => __( 'Send plugin events to this email address, such as callback requests.', 'multibanco_ifthen_for_woocommerce' ),
								)
				)	);
			//}
			$this->form_fields = array_merge($this->form_fields ,array(
				'settings_saved' => array(
								'title' => '', 
								'type' => 'hidden',
								'default' => 0
							),
			));
		
		}
		public function admin_options() {
			?>
			<div id="wc_ifthen">
				<div id="wc_ifthen_rightbar">
					<h4><?php _e('Commercial information', 'multibanco_ifthen_for_woocommerce'); ?>:</h4>
					<p><a href="http://www.ifthenpay.com/<?php echo esc_attr($this->out_link_utm); ?>" title="<?php echo esc_attr(sprintf(__('Please contact %s', 'multibanco_ifthen_for_woocommerce'), 'IfthenPay')); ?>" target="_blank"><img src="<?php echo plugins_url('images/ifthenpay.png', __FILE__); ?>" width="200"/></a></p>
					<h4><?php _e('Technical support or custom WordPress / WooCommerce development', 'multibanco_ifthen_for_woocommerce'); ?>:</h4>
					<p><a href="http://www.webdados.pt/contactos/<?php echo esc_attr($this->out_link_utm); ?>" title="<?php echo esc_attr(sprintf(__('Please contact %s', 'multibanco_ifthen_for_woocommerce'), 'Webdados')); ?>" target="_blank"><img src="<?php echo plugins_url('images/webdados.png', __FILE__); ?>" width="200"/></a></p>
					<h4><?php _e('Please rate our plugin at WordPress.org', 'multibanco_ifthen_for_woocommerce'); ?>:</h4>
					<a href="https://wordpress.org/support/view/plugin-reviews/multibanco-ifthen-software-gateway-for-woocommerce?filter=5#postform" target="_blank" style="text-align: center; display: block;">
						<div class="star-rating"><div class="star star-full"></div><div class="star star-full"></div><div class="star star-full"></div><div class="star star-full"></div><div class="star star-full"></div></div>
					</a>
					<div class="clear"></div>
				</div>
				<div id="wc_ifthen_settings">
					<h3><?php echo $this->method_title; ?> <span style="font-size: 75%;">v.<?php echo $this->version; ?></span></h3>
					<p><b><?php _e('In order to use this plugin you <u>must</u>:', 'multibanco_ifthen_for_woocommerce'); ?></b></p>
					<ul class="wc_ifthen_list">
						<li><?php printf( __('Set WooCommerce currency to <b>Euros (&euro;)</b> %1$s', 'multibanco_ifthen_for_woocommerce'), '<a href="admin.php?page=wc-settings&amp;tab=general">&gt;&gt;</a>.'); ?></li>
						<li><?php printf( __('Sign a contract with %1$s. To get more informations on this service go to %2$s.', 'multibanco_ifthen_for_woocommerce'), '<b><a href="http://www.ifthenpay.com/'.esc_attr($this->out_link_utm).'" target="_blank">IfthenPay</a></b>', '<a href="http://www.ifthenpay.com/'.esc_attr($this->out_link_utm).'" target="_blank">http://www.ifthenpay.com</a>'); ?></li>
						<li><?php _e('Fill in all details (entity and subentity) provided by <b>IfthenPay</b> on the fields below.', 'multibanco_ifthen_for_woocommerce'); ?>
						<li><?php printf( __('Ask IfthenPay to activate "Callback" on your account using this exact URL: %1$s and this Anti-phishing key: %2$s', 'multibanco_ifthen_for_woocommerce'), '<br/><code><b>'.$this->notify_url.'</b></code><br/>', '<br/><code><b>'.$this->secret_key.'</b></code>'); ?></li>
					</ul>
					<?php
					$hide_extra_fields=false;
					if(trim(strlen($this->ent))==5 && trim(strlen($this->subent))<=3 && intval($this->ent)>0 && intval($this->subent)>0 && trim($this->secret_key)!='') {
						if ($callback_email_sent=get_option($this->id . '_callback_email_sent')) { //No notice for older versions
							if ($callback_email_sent=='no') {
								if (!isset($_GET['callback_warning'])) {
									?>
									<div id="message" class="error">
										<p><strong><?php _e('You haven\'t yet asked IfthenPay for the "Callback" activation. The orders will NOT be automatically updated upon payment.', 'multibanco_ifthen_for_woocommerce'); ?></strong></p>
									</div>
									<?php
								}
							}
						}
						?>
						<p id="wc_ifthen_callback_open_p"><a href="#" id="wc_ifthen_callback_open" class="button button-small"><?php _e('Click here to ask IfthenPay to activate the "Callback"', 'multibanco_ifthen_for_woocommerce'); ?></a></p>
						<div id="wc_ifthen_callback_div">
							<p><?php _e('This will submit a request to IfthenPay asking them to activate the "Callback" on your account. If you have already asked for the "Callback" activation, wait for their feedback before submiting a new request. The following details will be sent via email to IfthenPay (with CC to your email address):', 'multibanco_ifthen_for_woocommerce'); ?></p>
							<table class="form-table">
								<tr valign="top">
									<th scope="row" class="titledesc"><?php _e('Email'); ?></th>
									<td class="forminp">
										<?php echo get_option('admin_email'); ?>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row" class="titledesc"><?php _e('Entity', 'multibanco_ifthen_for_woocommerce'); ?> / <?php _e('Subentity', 'multibanco_ifthen_for_woocommerce'); ?></th>
									<td class="forminp">
										<?php echo $this->ent; ?> / <?php echo $this->subent; ?>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row" class="titledesc"><?php _e('Anti-phishing key', 'multibanco_ifthen_for_woocommerce'); ?></th>
									<td class="forminp">
										<?php echo $this->secret_key; ?>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row" class="titledesc"><?php _e('Callback URL', 'multibanco_ifthen_for_woocommerce'); ?></th>
									<td class="forminp">
										<?php echo $this->notify_url; ?>
									</td>
								</tr>
							</table>
							<p style="text-align: center;">
								<b><?php _e('Attention: if you ever change from/to HTTP to/from HTTPS, or the permalinks structure,<br/>you may have to ask IfthenPay to update the callback URL.', 'multibanco_ifthen_for_woocommerce'); ?></b>
							</p>
							<p style="text-align: center; margin-bottom: 0px;">
								<input type="hidden" id="wc_ifthen_callback_send" name="wc_ifthen_callback_send" value="0"/>
								<input id="wc_ifthen_callback_submit" class="button-primary" type="button" value="<?php _e('Ask for Callback activation', 'multibanco_ifthen_for_woocommerce'); ?>"/>
								<input id="wc_ifthen_callback_cancel" class="button" type="button" value="<?php _e('Cancel', 'woocommerce'); ?>"/>
							</p>
						</div>
						<?php
					} else {
						$hide_extra_fields=true;
						if ($this->settings_saved==1) {
							?>
							<div id="message" class="error">
								<p><strong><?php _e('Invalid Entity (exactly 5 numeric characters) and/or Subentity (1 to 3 numeric characters).', 'multibanco_ifthen_for_woocommerce'); ?></strong></p>
							</div>
							<?php
						} else {
							?>
							<div id="message" class="error">
								<p><strong><?php _e('Set the Entity/Subentity and Save changes to set the other plugin options.', 'multibanco_ifthen_for_woocommerce'); ?></strong></p>
							</div>
							<?php
						}
					}
					?>
					<hr/>
					<script type="text/javascript">
					jQuery(document).ready(function(){
						<?php if ($hide_extra_fields) { ?>
							//Hide extra fields of there are errors on Entity or Subentity
							jQuery('#wc_ifthen_settings table.form-table tr:nth-child(n+4)').hide();
						<?php } ?>
						//Save secret key
						if (jQuery('#woocommerce_multibanco_ifthen_for_woocommerce_secret_key').val()=='') {
							jQuery('#woocommerce_multibanco_ifthen_for_woocommerce_secret_key').val('<?php echo $this->secret_key; ?>');
							jQuery('#woocommerce_multibanco_ifthen_for_woocommerce_secret_key_label').html('<?php echo $this->secret_key; ?>');
							jQuery('#mainform').submit();
						}
						//Settings saved
						jQuery('#woocommerce_multibanco_ifthen_for_woocommerce_settings_saved').val('1');
						//Callback activation
						jQuery('#wc_ifthen_callback_open').click(function() {
							ifthen_callback_open();
							return false;
						});
						jQuery('#wc_ifthen_callback_cancel').click(function() {
							jQuery('#wc_ifthen_callback_div').toggle();
							jQuery('#wc_ifthen_callback_open_p').toggle();
							return false;
						});
						//Callback send
						jQuery('#wc_ifthen_callback_submit').click(function() {
							if (confirm('<?php echo strip_tags(__('Are you sure you want to ask IfthenPay to activate the "Callback"?', 'multibanco_ifthen_for_woocommerce')); ?>')) {
								jQuery('#wc_ifthen_callback_send').val(1);
								jQuery('#mainform').submit();
								return true;
							} else {
								return false;
							}
						});
						function ifthen_callback_open() {
							jQuery('#wc_ifthen_callback_div').toggle();
							jQuery('#wc_ifthen_callback_open_p').toggle();
						}
						<?php
						if (isset($_GET['callback_warning']) && intval($_GET['callback_warning'])==1) {
							if ($callback_email_sent=get_option($this->id . '_callback_email_sent')) {
								if ($callback_email_sent=='no') { 
									?>
									ifthen_callback_open();
									jQuery('#wc_ifthen_callback_div').addClass('focus');
									setTimeout(function() {
										jQuery('#wc_ifthen_callback_div').removeClass('focus');
									}, 1000);
									<?php
								}
							}
						}
						?>
					});
					</script>
					<table class="form-table">
					<?php
					if (trim(get_woocommerce_currency())=='EUR') {
						$this->generate_settings_html();
					} else {
						?>
						<p><b><?php _e('ERROR!', 'multibanco_ifthen_for_woocommerce'); ?> <?php printf( __('Set WooCommerce currency to <b>Euros (&euro;)</b> %1$s', 'multibanco_ifthen_for_woocommerce'), '<a href="admin.php?page=wc-settings&amp;tab=general">'.__('here', 'multibanco_ifthen_for_woocommerce').'</a>.'); ?></b></p>
						<?php
					}
					?>
					</table>
				</div>
			</div>
			<div class="clear"></div>
			<style type="text/css">
				#wc_ifthen_rightbar {
					display: none;
				}
				@media (min-width: 961px) {
					#wc_ifthen {
						height: auto;
						overflow: hidden;
					}
					#wc_ifthen_settings {
						width: auto;
						overflow: hidden;
					}
					#wc_ifthen_rightbar {
						display: block;
						float: right;
						width: 200px;
						max-width: 20%;
						margin-left: 20px;
						padding: 15px;
						background-color: #fff;
					}
					#wc_ifthen_rightbar h4:first-child {
						margin-top: 0px;
					}
					#wc_ifthen_rightbar p {
					}
					#wc_ifthen_rightbar p img {
						max-width: 100%;
						height: auto;
					}
				}
				.wc_ifthen_list {
					list-style-type: disc;
					list-style-position: inside;
				}
				.wc_ifthen_list li {
					margin-left: 1.5em;
				}
				#wc_ifthen_callback_div {
					display: none;
					background-color: #fff;
					transition: background-color 0.5s ease;
					max-width: 90%;
					margin: auto;
					padding: 20px;
				}
				#wc_ifthen_callback_div.focus {
					background-color: #FFEFBF;
				}
			</style>
			<?php
		}

		public function send_callback_email() {
			if (isset($_POST['wc_ifthen_callback_send']) && intval($_POST['wc_ifthen_callback_send'])==1) {
				$to='callback@ifthenpay.com';
				$cc=get_option('admin_email');
				$subject='Activação de callback Ent. '.$this->ent.' Subent. '.$this->subent;
				$message='Por favor activar Callback com os seguintes dados:

Entidade:
'.$this->ent.'

Sub-entidade:
'.$this->subent.'

Chave anti-phishing:
'.$this->secret_key.'

URL:
'.$this->notify_url.'

Email enviado automaticamente do plugin WordPress "Multibanco (IfthenPay gateway) for WooCommerce" para '.$to.' com CC para '.$cc;
				$headers=array(
					'From: '.get_option('admin_email').' <'.get_option('admin_email').'>',
					'Cc: '.$cc
				);
				wp_mail($to, $subject, $message, $headers);
				update_option($this->id . '_callback_email_sent', 'yes');
				WC_Admin_Settings::add_message( __( 'The "Callback" activation request has been submited to IfthenPay. Wait for their feedback.', 'multibanco_ifthen_for_woocommerce' ) );
			}
		}

		/**
		 * Icon HTML
		 */
		public function get_icon() {
			$alt=($this->wpml ? icl_t($this->id, $this->id.'_title', $this->title) : $this->title);
			$icon_html = '<img src="'.esc_attr($this->icon).'" alt="'.esc_attr($alt).'" />';
			return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
		}

		/**
		 * Thank you page
		 */
		function thankyou($order_id) {
			$order = new WC_Order_MB_Ifthen($order_id);
			$ref = $this->get_ref($order_id);
			if (is_array($ref)) {
				echo $this->thankyou_instructions_table_html( $ref['ent'], $ref['ref'], $order->mb_get_total() );
			} else {
				?>
				<p><b><?php _e('Error getting Multibanco payment details', 'multibanco_ifthen_for_woocommerce'); ?>.</b></p>
				<?php
				if (is_string($ref)) {
					?>
					<p><?php echo $ref; ?></p>
					<?php
				}
			}
		}
		function thankyou_instructions_table_html($ent, $ref, $order_total) {
			$alt=($this->wpml ? icl_t($this->id, $this->id.'_title', $this->title) : $this->title);
			$extra_instructions=($this->wpml ? icl_t($this->id, $this->id.'_extra_instructions', $this->extra_instructions) : $this->extra_instructions);
			ob_start();
			?>
			<style type="text/css">
				table.multibanco_ifthen_for_woocommerce_table {
					width: auto !important;
					margin: auto;
				}
				table.multibanco_ifthen_for_woocommerce_table td,
				table.multibanco_ifthen_for_woocommerce_table th {
					background-color: #FFFFFF;
					color: #000000;
					padding: 10px;
					vertical-align: middle;
					white-space: nowrap;
				}
				@media only screen and (max-width: 450px)  {
					table.multibanco_ifthen_for_woocommerce_table td,
					table.multibanco_ifthen_for_woocommerce_table th {
						white-space: normal;
					}
				}
				table.multibanco_ifthen_for_woocommerce_table th {
					text-align: center;
					font-weight: bold;
				}
				table.multibanco_ifthen_for_woocommerce_table th img {
					margin: auto;
					margin-top: 10px;
				}
			</style>
			<table class="multibanco_ifthen_for_woocommerce_table" cellpadding="0" cellspacing="0">
				<tr>
					<th colspan="2">
						<?php _e('Payment instructions', 'multibanco_ifthen_for_woocommerce'); ?>
						<br/>
						<img src="<?php echo plugins_url('images/banner.png', __FILE__); ?>" alt="<?php echo esc_attr($alt); ?>" title="<?php echo esc_attr($alt); ?>"/>
					</th>
				</tr>
				<tr>
					<td><?php _e('Entity', 'multibanco_ifthen_for_woocommerce'); ?>:</td>
					<td><?php echo $ent; ?></td>
				</tr>
				<tr>
					<td><?php _e('Reference', 'multibanco_ifthen_for_woocommerce'); ?>:</td>
					<td><?php echo mbifthen_format_ref($ref); ?></td>
				</tr>
				<tr>
					<td><?php _e('Value', 'multibanco_ifthen_for_woocommerce'); ?>:</td>
					<td><?php echo $order_total; ?> &euro;</td>
				</tr>
				<tr>
					<td colspan="2" style="font-size: small;"><?php echo nl2br($extra_instructions); ?></td>
				</tr>
			</table>
			<?php
			return apply_filters('multibanco_ifthen_thankyou_instructions_table_html', ob_get_clean(), $ent, $ref, $order_total);
		}


		/**
		 * Email instructions
		 */
		function email_instructions_1($order, $sent_to_admin) { //"Hyyan WooCommerce Polylang" Integration removes "email_instructions" so we use "email_instructions_1"
			$this->email_instructions($order, $sent_to_admin);
		}
		function email_instructions($order, $sent_to_admin) {
			$order_id = version_compare( WC_VERSION, '3.0', '>=' ) ? $order->get_id() : $order->id;
			$order = new WC_Order_MB_Ifthen( $order_id );
			//WPML - Force correct language (?)
			if (function_exists('icl_object_id')) {
				global $sitepress;
				if ($sitepress) {
					$lang = $order->mb_get_meta('wpml_language');
					if( !empty( $lang ) ){
						$this->change_email_language($lang);
					}
				}
			}
			//Go
			if ( $this->id === $order->mb_get_payment_method() ) {
				$show=false;
				if (!$sent_to_admin) {
					$show=true;
				} else {
					if ($this->send_to_admin) {
						$show=true;
					}
				}
				if ($show) {
					//On Hold
					if ( $order->mb_has_status( 'on-hold' ) ) {
						$ref = $this->get_ref($order_id);
						if (is_array($ref)) {
							echo $this->email_instructions_table_html( $ref['ent'], $ref['ref'], $order->mb_get_total() );
						} else {
							?>
							<p><b><?php _e('Error getting Multibanco payment details', 'multibanco_ifthen_for_woocommerce'); ?>.</b></p>
							<?php
							if (is_string($ref)) {
								?>
								<p><?php echo $ref; ?></p>
								<?php
							}
						}
					}
					//Processing
					if ( $order->mb_has_status( 'processing' ) ) {
						echo $this->email_instructions_payment_received();
					}
				}
			}
		}
		function email_instructions_table_html($ent, $ref, $order_total) {
			$alt=($this->wpml ? icl_t($this->id, $this->id.'_title', $this->title) : $this->title);
			$extra_instructions=($this->wpml ? icl_t($this->id, $this->id.'_extra_instructions', $this->extra_instructions) : $this->extra_instructions);
			ob_start();
			?>
			<table cellpadding="10" cellspacing="0" align="center" border="0" style="margin: auto; margin-top: 10px; margin-bottom: 10px; border-collapse: collapse; border: 1px solid #1465AA; border-radius: 4px !important; background-color: #FFFFFF;">
				<tr>
					<td style="border: 1px solid #1465AA; border-top-right-radius: 4px !important; border-top-left-radius: 4px !important; text-align: center; color: #000000; font-weight: bold;" colspan="2">
						<?php _e('Payment instructions', 'multibanco_ifthen_for_woocommerce'); ?>
						<br/>
						<img src="<?php echo plugins_url('images/banner.png', __FILE__); ?>" alt="<?php echo esc_attr($alt); ?>" title="<?php echo esc_attr($alt); ?>" style="margin-top: 10px;"/>
					</td>
				</tr>
				<tr>
					<td style="border: 1px solid #1465AA; color: #000000;"><?php _e('Entity', 'multibanco_ifthen_for_woocommerce'); ?>:</td>
					<td style="border: 1px solid #1465AA; color: #000000; white-space: nowrap;"><?php echo $ent; ?></td>
				</tr>
				<tr>
					<td style="border: 1px solid #1465AA; color: #000000;"><?php _e('Reference', 'multibanco_ifthen_for_woocommerce'); ?>:</td>
					<td style="border: 1px solid #1465AA; color: #000000; white-space: nowrap;"><?php echo mbifthen_format_ref($ref); ?></td>
				</tr>
				<tr>
					<td style="border: 1px solid #1465AA; color: #000000;"><?php _e('Value', 'multibanco_ifthen_for_woocommerce'); ?>:</td>
					<td style="border: 1px solid #1465AA; color: #000000; white-space: nowrap;"><?php echo $order_total; ?> &euro;</td>
				</tr>
				<tr>
					<td style="font-size: x-small; border: 1px solid #1465AA; border-bottom-right-radius: 4px !important; border-bottom-left-radius: 4px !important; color: #000000; text-align: center;" colspan="2"><?php echo nl2br($extra_instructions); ?></td>
				</tr>
			</table>
			<?php
			return apply_filters('multibanco_ifthen_email_instructions_table_html', ob_get_clean(), $ent, $ref, $order_total);
		}
		function email_instructions_payment_received() {
			ob_start();
			?>
			<p>
				<b><?php _e('Multibanco payment received.', 'multibanco_ifthen_for_woocommerce'); ?></b>
				<br/>
				<?php _e('We will now process your order.', 'multibanco_ifthen_for_woocommerce'); ?>
			</p>
			<?php
			return apply_filters('multibanco_ifthen_email_instructions_payment_received', ob_get_clean());
		}

		/**
		 * SMS instructions - General. Can be used to feed any SMS gateway/plugin
		 */
		function sms_instructions($message, $order_id) {
			$order = new WC_Order_MB_Ifthen($order_id);
			$instructions='';
			if ($order->mb_get_payment_method() == $this->id) {
				switch ( $order->mb_get_status() ) {
				case 'on-hold':
				case 'pending':
					$ref = $this->get_ref($order_id);
					if (is_array($ref)) {
						$instructions = 
							__('Ent', 'multibanco_ifthen_for_woocommerce')
							.' '
							.$ref['ent']
							.' '
							.__('Ref', 'multibanco_ifthen_for_woocommerce')
							.' '.mbifthen_format_ref($ref['ref'])
							.' '
							.__('Val', 'multibanco_ifthen_for_woocommerce')
							.' '
							.$order->mb_get_total();
						$instructions=preg_replace('/\s+/', ' ', $instructions); //Remove extra spaces if necessary
						//Filters in case the website owner wants to customize the message
						$instructions=apply_filters( 'multibanco_ifthen_sms_instructions', $instructions, $ref['ent'], $ref['ref'], $order->mb_get_total() );
					} else {
						//error getting ref
					}
					break;
				case 'processing':
					//No instructions
					break;
				default:
					return;
					break;
				}
			}
			return $instructions;
		}
		/**
		 * SMS instructions for APG SMS Notifications
		 */
		function sms_instructions_apg($message, $order_id) {
			$replace=$this->sms_instructions($message, $order_id); //Get instructions
			return trim(str_replace('%multibanco_ifthen%', $replace, $message)); //Return message with %multibanco_ifthen% replaced by the instructions
		}

		/**
		 * Process it
		 */
		function process_payment($order_id) {
			$order = new WC_Order_MB_Ifthen($order_id);
			// Mark as on-hold
			$order->update_status('on-hold', __('Awaiting Multibanco payment.', 'multibanco_ifthen_for_woocommerce'));
			// Reduce stock levels
			if ($this->stock_when=='order') $order->mb_reduce_order_stock();
			// Remove cart
			WC()->cart->empty_cart();
			// Empty awaiting payment session
			if (isset($_SESSION['order_awaiting_payment'])) unset($_SESSION['order_awaiting_payment']);
			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url($order)
			);
		}


		/**
		 * Just for €
		 */
		function disable_if_currency_not_euro($available_gateways) {
			if ( isset($available_gateways[$this->id]) ) {
				if ( trim(get_woocommerce_currency())!='EUR' ) unset($available_gateways[$this->id]);
			}
			return $available_gateways;
		}

		/**
		 * Just for Portugal
		 */
		function get_customer_billing_country() {
			if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
				return trim(WC()->customer->get_billing_country());
			} else {
				return trim(WC()->customer->get_country());
			}
		}
		function disable_unless_portugal($available_gateways) {
			if ( isset($available_gateways[$this->id]) ) {
				if ( $available_gateways[$this->id]->only_portugal && WC()->customer && $this->get_customer_billing_country()!='PT') unset($available_gateways[$this->id]);
			}
			return $available_gateways;
		}

		/**
		 * Just above/bellow certain amounts
		 */
		function disable_only_above_or_bellow($available_gateways) {
			if ( isset($available_gateways[$this->id]) ) {
				if ( @floatval($available_gateways[$this->id]->only_above)>0 ) {
					if( WC()->cart->total<floatval($available_gateways[$this->id]->only_above) ) {
						unset($available_gateways[$this->id]);
					}
				} 
				if ( @floatval($available_gateways[$this->id]->only_bellow)>0 ) {
					if( WC()->cart->total>floatval($available_gateways[$this->id]->only_bellow) ) {
						unset($available_gateways[$this->id]);
					}
				} 
			}
			return $available_gateways;
		}


		/**
		 * Get current order Entity/Reference/Value from meta
		 */
		function get_order_mb_details($order_id) {
			$order = new WC_Order_MB_Ifthen($order_id);
			$ent = $order->mb_get_meta( '_'.$this->id.'_ent' );
			$ref = $order->mb_get_meta( '_'.$this->id.'_ref' );
			$val = $order->mb_get_meta( '_'.$this->id.'_val' );
			if ( !empty($ent) &&  !empty($ref) &&  !empty($val) ) {
				return array(
							'ent'	=> $ent,
							'ref'	=> $ref,
							'val'	=> $val,
						);
			}
			return false;
		}


		/**
		 * Set new order Entity/Reference/Value on meta
		 */
		function set_order_mb_details($order_id, $order_mb_details) {
			$order = new WC_Order_MB_Ifthen($order_id);
			$order->mb_update_meta_data( '_'.$this->id.'_ent', $order_mb_details['ent']);
			$order->mb_update_meta_data( '_'.$this->id.'_ref', $order_mb_details['ref']);
			$order->mb_update_meta_data( '_'.$this->id.'_val', $order_mb_details['val']);
		}


		/**
		 * Get/Create Reference
		 */
		function get_ref($order_id, $force_change=false) {
			$order = new WC_Order_MB_Ifthen($order_id);
			if (trim(get_woocommerce_currency())=='EUR') {
				if (
					!$force_change
					&&
					$order_mb_details = $this->get_order_mb_details($order_id)

				) {
					//Already created, return it!
					return array(
						'ent' => $order_mb_details['ent'],
						'ref' => $order_mb_details['ref'],
					);
				} else {
					//Value ok?
					if ( $order->mb_get_total()<1 ){
						return __('It\'s not possible to use Multibanco to pay values under 1&euro;.', 'multibanco_ifthen_for_woocommerce');
				 	} else {
				 		//Value ok? (again)
						if ( $order->mb_get_total()>=1000000 ){
							return __('It\'s not possible to use Multibanco to pay values above 999999&euro;.', 'multibanco_ifthen_for_woocommerce');
						} else {
							//Create a new reference
							//Filters to be able to override the Entity and Sub-entity - Can be usefull for marketplaces
							$base = apply_filters('multibanco_ifthen_base_ent_subent', array('ent' => $this->ent, 'subent' => $this->subent), $order);
							if ( trim(strlen($base['ent']))==5 && trim(strlen($base['subent']))<=3 && intval($base['ent'])>0 && intval($base['subent'])>0 && trim($this->secret_key)!='' ) {
								//$ref=$this->create_ref($base['ent'], $base['subent'], 0, $order->mb_get_total()); //For incremental mode
								$ref = $this->create_ref( $base['ent'], $base['subent'], rand(0,9999), $order->mb_get_total() ); //For random mode - Less loop possibility
								//Store on the order for later use (like the email)
								$this->set_order_mb_details($order_id, array(
									'ent'	=>	$base['ent'],
									'ref'	=>	$ref,
									'val'	=>	$order->mb_get_total(),
								));
								$this->debug_log( 'Multibanco payment details ('.$base['ent'].' / '.$ref.' / '.$order->mb_get_total().') generated for Order '.$order_id );
								//Return it!
								return array(
									'ent' => $base['ent'],
									'ref' => $ref
								);
							} else {
								$error_details='';
								if (trim(strlen($base['ent']))!=5 || (!intval($base['ent'])>0) ) {
									$error_details=__('Entity', 'multibanco_ifthen_for_woocommerce');
								} else {
									if (trim(strlen($base['subent']))!=5 || (!intval($base['subent'])>0) ) {
										$error_details=__('Subentity', 'multibanco_ifthen_for_woocommerce');
									} else {
										if (trim($this->secret_key)=='') {
											$error_details=__('Anti-phishing key', 'multibanco_ifthen_for_woocommerce');
										}
									}
								}
								return __('Configuration error. This payment method is disabled because required information was not set.', 'multibanco_ifthen_for_woocommerce').' '.$error_details.'.';
							}
						}
					}
				}
			} else {
				return __('Configuration error. This store currency is not Euros (&euro;).', 'multibanco_ifthen_for_woocommerce');
			}
		}
		function create_ref($ent, $subent, $seed, $total) {
			$subent=str_pad(intval($subent), 3, "0", STR_PAD_LEFT);
			$seed=str_pad(intval($seed), 4, "0", STR_PAD_LEFT);
			$chk_str=sprintf('%05u%03u%04u%08u', $ent, $subent, $seed, round($total*100));
			$chk_array=array(3, 30, 9, 90, 27, 76, 81, 34, 49, 5, 50, 15, 53, 45, 62, 38, 89, 17, 73, 51);
			$chk_val=0;
			for ($i = 0; $i < 20; $i++) {
				$chk_int = substr($chk_str, 19-$i, 1);
				$chk_val += ($chk_int%10)*$chk_array[$i];
			}
			$chk_val %= 97;
			$chk_digits = sprintf('%02u', 98-$chk_val);
			$ref=$subent.$seed.$chk_digits;
			//Does it exists already? Let's browse the database! - This should be dealt with when orders are no longer posts
			$exists=false;
			/* THIS SHOULD BE ABSTRACTED FROM POST / POST META - START */
			$args = array(
				'meta_query' => array(
					array(
						'key' => '_'.$this->id.'_ent',
						'value' => $ent
					),
					array(
						'key' => '_'.$this->id.'_ref',
						'value' => $ref
					)
				),
				'post_type' => 'shop_order',
				'posts_per_page' => -1
			);
			$the_query = new WP_Query($args);
			if ($the_query->have_posts()) $exists=true;
			wp_reset_postdata();
			/* THIS SHOULD BE ABSTRACTED FROM POST / POST META - END */
			if ($exists) {
				//Reference exists - Let's try again
				//$seed=intval($seed)+1; //For incremental mode
				$seed=rand(0,9999); //For random mode - Less loop possibility
				$ref=$this->create_ref($ent, $subent, $seed, $total);
			}
			return $ref;
		}

		/* Payment complete - Stolen from PayPal method */
		function payment_complete( $order, $txn_id = '', $note = '' ) {
			$order->add_order_note( $note );
			$order->payment_complete( $txn_id );
		}
		/* Reduce stock on payment complete? */
		function woocommerce_payment_complete_reduce_order_stock($bool, $order_id) {
			$order = new WC_Order_MB_Ifthen($order_id);
			if ( $order->mb_get_payment_method() == $this->id ) {
				return $this->stock_when=='';
			} else {
				return $bool;
			}
		}

		/**
		 * Callback
		 *
		 * @access public
		 * @return void
		 */
		function callback() {
			@ob_clean();
			//We must 1st check the situation and then process it and send email to the store owner in case of error.
			if (isset($_GET['chave'])
				&&
				isset($_GET['entidade'])
				&&
				isset($_GET['referencia'])
				&&
				isset($_GET['valor'])
			) {
				//Let's process it
				$this->debug_log( '- Callback ('.$_SERVER['REQUEST_URI'].') with all arguments from '.$_SERVER['REMOTE_ADDR'] );
				$ref=trim(str_replace(' ', '', $_GET['referencia']));
				$ent=trim($_GET['entidade']);
				$val=floatval($_GET['valor']);
				$arguments_ok=true;
				$arguments_error='';
				if (trim($_GET['chave'])!=trim($this->secret_key)) {
					$arguments_ok=false;
					$arguments_error.=' - Key';
				}
				if (!is_numeric($ref)) {
					$arguments_ok=false;
					$arguments_error.=' - Ref (numeric)';
				}
				if (strlen($ref)!=9) {
					$arguments_ok=false;
					$arguments_error.=' - Ref (length)';
				}
				if (!is_numeric($ent)) {
					$arguments_ok=false;
					$arguments_error.=' - Ent (numeric)';
				}
				if (strlen($ent)!=5) {
					$arguments_ok=false;
					$arguments_error.=' - Ent (length)';
				}
				if (!$val>=1) {
					$arguments_ok=false;
					$arguments_error.=' - Value';
				}
				if ($arguments_ok) { // Isto deve ser separado em vários IFs para melhor se identificar o erro
					//if (WC()->version<'2.2') {
					if ( version_compare( WC_VERSION, '2.2', '<' ) ) {
						//The old way
						$args = array(
							'post_type'	=> 'shop_order',
							'post_status' => 'publish',
							'posts_per_page' => -1,
							'tax_query' => array(
								array(
								'taxonomy' => 'shop_order_status',
								'field' => 'slug',
								'terms' => array('on-hold', 'pending')
								)
							),
							'meta_query' => array(
								array(
									'key'=>'_'.$this->id.'_ent',
									'value'=>$ent,
									'compare'=>'LIKE'
								),
								array(
									'key'=>'_'.$this->id.'_ref',
									'value'=>$ref,
									'compare'=>'LIKE'
								)
							)
						);
					} else {
						/* THIS SHOULD BE ABSTRACTED FROM POST / POST META - START */
						//The new way
						$args = array(
							'post_type' => 'shop_order',
							'post_status' => array('wc-on-hold', 'wc-pending'),
							'posts_per_page' => -1,
							'meta_query' => array(
								array(
									'key'=>'_'.$this->id.'_ent',
									'value'=>$ent,
									'compare'=>'LIKE'
								),
								array(
									'key'=>'_'.$this->id.'_ref',
									'value'=>$ref,
									'compare'=>'LIKE'
								)
							)
						);
					}
					$the_query = new WP_Query($args);
					if ($the_query->have_posts()) {
						if ($the_query->post_count==1) {
							while ( $the_query->have_posts() ) : $the_query->the_post();
								$order = new WC_Order_MB_Ifthen( $the_query->post->ID );
							endwhile;
							/* THIS SHOULD BE ABSTRACTED FROM POST / POST META - END */
							if ($val==floatval($order->mb_get_total())) {
								//We must first change the order status to "pending" and then to "processing" or no email will be sent to the client
								include_once(ABSPATH.'wp-admin/includes/plugin.php' );
								if ( !is_plugin_active('order-status-emails-for-woocommerce/order-status-emails-for-woocommerce.php') ) //Only if this plugin is not active
									if ($order->mb_get_status()!='pending') $order->update_status('pending', __('Temporary status. Used to force an email on the next order status change.', 'multibanco_ifthen_for_woocommerce'));
								
								/*if ($this->stock_when=='') $order->mb_reduce_order_stock();
								$order->update_status('processing', __('Multibanco payment received.', 'multibanco_ifthen_for_woocommerce')); //Paid */
								$note=__('Multibanco payment received.', 'multibanco_ifthen_for_woocommerce');
								if (isset($_GET['datahorapag']) && trim($_GET['datahorapag'])!='') {
									$note.=' '.trim($_GET['datahorapag']);
								}
								if (isset($_GET['terminal']) && trim($_GET['terminal'])!='') {
									$note.=' '.trim($_GET['terminal']);
								}
								$this->payment_complete($order, '', $note);
								
								header('HTTP/1.1 200 OK');
								$this->debug_log( '-- Multibanco payment received - Order '.$order->mb_get_id(), 'notice', true, 'Callback ( '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REQUEST_URI'].' ) from '.$_SERVER['REMOTE_ADDR'].' - Multibanco payment received' );
								echo 'OK - Multibanco payment received';
							} else {
								header('HTTP/1.1 200 OK');
								$this->debug_log( '-- Error: The value does not match - Order '.$order->mb_get_id(), 'warning', true, 'Callback ( '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REQUEST_URI'].' ) from '.$_SERVER['REMOTE_ADDR'].' - The value does not match' );
								echo 'Error: The value does not match';
							}
						} else {
							header('HTTP/1.1 200 OK');
							$this->debug_log( '-- Error: More than 1 order found awaiting payment with these details', 'warning', true, 'Callback ( '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REQUEST_URI'].' ) from '.$_SERVER['REMOTE_ADDR'].' - More than 1 order found awaiting payment with these details' );
							echo 'Error: More than 1 order found awaiting payment with these details';
						}
					} else {
						header('HTTP/1.1 200 OK');
						$this->debug_log( '-- Error: No orders found awaiting payment with these details', 'warning', true, 'Callback ( '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REQUEST_URI'].' ) from '.$_SERVER['REMOTE_ADDR'].' - No orders found awaiting payment with these details' );
						echo 'Error: No orders found awaiting payment with these details';
					}
				} else {
					//header("Status: 400");
					$this->debug_log( '-- Argument errors', 'warning', true, 'Callback ( '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REQUEST_URI'].' ) with argument errors from '.$_SERVER['REMOTE_ADDR'].$arguments_error );
					wp_die('Argument errors', 'WC_Multibanco_IfThen_Webdados', array( 'response' => 500 ) ); //Sends 500
				}
			} else {
				//header("Status: 400");
				$this->debug_log( '- Callback ('.$_SERVER['REQUEST_URI'].') with missing arguments from '.$_SERVER['REMOTE_ADDR'], 'warning', true, 'Callback ( '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REQUEST_URI'].' ) with missing arguments from '.$_SERVER['REMOTE_ADDR'] );
				wp_die('Error: Something is missing...', 'WC_Multibanco_IfThen_Webdados', array( 'response' => 500 ) ); //Sends 500
			}
		}

		/* Debug / Log */
		public function debug_log( $message, $level='debug', $to_email=false, $email_message='' ) {
			if ( $this->debug ) {
				if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
					$this->log->$level( $message, array( 'source' => $this->id ) );
				} else {
					$this->log->add($this->id, $message);
				}
			}
			if ( trim($this->debug_email)!='' && $to_email ) {
				wp_mail(
					trim($this->debug_email),
					$this->id.' - '.$message,
					$email_message
				);
			}
		}

		/* Global admin notices - For example if callback email activation is still not sent */
		function admin_notices() {
			if (trim($this->enabled)=='yes' && trim(strlen($this->ent))==5 && trim(strlen($this->subent))<=3 && intval($this->ent)>0 && intval($this->subent)>0 && trim($this->secret_key)!='') {
				if ($callback_email_sent=get_option($this->id . '_callback_email_sent')) { //No notice for older versions
					if ($callback_email_sent=='no') {
						if (!isset($_GET['callback_warning'])) {
							$callback_notice_dismiss=get_option($this->id . '_callback_notice_dismiss');
							if (!$callback_notice_dismiss || $callback_notice_dismiss!='yes') {
								?>
								<div id="multibanco_ifthen_callback_notice" class="error notice" style="padding-right: 38px; position: relative;">
									<p>
										<b><?php _e('Pagamento de Serviços no Multibanco (IfthenPay)', 'multibanco_ifthen_for_woocommerce'); ?></b>
										<br/>
										<?php _e('You haven\'t yet asked IfthenPay for the "Callback" activation. The orders will NOT be automatically updated upon payment.', 'multibanco_ifthen_for_woocommerce'); ?>
										<br/>
										<a href="admin.php?page=wc-settings&amp;tab=checkout&amp;section=wc_multibanco_ifthen_webdados&amp;callback_warning=1"><b><?php _e('Do it here', 'multibanco_ifthen_for_woocommerce'); ?></b></a>.
									</p>
									<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
								</div>
								<script type="text/javascript">
								jQuery(document).ready(function(){
									jQuery('#multibanco_ifthen_callback_notice .notice-dismiss').click(function() {
										if (confirm('<?php _e('Are you sure you want to dismiss this notice? You will not be warned again.', 'multibanco_ifthen_for_woocommerce'); ?>')) {
											var data = {
												'action': 'multibanco_ifthen_dismiss_callback_notice',
											};
											jQuery.post(ajaxurl, data, function(response) {
												//alert('Got this from the server: ' + response);
											});
											jQuery('#multibanco_ifthen_callback_notice').fadeTo( 100 , 0, function() {
												jQuery(this).slideUp( 100, function() {
													jQuery(this).remove();
												});
											});
										}
									});
								});
								</script>
								<?php
							}
						}
					}
				}
			}
		}

	}
}