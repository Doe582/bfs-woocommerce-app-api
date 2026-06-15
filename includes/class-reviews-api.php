<?php
/**
 * BFS App Reviews API endpoints.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BFS_Reviews_API
{
    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        $namespace = 'bfsapp/v1';
        $base      = 'reviews';

        // GET: Fetch reviews for a specific product
        register_rest_route($namespace, '/' . $base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_reviews'),
                'permission_callback' => '__return_true', // Publicly readable
                'args'                => array(
                    'product_id' => array(
                        'required'          => true,
                        'validate_callback' => function ($param) {
                            return is_numeric($param);
                        }
                    ),
                    'per_page' => array(
                        'required'          => false,
                        'default'           => 10,
                        'validate_callback' => function ($param) {
                            return is_numeric($param);
                        }
                    ),
                    'page' => array(
                        'required'          => false,
                        'default'           => 1,
                        'validate_callback' => function ($param) {
                            return is_numeric($param);
                        }
                    )
                ),
            ),
            // POST: Submit a new review
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'submit_review'),
                'permission_callback' => array($this, 'check_post_permission'),
                'args'                => array(
                    'product_id' => array(
                        'required'          => true,
                        'validate_callback' => function ($param) {
                            return is_numeric($param);
                        }
                    ),
                    'reviewer' => array(
                        'required'          => true,
                        'validate_callback' => function ($param) {
                            return !empty(trim($param));
                        }
                    ),
                    'reviewer_email' => array(
                        'required'          => true,
                        'validate_callback' => 'is_email'
                    ),
                    'review' => array(
                        'required'          => true,
                        'validate_callback' => function ($param) {
                            return !empty(trim($param));
                        }
                    ),
                    'rating' => array(
                        'required'          => true,
                        'validate_callback' => function ($param) {
                            return in_array((int)$param, array(1, 2, 3, 4, 5), true);
                        }
                    )
                )
            ),
        ));
    }

    /**
     * Fetch reviews for a product.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_reviews($request)
    {
        $product_id = (int) $request->get_param('product_id');
        $per_page   = (int) $request->get_param('per_page');
        $page       = (int) $request->get_param('page');

        $args = array(
            'post_id' => $product_id,
            'status'  => 'approve',
            'type'    => 'review',
            'number'  => $per_page,
            'paged'   => $page,
        );

        $comments = get_comments($args);
        $formatted_reviews = array();

        foreach ($comments as $comment) {
            $formatted_reviews[] = array(
                'id'             => (int) $comment->comment_ID,
                'reviewer'       => $comment->comment_author,
                'reviewer_email' => $comment->comment_author_email,
                'review'         => $comment->comment_content,
                'rating'         => (int) get_comment_meta($comment->comment_ID, 'rating', true),
                'date_created'   => gmdate('Y-m-d\TH:i:s', strtotime($comment->comment_date)),
                'product_id'     => (int) $comment->comment_post_ID,
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'data'    => $formatted_reviews,
        ));
    }

    /**
     * Check permission to submit a review.
     * Allows both logged-in and guest users to submit reviews unconditionally.
     */
    public function check_post_permission($request)
    {
        return true;
    }

    /**
     * Submit a new review.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function submit_review($request)
    {
        $product_id     = (int) $request->get_param('product_id');
        $reviewer       = sanitize_text_field($request->get_param('reviewer'));
        $reviewer_email = sanitize_email($request->get_param('reviewer_email'));
        $review_content = sanitize_textarea_field($request->get_param('review'));
        $rating         = (int) $request->get_param('rating');

        // Check if product exists and is published
        $product = wc_get_product($product_id);
        if (!$product || $product->get_status() !== 'publish') {
            return rest_ensure_response(new WP_Error('invalid_product', esc_html__('Invalid product ID.', 'bfs-app-api'), array('status' => 404)));
        }

        // Prepare comment data
        $comment_data = array(
            'comment_post_ID'      => $product_id,
            'comment_author'       => $reviewer,
            'comment_author_email' => $reviewer_email,
            'comment_content'      => $review_content,
            'comment_type'         => 'review',
            'comment_approved'     => wp_allow_comment(array('comment_author_email' => $reviewer_email, 'comment_author' => $reviewer)) ? 1 : 0, 
        );

        // If user is logged in, attach their user ID
        if (is_user_logged_in()) {
            $comment_data['user_id'] = get_current_user_id();
        }

        // Temporarily bypass WordPress comment flood control for the API to prevent 'wp_die' 429 errors
        add_filter('wp_is_comment_flood', '__return_false');
        
        // Insert the comment
        $comment_id = wp_new_comment($comment_data);

        // Re-enable flood control
        remove_filter('wp_is_comment_flood', '__return_false');

        if (is_wp_error($comment_id) || !$comment_id) {
            return rest_ensure_response(new WP_Error('review_failed', esc_html__('Failed to submit review.', 'bfs-app-api'), array('status' => 500)));
        }

        // Save the WooCommerce rating meta
        add_comment_meta($comment_id, 'rating', $rating);

        // Update product average rating
        WC_Comments::clear_transients($product_id);
        
        // Fetch the inserted comment to return
        $comment = get_comment($comment_id);

        $response_review = array(
            'id'             => (int) $comment->comment_ID,
            'reviewer'       => $comment->comment_author,
            'reviewer_email' => $comment->comment_author_email,
            'review'         => $comment->comment_content,
            'rating'         => $rating,
            'date_created'   => gmdate('Y-m-d\TH:i:s', strtotime($comment->comment_date)),
            'product_id'     => (int) $comment->comment_post_ID,
        );

        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Review submitted successfully.',
            'review'  => $response_review,
        ));
    }
}
