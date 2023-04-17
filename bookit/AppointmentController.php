<?php

namespace Bookit\Classes;

use Bookit\Classes\Admin\SettingsController;
use Bookit\Classes\AppointmentController;
use Bookit\Classes\Base\Plugin;
use Bookit\Classes\Database\Appointments;
use Bookit\Classes\Database\AppointmentsChild;
use Bookit\Classes\Vendor\Payments;
use Bookit\Helpers\CleanHelper;

class AppointmentControllerChild extends AppointmentController {

	private static function getCleanRules() {

		return [
			'clear_price'             => [ 'type' => 'floatval' ],
			'user_id'                 => [ 'type' => 'intval' ],
			'staff_id'                => [ 'type' => 'intval' ],
			'service_id'              => [ 'type' => 'intval' ],
			'start_timestamp'         => [ 'type' => 'intval' ],
			'end_timestamp'           => [ 'type' => 'intval' ],
			'now_timestamp'           => [ 'type' => 'intval' ],
			'today_timestamp'         => [ 'type' => 'intval' ],
			'email'                   => [ 'type' => 'strval', 'function' => [ 'custom' => false, 'name' => 'sanitize_email' ] ],
			'payment_method'          => [ 'type' => 'strval' ],
			'full_name'               => [ 'type' => 'strval' ],
			'phone'                   => [ 'function' => [ 'custom' => true, 'name' => 'custom_sanitize_phone' ] ],
			'clear_adult_total_price' => [ 'type' => 'floatval' ],
			'clear_child_total_price' => [ 'type' => 'floatval' ],
			'clear_total_price'       => [ 'type' => 'floatval' ],
			'adult_qty'               => [ 'type' => 'intval' ],
			'child_qty'               => [ 'type' => 'intval' ],
		];
	}

	/**
	 * Validation
	 *
	 * @param $data
	 */
	public static function validate( $data ) {
		$errors      = [];
		$settings    = SettingsController::get_settings();
		$appointment = Appointments::checkAppointment( $data );
		if ( $appointment > 0 ) {
			$errors['appointment'] = __( 'Selected Service Time is not available!', 'bookit' );
		}
		if ( $data['phone'] || $data['phone'] === false ) {
			if ( ! preg_match( '/^((\+)?[0-9]{8,14})$/', $data['phone'] ) ) {
				$errors['phone'] = __( 'Please enter a valid phone number' );
			}
		}
		if ( $settings['booking_type'] == 'guest' ) {
			if ( ! $data['email'] && ! $data['phone'] ) {
				$errors['phone'] = __( "Please enter email or phone" );
			}
		}
		if ( ! $data['email'] ) {
			$errors['email'] = __( "Please enter email" );
		}
		if ( $data['email'] && ! is_email( $data['email'] ) ) {
			$errors['email'] = __( 'Please enter your email in format youremail@example.com' );
		}
		if ( $data['full_name'] ) {
			if ( strlen( $data['full_name'] ) < 3 || strlen( $data['full_name'] ) > 25 ) {
				$errors['full_name'] = __( 'Full name must be between 3 and 25 characters long' );
			}
		} else {
			$errors['full_name'] = __( 'Please enter full name' );
		}
		if ( ! $data['user_id'] && $settings['booking_type'] == 'registered' ) {

			if ( empty( $data['password'] ) ) {
				$errors['password'] = __( 'Please enter a password' );
			}
			if ( false !== strpos( wp_unslash( $data['password'] ), '\\' ) ) {
				$errors['password'] = __( "Passwords may not contain the character '\\'" );
			}
			if ( ( ! empty( $data['password'] ) ) && $data['password'] != $data['password_confirmation'] ) {
				$errors['password_confirmation'] = __( "Please enter the same password in both password fields" );
			}
		}
		if ( (float) $data['clear_price'] > 0 && ! array_key_exists( $data['payment_method'], $settings['payments'] ) ) {
			//$errors['payment_method'] = __( 'Please choose correct payment method ddd' );
		}
		if ( count( $errors ) > 0 ) {
			wp_send_json_error( [ 'errors' => $errors ] );
		}
	}

	/**
	 * Book Appointment
	 */
	public static function save() {

		$send_no_cache_headers = apply_filters( 'rest_send_nocache_headers', is_user_logged_in() );
		if ( ! $send_no_cache_headers ) {
			$nonce                      = wp_create_nonce( 'bookit_nonce' );
			$_SERVER['HTTP_X_WP_NONCE'] = $nonce;
		}
		check_ajax_referer( 'bookit_book_appointment', 'nonce' );
		$data = CleanHelper::cleanData( $_POST, self::getCleanRules() );
		self::validate( $data );
		if ( ! empty( $data ) ) {

			$customer = CustomerController::get_customer( $data );
			$notes    = [];
			if ( ! empty( $data['comment'] ) ) {
				$notes['comment'] = $data['comment'];
			}
			$custom_data      = [
				'adult_total_price' => esc_html__('Adult Total Price', 'hello-elementor-child'),
				'child_total_price'=> esc_html__('Child Total Price', 'hello-elementor-child'),
				'total_price'=> esc_html__('Total Price', 'hello-elementor-child'),
				'adult_qty' => esc_html__('Adults Numbers', 'hello-elementor-child'),
				'child_qty' => esc_html__('Children Numbers', 'hello-elementor-child'),
			];

			foreach ($custom_data as $cd=>$cl){
				if (!empty($data[$cd] )) $notes['comment'] .= "\r\n{$cl} - {$data[$cd]}, ";
			}

			if ( $customer->email != $data['email'] ) {
				$notes['email'] = $data['email'];
			}
			if ( $customer->phone != $data['phone'] && ! empty( $data['phone'] ) ) {
				$notes['phone'] = $data['phone'];
			}
			if ( $customer->full_name != $data['full_name'] && ! empty( $data['full_name'] ) ) {
				$notes['full_name'] = $data['full_name'];
			}
			$data['customer_id'] = $customer->id;
			$data['notes']       = serialize( $notes );
			$data['status']      = Appointments::$pending;
			$id = AppointmentsChild::create_appointment( $data );
			do_action( 'bookit_appointment_created', $id );
			$appointment          = (array) Appointments::get_full_appointment_by_id( $id );
			$appointment['token'] = $data['token'];
			/** if google calendar addon is installed */
			if ( Plugin::isAddonInstalledAndEnabled( self::$googleCalendarAddon ) && has_action( 'bookit_google_calendar_create_appointment' ) ) {
				$appointment['customer_email'] = $notes['email'] ?? $appointment['customer_email'];
				$appointment['customer_phone'] = $notes['phone'] ?? $appointment['customer_phone'];
				do_action( 'bookit_google_calendar_create_appointment', $appointment );
			}
			/** if google calendar addon is installed | end */
			$redirect_url = '';
			if ( ! is_null( $appointment['payment_method'] ) ) {
				$payments     = new Payments( $appointment );
				$redirect_url = $payments->redirect_url();
			}
			wp_send_json_success( [ 'appointment' => $appointment, 'customer' => $customer, 'nonce' => $nonce, 'redirect_url' => $redirect_url, 'message' => __( 'Appointment Saved!', 'bookit' ) ] );
		}
		wp_send_json_error( [ 'message' => __( 'Error occurred!', 'bookit' ) ] );
	}


}
