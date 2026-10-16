<?php
/**
 * Storage of redirect rules.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\WordPress;

use PNScripts\WaymendLite\Core\Rule;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; reads of enabled rules are cached below.

/**
 * CRUD for the {prefix}waymend_rules table. Every query is prepared.
 */
final class Rule_Repository {

	const CACHE_GROUP = 'pnscripts-waymend-lite';

	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'waymend_lite_rules';
	}

	/**
	 * Enabled rules, cached in the object cache until the next change.
	 *
	 * @return list<Rule>
	 */
	public function enabled(): array {
		$cached = wp_cache_get( 'enabled_rules', self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE enabled = %d', $this->table(), 1 ), ARRAY_A );
		$rules = array_map( array( Rule::class, 'from_array' ), (array) $rows );
		wp_cache_set( 'enabled_rules', $rules, self::CACHE_GROUP );

		return $rules;
	}

	/**
	 * @return list<Rule>
	 */
	public function all(): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id ASC', $this->table() ), ARRAY_A );

		return array_map( array( Rule::class, 'from_array' ), (array) $rows );
	}

	public function find( int $id ): ?Rule {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table(), $id ), ARRAY_A );

		return is_array( $row ) ? Rule::from_array( $row ) : null;
	}

	/**
	 * Paged list for the admin screen.
	 *
	 * @param array{search?: string, view?: string, orderby?: string, order?: string, per_page?: int, page?: int} $args
	 * @return array{items: list<Rule>, total: int}
	 */
	public function query( array $args ): array {
		global $wpdb;

		$where  = array( '1 = %d' );
		$params = array( 1 );

		$search = trim( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(source LIKE %s OR target LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		switch ( $args['view'] ?? 'all' ) {
			case 'enabled':
				$where[]  = 'enabled = %d';
				$params[] = 1;
				break;
			case 'disabled':
				$where[]  = 'enabled = %d';
				$params[] = 0;
				break;
			case 'gone':
				$where[]  = 'status = %d';
				$params[] = 410;
				break;
		}

		$columns = array( 'source', 'target', 'status', 'hits', 'last_hit', 'id', 'match_type' );
		$orderby = in_array( $args['orderby'] ?? '', $columns, true ) ? $args['orderby'] : 'id';
		$order   = 'asc' === strtolower( (string) ( $args['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';
		$limit   = max( 1, (int) ( $args['per_page'] ?? 25 ) );
		$offset  = max( 0, ( (int) ( $args['page'] ?? 1 ) - 1 ) * $limit );
		$clause  = implode( ' AND ', $where );

		// $clause only contains placeholders and fixed column names; $orderby and $order are whitelisted.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$clause}", array_merge( array( $this->table() ), $params ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$clause} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d", array_merge( array( $this->table() ), $params, array( $limit, $offset ) ) ), ARRAY_A );

		return array(
			'items' => array_map( array( Rule::class, 'from_array' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * @return array{all: int, enabled: int, disabled: int, gone: int}
	 */
	public function counts(): array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT COUNT(*) AS total, SUM(enabled = %d) AS on_count, SUM(status = %d) AS gone FROM %i', 1, 410, $this->table() ),
			ARRAY_A
		);

		return array(
			'all'      => (int) ( $row['total'] ?? 0 ),
			'enabled'  => (int) ( $row['on_count'] ?? 0 ),
			'disabled' => (int) ( $row['total'] ?? 0 ) - (int) ( $row['on_count'] ?? 0 ),
			'gone'     => (int) ( $row['gone'] ?? 0 ),
		);
	}

	/**
	 * Inserts or updates; returns the rule id.
	 */
	public function save( Rule $rule ): int {
		global $wpdb;

		$data = array(
			'source'         => $rule->source,
			'target'         => $rule->target,
			'match_type'     => $rule->match_type,
			'status'         => $rule->status,
			'query_mode'     => $rule->query_mode,
			'case_sensitive' => $rule->case_sensitive ? 1 : 0,
			'enabled'        => $rule->enabled ? 1 : 0,
			'position'       => $rule->position,
		);
		$formats = array( '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d' );

		if ( $rule->id > 0 ) {
			$wpdb->update( $this->table(), $data, array( 'id' => $rule->id ), $formats, array( '%d' ) );
			$id = $rule->id;
		} else {
			$data['hits']       = $rule->hits;
			$data['last_hit']   = $rule->last_hit;
			$data['created_at'] = current_time( 'mysql', true );
			$wpdb->insert( $this->table(), $data, array_merge( $formats, array( '%d', '%s', '%s' ) ) );
			$id = (int) $wpdb->insert_id;
		}

		$this->flush();

		return $id;
	}

	/**
	 * @param list<int> $ids
	 */
	public function delete( array $ids ): int {
		return $this->update_ids( $ids, 'DELETE FROM %i WHERE id IN (%s)' );
	}

	/**
	 * @param list<int> $ids
	 */
	public function set_enabled( array $ids, bool $enabled ): int {
		return $this->update_ids( $ids, 'UPDATE %i SET enabled = ' . ( $enabled ? '1' : '0' ) . ' WHERE id IN (%s)' );
	}

	/**
	 * @param list<int> $ids
	 */
	public function reset_hits( array $ids ): int {
		return $this->update_ids( $ids, 'UPDATE %i SET hits = 0, last_hit = NULL WHERE id IN (%s)' );
	}

	public function record_hit( int $id ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET hits = hits + 1, last_hit = %s WHERE id = %d', $this->table(), current_time( 'mysql', true ), $id ) );
		// Hit counts do not change matching, so the cached rule list stays valid.
	}

	public function flush(): void {
		wp_cache_delete( 'enabled_rules', self::CACHE_GROUP );
	}

	/**
	 * Runs a statement with "%i" for the table and "(%s)" for a list of integer ids.
	 *
	 * @param list<int> $ids
	 */
	private function update_ids( array $ids, string $sql ): int {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = str_replace( '(%s)', '(' . $placeholders . ')', $sql );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is a fixed statement with placeholders only.
		$count = (int) $wpdb->query( $wpdb->prepare( $sql, array_merge( array( $this->table() ), $ids ) ) );
		$this->flush();

		return $count;
	}
}
