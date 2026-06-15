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

    // Include the Orders API class.
    require_once BFS_APP_API_DIR . 'includes/class-orders-api.php';

    // Instantiate the Orders API class to register endpoints.
    $orders_api = new BFS_Orders_API();
    $orders_api->register_routes();

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

/**
 * WooCommerce Store API Product Filters Fix
 *
 * Resolves strict typing issues for native filters (price, stock, sale)
 * and safely applies custom taxonomy filters (color, size, fabric, Region)
 * directly into the database query to bypass Store API restrictions on non-WC taxonomies.
 */

// 1. Fix native Store API parameter formats
add_filter('rest_request_before_callbacks', 'bfs_app_api_fix_native_store_api_params', 10, 3);
function bfs_app_api_fix_native_store_api_params($response, $handler, $request)
{
    $route = $request->get_route();

    if (strpos($route, '/wc/store/v1/products') !== false) {
        // Fix stock_status (Store API strictly expects an array)
        if (isset($_GET['stock_status']) && !empty($_GET['stock_status'])) {
            $status = sanitize_text_field(wp_unslash($_GET['stock_status']));
            $request->set_param('stock_status', array_map('trim', explode(',', $status)));
        }

        // Fix on_sale (Store API strictly expects boolean)
        if (isset($_GET['on_sale']) && !empty($_GET['on_sale'])) {
            $on_sale = sanitize_text_field(wp_unslash($_GET['on_sale']));
            $request->set_param('on_sale', ($on_sale === 'true' || $on_sale === '1'));
        }

        // Fix min_price and max_price (Store API strictly expects minor units)
        if (function_exists('wc_get_price_decimals')) {
            $multiplier = pow(10, wc_get_price_decimals());
            
            if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
                $minor_price = floatval($_GET['min_price']) * $multiplier;
                $request->set_param('min_price', strval(round($minor_price)));
            }
            
            if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
                $minor_price = floatval($_GET['max_price']) * $multiplier;
                $request->set_param('max_price', strval(round($minor_price)));
            }
        }
    }

    return $response;
}

// 2. Safely apply custom taxonomies dynamically (attributes, fabric, Region, etc)
add_action('pre_get_posts', 'bfs_app_api_custom_tax_filters', 10, 1);
function bfs_app_api_custom_tax_filters($query)
{
    if (defined('REST_REQUEST') && REST_REQUEST) {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        
        if (strpos($request_uri, '/wc/store/v1/products') !== false) {
            
            // Ensure we are targeting a product query
            $post_type = $query->get('post_type');
            if ($post_type !== 'product' && (!is_array($post_type) || !in_array('product', $post_type))) {
                return;
            }

            // Standard parameters to ignore (Store API natively handles these)
            $ignore_params = array(
                'min_price', 'max_price', 'stock_status', 'on_sale', 'page', 'per_page', 
                'search', 'category', 'tag', 'orderby', 'order', 'lang', 'rest_route', 'attributes'
            );
            
            $tax_query = $query->get('tax_query');
            if (!is_array($tax_query)) {
                $tax_query = array();
            }
            $modified = false;
            
            foreach ($_GET as $query_key => $val) {
                // Skip known standard parameters and empty/array values
                if (in_array($query_key, $ignore_params) || empty($val) || is_array($val)) {
                    continue;
                }

                $query_key_clean = sanitize_text_field(wp_unslash($query_key));
                $val_clean = sanitize_text_field(wp_unslash($val));
                
                // Potential taxonomy names (e.g. pa_color, color, pa_Region, Region)
                $tax_candidates = array(
                    'pa_' . strtolower($query_key_clean),
                    strtolower($query_key_clean),
                    $query_key_clean,
                    'pa_' . $query_key_clean
                );
                
                $actual_taxonomy = '';
                foreach ($tax_candidates as $candidate) {
                    if (taxonomy_exists($candidate)) {
                        $actual_taxonomy = $candidate;
                        break;
                    }
                }
                
                // If the parameter doesn't match a valid taxonomy, skip it
                if (empty($actual_taxonomy)) {
                    continue;
                }

                $terms_input = array_map('trim', explode(',', $val_clean));
                $term_ids = array();
                
                foreach ($terms_input as $t) {
                    // Try by slug
                    $term = get_term_by('slug', strtolower($t), $actual_taxonomy);
                    if (!$term) {
                        $term = get_term_by('slug', $t, $actual_taxonomy);
                    }
                    // Try by name
                    if (!$term) {
                        $term = get_term_by('name', $t, $actual_taxonomy);
                    }
                    
                    if ($term && !is_wp_error($term)) {
                        $term_ids[] = (int) $term->term_id;
                    }
                }
                
                if (!empty($term_ids)) {
                    $tax_query[] = array(
                        'taxonomy' => $actual_taxonomy,
                        'field'    => 'term_id',
                        'terms'    => $term_ids,
                        'operator' => 'IN',
                    );
                    $modified = true;
                }
            }
            
            if ($modified) {
                if (count($tax_query) > 1 && !isset($tax_query['relation'])) {
                    $tax_query['relation'] = 'AND';
                }
                $query->set('tax_query', $tax_query);
            }
        }
    }
}

// 3. Expose custom data (fabric, Region, price) in the Store API JSON response
add_action('woocommerce_blocks_loaded', 'bfs_app_api_expose_custom_product_data');
function bfs_app_api_expose_custom_product_data()
{
    if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
        return;
    }

    woocommerce_store_api_register_endpoint_data(array(
        'endpoint'        => 'product',
        'namespace'       => 'bfs_app',
        'schema_callback' => 'bfs_app_api_product_data_schema',
        'data_callback'   => 'bfs_app_api_product_data_callback',
    ));
}

function bfs_app_api_product_data_schema()
{
    return array(
        'fabric' => array(
            'description' => 'Fabric of the product',
            'type'        => 'array',
            'context'     => array('view'),
            'items'       => array('type' => 'string'),
        ),
        'Region' => array(
            'description' => 'Region of the product',
            'type'        => 'array',
            'context'     => array('view'),
            'items'       => array('type' => 'string'),
        ),
        'price' => array(
            'description' => 'Formatted major-unit price',
            'type'        => 'string',
            'context'     => array('view'),
        )
    );
}

function bfs_app_api_product_data_callback($product)
{
    // Fetch terms from either pa_fabric (WC attribute) or fabric (Custom Taxonomy)
    $fabric_terms = wc_get_product_terms($product->get_id(), 'pa_fabric', array('fields' => 'names'));
    if (empty($fabric_terms) || is_wp_error($fabric_terms)) {
        $fabric_terms = wc_get_product_terms($product->get_id(), 'fabric', array('fields' => 'names'));
    }

    // Fetch terms from either pa_region or Region
    $region_terms = wc_get_product_terms($product->get_id(), 'pa_region', array('fields' => 'names'));
    if (empty($region_terms) || is_wp_error($region_terms)) {
        // WordPress taxonomy names are usually lowercase, check 'region'
        $region_terms = wc_get_product_terms($product->get_id(), 'region', array('fields' => 'names'));
        if (empty($region_terms) || is_wp_error($region_terms)) {
            $region_terms = wc_get_product_terms($product->get_id(), 'Region', array('fields' => 'names'));
        }
    }

    return array(
        'fabric' => (is_array($fabric_terms) && !is_wp_error($fabric_terms)) ? $fabric_terms : array(),
        'Region' => (is_array($region_terms) && !is_wp_error($region_terms)) ? $region_terms : array(),
        'price'  => $product->get_price(),
    );
}
