<?php
/**
 * Vector retrieval service.
 *
 * @package AskMeAI\Services
 */

namespace AskMeAI\Services;

use AskMeAI\Core\Database;
use AskMeAI\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Custom vector tables are queried directly for local retrieval.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

class Retriever {
	/**
	 * Search relevant chunks for a question.
	 *
	 * @param string $question User question.
	 * @param int    $limit Max results.
	 * @return array|\WP_Error
	 */
	public function search( $question, $limit = 5 ) {
		global $wpdb;

		$query_vector = ( new OpenRouter_Client() )->embed( $question );
		if ( is_wp_error( $query_vector ) ) {
			return $query_vector;
		}

		$model            = Settings::get_value( 'embedding_model', '' );
		$embeddings_table = Database::table( 'embeddings' );
		$chunks_table     = Database::table( 'chunks' );
		$documents_table  = Database::table( 'documents' );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT c.id AS chunk_id, c.chunk_text, c.page_number, d.filename, e.vector
				FROM %i e
				INNER JOIN %i c ON c.id = e.chunk_id
				INNER JOIN %i d ON d.id = c.document_id
				WHERE e.model = %s AND d.status = %s',
				$embeddings_table,
				$chunks_table,
				$documents_table,
				$model,
				'ready'
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$scored = array();
		foreach ( $rows as $row ) {
			$vector = json_decode( $row['vector'], true );
			if ( ! is_array( $vector ) ) {
				continue;
			}

			$score = $this->cosine_similarity( $query_vector, array_map( 'floatval', $vector ) );
			if ( $score <= 0 ) {
				continue;
			}

			$row['score']   = $score;
			$row['snippet'] = $this->snippet( $row['chunk_text'] );
			unset( $row['vector'] );
			$scored[] = $row;
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return array_slice( $scored, 0, max( 1, absint( $limit ) ) );
	}

	/**
	 * Cosine similarity.
	 *
	 * @param array $a Vector A.
	 * @param array $b Vector B.
	 * @return float
	 */
	private function cosine_similarity( $a, $b ) {
		$count = min( count( $a ), count( $b ) );
		if ( 0 === $count ) {
			return 0.0;
		}

		$dot = 0.0;
		$na  = 0.0;
		$nb  = 0.0;

		for ( $i = 0; $i < $count; $i++ ) {
			$dot += $a[ $i ] * $b[ $i ];
			$na  += $a[ $i ] * $a[ $i ];
			$nb  += $b[ $i ] * $b[ $i ];
		}

		if ( 0.0 === $na || 0.0 === $nb ) {
			return 0.0;
		}

		return $dot / ( sqrt( $na ) * sqrt( $nb ) );
	}

	/**
	 * Build short citation snippet.
	 *
	 * @param string $text Chunk text.
	 * @return string
	 */
	private function snippet( $text ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
		if ( strlen( $text ) <= 220 ) {
			return $text;
		}

		return rtrim( substr( $text, 0, 220 ) ) . '...';
	}
}
