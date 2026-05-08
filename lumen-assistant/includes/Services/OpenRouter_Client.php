<?php
/**
 * OpenRouter API client.
 *
 * @package AskMeAI\Services
 */

namespace AskMeAI\Services;

use AskMeAI\Core\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OpenRouter_Client {
	const CHAT_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

	/**
	 * Generate an embedding vector.
	 *
	 * @param string $text Text to embed.
	 * @return array|\WP_Error
	 */
	public function embed( $text ) {
		$settings = Settings::get();
		$api_key  = $settings['openrouter_api_key'];

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'missing_api_key', __( 'OpenRouter API key is not configured.', 'lumen-assistant' ) );
		}

		$response = wp_remote_post(
			$settings['embedding_endpoint'],
			array(
				'timeout' => 45,
				'headers' => $this->headers( $api_key ),
				'body'    => wp_json_encode(
					array(
						'model' => $settings['embedding_model'],
						'input' => $text,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error( 'embedding_failed', $this->extract_error_message( $body, __( 'Embedding request failed.', 'lumen-assistant' ) ) );
		}

		$vector = $body['data'][0]['embedding'] ?? null;
		if ( ! is_array( $vector ) ) {
			return new \WP_Error( 'invalid_embedding_response', __( 'Embedding response did not include a vector.', 'lumen-assistant' ) );
		}

		return array_map( 'floatval', $vector );
	}

	/**
	 * Generate an answer from chat completion.
	 *
	 * @param array  $messages Chat messages.
	 * @param string $model Model name.
	 * @param float  $temperature Sampling temperature.
	 * @return string|\WP_Error
	 */
	public function chat( $messages, $model, $temperature = 0.2 ) {
		$api_key = Settings::get_value( 'openrouter_api_key', '' );
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'missing_api_key', __( 'OpenRouter API key is not configured.', 'lumen-assistant' ) );
		}

		$response = wp_remote_post(
			self::CHAT_ENDPOINT,
			array(
				'timeout' => 60,
				'headers' => $this->headers( $api_key ),
				'body'    => wp_json_encode(
					array(
						'model'       => $model,
						'messages'    => $messages,
						'temperature' => $temperature,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error( 'chat_failed', $this->extract_error_message( $body, __( 'Chat completion request failed.', 'lumen-assistant' ) ) );
		}

		$content = $body['choices'][0]['message']['content'] ?? '';
		if ( '' === trim( $content ) ) {
			return new \WP_Error( 'empty_chat_response', __( 'The AI provider returned an empty response.', 'lumen-assistant' ) );
		}

		return trim( wp_kses_post( $content ) );
	}

	/**
	 * Request headers.
	 *
	 * @param string $api_key API key.
	 * @return array
	 */
	private function headers( $api_key ) {
		return array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
			'HTTP-Referer'  => home_url(),
			'X-Title'       => get_bloginfo( 'name' ),
		);
	}

	/**
	 * Extract API error message.
	 *
	 * @param array|null $body Parsed body.
	 * @param string     $fallback Fallback message.
	 * @return string
	 */
	private function extract_error_message( $body, $fallback ) {
		if ( is_array( $body ) && ! empty( $body['error']['message'] ) ) {
			return sanitize_text_field( $body['error']['message'] );
		}

		return $fallback;
	}
}
