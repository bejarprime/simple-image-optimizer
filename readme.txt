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

The MVP focuses on batch optimization for existing media library images. It scans JPEG and PNG attachments, processes them in small AJAX batches, resizes oversized images and generates WebP sibling files when the server supports it.

This plugin does not send files to external APIs and does not include tracking.

== Features ==

* Batch optimization for existing JPEG and PNG media library images.
* Local processing using available WordPress/PHP image capabilities.
* Server checks for GD, Imagick, WebP and uploads folder writability.
* Optional WebP generation when supported by the server.
* High visual quality defaults.
* Configurable max width and height.
* Optional local backups of original files.
* Processing in small batches to reduce timeout risk.
* Basic optimization stats.
* Latest results panel with before/after size, saved bytes, WebP and backup status.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the WordPress admin.
3. Go to `Tools > Simple Image Optimizer`.
4. Review the defaults and scan your media library.
5. Start the batch process when you are ready.

== Frequently Asked Questions ==

= Does it use an external service? =

No. The goal of this plugin is local optimization without sending images to third-party APIs.

= Will it preserve the original quality? =

The plugin aims to reduce file size while keeping high visual quality. Perfect lossless quality is not guaranteed when lossy formats or resizing are used.

= Does WebP always work? =

No. WebP generation depends on server support through the available image engine.

= Does it process images in one request? =

No. Images are processed in small AJAX batches to reduce timeout risk on shared hosting.

== Changelog ==

= 0.1.0 =
* Initial development version.
