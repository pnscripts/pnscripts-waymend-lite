<?php

namespace PNScripts\WaymendLite\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PNScripts\WaymendLite\Core\Loop_Detector;
use PNScripts\WaymendLite\Core\Rule;
use PNScripts\WaymendLite\Core\Rule_Validator;

class LoopAndValidatorTest extends TestCase {

	const HOME = 'https://example.com';

	private function codes( array $result, string $kind = 'errors' ): array {
		return array_column( $result[ $kind ], 'code' );
	}

	private function check( Rule $rule, array $existing = array() ): array {
		$v = new Rule_Validator( self::HOME );

		return $v->validate( $v->normalize( $rule ), $existing );
	}

	public function test_trace_follows_hops_and_stops_at_external_targets(): void {
		$rules = array(
			new Rule( '/a', '/b', Rule::EXACT, 301, 'ignore', false, true, 1 ),
			new Rule( '/b', 'https://example.com/c', Rule::EXACT, 301, 'ignore', false, true, 2 ),
			new Rule( '/c', 'https://elsewhere.org/', Rule::EXACT, 302, 'ignore', false, true, 3 ),
		);
		$trace = ( new Loop_Detector( self::HOME ) )->trace( '/a', $rules );

		$this->assertFalse( $trace['loop'] );
		$this->assertSame( array( 1, 2, 3 ), array_column( $trace['hops'], 'rule' ) );
	}

	public function test_direct_self_redirect_is_a_loop(): void {
		$this->assertContains( 'loop', $this->codes( $this->check( new Rule( '/same', '/same/' ) ) ) );
		$this->assertContains( 'loop', $this->codes( $this->check( new Rule( '/same', 'https://example.com/SAME' ) ) ) );
	}

	public function test_two_rule_loop(): void {
		$existing = array( new Rule( '/b', '/a', Rule::EXACT, 301, 'ignore', false, true, 7 ) );

		$this->assertContains( 'loop', $this->codes( $this->check( new Rule( '/a', '/b' ), $existing ) ) );
	}

	public function test_three_rule_loop_through_a_prefix(): void {
		$existing = array(
			new Rule( '/b', '/c/x', Rule::EXACT, 301, 'ignore', false, true, 1 ),
			new Rule( '/c', '/a', Rule::PREFIX, 301, 'ignore', false, true, 2 ),
		);

		$this->assertContains( 'loop', $this->codes( $this->check( new Rule( '/a/x', '/b' ), $existing ) ) );
	}

	public function test_self_feeding_prefix_is_a_loop(): void {
		$this->assertContains( 'loop', $this->codes( $this->check( new Rule( '/shop', '/shop/new', Rule::PREFIX ) ) ) );
	}

	public function test_regex_rules_are_refused_in_the_lite_edition(): void {
		$this->assertContains( 'match_invalid', $this->codes( $this->check( new Rule( '^/blog/(.*)$', '/news/$1', Rule::REGEX ) ) ) );
		$this->assertSame( array( Rule::EXACT, Rule::PREFIX ), Rule::MATCH_TYPES );
	}

	public function test_chain_is_a_warning_with_the_final_target(): void {
		$existing = array(
			new Rule( '/b', '/c', Rule::EXACT, 301, 'ignore', false, true, 1 ),
			new Rule( '/c', '/d', Rule::EXACT, 301, 'ignore', false, true, 2 ),
		);
		$result = $this->check( new Rule( '/a', '/b' ), $existing );

		$this->assertSame( array(), $this->codes( $result ) );
		$this->assertSame( array( 'chain' ), $this->codes( $result, 'warnings' ) );
		$this->assertSame( '/d', $result['warnings'][0]['detail'] );
	}

	public function test_editing_a_rule_replaces_its_old_version_in_the_check(): void {
		$existing = array( new Rule( '/a', '/a', Rule::EXACT, 301, 'ignore', false, true, 5 ) );
		$fixed    = new Rule( '/a', '/z', Rule::EXACT, 301, 'ignore', false, true, 5 );

		$this->assertSame( array(), $this->codes( $this->check( $fixed, $existing ) ) );
	}

	public function test_gone_and_disabled_rules_cannot_loop(): void {
		$this->assertSame( array(), $this->codes( $this->check( new Rule( '/same', '/same', Rule::EXACT, 410 ) ) ) );
		$this->assertSame( array(), $this->codes( $this->check( new Rule( '/same', '/same', Rule::EXACT, 301, 'ignore', false, false ) ) ) );
	}

	public function test_normalize(): void {
		$v    = new Rule_Validator( self::HOME );
		$rule = $v->normalize( new Rule( '  https://example.com/old?x=1 ', 'new-page', ' EXACT ', 301, ' PASS ' ) );

		$this->assertSame( '/old?x=1', $rule->source );
		$this->assertSame( '/new-page', $rule->target );
		$this->assertSame( 'exact', $rule->match_type );
		$this->assertSame( 'pass', $rule->query_mode );
		$this->assertSame( '/relative', $v->normalize( new Rule( 'relative', '/x' ) )->source );
		$this->assertSame( '', $v->normalize( new Rule( '/gone', '/ignored', 'exact', 410 ) )->target );
		$this->assertSame( 'mailto:a@example.com', $v->normalize( new Rule( '/m', 'mailto:a@example.com' ) )->target );
	}

	public function test_field_errors(): void {
		$this->assertContains( 'source_empty', $this->codes( $this->check( new Rule( '', '/x' ) ) ) );
		$this->assertContains( 'target_empty', $this->codes( $this->check( new Rule( '/a', '' ) ) ) );
		$this->assertContains( 'status_invalid', $this->codes( $this->check( new Rule( '/a', '/b', 'exact', 303 ) ) ) );
		$this->assertContains( 'match_invalid', $this->codes( $this->check( new Rule( '/a', '/b', 'glob' ) ) ) );
		$this->assertContains( 'regex_invalid', $this->codes( $this->check( new Rule( '^/(x', '/b', 'regex' ) ) ) );
		$this->assertContains( 'target_scheme', $this->codes( $this->check( new Rule( '/a', 'javascript:alert(1)' ) ) ) );
		$this->assertContains( 'source_external', $this->codes( $this->check( new Rule( 'https://other.org/a', '/b' ) ) ) );
	}

	public function test_warnings(): void {
		$this->assertContains( 'query_exact_without_query', $this->codes( $this->check( new Rule( '/a', '/b', 'exact', 301, 'exact' ) ), 'warnings' ) );
		$this->assertContains( 'captures_without_regex', $this->codes( $this->check( new Rule( '/a', '/b/$1' ) ), 'warnings' ) );
	}

	public function test_duplicates(): void {
		$existing = array( new Rule( '/a', '/b', Rule::EXACT, 301, 'ignore', false, true, 9 ) );

		$this->assertContains( 'duplicate', $this->codes( $this->check( new Rule( '/A/', '/c' ), $existing ) ) );
		$this->assertNotContains( 'duplicate', $this->codes( $this->check( new Rule( '/a', '/c', Rule::PREFIX ), $existing ) ) );
	}
}
