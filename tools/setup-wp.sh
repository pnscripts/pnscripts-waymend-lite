#!/usr/bin/env bash
# Builds a disposable WordPress on SQLite in build/wp (no MySQL, no Docker) with the plugin installed
# from the release ZIP (the newest dist/pnscripts-waymend-lite-*.zip). Downloads come from wordpress.org and the official WP-CLI build.
# Local test site only: it listens on 127.0.0.1 and uses throw-away credentials.
set -euo pipefail
# DB=mysql DB_HOST=127.0.0.1:3306 DB_NAME=<db> DB_USER=root DB_PASS= builds it on a real MySQL/MariaDB
# database instead (the database is emptied first). WP_VERSION=6.5.5 uses an older WordPress.
DB="${DB:-sqlite}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP_DIR="$ROOT/build/wp"
CACHE="$ROOT/build/cache"
PORT="${WP_PORT:-8811}"
WP_CLI="$CACHE/wp-cli.phar"

mkdir -p "$CACHE"
[ -f "$WP_CLI" ] || curl -fsSL -o "$WP_CLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
wp() { php -d memory_limit=512M "$WP_CLI" --path="$WP_DIR" --quiet "$@"; }

fetch() { # url, target dir
	local name="$CACHE/$(basename "$1")"
	[ -f "$name" ] || curl -fsSL -o "$name" "$1"
	unzip -q -o "$name" -d "$2"
}

rm -rf "$WP_DIR"
mkdir -p "$WP_DIR"
WPZIP=wordpress.zip; WPURL=https://wordpress.org/latest.zip
[ -n "${WP_VERSION:-}" ] && { WPZIP="wordpress-$WP_VERSION.zip"; WPURL="https://wordpress.org/$WPZIP"; }
if [ ! -f "$CACHE/$WPZIP" ]; then curl -fsSL -o "$CACHE/$WPZIP" "$WPURL"; fi
unzip -q "$CACHE/$WPZIP" -d "$ROOT/build/wp-unpack" && cp -r "$ROOT/build/wp-unpack/wordpress/." "$WP_DIR/" && rm -rf "$ROOT/build/wp-unpack"
rm -rf "$WP_DIR/wp-content/plugins/akismet" "$WP_DIR/wp-content/plugins/hello.php"

if [ "$DB" = sqlite ]; then
fetch https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip "$WP_DIR/wp-content/plugins"
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$WP_DIR/wp-content/plugins/sqlite-database-integration#" \
	-e 's#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#' \
	"$WP_DIR/wp-content/plugins/sqlite-database-integration/db.copy" > "$WP_DIR/wp-content/db.php"
fi

if [ "$DB" = sqlite ]; then DBNAME=wordpress DBUSER= DBPASS= DBHOST=
else DBNAME="${DB_NAME:-waymend_lite_smoke}" DBUSER="${DB_USER:-root}" DBPASS="${DB_PASS:-}" DBHOST="${DB_HOST:-127.0.0.1}"; fi
cat > "$WP_DIR/wp-config.php" <<PHP
<?php
define( 'DB_NAME', '$DBNAME' );
define( 'DB_USER', '$DBUSER' );
define( 'DB_PASSWORD', '$DBPASS' );
define( 'DB_HOST', '$DBHOST' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'DB_DIR', __DIR__ . '/wp-content/database/' );
define( 'DB_FILE', '.ht.sqlite' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', __DIR__ . '/debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISABLE_WP_CRON', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as \$k ) {
	define( \$k, 'local-test-only-' . \$k );
}
\$table_prefix = 'wp_';
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
PHP

[ "$DB" = sqlite ] || wp db reset --yes
wp core install --url="http://127.0.0.1:$PORT" --title="Waymend test site" \
	--admin_user=admin --admin_password="$(head -c 18 /dev/urandom | base64)" --admin_email=admin@example.test --skip-email
wp rewrite structure '/%postname%/'
# Installed from the release ZIP, the way a buyer uploads it.
wp plugin install "$(ls -v "$ROOT"/dist/pnscripts-waymend-lite-*.zip | tail -1)"
wp language core install bg_BG
echo "WordPress $(php "$WP_CLI" --path="$WP_DIR" core version) ready in $WP_DIR"
