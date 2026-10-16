<?php
/**
 * Bootstrap.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\WordPress;

use PNScripts\WaymendLite\WordPress\Admin\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin into WordPress.
 */
final class Plugin {

	/** @var Plugin|null */
	private static $instance;

	/** @var Rule_Repository */
	public $rules;
	/** @var Not_Found_Log */
	public $log;
	/** @var Redirector */
	public $redirector;

	public static function boot(): void {
		self::instance();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->rules      = new Rule_Repository();
		$this->log        = new Not_Found_Log();
		$this->redirector = new Redirector( $this->rules, $this->log );

		Installer::maybe_upgrade();
		$this->redirector->register();
		add_action( 'waymend_lite_prune_log', array( $this->log, 'prune' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( is_admin() ) {
			( new Admin( $this ) )->register();
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'pnscripts-waymend-lite', false, dirname( plugin_basename( WAYMEND_LITE_FILE ) ) . '/languages' );
	}
}
