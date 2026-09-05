# Immersive Gallery Studio

A self-contained WordPress gallery platform with a visual builder, ten presentation templates, protected galleries, albums, local Code-128 gallery barcodes, fullscreen viewing and interaction analytics.

## Review responsibilities

The code review uses product/workflow, WordPress architecture, PHP/backend, application security, privacy, frontend, 3D interaction, UI/UX, accessibility, media/algorithms, performance/concurrency, QA, and release/documentation responsibilities. The source checkout includes the findings, fixes, validation and deployment boundaries in `docs/REVIEW.md`.

## Ten templates

1. Smart Grid
2. Masonry
3. Justified Mosaic
4. Story / List
5. Carousel
6. Polaroid Collage
7. Cinematic Slideshow
8. 3D Circular Ring
9. 3D Photo Book
10. 3D Exhibition

The 3D templates are intentionally self-contained and do not require a third-party CDN. The circular ring uses one shared rotational state with a fixed viewpoint, pointer/touch dragging and inertia. The photo-book keeps page state while supporting forward/backward drag gestures, progress-based turning and velocity completion.

## Core features in this package

- Unlimited galleries and albums.
- WordPress Media Library integration and multi-image selection.
- Drag-and-drop image ordering.
- Visual administration builder with live preview.
- Template-specific controls and persistent settings.
- Desktop/tablet/mobile column controls.
- Captions, lightbox, downloads, random order and gallery search toggles.
- Public, password-protected and logged-in-only access modes.
- One-way hashed gallery passwords; no password in links/barcodes.
- HMAC-signed HttpOnly access cookie; changing the password invalidates old access.
- Password attempt rate limiting.
- Optional gallery expiration.
- Locally generated Code-128 barcode that encodes the gallery URL.
- Printable gallery barcode.
- Gallery shortcode: `[immersive_gallery id="123"]`.
- Album shortcode: `[immersive_album id="123"]`.
- Gallery categories and tags.
- Fullscreen HTML/CSS viewer above interactive scenes.
- Keyboard open/close controls and Escape-to-close.
- Reduced-motion CSS support.
- Touch/pointer interactions for interactive templates.
- Forward and backward 3D-book gestures.
- Interaction/view/open analytics counters.
- Privileged REST endpoint for gallery builder/integrations.
- Custom gallery capabilities for administrators/editors.
- No external runtime JavaScript/CSS dependencies.
- Content is preserved on uninstall by design.

## Installation

1. Upload the plugin ZIP in **Plugins → Add New → Upload Plugin**.
2. Activate **Immersive Gallery Studio**.
3. Open **Gallery Studio → Create Gallery**.
4. Open the visual builder, add images, choose a template and tune its settings. Use **Open visual builder** in Access & Sharing to reveal the builder, including the collapsed Meta Boxes pane in WordPress 7.1.
5. Configure access/sharing and publish.
6. Use the gallery permalink or embed its shortcode.

## Security model

All administrative writes are protected by WordPress nonces and capability checks. Gallery settings are sanitized before persistence and frontend output is escaped. Protected gallery passwords are stored using `wp_hash_password()` and validated with `wp_check_password()`. Successful access creates a short-lived HMAC-signed HttpOnly cookie; the password itself is never sent in a URL or barcode. Failed attempts are rate-limited without persisting a raw IP address.

Gallery access controls protect rendered gallery output. Original WordPress upload URLs remain public; private file delivery requires separate protected storage/server rules. Full-page caches and CDNs must exclude protected gallery/album pages and pages embedding them. Purge existing cached pages after upgrading. Viewers must enter gallery passwords again after the 1.0.2 cookie-format change.

Analytics are aggregate event counters. MySQL/MariaDB named locks protect analytics and password-attempt updates from races. The plugin does not require third-party runtime JavaScript or CSS.

## Development validation

The source checkout includes `tests/README.md` with PHP integration, Chrome workflow and concurrent database checks. `python3 scripts/package.py` creates a deterministic installable ZIP in `dist/`, excluding development tools and tests. Version 1.0.2 was exercised with WordPress 6.6.2 and 7.1, MySQL 8.4 and PHP 8.5.8. Test coverage and deployment limits are recorded in the review report.

## Architecture

- `immersive-gallery-studio.php` — bootstrap/lifecycle.
- `src/class-igs-settings.php` — shared defaults, numeric bounds and module settings schemas.
- `src/class-igs-plugin.php` — WordPress application layer, gallery orchestration, access, REST and barcode workflows.
- `src/class-igs-template-registry.php` — discovers template manifests, exposes settings schemas, renders templates and conditionally enqueues their assets.
- `src/class-igs-template-view.php` — escaped shared media/view primitives used by template renderers.
- `templates/` — the ten production template modules. Each module owns `manifest.php`, `settings.php`, `renderer.php`, `frontend.css`, and template JavaScript when interaction is required.
- `assets/js/admin.js` — gallery builder/media workflow.
- `assets/js/frontend.js` — shared lightbox, search, analytics and gallery lifecycle only.
- `assets/css/admin.css` — administration design system.
- `assets/css/frontend.css` — shared viewer, toolbar, album and accessibility styles only.


## Production template modules

- `templates/grid/`
- `templates/masonry/`
- `templates/justified/`
- `templates/story/`
- `templates/carousel/`
- `templates/collage/`
- `templates/cinematic/`
- `templates/ring-3d/`
- `templates/book-3d/`
- `templates/exhibition-3d/`

The core does not hardcode template rendering. A selected template is resolved through its manifest and only that template's production assets are enqueued on the frontend.

## Scope note

The earlier product specification contains a larger roadmap including cloud storage adapters, AI tagging, WooCommerce print/licensing workflows, client proofing, guest uploads, migrations, template marketplace/SDK and specialized CDN pipelines. Those are substantial independent subsystems and are not falsely represented as completed in this 1.0 package. The architecture and extension points are designed so they can be added without replacing gallery content.

## License

GPL-2.0-or-later.
