=== Simple Image Optimizer ===
Contributors: wphubb
Tags: images, optimization, webp, media, performance
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight local image optimization for WordPress media libraries, with batch processing and optional WebP generation.

== Description ==

Simple Image Optimizer helps reduce image weight locally from the WordPress admin without relying on external optimization services.

The first MVP focuses on batch optimization for existing media library images, resizing oversized images and generating WebP files when the server supports it.

This plugin does not send files to external APIs and does not include tracking.

== Features ==

* Batch optimization for existing media library images.
* Local processing using available WordPress/PHP image capabilities.
* Optional WebP generation when supported by the server.
* High visual quality defaults.
* Processing in small batches to reduce timeout risk.
* Basic optimization stats.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the WordPress admin.
3. Go to the plugin settings screen.
4. Review the defaults and start the batch process.

== Frequently Asked Questions ==

= Does it use an external service? =

No. The goal of this plugin is local optimization without sending images to third-party APIs.

= Will it preserve the original quality? =

The plugin aims to reduce file size while keeping high visual quality. Perfect lossless quality is not guaranteed when lossy formats or resizing are used.

= Does WebP always work? =

No. WebP generation depends on server support through the available image engine.

== Changelog ==

= 0.1.0 =
* Initial development version.
