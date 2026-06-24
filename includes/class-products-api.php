<?php
defined('ABSPATH') || exit;

/**
 * Class BFS_Products_API
 *
 * Product endpoints:
 *   GET /bfsapp/v1/products               — List products with pagination, sorting, search, and filters
 *   GET /bfsapp/v1/products/{id_or_slug}  — Fetch a product by its ID or slug
 */
class BFS_Products_API {

    // ── Route Registration ────────────────────────────────────────────────────

    public function register_routes(): void {
        $namespace = 'bfsapp/v1';

        // List products
        register_rest_route($namespace, '/products', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_products'],
            'permission_callback' => '__return_true',
        ]);

        // Get product by ID or slug
        register_rest_route($namespace, '/products/(?P<identifier>[a-zA-Z0-9\-_]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_product_by_identifier'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ── Endpoints ─────────────────────────────────────────────────────────────

    /**
     * List products with pagination, sorting, search, and filters.
     */
    public function get_products(\WP_REST_Request $request) {
        $page      = (int) $request->get_param('page') ?: 1;
        $per_page  = (int) $request->get_param('per_page') ?: 10;
        $orderby   = sanitize_text_field($request->get_param('orderby')) ?: 'date';
        $order     = strtoupper(sanitize_text_field($request->get_param('order'))) === 'ASC' ? 'ASC' : 'DESC';
        $search    = sanitize_text_field($request->get_param('search'));
        $category  = sanitize_text_field($request->get_param('category'));
        $tag       = sanitize_text_field($request->get_param('tag'));
        $featured  = $request->get_param('featured');
        $on_sale   = $request->get_param('on_sale');
        $min_price = $request->get_param('min_price');
        $max_price = $request->get_param('max_price');

        $query_args = [
            'status'   => 'publish',
            'limit'    => $per_page,
            'page'     => $page,
            'orderby'  => $orderby,
            'order'    => $order,
            'paginate' => true,
        ];

        // Map orderby parameter to valid WooCommerce orderby options
        if ($orderby === 'price') {
            $query_args['orderby'] = 'price';
        } elseif ($orderby === 'popularity') {
            $query_args['orderby'] = 'popularity';
        } elseif ($orderby === 'rating') {
            $query_args['orderby'] = 'rating';
        } elseif ($orderby === 'title' || $orderby === 'name') {
            $query_args['orderby'] = 'title';
        } else {
            $query_args['orderby'] = 'date';
        }

        if (!empty($search)) {
            $query_args['s'] = $search;
        }

        if (!empty($category)) {
            $query_args['category'] = [ $category ];
        }

        if (!empty($tag)) {
            $query_args['tag'] = [ $tag ];
        }

        if ($featured !== null) {
            $query_args['featured'] = (bool) $featured;
        }

        if ($on_sale !== null) {
            $query_args['on_sale'] = (bool) $on_sale;
        }

        // Handle price range filter
        if ($min_price !== null || $max_price !== null) {
            $meta_query = [];
            if ($min_price !== null) {
                $meta_query[] = [
                    'key'     => '_price',
                    'value'   => floatval($min_price),
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                ];
            }
            if ($max_price !== null) {
                $meta_query[] = [
                    'key'     => '_price',
                    'value'   => floatval($max_price),
                    'compare' => '<=',
                    'type'    => 'NUMERIC',
                ];
            }
            if (count($meta_query) > 1) {
                $meta_query['relation'] = 'AND';
            }
            $query_args['meta_query'] = $meta_query;
        }

        $results = wc_get_products($query_args);

        $formatted_products = [];
        foreach ($results->products as $product) {
            $formatted_products[] = $this->format_product($product);
        }

        return rest_ensure_response([
            'success' => true,
            'data'    => $formatted_products,
            'pagination' => [
                'page'         => $page,
                'per_page'     => $per_page,
                'total'        => (int) $results->total,
                'total_pages'  => (int) $results->max_num_pages,
            ],
        ]);
    }

    /**
     * Fetch a single product by its ID or slug.
     */
    public function get_product_by_identifier(\WP_REST_Request $request) {
        $identifier = $request->get_param('identifier');
        $product = null;

        // 1. Try treating it as an ID if numeric
        if (is_numeric($identifier)) {
            $product = wc_get_product((int) $identifier);
        }

        // 2. Try treating it as a slug if no product found by ID
        if (!$product || $product->get_status() !== 'publish') {
            $slug = sanitize_title($identifier);
            $products = wc_get_products([
                'slug'   => $slug,
                'status' => 'publish',
                'limit'  => 1,
            ]);
            if (!empty($products)) {
                $product = reset($products);
            }
        }

        if (!$product || $product->get_status() !== 'publish') {
            return new \WP_Error('not_found', esc_html__('Product not found.', 'bfs-app-api'), ['status' => 404]);
        }

        return rest_ensure_response([
            'success' => true,
            'data'    => $this->format_product($product),
        ]);
    }

    // ── Helper Formatting ─────────────────────────────────────────────────────

    /**
     * Format a product object into a standardized schema.
     */
    private function format_product(\WC_Product $product): array {
        $image_id = $product->get_image_id();
        $attachment_ids = $product->get_gallery_image_ids();
        
        $images = [];
        if ($image_id) {
            $images[] = [
                'id'  => $image_id,
                'src' => wp_get_attachment_image_url($image_id, 'full'),
                'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: $product->get_name(),
            ];
        } else {
            $images[] = [
                'id'  => 0,
                'src' => wc_placeholder_img_src('full'),
                'alt' => $product->get_name(),
            ];
        }
        
        foreach ($attachment_ids as $attachment_id) {
            $images[] = [
                'id'  => $attachment_id,
                'src' => wp_get_attachment_image_url($attachment_id, 'full'),
                'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true) ?: $product->get_name(),
            ];
        }

        $categories = [];
        $cat_ids = $product->get_category_ids();
        foreach ($cat_ids as $cat_id) {
            $term = get_term($cat_id, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $categories[] = [
                    'id'   => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                ];
            }
        }

        $tags = [];
        $tag_ids = $product->get_tag_ids();
        foreach ($tag_ids as $tag_id) {
            $term = get_term($tag_id, 'product_tag');
            if ($term && !is_wp_error($term)) {
                $tags[] = [
                    'id'   => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                ];
            }
        }

        $attributes = [];
        foreach ($product->get_attributes() as $attr_name => $attr_obj) {
            if ($attr_obj->is_taxonomy()) {
                $taxonomy = $attr_obj->get_name();
                $terms = $attr_obj->get_terms();
                $options = [];
                foreach ($terms as $term) {
                    $options[] = [
                        'id'   => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    ];
                }
                $attributes[] = [
                    'name'        => wc_attribute_label($taxonomy),
                    'slug'        => $taxonomy,
                    'is_taxonomy' => true,
                    'options'     => $options,
                ];
            } else {
                $attributes[] = [
                    'name'        => $attr_obj->get_name(),
                    'slug'        => sanitize_title($attr_obj->get_name()),
                    'is_taxonomy' => false,
                    'options'     => $attr_obj->get_options(),
                ];
            }
        }

        $variations = [];
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $child_id) {
                $variation = wc_get_product($child_id);
                if ($variation && $variation->is_type('variation')) {
                    $variations[] = [
                        'id'             => $variation->get_id(),
                        'name'           => $variation->get_name(),
                        'sku'            => $variation->get_sku(),
                        'price'          => wc_format_decimal($variation->get_price(), 2),
                        'regular_price'  => wc_format_decimal($variation->get_regular_price(), 2),
                        'sale_price'     => wc_format_decimal($variation->get_sale_price(), 2),
                        'on_sale'        => $variation->is_on_sale(),
                        'in_stock'       => $variation->is_in_stock(),
                        'stock_quantity' => $variation->get_stock_quantity(),
                        'stock_status'   => $variation->get_stock_status(),
                        'attributes'     => $variation->get_variation_attributes(),
                    ];
                }
            }
        }

        return [
            'id'                => $product->get_id(),
            'name'              => $product->get_name(),
            'slug'              => $product->get_slug(),
            'permalink'         => $product->get_permalink(),
            'type'              => $product->get_type(),
            'status'            => $product->get_status(),
            'featured'          => $product->is_featured(),
            'description'       => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'sku'               => $product->get_sku(),
            'price'             => wc_format_decimal($product->get_price(), 2),
            'regular_price'     => wc_format_decimal($product->get_regular_price(), 2),
            'sale_price'        => wc_format_decimal($product->get_sale_price(), 2),
            'on_sale'           => $product->is_on_sale(),
            'in_stock'          => $product->is_in_stock(),
            'stock_quantity'    => $product->get_stock_quantity(),
            'stock_status'      => $product->get_stock_status(),
            'images'            => $images,
            'categories'        => $categories,
            'tags'              => $tags,
            'attributes'        => $attributes,
            'variations'        => $variations,
        ];
    }
}
