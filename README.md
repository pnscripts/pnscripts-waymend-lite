# PN Scripts Waymend Lite

Free WordPress plugin (GPL-3.0-or-later): keep old links working with exact and prefix redirects
(301, 302, 307, 410), a 404 log with one-click redirects and suggested targets, loop protection, a
URL tracer, and **Check old URLs**: paste the old site's addresses before a move and see which work,
which are redirected and which would end in a 404, then download the missing ones as CSV.

The WordPress.org description is `readme.txt`. Screenshots and plugin-directory art are in
`listing/wporg/`.

## Build and test

```
make test     # 47 PHPUnit tests (core classes, no WordPress)
make lint     # php -l on PHP 8.4 and 8.0
make build    # dist/pnscripts-waymend-lite-<version>.zip
make smoke    # real WordPress from the zip: 38 HTTP/WP-CLI checks; DB=mysql, WP_VERSION=6.4.7, PLUGIN_CHECK=1
```

Tested 2026-10-17: WordPress 7.1.3 on MySQL 8.0.46 (38/38, Plugin Check 0 errors), on SQLite
(37/37) and WordPress 6.4.7 on MySQL (37/37), PHP 8.4. Not tested: MariaDB, multisite.

## Full edition

Waymend adds regular-expression rules and CSV import: https://pnscripts.com/marketplace/wp-waymend.
With the full Waymend active, Waymend Lite stands aside.

Copyright (c) 2026 ПН СКРИПТС ЕООД / PN Scripts. GPL-3.0-or-later (LICENSE).
