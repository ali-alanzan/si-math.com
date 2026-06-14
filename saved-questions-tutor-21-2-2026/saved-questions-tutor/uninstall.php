<?php
/**
 * Uninstall script for Saved Questions Tutor
 *
 * This file is executed when the plugin is uninstalled.
 *
 * @package Saved_Questions_Tutor
 */

// Exit if uninstall not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check user capabilities
if ( ! current_user_can( 'activate_plugins' ) ) {
	return;
}

// Check if user wants to delete data
$delete_data = get_option( 'sqt_delete_data_on_uninstall', false );

if ( ! $delete_data ) {
	return;
}

global $wpdb;

// Delete user meta
$wpdb->query(
	"DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'saved_questions'"
);

// Delete custom table if exists
$table_name = $wpdb->prefix . 'saved_questions';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Delete plugin options
delete_option( 'sqt_use_custom_table' );
delete_option( 'sqt_delete_data_on_uninstall' );

// Clear any transients
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_sqt_' ) . '%'
	)
);

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_timeout_sqt_' ) . '%'
	)
);

