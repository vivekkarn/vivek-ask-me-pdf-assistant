<?php
/**
 * Simple PSR-4 style autoloader for plugin classes.
 *
 * @package AskMeAI\Core
 */

namespace AskMeAI\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Autoloader {
	/**
	 * Register autoloader.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Load AskMeAI classes.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, 'AskMeAI\\' ) ) {
			return;
		}

		$relative = str_replace( 'AskMeAI\\', '', $class );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$file     = ASK_ME_AI_PATH . 'includes' . DIRECTORY_SEPARATOR . $relative . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
