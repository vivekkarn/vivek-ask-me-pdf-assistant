<?php
/**
 * RAG chat orchestration.
 *
 * @package AskMeAI\Services
 */

namespace AskMeAI\Services;

use AskMeAI\Core\Database;
use AskMeAI\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Chat_Service {
	/**
	 * Answer a user question from retrieved PDF chunks.
	 *
	 * @param string $question User question.
	 * @param string $session_id Session ID.
	 * @return array|\WP_Error
	 */
	public function answer( $question, $session_id = '' ) {
		$question = trim( wp_strip_all_tags( $question ) );
		if ( strlen( $question ) < 2 ) {
			return new \WP_Error( 'invalid_question', __( 'Please enter a question.', 'lumen-assistant' ) );
		}

		$settings = Settings::get();
		$chunks   = ( new Retriever() )->search( $question, absint( $settings['max_context_chunks'] ) );

		if ( is_wp_error( $chunks ) ) {
			return $chunks;
		}

		if ( empty( $chunks ) ) {
			return array(
				'answer'  => __( 'I do not know based on the available documents.', 'lumen-assistant' ),
				'sources' => array(),
			);
		}

		$context = $this->format_context( $chunks );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $settings['assistant_instructions'] . "\n\nRules:\n- Use only the context below.\n- Do not use outside knowledge.\n- If the context does not contain the answer, say: \"I do not know based on the available documents.\"\n- Include brief source references using the provided source labels.",
			),
			array(
				'role'    => 'user',
				'content' => "Context:\n{$context}\n\nQuestion: {$question}",
			),
		);

		$answer = ( new OpenRouter_Client() )->chat( $messages, $settings['chat_model'], (float) $settings['temperature'] );
		if ( is_wp_error( $answer ) ) {
			return $answer;
		}

		$sources = $this->sources( $chunks );
		$this->log_chat( $session_id, $question, $answer, $sources );

		return array(
			'answer'  => $answer,
			'sources' => $sources,
		);
	}

	/**
	 * Format retrieved context.
	 *
	 * @param array $chunks Retrieved chunks.
	 * @return string
	 */
	private function format_context( $chunks ) {
		$parts = array();

		foreach ( $chunks as $index => $chunk ) {
			$label   = 'Source ' . ( $index + 1 ) . ': ' . $chunk['filename'];
			$page    = ! empty( $chunk['page_number'] ) ? ', page ' . absint( $chunk['page_number'] ) : '';
			$parts[] = $label . $page . "\n" . trim( $chunk['chunk_text'] );
		}

		return implode( "\n\n---\n\n", $parts );
	}

	/**
	 * Public source metadata.
	 *
	 * @param array $chunks Retrieved chunks.
	 * @return array
	 */
	private function sources( $chunks ) {
		$sources = array();

		foreach ( $chunks as $chunk ) {
			$sources[] = array(
				'filename' => sanitize_text_field( $chunk['filename'] ),
				'page'     => absint( $chunk['page_number'] ),
				'snippet'  => sanitize_text_field( $chunk['snippet'] ),
				'score'    => round( (float) $chunk['score'], 4 ),
			);
		}

		return $sources;
	}

	/**
	 * Store optional chat log.
	 *
	 * @param string $session_id Session ID.
	 * @param string $question Question.
	 * @param string $answer Answer.
	 * @param array  $sources Source metadata.
	 */
	private function log_chat( $session_id, $question, $answer, $sources ) {
		global $wpdb;

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$wpdb->insert(
			Database::table( 'chat_logs' ),
			array(
				'session_id' => sanitize_text_field( $session_id ),
				'ip_hash'    => hash( 'sha256', $ip . wp_salt( 'nonce' ) ),
				'question'   => sanitize_textarea_field( $question ),
				'answer'     => wp_kses_post( $answer ),
				'sources'    => wp_json_encode( $sources ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
