<?php
defined('ABSPATH') || exit;

/**
 * Class BFS_Fees_API
 *
 * Add/remove custom cart fees (handling, rush, COD charge etc.).
 * Admin-only write endpoints by default (configurable via filter).
 *
 * Endpoints:
 *   GET    /bfsapp/v1/cart/fees        — list current fees
 *   POST   /bfsapp/v1/cart/fee        — add a fee
 *   PUT    /bfsapp/v1/cart/fee/{id}   — update a fee
 *   DELETE /bfsapp/v1/cart/fee/{id}   — remove a fee
 */
class BFS_Fees_API {

    public function register_routes(): void {
        $ns        = 'bfsapp/v1';
        $write_perm = apply_filters('bfs_fee_write_permission', [BFS_JWT_API::class, 'require_auth']);

        register_rest_route($ns, '/cart/fees', [
            'methods'             => 'GET',
            'callback'            => [$this, 'list_fees'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/cart/fee', [
            'methods'             => 'POST',
            'callback'            => [$this, 'add'],
            'permission_callback' => $write_perm,
            'args'                => $this->fee_args(),
        ]);

        register_rest_route($ns, '/cart/fee/(?P<id>[a-z0-9\-_]+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [$this, 'update'],
                'permission_callback' => $write_perm,
                'args'                => $this->fee_args(false),
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [$this, 'remove'],
                'permission_callback' => $write_perm,
            ],
        ]);
    }

    // ── Endpoints ─────────────────────────────────────────────────────────────

    public function list_fees(\WP_REST_Request $req) {
        $cart = (new BFS_Cart_API())->load_session($req);
        return rest_ensure_response(array_values($cart['fees']));
    }

    public function add(\WP_REST_Request $req) {
        $name    = sanitize_text_field($req->get_param('name'));
        $amount  = (float) $req->get_param('amount');
        $taxable = (bool) ($req->get_param('taxable') ?? false);
        $id      = sanitize_title($name . '-' . uniqid());

        if ($amount == 0) {
            return new \WP_Error('bfs_fee_zero', __('Fee amount cannot be zero.', 'bfs-app-api'), ['status' => 400]);
        }

        $cart_ctrl = new BFS_Cart_API();
        $cart      = $cart_ctrl->load_session($req);

        // Prevent duplicate name
        foreach ($cart['fees'] as $fee) {
            if (strtolower($fee['name']) === strtolower($name)) {
                return new \WP_Error('bfs_fee_duplicate', __('A fee with this name already exists.', 'bfs-app-api'), ['status' => 400]);
            }
        }

        $cart['fees'][$id] = [
            'id'      => $id,
            'name'    => $name,
            'amount'  => $amount,
            'taxable' => $taxable,
        ];

        $cart_ctrl->save_session($req, $cart);

        return rest_ensure_response([
            'message' => __('Fee added.', 'bfs-app-api'),
            'fee'     => $cart['fees'][$id],
            'cart'    => $cart_ctrl->format_cart($cart),
        ]);
    }

    public function update(\WP_REST_Request $req) {
        $id        = sanitize_text_field($req->get_param('id'));
        $cart_ctrl = new BFS_Cart_API();
        $cart      = $cart_ctrl->load_session($req);

        if (!isset($cart['fees'][$id])) {
            return new \WP_Error('bfs_fee_not_found', __('Fee not found.', 'bfs-app-api'), ['status' => 404]);
        }

        if ($req->get_param('name') !== null) {
            $cart['fees'][$id]['name'] = sanitize_text_field($req->get_param('name'));
        }
        if ($req->get_param('amount') !== null) {
            $cart['fees'][$id]['amount'] = (float) $req->get_param('amount');
        }
        if ($req->get_param('taxable') !== null) {
            $cart['fees'][$id]['taxable'] = (bool) $req->get_param('taxable');
        }

        $cart_ctrl->save_session($req, $cart);

        return rest_ensure_response([
            'message' => __('Fee updated.', 'bfs-app-api'),
            'fee'     => $cart['fees'][$id],
            'cart'    => $cart_ctrl->format_cart($cart),
        ]);
    }

    public function remove(\WP_REST_Request $req) {
        $id        = sanitize_text_field($req->get_param('id'));
        $cart_ctrl = new BFS_Cart_API();
        $cart      = $cart_ctrl->load_session($req);

        if (!isset($cart['fees'][$id])) {
            return new \WP_Error('bfs_fee_not_found', __('Fee not found.', 'bfs-app-api'), ['status' => 404]);
        }

        $removed = $cart['fees'][$id];
        unset($cart['fees'][$id]);
        $cart_ctrl->save_session($req, $cart);

        return rest_ensure_response([
            'message' => __('Fee removed.', 'bfs-app-api'),
            'removed' => $removed,
            'cart'    => $cart_ctrl->format_cart($cart),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function fee_args(bool $require_name_amount = true): array {
        return [
            'name'    => ['required' => $require_name_amount, 'type' => 'string'],
            'amount'  => [
                'required' => $require_name_amount,
                'type'     => 'number',
                // Allow negative fees (discounts) if needed
            ],
            'taxable' => ['required' => false, 'type' => 'boolean', 'default' => false],
        ];
    }
}
