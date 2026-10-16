<?php

namespace PNScripts\WaymendLite\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PNScripts\WaymendLite\Core\Matcher;
use PNScripts\WaymendLite\Core\Rule;

class MatcherTest extends TestCase {

	private function target( array $rules, string $uri ): ?string {
		$result = ( new Matcher( $rules ) )->match( $uri );

		return null === $result ? null : $result->status . ' ' . $result->target;
	}

	public function test_exact_match_ignores_trailing_slash_and_case(): void {
		$rules = array( new Rule( '/Old-Page', '/new-page' ) );

		$this->assertSame( '301 /new-page', $this->target( $rules, '/old-page' ) );
		$this->assertSame( '301 /new-page', $this->target( $rules, '/OLD-PAGE/' ) );
		$this->assertNull( $this->target( $rules, '/old-page-2' ) );
		$this->assertNull( $this->target( $rules, '/old' ) );
	}

	public function test_case_sensitive_rule(): void {
		$rules = array( new Rule( '/Docs', '/manual', Rule::EXACT, 301, Rule::QUERY_IGNORE, true ) );

		$this->assertSame( '301 /manual', $this->target( $rules, '/Docs' ) );
		$this->assertNull( $this->target( $rules, '/docs' ) );
	}

	public function test_url_encoded_and_unicode_paths(): void {
		$rules = array( new Rule( '/продукти/чай', '/shop/tea' ) );

		$this->assertSame( '301 /shop/tea', $this->target( $rules, '/%D0%BF%D1%80%D0%BE%D0%B4%D1%83%D0%BA%D1%82%D0%B8/%D1%87%D0%B0%D0%B9' ) );
		$this->assertSame( '301 /shop/tea', $this->target( $rules, '/ПРОДУКТИ/Чай/' ) );
	}

	public function test_query_modes(): void {
		$ignore = array( new Rule( '/a', '/b' ) );
		$pass   = array( new Rule( '/a', '/b?lang=bg', Rule::EXACT, 301, Rule::QUERY_PASS ) );
		$exact  = array( new Rule( '/a?id=7&x=1', '/seven', Rule::EXACT, 301, Rule::QUERY_EXACT ) );

		$this->assertSame( '301 /b', $this->target( $ignore, '/a?utm_source=mail' ) );
		$this->assertSame( '301 /b?lang=bg&utm_source=mail', $this->target( $pass, '/a?utm_source=mail' ) );
		$this->assertSame( '301 /b?lang=bg', $this->target( $pass, '/a' ) );
		$this->assertSame( '301 /seven', $this->target( $exact, '/a?x=1&id=7' ) );
		$this->assertNull( $this->target( $exact, '/a?id=8&x=1' ) );
		$this->assertNull( $this->target( $exact, '/a' ) );
	}

	public function test_prefix_matches_whole_segments_and_appends_the_rest(): void {
		$rules = array( new Rule( '/old-shop/', '/shop', Rule::PREFIX ) );

		$this->assertSame( '301 /shop/hats/red', $this->target( $rules, '/old-shop/hats/red' ) );
		$this->assertSame( '301 /shop/hats/', $this->target( $rules, '/old-shop/hats/' ) );
		$this->assertSame( '301 /shop', $this->target( $rules, '/old-shop' ) );
		$this->assertSame( '301 /shop/', $this->target( $rules, '/old-shop/' ) );
		$this->assertNull( $this->target( $rules, '/old-shopping' ) );
	}

	public function test_prefix_to_external_domain_and_root(): void {
		$rules = array(
			new Rule( '/forum', 'https://community.example.org/', Rule::PREFIX, 302 ),
			new Rule( '/', '/', Rule::PREFIX ),
		);

		$this->assertSame( '302 https://community.example.org/t/42', $this->target( $rules, '/forum/t/42' ) );
	}

	public function test_longest_prefix_wins_and_exact_beats_prefix(): void {
		$rules = array(
			new Rule( '/docs', '/manual', Rule::PREFIX, 301, Rule::QUERY_IGNORE, false, true, 1 ),
			new Rule( '/docs/api', '/developers', Rule::PREFIX, 301, Rule::QUERY_IGNORE, false, true, 2 ),
			new Rule( '/docs/api/v1', '/developers/legacy', Rule::EXACT, 302, Rule::QUERY_IGNORE, false, true, 3 ),
		);

		$this->assertSame( '301 /manual/install', $this->target( $rules, '/docs/install' ) );
		$this->assertSame( '301 /developers/v2', $this->target( $rules, '/docs/api/v2' ) );
		$this->assertSame( '302 /developers/legacy', $this->target( $rules, '/docs/api/v1/' ) );
	}

	public function test_regex_with_capture_groups(): void {
		$rules = array( new Rule( '^/blog/(\d{4})/(\d{2})/([^/]+)/?$', '/news/$3?year=$1&month=${2}', Rule::REGEX ) );

		$this->assertSame( '301 /news/hello-world?year=2019&month=04', $this->target( $rules, '/blog/2019/04/hello-world/' ) );
		$this->assertNull( $this->target( $rules, '/blog/19/04/hello' ) );
	}

	public function test_regex_order_follows_position(): void {
		$rules = array(
			new Rule( '^/p/(.*)$', '/second/$1', Rule::REGEX, 301, Rule::QUERY_IGNORE, false, true, 1, 0, null, 20 ),
			new Rule( '^/p/(\d+)$', '/first/$1', Rule::REGEX, 301, Rule::QUERY_IGNORE, false, true, 2, 0, null, 10 ),
		);

		$this->assertSame( '301 /first/5', $this->target( $rules, '/p/5' ) );
		$this->assertSame( '301 /second/x', $this->target( $rules, '/p/x' ) );
	}

	public function test_regex_is_case_insensitive_unless_asked(): void {
		$insensitive = array( new Rule( '^/Promo/(.+)$', '/offers/$1', Rule::REGEX ) );
		$sensitive   = array( new Rule( '^/Promo/(.+)$', '/offers/$1', Rule::REGEX, 301, Rule::QUERY_IGNORE, true ) );

		$this->assertSame( '301 /offers/x', $this->target( $insensitive, '/promo/x' ) );
		$this->assertNull( $this->target( $sensitive, '/promo/x' ) );
	}

	public function test_regex_can_match_the_query_string_in_exact_query_mode(): void {
		$rules = array( new Rule( '^/index\.php\?p=(\d+)$', '/archive/$1', Rule::REGEX, 301, Rule::QUERY_EXACT ) );

		$this->assertSame( '301 /archive/77', $this->target( $rules, '/index.php?p=77' ) );
	}

	public function test_broken_regex_never_matches_and_never_throws(): void {
		$rules = array( new Rule( '^/(unclosed', '/x', Rule::REGEX ), new Rule( '/ok', '/fine' ) );

		$this->assertNull( $this->target( $rules, '/(unclosed' ) );
		$this->assertSame( '301 /fine', $this->target( $rules, '/ok' ) );
	}

	public function test_gone_rules_and_disabled_rules(): void {
		$rules = array(
			new Rule( '/discontinued', '', Rule::EXACT, 410 ),
			new Rule( '/old-range', '/ignored', Rule::PREFIX, 410 ),
			new Rule( '/off', '/x', Rule::EXACT, 301, Rule::QUERY_IGNORE, false, false ),
		);

		$this->assertSame( '410 ', $this->target( $rules, '/discontinued' ) );
		$this->assertSame( '410 ', $this->target( $rules, '/old-range/item' ) );
		$this->assertNull( $this->target( $rules, '/off' ) );
	}

	public function test_hash_and_double_slashes(): void {
		$rules = array( new Rule( '/a/b', '/c' ) );

		$this->assertSame( '301 /c', $this->target( $rules, '//a//b#top' ) );
	}
}
