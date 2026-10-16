<?php
/**
 * Runs "Check old URLs" inside WordPress: rules from the database; pages checked
 * through WordPress itself (published posts, real files and WordPress's routing,
 * without HTTP), then confirmed with a loopback HEAD request that follows no
 * redirects and is kept out of the 404 log, when the site can call itself.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\WordPress;

use PNScripts\WaymendLite\Core\Link_Check;

/**
 * WordPress side of the link check.
 */
final class Link_Checker {

	/** Seconds one check may take before the rest is reported as not checked. */
	const TIME_LIMIT = 25;

	/** @var Plugin */
	private $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * @param list<string> $uris Site-relative URIs from Link_Check::parse_list().
	 * @return array{results: list<array<string, mixed>>, unchecked: int}
	 */
	public function run( array $uris ): array {
		$check     = new Link_Check( home_url( '/' ) );
		$token     = wp_generate_password( 32, false );
		$rules     = $this->plugin->rules->enabled();
		$results   = array();
		$unchecked = 0;
		$deadline  = microtime( true ) + self::TIME_LIMIT;

		set_transient( 'waymend_lite_link_check_token', $token, 10 * MINUTE_IN_SECONDS );
		$loopback  = true;
		$status_of = static function ( string $uri ) use ( $token, &$loopback ): ?int {
			if ( '/' === $uri ) {
				return 200;
			}
			$url = home_url( $uri );
			$id  = url_to_postid( $url );
			if ( $id > 0 && 'publish' === get_post_status( $id ) ) {
				return 200;
			}
			$inside = self::status_inside_wordpress( $uri );
			if ( 200 === $inside || ! $loopback ) {
				return $inside;
			}
			// Ask the site itself (catches WordPress's own guess redirects and other plugins' pages).
			$response = wp_remote_head(
				$url,
				array(
					'redirection' => 0,
					'timeout'     => 5,
					// Same rule as WordPress's own loopback checks (Site Health): local certificates may be self-signed.
					'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
					'headers'     => array(
						'X-Waymend-Check' => $token,
						'Cache-Control'   => 'no-cache',
					),
					'user-agent'  => 'Waymend link check; ' . home_url( '/' ),
				)
			);
			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			if ( $code <= 0 ) {
				$loopback = false; // The site cannot call itself (a single-worker server, a firewall): stop trying.
				return $inside;
			}

			return $code;
		};

		foreach ( $uris as $uri ) {
			if ( microtime( true ) > $deadline ) {
				++$unchecked;
				continue;
			}
			$row = $check->check( $uri, $rules, $status_of );
			if ( Link_Check::MISSING === $row['verdict'] ) {
				$row['suggestion'] = $this->plugin->log->suggest_target( $uri );
			}
			$results[] = $row;
		}
		delete_transient( 'waymend_lite_link_check_token' );

		return array(
			'results'   => $results,
			'unchecked' => $unchecked,
		);
	}

	/**
	 * Status of a site-relative URI worked out inside WordPress, without an HTTP request:
	 * a real file, or WordPress's own routing (rewrite rules + query), 200 or 404.
	 */
	public static function status_inside_wordpress( string $uri ): ?int {
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$file = realpath( ABSPATH . ltrim( rawurldecode( $path ), '/' ) );
		$root = realpath( ABSPATH );
		if ( '/' !== $path && false !== $file && false !== $root && 0 === strpos( $file, $root ) && is_file( $file ) ) {
			return 200;
		}

		$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		// Parse the URI as a front-end request: WP::parse_request() drops permalink matches when
		// PHP_SELF is inside wp-admin, which is where this runs from.
		$keys  = array( 'REQUEST_URI', 'PHP_SELF', 'SCRIPT_NAME', 'PATH_INFO' );
		$saved = array();
		foreach ( $keys as $key ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- restored unchanged below.
			$saved[ $key ] = array_key_exists( $key, $_SERVER ) ? $_SERVER[ $key ] : null;
		}
		$_SERVER['REQUEST_URI'] = $base . $uri;
		$_SERVER['PHP_SELF']    = $base . '/index.php';
		$_SERVER['SCRIPT_NAME'] = $base . '/index.php';
		unset( $_SERVER['PATH_INFO'] );
		try {
			$wp = new \WP();
			$wp->parse_request();
			$vars = $wp->query_vars;
		} finally {
			foreach ( $saved as $key => $value ) {
				if ( null === $value ) {
					unset( $_SERVER[ $key ] );
				} else {
					$_SERVER[ $key ] = $value;
				}
			}
		}
		if ( ! empty( $vars['error'] ) ) {
			return 404;
		}
		$query = new \WP_Query();
		$query->query( $vars );
		if ( $query->have_posts() || $query->is_home() || $query->is_front_page() || $query->is_search() || $query->is_feed() ) {
			return 200;
		}

		return 404;
	}
}
