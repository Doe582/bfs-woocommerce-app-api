<?php
/**
 * Footer REST API Endpoint
 * 
 * Registers the /wp-json/bfsapp/v1/footer endpoint to expose dynamic footer data.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class BFS_Footer_API {

    /**
     * Register the REST API route.
     */
    public function register_routes() {
        register_rest_route( 'bfsapp/v1', '/footer', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_footer_data' ),
            'permission_callback' => '__return_true', // Public endpoint
        ) );
    }

    /**
     * Callback function to retrieve footer data.
     * 
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function get_footer_data( $request ) {
        // 1. Social Links (from existing option)
        $social_settings = get_option( 'bfs_social_settings', array() );
        $social_links = array(
            'facebook'  => isset( $social_settings['facebook'] ) ? $social_settings['facebook'] : '',
            'instagram' => isset( $social_settings['instagram'] ) ? $social_settings['instagram'] : '',
            'youtube'   => isset( $social_settings['youtube'] ) ? $social_settings['youtube'] : '',
            'linkedin'  => isset( $social_settings['linkedin'] ) ? $social_settings['linkedin'] : '',
            'twitter'   => isset( $social_settings['twitter'] ) ? $social_settings['twitter'] : '',
        );

        // 2. Contact Information (from Customizer)
        $contact = array(
            'address' => get_option( 'bfs_footer_address', '' ),
            'email'   => get_option( 'bfs_footer_email', '' ),
            'phone'   => get_option( 'bfs_footer_phone', '' ),
        );

        // 3. Dynamic Widgets Data
        $footer_logo = '';
        $footer_description = '';
        $quick_links = array();
        $get_to_know_us = array();

        $sidebars_widgets = wp_get_sidebars_widgets();

        // 3a. Footer Logo & Description (from Customizer)
        $footer_logo = get_option( 'bfs_footer_logo', '' );
        $footer_description = get_option( 'bfs_footer_description', '' );

        // 3b. Parse footer-2 for Quick Links
        if ( ! empty( $sidebars_widgets['footer-2'] ) ) {
            foreach ( $sidebars_widgets['footer-2'] as $widget_id ) {
                if ( strpos( $widget_id, 'nav_menu-' ) === 0 ) {
                    $quick_links = $this->get_nav_menu_items_by_widget( $widget_id );
                    break; // Just grab the first menu widget
                }
            }
        }

        // 3c. Parse footer-3 for Get To Know Us
        if ( ! empty( $sidebars_widgets['footer-3'] ) ) {
            foreach ( $sidebars_widgets['footer-3'] as $widget_id ) {
                if ( strpos( $widget_id, 'nav_menu-' ) === 0 ) {
                    $get_to_know_us = $this->get_nav_menu_items_by_widget( $widget_id );
                    break;
                }
            }
        }

        // 4. Construct Response
        $response_data = array(
            'success' => true,
            'data'    => array(
                'footer_logo'        => $footer_logo,
                'footer_description' => $footer_description,
                'quick_links'        => $quick_links,
                'get_to_know_us'     => $get_to_know_us,
                'contact'            => $contact,
                'social_links'       => $social_links,
            ),
        );

        return new WP_REST_Response( $response_data, 200 );
    }

    /**
     * Helper to extract image URL and text from a widget.
     * 
     * @param string $widget_id The widget ID (e.g., custom_html-2, text-3, media_image-4)
     * @return array Contains 'image_url' and 'text'
     */
    private function parse_widget_for_content( $widget_id ) {
        $result = array( 'image_url' => '', 'text' => '' );
        
        // Parse base ID and number
        preg_match( '/^([a-z_]+)-(\d+)$/', $widget_id, $matches );
        if ( empty( $matches ) ) {
            return $result;
        }

        $id_base = $matches[1];
        $widget_number = $matches[2];
        $widget_instances = get_option( 'widget_' . $id_base );

        if ( isset( $widget_instances[ $widget_number ] ) ) {
            $instance = $widget_instances[ $widget_number ];

            if ( $id_base === 'media_image' ) {
                $result['image_url'] = isset( $instance['url'] ) ? $instance['url'] : '';
            } elseif ( $id_base === 'text' || $id_base === 'custom_html' ) {
                $content = isset( $instance['content'] ) ? $instance['content'] : ( isset( $instance['text'] ) ? $instance['text'] : '' );
                
                // Extract img src
                if ( preg_match( '/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $content, $img_matches ) ) {
                    $result['image_url'] = $img_matches[1];
                }
                
                // Extract text (strip tags and trim)
                $stripped_text = wp_strip_all_tags( $content );
                // Basic cleanup of excessive whitespace
                $stripped_text = trim( preg_replace( '/\s+/', ' ', $stripped_text ) );
                if ( ! empty( $stripped_text ) ) {
                    $result['text'] = $stripped_text;
                }
            }
        }

        return $result;
    }

    /**
     * Helper to get formatted menu items from a nav_menu widget.
     * 
     * @param string $widget_id The widget ID
     * @return array Formatted menu items
     */
    private function get_nav_menu_items_by_widget( $widget_id ) {
        $formatted_items = array();
        
        preg_match( '/-(\d+)$/', $widget_id, $matches );
        if ( empty( $matches ) ) return $formatted_items;

        $widget_number = $matches[1];
        $nav_menu_widgets = get_option( 'widget_nav_menu' );

        if ( isset( $nav_menu_widgets[ $widget_number ]['nav_menu'] ) ) {
            $menu_id = $nav_menu_widgets[ $widget_number ]['nav_menu'];
            $menu_items = wp_get_nav_menu_items( $menu_id );

            if ( $menu_items ) {
                foreach ( $menu_items as $item ) {
                    $formatted_items[] = array(
                        'id'         => $item->ID,
                        'title'      => $item->title,
                        'url'        => $item->url,
                        'target'     => $item->target ? $item->target : '_self',
                        'menu_order' => $item->menu_order,
                        'parent'     => $item->menu_item_parent,
                    );
                }
            }
        }

        return $formatted_items;
    }
}
