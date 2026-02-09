<?php
/**
 * WP-Autoplugin Uninstall
 *
 * Fired when the plugin is uninstalled (deleted from the WordPress admin).
 * Cleans up all plugin data from the database.
 *
 * @package WP-Autoplugin
 * @since 2.0.0
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
$options_to_delete = [
	'wp_autoplugin_api_provider',
	'wp_autoplugin_model',
	'wp_autoplugin_enable_visual_feedback',
	'wp_autoplugin_enable_notifications',
	'wp_autoplugin_log_level',
	'wp_autoplugin_max_retries',
	'wp_autoplugin_timeout',
	'wp_autoplugin_stream_responses',
	'wp_autoplugin_openai_api_key',
	'wp_autoplugin_anthropic_api_key',
	'wp_autoplugin_google_api_key',
	'wp_autoplugin_xai_api_key',
	'wp_autoplugin_custom_models',
	'wp_autoplugin_features',
	'wp_autoplugins',
	'wp_autoplugin_fatal_error',
	'wp_autoplugin_email_critical_errors',
];

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// Remove transients.
delete_transient( 'wp_autoplugin_admin_notice' );
delete_transient( 'wp_autoplugin_errors' );

// Remove custom database tables.
global $wpdb;
$table_name = $wpdb->prefix . 'autoplugin_operations';
// Validate table name contains only safe characters before using in query.
if ( preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );
}

// Remove log files directory.
$log_dir = WP_CONTENT_DIR . '/wp-autoplugin-logs/';
if ( is_dir( $log_dir ) ) {
	$files = glob( $log_dir . '*' );
	if ( is_array( $files ) ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}
	rmdir( $log_dir );
}

// Remove custom capabilities from administrator role.
$role = get_role( 'administrator' );
if ( $role ) {
	$role->remove_cap( 'wp_autoplugin_generate' );
	$role->remove_cap( 'wp_autoplugin_fix' );
	$role->remove_cap( 'wp_autoplugin_extend' );
	$role->remove_cap( 'wp_autoplugin_explain' );
	$role->remove_cap( 'wp_autoplugin_settings' );
}

// Clear any scheduled hooks.
wp_clear_scheduled_hook( 'wp_autoplugin_cleanup' );
