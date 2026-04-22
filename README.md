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
- Media Library list-view column with optimization status and restore action when a backup exists.
- Restore from backup for optimized images, including generated sizes tracked by the plugin.
- Safety guard: if a re-encoded file becomes larger than the original, the original file is kept.

## Important limitation

This plugin aims to reduce image weight while keeping high visual quality. It does not promise perfect lossless optimization.

Some images are already compressed. In those cases, WordPress/GD/Imagick re-encoding can produce a larger file. The plugin now keeps the original file instead of replacing it with a larger result.

## Safe workflow

1. Keep `Keep local backup of original files` enabled if you want the restore action.
2. Scan the media library from `Tools > Simple Image Optimizer`.
3. Optimize in small batches.
4. Review the Latest results panel or switch the Media Library to list view.
5. Use `Restore` in the Optimization column if an image needs to be reverted.

## Status

Development MVP. Not production-ready yet.

## License

GPLv2 or later.
