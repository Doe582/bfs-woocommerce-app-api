<?php
/**
 * Plugin Name: BFS App API
 * Plugin URI: https://example.com
 * Description: Custom REST API endpoints for BFS App
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: bfs-app-api
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define('BFS_APP_API_VERSION', '1.0.0');
define('BFS_APP_API_DIR', plugin_dir_path(__FILE__));
define('BFS_APP_API_URL', plugin_dir_url(__FILE__));

// Include the Cart API class.
require_once BFS_APP_API_DIR . 'includes/class-cart-api.php';

// Include the Coupon API class.
require_once BFS_APP_API_DIR . 'includes/class-coupon-api.php';

// Include the Checkout API class.
require_once BFS_APP_API_DIR . 'includes/class-checkout-api.php';

// Include the Batch API class.
require_once BFS_APP_API_DIR . 'includes/class-batch-api.php';

// Include the Shipping API class.
require_once BFS_APP_API_DIR . 'includes/class-shipping-api.php';

// Include the Fees API class.
require_once BFS_APP_API_DIR . 'includes/class-fees-api.php';

// Include the JWT API class.
require_once BFS_APP_API_DIR . 'includes/class-jwt-api.php';

// Include the Rate Limit API class.
require_once BFS_APP_API_DIR . 'includes/class-rate-limit-api.php';

// Include the Sync API class.
require_once BFS_APP_API_DIR . 'includes/class-sync-api.php';

// Include the Install API class.
require_once BFS_APP_API_DIR . 'includes/class-install-api.php';

// Include the Products API class.
require_once BFS_APP_API_DIR . 'includes/class-products-api.php';

// Hook JWT into WordPress authentication
add_filter('determine_current_user', ['BFS_JWT_API', 'authenticate'], 20);
add_filter('rest_authentication_errors', ['BFS_JWT_API', 'auth_errors']);

// Initialize Sync hooks
BFS_Sync_API::init();

// Register activation hook
register_activation_hook(__FILE__, 'bfs_app_api_activate');
function bfs_app_api_activate() {
    require_once BFS_APP_API_DIR . 'includes/class-install-api.php';
    BFS_Install_API::activate();
}

// Auto-run installer if table or version is missing (self-healing / migration support)
add_action('plugins_loaded', function() {
    if (!get_option('bfs_db_version')) {
        require_once BFS_APP_API_DIR . 'includes/class-install-api.php';
        BFS_Install_API::activate();
    }
}, 5);

// CORS headers for headless REST endpoints
add_action('rest_api_init', static function () {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', static function ($value) {
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '*';
        $allowed = apply_filters('bfs_allowed_origins', apply_filters('bfs_allowed_origins', [$origin]));

        if (in_array($origin, $allowed, true) || in_array('*', $allowed, true)) {
            header('Access-Control-Allow-Origin: '  . esc_url_raw($origin));
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Cart-Key');
        }
        return $value;
    });
}, 15);

/**
 * Initialize the plugin.
 */
function bfs_app_api_init()
{
    // Include the main API class.
    require_once BFS_APP_API_DIR . 'includes/class-header-api.php';

    // Instantiate the Header API class to register endpoints.
    $header_api = new BFS_Header_API();
    $header_api->register_routes();

    // Include the Footer API class.
    require_once BFS_APP_API_DIR . 'includes/class-footer-api.php';

    // Instantiate the Footer API class to register endpoints.
    $footer_api = new BFS_Footer_API();
    $footer_api->register_routes();

    // Include the Homepage API class.
    require_once BFS_APP_API_DIR . 'includes/class-homepage-api.php';

    // Instantiate the Homepage API class to register endpoints.
    $homepage_api = new BFS_Homepage_API();
    $homepage_api->register_routes();

    // Include the Instagram Feed API class.
    require_once BFS_APP_API_DIR . 'includes/class-instagram-feed-api.php';

    // Instantiate and register routes for the Instagram Feed API class.
    $instagram_feed_api = new BFS_Instagram_Feed_API();
    $instagram_feed_api->register_routes();

    // Include the Account API class.
    require_once BFS_APP_API_DIR . 'includes/class-account-api.php';

    // Instantiate the Account API class to register endpoints.
    $account_api = new BFS_Account_API();
    $account_api->register_routes();

    // Include the Addresses API class.
    require_once BFS_APP_API_DIR . 'includes/class-addresses-api.php';

    // Instantiate the Addresses API class to register endpoints.
    $addresses_api = new BFS_Addresses_API();
    $addresses_api->register_routes();

    // Include the Reviews API class.
    require_once BFS_APP_API_DIR . 'includes/class-reviews-api.php';

    // Instantiate the Reviews API class to register endpoints.
    $reviews_api = new BFS_Reviews_API();
    $reviews_api->register_routes();

    // Register Cart API endpoints.
    $cart_api = new BFS_Cart_API();
    $cart_api->register_routes();

    // Register Coupon API endpoints.
    $coupon_api = new BFS_Coupon_API();
    $coupon_api->register_routes();

    // Register Checkout API endpoints.
    $checkout_api = new BFS_Checkout_API();
    $checkout_api->register_routes();

    // Register Batch API endpoints.
    $batch_api = new BFS_Batch_API();
    $batch_api->register_routes();

    // Register Shipping API endpoints.
    $shipping_api = new BFS_Shipping_API();
    $shipping_api->register_routes();

    // Register Fees API endpoints.
    $fees_api = new BFS_Fees_API();
    $fees_api->register_routes();

    // Register JWT Auth API endpoints.
    BFS_JWT_API::register_routes();

    // Register Products API endpoints.
    $products_api = new BFS_Products_API();
    $products_api->register_routes();
}
add_action('rest_api_init', 'bfs_app_api_init');

// Include and initialize Social Settings in the Customizer.
require_once BFS_APP_API_DIR . 'customizer/class-bfs-customizer.php';
$bfs_customizer = new BFS_Customizer();
$bfs_customizer->init();

// Include and initialize Footer Customizer settings.
require_once BFS_APP_API_DIR . 'includes/class-footer-customizer.php';
$footer_customizer = new BFS_Footer_Customizer();
$footer_customizer->init();

// Include and initialize Header Customizer settings.
require_once BFS_APP_API_DIR . 'includes/class-header-customizer.php';
$header_customizer = new BFS_Header_Customizer();
$header_customizer->init();


