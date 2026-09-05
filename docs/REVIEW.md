# Plugin review and fixes — 1.0.2

The review covered every production PHP, JavaScript and CSS file, all ten manifests, settings schemas and renderers, and the activation, deactivation and uninstall paths. The roles below are review responsibilities applied to this work; they do not represent separate human reviewers or independent agent approvals.

## Extracted review roles

| Role | Responsibility in this project |
|---|---|
| Product and workflow reviewer | Create, configure, preview, save, publish, unlock, embed and organize galleries. |
| WordPress architect | Hooks, lifecycle, post types, ownership capabilities, REST and asset timing. |
| PHP/backend engineer | Input handling, persistence, date handling, media selection and rendering. |
| Application security engineer | Nonces, per-post authorization, protected content, cookies, password attempts and escaping. |
| Privacy reviewer | Cache behavior, public media boundaries and aggregate tracking. |
| Frontend engineer | Search, shared viewer, controls, template initialization and cleanup. |
| 3D interaction engineer | Ring orientation/inertia, book pagination/gestures and exhibition rotation. |
| UI/UX reviewer | Accurate previews, relevant controls, empty/error states and image ordering. |
| Accessibility reviewer | Keyboard behavior, modal focus, accessible names, touch input and reduced motion. |
| Media/algorithm engineer | Responsive images, justified aspect ratios and Code-128 encoding. |
| Performance/concurrency engineer | Asset loading, attachment caches, idle animation and simultaneous writes. |
| QA engineer | Regression tests, real browser workflows and WordPress compatibility. |
| Release/documentation engineer | Version consistency, reproducible packaging, installation notes and limitations. |

## Findings addressed

| Priority | Finding and resulting behavior | Main implementation |
|---|---|---|
| High | Gallery capabilities did not map ownership and status; albums used ordinary post permissions. Both now use the gallery capability model and per-post checks. | `src/class-igs-plugin.php` |
| High | Album shortcodes accepted arbitrary/draft/private post IDs, and covers disclosed locked media. Album type/status/password checks and cover authorization now apply. | `src/class-igs-plugin.php` |
| High | Custom passwords did not protect descriptions/excerpts/featured images through normal content and REST rendering. Those output paths now withhold protected content. Native WordPress post passwords also gate gallery media. | `src/class-igs-plugin.php` |
| High | Cookie expiry existed only in browser metadata. The signed token now includes an expiry checked on the server; password changes invalidate tokens. | `src/class-igs-plugin.php` |
| High | Parallel password submissions could race the attempt counter. A database lock now admits at most eight attempts per gallery/client window. | `src/class-igs-plugin.php` |
| High | Settings accepted invalid ranges and nested values; saved values could make layouts or animation unusable. Shared bounded settings now drive saving, rendering, builder controls and module schemas. | `src/class-igs-settings.php`, `templates/*/settings.php` |
| High | The book allowed a blank terminal page and reported the wrong index; stage pointer capture interfered with taps. Pagination now remains on actual images, with forward/backward gestures and separate tap handling. | `templates/book-3d/*`, `assets/js/frontend.js` |
| Medium | Expiration used the PHP timezone and accepted impossible dates. Validation and comparisons now use the WordPress timezone; malformed stored access metadata fails closed. | `src/class-igs-plugin.php` |
| Medium | A global hook changed every native WordPress password cookie lifetime. It was removed. | `src/class-igs-plugin.php` |
| Medium | Protected output lacked reliable cache handling. Gallery/album responses set no-store headers and DONOTCACHEPAGE; protected REST responses are private/no-store. | `src/class-igs-plugin.php` |
| Medium | Invalid/duplicate/non-image IDs could be persisted. Selection is validated and attachment caches are populated together. | `src/class-igs-plugin.php` |
| Medium | The custom REST validator used a one-argument PHP builtin where WordPress passes more arguments, and accepted non-gallery IDs. Both cases are corrected. | `src/class-igs-plugin.php` |
| Medium | Styles could be registered too late or never printed for shortcodes, albums and lock forms. Registration is idempotent and late queued styles are printed. | `src/class-igs-plugin.php` |
| Medium | Search hid DOM items without updating navigation/layout/counters. Every interactive template now recomputes its visible set and has a zero-result state. | `assets/js/frontend.js`, `templates/*/frontend.js` |
| Medium | The lightbox left controls focusable when closed, lacked focus containment/restoration and overwrote scroll/pause state. It now behaves as a named modal and restores the previous state. | `assets/js/frontend.js`, `assets/css/frontend.css` |
| Medium | Carousel autoplay was absent; slide width changes and zero gaps were mishandled. Autoplay/pause, resize observation, gestures and precise offsets are implemented. | `templates/carousel/*` |
| Medium | Invisible cinematic/book/ring items remained keyboard targets. Inactive items are inert; reduced-motion book/ring layouts expose all images. | Shared frontend and interactive templates |
| Medium | Ring cards faced the wrong direction and ran an endless idle animation loop. Orientation, responsive geometry, keyboard navigation and demand-driven inertia are corrected. | `templates/ring-3d/*` |
| Medium | Exhibition rotation could turn the entire gallery away and lacked cancellation handling. Rotation is bounded and shared gesture handling covers cancellation and vertical scrolling. | `templates/exhibition-3d/*` |
| Medium | “Justified” widths cropped arbitrary aspect ratios. Rows now distribute available width using source aspect ratios and retain a restrained last row. | `templates/justified/*` |
| Medium | The builder preview used an unrelated approximation. It now calls a nonce/capability-protected preview endpoint and loads the production renderer/assets in an isolated iframe. | `assets/js/admin.js`, `src/class-igs-plugin.php` |
| Medium | Builder toggles were not keyboard accessible and ordering required dragging. Focus styles and earlier/later buttons are available, duplicate selection is prevented, only relevant controls are shown, and an Open visual builder shortcut reveals the collapsed pane in WordPress 7.1. | Admin assets and builder markup |
| Medium | Concurrent analytics lost increments and accepted inaccessible galleries. Serialized increments and normal access checks now apply; downloads/navigation emit events. A capability-protected activity box exposes all four counters. | `src/class-igs-plugin.php`, shared frontend |
| Medium | Barcode generation replaced Unicode bytes with question marks, had undersized quiet zones and incorrect error responses. URLs are percent-encoded, quiet zones are corrected, requests are bounded, and printing waits for image load. | PHP barcode and admin JS |
| Low | Story captions inherited white text on a light page; collage/exhibition ignored controls; media omitted dimensions/srcset. These presentation and media issues are corrected. | Template CSS and `src/class-igs-template-view.php` |
| Low | Template discovery checked noncanonical paths. Renderer/settings paths are now resolved within their module directory. | `src/class-igs-template-registry.php` |
| Low | Compressed source and absent regression/package tooling made defects difficult to diagnose. JavaScript/CSS are formatted and repeatable tests/package tooling are included in the source checkout. | `tests/`, `scripts/package.py` |

## Validation

- PHP integration: 71 assertions for module coverage, schema validity, saving/nonces/ownership, access, expiry, REST, escaping and barcode structure.
- Browser regression: 113 assertions for all ten templates, desktop/mobile layouts, filtering, empty states, counters, viewer focus, navigation, reduced motion, shortcode/album output, password unlock and builder preview/ordering.
- Interaction suite: pointer taps, forward/backward book gestures, ring drag suppression, carousel/cinematic autoplay and pause, justified ratios, an actual WordPress editor save, and barcode response/error handling.
- Concurrency: 20 simultaneous analytics increments are retained; inaccessible gallery events are rejected; exactly eight of 20 simultaneous password attempts are admitted.
- Syntax: every PHP and JavaScript source/test file passes its interpreter's syntax check.
- Packaging: deterministic ZIP validation checks the plugin root and all ten template manifests.

Tests used disposable installations, MySQL 8.4, PHP 8.5.8 and headless Chrome. WordPress 6.6.2 was exercised as the minimum-version baseline, followed by WordPress 7.1 compatibility testing. The bundled Twenty Twenty-Four block theme was used. WordPress 7.1 places the builder in its expandable Meta Boxes pane; this is covered in the browser tests and installation notes. Screenshots of the ring, book and story layouts were inspected. Older WordPress/WP-CLI code emits PHP 8.5 deprecation notices; these are outside this plugin.

See `tests/README.md` for commands and the exact test boundaries. Passing these checks is evidence for the exercised workflows, not a guarantee across all hosts, themes, browsers or plugin combinations.

## Deployment boundaries

1. Gallery access protects the plugin's rendered gallery, descriptions and album covers. WordPress upload files remain public at their original URLs. Truly private files require protected storage or web-server delivery rules; changing upload visibility automatically could break existing site content.
2. Full-page caches/CDNs that answer before WordPress runs must exclude gallery/album pages and pages embedding protected galleries. Purge cached pages after upgrading. Headers cannot revoke an already-cached response served upstream.
3. This version changes the access-token format, so viewers of password-protected galleries must enter the password again after upgrading.
4. Analytics are aggregate event counters, not unique visitor counts or a fraud-resistant analytics service. MySQL/MariaDB named locks are used for concurrency; SQLite is not a supported substitute for these two locking paths.
5. The plugin does not promise support for every third-party page-builder injection mode. Dynamic galleries initialize when their template script is already available; otherwise the integrator must enqueue the relevant template assets.
6. Physical touch devices, physical barcode printing/scanning, Safari/Firefox, multisite network activation, large media-library stress tests, external object caches and the user's actual production theme/cache stack were not exercised. Browser-native downloads from a different media/CDN origin may open the image instead of forcing a download.
7. The existing roadmap subsystems described in README (cloud storage, commerce, proofing, uploads, AI tagging, migrations and marketplace features) are outside this package's implemented feature set.

## Reference checks

Capability mapping follows the [WordPress post-type API](https://developer.wordpress.org/reference/functions/register_post_type/) and [meta-capability behavior](https://developer.wordpress.org/reference/functions/map_meta_cap/). Asset timing was checked against the [WordPress enqueue hook documentation](https://developer.wordpress.org/reference/hooks/wp_enqueue_scripts/).
