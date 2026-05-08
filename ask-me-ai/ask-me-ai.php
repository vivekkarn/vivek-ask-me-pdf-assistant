<?php
/**
 * Plugin Name: Lumen Assistant
 * Plugin URI: https://github.com/vivekkarn/lumen-assistant
 * Description: Intercom-inspired floating document support assistant that answers from uploaded PDF documents using RAG and OpenRouter.
 * Version: 1.0.0
 * Author: Vivek
 * Author URI: https://buymemomo.com/vivek
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Donate link: https://buymemomo.com/vivek
 * Text Domain: ask-me-ai
 *
 * @package AskMeAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASK_ME_AI_VERSION', '1.0.0' );
define( 'ASK_ME_AI_FILE', __FILE__ );
define( 'ASK_ME_AI_PATH', plugin_dir_path( __FILE__ ) );
define( 'ASK_ME_AI_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( ASK_ME_AI_PATH . 'vendor/autoload.php' ) ) {
	require_once ASK_ME_AI_PATH . 'vendor/autoload.php';
}

require_once ASK_ME_AI_PATH . 'includes/Core/Autoloader.php';

\AskMeAI\Core\Autoloader::register();

register_activation_hook( __FILE__, array( \AskMeAI\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \AskMeAI\Core\Activator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\AskMeAI\Core\Plugin::instance()->init();
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		$settings_url = admin_url( 'admin.php?page=ask-me-ai' );
		$donate_url   = 'https://buymemomo.com/vivek';

		array_unshift(
			$links,
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'ask-me-ai' ) . '</a>',
			'<a href="' . esc_url( $donate_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Donate', 'ask-me-ai' ) . '</a>'
		);

		return $links;
	}
);
