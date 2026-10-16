<?php
/**
 * Redirect list table.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\WordPress\Admin;

use PNScripts\WaymendLite\Core\Rule;
use PNScripts\WaymendLite\WordPress\Rule_Repository;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists rules with search, views, sorting, row actions and bulk actions.
 */
final class Rules_Table extends \WP_List_Table {

	/** @var Rule_Repository */
	private $repository;

	public function __construct( Rule_Repository $repository ) {
		$this->repository = $repository;
		parent::__construct(
			array(
				'singular' => 'waymend_lite_rule',
				'plural'   => 'waymend_lite_rules',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox" />',
			'source'   => __( 'Source', 'pnscripts-waymend-lite' ),
			'target'   => __( 'Target', 'pnscripts-waymend-lite' ),
			'match'    => __( 'Match', 'pnscripts-waymend-lite' ),
			'status'   => __( 'Status', 'pnscripts-waymend-lite' ),
			'hits'     => __( 'Hits', 'pnscripts-waymend-lite' ),
			'last_hit' => __( 'Last hit', 'pnscripts-waymend-lite' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'source'   => array( 'source', false ),
			'status'   => array( 'status', false ),
			'hits'     => array( 'hits', true ),
			'last_hit' => array( 'last_hit', true ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'enable'     => __( 'Enable', 'pnscripts-waymend-lite' ),
			'disable'    => __( 'Disable', 'pnscripts-waymend-lite' ),
			'reset_hits' => __( 'Reset hit counts', 'pnscripts-waymend-lite' ),
			'delete'     => __( 'Delete', 'pnscripts-waymend-lite' ),
		);
	}

	protected function get_views() {
		$counts  = $this->repository->counts();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$current = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'all';
		$labels  = array(
			'all'      => __( 'All', 'pnscripts-waymend-lite' ),
			'enabled'  => __( 'Active', 'pnscripts-waymend-lite' ),
			'disabled' => __( 'Inactive', 'pnscripts-waymend-lite' ),
			'gone'     => __( '410 Gone', 'pnscripts-waymend-lite' ),
		);
		$views   = array();
		foreach ( $labels as $view => $label ) {
			$url            = add_query_arg( array( 'page' => Admin::SLUG, 'tab' => 'rules', 'view' => $view ), admin_url( 'tools.php' ) );
			$views[ $view ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( $url ),
				$current === $view ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $counts[ $view ] ) )
			);
		}

		return $views;
	}

	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list arguments.
		$per_page = 25;
		$result   = $this->repository->query(
			array(
				'search'   => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
				'view'     => isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'all',
				'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id',
				'order'    => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc',
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);
		// phpcs:enable

		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'source' );
		$this->set_pagination_args( array( 'total_items' => $result['total'], 'per_page' => $per_page ) );
	}

	public function no_items() {
		esc_html_e( 'No redirects yet. Add one above, import a CSV file or create one from the 404 log.', 'pnscripts-waymend-lite' );
	}

	/**
	 * @param Rule $item
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item->id );
	}

	/**
	 * @param Rule $item
	 */
	protected function column_source( $item ) {
		$edit   = add_query_arg( array( 'page' => Admin::SLUG, 'tab' => 'rules', 'action' => 'edit', 'id' => $item->id ), admin_url( 'tools.php' ) );
		$toggle = wp_nonce_url(
			add_query_arg( array( 'action' => 'waymend_lite_toggle_rule', 'id' => $item->id ), admin_url( 'admin-post.php' ) ),
			'waymend_lite_toggle_rule_' . $item->id
		);
		$delete = wp_nonce_url(
			add_query_arg( array( 'action' => 'waymend_lite_delete_rule', 'id' => $item->id ), admin_url( 'admin-post.php' ) ),
			'waymend_lite_delete_rule_' . $item->id
		);

		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html__( 'Edit', 'pnscripts-waymend-lite' ) ),
			'toggle' => sprintf( '<a href="%s">%s</a>', esc_url( $toggle ), $item->enabled ? esc_html__( 'Disable', 'pnscripts-waymend-lite' ) : esc_html__( 'Enable', 'pnscripts-waymend-lite' ) ),
			'delete' => sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(%s);">%s</a>',
				esc_url( $delete ),
				esc_attr( wp_json_encode( __( 'Delete this redirect?', 'pnscripts-waymend-lite' ) ) ),
				esc_html__( 'Delete', 'pnscripts-waymend-lite' )
			),
		);

		$state = $item->enabled ? '' : ' <span class="waymend-badge waymend-badge--off">' . esc_html__( 'disabled', 'pnscripts-waymend-lite' ) . '</span>';

		return sprintf( '<a class="row-title waymend-mono" href="%s">%s</a>%s%s', esc_url( $edit ), esc_html( $item->source ), $state, $this->row_actions( $actions ) );
	}

	/**
	 * @param Rule $item
	 */
	protected function column_target( $item ) {
		if ( $item->is_gone() ) {
			return '<span class="waymend-muted">' . esc_html__( '(none, answers 410 Gone)', 'pnscripts-waymend-lite' ) . '</span>';
		}

		return '<span class="waymend-mono">' . esc_html( $item->target ) . '</span>';
	}

	/**
	 * @param Rule $item
	 */
	protected function column_match( $item ) {
		$labels = array(
			Rule::EXACT  => __( 'Exact', 'pnscripts-waymend-lite' ),
			Rule::PREFIX => __( 'Prefix', 'pnscripts-waymend-lite' ),
			Rule::REGEX  => __( 'Regex', 'pnscripts-waymend-lite' ),
		);
		$extra  = array();
		if ( Rule::QUERY_PASS === $item->query_mode ) {
			$extra[] = __( 'passes query', 'pnscripts-waymend-lite' );
		} elseif ( Rule::QUERY_EXACT === $item->query_mode ) {
			$extra[] = __( 'exact query', 'pnscripts-waymend-lite' );
		}
		if ( $item->case_sensitive ) {
			$extra[] = __( 'case-sensitive', 'pnscripts-waymend-lite' );
		}

		return esc_html( $labels[ $item->match_type ] ?? $item->match_type )
			. ( $extra ? '<br><span class="waymend-muted">' . esc_html( implode( ', ', $extra ) ) . '</span>' : '' );
	}

	/**
	 * @param Rule $item
	 */
	protected function column_status( $item ) {
		return sprintf( '<span class="waymend-badge waymend-badge--%1$d">%1$d</span>', (int) $item->status );
	}

	/**
	 * @param Rule $item
	 */
	protected function column_hits( $item ) {
		return esc_html( number_format_i18n( $item->hits ) );
	}

	/**
	 * @param Rule $item
	 */
	protected function column_last_hit( $item ) {
		if ( null === $item->last_hit ) {
			return '<span class="waymend-muted">' . esc_html__( 'never', 'pnscripts-waymend-lite' ) . '</span>';
		}
		$time = strtotime( $item->last_hit . ' UTC' );

		return esc_html(
			sprintf(
				/* translators: %s: human-readable time difference. */
				__( '%s ago', 'pnscripts-waymend-lite' ),
				human_time_diff( $time, time() )
			)
		);
	}
}
