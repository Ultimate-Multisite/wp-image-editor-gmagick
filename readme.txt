=== Gmagick Image Editor ===
Contributors: superdav42
Tags: gmagick, graphicsmagick, image, webp, avif, performance
Requires at least: 5.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Use GraphicsMagick (Gmagick) as the WordPress image editor. Faster than Imagick with full WebP and AVIF support.

== Description ==

Registers a `WP_Image_Editor_Gmagick` class that uses the [Gmagick PHP extension](https://www.php.net/manual/en/book.gmagick.php) (GraphicsMagick) for image manipulation in WordPress.

GraphicsMagick is a fork of ImageMagick focused on stability and performance. It is typically faster for common web operations like resize, crop, and thumbnail generation.

**Features:**

* Drop-in replacement for the built-in Imagick editor
* Full support for JPEG, PNG, GIF, WebP, and AVIF
* Pre-sampling optimization for large image resizes
* Post-resize sharpening for JPEGs
* Progressive JPEG support via `image_save_progressive` filter
* Metadata stripping via `image_strip_meta` filter
* Compatible with `make_subsize()` (WordPress 5.3+)
* Works as a regular plugin or mu-plugin

**How it works:**

The plugin registers `WP_Image_Editor_Gmagick` as the highest-priority image editor. WordPress will use it for all image operations when the Gmagick extension is available, falling back to GD otherwise.

== Installation ==

= Requirements =

The `php-gmagick` extension must be installed on your server.

**Ubuntu/Debian:**

    sudo apt install php8.4-gmagick

**Note:** The Gmagick and Imagick PHP extensions cannot be loaded at the same time. Installing Gmagick will typically disable Imagick. GD remains available as a fallback.

= Plugin Installation =

1. Upload the `wp-image-editor-gmagick` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. That's it — no configuration needed

= mu-plugin Installation =

Copy `gmagick-image-editor.php` and `class-wp-image-editor-gmagick.php` to `/wp-content/mu-plugins/`.

== Frequently Asked Questions ==

= How do I verify it's working? =

Use WP-CLI:

    wp eval '$e = apply_filters("wp_image_editors", []); print_r($e);'

`WP_Image_Editor_Gmagick` should appear first in the list.

= Can I use this alongside Imagick? =

No. The `gmagick` and `imagick` PHP extensions are mutually exclusive — only one can be loaded at a time. The plugin automatically removes `WP_Image_Editor_Imagick` from the editor list.

= What happens if Gmagick is not installed? =

The plugin does nothing. WordPress falls back to its default editors (Imagick or GD).

= Is GraphicsMagick really faster? =

For typical WordPress operations (resize, crop, thumbnail), yes. GraphicsMagick uses less memory and processes images faster than ImageMagick in most benchmarks. The difference is most noticeable on sites that generate many image sizes on upload.

== Changelog ==

= 1.0.0 =
* Initial release
* Full WP_Image_Editor implementation for Gmagick
* Support for JPEG, PNG, GIF, WebP, AVIF
* Pre-sampling, post-resize sharpening, metadata stripping
* Compatible with WordPress 5.3+ make_subsize() API
