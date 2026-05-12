<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package AskMeAI
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Custom plugin tables are intentionally removed during uninstall.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

( function () {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . 'ask_me_ai_chat_logs',
		$wpdb->prefix . 'ask_me_ai_embeddings',
		$wpdb->prefix . 'ask_me_ai_chunks',
		$wpdb->prefix . 'ask_me_ai_documents',
	);

	foreach ( $tables as $table ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	delete_option( 'ask_me_ai_settings' );
	delete_option( 'ask_me_ai_db_version' );

	$upload_dir = wp_upload_dir();
	$target_dir = trailingslashit( $upload_dir['basedir'] ) . 'vivek-ask-me-pdf-assistant-docs';

	if ( is_dir( $target_dir ) ) {
		$files = glob( trailingslashit( $target_dir ) . '*' );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
		}
	}
} )();
