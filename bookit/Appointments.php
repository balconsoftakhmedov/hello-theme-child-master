<?php

namespace Bookit\Classes\Database;

use Bookit\Classes\Database\Appointments;
use Bookit\Classes\Vendor\DatabaseModel;

class AppointmentsChild extends Appointments {

	/**
	 * Create Table
	 */
	public static function modify_table() {
		global $wpdb;
		$table_name   = Appointments::_table();
		$total_prices = [
			'clear_adult_total_price',
			'clear_child_total_price',
			'clear_basket_total_price',
			'clear_basket_cheese_total_price',
			'clear_total_price'
		];
		$vistors_qty  = [
			'adult_qty',
			'child_qty'
		];
		foreach ( $total_prices as $t_price ) {
			$column_exists = $wpdb->get_var( "SHOW COLUMNS FROM $table_name LIKE '$t_price'" );
			if ( $column_exists !== $t_price ) {
				$wpdb->query( "ALTER TABLE $table_name ADD COLUMN $t_price DECIMAL(10,2) NOT NULL DEFAULT 0.00" );
			}
		}
		foreach ( $vistors_qty as $qty ) {
			$column_exists = $wpdb->get_var( "SHOW COLUMNS FROM $table_name LIKE '$qty'" );
			if ( $column_exists !== $qty ) {
				$wpdb->query( "ALTER TABLE $table_name ADD COLUMN $qty INT UNSIGNED NOT NULL" );
			}
		}

	}

	/**
	 * Create Appointment with payment
	 */
	public static function create_appointment( $data ) {
		$appointment_data = [
			'staff_id'                => $data['staff_id'],
			'customer_id'             => $data['customer_id'],
			'service_id'              => $data['service_id'],
			'status'                  => $data['status'],
			'date_timestamp'          => $data['date_timestamp'],
			'start_time'              => $data['start_time'],
			'end_time'                => $data['end_time'],
			'price'                   => number_format( (float) $data['clear_total_price'], 2, '.', '' ),
			'notes'                   => $data['notes'],
			'created_at'              => wp_date( 'Y-m-d H:i:s' ),
			'updated_at'              => wp_date( 'Y-m-d H:i:s' ),
			'clear_adult_total_price' => number_format( (float) $data['clear_adult_total_price'], 2, '.', '' ),
			'clear_child_total_price' => number_format( (float) $data['clear_child_total_price'], 2, '.', '' ),
			'clear_total_price'       => number_format( (float) $data['clear_total_price'], 2, '.', '' ),
		];
		$qty              = [ 'adult_qty', 'child_qty' ];
		foreach ( $qty as $qt ) {
			if ( ! empty( $data[$qt ] ) ) {
				$appointment_data[$qt ] = $data[$qt ];
			}
		}
		Appointments::insert( $appointment_data );
		$appointment_id = Appointments::insert_id();
		/** create payment **/
		if ( (float) $appointment_data['price'] == 0 ) {
			$data['payment_method'] = Payments::$freeType;
			$data['payment_status'] = Payments::$completeType;
		}
		$payment_data = [
			'appointment_id' => $appointment_id,
			'type'           => ( ! empty( $data['payment_method'] ) ) ? $data['payment_method'] : Payments::$defaultType,
			'status'         => ( ! empty( $data['payment_status'] ) ) ? $data['payment_status'] : Payments::$defaultStatus,
			'total'          => $appointment_data['clear_total_price'],
			'created_at'     => wp_date( 'Y-m-d H:i:s' ),
			'updated_at'     => wp_date( 'Y-m-d H:i:s' ),
		];
		Payments::insert( $payment_data );

		return $appointment_id;
	}

	/**
	 * Update Appointment with payment
	 */
	public static function update_appointment( $data, $id ) {

		$appointment = [
			'staff_id'                => $data['staff_id'],
			'service_id'              => $data['service_id'],
			'date_timestamp'          => $data['date_timestamp'],
			'start_time'              => $data['start_time'],
			'end_time'                => $data['end_time'],
			'price'                   => number_format( (float) $data['price'], 2, '.', '' ),
			'status'                  => $data['status'],
			'notes'                   => $data['notes'],
			'created_from'            => $data['created_from'],
			'updated_at'              => wp_date( 'Y-m-d H:i:s' ),
			'clear_adult_total_price' => number_format( (float) $data['clear_adult_total_price'], 2, '.', '' ),
			'clear_child_total_price' => number_format( (float) $data['clear_child_total_price'], 2, '.', '' ),
			'clear_total_price'       => number_format( (float) $data['clear_total_price'], 2, '.', '' ),
			'adult_qty'               => $data['adult_qty'],
			'child_qty'               => $data['child_qty'],
		];
		self::update( $appointment, [ 'id' => $id ] );
		/** update payment **/
		if ( $data['payment_method'] == Payments::$freeType ) {
			$data['payment_status'] = Payments::$completeType;
		}
		$payment_data = [
			'type'       => $data['payment_method'],
			'status'     => $data['payment_status'],
			'total'      => $appointment['clear_total_price'],
			'updated_at' => wp_date( 'Y-m-d H:i:s' ),
		];
		Payments::update( $payment_data, [ 'appointment_id' => $id ] );
	}

}

add_action( 'init', function () {
	\Bookit\Classes\Database\AppointmentsChild::modify_table();
} );