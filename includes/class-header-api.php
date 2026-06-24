<?php
/**
 * Header API Class
 * 
 * Registers and handles the /wp-json/bfsapp/v1/header endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class BFS_Header_API {

    /**
     * Namespace for the REST API
     */
    private $namespace = 'bfsapp/v1';

    /**
     * Route for the header endpoint
     */
    private $route = '/header';

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            $this->route,
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_header_data' ),
                'permission_callback' => '__return_true', // Make endpoint publicly accessible
            )
        );
    }

    /**
     * Get Header Data callback function.
     * 
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response JSON response.
     */
    public function get_header_data( $request ) {

        // 1. Website Logo URL
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        $logo_url       = '';
        if ( $custom_logo_id ) {
            $logo_data = wp_get_attachment_image_src( $custom_logo_id, 'full' );
            if ( $logo_data && isset( $logo_data[0] ) ) {
                $logo_url = $logo_data[0];
            }
        }

        // 2. Site Name
        $site_name = get_bloginfo( 'name' );

        // 3. Header Menu Items (Fetched dynamically)
        $menu_items = array();

        // Get navigation menu locations
        $locations = get_nav_menu_locations();

        // Find the main menu location. Prioritize 'menu-1' (styluza Primary), 'primary', 'main', 'header'.
        $menu_id = 0;
        if ( isset( $locations['menu-1'] ) ) {
            $menu_id = $locations['menu-1'];
        } elseif ( isset( $locations['primary'] ) ) {
            $menu_id = $locations['primary'];
        } elseif ( isset( $locations['main'] ) ) {
            $menu_id = $locations['main'];
        } elseif ( isset( $locations['header'] ) ) {
            $menu_id = $locations['header'];
        } elseif ( ! empty( $locations ) ) {
            // Fallback to the first available menu location
            $menu_id = reset( $locations );
        }

        if ( $menu_id ) {
            $nav_items = wp_get_nav_menu_items( $menu_id );

            if ( ! empty( $nav_items ) && ! is_wp_error( $nav_items ) ) {
                foreach ( $nav_items as $item ) {
                    $menu_items[] = array(
                        'id'         => (int) $item->ID,
                        'title'      => $item->title,
                        'url'        => $item->url,
                        'target'     => ! empty( $item->target ) ? $item->target : '_self',
                        'menu_order' => (int) $item->menu_order,
                        'parent'     => (int) $item->menu_item_parent,
                    );
                }
            }
        }

        // 4. Search Status, Wishlist Status, Cart Status
        $search_status   = get_option( 'bfs_header_show_search', '1' ) == '1';
        $wishlist_status = get_option( 'bfs_header_show_wishlist', '1' ) == '1';
        $cart_status     = get_option( 'bfs_header_show_cart', '1' ) == '1';

        // 5. User Login Status, 6. User Name, 7. User Email
        $is_logged_in = is_user_logged_in();
        $user_name    = '';
        $user_email   = '';
        
        if ( $is_logged_in ) {
            $current_user = wp_get_current_user();
            $user_name    = $current_user->display_name;
            $user_email   = $current_user->user_email;
        }

        // 8. Wishlist Count
        $wishlist_count = 0;
        if ( class_exists( 'BFS_Wishlist_API' ) ) {
            $wishlist_api = new BFS_Wishlist_API();
            $wishlist_count = $wishlist_api->get_wishlist_count( $request );
        } elseif ( function_exists( 'yith_wcwl_count_all_products' ) ) {
            // Get count for YITH WooCommerce Wishlist
            $wishlist_count = yith_wcwl_count_all_products();
        } elseif ( class_exists( 'TInvWL_Public_WishlistCounter' ) ) {
            // Get count for TI WooCommerce Wishlist
            $wishlist_count = TInvWL_Public_WishlistCounter::counter();
        }

        // 9. WooCommerce Cart Count & 10. WooCommerce Cart Total
        $cart_count = 0;
        $cart_total = '0.00';
        
        if ( class_exists( 'WooCommerce' ) && isset( WC()->cart ) ) {
            // Using WC() instance to fetch cart details
            $cart_count = WC()->cart->get_cart_contents_count();
            
            // Get formatted cart total (could include HTML spans depending on settings)
            // Use strip_tags to return a clean string suitable for mobile apps.
            $cart_total = wp_strip_all_tags( WC()->cart->get_cart_total() ); 
        }

        // 11. Currency Symbol & 12. Currency Code
        $currency_symbol = '';
        $currency_code   = '';
        
        if ( function_exists( 'get_woocommerce_currency_symbol' ) && function_exists( 'get_woocommerce_currency' ) ) {
            $currency_symbol = get_woocommerce_currency_symbol();
            $currency_code   = get_woocommerce_currency();
        }

        // 13. Social Media Links
        $social_settings = get_option( 'bfs_social_settings', array() );
        // Filter out empty URLs
        $social_media = array();
        if ( is_array( $social_settings ) ) {
            foreach ( $social_settings as $key => $url ) {
                if ( ! empty( $url ) ) {
                    $social_media[ $key ] = $url;
                }
            }
        }

        // Prepare the response array
        $response_data = array(
            'topbar_text'     => get_option( 'bfs_topbar_text', 'FREE shipping on US$39.00+' ),
            'logo_url'        => $logo_url,
            'site_name'       => $site_name,
            'menu_items'      => $menu_items,
            'search_status'   => $search_status,
            'wishlist_status' => $wishlist_status,
            'cart_status'     => $cart_status,
            'is_logged_in'    => $is_logged_in,
            'user_name'       => $user_name,
            'user_email'      => $user_email,
            'wishlist_count'  => $wishlist_count,
            'cart_count'      => $cart_count,
            'cart_total'      => $cart_total,
            'currency_symbol' => $currency_symbol,
            'currency_code'   => $currency_code,
            'social_media'    => $social_media,
        );

        // Ensure exactly match the requested wrapper schema if possible, but keeping current schema.
        // The API returns data array currently directly or with a wrapper. I will just add the key.
        
        // Return data in JSON format via WP_REST_Response
        return new WP_REST_Response( $response_data, 200 );
    }
}
