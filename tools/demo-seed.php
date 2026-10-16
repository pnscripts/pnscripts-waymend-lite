<?php
/**
 * Demo content for the screenshots (wp eval-file tools/demo-seed.php), on the disposable test site.
 */

use PNScripts\WaymendLite\WordPress\Admin\Admin;
use PNScripts\WaymendLite\WordPress\Plugin;

$plugin = Plugin::instance();
$pages  = array(
	'contact'                  => array( 'Contact', 'page' ),
	'shop'                     => array( 'Shop', 'page' ),
	'about-us'                 => array( 'About us', 'page' ),
	'winter-tyre-guide'        => array( 'Winter tyre guide', 'post' ),
	'how-to-store-summer-tyres' => array( 'How to store summer tyres', 'post' ),
	'tyre-pressure-chart'      => array( 'Tyre pressure chart', 'post' ),
);
foreach ( $pages as $slug => $info ) {
	if ( ! get_page_by_path( $slug, OBJECT, array( 'post', 'page' ) ) ) {
		wp_insert_post( array( 'post_title' => $info[0], 'post_name' => $slug, 'post_status' => 'publish', 'post_type' => $info[1], 'post_content' => 'Demo content.' ) );
	}
}

$csv = <<<CSV
source,target,match,status,query,case_sensitive,enabled
/kontakti,/contact/,exact,301,ignore,0,1
/old-shop,/shop,prefix,301,ignore,0,1
/blog/2019/11/winter-tyre-guide,/winter-tyre-guide/,exact,301,ignore,0,1
/tyres/winter,/winter-tyre-guide/,exact,301,pass,0,1
/discontinued/rim-cleaner,,exact,410,ignore,0,1
/black-friday,/shop/?sale=1,exact,302,pass,0,1
/team,/about-us/,exact,301,ignore,0,1
/about,/team,exact,301,ignore,0,1
/forum,https://community.example.org/,prefix,307,ignore,0,0
CSV;

$report = ( new Admin( $plugin ) )->import_csv( $csv, false );
echo wp_json_encode( $report ), "\n";
