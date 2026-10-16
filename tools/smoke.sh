#!/usr/bin/env bash
# End-to-end check in a real WordPress (build/wp, SQLite): activates the built plugin,
# seeds rules through the plugin's own importer and checks real HTTP responses.
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP_DIR="$ROOT/build/wp"
PORT="${WP_PORT:-8811}"
BASE="http://127.0.0.1:$PORT"
wp() { php -d memory_limit=512M "$ROOT/build/cache/wp-cli.phar" --path="$WP_DIR" "$@"; }
fail=0
q() { WAYMEND_LITE_SQL="$1" wp eval 'global $wpdb; echo $wpdb->get_var( getenv( "WAYMEND_LITE_SQL" ) );'; }
# Plugin tables, on SQLite and on MySQL/MariaDB (DB=mysql).
tables() {
	if [ "${DB:-sqlite}" = sqlite ]; then q "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name LIKE 'wp_waymend_%'"
	else q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'wp\\_waymend\\_lite\\_%'"; fi
}
check() { # label, expected, actual
	if [ "$2" = "$3" ]; then echo "ok   $1"; else echo "FAIL $1: expected [$2], got [$3]"; fail=1; fi
}

wp plugin activate pnscripts-waymend-lite --quiet
check "plugin active" "active" "$(wp plugin get pnscripts-waymend-lite --field=status)"
check "tables created" "2" "$(tables)"
echo "seed: $(wp eval-file "$ROOT/tools/smoke-seed.php")"

php -S "127.0.0.1:$PORT" -t "$WP_DIR" "$ROOT/tools/router.php" >"$ROOT/build/server.log" 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null' EXIT
for i in $(seq 1 30); do curl -s -o /dev/null "$BASE/" && break; sleep 1; done

req() { curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$BASE$1"; }
check "exact 301"                  "301 $BASE/contact/"                         "$(req /old-contact)"
check "exact, trailing slash, case" "301 $BASE/contact/"                        "$(req /OLD-Contact/)"
check "query passed on"            "301 $BASE/winter-tyre-guide/?utm_source=mail" "$(req '/tyres/winter?utm_source=mail')"
check "prefix appends the rest"    "301 $BASE/shop/hats/red"                    "$(req /old-shop/hats/red)"
check "regex rules are not in Lite"   "0" "$(q "SELECT COUNT(*) FROM wp_waymend_lite_rules WHERE match_type='regex'")"
check "410 gone"                   "410 "                                       "$(req /discontinued-product)"
check "302 external, query passed" "302 https://example.org/promo?ref=x"        "$(req '/promo?ref=x')"
check "chain is followed per hop"  "301 $BASE/b-to-c"                           "$(req /a-to-b)"
check "runtime self-loop guard"    "404"                                        "$(req /manual-loop | tr -d ' ')"
check "normal page untouched"      "200 "                                       "$(req /contact/)"
check "X-Redirect-By header"       "Waymend" "$(curl -sI "$BASE/old-contact" | tr -d '\r' | awk -F': ' 'tolower($1)=="x-redirect-by"{print $2}')"

req /this-page-never-existed >/dev/null
req /this-page-never-existed >/dev/null
req /wp-config-backup.php >/dev/null
req /summer-sale >/dev/null
check "404 logged with hits"       "2" "$(q "SELECT hits FROM wp_waymend_lite_404 WHERE path='/this-page-never-existed'")"
check "ignored pattern not logged" "0" "$(q "SELECT COUNT(*) FROM wp_waymend_lite_404 WHERE path LIKE '%.php'")"
check "redirected 404 not logged"  "0" "$(q "SELECT COUNT(*) FROM wp_waymend_lite_404 WHERE path='/old-contact'")"
check "hits counted (2 GET + 1 HEAD)" "3" "$(q "SELECT hits FROM wp_waymend_lite_rules WHERE source='/old-contact'")"
check "import refused the loop"    "0" "$(q "SELECT COUNT(*) FROM wp_waymend_lite_rules WHERE source='/self'")"
COOKIE="$(wp eval '$e = time() + 600; $h = md5( get_option( "siteurl" ) ); echo "wordpress_$h=" . wp_generate_auth_cookie( 1, $e, "auth" ) . "; wordpress_logged_in_$h=" . wp_generate_auth_cookie( 1, $e, "logged_in" );')"
admin() { curl -s -b "$COOKIE" "$BASE/wp-admin/tools.php?page=pnscripts-waymend-lite$1"; }
for tab in "&tab=rules" "&tab=log" "&tab=import" "&tab=settings" "&tab=rules&action=new" "&tab=rules&test_url=/a-to-b"; do
	html="$(admin "$tab")"
	if grep -q 'class="wrap waymend"' <<< "$html" && ! grep -qi 'fatal error\|warning:' <<< "$html"; then echo "ok   admin screen $tab renders"; else echo "FAIL admin screen $tab"; fail=1; fi
done
grep -q '2 redirects in a row' <<< "$(admin '&tab=rules&test_url=/a-to-b')" && echo "ok   URL tracer shows the chain" || { echo "FAIL URL tracer"; fail=1; }
check "not a symlink (installed from ZIP)" "no" "$([ -L "$WP_DIR/wp-content/plugins/pnscripts-waymend-lite" ] && echo yes || echo no)"
wp option update WPLANG bg_BG --quiet
check "Bulgarian translation loads"  "Пренасочвания" "$(wp eval 'echo __( "Redirects", "pnscripts-waymend-lite" );')"
grep -q 'Проверка на адрес' <<< "$(admin '&tab=rules')" && echo "ok   admin screen in Bulgarian" || { echo "FAIL admin screen in Bulgarian"; fail=1; }
wp option update WPLANG '' --quiet
check "suggested target"           "/summer-sale-2026/" "$(wp eval 'echo PNScripts\WaymendLite\WordPress\Plugin::instance()->log->suggest_target("/summer-sale");')"

# "Check old URLs": real rules, real pages (loopback HEAD to this server), 404s of the check not logged.
LC="$(wp eval '$p = PNScripts\WaymendLite\WordPress\Plugin::instance(); $c = new PNScripts\WaymendLite\Core\Link_Check( home_url( "/" ) ); $l = $c->parse_list( "https://old-site.example.net/old-contact\n/contact/\n/discontinued-product\n/this-is-missing-xyz\n/summer-sale\n/promo\n/a-to-b\nmailto:x@example.com" ); $r = ( new PNScripts\WaymendLite\WordPress\Link_Checker( $p ) )->run( $l["uris"] ); foreach ( $r["results"] as $x ) { echo $x["uri"], "=", $x["verdict"], isset( $x["suggestion"] ) && "" !== $x["suggestion"] ? ">" . $x["suggestion"] : "", " "; } echo count( $l["skipped"] );')"
check "check old URLs: verdicts" "/old-contact=redirect /contact/=ok /discontinued-product=gone /this-is-missing-xyz=missing /summer-sale=ok /promo=external /a-to-b=redirect 1" "$LC"
check "check old URLs: WordPress's own guess redirect counts as working" "301" "$(curl -s -o /dev/null -w '%{http_code}' -I "$BASE/summer-sale")"
check "check old URLs: its 404s not logged" "0" "$(q "SELECT COUNT(*) FROM wp_waymend_lite_404 WHERE path='/this-is-missing-xyz'")"
check "check old URLs: resolved inside WordPress from wp-admin (page, missing, file, category)" "200 404 200 200" "$(wp eval '$_SERVER["PHP_SELF"] = "/wp-admin/admin-post.php"; foreach ( array( "/contact/", "/this-is-missing-xyz", "/wp-includes/js/jquery/jquery.min.js", "/category/uncategorized/" ) as $u ) echo PNScripts\WaymendLite\WordPress\Link_Checker::status_inside_wordpress( $u ), " ";' | sed 's/ $//')"
html="$(admin '&tab=import')"
grep -q 'Check old URLs' <<< "$html" && echo "ok   import tab shows Check old URLs" || { echo "FAIL Check old URLs card"; fail=1; }

# PLUGIN_CHECK=1: the official Plugin Check (WordPress.org review tool) must report no errors.
if [ -n "${PLUGIN_CHECK:-}" ]; then
	PC="$ROOT/build/cache/plugin-check.latest-stable.zip"
	[ -f "$PC" ] || curl -fsSL -o "$PC" https://downloads.wordpress.org/plugin/plugin-check.latest-stable.zip
	unzip -q -o "$PC" -d "$WP_DIR/wp-content/plugins"
	wp plugin activate plugin-check --quiet
	wp plugin check pnscripts-waymend-lite --format=csv --fields=line,column,type,code,message,file > "$ROOT/build/plugin-check.csv" 2>&1
	errors=$(grep -c ',ERROR,' "$ROOT/build/plugin-check.csv"); warnings=$(grep -c ',WARNING,' "$ROOT/build/plugin-check.csv")
	check "plugin check: 0 errors ($warnings warnings, build/plugin-check.csv)" "0" "$errors"
	wp plugin deactivate plugin-check --quiet
fi

if [ -s "$WP_DIR/debug.log" ] && grep -qi 'plugins/pnscripts-waymend-lite/' "$WP_DIR/debug.log"; then
	echo "FAIL debug.log mentions waymend:"; grep -i 'plugins/pnscripts-waymend-lite/' "$WP_DIR/debug.log" | head; fail=1
else
	echo "ok   no PHP notices from the plugin in debug.log"
fi

kill $SERVER 2>/dev/null
wp plugin deactivate pnscripts-waymend-lite --quiet
wp plugin uninstall pnscripts-waymend-lite --skip-delete --quiet
check "uninstall drops the tables" "0" "$(tables)"
check "uninstall removes settings" "" "$(wp option get waymend_lite_settings 2>/dev/null)"

echo "WordPress $(wp core version) (${DB:-sqlite}${DB:+ $(wp db query "SELECT VERSION()" --skip-column-names 2>/dev/null)}), PHP $(php -r 'echo PHP_VERSION;')"
[ $fail = 0 ] && echo "SMOKE PASSED" || echo "SMOKE FAILED"
exit $fail
