<?php
/**
 * PDF text extraction.
 *
 * @package AskMeAI\Services
 */

namespace AskMeAI\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pdf_Text_Extractor {
	/**
	 * Extract text from PDF.
	 *
	 * Uses Smalot\PdfParser when available, then falls back to a small stream parser.
	 * Hosts can replace this through the ask_me_ai_pdf_text filter.
	 *
	 * @param string $file_path PDF path.
	 * @return array[] Array of page rows with page and text.
	 */
	public function extract( $file_path ) {
		$text = '';

		if ( class_exists( '\Smalot\PdfParser\Parser' ) ) {
			try {
				$parser = new \Smalot\PdfParser\Parser();
				$pdf    = $parser->parseFile( $file_path );
				$pages  = array();
				foreach ( $pdf->getPages() as $index => $page ) {
					$pages[] = array(
						'page' => $index + 1,
						'text' => $this->clean_text( $page->getText() ),
					);
				}
				return apply_filters( 'ask_me_ai_pdf_text', $pages, $file_path );
			} catch ( \Exception $e ) {
				$text = '';
			}
		}

		$raw = $this->read_file( $file_path );
		if ( false === $raw ) {
			return array();
		}

		$text  = $this->extract_from_streams( $raw );
		$pages = array(
			array(
				'page' => 1,
				'text' => $this->clean_text( $text ),
			),
		);

		return apply_filters( 'ask_me_ai_pdf_text', $pages, $file_path );
	}

	/**
	 * Best-effort extraction from PDF content streams.
	 *
	 * @param string $raw Raw PDF bytes.
	 * @return string
	 */
	private function extract_from_streams( $raw ) {
		$text = '';

		if ( preg_match_all( '/stream\s*(.*?)\s*endstream/s', $raw, $matches ) ) {
			foreach ( $matches[1] as $stream ) {
				$decoded = @gzuncompress( trim( $stream ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( false === $decoded ) {
					$decoded = $stream;
				}

				$text .= "\n" . $this->decode_text_operators( $decoded );
			}
		}

		return $text;
	}

	/**
	 * Decode common PDF text operators.
	 *
	 * @param string $stream Content stream.
	 * @return string
	 */
	private function decode_text_operators( $stream ) {
		$out = '';

		if ( preg_match_all( '/\((?:\\\\.|[^\\\\)])*\)\s*T[jJ]/s', $stream, $matches ) ) {
			foreach ( $matches[0] as $match ) {
				if ( preg_match( '/\((.*)\)\s*T[jJ]/s', $match, $text_match ) ) {
					$out .= ' ' . $this->decode_pdf_string( $text_match[1] );
				}
			}
		}

		if ( preg_match_all( '/\[(.*?)\]\s*TJ/s', $stream, $matches ) ) {
			foreach ( $matches[1] as $array ) {
				if ( preg_match_all( '/\((?:\\\\.|[^\\\\)])*\)/s', $array, $strings ) ) {
					foreach ( $strings[0] as $string ) {
						$out .= ' ' . $this->decode_pdf_string( trim( $string, '()' ) );
					}
				}
			}
		}

		return $out;
	}

	/**
	 * Decode escaped PDF string.
	 *
	 * @param string $value Encoded string.
	 * @return string
	 */
	private function decode_pdf_string( $value ) {
		$value = preg_replace_callback(
			'/\\\\([0-7]{1,3}|n|r|t|b|f|\\\\|\(|\))/',
			static function ( $match ) {
				$char = $match[1];
				if ( is_numeric( $char ) ) {
					return chr( octdec( $char ) );
				}
				$map = array(
					'n'  => "\n",
					'r'  => "\r",
					't'  => "\t",
					'b'  => "\b",
					'f'  => "\f",
					'\\' => '\\',
					'('  => '(',
					')'  => ')',
				);
				return $map[ $char ] ?? $char;
			},
			$value
		);

		return wp_strip_all_tags( $value );
	}

	/**
	 * Normalize extracted text.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function clean_text( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );

		return trim( $text );
	}

	/**
	 * Read a local file through the WordPress filesystem API when available.
	 *
	 * @param string $file_path File path.
	 * @return string|false
	 */
	private function read_file( $file_path ) {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( WP_Filesystem() && $wp_filesystem ) {
			return $wp_filesystem->get_contents( $file_path );
		}

		return false;
	}
}
