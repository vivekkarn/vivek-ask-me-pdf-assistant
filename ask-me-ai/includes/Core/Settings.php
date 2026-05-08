<?php
/**
 * Settings helper.
 *
 * @package AskMeAI\Core
 */

namespace AskMeAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {
	const OPTION_NAME = 'ask_me_ai_settings';

	/**
	 * Default plugin settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'                => '1',
			'shortcode_only'         => '0',
			'assistant_name'         => 'AskMe AI Docs',
			'welcome_message'        => 'Hi, I can help you find answers from the documents on this site.',
			'widget_color'           => '#0f766e',
			'position'               => 'bottom-right',
			'placeholder'            => 'Ask about the documents...',
			'suggested_questions'    => "What is covered in these documents?\nCan you summarize the key points?\nWhat should I know before getting started?",
			'powered_by'             => '1',
			'openrouter_api_key'     => '',
			'chat_model'             => 'openai/gpt-4o-mini',
			'embedding_model'        => 'openai/text-embedding-3-small',
			'embedding_endpoint'     => 'https://openrouter.ai/api/v1/embeddings',
			'assistant_instructions' => 'Answer using only the provided PDF context. If the answer is not in the context, say you do not know based on the available documents. Keep answers concise and cite sources when available.',
			'temperature'            => '0.2',
			'max_context_chunks'     => '5',
			'rate_limit_count'       => '20',
			'rate_limit_window'      => '300',
		);
	}

	/**
	 * Ensure option exists.
	 */
	public static function ensure_defaults() {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults(), '', false );
		}
	}

	/**
	 * Get settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$options = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return wp_parse_args( $options, self::defaults() );
	}

	/**
	 * Get one setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_value( $key, $default = null ) {
		$settings = self::get();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Persist settings with sanitization.
	 *
	 * @param array $input Raw settings.
	 */
	public static function update( $input ) {
		$current = self::get();
		$clean   = array();

		$clean['enabled']                = empty( $input['enabled'] ) ? '0' : '1';
		$clean['shortcode_only']         = empty( $input['shortcode_only'] ) ? '0' : '1';
		$clean['assistant_name']         = sanitize_text_field( $input['assistant_name'] ?? $current['assistant_name'] );
		$clean['welcome_message']        = sanitize_textarea_field( $input['welcome_message'] ?? $current['welcome_message'] );
		$clean['widget_color']           = sanitize_hex_color( $input['widget_color'] ?? $current['widget_color'] ) ?: $current['widget_color'];
		$clean['position']               = in_array( $input['position'] ?? '', array( 'bottom-right', 'bottom-left' ), true ) ? $input['position'] : 'bottom-right';
		$clean['placeholder']            = sanitize_text_field( $input['placeholder'] ?? $current['placeholder'] );
		$clean['suggested_questions']    = sanitize_textarea_field( $input['suggested_questions'] ?? $current['suggested_questions'] );
		$clean['powered_by']             = empty( $input['powered_by'] ) ? '0' : '1';
		$incoming_api_key                = sanitize_text_field( $input['openrouter_api_key'] ?? '' );
		$clean['openrouter_api_key']     = '' !== $incoming_api_key ? $incoming_api_key : $current['openrouter_api_key'];
		$clean['chat_model']             = sanitize_text_field( $input['chat_model'] ?? $current['chat_model'] );
		$clean['embedding_model']        = sanitize_text_field( $input['embedding_model'] ?? $current['embedding_model'] );
		$clean['embedding_endpoint']     = esc_url_raw( $input['embedding_endpoint'] ?? $current['embedding_endpoint'] );
		$clean['assistant_instructions'] = sanitize_textarea_field( $input['assistant_instructions'] ?? $current['assistant_instructions'] );
		$clean['temperature']            = (string) min( 1, max( 0, (float) ( $input['temperature'] ?? $current['temperature'] ) ) );
		$clean['max_context_chunks']     = (string) min( 12, max( 1, absint( $input['max_context_chunks'] ?? $current['max_context_chunks'] ) ) );
		$clean['rate_limit_count']       = (string) min( 200, max( 1, absint( $input['rate_limit_count'] ?? $current['rate_limit_count'] ) ) );
		$clean['rate_limit_window']      = (string) min( 3600, max( 60, absint( $input['rate_limit_window'] ?? $current['rate_limit_window'] ) ) );

		update_option( self::OPTION_NAME, wp_parse_args( $clean, $current ), false );
	}

	/**
	 * Return suggested questions as an array.
	 *
	 * @return array
	 */
	public static function suggested_questions() {
		$lines = preg_split( '/\r\n|\r|\n/', (string) self::get_value( 'suggested_questions', '' ) );
		$lines = array_map( 'trim', $lines );
		$lines = array_filter( $lines );

		return array_values( array_slice( $lines, 0, 6 ) );
	}
}
