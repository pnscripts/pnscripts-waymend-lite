<?php

namespace PNScripts\WaymendLite\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * WordPress.org review rules (review of 1.0.1, 2026-11-21), checked on the shipped source:
 * every request input is sanitized where it is read, files are not located through ABSPATH,
 * translations come from translate.wordpress.org.
 */
class DirectoryRulesTest extends TestCase {

	/** @return array<string, string> Shipped PHP files, path => source. */
	private static function shipped(): array {
		$root  = dirname( __DIR__, 2 );
		$files = array( 'pnscripts-waymend-lite.php' => '', 'uninstall.php' => '' );
		$it    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/src', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( 'php' === $file->getExtension() ) {
				$files[ substr( $file->getPathname(), strlen( $root ) + 1 ) ] = '';
			}
		}
		foreach ( array_keys( $files ) as $path ) {
			$files[ $path ] = (string) file_get_contents( $root . '/' . $path );
		}
		ksort( $files );

		return $files;
	}

	public function test_no_sanitizing_check_is_silenced(): void {
		foreach ( self::shipped() as $path => $source ) {
			foreach ( explode( "\n", $source ) as $n => $line ) {
				if ( false === strpos( $line, 'InputNotSanitized' ) ) {
					continue;
				}
				// The one exception: Link_Checker saves three server values and puts them back unchanged.
				$this->assertSame( 'src/WordPress/Link_Checker.php', $path, $path . ':' . ( $n + 1 ) . ' silences the sanitizing check' );
				$this->assertStringContainsString( 'restored unchanged', $line );
			}
		}
	}

	public function test_request_arrays_are_never_taken_whole(): void {
		foreach ( self::shipped() as $path => $source ) {
			$this->assertDoesNotMatchRegularExpression( '/wp_unslash\(\s*\$_(POST|GET|REQUEST|SERVER|COOKIE)\s*\)/', $source, $path );
		}
	}

	public function test_every_read_of_a_request_value_is_wrapped_by_a_sanitizer(): void {
		$sanitizers = 'sanitize_key|sanitize_text_field|sanitize_textarea_field|esc_url_raw|wp_sanitize_redirect|absint';
		foreach ( self::shipped() as $path => $source ) {
			if ( 'src/WordPress/Link_Checker.php' === $path ) {
				continue; // Only writes and restores $_SERVER (checked above).
			}
			foreach ( explode( "\n", $source ) as $n => $line ) {
				if ( ! preg_match_all( '/\$_(POST|GET|REQUEST|SERVER|COOKIE)\[[^\]]+\]/', $line, $reads, PREG_OFFSET_CAPTURE ) ) {
					continue;
				}
				foreach ( $reads[0] as $read ) {
					$before = substr( $line, 0, $read[1] );
					// Allowed: isset()/empty() tests, and reads inside a sanitizer call.
					if ( preg_match( '/(isset|empty)\(\s*$/', $before ) ) {
						continue;
					}
					// Also allowed: a list of IDs mapped through absint().
					if ( preg_match( "/array_map\\(\\s*'absint',\\s*\\(array\\)\\s*wp_unslash\\(\\s*$/", $before ) ) {
						continue;
					}
					$this->assertMatchesRegularExpression( '/(' . $sanitizers . ')\(\s*(wp_unslash\(\s*)?(\(string\)\s*)?$/', $before, $path . ':' . ( $n + 1 ) . ' reads ' . $read[0] . ' without a sanitizer' );
				}
			}
		}
	}

	public function test_files_are_not_located_through_abspath(): void {
		foreach ( self::shipped() as $path => $source ) {
			foreach ( explode( "\n", $source ) as $n => $line ) {
				if ( false === strpos( $line, 'ABSPATH' ) || false !== strpos( $line, "defined( 'ABSPATH' )" ) ) {
					continue;
				}
				// Loading WordPress's own admin includes is the documented way.
				$this->assertMatchesRegularExpression( "#require_once ABSPATH \\. 'wp-admin/includes/[a-z\\-]+\\.php';#", $line, $path . ':' . ( $n + 1 ) );
			}
		}
	}

	public function test_translations_come_from_translate_wordpress_org(): void {
		$root = dirname( __DIR__, 2 );
		foreach ( self::shipped() as $path => $source ) {
			$this->assertStringNotContainsString( 'load_plugin_textdomain', $source, $path );
		}
		$this->assertStringNotContainsString( 'Domain Path', (string) file_get_contents( $root . '/pnscripts-waymend-lite.php' ) );
		$this->assertSame( array(), glob( $root . '/languages/*' ) ?: array(), 'no bundled translation files' );
		preg_match( '/^RUNTIME\s*:=(.*)$/m', (string) file_get_contents( $root . '/Makefile' ), $m );
		$this->assertStringNotContainsString( 'languages', $m[1] ?? 'missing', 'the release ZIP carries no languages folder' );
	}
}
