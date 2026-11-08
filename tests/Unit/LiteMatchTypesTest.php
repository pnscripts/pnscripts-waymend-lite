<?php

namespace PNScripts\WaymendLite\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PNScripts\WaymendLite\Core\Csv;
use PNScripts\WaymendLite\Core\Rule;
use PNScripts\WaymendLite\Core\Rule_Validator;

/**
 * The Lite edition matches exact URLs and prefixes only. Every message a user
 * reads must say so: an import error or a form hint that offers regex sends
 * people to an option that the Lite edition then refuses.
 */
class LiteMatchTypesTest extends TestCase {

	public function test_lite_accepts_exact_and_prefix_only(): void {
		$this->assertSame( array( Rule::EXACT, Rule::PREFIX ), Rule::MATCH_TYPES );
	}

	public function test_csv_regex_row_is_refused_without_offering_regex(): void {
		$import = Csv::import( "source,target,match\n^/blog/(.+)$,/news/\$1,regex\n/old,/new,exact\n" );

		$this->assertCount( 1, $import['rules'], 'the exact row is still imported' );
		$this->assertSame(
			array( 2 => 'Regex rules are part of the full Waymend; the Lite edition imports exact and prefix rules.' ),
			$import['errors']
		);
	}

	public function test_csv_regex_match_type_is_refused_in_any_case(): void {
		$import = Csv::import( "source,target,match\n^/a$,/b,REGEX\n" );

		$this->assertSame( array(), $import['rules'] );
		$this->assertSame(
			array( 2 => 'Regex rules are part of the full Waymend; the Lite edition imports exact and prefix rules.' ),
			$import['errors']
		);
	}

	public function test_csv_unknown_match_type_lists_only_the_lite_types(): void {
		$import = Csv::import( "source,target,match\n/a,/b,glob\n" );

		$this->assertSame( array( 2 => 'Unsupported match type "glob" (use exact or prefix).' ), $import['errors'] );
	}

	public function test_validator_refuses_a_regex_rule(): void {
		$validator = new Rule_Validator( 'https://example.test/' );
		$result    = $validator->validate( $validator->normalize( new Rule( '^/a/(.+)$', '/b/$1', Rule::REGEX ) ) );

		$this->assertContains( 'match_invalid', array_column( $result['errors'], 'code' ) );
	}

	/**
	 * Every translatable string (the .pot that make i18n writes from the code).
	 * Only the label of a stored rule's match type and the pattern error may
	 * name regular expressions; nothing may offer them as a choice.
	 */
	public function test_no_user_facing_string_offers_regex(): void {
		$pot = (string) file_get_contents( dirname( __DIR__, 2 ) . '/languages/pnscripts-waymend-lite.pot' );
		preg_match_all( '/^msgid "(.+)"$/m', $pot, $matches );

		$allowed = array( 'Regex', 'The regular expression is not valid: %s' );
		$offers  = array_values(
			array_filter(
				$matches[1],
				static function ( string $msgid ) use ( $allowed ): bool {
					return 1 === preg_match( '/regex|regular expression/i', $msgid ) && ! in_array( $msgid, $allowed, true );
				}
			)
		);

		$this->assertNotEmpty( $matches[1], 'the .pot was read' );
		$this->assertSame( array(), $offers );
	}
}
