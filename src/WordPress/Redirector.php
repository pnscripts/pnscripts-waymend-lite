<?php
/**
 * Front-end redirect handling and 404 logging.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\WordPress;

use PNScripts\WaymendLite\Core\Match_Result;
use PNScripts\WaymendLite\Core\Matcher;
use PNScripts\WaymendLite\Core\Path;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on template_redirect: answers matching requests with the rule's redirect or 410,
 * and records unanswered 404s in the log.
 */
final class Redirector {

	/** @var Rule_Repository */
	private $rules;
	/** @var Not_Found_Log */
	private $log;
	/** @var bool */
	private $handled = false;

	public function __construct( Rule_Repository $rules, Not_Found_Log $log ) {
		$this->rules = $rules;
		$this->log   = $log;
	}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
		add_action( 'template_redirect', array( $this, 'maybe_log_404' ), 999 );
	}

	/**
	 * The request URI relative to the site address, for example "/old-page/?a=1".
	 */
	public static function request_uri(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared against rules, never output; sanitized on storage.
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/';
		$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $base && 0 === strpos( $uri . '/', $base . '/' ) ) {
			$uri = substr( $uri, strlen( $base ) );
		}

		return '' === $uri ? '/' : $uri;
	}

	public function find( string $uri ): ?Match_Result {
		/**
		 * Filters the request URI before it is matched against the redirect rules.
		 *
		 * @param string $uri Site-relative URI.
		 */
		$uri = (string) apply_filters( 'waymend_lite_request_uri', $uri );

		return ( new Matcher( $this->rules->enabled() ) )->match( $uri );
	}

	public function maybe_redirect(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$uri    = self::request_uri();
		$result = $this->find( $uri );
		if ( null === $result ) {
			return;
		}

		if ( $result->rule->is_gone() ) {
			$this->handled = true;
			$this->rules->record_hit( $result->rule->id );
			global $wp_query;
			$wp_query->set_404();
			status_header( 410 );
			nocache_headers();
			header( 'X-Redirect-By: Waymend' );

			return;
		}

		$location = self::absolute( $result->target );

		// Runtime loop guard: never redirect a URL to itself.
		$local = Path::local( $location, home_url( '/' ) );
		if ( null !== $local && self::same( $local, $uri ) ) {
			return;
		}

		$this->handled = true;
		$this->rules->record_hit( $result->rule->id );

		/**
		 * Filters the redirect location before Waymend sends it.
		 *
		 * @param string                               $location Absolute URL.
		 * @param \PNScripts\WaymendLite\Core\Match_Result $result   The matched rule and status.
		 */
		$location = (string) apply_filters( 'waymend_lite_redirect_location', $location, $result );

		if ( wp_redirect( $location, $result->status, 'Waymend' ) ) {
			exit;
		}
	}

	/**
	 * Requests made by "Check old URLs" carry a one-time token; their 404s are not logged.
	 */
	public static function is_link_check(): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared with hash_equals only.
		$sent  = isset( $_SERVER['HTTP_X_WAYMEND_CHECK'] ) ? (string) wp_unslash( $_SERVER['HTTP_X_WAYMEND_CHECK'] ) : '';
		$token = (string) get_transient( 'waymend_lite_link_check_token' );

		return '' !== $sent && '' !== $token && hash_equals( $token, $sent );
	}

	public function maybe_log_404(): void {
		if ( $this->handled || ! is_404() || self::is_link_check() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized with esc_url_raw() in record().
		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( (string) $_SERVER['HTTP_REFERER'] ) : '';
		$this->log->record( self::request_uri(), $referrer );
	}

	/**
	 * Site-relative targets become absolute URLs on this site; others are returned as they are.
	 */
	public static function absolute( string $target ): string {
		if ( '' !== $target && '/' === $target[0] && ( ! isset( $target[1] ) || '/' !== $target[1] ) ) {
			return home_url( $target );
		}

		return $target;
	}

	private static function same( string $a, string $b ): bool {
		list( $pa, $qa ) = Path::split( $a );
		list( $pb, $qb ) = Path::split( $b );

		return Path::key( $pa, true ) === Path::key( $pb, true ) && Path::query_params( $qa ) === Path::query_params( $qb );
	}
}
