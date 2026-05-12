<?php
/**
 * Frontend widget loader.
 *
 * @package AskMeAI\Core
 */

namespace AskMeAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Widget {
	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_auto_widget' ) );
		add_shortcode( 'ask_me_ai_widget', array( $this, 'shortcode' ) );
	}

	/**
	 * Register assets.
	 */
	public function register_assets() {
		wp_register_style( 'ask-me-ai-widget', ASK_ME_AI_URL . 'assets/css/widget.css', array(), ASK_ME_AI_VERSION );
		wp_register_script( 'ask-me-ai-widget', ASK_ME_AI_URL . 'assets/js/widget.js', array(), ASK_ME_AI_VERSION, true );
	}

	/**
	 * Render globally enabled widget.
	 */
	public function render_auto_widget() {
		$settings = Settings::get();
		if ( '1' !== $settings['enabled'] || '1' === $settings['shortcode_only'] ) {
			return;
		}

		$this->render();
	}

	/**
	 * Shortcode renderer.
	 *
	 * @return string
	 */
	public function shortcode() {
		ob_start();
		$this->render();
		return ob_get_clean();
	}

	/**
	 * Render widget mount.
	 */
	private function render() {
		wp_enqueue_style( 'ask-me-ai-widget' );
		wp_enqueue_script( 'ask-me-ai-widget' );
		wp_localize_script(
			'ask-me-ai-widget',
			'AskMeAIConfig',
			array(
				'restUrl' => esc_url_raw( rest_url( Rest_Controller::NAMESPACE ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'config'  => self::public_config(),
			)
		);

		echo '<div id="ask-me-ai-widget-root" data-ask-me-ai-widget></div>';
	}

	/**
	 * Public settings for widget.
	 *
	 * @return array
	 */
	public static function public_config() {
		$settings = Settings::get();

		return array(
			'assistantName'      => $settings['assistant_name'],
			'welcomeMessage'     => $settings['welcome_message'],
			'widgetColor'        => $settings['widget_color'],
			'position'           => $settings['position'],
			'placeholder'        => $settings['placeholder'],
			'suggestedQuestions' => Settings::suggested_questions(),
			'enabled'            => '1' === $settings['enabled'],
		);
	}
}
