<?php
/**
 * Database schema and helpers.
 *
 * @package AskMeAI\Core
 */

namespace AskMeAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Database {
	/**
	 * Get table name.
	 *
	 * @param string $table Logical table suffix.
	 * @return string
	 */
	public static function table( $table ) {
		global $wpdb;
		return $wpdb->prefix . 'ask_me_ai_' . $table;
	}

	/**
	 * Create plugin tables.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$documents = self::table( 'documents' );
		$chunks    = self::table( 'chunks' );
		$embeds    = self::table( 'embeddings' );
		$logs      = self::table( 'chat_logs' );

		dbDelta(
			"CREATE TABLE {$documents} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				filename VARCHAR(255) NOT NULL,
				file_path TEXT NOT NULL,
				file_hash VARCHAR(64) NOT NULL,
				status VARCHAR(40) NOT NULL DEFAULT 'uploaded',
				chunk_count INT UNSIGNED NOT NULL DEFAULT 0,
				error_message TEXT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY status (status),
				KEY file_hash (file_hash)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$chunks} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				document_id BIGINT UNSIGNED NOT NULL,
				chunk_index INT UNSIGNED NOT NULL,
				page_number INT UNSIGNED NULL,
				chunk_text LONGTEXT NOT NULL,
				token_estimate INT UNSIGNED NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY document_id (document_id),
				KEY page_number (page_number)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$embeds} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				chunk_id BIGINT UNSIGNED NOT NULL,
				model VARCHAR(255) NOT NULL,
				vector LONGTEXT NOT NULL,
				dimensions INT UNSIGNED NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY chunk_model (chunk_id, model(191)),
				KEY chunk_id (chunk_id)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$logs} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				session_id VARCHAR(80) NOT NULL,
				ip_hash VARCHAR(64) NOT NULL,
				question TEXT NOT NULL,
				answer LONGTEXT NOT NULL,
				sources LONGTEXT NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY session_id (session_id),
				KEY ip_hash (ip_hash),
				KEY created_at (created_at)
			) {$charset};"
		);

		update_option( 'ask_me_ai_db_version', ASK_ME_AI_VERSION, false );
	}
}
