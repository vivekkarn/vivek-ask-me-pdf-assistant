<?php
/**
 * Text chunking service.
 *
 * @package AskMeAI\Services
 */

namespace AskMeAI\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Text_Chunker {
	/**
	 * Split pages into searchable chunks.
	 *
	 * @param array $pages Page rows.
	 * @param int   $target_chars Target chunk size.
	 * @param int   $overlap_chars Overlap between chunks.
	 * @return array
	 */
	public function chunk_pages( $pages, $target_chars = 1400, $overlap_chars = 180 ) {
		$chunks = array();

		foreach ( $pages as $page ) {
			$text = trim( (string) ( $page['text'] ?? '' ) );
			if ( '' === $text ) {
				continue;
			}

			$paragraphs = preg_split( '/\n\s*\n/', $text );
			$buffer     = '';

			foreach ( $paragraphs as $paragraph ) {
				$paragraph = trim( preg_replace( '/\s+/', ' ', $paragraph ) );
				if ( '' === $paragraph ) {
					continue;
				}

				if ( strlen( $buffer . ' ' . $paragraph ) > $target_chars && '' !== $buffer ) {
					$chunks[] = array(
						'page' => absint( $page['page'] ?? 1 ),
						'text' => trim( $buffer ),
					);
					$buffer = substr( $buffer, max( 0, strlen( $buffer ) - $overlap_chars ) );
				}

				$buffer .= ' ' . $paragraph;
			}

			if ( '' !== trim( $buffer ) ) {
				$chunks[] = array(
					'page' => absint( $page['page'] ?? 1 ),
					'text' => trim( $buffer ),
				);
			}
		}

		return $chunks;
	}
}
