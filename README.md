# Simple Image Optimizer

Lightweight local image optimization for WordPress media libraries.

## MVP goal

Reduce image weight from the WordPress admin with a simple batch flow:

- scan existing JPEG/PNG media library images;
- resize oversized images;
- optimize files locally using WordPress/PHP image editors;
- generate WebP sibling files when supported by the server;
- preserve high visual quality by default;
- process images in small AJAX batches;
- avoid external services and hidden tracking.

## Current features

- Admin page under `Tools > Simple Image Optimizer`.
- WPHubb admin design system.
- Server checks for GD, Imagick, WebP and uploads writability.
- Settings for quality preset, max dimensions, batch size, backups and WebP generation.
- Optional optimization of generated WordPress image sizes.
- Optional automatic optimization for new uploads.
- Secure AJAX scanning of candidate media files.
- Secure AJAX batch optimization with nonces and `manage_options` checks.
- Basic stats for processed, skipped, errors and estimated saved bytes.
- Latest results panel with before/after size, saved bytes, WebP and backup status.
- Diagnostics tab with server details, plugin settings, stats, recent events and a copyable report.
- Media Library list-view column with optimization status and restore action when a backup exists.
- Restore from backup for optimized images, including generated sizes tracked by the plugin.
- Safety guard: if a re-encoded file becomes larger than the original, the original file is kept.
- Hardened upload path validation before writing, restoring or deleting generated files.
- `View WebP` and `Copy WebP URL` actions in the Media Library list view when a WebP file exists.
- Diagnostic warning in the Media Library when WebP metadata exists but the file URL cannot be resolved.
- Optional frontend WebP delivery for standard WordPress image output.

## Important limitation

This plugin aims to reduce image weight while keeping high visual quality. It does not promise perfect lossless optimization.

Some images are already compressed. In those cases, WordPress/GD/Imagick re-encoding can produce a larger file. The plugin now keeps the original file instead of replacing it with a larger result.

## Safe workflow

1. Keep `Keep local backup of original files` enabled if you want the restore action.
2. Scan the media library from `Tools > Simple Image Optimizer`.
3. Optimize in small batches.
4. Review the Latest results panel or switch the Media Library to list view.
5. Use `View WebP` or `Copy WebP URL` in the Optimization column to inspect generated WebP files.
6. Use `Restore` in the Optimization column if an image needs to be reverted.

If the column shows a WebP badge but no WebP action, the plugin will now show a diagnostic line. That usually means the stored WebP metadata exists, but the file path cannot be mapped back to a public uploads URL.

## Frontend WebP delivery

The `Serve generated WebP on the frontend` setting is disabled by default. When enabled, the plugin replaces local JPEG/PNG uploads with existing generated WebP files in standard WordPress image output, including attachment image URLs, `srcset` sources and image tags processed by WordPress content filters.

This safe mode does not promise full page-builder coverage. Some Elementor or page-builder background images, CSS-generated URLs, external images, CDN rewrites and cached HTML may require a future advanced mode or cache clearing.

## Status

Development MVP. Not production-ready yet.

## License

GPLv2 or later.
