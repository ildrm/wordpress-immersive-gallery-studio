=== Immersive Gallery Studio ===
Contributors: ildrm
Tags: gallery, image gallery, photo album, 3d gallery, carousel, masonry, photo book
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced visual gallery platform with ten responsive 2D/3D templates, protected galleries, albums, barcodes, fullscreen viewing and a visual builder.

== Description ==
Create responsive photo galleries and albums with Smart Grid, Masonry, Justified Mosaic, Story, Carousel, Polaroid Collage, Cinematic, 3D Ring, 3D Photo Book and 3D Exhibition templates.

Includes hashed gallery passwords, signed access cookies, expiration, gallery barcodes, Media Library integration, drag ordering, fullscreen viewing, responsive controls and lightweight analytics.

Access controls protect gallery output. Original WordPress media URLs retain their existing public visibility. Exclude protected gallery and album pages, and pages embedding them, from upstream page caches/CDNs.

== Installation ==
1. Upload and activate the plugin.
2. Open Gallery Studio in wp-admin.
3. Create a gallery, add images and select a template. Use Open visual builder in Access & Sharing to reveal the builder.
4. Publish or embed with [immersive_gallery id="123"].

== Changelog ==
= 1.0.2 =
* Fixed access checks, password cookie expiration, concurrent password attempt limits, timezone-aware expiration, protected album covers and REST content.
* Fixed all interactive templates, search/counter synchronization, book pagination, carousel autoplay and pointer gestures.
* Added accessible viewer focus management, reduced-motion layouts, keyboard media ordering and a production-renderer live preview.
* Unified settings validation, improved responsive media, fixed conditional styles, barcode encoding and atomic analytics.

= 1.0.1 =
* Restored the production templates directory and moved all ten presentations into independently discoverable template modules.
* Added manifest/settings/renderer separation and conditional per-template asset loading.
* Kept development/release artifacts out of the distribution ZIP.

= 1.0.0 =
* Initial release.
