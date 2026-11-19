# Regression tests

Use a **disposable local WordPress installation** with this source checkout linked or copied to `wp-content/plugins/immersive-gallery-studio`. These tests create gallery, album, attachment and user fixtures. They require `WP_ENVIRONMENT_TYPE=local` and a standard MySQL/MariaDB database. Never run them against a production site.

Create a local admin named `reviewer` with password `igs-local-review-only`. Use a loopback site URL, enable WordPress debug logging without displaying errors, and disable external requests. Configure `IGS_PRIVATE_MEDIA_DIR` as a writable directory outside the WordPress root to include private upload tests. Leave tracking disabled in the WordPress configuration. The concurrency helper enables it in its own test process.

Install the locked browser test dependency and Chromium.

```sh
npm ci
npx playwright install --with-deps chromium
```

Run in this order from the source checkout (adjust paths/ports as needed):

```sh
IGS_WP_ROOT=/path/to/disposable-wordpress php tests/wordpress.php
IGS_TEST_URL=http://127.0.0.1:8878 node tests/browser.mjs
IGS_TEST_URL=http://127.0.0.1:8878 node tests/interactions.mjs
IGS_WP_ROOT=/path/to/disposable-wordpress node tests/analytics.mjs
python3 scripts/package.py
```

`wordpress.php` writes generated IDs to the operating system's temporary directory. The other suites consume that fixture file. Run the PHP suite again before repeating the browser suites, because they save changes to galleries.

`analytics.mjs` starts 20 separate PHP processes to verify real database concurrency, and checks authorization as an anonymous visitor. Password-attempt concurrency is verified independently of browser timing. `track-worker.php` is a local test helper and is excluded from the distribution.

The browser suite opens each template using a keyboard, checks layout at desktop and mobile widths, and verifies protected images and the tracking default. The interaction suite also exercises gestures, autoplay and editor saves in a block theme.
