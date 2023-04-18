<?php

namespace Bookit\Classes\Admin;

use Bookit\Classes\Base\Plugin;
use Bookit\Classes\Base\User;
use Bookit\Classes\Database\Appointments;
use Bookit\Classes\Database\Customers;
use Bookit\Classes\Database\Services;
use Bookit\Classes\Database\Staff;
use Bookit\Classes\Database\Staff_Services;
use Bookit\Classes\Database\Staff_Working_Hours;
use Bookit\Classes\Template;
use Bookit\Helpers\CleanHelper;
use Bookit\Classes\Admin\StaffController;

class StaffControllerChild extends StaffController {
	public static function save_child() {
		check_ajax_referer( 'bookit_save_item', 'nonce' );
		if ( ! current_user_can( 'manage_bookit_staff' ) ) {
			return false;
		}
		$data = CleanHelper::cleanData( $_POST, self::getCleanRules() );
		self::validate( $data );
		$id = ( ! empty( $data['id'] ) ) ? $data['id'] : null;
		/** if this is staff can edit just self data */
		$bookitUser = self::bookitUser();
		if ( $bookitUser['is_staff'] == true && ( $id == null || ( (int) $bookitUser['staff'][0]['id'] != (int) $id ) ) ) {
			return false;
		}
		if ( empty( $data ) ) {
			wp_send_json_error( [ 'message' => __( 'Error occurred!', 'bookit' ) ] );

			return false;
		}
		$staff_services = json_decode( stripslashes( $data['staff_services'] ) );
		$working_hours  = json_decode( stripslashes( $data['working_hours'] ) );
		unset( $data['staff_services'] );
		unset( $data['working_hours'] );
		unset( $data['gc_token'] );
		if ( $id ) {
			Staff::update( $data, [ 'id' => $id ] );
			Staff_Services::delete_where( 'staff_id', $id );
			foreach ( $working_hours as $working_hour ) {
				$update = [
					'id'         => $working_hour->id,
					'staff_id'   => $id,
					'weekday'    => $working_hour->weekday,
					'start_time' => $working_hour->start_time,
					'end_time'   => $working_hour->end_time,
					'break_from' => $working_hour->break_from,
					'break_to'   => $working_hour->break_to
				];
				Staff_Working_Hours::update( $update, [ 'id' => $update['id'] ] );
			}
		} else {
			Staff::insert( $data );
			$id = Staff::insert_id();
			foreach ( $working_hours as $working_hour ) {
				$insert = [
					'staff_id'   => $id,
					'weekday'    => $working_hour->weekday,
					'start_time' => $working_hour->start_time,
					'end_time'   => $working_hour->end_time,
					'break_from' => $working_hour->break_from,
					'break_to'   => $working_hour->break_to
				];
				Staff_Working_Hours::insert( $insert );
			}
		}
		foreach ( $staff_services as $staff_service ) {
			$insert = [
				'staff_id'            => $id,
				'service_id'          => $staff_service->id,
				'price'               => number_format( (float) $staff_service->price, 2, '.', '' ),
				'child_price'         => number_format( (float) $staff_service->child_price, 2, '.', '' ),
				'basket_price'        => number_format( (float) $staff_service->basket_price, 2, '.', '' ),
				'basket_cheese_price' => number_format( (float) $staff_service->basket_cheese_price, 2, '.', '' ),
			];
			Staff_Services::insert( $insert );
		}
		/** set bookit staff role if wordpress user connected */
		if ( $data['wp_user_id'] ) {
			$wpUser = get_user_by( 'ID', $data['wp_user_id'] );
			$wpUser->set_role( User::$staff_role );
		}
		/** if google calendar addon is installed */
		if ( Plugin::isAddonInstalledAndEnabled( self::$googleCalendarAddon ) && has_filter( 'bookit_filter_connect_employee_google_calendar' ) ) {
			$staff = (array) Staff::get( 'id', $id );
			$staff = apply_filters( 'bookit_filter_connect_employee_google_calendar', $staff );
		}
		/** if google calendar addon is installed | end */
		do_action( 'bookit_staff_saved', $id );
		wp_send_json_success( [ 'id' => $id, 'staff' => $staff, 'service' => $insert, 'message' => __( 'Staff Saved!', 'bookit' ) ] );

	}

	private static function getCleanRules() {
		return [
			'booking_type'                      => [ 'type' => 'strval' ],
			'theme'                             => [ 'type' => 'strval' ],
			'sender_name'                       => [ 'type' => 'strval' ],
			'sender_email'                      => [ 'type' => 'strval' ],
			'hide_header_titles'                => [ 'type' => 'strval' ],
			'currency_symbol'                   => [ 'type' => 'strval' ],
			'currency_position'                 => [ 'type' => 'strval' ],
			'thousands_separator'               => [ 'type' => 'strval' ],
			'decimals_separator'                => [ 'type' => 'strval' ],
			'decimals_number'                   => [ 'type' => 'intval' ],
			'custom_colors_enabled'             => [ 'type' => 'strval' ],
			'hide_from_for_equal_service_price' => [ 'type' => 'strval' ],
			'currency'                          => [ 'function' => [ 'custom' => false, 'name' => 'strtolower' ] ],
			'payments'                          => [ 'function' => [ 'custom' => true, 'name' => 'custom_sanitize_json' ] ],
			'emails'                            => [ 'function' => [ 'custom' => true, 'name' => 'custom_sanitize_json' ] ],
			'custom_colors'                     => [ 'function' => [ 'custom' => true, 'name' => 'custom_sanitize_json' ] ],
		];
	}

}