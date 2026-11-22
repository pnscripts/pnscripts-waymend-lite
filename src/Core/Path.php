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
	 * Cleans a path or URL from a request or a form before esc_url_raw(): removes control
	 * characters and percent-encodes everything esc_url_raw() would silently drop (spaces,
	 * quotes, angle brackets, backslashes, braces, a lone "%", invalid UTF-8), so the cleaned
	 * value still means the same page. At most 2000 bytes.
	 */
	public static function clean( string $raw ): string {
		$value = trim( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', $raw ) );
		if ( 1 !== preg_match( '//u', $value ) ) {
			$value = (string) preg_replace_callback(
				'/[\x80-\xFF]/',
				static function ( array $m ): string {
					return rawurlencode( $m[0] );
				},
				$value
			);
		}
		$value = (string) preg_replace_callback(
			'/%(?![0-9A-Fa-f]{2})|[^A-Za-z0-9\-~+_.?#=!&;,\/:%@$|*\'()\[\]\x80-\xFF]/',
			static function ( array $m ): string {
				return rawurlencode( $m[0] );
			},
			$value
		);

		return self::cut( $value, 2000 );
	}

	/**
	 * Decodes percent-encoded UTF-8 letters (as wp_sanitize_redirect() leaves them) back into
	 * text, so non-Latin paths stay readable. ASCII octets and invalid sequences stay encoded,
	 * so nothing esc_url_raw() would refuse comes back.
	 */
	public static function readable( string $value ): string {
		return (string) preg_replace_callback(
			'/(?:%[C-Fc-f][0-9A-Fa-f](?:%[89ABab][0-9A-Fa-f]){1,3})+/',
			static function ( array $m ): string {
				$text = rawurldecode( $m[0] );

				return 1 === preg_match( '//u', $text ) ? $text : $m[0];
			},
			$value
		);
	}

	/**
	 * A cleaned site-relative path that starts with exactly one "/" (never a protocol-relative
	 * "//host" URL), or "" when nothing is left.
	 */
	public static function site_path( string $raw ): string {
		$value = self::clean( $raw );
		if ( '' === $value ) {
			return '';
		}

		return self::cut( '/' . ltrim( $value, '/' ), 2000 );
	}

	/**
	 * The file a URL path points to below a folder served at $base_url (the uploads folder,
	 * from wp_upload_dir()), or null when the path is not below it or tries to climb out.
	 *
	 * @param string $path     Decoded URL path from the site root, for example "/wp-content/uploads/a.jpg".
	 * @param string $base_url URL of the folder, for example "https://example.com/wp-content/uploads".
	 * @param string $base_dir Directory of the folder on disk.
	 */
	public static function file_below( string $path, string $base_url, string $base_dir ): ?string {
		$parts = self::parse( $base_url );
		$base  = rtrim( (string) ( is_array( $parts ) ? ( $parts['path'] ?? '' ) : '' ), '/' );
		if ( 0 !== strpos( $path, $base . '/' ) || false !== strpos( $path, "\0" ) ) {
			return null;
		}
		$rest = substr( $path, strlen( $base ) + 1 );
		if ( '' === $rest ) {
			return null;
		}
		foreach ( explode( '/', str_replace( '\\', '/', $rest ) ) as $segment ) {
			if ( '..' === $segment || '.' === $segment ) {
				return null;
			}
		}

		return rtrim( $base_dir, '/\\' ) . '/' . $rest;
	}

	/**
	 * Shortens to at most $bytes bytes without splitting a percent-encoded octet.
	 */
	private static function cut( string $value, int $bytes ): string {
		if ( strlen( $value ) <= $bytes ) {
			return $value;
		}
		$value = substr( $value, 0, $bytes );

		return (string) preg_replace( '/%[0-9A-Fa-f]?$/', '', $value );
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
