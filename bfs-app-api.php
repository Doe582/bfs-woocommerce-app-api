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
