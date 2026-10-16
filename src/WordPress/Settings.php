<?php
/**
 * Plugin settings.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\WordPress;

use PNScripts\WaymendLite\Core\Ignore_List;

defined( 'ABSPATH' ) || exit;

/**
 * Settings stored in the "waymend_lite_settings" option.
 */
final class Settings {

	/**
	 * @return array{log_404: bool, log_max_rows: int, ignore_patterns: string, default_status: int}
	 */
	public static function defaults(): array {
		return array(
			'log_404'         => true,
			'log_max_rows'    => 2000,
			'ignore_patterns' => Ignore_List::DEFAULT_PATTERNS,
			'default_status'  => 301,
		);
	}

	/**
	 * @return array{log_404: bool, log_max_rows: int, ignore_patterns: string, default_status: int}
	 */
	public static function all(): array {
		$saved = get_option( 'waymend_lite_settings', array() );

		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * @return mixed
	 */
	public static function get( string $key ) {
		return self::all()[ $key ] ?? null;
	}

	/**
	 * @param array<string, mixed> $input Raw (unslashed) form input.
	 * @return array{log_404: bool, log_max_rows: int, ignore_patterns: string, default_status: int}
	 */
	public static function sanitize( array $input ): array {
		$status = absint( $input['default_status'] ?? 301 );

		return array(
			'log_404'         => ! empty( $input['log_404'] ),
			'log_max_rows'    => max( 100, min( 100000, absint( $input['log_max_rows'] ?? 2000 ) ) ),
			'ignore_patterns' => sanitize_textarea_field( (string) ( $input['ignore_patterns'] ?? '' ) ),
			'default_status'  => in_array( $status, array( 301, 302, 307, 410 ), true ) ? $status : 301,
		);
	}

	/**
	 * @param array<string, mixed> $values
	 */
	public static function save( array $values ): void {
		update_option( 'waymend_lite_settings', self::sanitize( $values ), false );
	}
}
