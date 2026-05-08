<?php
/**
 * Main plugin bootstrap.
 *
 * @package AskMeAI\Core
 */

namespace AskMeAI\Core;

use AskMeAI\Admin\Admin_Page;
use AskMeAI\Core\Rest_Controller;
use AskMeAI\Core\Widget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		load_plugin_textdomain( 'ask-me-ai', false, dirname( plugin_basename( ASK_ME_AI_FILE ) ) . '/languages' );

		Settings::ensure_defaults();

		( new Admin_Page() )->init();
		( new Rest_Controller() )->init();
		( new Widget() )->init();
	}
}
