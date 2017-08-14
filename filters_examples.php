<?php

// Multibanco IfThen - Email payment instructions filter
add_filter('multibanco_ifthen_email_instructions_table_html', 'my_multibanco_ifthen_email_instructions_table_html', 1, 4);
function my_multibanco_ifthen_email_instructions_table_html($html, $ent, $ref, $order_total) {
	ob_start();
	?>
	<h2>Multibanco payment instructions</h2>
	<p>
		<b>Entity:</b> <?php echo $ent; ?>
		<br/>
		<b>Reference:</b> <?php echo mbifthen_format_ref($ref); ?>
		<br/>
		<b>Value:</b> <?php echo $order_total; ?>
	</p>
	<p><?php
	$mb=new WC_Multibanco_IfThen_Webdados();
	//With WPML
	echo nl2br(function_exists('icl_object_id') ? icl_t($mb->id, $mb->id.'_extra_instructions', $mb->extra_instructions) : $mb->extra_instructions);
	?></p>
	<?php
	return ob_get_clean();
}

// Multibanco IfThen - Email payment received text filter
add_filter('multibanco_ifthen_email_instructions_payment_received', 'my_multibanco_ifthen_email_instructions_payment_received');
function my_multibanco_ifthen_email_instructions_payment_received ($html) {
	//We can, for example, format and return just part of the text
	ob_start();
	?>
	<p style="color: #FF0000; font-weight: bold;">
		Multibanco payment received.
	</p>
	<?php
	return ob_get_clean();
}

// Multibanco IfThen - Thank you page payment instructions filter
add_filter('multibanco_ifthen_thankyou_instructions_table_html', 'my_multibanco_ifthen_thankyou_instructions_table_html', 1, 4);
function my_multibanco_ifthen_thankyou_instructions_table_html($html, $ent, $ref, $order_total) {
	ob_start();
	?>
	<h2>Multibanco payment instructions</h2>
	<p>
		<b>Entity:</b> <?php echo $ent; ?>
		<br/>
		<b>Reference:</b> <?php echo mbifthen_format_ref($ref); ?>
		<br/>
		<b>Value:</b> <?php echo $order_total; ?>
	</p>
	<p><?php
	$mb=new WC_Multibanco_IfThen_Webdados();
	//Without WPML
	echo $mb->extra_instructions;
	?></p>
	<?php
	return ob_get_clean();
}

// Multibanco IfThen - SMS Instructions filter
add_filter('multibanco_ifthen_sms_instructions', 'my_multibanco_ifthen_sms_instructions', 1, 4);
function my_multibanco_ifthen_sms_instructions($html, $ent, $ref, $order_total) {
	return 'Ent. '.$ent.' Ref. '.$ref.' Val. '.$order_total;
}

// Multibanco IfThen - Change the icon html
add_filter('woocommerce_gateway_icon', 'my_woocommerce_gateway_icon', 1, 2);
function my_woocommerce_gateway_icon($html, $id) {
	if ($id=='multibanco_ifthen_for_woocommerce') {
		$html='No icon'; //Any html you want here
	}
	return $html;
}

// Multibanco IfThen - Use specific Entity and Subentity for some specific order details (Example: depending on the delivery method, or the items bought, the payment must be made with different Ent/Subent)
add_filter('multibanco_ifthen_base_ent_subent', 'testing_multibanco_ifthen_base_ent_subent', 1, 2);
function testing_multibanco_ifthen_base_ent_subent($base, $order) {
	//$base is a array with 'ent' and 'subent' keys / values
	//Test whatever you want here related to the $order object
	if (true) {
		//Change Entity and Subentity
		$base['ent'] = '99999';
		$base['subent'] = '999';
	} else {
		//Just use the plugin settings
	}
	return $base;
}

?>