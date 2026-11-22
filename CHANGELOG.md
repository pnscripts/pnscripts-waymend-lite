# Changelog

## 1.0.2 - 2026-11-22

- WordPress.org review (2026-11-21). Request and form values are sanitized where they are read: the redirect source and target with wp_sanitize_redirect() and then esc_url_raw() or a site path cleaner (non-Latin letters stay readable), REQUEST_URI and HTTP_REFERER with esc_url_raw(), the link check header with sanitize_text_field(), settings and bulk actions field by field. The 404 log stores paths with markup and control characters removed or encoded.
- Check old URLs no longer maps URLs onto ABSPATH: uploaded files are found through wp_upload_dir() (also when uploads are moved with upload_path), other static files are confirmed by a HEAD request to their URL.
- No bundled translations and no load_plugin_textdomain(): WordPress loads language packs from translate.wordpress.org. The .pot and the Bulgarian .po stay in translations/ in the repository as the source for translate.wordpress.org.
- Plugin name without a dash: "PN Scripts Waymend Lite: Redirects, 404 Log and Old URL Check".

## 1.0.1 - 2026-11-08

- Messages no longer offer regex rules, which only the full Waymend has. A CSV row with match type "regex" is refused with a message that says so; any other unknown match type lists exact and prefix. The form's Source hint describes paths only, the "Choose ... matching" error names exact and prefix, the warning about $1 placeholders says the Lite edition does not replace them, and the Order field (it only ordered regex rules) is gone. English and Bulgarian.

## 1.0.0 - 2026-10-17

- First release of the Lite edition of Waymend: exact and prefix redirects (301, 302, 307, 410), query string modes, loop protection, URL tracer, hit counter, 404 log with "Redirect this" and suggested targets, Check old URLs with a CSV of the URLs that need a redirect, CSV export, English and Bulgarian. Own slug, text domain, tables and options; stands aside when the full Waymend is active.
