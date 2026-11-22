# Waymend Lite: WordPress.org

Status 2026-11-22: 1.0.1 was submitted and pended on 2026-11-21 (sanitizing, ABSPATH file lookup, bundled translations, load_plugin_textdomain). 1.0.2 fixes all four (Plugin Check 2.2.0: 0 errors; smoke from the zip on WP 7.1.5 and 6.4.7) and waits to be uploaded on the same "Add your plugin" page, followed by a reply in the same review mail thread.

1. Owner or main session: signed in at https://wordpress.org/plugins/developers/add/ as `pnscripts`, upload `dist/pnscripts-waymend-lite-1.0.2.zip` (built by `make build`); slug `pnscripts-waymend-lite` (unchanged).
2. After approval: SVN (credentials are cached on this machine): `trunk/` = the zip contents, `tags/1.0.2/`, `assets/` = everything in `listing/wporg/` (screenshot-1..3, icon-128/256, banner-772/1544).
3. Name check before submitting: "Waymend" has no WordPress.org plugin and no redirect product of that name (re-check on the day).
