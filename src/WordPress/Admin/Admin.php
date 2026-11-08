<?php
/**
 * Admin screens and form handlers.
 *
 * @package Waymend
 */

namespace PNScripts\WaymendLite\WordPress\Admin;

use PNScripts\WaymendLite\Core\Csv;
use PNScripts\WaymendLite\Core\Link_Check;
use PNScripts\WaymendLite\Core\Loop_Detector;
use PNScripts\WaymendLite\Core\Rule;
use PNScripts\WaymendLite\Core\Rule_Validator;
use PNScripts\WaymendLite\WordPress\Link_Checker;
use PNScripts\WaymendLite\WordPress\Plugin;
use PNScripts\WaymendLite\WordPress\Redirector;
use PNScripts\WaymendLite\WordPress\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Tools > Redirects: rules, 404 log, import/export and settings. Every handler checks
 * the manage_options capability and a nonce; every output is escaped.
 */
final class Admin {

	const SLUG       = 'pnscripts-waymend-lite';
	const CAPABILITY = 'manage_options';

	/** @var Plugin */
	private $plugin;

	/** Per-user transient with the last "Check old URLs" result. */
	const CHECK_TRANSIENT = 'waymend_lite_link_check_';

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WAYMEND_LITE_FILE ), array( $this, 'action_links' ) );

		foreach ( array( 'save_rule', 'delete_rule', 'toggle_rule', 'delete_404', 'clear_404', 'export', 'save_settings', 'check_links', 'check_links_csv' ) as $action ) {
			add_action( 'admin_post_waymend_lite_' . $action, array( $this, 'handle_' . $action ) );
		}
		// Bulk actions post back to the screen itself (the list table owns the "action" field).
		add_action( 'load-tools_page_' . self::SLUG, array( $this, 'handle_bulk' ) );
	}

	public function menu(): void {
		add_management_page(
			__( 'Waymend redirects', 'pnscripts-waymend-lite' ),
			__( 'Redirects', 'pnscripts-waymend-lite' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function assets( string $hook ): void {
		if ( 'tools_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'waymend-admin', plugins_url( 'assets/admin.css', WAYMEND_LITE_FILE ), array(), WAYMEND_LITE_VERSION );
	}

	/**
	 * @param array<string, string> $links
	 * @return array<string, string>
	 */
	public function action_links( array $links ): array {
		$links['pnscripts-waymend-lite'] = sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Redirects', 'pnscripts-waymend-lite' ) );

		return $links;
	}

	/**
	 * @param array<string, scalar> $args
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'tools.php' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Screens                                                              */
	/* ------------------------------------------------------------------ */

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage redirects.', 'pnscripts-waymend-lite' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation only.
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'rules';
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		// phpcs:enable

		$tabs = array(
			'rules'    => __( 'Redirects', 'pnscripts-waymend-lite' ),
			'log'      => __( '404 log', 'pnscripts-waymend-lite' ),
			'import'   => __( 'Check old URLs and export', 'pnscripts-waymend-lite' ),
			'settings' => __( 'Settings', 'pnscripts-waymend-lite' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'rules';
		}

		echo '<div class="wrap waymend">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Redirects', 'pnscripts-waymend-lite' ) . '</h1>';
		if ( 'rules' === $tab && '' === $action ) {
			printf( ' <a href="%s" class="page-title-action">%s</a>', esc_url( self::url( array( 'action' => 'new' ) ) ), esc_html__( 'Add redirect', 'pnscripts-waymend-lite' ) );
		}
		echo '<hr class="wp-header-end">';

		Messages::render_flashed();

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
				esc_url( self::url( array( 'tab' => $key ) ) ),
				$key === $tab ? ' nav-tab-active' : '',
				$key === $tab ? ' aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		switch ( $tab ) {
			case 'log':
				$this->render_log();
				break;
			case 'import':
				$this->render_import();
				break;
			case 'settings':
				$this->render_settings();
				break;
			default:
				if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
					$this->render_form();
				} else {
					$this->render_rules();
				}
		}
		echo '</div>';
	}

	private function render_rules(): void {
		$this->render_tester();

		$table = new Rules_Table( $this->plugin->rules );
		$table->prepare_items();
		echo '<form method="get">';
		printf( '<input type="hidden" name="page" value="%s"><input type="hidden" name="tab" value="rules">', esc_attr( self::SLUG ) );
		$table->views();
		$table->search_box( __( 'Search redirects', 'pnscripts-waymend-lite' ), 'waymend-search' );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( self::url( array( 'tab' => 'rules' ) ) ) . '">';
		$table->display();
		echo '</form>';
	}

	/**
	 * "Test a URL": shows which rule answers a URL and every hop after it.
	 */
	private function render_tester(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tool.
		$test = isset( $_GET['test_url'] ) ? sanitize_text_field( wp_unslash( $_GET['test_url'] ) ) : '';

		echo '<div class="waymend-tester">';
		echo '<form method="get" class="waymend-tester__form">';
		printf( '<input type="hidden" name="page" value="%s"><input type="hidden" name="tab" value="rules">', esc_attr( self::SLUG ) );
		printf(
			'<label for="waymend-test-url">%s</label> <input type="text" id="waymend-test-url" name="test_url" class="regular-text waymend-mono" value="%s" placeholder="/old-page/?ref=mail"> ',
			esc_html__( 'Test a URL', 'pnscripts-waymend-lite' ),
			esc_attr( $test )
		);
		submit_button( __( 'Trace', 'pnscripts-waymend-lite' ), 'secondary', '', false );
		echo '</form>';

		if ( '' !== $test ) {
			$home  = home_url( '/' );
			$start = \PNScripts\WaymendLite\Core\Path::local( $test, $home );
			if ( null === $start ) {
				$start = '/' === ( $test[0] ?? '' ) ? $test : '/' . $test;
			}
			$trace = ( new Loop_Detector( $home ) )->trace( $start, $this->plugin->rules->enabled() );

			echo '<ol class="waymend-trace">';
			printf( '<li><code>%s</code></li>', esc_html( $start ) );
			foreach ( $trace['hops'] as $hop ) {
				printf(
					'<li><span class="waymend-badge waymend-badge--%1$d">%1$d</span> <code>%2$s</code> <a href="%3$s">%4$s</a></li>',
					(int) $hop['status'],
					esc_html( $hop['to'] ),
					esc_url( self::url( array( 'action' => 'edit', 'id' => $hop['rule'] ) ) ),
					/* translators: %d: rule id. */
					esc_html( sprintf( __( 'rule #%d', 'pnscripts-waymend-lite' ), $hop['rule'] ) )
				);
			}
			echo '</ol>';

			if ( $trace['loop'] ) {
				echo '<p class="waymend-trace__verdict waymend-trace__verdict--bad">' . esc_html__( 'Loop: these rules send the visitor around in circles. Waymend stops at the URL it would redirect to itself, but fix the rules.', 'pnscripts-waymend-lite' ) . '</p>';
			} elseif ( ! $trace['hops'] ) {
				echo '<p class="waymend-trace__verdict">' . esc_html__( 'No rule matches this URL; WordPress handles it as usual.', 'pnscripts-waymend-lite' ) . '</p>';
			} elseif ( count( $trace['hops'] ) > 1 ) {
				/* translators: %d: number of redirects. */
				echo '<p class="waymend-trace__verdict waymend-trace__verdict--warn">' . esc_html( sprintf( _n( '%d redirect in a row. Point the first rule at the final URL to save visitors a round trip.', '%d redirects in a row. Point the first rule at the final URL to save visitors a round trip.', count( $trace['hops'] ), 'pnscripts-waymend-lite' ), count( $trace['hops'] ) ) ) . '</p>';
			} else {
				echo '<p class="waymend-trace__verdict waymend-trace__verdict--ok">' . esc_html__( 'One redirect, no loop.', 'pnscripts-waymend-lite' ) . '</p>';
			}
		}
		echo '</div>';
	}

	private function render_form(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the form itself is protected on submit.
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$log_id = isset( $_GET['log_id'] ) ? absint( $_GET['log_id'] ) : 0;
		// phpcs:enable

		$rule = $id ? $this->plugin->rules->find( $id ) : null;
		if ( $id && null === $rule ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'That redirect does not exist.', 'pnscripts-waymend-lite' ) . '</p></div>';

			return;
		}

		$suggested = '';
		$draft     = get_transient( 'waymend_lite_draft_' . get_current_user_id() );
		if ( is_array( $draft ) ) {
			// The form came back with errors: show what was typed.
			delete_transient( 'waymend_lite_draft_' . get_current_user_id() );
			if ( (int) ( $draft['id'] ?? 0 ) === $id ) {
				$rule   = Rule::from_array( $draft );
				$log_id = (int) ( $draft['log_id'] ?? $log_id );
			}
		}
		if ( null === $rule ) {
			$rule = new Rule( '', '', Rule::EXACT, (int) Settings::get( 'default_status' ) );
			if ( $log_id ) {
				$entry = $this->plugin->log->find( $log_id );
				if ( $entry ) {
					$rule->source = (string) $entry['path'];
					$suggested    = $this->plugin->log->suggest_target( (string) $entry['path'] );
					$rule->target = $suggested;
				}
			}
		}

		$statuses = array(
			301 => __( '301 Moved permanently', 'pnscripts-waymend-lite' ),
			302 => __( '302 Found (temporary)', 'pnscripts-waymend-lite' ),
			307 => __( '307 Temporary redirect (keeps the method)', 'pnscripts-waymend-lite' ),
			410 => __( '410 Gone (no target)', 'pnscripts-waymend-lite' ),
		);
		$matches  = array(
			Rule::EXACT  => __( 'Exact: this URL only', 'pnscripts-waymend-lite' ),
			Rule::PREFIX => __( 'Prefix: this path and everything below it; the rest is appended to the target', 'pnscripts-waymend-lite' ),
		);
		$queries  = array(
			Rule::QUERY_IGNORE => __( 'Ignore: match the path, drop the visitor\'s query string', 'pnscripts-waymend-lite' ),
			Rule::QUERY_PASS   => __( 'Pass: match the path, append the visitor\'s query string to the target', 'pnscripts-waymend-lite' ),
			Rule::QUERY_EXACT  => __( 'Exact: the source\'s query parameters must be present, in any order', 'pnscripts-waymend-lite' ),
		);

		printf( '<h2>%s</h2>', $rule->id ? esc_html__( 'Edit redirect', 'pnscripts-waymend-lite' ) : esc_html__( 'Add redirect', 'pnscripts-waymend-lite' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="waymend-form">';
		echo '<input type="hidden" name="action" value="waymend_lite_save_rule">';
		printf( '<input type="hidden" name="id" value="%d">', (int) $rule->id );
		printf( '<input type="hidden" name="log_id" value="%d">', (int) $log_id );
		wp_nonce_field( 'waymend_lite_save_rule' );

		echo '<table class="form-table" role="presentation"><tbody>';

		printf(
			'<tr><th scope="row"><label for="waymend-source">%s</label></th><td><input type="text" id="waymend-source" name="source" class="large-text waymend-mono" value="%s" required><p class="description">%s</p></td></tr>',
			esc_html__( 'Source', 'pnscripts-waymend-lite' ),
			esc_attr( $rule->source ),
			esc_html__( 'A path on this site, such as /old-page/.', 'pnscripts-waymend-lite' )
		);
		printf(
			'<tr><th scope="row"><label for="waymend-target">%s</label></th><td><input type="text" id="waymend-target" name="target" class="large-text waymend-mono" value="%s"><p class="description">%s</p>%s</td></tr>',
			esc_html__( 'Target', 'pnscripts-waymend-lite' ),
			esc_attr( $rule->target ),
			esc_html__( 'A path on this site (/new-page/) or a full URL. Leave empty for 410 Gone.', 'pnscripts-waymend-lite' ),
			'' !== $suggested ? '<p class="waymend-suggested">' . esc_html__( 'Suggested from a published item with a matching slug. Check it before saving.', 'pnscripts-waymend-lite' ) . '</p>' : ''
		);

		echo '<tr><th scope="row"><label for="waymend-status">' . esc_html__( 'Status', 'pnscripts-waymend-lite' ) . '</label></th><td><select id="waymend-status" name="status">';
		foreach ( $statuses as $code => $label ) {
			printf( '<option value="%d"%s>%s</option>', (int) $code, selected( $rule->status, $code, false ), esc_html( $label ) );
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Match', 'pnscripts-waymend-lite' ) . '</th><td><fieldset>';
		foreach ( $matches as $value => $label ) {
			printf( '<label><input type="radio" name="match_type" value="%s"%s> %s</label><br>', esc_attr( $value ), checked( $rule->match_type, $value, false ), esc_html( $label ) );
		}
		printf(
			'<label class="waymend-inline"><input type="checkbox" name="case_sensitive" value="1"%s> %s</label>',
			checked( $rule->case_sensitive, true, false ),
			esc_html__( 'Case-sensitive', 'pnscripts-waymend-lite' )
		);
		echo '</fieldset></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Query string', 'pnscripts-waymend-lite' ) . '</th><td><fieldset>';
		foreach ( $queries as $value => $label ) {
			printf( '<label><input type="radio" name="query_mode" value="%s"%s> %s</label><br>', esc_attr( $value ), checked( $rule->query_mode, $value, false ), esc_html( $label ) );
		}
		echo '</fieldset></td></tr>';

		printf(
			'<tr><th scope="row">%s</th><td><label><input type="checkbox" name="enabled" value="1"%s> %s</label></td></tr>',
			esc_html__( 'Enabled', 'pnscripts-waymend-lite' ),
			checked( $rule->enabled, true, false ),
			esc_html__( 'Active on the site', 'pnscripts-waymend-lite' )
		);
		echo '</tbody></table>';

		submit_button( $rule->id ? __( 'Save redirect', 'pnscripts-waymend-lite' ) : __( 'Add redirect', 'pnscripts-waymend-lite' ) );
		echo '</form>';
	}

	private function render_log(): void {
		$table = new Log_Table( $this->plugin->log );
		$table->prepare_items();

		if ( ! Settings::get( 'log_404' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( '404 logging is switched off in the settings.', 'pnscripts-waymend-lite' ) . '</p></div>';
		}

		echo '<form method="get">';
		printf( '<input type="hidden" name="page" value="%s"><input type="hidden" name="tab" value="log">', esc_attr( self::SLUG ) );
		$table->search_box( __( 'Search 404s', 'pnscripts-waymend-lite' ), 'waymend-404-search' );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( self::url( array( 'tab' => 'log' ) ) ) . '">';
		$table->display();
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="waymend-clear">';
		echo '<input type="hidden" name="action" value="waymend_lite_clear_404">';
		wp_nonce_field( 'waymend_lite_clear_404' );
		submit_button( __( 'Clear the whole log', 'pnscripts-waymend-lite' ), 'delete', 'submit', false, array( 'onclick' => 'return confirm(' . wp_json_encode( __( 'Delete every logged 404?', 'pnscripts-waymend-lite' ) ) . ');' ) );
		echo '</form>';
	}

	private function render_import(): void {
		echo '<div class="waymend-columns">';

		$this->render_link_check();

		echo '<div class="waymend-card"><h2>' . esc_html__( 'Export CSV', 'pnscripts-waymend-lite' ) . '</h2>';
		echo '<p>' . esc_html__( 'Downloads every rule with its hit count and last hit, in the same format the importer reads.', 'pnscripts-waymend-lite' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="waymend_lite_export">';
		wp_nonce_field( 'waymend_lite_export' );
		submit_button( __( 'Download CSV', 'pnscripts-waymend-lite' ), 'secondary', 'submit', false );
		echo '</form></div>';

		echo '</div>';
	}

	/**
	 * "Check old URLs": the migration check with its last result.
	 */
	private function render_link_check(): void {
		echo '<div class="waymend-card waymend-card--wide" id="waymend-check"><h2>' . esc_html__( 'Check old URLs', 'pnscripts-waymend-lite' ) . '</h2>';
		echo '<p>' . esc_html__( 'Moving or redesigning a site? Paste the old addresses (from the old sitemap, Search Console or an analytics export), one per line. The old domain is fine: each path is checked on this site, the way a visitor following an old link would land.', 'pnscripts-waymend-lite' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="waymend_lite_check_links">';
		wp_nonce_field( 'waymend_lite_check_links' );
		printf(
			'<p><label for="waymend-check-urls" class="screen-reader-text">%1$s</label><textarea id="waymend-check-urls" name="urls" rows="6" class="large-text code" placeholder="%2$s"></textarea></p>',
			esc_html__( 'Old URLs, one per line', 'pnscripts-waymend-lite' ),
			esc_attr( "https://old.example.com/about-us
/products/blue-chair
/blog/2019/05/hello" )
		);
		submit_button( __( 'Check URLs', 'pnscripts-waymend-lite' ), 'secondary', 'submit', false );
		/* translators: %d: maximum number of URLs per check. */
		echo ' <span class="description">' . esc_html( sprintf( __( 'Up to %d URLs per check.', 'pnscripts-waymend-lite' ), Link_Check::MAX_URLS ) ) . '</span>';
		echo '</form>';

		$report = get_transient( self::CHECK_TRANSIENT . get_current_user_id() );
		if ( ! is_array( $report ) || empty( $report['results'] ) ) {
			echo '</div>';
			return;
		}

		$labels = self::check_labels();
		$counts = array_count_values( array_column( $report['results'], 'verdict' ) );
		echo '<p class="waymend-check-summary">';
		foreach ( $labels as $verdict => $label ) {
			if ( ! empty( $counts[ $verdict ] ) ) {
				printf( '<span class="waymend-badge waymend-badge--%1$s">%2$s: %3$d</span> ', esc_attr( $verdict ), esc_html( $label ), (int) $counts[ $verdict ] );
			}
		}
		if ( ! empty( $report['unchecked'] ) ) {
			/* translators: %d: number of URLs not checked because of the time limit. */
			echo '<span class="waymend-badge">' . esc_html( sprintf( __( 'Not checked (time limit, run again): %d', 'pnscripts-waymend-lite' ), (int) $report['unchecked'] ) ) . '</span>';
		}
		echo '</p>';

		$need = ( $counts[ Link_Check::MISSING ] ?? 0 ) + ( $counts[ Link_Check::BROKEN ] ?? 0 );
		if ( $need > 0 ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="waymend_lite_check_links_csv">';
			wp_nonce_field( 'waymend_lite_check_links_csv' );
			/* translators: %d: number of URLs that need a redirect. */
			submit_button( sprintf( _n( 'Download the %d URL that needs a redirect (CSV)', 'Download the %d URLs that need a redirect (CSV)', $need, 'pnscripts-waymend-lite' ), $need ), 'primary', 'submit', false );
			echo '</form>';
		}

		echo '<table class="widefat striped waymend-check-table"><thead><tr><th scope="col">' . esc_html__( 'Old URL', 'pnscripts-waymend-lite' ) . '</th><th scope="col">' . esc_html__( 'Result', 'pnscripts-waymend-lite' ) . '</th><th scope="col">' . esc_html__( 'Lands on', 'pnscripts-waymend-lite' ) . '</th></tr></thead><tbody>';
		$order = array_flip( array( Link_Check::MISSING, Link_Check::BROKEN, Link_Check::LOOP, Link_Check::UNKNOWN, Link_Check::EXTERNAL, Link_Check::GONE, Link_Check::REDIRECT, Link_Check::OK ) );
		$rows  = $report['results'];
		usort( $rows, static function ( array $a, array $b ) use ( $order ) {
			return ( $order[ $a['verdict'] ] ?? 9 ) <=> ( $order[ $b['verdict'] ] ?? 9 );
		} );
		foreach ( array_slice( $rows, 0, 300 ) as $row ) {
			$lands = $row['final'];
			if ( Link_Check::MISSING === $row['verdict'] && '' !== (string) ( $row['suggestion'] ?? '' ) ) {
				/* translators: %s: suggested target path. */
				$lands = sprintf( __( 'Suggested: %s', 'pnscripts-waymend-lite' ), $row['suggestion'] );
			} elseif ( null !== $row['status'] && $row['status'] >= 300 && $row['status'] < 400 ) {
				/* translators: %d: HTTP status code of WordPress's own redirect. */
				$lands .= ' ' . sprintf( __( '(%d, WordPress redirects it further)', 'pnscripts-waymend-lite' ), (int) $row['status'] );
			} elseif ( null !== $row['status'] && Link_Check::OK !== $row['verdict'] ) {
				$lands .= ' (' . (int) $row['status'] . ')';
			}
			printf(
				'<tr><td><code>%1$s</code></td><td><span class="waymend-badge waymend-badge--%2$s">%3$s</span></td><td>%4$s</td></tr>',
				esc_html( $row['uri'] ),
				esc_attr( $row['verdict'] ),
				esc_html( $labels[ $row['verdict'] ] ?? $row['verdict'] ),
				esc_html( $lands )
			);
		}
		echo '</tbody></table>';
		if ( count( $rows ) > 300 ) {
			echo '<p class="description">' . esc_html__( 'The first 300 results are shown; the CSV has every URL that needs a redirect.', 'pnscripts-waymend-lite' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * @return array<string, string>
	 */
	private static function check_labels(): array {
		return array(
			Link_Check::MISSING  => __( 'Missing, needs a redirect', 'pnscripts-waymend-lite' ),
			Link_Check::BROKEN   => __( 'Redirected to a missing page', 'pnscripts-waymend-lite' ),
			Link_Check::LOOP     => __( 'Redirect loop', 'pnscripts-waymend-lite' ),
			Link_Check::UNKNOWN  => __( 'Could not be checked', 'pnscripts-waymend-lite' ),
			Link_Check::EXTERNAL => __( 'Sent to another site', 'pnscripts-waymend-lite' ),
			Link_Check::GONE     => __( 'Gone (410) on purpose', 'pnscripts-waymend-lite' ),
			Link_Check::REDIRECT => __( 'Redirected, works', 'pnscripts-waymend-lite' ),
			Link_Check::OK       => __( 'Works', 'pnscripts-waymend-lite' ),
		);
	}

	public function handle_check_links(): void {
		$this->guard( 'waymend_lite_check_links' );
		$text  = isset( $_POST['urls'] ) ? sanitize_textarea_field( wp_unslash( $_POST['urls'] ) ) : '';
		$check = new Link_Check( home_url( '/' ) );
		$list  = $check->parse_list( $text );
		if ( ! $list['uris'] ) {
			Messages::flash( 'error', __( 'Paste at least one old URL or path, one per line.', 'pnscripts-waymend-lite' ) );
			$this->back( array( 'tab' => 'import' ) );
		}

		$report = ( new Link_Checker( $this->plugin ) )->run( $list['uris'] );

		set_transient( self::CHECK_TRANSIENT . get_current_user_id(), $report, DAY_IN_SECONDS );
		if ( $list['skipped'] ) {
			/* translators: %d: number of lines that are not URLs. */
			Messages::flash( 'warning', sprintf( _n( '%d line is not a URL and was skipped.', '%d lines are not URLs and were skipped.', count( $list['skipped'] ), 'pnscripts-waymend-lite' ), count( $list['skipped'] ) ) );
		}
		if ( $list['truncated'] ) {
			/* translators: %d: maximum number of URLs per check. */
			Messages::flash( 'warning', sprintf( __( 'Only the first %d URLs were checked.', 'pnscripts-waymend-lite' ), Link_Check::MAX_URLS ) );
		}
		wp_safe_redirect( self::url( array( 'tab' => 'import' ) ) . '#waymend-check' );
		exit;
	}

	public function handle_check_links_csv(): void {
		$this->guard( 'waymend_lite_check_links_csv' );
		$report = get_transient( self::CHECK_TRANSIENT . get_current_user_id() );
		$csv    = Link_Check::needs_rules_csv( is_array( $report ) && isset( $report['results'] ) ? $report['results'] : array() );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="old-urls-to-redirect-' . gmdate( 'Y-m-d' ) . '.csv"' );
		echo "\xEF\xBB\xBF" . $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, not HTML.
		exit;
	}

	private function render_settings(): void {
		$s = Settings::all();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="waymend_lite_save_settings">';
		wp_nonce_field( 'waymend_lite_save_settings' );
		echo '<table class="form-table" role="presentation"><tbody>';
		printf(
			'<tr><th scope="row">%s</th><td><label><input type="checkbox" name="log_404" value="1"%s> %s</label><p class="description">%s</p></td></tr>',
			esc_html__( '404 log', 'pnscripts-waymend-lite' ),
			checked( $s['log_404'], true, false ),
			esc_html__( 'Record URLs that return 404', 'pnscripts-waymend-lite' ),
			esc_html__( 'Stores the path, the number of hits, first and last time and the last referrer. No IP addresses or user agents.', 'pnscripts-waymend-lite' )
		);
		printf(
			'<tr><th scope="row"><label for="waymend-max">%s</label></th><td><input type="number" min="100" max="100000" id="waymend-max" name="log_max_rows" value="%d" class="small-text"><p class="description">%s</p></td></tr>',
			esc_html__( 'Keep at most', 'pnscripts-waymend-lite' ),
			(int) $s['log_max_rows'],
			esc_html__( 'distinct 404 URLs; older ones are removed once a day.', 'pnscripts-waymend-lite' )
		);
		printf(
			'<tr><th scope="row"><label for="waymend-ignore">%s</label></th><td><textarea id="waymend-ignore" name="ignore_patterns" rows="7" class="large-text code">%s</textarea><p class="description">%s</p></td></tr>',
			esc_html__( 'Do not log', 'pnscripts-waymend-lite' ),
			esc_textarea( $s['ignore_patterns'] ),
			esc_html__( 'One pattern per line, * matches anything. The defaults skip common vulnerability scans such as *.php and /.env.', 'pnscripts-waymend-lite' )
		);
		echo '<tr><th scope="row"><label for="waymend-default-status">' . esc_html__( 'Default status for new redirects', 'pnscripts-waymend-lite' ) . '</label></th><td><select id="waymend-default-status" name="default_status">';
		foreach ( array( 301, 302, 307, 410 ) as $code ) {
			printf( '<option value="%1$d"%2$s>%1$d</option>', (int) $code, selected( $s['default_status'], $code, false ) );
		}
		echo '</select></td></tr>';
		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	/* ------------------------------------------------------------------ */
	/* Handlers (admin-post.php)                                            */
	/* ------------------------------------------------------------------ */

	private function guard( string $nonce_action ): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage redirects.', 'pnscripts-waymend-lite' ), 403 );
		}
		check_admin_referer( $nonce_action );
	}

	private function back( array $args = array() ): void {
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	public function handle_save_rule(): void {
		$this->guard( 'waymend_lite_save_rule' );

		$input = wp_unslash( $_POST );
		$rule  = new Rule(
			// Paths and patterns are kept verbatim (a regex needs its backslashes); they are escaped on output.
			isset( $input['source'] ) ? trim( (string) $input['source'] ) : '',
			isset( $input['target'] ) ? trim( (string) $input['target'] ) : '',
			isset( $input['match_type'] ) ? sanitize_key( $input['match_type'] ) : Rule::EXACT,
			isset( $input['status'] ) ? absint( $input['status'] ) : 301,
			isset( $input['query_mode'] ) ? sanitize_key( $input['query_mode'] ) : Rule::QUERY_IGNORE,
			! empty( $input['case_sensitive'] ),
			! empty( $input['enabled'] ),
			isset( $input['id'] ) ? absint( $input['id'] ) : 0,
			0,
			null,
			isset( $input['position'] ) ? (int) $input['position'] : 0
		);
		$rule   = $this->strip_controls( $rule );
		$log_id = isset( $input['log_id'] ) ? absint( $input['log_id'] ) : 0;

		$validator = new Rule_Validator( home_url( '/' ) );
		$rule      = $validator->normalize( $rule );
		$result    = $validator->validate( $rule, $this->plugin->rules->all() );

		if ( $result['errors'] ) {
			foreach ( $result['errors'] as $error ) {
				Messages::flash( 'error', Messages::problem( $error ) );
			}
			set_transient( 'waymend_lite_draft_' . get_current_user_id(), array_merge( $rule->to_array(), array( 'log_id' => $log_id ) ), 5 * MINUTE_IN_SECONDS );
			$this->back( $rule->id ? array( 'action' => 'edit', 'id' => $rule->id ) : array( 'action' => 'new' ) );
		}

		$existing = $rule->id ? $this->plugin->rules->find( $rule->id ) : null;
		if ( $existing ) {
			$rule->hits     = $existing->hits;
			$rule->last_hit = $existing->last_hit;
		}
		$id = $this->plugin->rules->save( $rule );

		if ( $log_id ) {
			$this->plugin->log->delete( array( $log_id ) );
		}
		$this->plugin->log->delete_path( $rule->source );

		Messages::flash( 'success', $existing ? __( 'Redirect saved.', 'pnscripts-waymend-lite' ) : __( 'Redirect added.', 'pnscripts-waymend-lite' ) );
		foreach ( $result['warnings'] as $warning ) {
			Messages::flash( 'warning', Messages::problem( $warning ) );
		}
		$this->back( array( 'highlight' => $id ) );
	}

	public function handle_delete_rule(): void {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$this->guard( 'waymend_lite_delete_rule_' . $id );
		$this->plugin->rules->delete( array( $id ) );
		Messages::flash( 'success', __( 'Redirect deleted.', 'pnscripts-waymend-lite' ) );
		$this->back();
	}

	public function handle_toggle_rule(): void {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$this->guard( 'waymend_lite_toggle_rule_' . $id );
		$rule = $this->plugin->rules->find( $id );
		if ( $rule ) {
			if ( ! $rule->enabled ) {
				$check = ( new Rule_Validator( home_url( '/' ) ) )->validate( $rule, $this->plugin->rules->enabled() );
				if ( in_array( 'loop', array_column( $check['errors'], 'code' ), true ) ) {
					Messages::flash( 'error', Messages::problem( array( 'code' => 'loop', 'detail' => '' ) ) );
					$this->back();
				}
			}
			$this->plugin->rules->set_enabled( array( $id ), ! $rule->enabled );
			Messages::flash( 'success', $rule->enabled ? __( 'Redirect disabled.', 'pnscripts-waymend-lite' ) : __( 'Redirect enabled.', 'pnscripts-waymend-lite' ) );
		}
		$this->back();
	}

	/**
	 * Bulk actions of both list tables (nonce "bulk-<plural>" is printed by WP_List_Table).
	 */
	public function handle_bulk(): void {
		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || empty( $_POST['ids'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked right below.
		$tab    = isset( $_GET['tab'] ) && 'log' === $_GET['tab'] ? 'log' : 'rules';
		$this->guard( 'log' === $tab ? 'bulk-waymend_lite_404s' : 'bulk-waymend_lite_rules' );
		$action = $this->bulk_action();
		$ids    = array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) );

		if ( 'log' === $tab ) {
			if ( 'delete' === $action ) {
				$count = $this->plugin->log->delete( $ids );
				/* translators: %d: number of log entries. */
				Messages::flash( 'success', sprintf( _n( '%d entry deleted.', '%d entries deleted.', $count, 'pnscripts-waymend-lite' ), $count ) );
			}
			$this->back( array( 'tab' => 'log' ) );
		}

		switch ( $action ) {
			case 'delete':
				$count = $this->plugin->rules->delete( $ids );
				/* translators: %d: number of redirects. */
				Messages::flash( 'success', sprintf( _n( '%d redirect deleted.', '%d redirects deleted.', $count, 'pnscripts-waymend-lite' ), $count ) );
				break;
			case 'enable':
			case 'disable':
				$count = $this->plugin->rules->set_enabled( $ids, 'enable' === $action );
				/* translators: %d: number of redirects. */
				Messages::flash( 'success', sprintf( _n( '%d redirect updated.', '%d redirects updated.', $count, 'pnscripts-waymend-lite' ), $count ) );
				break;
			case 'reset_hits':
				$count = $this->plugin->rules->reset_hits( $ids );
				/* translators: %d: number of redirects. */
				Messages::flash( 'success', sprintf( _n( 'Hit count reset for %d redirect.', 'Hit counts reset for %d redirects.', $count, 'pnscripts-waymend-lite' ), $count ) );
				break;
		}
		$this->back();
	}

	public function handle_delete_404(): void {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$this->guard( 'waymend_lite_delete_404_' . $id );
		$this->plugin->log->delete( array( $id ) );
		$this->back( array( 'tab' => 'log' ) );
	}

	public function handle_clear_404(): void {
		$this->guard( 'waymend_lite_clear_404' );
		$this->plugin->log->clear();
		Messages::flash( 'success', __( 'The 404 log is empty.', 'pnscripts-waymend-lite' ) );
		$this->back( array( 'tab' => 'log' ) );
	}

	/**
	 * Imports CSV text; shared by the admin screen and WP-CLI-style scripts.
	 *
	 * @return array{added: int, updated: int, skipped: int, errors: array<int, string>}
	 */
	public function import_csv( string $csv, bool $update ): array {
		$parsed    = Csv::import( $csv );
		$validator = new Rule_Validator( home_url( '/' ) );
		$existing  = $this->plugin->rules->all();
		$report    = array( 'added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => $parsed['errors'] );

		foreach ( $parsed['rules'] as $entry ) {
			$rule   = $validator->normalize( $this->strip_controls( $entry['rule'] ) );
			$result = $validator->validate( $rule, $existing );
			$codes  = array_column( $result['errors'], 'code' );

			if ( array( 'duplicate' ) === $codes ) {
				if ( ! $update ) {
					++$report['skipped'];
					continue;
				}
				$rule->id = (int) $result['errors'][0]['detail'];
				$result   = $validator->validate( $rule, $existing );
				$codes    = array_column( $result['errors'], 'code' );
			}
			if ( $codes ) {
				$report['errors'][ $entry['line'] ] = implode( ' ', array_map( array( Messages::class, 'problem' ), $result['errors'] ) );
				continue;
			}

			$was_update = $rule->id > 0;
			$rule->id   = $this->plugin->rules->save( $rule );
			$existing   = array_values( array_filter( $existing, static function ( Rule $r ) use ( $rule ) {
				return $r->id !== $rule->id;
			} ) );
			$existing[] = $rule;
			++$report[ $was_update ? 'updated' : 'added' ];
		}
		ksort( $report['errors'] );

		return $report;
	}

	public function handle_export(): void {
		$this->guard( 'waymend_lite_export' );
		$csv = Csv::export( $this->plugin->rules->all() );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="redirects-' . gmdate( 'Y-m-d' ) . '.csv"' );
		echo "\xEF\xBB\xBF" . $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, not HTML.
		exit;
	}

	public function handle_save_settings(): void {
		$this->guard( 'waymend_lite_save_settings' );
		Settings::save( wp_unslash( $_POST ) );
		Messages::flash( 'success', __( 'Settings saved.', 'pnscripts-waymend-lite' ) );
		$this->back( array( 'tab' => 'settings' ) );
	}

	private function bulk_action(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in guard() by the caller.
		foreach ( array( 'action', 'action2' ) as $key ) {
			if ( ! empty( $_POST[ $key ] ) && '-1' !== $_POST[ $key ] ) {
				return sanitize_key( wp_unslash( $_POST[ $key ] ) );
			}
		}
		// phpcs:enable

		return '';
	}

	/**
	 * Removes control characters and limits the length of user-entered paths and URLs.
	 */
	private function strip_controls( Rule $rule ): Rule {
		$rule->source = substr( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', $rule->source ), 0, 2000 );
		$rule->target = substr( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', $rule->target ), 0, 2000 );

		return $rule;
	}
}
