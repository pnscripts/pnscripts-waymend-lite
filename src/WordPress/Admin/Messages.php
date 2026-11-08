<?php
/**
 * Translatable texts for validation codes and admin notices.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\WordPress\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the codes from Rule_Validator into translated sentences, and keeps notices
 * between a form post and the page it redirects to.
 */
final class Messages {

	/**
	 * @param array{code: string, detail: string} $problem
	 */
	public static function problem( array $problem ): string {
		$detail = $problem['detail'];
		switch ( $problem['code'] ) {
			case 'source_empty':
				return __( 'Enter the old URL (source).', 'pnscripts-waymend-lite' );
			case 'source_external':
				return __( 'The source must be a path on this site, such as /old-page.', 'pnscripts-waymend-lite' );
			case 'target_empty':
				return __( 'Enter the target URL, or choose 410 Gone.', 'pnscripts-waymend-lite' );
			case 'target_scheme':
				return __( 'The target must be a path or an http(s) URL.', 'pnscripts-waymend-lite' );
			case 'status_invalid':
				return __( 'Choose 301, 302, 307 or 410.', 'pnscripts-waymend-lite' );
			case 'match_invalid':
				return __( 'Choose exact or prefix matching.', 'pnscripts-waymend-lite' );
			case 'query_invalid':
				return __( 'Choose how the query string is handled.', 'pnscripts-waymend-lite' );
			case 'regex_invalid':
				/* translators: %s: the PCRE error message. */
				return sprintf( __( 'The regular expression is not valid: %s', 'pnscripts-waymend-lite' ), $detail );
			case 'duplicate':
				/* translators: %s: id of the existing rule. */
				return sprintf( __( 'Rule #%s already uses this source with the same match type.', 'pnscripts-waymend-lite' ), $detail );
			case 'loop':
				return __( 'This rule would create a redirect loop: following it brings the visitor back to a URL already visited.', 'pnscripts-waymend-lite' );
			case 'chain':
				/* translators: %s: final URL of the redirect chain. */
				return sprintf( __( 'The target is redirected again; visitors end up at %s. Consider pointing this rule there directly.', 'pnscripts-waymend-lite' ), $detail );
			case 'query_exact_without_query':
				return __( 'Query mode "exact" is selected but the source has no query string, so only requests without one will match.', 'pnscripts-waymend-lite' );
			case 'captures_without_regex':
				return __( 'The target contains $1-style placeholders, which the Lite edition does not replace.', 'pnscripts-waymend-lite' );
		}

		return $problem['code'];
	}

	/**
	 * @param 'success'|'error'|'warning'|'info' $type
	 */
	public static function flash( string $type, string $text ): void {
		$key            = 'waymend_lite_notices_' . get_current_user_id();
		$notices        = get_transient( $key );
		$notices        = is_array( $notices ) ? $notices : array();
		$notices[]      = array( 'type' => $type, 'text' => $text );
		set_transient( $key, $notices, 5 * MINUTE_IN_SECONDS );
	}

	public static function render_flashed(): void {
		$key     = 'waymend_lite_notices_' . get_current_user_id();
		$notices = get_transient( $key );
		if ( ! is_array( $notices ) ) {
			return;
		}
		delete_transient( $key );
		foreach ( $notices as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $notice['type'] ),
				esc_html( $notice['text'] )
			);
		}
	}
}
