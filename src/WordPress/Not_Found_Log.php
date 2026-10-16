<?php
/**
 * The 404 log.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\WordPress;

use PNScripts\WaymendLite\Core\Ignore_List;
use PNScripts\WaymendLite\Core\Path;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, write-heavy log.

/**
 * One row per distinct path that returned 404: hits, first and last time seen and the
 * last referrer. No IP addresses or user agents are stored.
 */
final class Not_Found_Log {

	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'waymend_lite_404';
	}

	/**
	 * Records a 404 for a site-relative URI unless logging is off or the path is ignored.
	 */
	public function record( string $uri, string $referrer = '' ): bool {
		if ( ! Settings::get( 'log_404' ) ) {
			return false;
		}

		list( $path, $query ) = Path::split( $uri );
		if ( ( new Ignore_List( (string) Settings::get( 'ignore_patterns' ) ) )->matches( $path ) ) {
			return false;
		}

		global $wpdb;
		$stored   = substr( $path . ( '' !== $query ? '?' . $query : '' ), 0, 2000 );
		$hash     = md5( $stored );
		$now      = current_time( 'mysql', true );
		$referrer = substr( esc_url_raw( $referrer ), 0, 2000 );

		$updated = $wpdb->query(
			$wpdb->prepare( 'UPDATE %i SET hits = hits + 1, last_seen = %s, referrer = %s WHERE path_hash = %s', $this->table(), $now, $referrer, $hash )
		);

		if ( 0 === (int) $updated ) {
			$wpdb->insert(
				$this->table(),
				array(
					'path'       => $stored,
					'path_hash'  => $hash,
					'hits'       => 1,
					'first_seen' => $now,
					'last_seen'  => $now,
					'referrer'   => $referrer,
				),
				array( '%s', '%s', '%d', '%s', '%s', '%s' )
			);
		}

		return true;
	}

	/**
	 * @param array{search?: string, orderby?: string, order?: string, per_page?: int, page?: int} $args
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function query( array $args ): array {
		global $wpdb;

		$where  = '1 = %d';
		$params = array( 1 );
		$search = trim( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$where   .= ' AND (path LIKE %s OR referrer LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$orderby = in_array( $args['orderby'] ?? '', array( 'path', 'hits', 'last_seen', 'first_seen' ), true ) ? $args['orderby'] : 'last_seen';
		$order   = 'asc' === strtolower( (string) ( $args['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';
		$limit   = max( 1, (int) ( $args['per_page'] ?? 25 ) );
		$offset  = max( 0, ( (int) ( $args['page'] ?? 1 ) - 1 ) * $limit );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed SQL with placeholders; order whitelisted.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$where}", array_merge( array( $this->table() ), $params ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$where} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d", array_merge( array( $this->table() ), $params, array( $limit, $offset ) ) ), ARRAY_A );

		return array( 'items' => (array) $rows, 'total' => $total );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table(), $id ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param list<int> $ids
	 */
	public function delete( array $ids ): int {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- only %d placeholders are interpolated.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE id IN ({$placeholders})", array_merge( array( $this->table() ), $ids ) ) );
	}

	/**
	 * Removes the entry for a path (after a redirect was created for it).
	 */
	public function delete_path( string $uri ): int {
		global $wpdb;
		list( $path, $query ) = Path::split( $uri );
		$stored               = $path . ( '' !== $query ? '?' . $query : '' );

		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE path_hash = %s', $this->table(), md5( $stored ) ) );
	}

	public function clear(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE 1 = %d', $this->table(), 1 ) );
	}

	/**
	 * Keeps the newest $max rows (by last_seen). Runs daily from cron.
	 */
	public function prune( ?int $max = null ): int {
		global $wpdb;
		$max   = $max ?? (int) Settings::get( 'log_max_rows' );
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE 1 = %d', $this->table(), 1 ) );
		if ( $total <= $max ) {
			return 0;
		}
		$ids = $wpdb->get_col(
			$wpdb->prepare( 'SELECT id FROM %i ORDER BY last_seen DESC, id DESC LIMIT %d OFFSET %d', $this->table(), $total - $max, $max )
		);

		return $this->delete( array_map( 'intval', $ids ) );
	}

	/**
	 * A likely target for a 404 path: a published post or term whose slug matches the last segment.
	 */
	public function suggest_target( string $uri ): string {
		list( $path ) = Path::split( $uri );
		$slug         = sanitize_title( basename( rtrim( $path, '/' ) ) );
		if ( '' === $slug ) {
			return '';
		}

		// Try the slug itself, then without a trailing "-2" style number.
		$candidates = array_unique( array( $slug, (string) preg_replace( '/-\d+$/', '', $slug ) ) );
		foreach ( $candidates as $candidate ) {
			$posts = get_posts(
				array(
					'name'           => $candidate,
					'post_type'      => 'any',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			if ( $posts ) {
				return $this->relative( (string) get_permalink( $posts[0] ) );
			}
		}

		// Then a published item whose slug starts with this one ("tyre-pressure" finds "tyre-pressure-chart").
		global $wpdb;
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM %i WHERE post_name LIKE %s AND post_status = 'publish' AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment') ORDER BY post_date DESC LIMIT 1",
				$wpdb->posts,
				$wpdb->esc_like( $slug ) . '%'
			)
		);
		if ( $id ) {
			return $this->relative( (string) get_permalink( (int) $id ) );
		}

		$term = get_terms( array( 'slug' => $slug, 'hide_empty' => false, 'number' => 1 ) );
		if ( is_array( $term ) && $term && ! is_wp_error( $link = get_term_link( $term[0] ) ) ) {
			return $this->relative( (string) $link );
		}

		return '';
	}

	/**
	 * A permalink as a path relative to the site address (the form rule targets use).
	 */
	private function relative( string $url ): string {
		return (string) ( Path::local( $url, home_url( '/' ) ) ?? $url );
	}
}
