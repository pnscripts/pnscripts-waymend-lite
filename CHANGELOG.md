# Changelog

## 1.0.1 - 2026-11-08

- Messages no longer offer regex rules, which only the full Waymend has. A CSV row with match type "regex" is refused with a message that says so; any other unknown match type lists exact and prefix. The form's Source hint describes paths only, the "Choose ... matching" error names exact and prefix, the warning about $1 placeholders says the Lite edition does not replace them, and the Order field (it only ordered regex rules) is gone. English and Bulgarian.

## 1.0.0 - 2026-10-17

- First release of the Lite edition of Waymend: exact and prefix redirects (301, 302, 307, 410), query string modes, loop protection, URL tracer, hit counter, 404 log with "Redirect this" and suggested targets, Check old URLs with a CSV of the URLs that need a redirect, CSV export, English and Bulgarian. Own slug, text domain, tables and options; stands aside when the full Waymend is active.
