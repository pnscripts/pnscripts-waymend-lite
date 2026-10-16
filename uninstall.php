<?php
/**
 * Removes Waymend's tables and options when the plugin is deleted from the Plugins screen.
 *
 * @package Waymend
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- dropping the plugin's own tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}waymend_lite_rules" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}waymend_lite_404" );
// phpcs:enable

delete_option( 'waymend_lite_settings' );
delete_option( 'waymend_lite_db_version' );
wp_clear_scheduled_hook( 'waymend_lite_prune_log' );
