<?php

namespace PNScripts\WaymendLite\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PNScripts\WaymendLite\Core\Path;

/**
 * Path::clean() runs before esc_url_raw() on every path that comes from a request or a form:
 * control characters go, everything esc_url_raw() would silently drop is percent-encoded
 * instead, so the cleaned value matches the same page as the raw one.
 */
class InputSanitizingTest extends TestCase {

	/** Characters esc_url_raw() keeps (WordPress core, esc_url()), plus the space it encodes itself. */
	private const ESC_URL_RAW_KEEPS = '#^[a-z0-9\-~+_.?\#=!&;,/:%@$|*\'()\[\]\x80-\xff]*$#i';

	public function test_control_characters_are_removed(): void {
		$this->assertSame( '/old-pageX', Path::clean( "/old-page\r\n\0\tX" ) );
		$this->assertSame( '/a', Path::clean( "/a\x7F" ) );
	}

	public function test_characters_esc_url_raw_would_drop_are_encoded(): void {
		$this->assertSame( '/promo-%3Cb%3E%22x%22', Path::clean( '/promo-<b>"x"' ) );
		$this->assertSame( '/a%20b%5Cc%5E%60%7B%7D', Path::clean( '/a b\\c^`{}' ) );
		foreach ( array( '/promo-<b>"x"', "/a b\\c^`{}\x01", '/100%', '/x?q=<script>' ) as $raw ) {
			$this->assertMatchesRegularExpression( self::ESC_URL_RAW_KEEPS, Path::clean( $raw ), $raw );
		}
	}

	public function test_nothing_a_visitor_can_request_is_lost(): void {
		foreach ( array( '/promo-<b>"x"', '/a b/c', '/кирилица/страница', '/shop?colour=red&size[]=m', '/100%' ) as $raw ) {
			list( $path, $query ) = Path::split( Path::clean( $raw ) );
			list( $raw_path, $raw_query ) = Path::split( $raw );
			$this->assertSame( $raw_path, $path, $raw );
			$this->assertSame( Path::query_params( $raw_query ), Path::query_params( $query ), $raw );
		}
	}

	public function test_valid_encodings_and_utf8_are_kept(): void {
		$this->assertSame( '/%D0%BA%20x', Path::clean( '/%D0%BA%20x' ) );
		$this->assertSame( '/кирилица/страница', Path::clean( '/кирилица/страница' ) );
		$this->assertSame( "/shop?colour=red&size[]=m&a=1;b=(2)*\$x@y!'z'|w", Path::clean( "/shop?colour=red&size[]=m&a=1;b=(2)*\$x@y!'z'|w" ) );
	}

	public function test_a_lone_percent_sign_is_encoded(): void {
		$this->assertSame( '/100%25', Path::clean( '/100%' ) );
		$this->assertSame( '/50%25-off%20', Path::clean( '/50%-off%20' ) );
	}

	public function test_invalid_utf8_is_encoded(): void {
		$this->assertSame( '/a%FF%FEb', Path::clean( "/a\xFF\xFEb" ) );
	}

	public function test_clean_is_idempotent_and_bounded(): void {
		foreach ( array( '/promo-<b>"x"', '/100%', "/a\xFFb", '/кирилица' ) as $raw ) {
			$once = Path::clean( $raw );
			$this->assertSame( $once, Path::clean( $once ), $raw );
		}
		$this->assertSame( 2000, strlen( Path::clean( '/' . str_repeat( 'a', 5000 ) ) ) );
		$this->assertSame( '', Path::clean( "  \r\n " ) );
	}

	public function test_a_site_path_always_starts_with_one_slash(): void {
		$this->assertSame( '/old-page', Path::site_path( 'old-page' ) );
		$this->assertSame( '/old-page', Path::site_path( ' /old-page ' ) );
		$this->assertSame( '/evil.example/x', Path::site_path( '//evil.example/x' ), 'never a protocol-relative URL' );
		$this->assertSame( '/a%3Cb', Path::site_path( "/a<b\n" ) );
		$this->assertSame( '', Path::site_path( " \t" ) );
	}

	public function test_readable_turns_encoded_letters_back_into_text_only(): void {
		// wp_sanitize_redirect() percent-encodes UTF-8; rule lists stay readable for non-Latin slugs.
		$this->assertSame( '/кирилица/страница', Path::readable( '/%D0%BA%D0%B8%D1%80%D0%B8%D0%BB%D0%B8%D1%86%D0%B0/%D1%81%D1%82%D1%80%D0%B0%D0%BD%D0%B8%D1%86%D0%B0' ) );
		$this->assertSame( '/%3Cb%3E%20%22x%22%2F%0A', Path::readable( '/%3Cb%3E%20%22x%22%2F%0A' ), 'ASCII stays encoded' );
		$this->assertSame( '/%C3%28/%FF', Path::readable( '/%C3%28/%FF' ), 'invalid UTF-8 stays encoded' );
		$this->assertSame( '/café?q=ü', Path::readable( '/caf%C3%A9?q=%c3%bc' ) );
	}

	public function test_file_below_a_url_base(): void {
		$base = 'https://example.com/media';
		$dir  = '/srv/site-media';
		$this->assertSame( '/srv/site-media/2026/11/photo.jpg', Path::file_below( '/media/2026/11/photo.jpg', $base, $dir ) );
		$this->assertSame( '/srv/site-media/2026/foto снимка.jpg', Path::file_below( '/media/2026/foto снимка.jpg', $base, $dir . '/' ) );
		$this->assertNull( Path::file_below( '/media', $base, $dir ), 'the folder itself is not a file' );
		$this->assertNull( Path::file_below( '/mediathek/x.jpg', $base, $dir ), 'whole segments only' );
		$this->assertNull( Path::file_below( '/other/x.jpg', $base, $dir ) );
		$this->assertNull( Path::file_below( '/media/../wp-config.php', $base, $dir ), 'no climbing out' );
		$this->assertNull( Path::file_below( "/media/a\0.jpg", $base, $dir ) );
		$this->assertSame( '/srv/site-media/x.jpg', Path::file_below( '/blog/media/x.jpg', 'https://example.com/blog/media/', $dir ) );
	}
}
