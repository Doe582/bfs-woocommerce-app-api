<?php
defined('ABSPATH') || exit;

/**
 * Class BFS_Wishlist_Web
 *
 * Handles WooCommerce frontend wishlist integration:
 *   - Cookies for guest session
 *   - Auto merging/transfer of guest wishlists on login
 *   - Wishlist button hook enqueues on shop catalog and product pages
 *   - [bfs_wishlist] shortcode for wishlist pages
 *   - Static assets enqueuing
 */
class BFS_Wishlist_Web
{

    public function init(): void
    {
        // Enqueue styles and scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Set guest cookie if not set
        add_action('init', [$this, 'set_guest_cookie']);

        // Merge guest wishlist into user ID upon login
        add_action('wp_login', [$this, 'transfer_guest_wishlist_on_login'], 10, 2);

        // Inject wishlist toggle button in product list and detail pages
        add_action('woocommerce_after_add_to_cart_button', [$this, 'render_single_wishlist_button'], 10);
        add_action('bfs_wishlist_loop_button', [$this, 'render_loop_wishlist_button']);

        // Register the shortcode
        add_shortcode('bfs_wishlist', [$this, 'render_wishlist_shortcode']);
    }

    /**
     * Enqueue CSS & JS assets on the frontend.
     */
    public function enqueue_assets(): void
    {
        wp_enqueue_style(
            'bfs-wishlist-web-css',
            plugins_url('assets/wishlist-web.css', dirname(__FILE__)),
            [],
            BFS_APP_API_VERSION
        );

        wp_enqueue_script(
            'bfs-wishlist-web-js',
            plugins_url('assets/wishlist-web.js', dirname(__FILE__)),
            ['jquery'],
            BFS_APP_API_VERSION,
            true
        );

        // Localize rest api data
        wp_localize_script('bfs-wishlist-web-js', 'bfsWishlistData', [
            'restUrl' => esc_url_raw(rest_url('bfsapp/v1/wishlist')),
            'nonce' => wp_create_nonce('wp_rest'),
            'shopUrl' => esc_url(apply_filters('woocommerce_return_to_shop_redirect', wc_get_page_permalink('shop'))),
        ]);
    }

    /**
     * Set a unique cookie for guest users to track their wishlist.
     */
    public function set_guest_cookie(): void
    {
        if (!headers_sent() && !is_user_logged_in() && empty($_COOKIE['bfs_wishlist_key'])) {
            $guest_uuid = 'guest_' . wp_generate_uuid4();
            // Set cookie for 30 days
            setcookie(
                'bfs_wishlist_key',
                $guest_uuid,
                time() + 30 * DAY_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
            $_COOKIE['bfs_wishlist_key'] = $guest_uuid;
        }
    }

    /**
     * Automatically transfer guest wishlist database entries into logged-in user on login.
     */
    public function transfer_guest_wishlist_on_login(string $user_login, \WP_User $user): void
    {
        if (!empty($_COOKIE['bfs_wishlist_key'])) {
            $guest_key = sanitize_text_field(wp_unslash($_COOKIE['bfs_wishlist_key']));
            $wishlist_api = new BFS_Wishlist_API();
            $wishlist_api->merge_guest_to_user($guest_key, $user->ID);

            // Clear guest cookie
            setcookie(
                'bfs_wishlist_key',
                '',
                time() - 3600,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
            unset($_COOKIE['bfs_wishlist_key']);
        }
    }

    /**
     * Helper to render the wishlist heart button.
     */
    private function render_wishlist_button(string $class = ''): void
    {
        global $product;
        if (!$product)
            return;

        $wishlist_api = new BFS_Wishlist_API();
        $items = $wishlist_api->load_session(new \WP_REST_Request());
        $is_in_wishlist = in_array($product->get_id(), $items, true);

        $active_class = $is_in_wishlist ? 'active' : '';
        $title = $is_in_wishlist ? __('Remove from Wishlist', 'bfs-app-api') : __('Add to Wishlist', 'bfs-app-api');

        echo sprintf(
            '<button class="bfs-wishlist-btn %s %s" data-product-id="%d" title="%s" aria-label="%s">
                <svg class="bfs-heart-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="%s" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
                </svg>
             </button>',
            esc_attr($class),
            esc_attr($active_class),
            (int) $product->get_id(),
            esc_attr($title),
            esc_attr($title),
            $is_in_wishlist ? 'currentColor' : 'none'
        );
    }

    public function render_loop_wishlist_button(): void
    {
        $this->render_wishlist_button('bfs-loop-btn');
    }

    public function render_single_wishlist_button(): void
    {
        $this->render_wishlist_button('bfs-single-btn');
    }

    /**
     * Shortcode callback to render the wishlist table grid on the web.
     */
    public function render_wishlist_shortcode(): string
    {
        $wishlist_api = new BFS_Wishlist_API();
        $items = $wishlist_api->load_session(new \WP_REST_Request());

        ob_start();

        if (empty($items)) {
            echo '<div class="bfs-wishlist-empty">';
            echo '<p>' . esc_html__('Your wishlist is currently empty.', 'bfs-app-api') . '</p>';
            echo '<a class="button wc-backward" href="' . esc_url(apply_filters('woocommerce_return_to_shop_redirect', wc_get_page_permalink('shop'))) . '">' . esc_html__('Return to shop', 'bfs-app-api') . '</a>';
            echo '</div>';
            return ob_get_clean();
        }

        echo '<div class="bfs-wishlist-container">';
        echo '<table class="shop_table shop_table_responsive cart bfs-wishlist-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="product-remove">&nbsp;</th>';
        echo '<th class="product-thumbnail">&nbsp;</th>';
        echo '<th class="product-name">' . esc_html__('Product', 'bfs-app-api') . '</th>';
        echo '<th class="product-price">' . esc_html__('Price', 'bfs-app-api') . '</th>';
        echo '<th class="product-stock-status">' . esc_html__('Stock Status', 'bfs-app-api') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($items as $product_id) {
            $product = wc_get_product((int) $product_id);
            if (!$product || $product->get_status() !== 'publish')
                continue;

            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : wc_placeholder_img_src();
            $price_html = $product->get_price_html();
            $stock_status = $product->is_in_stock() ? 'instock' : 'outofstock';
            $stock_text = $product->is_in_stock() ? __('In Stock', 'bfs-app-api') : __('Out of Stock', 'bfs-app-api');

            echo sprintf('<tr class="bfs-wishlist-item" data-product-id="%d">', (int) $product_id);
            // Remove button
            echo '<td class="product-remove">';
            echo sprintf('<a href="#" class="bfs-wishlist-remove" data-product-id="%d">&times;</a>', (int) $product_id);
            echo '</td>';
            // Thumbnail
            echo '<td class="product-thumbnail">';
            echo sprintf('<a href="%s"><img src="%s" alt="%s"></a>', esc_url($product->get_permalink()), esc_url($image_url), esc_attr($product->get_name()));
            echo '</td>';
            // Name
            echo '<td class="product-name" data-title="' . esc_attr__('Product', 'bfs-app-api') . '">';
            echo sprintf('<a href="%s">%s</a>', esc_url($product->get_permalink()), esc_html($product->get_name()));
            echo '</td>';
            // Price
            echo '<td class="product-price" data-title="' . esc_attr__('Price', 'bfs-app-api') . '">';
            echo $price_html;
            echo '</td>';
            // Stock
            echo '<td class="product-stock-status" data-title="' . esc_attr__('Stock Status', 'bfs-app-api') . '">';
            echo sprintf('<span class="wishlist-in-stock %s">%s</span>', esc_attr($stock_status), esc_html($stock_text));
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        return ob_get_clean();
    }
}
