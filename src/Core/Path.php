<?php
/**
 * Path and URL helpers. Plain PHP, no WordPress dependency.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\Core;

/**
 * Normalises request paths and redirect sources the same way, so they compare reliably.
 */
final class Path {

	/**
	 * Splits "/a/b?x=1#frag" into a decoded path and a raw query string.
	 *
	 * @return array{0: string, 1: string} Path (decoded, leading slash) and query (without "?").
	 */
	public static function split( string $uri ): array {
		$uri = trim( $uri );
		$pos = strpos( $uri, '#' );
		if ( false !== $pos ) {
			$uri = substr( $uri, 0, $pos );
		}
		$query = '';
		$pos   = strpos( $uri, '?' );
		if ( false !== $pos ) {
			$query = substr( $uri, $pos + 1 );
			$uri   = substr( $uri, 0, $pos );
		}
		$path = rawurldecode( $uri );
		if ( '' === $path || '/' !== $path[0] ) {
			$path = '/' . $path;
		}
		$path = preg_replace( '#/{2,}#', '/', $path );

		return array( $path, $query );
	}

	/**
	 * Comparison key for a path: no trailing slash (except "/"), lower case unless case sensitive.
	 */
	public static function key( string $path, bool $case_sensitive = false ): string {
		if ( strlen( $path ) > 1 ) {
			$path = rtrim( $path, '/' );
			if ( '' === $path ) {
				$path = '/';
			}
		}

		return $case_sensitive ? $path : self::lower( $path );
	}

	public static function lower( string $value ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	/**
	 * Sorted query parameters, so "b=2&a=1" equals "a=1&b=2".
	 *
	 * @return array<string, mixed>
	 */
	public static function query_params( string $query ): array {
		if ( '' === $query ) {
			return array();
		}
		parse_str( $query, $params );
		self::ksort_recursive( $params );

		return $params;
	}

	/**
	 * Appends a raw query string to a URL or path.
	 */
	public static function with_query( string $target, string $query ): string {
		if ( '' === $query ) {
			return $target;
		}
		$hash = '';
		$pos  = strpos( $target, '#' );
		if ( false !== $pos ) {
			$hash   = substr( $target, $pos );
			$target = substr( $target, 0, $pos );
		}

		return $target . ( false === strpos( $target, '?' ) ? '?' : '&' ) . $query . $hash;
	}

	/**
	 * URL parts: wp_parse_url() inside WordPress, PHP's parse_url() when the core classes run on
	 * their own (unit tests, CLI).
	 *
	 * @return array<string, int|string>|false
	 */
	private static function parse( string $url ) {
		if ( function_exists( 'wp_parse_url' ) ) {
			return wp_parse_url( $url );
		}
		return parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- only outside WordPress.
	}

	/**
	 * The site-relative path ("/x?y") of a target, or null when the target points to another host.
	 *
	 * @param string $target   Path or absolute URL.
	 * @param string $home_url The site address, for example "https://example.com/blog".
	 */
	public static function local( string $target, string $home_url ): ?string {
		$target = trim( $target );
		if ( '' === $target ) {
			return null;
		}
		if ( '/' === $target[0] && ( ! isset( $target[1] ) || '/' !== $target[1] ) ) {
			return $target;
		}

		$home   = self::parse( $home_url );
		$parsed = self::parse( 0 === strpos( $target, '//' ) ? 'http:' . $target : $target );
		if ( ! is_array( $home ) || ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return null;
		}
		if ( self::lower( $parsed['host'] ) !== self::lower( (string) ( $home['host'] ?? '' ) ) ) {
			return null;
		}

		$base = rtrim( (string) ( $home['path'] ?? '' ), '/' );
		$path = (string) ( $parsed['path'] ?? '/' );
		if ( '' !== $base ) {
			if ( 0 !== strpos( $path . '/', $base . '/' ) ) {
				return null;
			}
			$path = substr( $path, strlen( $base ) );
			$path = '' === $path ? '/' : $path;
		}

		return $path . ( isset( $parsed['query'] ) ? '?' . $parsed['query'] : '' );
	}

	/**
	 * @param array<mixed> $array
	 */
	private static function ksort_recursive( array &$array ): void {
		ksort( $array );
		foreach ( $array as &$value ) {
			if ( is_array( $value ) ) {
				self::ksort_recursive( $value );
			}
		}
	}
}
