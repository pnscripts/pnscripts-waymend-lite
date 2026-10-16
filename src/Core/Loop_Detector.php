<?php
/**
 * Redirect loop and chain detection. Plain PHP, no WordPress dependency.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\Core;

/**
 * Follows local redirects hop by hop, the way a browser would, to find loops
 * (a path seen twice, or a chain that keeps growing) and chains (A to B to C).
 */
final class Loop_Detector {

	const MAX_HOPS = 10;

	/** @var string */
	private $home_url;

	public function __construct( string $home_url ) {
		$this->home_url = $home_url;
	}

	/**
	 * @param string     $start Site-relative URI to start from.
	 * @param list<Rule> $rules
	 * @return array{loop: bool, hops: list<array{from: string, to: string, rule: int, status: int}>}
	 */
	public function trace( string $start, array $rules ): array {
		$matcher = new Matcher( $rules );
		$seen    = array( $this->seen_key( $start ) => true );
		$hops    = array();
		$current = $start;

		for ( $i = 0; $i < self::MAX_HOPS; $i++ ) {
			$result = $matcher->match( $current );
			if ( null === $result || $result->rule->is_gone() ) {
				return array( 'loop' => false, 'hops' => $hops );
			}

			$hops[] = array( 'from' => $current, 'to' => $result->target, 'rule' => $result->rule->id, 'status' => $result->status );
			$next   = Path::local( $result->target, $this->home_url );
			if ( null === $next ) {
				return array( 'loop' => false, 'hops' => $hops );
			}

			$key = $this->seen_key( $next );
			if ( isset( $seen[ $key ] ) ) {
				return array( 'loop' => true, 'hops' => $hops );
			}
			$seen[ $key ] = true;
			$current      = $next;
		}

		// Ten hops without an end: a prefix or regex rule that keeps feeding itself.
		return array( 'loop' => true, 'hops' => $hops );
	}

	/**
	 * Checks a rule that is about to be saved against the other rules.
	 *
	 * @param list<Rule> $rules Existing rules (a rule with the same id is replaced by $candidate).
	 * @return array{loop: bool, chain: list<string>} chain lists the URIs a visitor is sent on to
	 *         after the candidate's own redirect (empty when the target is final).
	 */
	public function check( Rule $candidate, array $rules ): array {
		$none = array( 'loop' => false, 'chain' => array() );
		if ( $candidate->is_gone() || ! $candidate->enabled ) {
			return $none;
		}

		$candidate = clone $candidate;
		if ( 0 === $candidate->id ) {
			$candidate->id = -1;
		}

		$set = array( $candidate );
		foreach ( $rules as $rule ) {
			if ( $rule->id !== $candidate->id ) {
				$set[] = $rule;
			}
		}

		if ( Rule::REGEX === $candidate->match_type ) {
			// Start where the rule sends visitors (captures filled with a sample value)
			// and see whether the journey comes back to this rule.
			$start = Path::local( Regex::substitute( $candidate->target, array_fill( 0, 100, 'waymend-probe' ) ), $this->home_url );
			if ( null === $start ) {
				return $none;
			}
			$trace = $this->trace( $start, $set );
			$loop  = $trace['loop'];
			foreach ( $trace['hops'] as $hop ) {
				$loop = $loop || $hop['rule'] === $candidate->id;
			}
			$hops = $trace['hops'];
		} else {
			list( $path ) = Path::split( $candidate->source );
			$start        = Rule::PREFIX === $candidate->match_type ? rtrim( $path, '/' ) . '/waymend-probe' : $candidate->source;
			$trace        = $this->trace( $start, $set );
			$loop         = $trace['loop'];
			$hops         = array_slice( $trace['hops'], 1 );
		}

		if ( $loop ) {
			return array( 'loop' => true, 'chain' => array() );
		}

		return array( 'loop' => false, 'chain' => array_column( $hops, 'to' ) );
	}

	private function seen_key( string $uri ): string {
		list( $path, $query ) = Path::split( $uri );

		return Path::key( $path ) . '?' . http_build_query( Path::query_params( $query ) );
	}
}
