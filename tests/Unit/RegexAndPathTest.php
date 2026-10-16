<?php

namespace PNScripts\WaymendLite\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PNScripts\WaymendLite\Core\Ignore_List;
use PNScripts\WaymendLite\Core\Path;
use PNScripts\WaymendLite\Core\Regex;

class RegexAndPathTest extends TestCase {

	public function test_substitute(): void {
		$groups = array( 'x', 'a', 'b' );

		$this->assertSame( '/a/b', Regex::substitute( '/$1/$2', $groups ) );
		$this->assertSame( '/a0', Regex::substitute( '/$10', $groups ), '$10 without a 10th group is $1 followed by 0' );
		$this->assertSame( '/b9', Regex::substitute( '/${2}9', $groups ) );
		$this->assertSame( '/', Regex::substitute( '/$7', $groups ) );
		$groups = array_merge( $groups, array_fill( 3, 9, 'z' ) );
		$groups[10] = 'ten';
		$this->assertSame( '/ten', Regex::substitute( '/$10', $groups ) );
	}

	public function test_errors(): void {
		$this->assertNull( Regex::error( '^/ok/(\d+)$' ) );
		$this->assertNotNull( Regex::error( '^/(broken' ) );
		$this->assertNotNull( Regex::error( '' ) );
		$this->assertNull( Regex::error( '^/hash#tag$' ), 'the delimiter is escaped' );
		$this->assertNotNull( Regex::match( '^/hash#tag$', '/hash#tag' ) );
	}

	public function test_local_paths(): void {
		$home = 'https://example.com';
		$this->assertSame( '/a?b=1', Path::local( '/a?b=1', $home ) );
		$this->assertSame( '/a', Path::local( 'https://EXAMPLE.com/a', $home ) );
		$this->assertSame( '/', Path::local( 'http://example.com', $home ) );
		$this->assertNull( Path::local( 'https://other.org/a', $home ) );
		$this->assertNull( Path::local( '//other.org/a', $home ) );
		$this->assertNull( Path::local( '', $home ) );

		$sub = 'https://example.com/site';
		$this->assertSame( '/a', Path::local( 'https://example.com/site/a', $sub ) );
		$this->assertSame( '/', Path::local( 'https://example.com/site', $sub ) );
		$this->assertNull( Path::local( 'https://example.com/sitemap', $sub ) );
	}

	public function test_query_params_are_order_insensitive(): void {
		$this->assertSame( Path::query_params( 'b=2&a=1' ), Path::query_params( 'a=1&b=2' ) );
		$this->assertSame( '/x?a=1&b=2#top', Path::with_query( '/x?a=1#top', 'b=2' ) );
	}

	public function test_ignore_list(): void {
		$list = new Ignore_List( Ignore_List::DEFAULT_PATTERNS . "\n# comment\n/wp-content/uploads/2019/*" );

		$this->assertTrue( $list->matches( '/wp-admin/setup-config.php' ) );
		$this->assertTrue( $list->matches( '/.env.backup' ) );
		$this->assertTrue( $list->matches( '/wp-content/uploads/2019/01/x.jpg' ) );
		$this->assertTrue( $list->matches( '/XMLRPC.PHP' ) );
		$this->assertFalse( $list->matches( '/old-page' ) );
		$this->assertFalse( $list->matches( '/wp-content/uploads/2020/01/x.jpg' ) );
		$this->assertFalse( ( new Ignore_List( '' ) )->matches( '/anything' ) );
	}
}
