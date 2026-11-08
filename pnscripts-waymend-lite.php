<?php
/**
 * Plugin Name:       PN Scripts Waymend Lite – Redirects, 404 Log and Old URL Check
 * Plugin URI:        https://pnscripts.com/marketplace/wp-waymend
 * Description:       Keep old links working: 301, 302, 307 and 410 redirects by exact URL or prefix, a 404 log with one-click redirects, loop protection, and a check of the old site's URLs before a move.
 * Version:           1.0.1
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            PN Scripts
 * Author URI:        https://pnscripts.com
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       pnscripts-waymend-lite
 * Domain Path:       /languages
 *
 * Copyright (c) 2026 ПН СКРИПТС ЕООД / PN Scripts (office@pnscripts.com)
 *
 * @package WaymendLite
 */

defined( 'ABSPATH' ) || exit;

define( 'WAYMEND_LITE_VERSION', '1.0.1' );
define( 'WAYMEND_LITE_FILE', __FILE__ );
define( 'WAYMEND_LITE_DIR', plugin_dir_path( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'PNScripts\\WaymendLite\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$file = WAYMEND_LITE_DIR . 'src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, array( \PNScripts\WaymendLite\WordPress\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \PNScripts\WaymendLite\WordPress\Installer::class, 'deactivate' ) );

// With the full Waymend active, the Lite edition stands aside (both would answer the same URLs).
add_action(
	'plugins_loaded',
	static function () {
		if ( defined( 'WAYMEND_VERSION' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-info"><p>' . esc_html__( 'Waymend (full edition) is active, so Waymend Lite is not running. You can deactivate Waymend Lite.', 'pnscripts-waymend-lite' ) . '</p></div>';
					}
				}
			);
			return;
		}
		\PNScripts\WaymendLite\WordPress\Plugin::boot();
	}
);
