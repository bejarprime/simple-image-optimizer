=== Simple Image Optimizer ===
Contributors: wphubb
Tags: images, optimization, webp, media, performance
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 0.1.2
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
* Optional optimization of generated WordPress image sizes.
* Optional automatic optimization for new uploads.
* Processing in small batches to reduce timeout risk.
* Basic optimization stats.
* Latest results panel with before/after size, saved bytes, WebP and backup status.
* Media Library list-view status column with restore action when backups exist.
* Safety guard to keep the original file when re-encoding would make it larger.
* View and copy WebP URL actions in the Media Library list view.
* Diagnostic message when WebP metadata exists but the public uploads URL cannot be resolved.
* Optional frontend WebP delivery for standard WordPress image output.

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

Some images are already well compressed. If re-encoding would create a larger file, the plugin keeps the original file instead of replacing it.

= Does WebP always work? =

No. WebP generation depends on server support through the available image engine.

= Where can I find the generated WebP file? =

Generated WebP files are stored in the same uploads folder as the original file. In the Media Library list view, the Optimization column shows View WebP and Copy WebP URL actions when a WebP file exists.

If the column shows the WebP badge but no WebP action, the plugin displays a diagnostic message. This normally means metadata exists, but the stored path cannot be mapped to a public uploads URL.

= Can I restore an optimized image? =

Yes, when local backups were enabled before optimization. The restore action appears in the Media Library list view for optimized images with a backup.

= Does it optimize thumbnails and generated sizes? =

Yes, this can be enabled or disabled in the plugin settings. It is enabled by default for the generated WordPress sizes tracked in attachment metadata.

= Can it optimize new uploads automatically? =

Yes. Automatic optimization can be enabled in the plugin settings. It is disabled by default so administrators can test the batch workflow first.

= Can it serve WebP on the frontend? =

Yes. The plugin includes an opt-in setting to serve generated WebP files for standard WordPress image output. It replaces local JPEG/PNG uploads only when a generated WebP sibling file exists.

This safe mode does not guarantee complete page-builder coverage. Some Elementor background images, CSS-generated URLs, external images, CDN rewrites and cached HTML may not be replaced.

= Does it process images in one request? =

No. Images are processed in small AJAX batches to reduce timeout risk on shared hosting.

== Changelog ==

= 0.1.2 =
* Added opt-in frontend WebP delivery for standard WordPress image output.

= 0.1.1 =
* Improved WebP URL resolution and Media Library diagnostics when WebP metadata cannot be mapped to a public uploads URL.

= 0.1.0 =
* Initial development version.
