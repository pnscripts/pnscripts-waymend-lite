=== PN Scripts Waymend Lite – Redirects, 404 Log and Old URL Check ===
Contributors: pnscripts
Tags: redirect, 301, 404, migration, broken links
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Keep old links working: exact and prefix redirects, a 404 log with one-click redirects, loop protection and a check of your old URLs before a move.

== Description ==

Moving a site, changing permalinks or cleaning up old pages? **PN Scripts Waymend Lite** keeps the old links working. Paste the old site's addresses and see at once which still work, which are redirected, and which would end in a 404; add a redirect with one click; watch the 404 log for what visitors still request.

= Check old URLs before you switch =

Paste the old addresses (from the old sitemap, Search Console or an analytics export), one per line; the old domain is fine, each path is checked on this site. Every URL is reported as:

* works, or redirected and works,
* gone on purpose (410),
* **missing** (with a suggested target when a published post, page or term has the same slug),
* **redirected to a missing page**, a loop, or sent to another site.

The URLs that need a redirect download as a CSV. Pages are resolved inside WordPress (published posts, real files, WordPress's own routing) and confirmed with a request to the site itself when the server allows it; those requests are not written to the 404 log.

= Redirects =

* **Status codes:** 301, 302, 307 and 410 Gone (served with your theme's 404 template and status 410).
* **Exact match:** one URL, ignoring a trailing slash and letter case (case-sensitive on request); percent-encoded and non-Latin paths are compared decoded.
* **Prefix match:** a path and everything below it, with the rest appended to the target (/old-shop/hats/red to /shop/hats/red); whole path segments only.
* **Query strings:** ignore them, pass them on, or require exact parameters.
* **Hit counter** and last hit per rule; a **Test a URL** tracer that shows every hop.

= Loop protection =

Before a rule is saved, Waymend Lite follows it hop by hop the way a browser would and refuses rules that would send visitors in circles; a target that is redirected again is saved with a warning naming the final URL. On the live site a URL is never redirected to itself.

= 404 log =

One row per missing URL with hits, first and last time and the last referrer. **No IP addresses or user agents are stored.** Vulnerability scans (*.php, /.env, /.git) are skipped by default; the list is editable. "Redirect this" opens a new rule with the source filled in and a suggested target.

= Export =

Every rule with its hits and last hit as CSV.

= The full edition =

Waymend (full edition) adds regular-expression rules with capture groups and CSV import with per-line validation: https://pnscripts.com/marketplace/wp-waymend. Waymend Lite has no locked features, no trial and no upgrade notices.

= Translations =

English and Bulgarian (bg_BG).

= Privacy =

Waymend Lite stores redirect rules and, in the 404 log, the requested path, the time and the last referrer. It stores no IP addresses or user agents, sets no cookies and sends nothing to other sites.

== Installation ==

1. Install from Plugins > Add New, or upload the ZIP under Plugins > Add New > Upload Plugin.
2. Activate it.
3. Go to Tools > Redirects.

== Frequently Asked Questions ==

= Where are paths relative to? =

To the site address. On a site installed at https://example.com/blog, the source /old-post means https://example.com/blog/old-post.

= Does it redirect admin, login or REST requests? =

No. Rules apply to front-end requests handled by WordPress. Files served directly by the web server, such as images in wp-content/uploads, never reach WordPress.

= The check says "Could not be checked" =

The page could not be resolved inside WordPress and the server does not allow the site to call itself (some hosts block that). The result for such URLs is unknown rather than guessed.

= What happens to the data when I delete the plugin? =

Deleting the plugin from the Plugins screen removes its two tables (rules and 404 log) and its settings. Deactivating keeps them.

= Can it run next to the full Waymend? =

It does not need to: with the full Waymend active, Waymend Lite stands aside and says so on the dashboard.

== Screenshots ==

1. Check old URLs: results per URL and a CSV of the ones that need a redirect.
2. Redirects with hits, last hit and the URL tracer.
3. The 404 log with "Redirect this".

== Changelog ==

= 1.0.1 =
* Messages no longer offer regex rules, which only the full Waymend has: the CSV import names the match types the Lite edition accepts (exact, prefix), the form hints describe paths only, and the unused Order field is gone.

= 1.0.0 =
* First release of the Lite edition: exact and prefix redirects (301, 302, 307, 410), loop protection, URL tracer, hit counter, 404 log with suggestions, Check old URLs, CSV export, English and Bulgarian.

== Upgrade Notice ==

= 1.0.1 =
Clearer messages: the CSV import and the form no longer suggest regex rules.

= 1.0.0 =
First release.
