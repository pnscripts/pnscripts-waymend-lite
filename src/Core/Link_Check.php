<?php
/**
 * "Will my old links still work?": checks a list of old URLs against the rules
 * and the pages they end on. Plain PHP; the page check is passed in.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\Core;

/**
 * Classifies old URLs the way a visitor would experience them on this site.
 */
final class Link_Check {

	const MAX_URLS = 500;

	const OK       = 'ok';        // No rule needed: the page answers 2xx.
	const REDIRECT = 'redirect';  // A rule sends visitors to a page that answers 2xx.
	const GONE     = 'gone';      // A 410 rule says it is gone on purpose.
	const MISSING  = 'missing';   // No rule and the page answers 404: needs a rule.
	const BROKEN   = 'broken';    // A rule sends visitors to a page that answers 404.
	const LOOP     = 'loop';      // Rules send visitors around in circles.
	const EXTERNAL = 'external';  // A rule sends visitors to another site (not checked).
	const UNKNOWN  = 'unknown';   // The page could not be checked.

	/** @var string */
	private $home_url;

	public function __construct( string $home_url ) {
		$this->home_url = $home_url;
	}

	/**
	 * Old URLs, one per line: full URLs on any host (the old domain is fine: the path is
	 * checked on this site) or site-relative paths. Duplicates and blank lines are dropped.
	 *
	 * @return array{uris: list<string>, skipped: list<string>, truncated: bool}
	 */
	public function parse_list( string $text ): array {
		$uris    = array();
		$skipped = array();
		$lines   = preg_split( '/\r\n|\r|\n/', $text );
		foreach ( false === $lines ? array() : $lines as $line ) {
			$line = trim( $line, " \t\"'" );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$uri = $this->to_uri( $line );
			if ( null === $uri ) {
				$skipped[] = $line;
				continue;
			}
			$uris[ $uri ] = true;
		}
		$list = array_keys( $uris );

		return array(
			'uris'      => array_slice( $list, 0, self::MAX_URLS ),
			'skipped'   => $skipped,
			'truncated' => count( $list ) > self::MAX_URLS,
		);
	}

	/**
	 * @param list<Rule>                $rules     Enabled rules.
	 * @param callable(string): ?int    $status_of HTTP status of a site-relative URI on this site (no redirects followed), null when unknown.
	 * @return array{uri: string, verdict: string, final: string, status: ?int, hops: int}
	 */
	public function check( string $uri, array $rules, callable $status_of ): array {
		$trace = ( new Loop_Detector( $this->home_url ) )->trace( $uri, $rules );
		$hops  = count( $trace['hops'] );
		if ( $trace['loop'] ) {
			return $this->result( $uri, self::LOOP, $uri, null, $hops );
		}

		$final = $hops ? (string) $trace['hops'][ $hops - 1 ]['to'] : $uri;
		$local = $hops ? Path::local( $final, $this->home_url ) : $uri;

		// The trace stops before a 410 rule: if the last address is answered by one, it is gone on purpose.
		if ( null !== $local ) {
			$last = ( new Matcher( $rules ) )->match( $local );
			if ( null !== $last && $last->rule->is_gone() ) {
				return $this->result( $uri, self::GONE, $local, 410, $hops );
			}
		}
		if ( null === $local ) {
			return $this->result( $uri, self::EXTERNAL, $final, null, $hops );
		}

		$status = $status_of( $local );
		if ( null === $status ) {
			$verdict = self::UNKNOWN;
		} elseif ( $status >= 200 && $status < 400 ) {
			$verdict = $hops ? self::REDIRECT : self::OK;
		} elseif ( 410 === $status ) {
			$verdict = self::GONE;
		} elseif ( $status >= 400 && $status < 500 ) {
			$verdict = $hops ? self::BROKEN : self::MISSING;
		} else {
			$verdict = self::UNKNOWN;
		}

		return $this->result( $uri, $verdict, $local, $status, $hops );
	}

	/**
	 * The URLs that need a rule as importer CSV (source, target): missing ones with a suggested
	 * target where one was found, broken ones with their current target left for review.
	 *
	 * @param list<array{uri: string, verdict: string, final: string, suggestion?: string}> $results
	 */
	public static function needs_rules_csv( array $results ): string {
		$rows = array( array( 'source', 'target' ) );
		foreach ( $results as $r ) {
			if ( self::MISSING === $r['verdict'] || self::BROKEN === $r['verdict'] ) {
				$rows[] = array( $r['uri'], (string) ( $r['suggestion'] ?? '' ) );
			}
		}
		$out = '';
		foreach ( $rows as $row ) {
			$out .= implode( ',', array_map( array( self::class, 'csv_cell' ), $row ) ) . "\r\n";
		}

		return $out;
	}

	private static function csv_cell( string $value ): string {
		// Defuse spreadsheet formulas, then quote when needed.
		if ( '' !== $value && false !== strpos( '=+-@', $value[0] ) ) {
			$value = "'" . $value;
		}

		return preg_match( '/[",\r\n]/', $value ) ? '"' . str_replace( '"', '""', $value ) . '"' : $value;
	}

	/**
	 * A line as a site-relative URI (path and query), or null when it is not a URL.
	 */
	private function to_uri( string $line ): ?string {
		if ( '/' === $line[0] && ( ! isset( $line[1] ) || '/' !== $line[1] ) ) {
			$uri = $line;
		} else {
			if ( preg_match( '#^[a-z][a-z0-9+-]*:#i', $line ) && ! preg_match( '#^https?://#i', $line ) ) {
				return null; // mailto:, tel:, javascript: …
			}
			$parts = parse_url( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $line ) ? $line : 'http://' . ltrim( $line, '/' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- plain PHP core class.
			if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! preg_match( '/^[a-z0-9.-]+$/i', (string) $parts['host'] ) || false === strpos( (string) $parts['host'], '.' ) ) {
				return null;
			}
			$scheme = strtolower( (string) ( $parts['scheme'] ?? 'http' ) );
			if ( 'http' !== $scheme && 'https' !== $scheme ) {
				return null;
			}
			$uri = ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . $parts['query'] : '' );
		}
		// A site in a subfolder: "/blog/old" on https://example.com/blog/ is "/old" here.
		$base = rtrim( (string) parse_url( $this->home_url, PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- plain PHP core class.
		if ( '' !== $base && 0 === strpos( $uri, $base . '/' ) ) {
			$uri = substr( $uri, strlen( $base ) );
		}

		return '' === $uri ? '/' : $uri;
	}

	/**
	 * @return array{uri: string, verdict: string, final: string, status: ?int, hops: int}
	 */
	private function result( string $uri, string $verdict, string $final, ?int $status, int $hops ): array {
		return array(
			'uri'     => $uri,
			'verdict' => $verdict,
			'final'   => $final,
			'status'  => $status,
			'hops'    => $hops,
		);
	}
}
