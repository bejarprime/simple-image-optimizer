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
- Secure AJAX scanning of candidate media files.
- Secure AJAX batch optimization with nonces and `manage_options` checks.
- Basic stats for processed, skipped, errors and estimated saved bytes.
- Latest results panel with before/after size, saved bytes, WebP and backup status.

## Important limitation

This plugin aims to reduce image weight while keeping high visual quality. It does not promise perfect lossless optimization.

## Status

Development MVP. Not production-ready yet.

## License

GPLv2 or later.
