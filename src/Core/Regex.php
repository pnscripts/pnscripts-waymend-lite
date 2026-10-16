<?php
/**
 * Regular expression helpers for regex rules. Plain PHP, no WordPress dependency.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\Core;

/**
 * Sources of regex rules are written without delimiters, for example "^/blog/(\d{4})/(.+)$".
 * Targets use $1 to $9 or ${10} for capture groups.
 */
final class Regex {

	/**
	 * Wraps a delimiter-less pattern for preg_*.
	 */
	public static function compile( string $pattern, bool $case_sensitive = false ): string {
		return '#' . str_replace( '#', '\#', $pattern ) . '#u' . ( $case_sensitive ? '' : 'i' );
	}

	/**
	 * Null when the pattern is valid, otherwise a short error description.
	 */
	public static function error( string $pattern ): ?string {
		if ( '' === trim( $pattern ) ) {
			return 'The pattern is empty.';
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the warning is turned into a message below.
		$result = @preg_match( self::compile( $pattern ), '' );
		if ( false === $result ) {
			$error = error_get_last();
			$text  = is_array( $error ) ? preg_replace( '/^preg_match\(\): /', '', (string) $error['message'] ) : 'Invalid pattern.';

			return $text;
		}

		return null;
	}

	/**
	 * Capture groups (index 0 = whole match) or null when there is no match or the pattern fails.
	 *
	 * @return array<int|string, string>|null
	 */
	public static function match( string $pattern, string $subject, bool $case_sensitive = false ): ?array {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a broken pattern must never break the site.
		$result = @preg_match( self::compile( $pattern, $case_sensitive ), $subject, $groups );

		return 1 === $result ? $groups : null;
	}

	/**
	 * Replaces $1..$99 and ${1}..${99} in $target with captured groups (missing groups become "").
	 *
	 * @param array<int|string, string> $groups
	 */
	public static function substitute( string $target, array $groups ): string {
		return (string) preg_replace_callback(
			'/\$(?:\{(\d{1,2})\}|(\d{1,2}))/',
			static function ( array $m ) use ( $groups ) {
				if ( '' !== $m[1] ) {
					return $groups[ (int) $m[1] ] ?? '';
				}
				// "$10" means group 10 only when it exists; otherwise group 1 followed by "0".
				if ( strlen( $m[2] ) === 2 && ! isset( $groups[ (int) $m[2] ] ) ) {
					return ( $groups[ (int) $m[2][0] ] ?? '' ) . $m[2][1];
				}

				return $groups[ (int) $m[2] ] ?? '';
			},
			$target
		);
	}

	public static function has_captures( string $target ): bool {
		return 1 === preg_match( '/\$(?:\{\d{1,2}\}|\d{1,2})/', $target );
	}
}
