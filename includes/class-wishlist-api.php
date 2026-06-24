<?php
defined('ABSPATH') || exit;

/**
 * Class BFS_Wishlist_API
 *
 * Core wishlist engine. Stores wishlist product IDs as JSON in wp_bfs_wishlists.
 * Resolves session by:
 *   - Logged-in user → wishlist_key = "user_{id}"
 *   - Guest          → wishlist_key from X-Wishlist-Key / X-Cart-Key headers or wishlist_key / cart_key parameters.
 *
 * Endpoints:
 *   GET    /bfsapp/v1/wishlist          — get wishlist items
 *   POST   /bfsapp/v1/wishlist/add      — add product to wishlist
 *   DELETE /bfsapp/v1/wishlist/remove   — remove product from wishlist
 *   DELETE /bfsapp/v1/wishlist/clear    — clear all items
 *   POST   /bfsapp/v1/wishlist/transfer — guest → user merge
 */
class BFS_Wishlist_API {

    // ── Route Registration ────────────────────────────────────────────────────

    public function register_routes(): void {
        $ns = 'bfsapp/v1';

        register_rest_route($ns, '/wishlist', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_wishlist'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/wishlist/add', [
            'methods'             => 'POST',
            'callback'            => [$this, 'add_item'],
            'permission_callback' => '__return_true',
            'args' => [
                'product_id' => ['required' => true, 'type' => 'integer', 'minimum' => 1],
            ],
        ]);

        register_rest_route($ns, '/wishlist/remove', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'remove_item'],
            'permission_callback' => '__return_true',
            'args' => [
                'product_id' => ['required' => true, 'type' => 'integer', 'minimum' => 1],
            ],
        ]);

        register_rest_route($ns, '/wishlist/clear', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'clear_wishlist'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/wishlist/transfer', [
            'methods'             => 'POST',
            'callback'            => [$this, 'transfer_wishlist'],
            'permission_callback' => ['BFS_JWT_API', 'require_auth'],
            'args' => [
                'wishlist_key' => ['required' => true, 'type' => 'string'],
            ],
        ]);
    }

    // ── Endpoints ─────────────────────────────────────────────────────────────

    public function get_wishlist(\WP_REST_Request $req) {
        $items = $this->load_session($req);
        return rest_ensure_response([
            'success' => true,
            'data'    => $this->format_wishlist_items($items),
        ]);
    }

    public function add_item(\WP_REST_Request $req) {
        $product_id = (int) $req->get_param('product_id');

        // Verify product exists and is published
        $product = wc_get_product($product_id);
        if (!$product || $product->get_status() !== 'publish') {
            return new \WP_Error('bfs_product_not_found', __('Product not found.', 'bfs-app-api'), ['status' => 404]);
        }

        $items = $this->load_session($req);
        if (!in_array($product_id, $items, true)) {
            $items[] = $product_id;
            $this->save_session($req, $items);
        }

        return rest_ensure_response([
            'success' => true,
            'message' => __('Product added to wishlist.', 'bfs-app-api'),
            'data'    => $this->format_wishlist_items($items),
        ]);
    }

    public function remove_item(\WP_REST_Request $req) {
        $product_id = (int) $req->get_param('product_id');
        $items = $this->load_session($req);

        if (($key = array_search($product_id, $items, true)) !== false) {
            unset($items[$key]);
            $items = array_values($items);
            $this->save_session($req, $items);
        }

        return rest_ensure_response([
            'success' => true,
            'message' => __('Product removed from wishlist.', 'bfs-app-api'),
            'data'    => $this->format_wishlist_items($items),
        ]);
    }

    public function clear_wishlist(\WP_REST_Request $req) {
        $this->save_session($req, []);
        return rest_ensure_response([
            'success' => true,
            'message' => __('Wishlist cleared.', 'bfs-app-api'),
            'data'    => [],
        ]);
    }

    public function merge_guest_to_user(string $guest_key, int $user_id): array {
        global $wpdb;
        $table    = $wpdb->prefix . 'bfs_wishlists';
        $user_key = "user_{$user_id}";

        $guest_row = $wpdb->get_row(
            $wpdb->prepare("SELECT items FROM $table WHERE wishlist_key = %s", $guest_key)
        );

        if (!$guest_row) {
            return $this->get_by_key($user_key) ?: [];
        }

        $guest_items = json_decode($guest_row->items, true) ?: [];
        $user_items  = $this->get_by_key($user_key) ?: [];

        $merged_items = array_unique(array_merge($user_items, $guest_items));

        $this->upsert($user_key, $user_id, $merged_items);
        $wpdb->delete($table, ['wishlist_key' => $guest_key]);

        return $merged_items;
    }

    public function transfer_wishlist(\WP_REST_Request $req) {
        $guest_key = sanitize_text_field($req->get_param('wishlist_key'));
        $user_id   = get_current_user_id();

        global $wpdb;
        $table = $wpdb->prefix . 'bfs_wishlists';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE wishlist_key = %s", $guest_key));
        if (!$exists) {
            return new \WP_Error('bfs_wishlist_not_found', __('Guest wishlist not found.', 'bfs-app-api'), ['status' => 404]);
        }

        $merged_items = $this->merge_guest_to_user($guest_key, $user_id);

        return rest_ensure_response([
            'success' => true,
            'message' => __('Wishlist transferred successfully.', 'bfs-app-api'),
            'data'    => $this->format_wishlist_items($merged_items),
        ]);
    }

    // ── Session Helpers ───────────────────────────────────────────────────────

    /** Determine wishlist_key from request context */
    public function resolve_key(\WP_REST_Request $req): string {
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }

        $key = $req->get_header('x_wishlist_key')
            ?? $req->get_header('x_cart_key')
            ?? $req->get_param('wishlist_key')
            ?? $req->get_param('cart_key')
            ?? (isset($_COOKIE['bfs_wishlist_key']) ? sanitize_text_field(wp_unslash($_COOKIE['bfs_wishlist_key'])) : '')
            ?? '';

        return sanitize_text_field($key);
    }

    /** Load wishlist data for this request */
    public function load_session(\WP_REST_Request $req): array {
        $key = $this->resolve_key($req);
        if (!$key) return [];
        return $this->get_by_key($key) ?: [];
    }

    /** Save wishlist data for this request */
    public function save_session(\WP_REST_Request $req, array $items): void {
        $key = $this->resolve_key($req);
        if (!$key) return;
        $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        $this->upsert($key, $user_id, $items);
    }

    /** Get total number of items in wishlist */
    public function get_wishlist_count(\WP_REST_Request $req): int {
        return count($this->load_session($req));
    }

    // ── DB Helpers ────────────────────────────────────────────────────────────

    public function get_by_key(string $key): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT items FROM {$wpdb->prefix}bfs_wishlists WHERE wishlist_key = %s AND expires_at > NOW()",
            $key
        ));
        if (!$row) return null;
        $data = json_decode($row->items, true);
        return is_array($data) ? $data : null;
    }

    public function upsert(string $key, int $user_id, array $items): void {
        global $wpdb;
        $table   = $wpdb->prefix . 'bfs_wishlists';
        $ttl     = (int) apply_filters('bfs_wishlist_ttl', 30 * DAY_IN_SECONDS);
        $expires = gmdate('Y-m-d H:i:s', time() + $ttl);
        $json    = wp_json_encode(array_values(array_unique($items)));

        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (wishlist_key, user_id, items, expires_at)
             VALUES (%s, %d, %s, %s)
             ON DUPLICATE KEY UPDATE
               user_id    = VALUES(user_id),
               items      = VALUES(items),
               expires_at = VALUES(expires_at)",
            $key, $user_id, $json, $expires
        ));
    }

    // ── Response Formatting ───────────────────────────────────────────────────

    private function format_wishlist_items(array $product_ids): array {
        $formatted = [];
        foreach ($product_ids as $id) {
            $product = wc_get_product((int) $id);
            if ($product && $product->get_status() === 'publish') {
                $formatted[] = $this->format_product($product);
            }
        }
        return $formatted;
    }

    private function format_product(\WC_Product $product): array {
        $image_id = $product->get_image_id();
        return [
            'id'            => $product->get_id(),
            'name'          => $product->get_name(),
            'slug'          => $product->get_slug(),
            'permalink'     => $product->get_permalink(),
            'price'         => wc_format_decimal($product->get_price(), 2),
            'regular_price' => wc_format_decimal($product->get_regular_price(), 2),
            'sale_price'    => wc_format_decimal($product->get_sale_price(), 2),
            'on_sale'       => $product->is_on_sale(),
            'in_stock'      => $product->is_in_stock(),
            'stock_status'  => $product->get_stock_status(),
            'image'         => $image_id
                ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail')
                : wc_placeholder_img_src(),
        ];
    }
}
