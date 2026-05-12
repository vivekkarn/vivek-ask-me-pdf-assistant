<?php
/**
 * Public endpoint rate limiter.
 *
 * @package AskMeAI\Core
 */

namespace AskMeAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rate_Limiter {
	/**
	 * Check current request allowance.
	 *
	 * @return true|\WP_Error
	 */
	public static function check() {
		$settings = Settings::get();
		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key      = 'ask_me_ai_rate_' . md5( $ip . wp_salt( 'nonce' ) );
		$count    = (int) get_transient( $key );
		$limit    = absint( $settings['rate_limit_count'] );
		$window   = absint( $settings['rate_limit_window'] );

		if ( $count >= $limit ) {
			return new \WP_Error( 'rate_limited', __( 'Too many questions. Please try again later.', 'vivek-ask-me-pdf-assistant' ), array( 'status' => 429 ) );
		}

		set_transient( $key, $count + 1, $window );
		return true;
	}
}
