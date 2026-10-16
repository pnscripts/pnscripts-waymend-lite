#!/usr/bin/env bash
# Fresh test site, demo data, real admin screenshots into build/screens.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
PORT="${WP_PORT:-8811}"
SITE="http://127.0.0.1:$PORT"
wp() { php -d memory_limit=512M "$ROOT/build/cache/wp-cli.phar" --path="$ROOT/build/wp" "$@"; }

tools/setup-wp.sh
wp plugin activate pnscripts-waymend-lite --quiet
wp eval-file tools/demo-seed.php

php -S "127.0.0.1:$PORT" -t build/wp tools/router.php >build/server.log 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null' EXIT
for i in $(seq 1 30); do curl -s -o /dev/null "$SITE/" && break; sleep 1; done
tools/demo-traffic.sh "$SITE"

# Auth cookies for user 1 of the throw-away site (no password is used).
wp eval '
$exp = time() + 3600; $hash = md5( get_option( "siteurl" ) );
echo wp_json_encode( array(
  array( "name" => "wordpress_" . $hash, "value" => wp_generate_auth_cookie( 1, $exp, "auth" ), "path" => "/wp-admin" ),
  array( "name" => "wordpress_logged_in_" . $hash, "value" => wp_generate_auth_cookie( 1, $exp, "logged_in" ), "path" => "/" ),
) );' > build/cookies.json

[ -d build/node/node_modules/puppeteer-core ] || (mkdir -p build/node && cd build/node && echo '{"private":true}' > package.json && npm install --silent --no-audit --no-fund puppeteer-core@24)
node tools/screenshots.mjs "$SITE" build/cookies.json build/screens
wp option update WPLANG bg_BG --quiet
rm -rf build/chrome-profile
ONLY=bg node tools/screenshots.mjs "$SITE" build/cookies.json build/screens
wp option update WPLANG '' --quiet
rm -f build/cookies.json
if [ -s build/wp/debug.log ]; then echo "debug.log:"; cat build/wp/debug.log; fi
