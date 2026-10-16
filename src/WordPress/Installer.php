<?php
/**
 * Database tables and scheduled clean-up.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the two tables: rules and the 404 log.
 */
final class Installer {

	const DB_VERSION = '1';

	public static function activate(): void {
		self::install();
		if ( ! wp_next_scheduled( 'waymend_lite_prune_log' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'waymend_lite_prune_log' );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'waymend_lite_prune_log' );
	}

	public static function maybe_upgrade(): void {
		if ( get_option( 'waymend_lite_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$rules   = $wpdb->prefix . 'waymend_lite_rules';
		$log     = $wpdb->prefix . 'waymend_lite_404';

		dbDelta(
			"CREATE TABLE {$rules} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source varchar(2000) NOT NULL,
				target varchar(2000) NOT NULL DEFAULT '',
				match_type varchar(10) NOT NULL DEFAULT 'exact',
				status smallint(5) unsigned NOT NULL DEFAULT 301,
				query_mode varchar(10) NOT NULL DEFAULT 'ignore',
				case_sensitive tinyint(1) NOT NULL DEFAULT 0,
				enabled tinyint(1) NOT NULL DEFAULT 1,
				hits bigint(20) unsigned NOT NULL DEFAULT 0,
				last_hit datetime NULL DEFAULT NULL,
				position int(11) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY enabled (enabled)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$log} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				path varchar(2000) NOT NULL,
				path_hash char(32) NOT NULL,
				hits bigint(20) unsigned NOT NULL DEFAULT 1,
				first_seen datetime NOT NULL,
				last_seen datetime NOT NULL,
				referrer varchar(2000) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				UNIQUE KEY path_hash (path_hash),
				KEY last_seen (last_seen)
			) {$charset};"
		);

		update_option( 'waymend_lite_db_version', self::DB_VERSION, false );
		if ( false === get_option( 'waymend_lite_settings' ) ) {
			add_option( 'waymend_lite_settings', Settings::defaults(), '', false );
		}
	}
}
