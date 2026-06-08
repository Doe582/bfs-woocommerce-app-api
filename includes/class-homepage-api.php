<?php
/**
 * Home Page REST API Endpoint
 * 
 * Registers the /wp-json/bfsapp/v1/homepage endpoint to expose dynamic homepage data.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BFS_Homepage_API
{

    /**
     * Register the REST API route.
     */
    public function register_routes()
    {
        register_rest_route('bfsapp/v1', '/homepage', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_homepage_data'),
            'permission_callback' => '__return_true', // Public endpoint
        ));
    }

    /**
     * Callback function to retrieve homepage data.
     * 
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function get_homepage_data($request)
    {

        $shop_by_category = array();

        // Check if WooCommerce is active to avoid fatal errors
        if (taxonomy_exists('product_cat')) {

            // Fetch product categories matching the frontend design (8 categories, ordered by count)
            $terms = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'number' => 8,
                'parent' => 0, // Only top-level categories
                'orderby' => 'count',
                'order' => 'DESC',
            ));

            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    // Skip the default 'uncategorized' category
                    if ($term->slug === 'uncategorized') {
                        continue;
                    }

                    // Fetch the thumbnail ID from term meta
                    $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
                    $image_url = '';

                    if ($thumbnail_id) {
                        // Get the attachment URL for the thumbnail
                        $image_url = wp_get_attachment_url($thumbnail_id);
                    }

                    // Append to our list
                    $shop_by_category[] = array(
                        'id' => (int) $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'image' => $image_url ? $image_url : '', // Fallback to empty string if no image
                    );
                }
            }
        }

        // 2. Fetch Feature Products
        $feature_products = $this->get_products_by_query(array(
            'post_type' => 'product',
            'posts_per_page' => 10,
            'post_status' => 'publish',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_visibility',
                    'field' => 'name',
                    'terms' => 'featured',
                    'operator' => 'IN',
                ),
            ),
        ));

        // 3. Fetch Best Sellers
        $best_sellers = $this->get_products_by_query(array(
            'post_type' => 'product',
            'posts_per_page' => 10,
            'post_status' => 'publish',
            'meta_key' => 'total_sales',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
        ));

        // 4. Fetch New Products
        $new_products = $this->get_products_by_query(array(
            'post_type' => 'product',
            'posts_per_page' => 10,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        // 5. Fetch Sale Products
        $sale_products = $this->get_products_by_query(array(
            'post_type' => 'product',
            'posts_per_page' => 10,
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_sale_price',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'numeric'
                ),
                array(
                    'key' => '_min_variation_sale_price',
                    'value' => 0,
                    'compare' => '>',
                    'type' => 'numeric'
                )
            )
        ));

        // 6. Fetch Blog Posts
        $blog_posts = array();
        $blog_args = array(
            'post_type' => 'post',
            'posts_per_page' => 8,
            'post_status' => 'publish',
        );
        $blog_query = new WP_Query($blog_args);

        if ($blog_query->have_posts()) {
            while ($blog_query->have_posts()) {
                $blog_query->the_post();

                $image_url = '';
                if (has_post_thumbnail()) {
                    $image_url = get_the_post_thumbnail_url(get_the_ID(), 'full');
                }

                $post_categories = wp_get_post_categories(get_the_ID(), array('fields' => 'names'));

                $blog_posts[] = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'slug' => get_post_field('post_name', get_post()),
                    'image' => $image_url ? $image_url : '',
                    'excerpt' => wp_trim_words(get_the_excerpt(), 15),
                    'categories' => !is_wp_error($post_categories) ? $post_categories : array(),
                    'url' => get_permalink(),
                );
            }
            wp_reset_postdata();
        }

        // 7. Fetch Testimonials
        $testimonials = array();
        $testimonial_args = array(
            'post_type' => 'testimonial',
            'posts_per_page' => 10,
            'post_status' => 'publish',
        );
        $testimonial_query = new WP_Query($testimonial_args);

        if ($testimonial_query->have_posts()) {
            while ($testimonial_query->have_posts()) {
                $testimonial_query->the_post();
                $post_id = get_the_ID();

                $image_url = '';
                if (has_post_thumbnail()) {
                    $image_url = get_the_post_thumbnail_url($post_id, 'full');
                }

                $rating = get_post_meta($post_id, '_testimonial_rating', true);
                $description = get_post_meta($post_id, '_testimonial_description', true);
                $bg_image = get_post_meta($post_id, '_testimonial_bg_image', true);
                
                $terms = get_the_terms($post_id, 'testimonial_category');
                $categories = array();
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $categories[] = $term->name;
                    }
                }

                // Fallback to standard post content if the description meta is empty
                if (empty($description)) {
                    $description = wp_strip_all_tags(get_the_content());
                }

                $testimonials[] = array(
                    'id' => $post_id,
                    'name' => get_the_title(),
                    'slug' => get_post_field('post_name', get_post()),
                    'image' => $image_url ? $image_url : '',
                    'content' => $description ? $description : '',
                    'rating' => $rating ? (float) $rating : 0,
                    'bg_image' => $bg_image ? $bg_image : '',
                    'url' => get_permalink(),
                    'categories' => $categories ? $categories : array(),
                );
            }
            wp_reset_postdata();
        }

        // 8. Fetch Hero Section
        $hero_section = array();
        $front_page_id = get_option('page_on_front');
        if ($front_page_id) {
            $post = get_post($front_page_id);
            if ($post) {
                $blocks = parse_blocks($post->post_content);
                foreach ($blocks as $block) {
                    if ('styluza/hero' === $block['blockName']) {
                        $attrs = $block['attrs'];
                        $html = $block['innerHTML'];

                        $title = '';
                        $subtitle = '';
                        $description = '';

                        // Parse H1 for title and subtitle
                        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
                            $title_html = trim($matches[1]);
                            $parts = explode('<span', $title_html, 2);
                            $title = wp_strip_all_tags(trim($parts[0]));
                            $subtitle = isset($parts[1]) ? wp_strip_all_tags('<span' . $parts[1]) : '';
                        }

                        // Parse P for description
                        if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $html, $matches)) {
                            $description = wp_strip_all_tags($matches[1]);
                        }

                        $hero_section[] = array(
                            'id' => 1,
                            'title' => $title,
                            'subtitle' => $subtitle,
                            'description' => $description,
                            'image' => isset($attrs['imageUrl']) ? $attrs['imageUrl'] : 'http://bfsstyluza.webtx.co/wp-content/uploads/2026/05/hero-banner-1.png',
                            'button_text' => isset($attrs['buttonText']) ? $attrs['buttonText'] : 'SHOP NOW',
                            'button_url' => isset($attrs['buttonUrl']) ? $attrs['buttonUrl'] : '/index.php/shop/',
                        );
                        break; // Only fetch the first hero section
                    }
                }
            }
        }

        // Construct the response following the exact required schema
        $response_data = array(
            'success' => true,
            'data' => array(
                'hero_section' => $hero_section,
                'shop_by_category' => $shop_by_category,
                'feature_products' => $feature_products,
                'best_sellers' => $best_sellers,
                'new_products' => $new_products,
                'sale_products' => $sale_products,
                'blog_posts' => $blog_posts,
                'testimonials' => $testimonials,
            ),
        );

        return new WP_REST_Response($response_data, 200);
    }

    /**
     * Helper to fetch and format products based on WP_Query args.
     * 
     * @param array $args WP_Query arguments.
     * @return array Formatted products array.
     */
    private function get_products_by_query($args)
    {
        $formatted_products = array();

        if (!class_exists('WooCommerce')) {
            return $formatted_products;
        }

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if (!$product) {
                    continue;
                }

                $image_url = '';
                $image_id = $product->get_image_id();
                if ($image_id) {
                    $image_url = wp_get_attachment_url($image_id);
                }

                $formatted_products[] = array(
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'slug' => $product->get_slug(),
                    'price' => $product->get_price(),
                    'regular_price' => $product->get_regular_price(),
                    'sale_price' => $product->get_sale_price() ? $product->get_sale_price() : '',
                    'image' => $image_url ? $image_url : '',
                    'rating' => (float) $product->get_average_rating(),
                    'rating_count' => (int) $product->get_rating_count(),
                    'url' => get_permalink($product->get_id()),
                );
            }
            wp_reset_postdata();
        }

        return $formatted_products;
    }
}
