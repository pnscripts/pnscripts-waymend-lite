<?php
/**
 * Seeds the disposable test site (run with: wp eval-file tools/smoke-seed.php).
 * Uses the plugin's own classes, the way the admin screens do.
 */

use PNScripts\WaymendLite\Core\Rule;
use PNScripts\WaymendLite\WordPress\Admin\Admin;
use PNScripts\WaymendLite\WordPress\Plugin;

$plugin = Plugin::instance();

foreach ( array( 'Winter tyre guide' => 'winter-tyre-guide', 'Contact' => 'contact', 'Shop' => 'shop', 'Summer sale 2026' => 'summer-sale-2026' ) as $title => $slug ) {
	if ( ! get_page_by_path( $slug, OBJECT, array( 'post', 'page' ) ) ) {
		wp_insert_post( array( 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish', 'post_type' => 'contact' === $slug || 'shop' === $slug ? 'page' : 'post', 'post_content' => 'Test content.' ) );
	}
}

$csv = <<<CSV
source,target,match,status,query,case_sensitive,enabled
/old-contact,/contact/,exact,301,ignore,0,1
/tyres/winter,/winter-tyre-guide/,exact,301,pass,0,1
/old-shop,/shop,prefix,301,ignore,0,1
^/blog/(\d{4})/(\d{2})/([^/]+)/?$,/\$3/,regex,301,ignore,0,1
/discontinued-product,,exact,410,ignore,0,1
/promo,https://example.org/promo,exact,302,pass,0,1
/a-to-b,/b-to-c,exact,301,ignore,0,1
/b-to-c,/contact/,exact,301,ignore,0,1
/self,/self/,exact,301,ignore,0,1
CSV;

$admin  = new Admin( $plugin );
$report = $admin->import_csv( $csv, false );
echo wp_json_encode( $report ), "\n";

// A loop the validator refused can still exist if someone edits the database by hand:
// insert one directly to prove the runtime guard.
$plugin->rules->save( new Rule( '/manual-loop', '/manual-loop/', Rule::EXACT, 301 ) );
