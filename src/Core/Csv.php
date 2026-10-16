<?php
/**
 * CSV import and export of rules. Plain PHP, no WordPress dependency.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\Core;

/**
 * Columns: source, target, match, status, query, case_sensitive, enabled, hits, last_hit.
 * On import only "source" and "target" are required; a file without a header row is read
 * as source, target[, status[, match]]. Comma, semicolon and tab separators are detected.
 */
final class Csv {

	const COLUMNS = array( 'source', 'target', 'match', 'status', 'query', 'case_sensitive', 'enabled', 'hits', 'last_hit' );

	const ALIASES = array(
		'from'       => 'source',
		'url'        => 'source',
		'old'        => 'source',
		'to'         => 'target',
		'new'        => 'target',
		'match_type' => 'match',
		'type'       => 'match',
		'code'       => 'status',
		'query_mode' => 'query',
	);

	/**
	 * @param iterable<Rule> $rules
	 */
	public static function export( iterable $rules ): string {
		// An in-memory file object (no filesystem access, works outside WordPress too).
		$file = new \SplTempFileObject();
		$file->fputcsv( self::COLUMNS, ',', '"', '' );
		foreach ( $rules as $rule ) {
			$row = array(
				$rule->source,
				$rule->target,
				$rule->match_type,
				$rule->status,
				$rule->query_mode,
				$rule->case_sensitive ? 1 : 0,
				$rule->enabled ? 1 : 0,
				$rule->hits,
				(string) $rule->last_hit,
			);
			$file->fputcsv( array_map( array( self::class, 'defuse' ), $row ), ',', '"', '' );
		}
		$file->rewind();
		$csv = '';
		while ( ! $file->eof() ) {
			$csv .= (string) $file->fgets();
		}

		return $csv;
	}

	/**
	 * @return array{rules: list<array{line: int, rule: Rule}>, errors: array<int, string>}
	 */
	public static function import( string $csv ): array {
		$csv = preg_replace( '/^\xEF\xBB\xBF/', '', $csv );
		$csv = str_replace( array( "\r\n", "\r" ), "\n", (string) $csv );

		$first     = (string) strtok( $csv, "\n" );
		$delimiter = self::delimiter( $first );
		$file      = new \SplTempFileObject();
		$file->fwrite( $csv );
		$file->rewind();

		$rules  = array();
		$errors = array();
		$map    = null;
		$line   = 0;

		while ( ! $file->eof() ) {
			$cells = $file->fgetcsv( $delimiter, '"', '' );
			if ( false === $cells ) {
				break;
			}
			++$line;
			if ( array( null ) === $cells || '' === trim( implode( '', $cells ) ) ) {
				continue;
			}
			$cells = array_map( 'trim', $cells );

			if ( null === $map ) {
				$map = self::header_map( $cells );
				if ( null !== $map ) {
					continue;
				}
				$map = array( 0 => 'source', 1 => 'target', 2 => 'status', 3 => 'match' );
			}

			$record = array();
			foreach ( $map as $index => $column ) {
				if ( isset( $cells[ $index ] ) ) {
					$record[ $column ] = self::undefuse( $cells[ $index ] );
				}
			}

			$parsed = self::record_to_rule( $record );
			if ( is_string( $parsed ) ) {
				$errors[ $line ] = $parsed;
			} else {
				$rules[] = array( 'line' => $line, 'rule' => $parsed );
			}
		}
		return array( 'rules' => $rules, 'errors' => $errors );
	}

	/**
	 * @param array<string, string> $record
	 * @return Rule|string The rule, or an error message.
	 */
	private static function record_to_rule( array $record ) {
		$source = $record['source'] ?? '';
		if ( '' === $source ) {
			return 'Missing source.';
		}

		$status = isset( $record['status'] ) && '' !== $record['status'] ? $record['status'] : '301';
		if ( ! ctype_digit( $status ) || ! in_array( (int) $status, Rule::STATUSES, true ) ) {
			return sprintf( 'Unsupported status "%s" (use 301, 302, 307 or 410).', $status );
		}

		$match = strtolower( isset( $record['match'] ) && '' !== $record['match'] ? $record['match'] : Rule::EXACT );
		if ( ! in_array( $match, Rule::MATCH_TYPES, true ) ) {
			return sprintf( 'Unsupported match type "%s" (use exact, prefix or regex).', $match );
		}

		$query = strtolower( isset( $record['query'] ) && '' !== $record['query'] ? $record['query'] : Rule::QUERY_IGNORE );
		if ( ! in_array( $query, Rule::QUERY_MODES, true ) ) {
			return sprintf( 'Unsupported query mode "%s" (use ignore, pass or exact).', $query );
		}

		$target = $record['target'] ?? '';
		if ( '' === $target && 410 !== (int) $status ) {
			return 'Missing target.';
		}

		return new Rule(
			$source,
			$target,
			$match,
			(int) $status,
			$query,
			self::truthy( $record['case_sensitive'] ?? '0' ),
			self::truthy( $record['enabled'] ?? '1' ),
			0,
			isset( $record['hits'] ) && ctype_digit( $record['hits'] ) ? (int) $record['hits'] : 0,
			isset( $record['last_hit'] ) && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $record['last_hit'] ) ? $record['last_hit'] : null
		);
	}

	/**
	 * @param list<string> $cells
	 * @return array<int, string>|null Column index to field name, or null when the row is data.
	 */
	private static function header_map( array $cells ): ?array {
		$map = array();
		foreach ( $cells as $index => $cell ) {
			$name = strtolower( trim( $cell ) );
			$name = self::ALIASES[ $name ] ?? $name;
			if ( in_array( $name, self::COLUMNS, true ) ) {
				$map[ $index ] = $name;
			}
		}

		return in_array( 'source', $map, true ) ? $map : null;
	}

	private static function delimiter( string $line ): string {
		$counts = array(
			','  => substr_count( $line, ',' ),
			';'  => substr_count( $line, ';' ),
			"\t" => substr_count( $line, "\t" ),
		);
		arsort( $counts );

		return (string) key( $counts );
	}

	private static function truthy( string $value ): bool {
		return in_array( strtolower( trim( $value ) ), array( '1', 'yes', 'true', 'on', 'y' ), true );
	}

	/**
	 * Spreadsheet formula injection guard: cells that start with = + - @ get a leading apostrophe.
	 *
	 * @param mixed $value
	 */
	private static function defuse( $value ): string {
		$value = (string) $value;

		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $value : $value;
	}

	private static function undefuse( string $value ): string {
		return 1 === preg_match( "/^'[=+\-@]/", $value ) ? substr( $value, 1 ) : $value;
	}
}
