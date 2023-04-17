<?php

namespace BookitPro\Classes\Payments;

use Bookit\Classes\Database\Appointments;
use Bookit\Classes\Database\Customers;
use Bookit\Classes\Database\Staff;

class WooCommerceChild {

	private $total;
	private $payments;
	private $invoice;
	private $item_name;
	private $appointment;
	private $staff;
	private $customer;

	/**
	 * Stripe constructor.
	 *
	 * @param array $total
	 * @param $invoice
	 * @param $item_name
	 */
	public function __construct( $total, $invoice, $item_name ) {
		$this->total        = $total;
		$this->invoice      = $invoice;
		$this->item_name    = $item_name;
		$this->appointment  = Appointments::get('id', $invoice);
		$this->staff        = Staff::get('id', $this->appointment->staff_id);
		$this->customer     = Customers::get('id', $this->appointment->customer_id);
		$settings           = get_option('bookit_settings');
		$this->payments     = $settings['payments'];
	}

	public static function init() {
		add_action('woocommerce_add_order_item_meta', [self::class, 'bookit_woocommerce_add_item_meta'], 10, 3);
		add_action('woocommerce_order_status_completed', [self::class, 'bookit_woocommerce_insert_points'], 99, 2);
		add_action('woocommerce_order_status_processing', [self::class, 'bookit_woocommerce_insert_points'], 99, 2);
		add_filter('woocommerce_get_item_data', [self::class, 'bookit_woocommerce_get_item_data'], 10, 2);
		add_action('woocommerce_check_cart_items', [self::class, 'bookit_woocommerce_check_cart_items']);
		add_action('woocommerce_before_calculate_totals', [self::class, 'bookit_woocommerce_calc_total'], 90, 1);
	}

	/**
	 * @return mixed
	 * @throws \Exception
	 */
	public function generate_payment_url() {
		if ( empty($this->payments['woocommerce']) or empty($this->payments['woocommerce']['enabled']) or empty($this->payments['woocommerce']['product_id']) or !class_exists('WooCommerce') )
			wp_send_json_error( [ 'message' => __('Please check your website admin settings!', 'bookit-pro') ] );

		$this->bookit_woocommerce_add_to_cart();

		return get_permalink(get_option('woocommerce_checkout_page_id'));
	}

	/**
	 * Add to Cart
	 * @throws \Exception
	 */
	public function bookit_woocommerce_add_to_cart() {
		$meta = array();
		$time_format = get_option('time_format');
		$date_format = get_option('date_format');

		$meta['id'] = $this->appointment->id;
		$meta['customer_id']    = $this->appointment->customer_id;
		$meta['Service']        = $this->item_name;
		$meta['Staff']          = $this->staff->full_name;
		$meta['Total']          = $this->appointment->price;
		$meta['Total_price']          = $this->appointment->price;
		$meta['Date']           = bookit_datetime_i18n($date_format, $this->appointment->date_timestamp);
		$meta['Start Time']     = bookit_datetime_i18n($time_format, $this->appointment->start_time);
		$meta['End Time']       = bookit_datetime_i18n($time_format, $this->appointment->end_time);
		$meta['wc_checkout']    = [
			'billing_full_name'     => $this->customer->full_name,
			'billing_email'         => $this->customer->email,
		];

		foreach ( WC()->cart->get_cart() as $cart_key => $cart_value ) {
			if ( isset($cart_value['stm_bookit']) ) {
				WC()->cart->remove_cart_item($cart_key);
			}
		}

		WC()->cart->add_to_cart($this->payments['woocommerce']['product_id'], 1, '', array(), array('stm_bookit' => $meta));
	}

	/**
	 * @param $item_id
	 * @param $values
	 * @param $cart_item_key
	 *
	 * @throws \Exception
	 */
	public static function bookit_woocommerce_add_item_meta($item_id, $values, $cart_item_key) {
		if ( isset($values['stm_bookit']) ) {
			wc_add_order_item_meta($item_id, 'stm_bookit', $values['stm_bookit']);
		}
	}

	/**
	 * Change Appointment Status
	 * @param $cpt_id
	 *
	 * @throws \Exception
	 */
	public static function bookit_woocommerce_insert_points($cpt_id) {
		$cpt = new \WC_Order($cpt_id);

		foreach ( $cpt->get_items() as $cpt_key => $cpt_value ) {
			$item = wc_get_order_item_meta($cpt_key, 'stm_bookit');
			Appointments::change_payment_status($item['id'], 'complete');

			do_action( 'bookit_payment_complete', $item['id'] );
		}
	}

	/**
	 * @param $data
	 * @param $value
	 *
	 * @return array
	 */
	public static function bookit_woocommerce_get_item_data($data, $value) {
		if ( isset($value['stm_bookit']) ) {
			$stm_str = '';
			foreach ( $value['stm_bookit'] as $key => $value ) {
				if ( $key != 'wc_checkout' && $key != 'id' && $key != 'customer_id' )
					$stm_str .= $key . ' : ' . $value . "\n";
			}
			$data[] = ['name' => esc_html__('Appointment', 'bookit-pro'), 'value' => $stm_str];
		}
		return $data;
	}

	/**
	 * Check Cart Items
	 */
	public static function bookit_woocommerce_check_cart_items() {
		foreach ( wc()->cart->get_cart() as $key => $value ) {
			if ( isset($wc_item['stm_bookit']) ) {
				wc()->cart->set_quantity($key, 1, false);
			}
		}
	}

	/**
	 * @param $items
	 */
	public static function bookit_woocommerce_calc_total($items) {
		foreach ( $items->cart_contents as $key => $value ) {
			if ( isset($value['stm_bookit']) ) {
				$value['data']->set_price($value['stm_bookit']['Total']);
			}
		}
	}
}