<?php
/**
 * PDF upload and indexing service.
 *
 * @package AskMeAI\Services
 */

namespace AskMeAI\Services;

use AskMeAI\Core\Database;
use AskMeAI\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Document_Indexer {
	/**
	 * Upload and index a PDF.
	 *
	 * @param array $file Uploaded file array.
	 * @return int|\WP_Error Document ID.
	 */
	public function upload_and_index( $file ) {
		if ( empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
			return new \WP_Error( 'missing_file', __( 'No PDF file was uploaded.', 'ask-me-ai' ) );
		}

		$type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		if ( 'pdf' !== ( $type['ext'] ?? '' ) || 'application/pdf' !== ( $type['type'] ?? '' ) ) {
			return new \WP_Error( 'invalid_file_type', __( 'Please upload a valid PDF file.', 'ask-me-ai' ) );
		}

		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . 'ask-me-ai-docs';

		if ( ! wp_mkdir_p( $target_dir ) ) {
			return new \WP_Error( 'upload_dir_failed', __( 'Could not create the document upload directory.', 'ask-me-ai' ) );
		}

		$this->protect_upload_dir( $target_dir );

		$filename = sanitize_file_name( $file['name'] );
		$target   = trailingslashit( $target_dir ) . wp_unique_filename( $target_dir, $filename );

		if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
			return new \WP_Error( 'upload_failed', __( 'Could not save the uploaded PDF.', 'ask-me-ai' ) );
		}

		return $this->create_document_and_index( $filename, $target );
	}

	/**
	 * Create document record then index.
	 *
	 * @param string $filename Source filename.
	 * @param string $file_path Stored path.
	 * @return int|\WP_Error
	 */
	private function create_document_and_index( $filename, $file_path ) {
		global $wpdb;

		$now  = current_time( 'mysql' );
		$hash = hash_file( 'sha256', $file_path );

		$inserted = $wpdb->insert(
			Database::table( 'documents' ),
			array(
				'filename'    => $filename,
				'file_path'   => $file_path,
				'file_hash'   => $hash,
				'status'      => 'uploaded',
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new \WP_Error( 'document_insert_failed', __( 'Could not create document record.', 'ask-me-ai' ) );
		}

		$document_id = (int) $wpdb->insert_id;
		$result      = $this->index_document( $document_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $document_id;
	}

	/**
	 * Rebuild a document index.
	 *
	 * @param int $document_id Document ID.
	 * @return true|\WP_Error
	 */
	public function index_document( $document_id ) {
		global $wpdb;

		$document = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Database::table( 'documents' ) . ' WHERE id = %d', $document_id ),
			ARRAY_A
		);

		if ( ! $document ) {
			return new \WP_Error( 'document_not_found', __( 'Document not found.', 'ask-me-ai' ) );
		}

		$this->set_status( $document_id, 'indexing', '' );
		$this->delete_document_chunks( $document_id );

		$pages = ( new Pdf_Text_Extractor() )->extract( $document['file_path'] );
		if ( empty( $pages ) ) {
			$this->set_status( $document_id, 'failed', __( 'No text could be extracted from this PDF.', 'ask-me-ai' ) );
			return new \WP_Error( 'no_pdf_text', __( 'No text could be extracted from this PDF.', 'ask-me-ai' ) );
		}

		$chunks = ( new Text_Chunker() )->chunk_pages( $pages );
		if ( empty( $chunks ) ) {
			$this->set_status( $document_id, 'failed', __( 'No searchable text chunks were created.', 'ask-me-ai' ) );
			return new \WP_Error( 'no_chunks', __( 'No searchable text chunks were created.', 'ask-me-ai' ) );
		}

		$client          = new OpenRouter_Client();
		$embedding_model = Settings::get_value( 'embedding_model', '' );
		$chunk_count     = 0;

		foreach ( $chunks as $index => $chunk ) {
			$chunk_text = $chunk['text'];
			$vector     = $client->embed( $chunk_text );

			if ( is_wp_error( $vector ) ) {
				$this->set_status( $document_id, 'failed', $vector->get_error_message() );
				return $vector;
			}

			$wpdb->insert(
				Database::table( 'chunks' ),
				array(
					'document_id'    => $document_id,
					'chunk_index'    => $index,
					'page_number'    => $chunk['page'],
					'chunk_text'     => $chunk_text,
					'token_estimate' => (int) ceil( strlen( $chunk_text ) / 4 ),
					'created_at'     => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%d', '%s', '%d', '%s' )
			);

			$chunk_id = (int) $wpdb->insert_id;

			$wpdb->insert(
				Database::table( 'embeddings' ),
				array(
					'chunk_id'    => $chunk_id,
					'model'       => $embedding_model,
					'vector'      => wp_json_encode( $vector ),
					'dimensions'  => count( $vector ),
					'created_at'  => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%d', '%s' )
			);

			++$chunk_count;
		}

		$wpdb->update(
			Database::table( 'documents' ),
			array(
				'status'        => 'ready',
				'chunk_count'   => $chunk_count,
				'error_message' => '',
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $document_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Delete document and associated data.
	 *
	 * @param int $document_id Document ID.
	 * @return true|\WP_Error
	 */
	public function delete_document( $document_id ) {
		global $wpdb;

		$document = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . Database::table( 'documents' ) . ' WHERE id = %d', $document_id ),
			ARRAY_A
		);

		if ( ! $document ) {
			return new \WP_Error( 'document_not_found', __( 'Document not found.', 'ask-me-ai' ) );
		}

		$this->delete_document_chunks( $document_id );
		$wpdb->delete( Database::table( 'documents' ), array( 'id' => $document_id ), array( '%d' ) );

		if ( ! empty( $document['file_path'] ) && file_exists( $document['file_path'] ) ) {
			wp_delete_file( $document['file_path'] );
		}

		return true;
	}

	/**
	 * Delete chunks and embeddings for document.
	 *
	 * @param int $document_id Document ID.
	 */
	private function delete_document_chunks( $document_id ) {
		global $wpdb;

		$chunk_ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT id FROM ' . Database::table( 'chunks' ) . ' WHERE document_id = %d', $document_id )
		);

		if ( ! empty( $chunk_ids ) ) {
			$ids = implode( ',', array_map( 'absint', $chunk_ids ) );
			$wpdb->query( "DELETE FROM " . Database::table( 'embeddings' ) . " WHERE chunk_id IN ({$ids})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$wpdb->delete( Database::table( 'chunks' ), array( 'document_id' => $document_id ), array( '%d' ) );
	}

	/**
	 * Set document status.
	 *
	 * @param int    $document_id Document ID.
	 * @param string $status Status.
	 * @param string $error Error message.
	 */
	private function set_status( $document_id, $status, $error ) {
		global $wpdb;

		$wpdb->update(
			Database::table( 'documents' ),
			array(
				'status'        => sanitize_key( $status ),
				'error_message' => sanitize_textarea_field( $error ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $document_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Add lightweight directory protection files.
	 *
	 * @param string $target_dir Upload directory.
	 */
	private function protect_upload_dir( $target_dir ) {
		$index = trailingslashit( $target_dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$htaccess = trailingslashit( $target_dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
}
