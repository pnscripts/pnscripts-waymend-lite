# Waymend Lite by PN Scripts
SLUG    := pnscripts-waymend-lite
VERSION := 1.0.1
PY      ?= python3
ZIP     := dist/$(SLUG)-$(VERSION).zip
RUNTIME := pnscripts-waymend-lite.php uninstall.php readme.txt LICENSE src assets languages

.PHONY: test test-php80 lint i18n build smoke gallery clean

vendor/autoload.php:
	composer update --no-interaction --no-progress

test: vendor/autoload.php
	vendor/bin/phpunit

test-php80: vendor/autoload.php
	php8.0 vendor/bin/phpunit

lint:
	@find pnscripts-waymend-lite.php uninstall.php src tests tools -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v '^No syntax errors' || true
	@find pnscripts-waymend-lite.php uninstall.php src -name '*.php' -print0 | xargs -0 -n1 php8.0 -l | grep -v '^No syntax errors' || true
	@echo "php -l done (PHP 8.4 and 8.0)"

# Regenerates the .pot (WP-CLI i18n) and the Bulgarian .po/.mo.
i18n:
	php build/cache/wp-cli.phar i18n make-pot . languages/pnscripts-waymend-lite.pot --slug=pnscripts-waymend-lite --domain=pnscripts-waymend-lite --exclude=build,dist,vendor,tests,tools,listing --skip-audit
	python3 tools/bg_translations.py
	msgfmt --check -o languages/pnscripts-waymend-lite-bg_BG.mo languages/pnscripts-waymend-lite-bg_BG.po

build:
	rm -rf build/$(SLUG) $(ZIP)
	mkdir -p build/$(SLUG) dist
	cp -r $(RUNTIME) build/$(SLUG)/
	cd build && zip -qr ../$(ZIP) $(SLUG)
	@echo "Built $(ZIP)"

# Real WordPress + SQLite in build/wp, with the built plugin; then HTTP checks.
smoke: build
	tools/setup-wp.sh
	tools/smoke.sh

gallery: build
	tools/screenshots.sh
	$(PY) tools/gallery.py

clean:
	rm -rf build dist
