<?php

namespace PNScripts\WaymendLite\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PNScripts\WaymendLite\Core\Csv;
use PNScripts\WaymendLite\Core\Loop_Detector;
use PNScripts\WaymendLite\Core\Matcher;
use PNScripts\WaymendLite\Core\Path;
use PNScripts\WaymendLite\Core\Regex;
use PNScripts\WaymendLite\Core\Rule;

class EdgeCasesTest extends TestCase {

	public function test_tab_separated_import(): void {
		$import = Csv::import( "source\ttarget\tstatus\n/a\t/b\t302\n" );

		$this->assertSame( array(), $import['errors'] );
		$this->assertSame( 302, $import['rules'][0]['rule']->status );
	}

	public function test_import_flags_and_hits(): void {
		$import = Csv::import( "source,target,case_sensitive,enabled,hits,last_hit\n/A,/b,yes,no,17,2026-01-02 03:04:05\n/c,/d,1,1,abc,yesterday\n" );
		$first  = $import['rules'][0]['rule'];
		$second = $import['rules'][1]['rule'];

		$this->assertTrue( $first->case_sensitive );
		$this->assertFalse( $first->enabled );
		$this->assertSame( 17, $first->hits );
		$this->assertSame( '2026-01-02 03:04:05', $first->last_hit );
		$this->assertSame( 0, $second->hits, 'non-numeric hits are ignored' );
		$this->assertNull( $second->last_hit, 'malformed dates are ignored' );
	}

	public function test_empty_and_header_only_files(): void {
		$this->assertSame( array( 'rules' => array(), 'errors' => array() ), Csv::import( '' ) );
		$this->assertSame( array( 'rules' => array(), 'errors' => array() ), Csv::import( "source,target\n" ) );
	}

	public function test_prefix_with_query_in_target_and_pass_mode(): void {
		$rules = array( new Rule( '/old', '/new?from=old', Rule::PREFIX, 301, Rule::QUERY_PASS ) );
		$match = ( new Matcher( $rules ) )->match( '/old/a/b?x=1' );

		$this->assertSame( '/new/a/b?from=old&x=1', $match->target );
	}

	public function test_regex_pass_mode_and_fragment(): void {
		$rules = array( new Rule( '^/p/(\d+)$', '/post/$1#comments', Rule::REGEX, 301, Rule::QUERY_PASS ) );

		$this->assertSame( '/post/9?a=1#comments', ( new Matcher( $rules ) )->match( '/p/9?a=1' )->target );
	}

	public function test_case_sensitive_and_insensitive_rules_with_the_same_path(): void {
		$rules = array(
			new Rule( '/Docs', '/upper', Rule::EXACT, 301, Rule::QUERY_IGNORE, true, true, 1 ),
			new Rule( '/docs', '/any', Rule::EXACT, 301, Rule::QUERY_IGNORE, false, true, 2 ),
		);
		$matcher = new Matcher( $rules );

		$this->assertSame( '/any', $matcher->match( '/DOCS' )->target );
		$this->assertSame( '/any', $matcher->match( '/Docs' )->target, 'case-insensitive rules are tried first' );
	}

	public function test_root_rules(): void {
		$this->assertSame( '/home', ( new Matcher( array( new Rule( '/', '/home' ) ) ) )->match( '/' )->target );
		$this->assertNull( ( new Matcher( array( new Rule( '/', '/home' ) ) ) )->match( '/x' ) );
		$this->assertSame( '/', Path::key( '///' ) );
	}

	public function test_too_long_chain_is_reported_as_a_loop(): void {
		$rules = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$rules[] = new Rule( "/hop$i", '/hop' . ( $i + 1 ), Rule::EXACT, 301, Rule::QUERY_IGNORE, false, true, $i + 1 );
		}
		$trace = ( new Loop_Detector( 'https://example.com' ) )->trace( '/hop0', $rules );

		$this->assertTrue( $trace['loop'] );
		$this->assertCount( Loop_Detector::MAX_HOPS, $trace['hops'] );
	}

	public function test_query_variants_do_not_count_as_a_loop(): void {
		$rules = array( new Rule( '/a?x=1', '/a?x=2', Rule::EXACT, 301, Rule::QUERY_EXACT, false, true, 1 ) );
		$trace = ( new Loop_Detector( 'https://example.com' ) )->trace( '/a?x=1', $rules );

		$this->assertFalse( $trace['loop'] );
		$this->assertCount( 1, $trace['hops'] );
	}

	public function test_rule_array_round_trip(): void {
		$rule = new Rule( '^/x$', '/y', Rule::REGEX, 307, Rule::QUERY_PASS, true, false, 4, 9, '2026-10-10 10:00:00', 5 );

		$this->assertEquals( $rule, Rule::from_array( $rule->to_array() ) );
		$this->assertTrue( Rule::from_array( array( 'source' => '/a' ) )->enabled, 'enabled by default' );
	}

	public function test_has_captures(): void {
		$this->assertTrue( Regex::has_captures( '/a/$1' ) );
		$this->assertTrue( Regex::has_captures( '/a/${12}' ) );
		$this->assertFalse( Regex::has_captures( '/price-$' ) );
	}

	public function test_catastrophic_pattern_does_not_hang_or_throw(): void {
		$rules = array( new Rule( '^/(a+)+$', '/x', Rule::REGEX ) );
		$start = microtime( true );
		$result = ( new Matcher( $rules ) )->match( '/' . str_repeat( 'a', 5000 ) . 'b' );

		$this->assertNull( $result );
		$this->assertLessThan( 5, microtime( true ) - $start );
	}
}
