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

global $wpdb;

$lumen_assistant_tables = array(
	$wpdb->prefix . 'ask_me_ai_chat_logs',
	$wpdb->prefix . 'ask_me_ai_embeddings',
	$wpdb->prefix . 'ask_me_ai_chunks',
	$wpdb->prefix . 'ask_me_ai_documents',
);

foreach ( $lumen_assistant_tables as $lumen_assistant_table ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $lumen_assistant_table ) );
}

delete_option( 'ask_me_ai_settings' );
delete_option( 'ask_me_ai_db_version' );

$lumen_assistant_upload_dir = wp_upload_dir();
$lumen_assistant_target_dir = trailingslashit( $lumen_assistant_upload_dir['basedir'] ) . 'lumen-assistant-docs';

if ( is_dir( $lumen_assistant_target_dir ) ) {
	$lumen_assistant_files = glob( trailingslashit( $lumen_assistant_target_dir ) . '*' );
	if ( is_array( $lumen_assistant_files ) ) {
		foreach ( $lumen_assistant_files as $lumen_assistant_file ) {
			if ( is_file( $lumen_assistant_file ) ) {
				wp_delete_file( $lumen_assistant_file );
			}
		}
	}
}
