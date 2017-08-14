<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order Class.
 *
 * These are our Orders, which extend the regular WooCommerce Orders,in order to abstract properties access after the 3.0 changes
 *
 */
class WC_Order_MB_Ifthen extends WC_Order {

	/**
	 * Returns the unique ID for this order.
	 * @return int
	 */
	public function mb_get_id() {
		return version_compare( WC_VERSION, '3.0', '>=' ) ? $this->get_id() : $this->id;
	}

	/**
	 * Returns the order payment method
	 * @return string
	 */
	public function mb_get_payment_method() {
		return version_compare( WC_VERSION, '3.0', '>=' ) ? $this->get_payment_method() : $this->payment_method;
	}

	/**
	 * Returns the order total
	 * @return float
	 */
	public function mb_get_total() {
		return version_compare( WC_VERSION, '3.0', '>=' ) ? $this->get_total() : $this->order_total;
	}

	/**
	 * Returns the order status
	 * @return string
	 */
	public function mb_get_status() {
		return version_compare( WC_VERSION, '3.0', '>=' ) ? $this->get_status() : $this->status;
	}

	/**
	 * Checks the order status against a passed in status
	 * @return bool
	 */
	public function mb_has_status( $status ) {
		return version_compare( WC_VERSION, '2.2', '>=' )
				?
					( $this->has_status( $status ) )
				:
					apply_filters( 'woocommerce_order_has_status', ( is_array( $status ) && in_array( $this->get_status(), $status ) ) || $this->get_status() === $status ? true : false, $this, $status );
	}

	/**
	 * Returns the order WPML Language
	 * @return string
	 */
	public function mb_get_wpml_language() {
		return $this->get_meta('wpml_language');
	}

	/**
	 * Gets order meta
	 */
	public function mb_get_meta( $key ) {
		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			return $this->get_meta($key);
		} else {
			return get_post_meta($this->mb_get_id(), $key, true);
		}
	}

	/**
	 * Sets order meta
	 */
	public function mb_update_meta_data( $key, $value ) {
		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			$this->update_meta_data( $key, $value );
			$this->save();
		} else {
			update_post_meta( $this->mb_get_id(), $key, $value );
		}
	}

	/**
	 * Reduce order stock
	 */
	public function mb_reduce_order_stock() {
		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			wc_reduce_stock_levels( $this->get_id() );
		} else {
			$this->reduce_order_stock();
		}
	}


}