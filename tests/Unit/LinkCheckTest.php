<?php

namespace PNScripts\WaymendLite\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PNScripts\WaymendLite\Core\Link_Check;
use PNScripts\WaymendLite\Core\Rule;

class LinkCheckTest extends TestCase {

	/** @return list<Rule> */
	private function rules(): array {
		return array(
			new Rule( '/old-about', '/about/', Rule::EXACT, 301, Rule::QUERY_IGNORE, false, true, 1 ),
			new Rule( '/old-shop', '/shop', Rule::PREFIX, 301, Rule::QUERY_IGNORE, false, true, 2 ),
			new Rule( '/discontinued', '', Rule::EXACT, 410, Rule::QUERY_IGNORE, false, true, 3 ),
			new Rule( '/to-nowhere', '/deleted-page/', Rule::EXACT, 301, Rule::QUERY_IGNORE, false, true, 4 ),
			new Rule( '/a', '/b', Rule::EXACT, 301, Rule::QUERY_IGNORE, false, true, 5 ),
			new Rule( '/b', '/a', Rule::EXACT, 301, Rule::QUERY_IGNORE, false, true, 6 ),
			new Rule( '/partner', 'https://partner.example.org/page', Rule::EXACT, 302, Rule::QUERY_IGNORE, false, true, 7 ),
			new Rule( '/chain-start', '/old-about', Rule::EXACT, 301, Rule::QUERY_IGNORE, false, true, 8 ),
		);
	}

	private function status(): callable {
		$pages = array( '/about/' => 200, '/shop/hats/red' => 200, '/contact/' => 200, '/' => 200 );

		return static function ( string $uri ) use ( $pages ): ?int {
			if ( '/flaky' === $uri ) {
				return null;
			}
			return $pages[ $uri ] ?? 404;
		};
	}

	public function test_verdicts(): void {
		$check = new Link_Check( 'https://example.com/' );
		$cases = array(
			'/contact/'        => array( Link_Check::OK, '/contact/', 0 ),
			'/old-about'       => array( Link_Check::REDIRECT, '/about/', 1 ),
			'/old-shop/hats/red' => array( Link_Check::REDIRECT, '/shop/hats/red', 1 ),
			'/discontinued'    => array( Link_Check::GONE, '/discontinued', 0 ),
			'/never-existed'   => array( Link_Check::MISSING, '/never-existed', 0 ),
			'/to-nowhere'      => array( Link_Check::BROKEN, '/deleted-page/', 1 ),
			'/a'               => array( Link_Check::LOOP, '/a', 2 ),
			'/partner'         => array( Link_Check::EXTERNAL, 'https://partner.example.org/page', 1 ),
			'/chain-start'     => array( Link_Check::REDIRECT, '/about/', 2 ),
			'/flaky'           => array( Link_Check::UNKNOWN, '/flaky', 0 ),
		);
		foreach ( $cases as $uri => list( $verdict, $final, $hops ) ) {
			$r = $check->check( $uri, $this->rules(), $this->status() );
			$this->assertSame( $verdict, $r['verdict'], $uri );
			$this->assertSame( $final, $r['final'], $uri );
			$this->assertSame( $hops, $r['hops'], $uri );
		}
	}

	public function test_parse_list_takes_paths_from_any_host_and_drops_duplicates(): void {
		$check = new Link_Check( 'https://new.example.com/' );
		$list  = $check->parse_list( "https://old-shop.example.net/old-about\r\n\n/old-about\nold-shop.example.net/products?id=7\n# a comment\nhttp://old-shop.example.net\nmailto:x@example.com\nnot a url\n\"https://old.example.net/q?a=1&b=2\"" );
		$this->assertSame( array( '/old-about', '/products?id=7', '/', '/q?a=1&b=2' ), $list['uris'] );
		$this->assertSame( array( 'mailto:x@example.com', 'not a url' ), $list['skipped'] );
		$this->assertFalse( $list['truncated'] );

		$sub = new Link_Check( 'https://example.com/blog/' );
		$this->assertSame( array( '/old-post' ), $sub->parse_list( "https://example.com/blog/old-post\n" )['uris'] );

		$many = $check->parse_list( implode( "\n", array_map( static function ( $i ) {
			return '/p' . $i;
		}, range( 1, Link_Check::MAX_URLS + 5 ) ) ) );
		$this->assertCount( Link_Check::MAX_URLS, $many['uris'] );
		$this->assertTrue( $many['truncated'] );
	}

	public function test_needs_rules_csv_lists_missing_and_broken_ready_for_the_importer(): void {
		$csv = Link_Check::needs_rules_csv(
			array(
				array( 'uri' => '/never-existed', 'verdict' => Link_Check::MISSING, 'final' => '/never-existed', 'suggestion' => '/new-page/' ),
				array( 'uri' => '/old-about', 'verdict' => Link_Check::REDIRECT, 'final' => '/about/' ),
				array( 'uri' => '/to-nowhere', 'verdict' => Link_Check::BROKEN, 'final' => '/deleted-page/' ),
				array( 'uri' => '/q?a=1,2', 'verdict' => Link_Check::MISSING, 'final' => '/q?a=1,2' ),
				array( 'uri' => '=cmd', 'verdict' => Link_Check::MISSING, 'final' => '=cmd' ),
			)
		);
		$this->assertSame( "source,target\r\n/never-existed,/new-page/\r\n/to-nowhere,\r\n\"/q?a=1,2\",\r\n'=cmd,\r\n", $csv );
	}
}
