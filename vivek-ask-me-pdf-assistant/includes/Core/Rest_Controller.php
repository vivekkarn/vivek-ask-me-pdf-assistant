<?php
/**
 * REST API routes.
 *
 * @package AskMeAI\Core
 */

namespace AskMeAI\Core;

use AskMeAI\Services\Chat_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Controller {
	const NAMESPACE = 'vivek-ask-me-pdf-assistant/v1';

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/config',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'config' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/chat',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'chat' ),
				'permission_callback' => array( $this, 'public_permission' ),
				'args'                => array(
					'message'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'session_id' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Return public widget config.
	 *
	 * @return \WP_REST_Response
	 */
	public function config() {
		return rest_ensure_response( Widget::public_config() );
	}

	/**
	 * Public permission and rate limit check.
	 *
	 * @return true|\WP_Error
	 */
	public function public_permission() {
		$settings = Settings::get();
		if ( '1' !== $settings['enabled'] ) {
			return new \WP_Error( 'widget_disabled', __( 'The assistant is currently disabled.', 'vivek-ask-me-pdf-assistant' ), array( 'status' => 403 ) );
		}

		$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ?? '' ) );
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Security check failed.', 'vivek-ask-me-pdf-assistant' ), array( 'status' => 403 ) );
		}

		return Rate_Limiter::check();
	}

	/**
	 * Handle chat request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function chat( \WP_REST_Request $request ) {
		$message    = (string) $request->get_param( 'message' );
		$session_id = (string) $request->get_param( 'session_id' );
		$result     = ( new Chat_Service() )->answer( $message, $session_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}
}
