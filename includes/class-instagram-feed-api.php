<?php
/**
 * Instagram Feed API Endpoint and Settings
 *
 * Registers the /wp-json/bfsapp/v1/instagram-feed endpoint
 * and adds a settings page to configure the Instagram User ID and Access Token.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BFS_Instagram_Feed_API
{

    /**
     * Initialize hooks.
     */
    public function init()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register the REST API route.
     */
    public function register_routes()
    {
        register_rest_route('bfsapp/v1', '/instagram-feed', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_instagram_feed'),
            'permission_callback' => '__return_true', // Public endpoint
        ));
    }

    /**
     * Callback function to retrieve Instagram feed data.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function get_instagram_feed($request)
    {
        $transient_key = 'bfs_instagram_feed_data';
        $cached_data = get_transient($transient_key);

        if (false !== $cached_data) {
            return new WP_REST_Response(array(
                'success' => true,
                'data' => array(
                    'instagram_posts' => $cached_data
                )
            ), 200);
        }

        $user_id = get_option('bfs_instagram_user_id', '');
        $access_token = get_option('bfs_instagram_access_token', '');

        if (empty($user_id) || empty($access_token)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'User ID or Access Token is empty.',
                'data' => array(
                    'instagram_posts' => array()
                )
            ), 200);
        }

        // Instagram Graph API endpoint (Added thumbnail_url for videos)
        $url = sprintf(
            'https://graph.instagram.com/v22.0/%s/media?fields=id,caption,media_type,media_product_type,media_url,thumbnail_url,timestamp&access_token=%s&limit=10',
            urlencode($user_id),
            urlencode($access_token)
        );

        $response = wp_remote_get($url, array(
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'WP Error: ' . $response->get_error_message(),
                'data' => array(
                    'instagram_posts' => array()
                )
            ), 200);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Instagram API Error (HTTP ' . $status_code . '): ' . $body,
                'data' => array(
                    'instagram_posts' => array()
                )
            ), 200);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['data'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'No media found in Instagram response.',
                'raw_response' => $data,
                'data' => array(
                    'instagram_posts' => array()
                )
            ), 200);
        }

        $instagram_posts = array();
        foreach ($data['data'] as $post) {
            $instagram_posts[] = array(
                'id' => isset($post['id']) ? $post['id'] : '',
                'caption' => isset($post['caption']) ? $post['caption'] : '',
                'media_type' => isset($post['media_type']) ? $post['media_type'] : '',
                'media_url' => isset($post['media_url']) ? $post['media_url'] : '',
                'thumbnail_url' => isset($post['thumbnail_url']) ? $post['thumbnail_url'] : '',
                'timestamp' => isset($post['timestamp']) ? $post['timestamp'] : '',
            );
        }

        // Cache the formatted data for 24 hours (86400 seconds)
        set_transient($transient_key, $instagram_posts, 24 * HOUR_IN_SECONDS);

        return new WP_REST_Response(array(
            'success' => true,
            'data' => array(
                'instagram_posts' => $instagram_posts
            )
        ), 200);
    }
}
