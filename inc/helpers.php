<?php
add_filter( 'auto_update_plugin', '__return_false' );

function disable_plugin_updates( $value ) {
	if ( isset( $value ) && is_object( $value ) ) {
		if ( isset( $value->response['bookit/bookit.php'] ) ) {
			unset( $value->response['bookit/bookit.php'] );
		}
	}

	return $value;
}

add_filter( 'site_transient_update_plugins', 'disable_plugin_updates' );
include_once get_stylesheet_directory()."/bookit/ajax.php";
include_once get_stylesheet_directory()."/bookit/StaffController.php";

function add_child_price_column_to_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'bookit_staff_services'; // replace 'table_name' with your actual table name

	// Check if the column already exists
	$column_exists = $wpdb->get_var( "SHOW COLUMNS FROM $table_name LIKE 'child_price'" );

	// If the column does not exist, add it
	if ( $column_exists !== 'child_price' ) {
		$wpdb->query( "ALTER TABLE $table_name ADD COLUMN child_price DECIMAL(10,2) NOT NULL DEFAULT 0.00" );
	}
}

add_child_price_column_to_table();