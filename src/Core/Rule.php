<?php
/**
 * A redirect rule. Plain PHP, no WordPress dependency.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\Core;

/**
 * One redirect: where it matches (source + match type) and what it answers.
 */
final class Rule {

	const EXACT  = 'exact';
	const PREFIX = 'prefix';
	const REGEX  = 'regex';

	// Regular-expression rules are a feature of the full Waymend; the Lite edition matches exact URLs and prefixes.
	const MATCH_TYPES = array( self::EXACT, self::PREFIX );
	const STATUSES    = array( 301, 302, 307, 410 );

	/** Match on the path only; the visitor's query string is dropped. */
	const QUERY_IGNORE = 'ignore';
	/** Match on the path only; the visitor's query string is passed on to the target. */
	const QUERY_PASS = 'pass';
	/** The source contains a query string and the request must carry the same parameters. */
	const QUERY_EXACT = 'exact';

	const QUERY_MODES = array( self::QUERY_IGNORE, self::QUERY_PASS, self::QUERY_EXACT );

	/** @var int */
	public $id;
	/** @var string */
	public $source;
	/** @var string */
	public $target;
	/** @var string */
	public $match_type;
	/** @var int */
	public $status;
	/** @var string */
	public $query_mode;
	/** @var bool */
	public $case_sensitive;
	/** @var bool */
	public $enabled;
	/** @var int */
	public $hits;
	/** @var string|null Last hit, "Y-m-d H:i:s" UTC. */
	public $last_hit;
	/** @var int Order among regex rules (lower first). */
	public $position;

	public function __construct(
		string $source,
		string $target = '',
		string $match_type = self::EXACT,
		int $status = 301,
		string $query_mode = self::QUERY_IGNORE,
		bool $case_sensitive = false,
		bool $enabled = true,
		int $id = 0,
		int $hits = 0,
		?string $last_hit = null,
		int $position = 0
	) {
		$this->id             = $id;
		$this->source         = $source;
		$this->target         = $target;
		$this->match_type     = $match_type;
		$this->status         = $status;
		$this->query_mode     = $query_mode;
		$this->case_sensitive = $case_sensitive;
		$this->enabled        = $enabled;
		$this->hits           = $hits;
		$this->last_hit       = $last_hit;
		$this->position       = $position;
	}

	/**
	 * @param array<string, mixed> $row Database row or CSV record.
	 */
	public static function from_array( array $row ): self {
		return new self(
			(string) ( $row['source'] ?? '' ),
			(string) ( $row['target'] ?? '' ),
			(string) ( $row['match_type'] ?? self::EXACT ),
			(int) ( $row['status'] ?? 301 ),
			(string) ( $row['query_mode'] ?? self::QUERY_IGNORE ),
			! empty( $row['case_sensitive'] ),
			! array_key_exists( 'enabled', $row ) || ! empty( $row['enabled'] ),
			(int) ( $row['id'] ?? 0 ),
			(int) ( $row['hits'] ?? 0 ),
			isset( $row['last_hit'] ) && '' !== $row['last_hit'] ? (string) $row['last_hit'] : null,
			(int) ( $row['position'] ?? 0 )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'             => $this->id,
			'source'         => $this->source,
			'target'         => $this->target,
			'match_type'     => $this->match_type,
			'status'         => $this->status,
			'query_mode'     => $this->query_mode,
			'case_sensitive' => $this->case_sensitive ? 1 : 0,
			'enabled'        => $this->enabled ? 1 : 0,
			'hits'           => $this->hits,
			'last_hit'       => $this->last_hit,
			'position'       => $this->position,
		);
	}

	public function is_gone(): bool {
		return 410 === $this->status;
	}
}
