=== BFS App API ===
Contributors: Your Name
Donate link: https://example.com/
Tags: api, rest, app, flutter, mobile
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Custom REST API endpoints for BFS App to serve mobile app requirements like Flutter.

== Description ==

BFS App API is a custom WordPress plugin that registers REST API endpoints to serve data tailored for mobile applications (like Flutter). It provides an endpoint to fetch header information including website details, menu items, login status, user details, wishlist count, and WooCommerce cart data.

**API Endpoint:**
`/wp-json/bfsapp/v1/header`

== Installation ==

1. Upload the `bfs-app-api` directory to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Once activated, the API endpoint is immediately ready for use.
4. Test the API by visiting `https://your-domain.com/wp-json/bfsapp/v1/header` in your browser or a tool like Postman.

== Frequently Asked Questions ==

= Does it support WooCommerce? =
Yes, it automatically returns WooCommerce cart counts, totals, and currency data if WooCommerce is installed and active.

= Does it support Wishlist counts? =
Yes, it supports YITH WooCommerce Wishlist and TI WooCommerce Wishlist plugins out-of-the-box.

== Changelog ==

= 1.0.0 =
* Initial release.
