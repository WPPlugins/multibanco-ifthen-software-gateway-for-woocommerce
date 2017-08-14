=== Multibanco (IfthenPay gateway) for WooCommerce ===
Contributors: webdados, wonderm00n
Tags: woocommerce, payment, gateway, multibanco, atm, debit card, credit card, bank, ecommerce, e-commerce, ifthen, ifthen software, ifthenpay, webdados, sms, php7
Author URI: http://www.webdados.pt
Plugin URI: http://www.webdados.pt/produtos-e-servicos/internet/desenvolvimento-wordpress/multibanco-ifthen-software-gateway-woocommerce-wordpress/
Requires at least: 4.4
Tested up to: 4.7.3
Stable tag: 1.9.2

This plugin allows Portuguese customers to pay WooCommerce orders with Multibanco (Pag. Serviços), using the IfthenPay payment gateway.

== Description ==

“Pagamento de Serviços” (service payment) on Multibanco (the Portuguese ATM network), or on Home Banking services, is the most popular way of paying services and (online) purchases in Portugal. The Portuguese consumer trusts in the “Multibanco” system more than any other.

This plugin will allow you to generate a payment Reference that the customer can then use to pay for his order on the ATM or Home Banking service. This plugin uses one of the several gateways/services available in Portugal, [IfthenPay](http://www.ifthenpay.com), and a contract with this company is required.

This is the official [IfthenPay](http://www.ifthenpay.com) plugin, although the technical support is provided by [Webdados](http://www.webdados.pt).

= Features: =

* Generates a Multibanco Reference for simple payment on the Portuguese ATM or Home Banking service network;
* Automatically changes the order status to “Processing” (or “Completed” if the order only contains virtual downloadable products) and notifies both the customer and the store owner, if the automatic “Callback” upon payment is activated, which can be asked to IfthenPay via the plugin settings screen;
* It's possible to set minimum and maximum order totals for this payment gateway to be available;
* Possibility to choose to reduce stock when the order is created or when it's paid;
* Allows searching orders (on the admin area) by Reference;
* 3rd party plugin SMS notification integration:
	* WooCommerce - APG SMS Notifications;
	* Others to be added by request;
* WPML tested and compatible;
* Polylang tested;
* PHP7 tested and compatible;

== Installation ==

* Use the included automatic install feature on your WordPress admin panel and search for “Multibanco (IfthenPay gateway) for WooCommerce”.
* Go to WooCoomerce > Settings > Checkout > Pagamento de Serviços no Multibanco and fill in the details provided by IfthenPay (Entity and Subentity) in order to be able to use this payment method. A contract with IfthenPay is needed to get these details.
* Make sure you've asked IfthenPay to activate the “Callback” on their side with the URL and Anti-phishing key provided on the settings screen. This can now be done via this same screen.
* Start receiving “pilim”.

== Frequently Asked Questions ==

= Can I start receiving payments right away? Show me the money! =

Nop! You have to sign a contract with IfthenPay in order to activate this service. Go to [http://www.ifthenpay.com](http://www.ifthenpay.com) for more information.

= The generated reference does not contain the order number. How will I know which order as been paid? =

IfthenPay will send you an email each time a reference is paid, but the reference does not contain the order number. This is by design: our plugin does not include the order number on the reference because of the way WooCommerce/WordPress set orders/posts IDs.

Anyway, you do not need the order number on the reference because our plugin uses a callback mechanism so that IfthenPay can automatically notify WooCommerce that a specific reference was paid. The order is linked to the reference on the database and will, also automatically, be set as paid.

If you still need to know to which order a reference is linked to, you can use the search box on the WooCommerce Orders administration screen.

= Can I test the callback call to simulate a payment and test what will happen when a real payment is made by a customer? =

Yes, you can. Edit you wp-config.php file and set WP_DEBUG to true. Then place a test order on the frontend, go to the order edit screen and click the button that says "Simulate callback payment".

= Can I use this plugin / IfthenPay service on more than one website? =

Yes, but not with the same subentity. Ask IfthenPay for a different subentity for each website you want to use the service on. There is no extra costs involved, and you can even route the payments for distinct bank accounts.

= My website is multilingual. Will I be able to use this plugin? =

Yes. This plugin is officially WPML compatible. You will need the WPML and WPML String Translation Plugins (alongside with WooCommerce Multilingual that is mandatory for any WooCommerce + WPML install). 

= [WPML] How can I translate the payment method title and description that the client sees on the checkout page for secondary languages? =

Go to WPML > String Translation > Search and translate the `multibanco_ifthen_for_woocommerce_gateway_title` and `multibanco_ifthen_for_woocommerce_gateway_description` strings on the `woocommerce` domain. Don't forget to check the “Translation is complete” checkbox and click “Save”. You should also translate the “Extra instructions” string by searching the `multibanco_ifthen_for_woocommerce_extra_instructions` string on the `multibanco_ifthen_for_woocommerce` domain.

= [SMS] How to include the Multibanco payment instructions on the SMS sent by “WooCommerce - APG SMS Notifications”? =

Go to WooCommerce > SMS Notifications and add the `%multibanco_ifthen%` variable to “Order received custom message”.

= Can I change the payment instructions look and feel on the “Thank you” page and/or the new order email, as well as the SMS message format? =

Yes you can! But you have to know your way around WordPress filters. There are two filters to do this and you can find examples of them inside `filters_examples.php`.

= Can I change the Multibanco icon on the checkout page? =

There's also a filter for this. See `filters_examples.php`.

= Can I use a specific Entity or Subentity based on order details? =

Yes, you should use the `multibanco_ifthen_base_ent_subent` filter. See `filters_examples.php`.

= I want to charge an additional fee for payments via Multibanco. How should I do it? =

You don't! It's illegal under Portuguese legislation to charge more based on the payment method chosen by the customer.
If you don't care about the law, there are plugins that allow setting extra fees per payment method, but don't ask us for support regarding this.

= IfthenPay says my callback URL is returning a 404 error. Should I sit in a corner and cry or is there a solution? =

Don't cry! There's a solution!
You probably have weird permalink settings (or no permalinks set at all) on your WordPress installation.
Tell them to change the callback URL from `http://yourwebsite/wc-api/WC_Multibanco_IfThen_Webdados/?chave=[CHAVE_ANTI_PHISHING]&entidade=[ENTIDADE]&referencia=[REFERENCIA]&valor=[VALOR]` to `http://yourwebsite/?wc-api=WC_Multibanco_IfThen_Webdados&chave=[CHAVE_ANTI_PHISHING]&entidade=[ENTIDADE]&referencia=[REFERENCIA]&valor=[VALOR]`.

= I need technical support. Who should I contact, IfthenPay or Webdados? =

Although this is the official IfthenPay WooCommerce plugin, the development and support is [Webdados](http://www.webdados.pt) responsibility.
For free/standard support you should use the support forums here at WordPress.org
For premium/urgent support or custom developments you should contact [Webdados](http://www.webdados.pt/contactos/) directly. Charges may (and most certainly will) apply.

== Changelog ==

= 1.9.2 =
* Using `WC()` instead of `$woocommerce`
* Using `wc_reduce_stock_levels()` instead of `$order->reduce_order_stock()` on WooCommerce 3.0 and above
* Using `WC()->customer->get_billing_country()` instead of `WC()->customer->get_country()` on WooCommerce 3.0 and above

= 1.9.1 =
* Started using the new WooCommerce 3.0 [logging system](https://woocommerce.wordpress.com/2017/01/26/improved-logging-in-woocommerce-2-7/)
* Quick (and dirty) fix for [a bug on WooCommerce 3.0](https://github.com/woocommerce/woocommerce/issues/13966) that is not allowing payment gateways to add information to transactional emails
* Improved debug logging

= 1.9 =
* Tested with WooCommerce 3.0.0-rc.2
* Changed version tests from 2.7 to 3.0
* New WC_Multibanco_IfThen_Webdados class for better code organization
* New WC_Order_MB_Ifthen class (extends WC_Order) to be used by the plugin to get and set order details
* Bumped `Tested up to` tag

= 1.8.9 =
* Multibanco payment option is now not shown if the currency is not Euro (Thanks @topsolutions)
* Bumped `Tested up to` tag

= 1.8.8.2 =
* Bumped `Tested up to` tag

= 1.8.8.1 =
* Small CSS fix so that the payment instructions table on the "Thank You" page is not partially hidden on some mobile devices (Thanks Jorge Fonseca for the report)

= 1.8.8 =
* WPML Fix: Shows the payment instructions on the correct language on the “Thank You” page and on Order Status and Customer Notes emails
* Spanish basic translation (email strings only)

= 1.8.7 =
* Started preparations for the new order meta setter and getter functions [coming on WooCommerce 2.7](https://github.com/woocommerce/woocommerce/issues/10071#issuecomment-254797719)
* New `mbifthen_format_ref` function to format the reference with spaces after each 3 number (used by the plugin but can also be used externally)
* New `multibanco_ifthen_format_ref` applied on the string to be returned from the `mbifthen_format_ref` function
* Updated filters examples

= 1.8.6 =
* Warn the store owner that if he ever changes URL he may have to ask IfthenPay to update the callback URL
* Admin notice in case this plugin is active and WooCommerce is not
* Bumped "Requires at least" tag

= 1.8.5 =
* Small change to avoid Polylang removing the payment instructions from the client emails (Thanks Tiago Restivo for the report)
* Bumped "Tested up to" tag

= 1.8.4 =
* New `multibanco_ifthen_base_ent_subent` filter to be able to change the base Entity and Subentity used to generate the payment details, based on the order, which may be useful for marketplaces
* Settings link on the plugins list
* Bumped "Tested up to" tag

= 1.8.3 =
* French translation (Thanks vinha.pt / vinha.co.uk / vinha.fr)

= 1.8.2 =
* Fix: Fatal error on WooCommerce Subscriptions admin screen if the "Only for Portuguese customers?" option was activated  (Thanks TwistedStudio)
* FAQ update

= 1.8.1 =
* Fix: The callback url sent to IfthenPay would use http:// even if ssl was active
* Bumped "Tested up to" tag

= 1.8 =
* If the order changes value on the backend, normally by adding or removing products, a new reference is created to replace the old one. The customer can be notified of the new reference if that option is checked on the plugin settings
* On orders created on the backend the reference is now created correctly, even if it's not sent to the customer email because of a WooCommerce bug (that is going to be fixed when this commit goes into production https://github.com/woothemes/woocommerce/commit/7dadae7bc80a842e10e78a972334937ed5c4416a)
* Choose either to include the payment instructions on emails sent to admin, or not
* Better feedback on the payment details info box on the backend
* Small adjustments on the settings screen, typos fixing and code improvments

= 1.7.9.1 =
* Possibility to dismiss the new “Callback” activation notice
* New warning only on the settings page, before the “Callback” activation button


= 1.7.9 =
* Warning for new users that haven't yet asked IfthenPay for the “Callback” activation
* New `multibanco_ifthen_email_instructions_payment_received` filter to customize the “Multibanco payment received” text on emails
* Bugfix: Sometimes the “Multibanco payment received” wouldn't show up on the client email
* Small settings screen fixes
* Minor spelling errors correction (Thanks @dmatos)

= 1.7.8 =
* Better reporting if it's not possible to generate the reference

= 1.7.7.1 =
* Fixed “Tested up to” field

= 1.7.7 =
* WordPress 4.4, WooCommerce 2.4.12 and PHP 7 compatibility check - All good!

= 1.7.6 =
* Changes to the settings page in order to validate Entity and Subentity input
* Bumped required WordPress version to match the same requirements WooCommerce has (4.1)

= 1.7.5.1 =
* `readme.txt` changes

= 1.7.5 =
* It's now possible to set the extra instructions text below the payment details table on the “Thank you” page and “New order” email on the plugin settings screen
* Small adjustments on the WPML detection code
* Fix: Polylang conflict (Thanks fana605)
* Updated filters examples

= 1.7.4.1 =
* Minor fixes on wrong links to set the WooCommerce currency (Thanks JLuis Freitas)

= 1.7.4 =
* Added new debug variables to the callback URL: date and time of payment and used terminal (this information will only be visible on the “Order Notes” administration panel)
* Minor spelling errors correction

= 1.7.3.1 =
* Changelog version fix

= 1.7.3 =
* Bug fixes on `filters_examples.php` on the `multibanco_ifthen_email_instructions_table_html` and `multibanco_ifthen_sms_instructions` examples (props to Jorge Fonseca)

= 1.7.2 =
* Small changes on the callback validation to better debug possible argument errors

= 1.7.1 =
* Ask IfthenPay for “Callback” activation directly from the plugin settings screen
* Settings screen fields re-organization in a more logical order
* Adjustments in the plugin description and FAQ
* Minor fixes to avoid a PHP Notice on WPML string registration

= 1.7.0.2 =
* Fixing version numbers

= 1.7.0.1 =
* Uploading missing images

= 1.7 =
* Official IfthenPay plugin status \o/
* New “SMS payment instructions” class to be able to integrate with SMS sending plugins in the future
* New `multibanco_ifthen_sms_instructions` filter to customize the SMS payment instructions
* [WooCommerce - APG SMS Notifications](https://wordpress.org/support/plugin/woocommerce-apg-sms-notifications) plugin integration: it's now possible to add the Multibanco payment details to the SMS message sent by this plugin by using the %multibanco_ifthen% variable on the message template
* Shows alternate callback URL on WordPress installations that don't have pretty permalinks active (Why? Oh why??)
* New callback test tool on the edit order screen, if WP_DEBUG is set to true
* WPML: Tries to fix the locale if WPML is active and we're loading via AJAX
* WPML: Get's the title in the correct language for the icon's alt attribute
* WPML: Shows the payment instructions on the correct language on the “Thank You” page and on Order Status and Customer Notes emails
* Now using WooCommerce's `payment_complete` function so that orders with only downloadable items go directly to completed instead of processing
* Fix: eliminates duplicate “payment received” messages on emails
* Fix: Use “new” (2.2+) WooCommerce order status when searching for orders to be set as paid via callback (shame on us)
* “Commercial information” and “Technical support” information and links on the right of the plugin settings screen
* Adjustments in the plugin description and FAQ

= 1.6.2.1 =
* Fixes a fatal error if WPML String Translation plugin is not active

= 1.6.2 =
* WPML compatibility: You can now set the English title and description at the plugin's settings screen and then go to WPML > String Translation to set the same for each language
* Fix: `get_icon()` throw a notice

= 1.6.1 =
* It's now possible to change the payment gateway icon HTML using the `woocommerce_gateway_icon` filter. See `filters_examples.php`
* Fix: Debug log path.
* Fix: `multibanco_ifthen_thankyou_instructions_table_html` filter example had an error
* Minor Portuguese translation tweaks.

= 1.6 =
* It's now possible to decide either to reduce stock when the payment is confirmed via callback (default) or when the order is placed by the client. On the first case you don't have to fix the stock if the order is never paid but you'll also not have the quantity reserved for this order. On the second case you'll have to manually fix the stock if the order is never paid.
* There's 2 filters that allow changing the payment instructions on both the “Thank you” page and on the client email. You can choose either to manipulate the default HTML or create your own. See `filters_examples.php`
* Minor Portuguese translation tweaks.

= 1.5.1 =
* Minor visual tweaks
* Fix: eliminated some notices and warnings

= 1.5 =
* It's now possible to enable this payment method only for orders below a specific amount
* Fix: No more values passed by reference, in order to avoid “deprecated” notices from PHP
* Fix: Bug on the option introduced on version 1.3

= 1.4.2 =
* Removed unused `add_meta_box` code

= 1.4.1 =
* Minor Multibanco logo improvements (Thanks Gumelo)
* Fix: Small bug when detecting multisite installs

= 1.4 =
* WordPress Multisite support

= 1.3 =
* It's now possible to enable this payment method only for orders above a specific amount

= 1.2 =
* Added the ability to receive callback logs on an email address
* Fixed “Order Status Emails for WooCommerce” plugin detection (soon to be released)
* Fixed “IfthenPay” link

= 1.1 =
* Changed plugin name and instructions to reflect the new company/gateway name “IfthenPay” instead of “Ifthen Software”
* Fix: Changed textdomain calls from a variable to a string
* Fix: Icon and banner URL now uses `plugins_url` function instead of `WP_PLUGIN_URL` constant
* “Order Status Emails for WooCommerce” plugin integration (soon to be released, or not...)

= 1.0.1 =
* Fix: On some environments some labels were not being translated correctly
* Minor changes to allow running upgrade tasks

= 1.0 =
* Initial release.