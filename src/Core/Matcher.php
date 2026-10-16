<?php
/**
 * Request matching. Plain PHP, no WordPress dependency.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\Core;

/**
 * Finds the rule for a request path. Order: exact rules, then prefix rules (longest
 * source first), then regular expressions in their position order. Disabled rules
 * are ignored.
 */
final class Matcher {

	/** @var array<string, list<Rule>> Exact rules by comparison key. */
	private $exact = array();
	/** @var list<Rule> */
	private $prefix = array();
	/** @var list<Rule> */
	private $regex = array();

	/**
	 * @param iterable<Rule> $rules
	 */
	public function __construct( iterable $rules ) {
		foreach ( $rules as $rule ) {
			if ( ! $rule->enabled ) {
				continue;
			}
			if ( Rule::EXACT === $rule->match_type ) {
				list( $path )                   = Path::split( $rule->source );
				$this->exact[ Path::key( $path, $rule->case_sensitive ) ][] = $rule;
			} elseif ( Rule::PREFIX === $rule->match_type ) {
				$this->prefix[] = $rule;
			} elseif ( Rule::REGEX === $rule->match_type ) {
				$this->regex[] = $rule;
			}
		}

		usort(
			$this->prefix,
			static function ( Rule $a, Rule $b ) {
				return strlen( $b->source ) <=> strlen( $a->source ) ?: $a->id <=> $b->id;
			}
		);
		usort(
			$this->regex,
			static function ( Rule $a, Rule $b ) {
				return $a->position <=> $b->position ?: $a->id <=> $b->id;
			}
		);
	}

	/**
	 * @param string $uri Site-relative request URI, for example "/old-page/?ref=mail".
	 */
	public function match( string $uri ): ?Match_Result {
		list( $path, $query ) = Path::split( $uri );

		foreach ( array( false, true ) as $case_sensitive ) {
			$key = Path::key( $path, $case_sensitive );
			foreach ( $this->exact[ $key ] ?? array() as $rule ) {
				if ( $rule->case_sensitive === $case_sensitive && $this->query_matches( $rule, $query ) ) {
					return new Match_Result( $rule, $this->finish( $rule, $rule->target, $query ) );
				}
			}
		}

		foreach ( $this->prefix as $rule ) {
			$rest = $this->prefix_rest( $rule, $path );
			if ( null !== $rest ) {
				$target = $rule->is_gone() ? '' : self::join( $rule->target, $rest );
				return new Match_Result( $rule, $this->finish( $rule, $target, $query ) );
			}
		}

		foreach ( $this->regex as $rule ) {
			$subject = Rule::QUERY_EXACT === $rule->query_mode && '' !== $query ? $path . '?' . $query : $path;
			$groups  = Regex::match( $rule->source, $subject, $rule->case_sensitive );
			if ( null !== $groups ) {
				$target = $rule->is_gone() ? '' : Regex::substitute( $rule->target, $groups );
				return new Match_Result( $rule, $this->finish( $rule, $target, $query ) );
			}
		}

		return null;
	}

	/**
	 * The part of $path after a prefix rule's source, or null when it does not match.
	 * Matches whole path segments only: "/shop" matches "/shop" and "/shop/hats", not "/shopping".
	 */
	private function prefix_rest( Rule $rule, string $path ): ?string {
		list( $source ) = Path::split( $rule->source );
		$source         = Path::key( $source, $rule->case_sensitive );
		$compare        = $rule->case_sensitive ? $path : Path::lower( $path );

		if ( '/' === $source ) {
			return $path;
		}
		if ( Path::key( $compare, true ) === $source ) {
			return substr( $path, strlen( $source ) );
		}
		if ( 0 === strpos( $compare, $source . '/' ) ) {
			return substr( $path, strlen( $source ) );
		}

		return null;
	}

	private static function join( string $target, string $rest ): string {
		if ( '' === $rest || '/' === $rest ) {
			return $target . ( '/' === $rest && '/' !== substr( $target, -1 ) ? '/' : '' );
		}
		$query = '';
		$pos   = strpos( $target, '?' );
		if ( false !== $pos ) {
			$query  = substr( $target, $pos );
			$target = substr( $target, 0, $pos );
		}

		return rtrim( $target, '/' ) . '/' . ltrim( $rest, '/' ) . $query;
	}

	private function query_matches( Rule $rule, string $query ): bool {
		if ( Rule::QUERY_EXACT !== $rule->query_mode ) {
			return true;
		}
		list( , $source_query ) = Path::split( $rule->source );

		return Path::query_params( $source_query ) === Path::query_params( $query );
	}

	private function finish( Rule $rule, string $target, string $query ): string {
		if ( $rule->is_gone() ) {
			return '';
		}

		return Rule::QUERY_PASS === $rule->query_mode ? Path::with_query( $target, $query ) : $target;
	}
}
