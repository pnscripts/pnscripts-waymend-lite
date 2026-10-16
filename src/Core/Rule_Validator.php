<?php
/**
 * Rule clean-up and validation. Plain PHP, no WordPress dependency.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\Core;

/**
 * Normalises user input into a rule and reports problems as codes, so the
 * WordPress layer can translate the messages.
 */
final class Rule_Validator {

	/** @var string */
	private $home_url;

	public function __construct( string $home_url ) {
		$this->home_url = $home_url;
	}

	/**
	 * Trims fields, turns same-site absolute URLs into paths and adds missing leading slashes.
	 */
	public function normalize( Rule $rule ): Rule {
		$rule             = clone $rule;
		$rule->source     = trim( $rule->source );
		$rule->target     = trim( $rule->target );
		$rule->match_type = strtolower( trim( $rule->match_type ) );
		$rule->query_mode = strtolower( trim( $rule->query_mode ) );

		if ( Rule::REGEX !== $rule->match_type && '' !== $rule->source ) {
			if ( preg_match( '#^(https?:)?//#i', $rule->source ) ) {
				$local = Path::local( $rule->source, $this->home_url );
				if ( null !== $local ) {
					$rule->source = $local;
				}
			} elseif ( '/' !== $rule->source[0] ) {
				$rule->source = '/' . $rule->source;
			}
		}

		if ( '' !== $rule->target && ! preg_match( '#^([a-z][a-z0-9+.\-]*:|/)#i', $rule->target ) ) {
			$rule->target = '/' . $rule->target;
		}
		if ( $rule->is_gone() ) {
			$rule->target = '';
		}

		return $rule;
	}

	/**
	 * Problems with a (normalised) rule.
	 *
	 * @param list<Rule> $existing Other rules, for duplicates and loop checks.
	 * @return array{errors: list<array{code: string, detail: string}>, warnings: list<array{code: string, detail: string}>}
	 */
	public function validate( Rule $rule, array $existing = array() ): array {
		$errors   = array();
		$warnings = array();

		if ( ! in_array( $rule->match_type, Rule::MATCH_TYPES, true ) ) {
			$errors[] = array( 'code' => 'match_invalid', 'detail' => $rule->match_type );
		}
		if ( ! in_array( $rule->status, Rule::STATUSES, true ) ) {
			$errors[] = array( 'code' => 'status_invalid', 'detail' => (string) $rule->status );
		}
		if ( ! in_array( $rule->query_mode, Rule::QUERY_MODES, true ) ) {
			$errors[] = array( 'code' => 'query_invalid', 'detail' => $rule->query_mode );
		}

		if ( '' === $rule->source ) {
			$errors[] = array( 'code' => 'source_empty', 'detail' => '' );
		} elseif ( Rule::REGEX === $rule->match_type ) {
			$error = Regex::error( $rule->source );
			if ( null !== $error ) {
				$errors[] = array( 'code' => 'regex_invalid', 'detail' => $error );
			}
		} elseif ( '/' !== $rule->source[0] ) {
			$errors[] = array( 'code' => 'source_external', 'detail' => $rule->source );
		}

		if ( ! $rule->is_gone() && '' === $rule->target ) {
			$errors[] = array( 'code' => 'target_empty', 'detail' => '' );
		}
		if ( '' !== $rule->target && preg_match( '#^\s*(javascript|data|vbscript):#i', $rule->target ) ) {
			$errors[] = array( 'code' => 'target_scheme', 'detail' => $rule->target );
		}

		if ( Rule::QUERY_EXACT === $rule->query_mode && Rule::EXACT === $rule->match_type && false === strpos( $rule->source, '?' ) ) {
			$warnings[] = array( 'code' => 'query_exact_without_query', 'detail' => '' );
		}
		if ( Rule::REGEX !== $rule->match_type && Regex::has_captures( $rule->target ) ) {
			$warnings[] = array( 'code' => 'captures_without_regex', 'detail' => $rule->target );
		}

		if ( $errors ) {
			return array( 'errors' => $errors, 'warnings' => $warnings );
		}

		foreach ( $existing as $other ) {
			if ( $other->id !== $rule->id && $other->match_type === $rule->match_type && $this->same_source( $other, $rule ) ) {
				$errors[] = array( 'code' => 'duplicate', 'detail' => (string) $other->id );
				break;
			}
		}

		$check = ( new Loop_Detector( $this->home_url ) )->check( $rule, $existing );
		if ( $check['loop'] ) {
			$errors[] = array( 'code' => 'loop', 'detail' => '' );
		} elseif ( $check['chain'] ) {
			$warnings[] = array( 'code' => 'chain', 'detail' => (string) end( $check['chain'] ) );
		}

		return array( 'errors' => $errors, 'warnings' => $warnings );
	}

	private function same_source( Rule $a, Rule $b ): bool {
		if ( Rule::REGEX === $a->match_type ) {
			return $a->source === $b->source;
		}
		list( $pa, $qa ) = Path::split( $a->source );
		list( $pb, $qb ) = Path::split( $b->source );
		$case            = $a->case_sensitive && $b->case_sensitive;

		return Path::key( $pa, $case ) === Path::key( $pb, $case )
			&& ( Rule::QUERY_EXACT !== $a->query_mode || Path::query_params( $qa ) === Path::query_params( $qb ) );
	}
}
