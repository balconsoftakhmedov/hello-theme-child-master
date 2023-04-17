<?php

namespace Bookit\Classes;

use Bookit\Classes\AjaxActions;
use Bookit\Classes\Admin\StaffControllerChild;

class AjaxChildActions extends AjaxActions {

	public static function addAction1( $tag, $function_to_add, $nopriv = false, $priority = 10, $accepted_args = 1 ) {
		add_action( 'wp_ajax_' . $tag, $function_to_add, $priority = 10, $accepted_args = 1 );
		if ( $nopriv ) {
			add_action( 'wp_ajax_nopriv_' . $tag, $function_to_add );
		}

		return true;
	}

	/**
	 * Init Ajax Actions
	 */
	public static function init() {
self::addAction('bookit_book_appointment_child', [AppointmentControllerChild::class, 'save'], true);
		remove_all_actions('wp_ajax_bookit_save_staff');
		remove_all_actions('wp_ajax_nopriv_bookit_save_staff');
		if ( is_admin() ) {

			self::addAction1( 'bookit_save_staff', [ StaffControllerChild ::class, 'save_child' ], false, 10, 1 );
		}
	}
}

add_action( 'init', function () {
	\Bookit\Classes\AjaxChildActions::init();
} );