# Regression tests

Use a **disposable local WordPress installation** with this source checkout linked or copied to `wp-content/plugins/immersive-gallery-studio`. These tests create gallery, album, attachment and user fixtures. They require `WP_ENVIRONMENT_TYPE=local` and a standard MySQL/MariaDB database. Never run them against a production site.

Create a local admin named `reviewer` with password `igs-local-review-only`. Use a loopback site URL, enable WordPress debug logging, and disable external update/news requests in the test installation if they delay editor loading. No test dependencies are required by the shipped plugin.

Install browser-only tools in a temporary directory:

```sh
npm install --prefix /private/tmp/igs-review-tools --no-audit --no-fund playwright
```

Run in this order from the source checkout (adjust paths/ports as needed):

```sh
IGS_WP_ROOT=/path/to/disposable-wordpress php tests/wordpress.php
IGS_TEST_URL=http://127.0.0.1:8878 node tests/browser.mjs
IGS_TEST_URL=http://127.0.0.1:8878 node tests/interactions.mjs
IGS_WP_ROOT=/path/to/disposable-wordpress node tests/analytics.mjs
python3 scripts/package.py
```

The browser tests use an installed Chrome browser. Set `IGS_TEST_TOOLS` if the temporary dependency directory differs. `wordpress.php` writes generated IDs to the operating system's temporary directory; all other suites consume that fixture file. Run the PHP suite again to create fresh fixtures before repeating the browser suites, because the interaction suite intentionally saves changes to a gallery.

`analytics.mjs` starts 20 separate PHP processes to verify real database concurrency, and checks authorization as an anonymous visitor. Password-attempt concurrency is verified independently of browser timing. `track-worker.php` is a local test helper and is excluded from the distribution.

`browser.mjs` runs 113 assertions. `wordpress.php` runs 71 assertions. The interaction/concurrency suites cover additional behavior described in `docs/REVIEW.md`. The tests exercise a block theme, the tested PHP/WordPress/database versions and desktop Chrome with a mobile viewport; they do not simulate every production deployment or a physical touch device.
