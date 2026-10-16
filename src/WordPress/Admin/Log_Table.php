<?php
/**
 * 404 log list table.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\WordPress\Admin;

use PNScripts\WaymendLite\WordPress\Not_Found_Log;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists logged 404s with a "Redirect this" action.
 */
final class Log_Table extends \WP_List_Table {

	/** @var Not_Found_Log */
	private $log;

	public function __construct( Not_Found_Log $log ) {
		$this->log = $log;
		parent::__construct(
			array(
				'singular' => 'waymend_lite_404',
				'plural'   => 'waymend_lite_404s',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'path'       => __( 'URL', 'pnscripts-waymend-lite' ),
			'hits'       => __( 'Hits', 'pnscripts-waymend-lite' ),
			'last_seen'  => __( 'Last seen', 'pnscripts-waymend-lite' ),
			'first_seen' => __( 'First seen', 'pnscripts-waymend-lite' ),
			'referrer'   => __( 'Last referrer', 'pnscripts-waymend-lite' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'path'       => array( 'path', false ),
			'hits'       => array( 'hits', true ),
			'last_seen'  => array( 'last_seen', true ),
			'first_seen' => array( 'first_seen', true ),
		);
	}

	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'pnscripts-waymend-lite' ) );
	}

	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list arguments.
		$per_page = 25;
		$result   = $this->log->query(
			array(
				'search'   => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
				'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'last_seen',
				'order'    => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc',
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);
		// phpcs:enable

		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'path' );
		$this->set_pagination_args( array( 'total_items' => $result['total'], 'per_page' => $per_page ) );
	}

	public function no_items() {
		esc_html_e( 'No 404 errors logged.', 'pnscripts-waymend-lite' );
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item['id'] );
	}

	protected function column_path( $item ) {
		$redirect = add_query_arg(
			array(
				'page'   => Admin::SLUG,
				'tab'    => 'rules',
				'action' => 'new',
				'log_id' => (int) $item['id'],
			),
			admin_url( 'tools.php' )
		);
		$delete   = wp_nonce_url(
			add_query_arg( array( 'action' => 'waymend_lite_delete_404', 'id' => (int) $item['id'] ), admin_url( 'admin-post.php' ) ),
			'waymend_lite_delete_404_' . (int) $item['id']
		);

		$actions = array(
			'redirect' => sprintf( '<a href="%s"><strong>%s</strong></a>', esc_url( $redirect ), esc_html__( 'Redirect this', 'pnscripts-waymend-lite' ) ),
			'view'     => sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( home_url( $item['path'] ) ), esc_html__( 'Open', 'pnscripts-waymend-lite' ) ),
			'delete'   => sprintf( '<a href="%s" class="submitdelete">%s</a>', esc_url( $delete ), esc_html__( 'Delete', 'pnscripts-waymend-lite' ) ),
		);

		return sprintf(
			'<a class="row-title waymend-mono" href="%s">%s</a>%s',
			esc_url( $redirect ),
			esc_html( $item['path'] ),
			$this->row_actions( $actions )
		);
	}

	protected function column_hits( $item ) {
		return esc_html( number_format_i18n( (int) $item['hits'] ) );
	}

	protected function column_last_seen( $item ) {
		return esc_html( $this->ago( (string) $item['last_seen'] ) );
	}

	protected function column_first_seen( $item ) {
		return esc_html( get_date_from_gmt( (string) $item['first_seen'], get_option( 'date_format' ) ) );
	}

	protected function column_referrer( $item ) {
		if ( '' === (string) $item['referrer'] ) {
			return '<span class="waymend-muted">' . esc_html__( 'none', 'pnscripts-waymend-lite' ) . '</span>';
		}
		$host = wp_parse_url( (string) $item['referrer'], PHP_URL_HOST );

		return sprintf( '<span class="waymend-mono" title="%s">%s</span>', esc_attr( $item['referrer'] ), esc_html( $host ? $host : $item['referrer'] ) );
	}

	private function ago( string $gmt ): string {
		/* translators: %s: human-readable time difference. */
		return sprintf( __( '%s ago', 'pnscripts-waymend-lite' ), human_time_diff( (int) strtotime( $gmt . ' UTC' ), time() ) );
	}
}
