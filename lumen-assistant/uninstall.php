<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package AskMeAI
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'ask_me_ai_chat_logs',
	$wpdb->prefix . 'ask_me_ai_embeddings',
	$wpdb->prefix . 'ask_me_ai_chunks',
	$wpdb->prefix . 'ask_me_ai_documents',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

delete_option( 'ask_me_ai_settings' );
delete_option( 'ask_me_ai_db_version' );

$upload_dir = wp_upload_dir();
$target_dir = trailingslashit( $upload_dir['basedir'] ) . 'lumen-assistant-docs';

if ( is_dir( $target_dir ) ) {
	$files = glob( trailingslashit( $target_dir ) . '*' );
	if ( is_array( $files ) ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}
	@rmdir( $target_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}
