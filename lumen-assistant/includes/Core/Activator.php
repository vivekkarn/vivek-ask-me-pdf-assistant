<?php
/**
 * Activation and deactivation hooks.
 *
 * @package AskMeAI\Core
 */

namespace AskMeAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {
	/**
	 * Create tables and default options.
	 */
	public static function activate() {
		Database::create_tables();
		Settings::ensure_defaults();
		flush_rewrite_rules();
	}

	/**
	 * Flush routes on deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
