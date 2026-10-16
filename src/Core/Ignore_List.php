<?php
/**
 * Paths that the 404 log skips. Plain PHP, no WordPress dependency.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\Core;

/**
 * One pattern per line; "*" matches any characters. A pattern without "*" matches
 * that exact path. Matching is case-insensitive. Lines starting with "#" are comments.
 */
final class Ignore_List {

	const DEFAULT_PATTERNS = "*.php\n/.well-known/*\n/.env*\n/.git*\n/xmlrpc.php\n/wp-login.php";

	/** @var list<string> */
	private $regexes = array();

	public function __construct( string $patterns ) {
		foreach ( preg_split( '/\R/', $patterns ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$quoted          = preg_quote( Path::lower( $line ), '#' );
			$this->regexes[] = '#^' . str_replace( '\*', '.*', $quoted ) . '$#u';
		}
	}

	public function matches( string $path ): bool {
		list( $path ) = Path::split( $path );
		$path         = Path::lower( $path );
		foreach ( $this->regexes as $regex ) {
			if ( 1 === preg_match( $regex, $path ) ) {
				return true;
			}
		}

		return false;
	}
}
