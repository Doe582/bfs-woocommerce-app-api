<?php
/**
 * BFS App Orders API endpoints.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BFS_Orders_API
{
    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        $version = '1';
        $namespace = 'bfsapp/v' . $version;

        // Orders List Endpoint
        register_rest_route($namespace, '/orders', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_orders'),
                'permission_callback' => array($this, 'check_permission'),
            ),
        ));

        // Single Order Details Endpoint
        register_rest_route($namespace, '/orders/(?P<order_id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_order_details'),
                'permission_callback' => array($this, 'check_permission'),
                'args'                => array(
                    'order_id' => array(
                        'validate_callback' => function ($param, $request, $key) {
                            return is_numeric($param);
                        }
                    ),
                ),
            ),
        ));
    }

    /**
     * Check if a given request has access.
     */
    public function check_permission($request)
    {
        // TEMPORARY BYPASS FOR POSTMAN TESTING: Allow access to everyone so you can see the JSON.
        // TODO: Revert this to `return is_user_logged_in();` before going live!
        return true;
    }

    /**
     * Helper function to format prices cleanly without HTML spans.
     */
    private function format_price($amount, $order = null)
    {
        if ($order) {
            $formatted = wc_price($amount, array('currency' => $order->get_currency()));
        } else {
            $formatted = wc_price($amount);
        }
        return html_entity_decode(wp_strip_all_tags($formatted));
    }

    /**
     * Get a list of orders for the current user.
     */
    public function get_orders($request)
    {
        $user_id = get_current_user_id();

        // Query args
        $args = array(
            'limit'   => 10, // TEMPORARY BYPASS: Limit to 10 for testing
            'orderby' => 'date',
            'order'   => 'DESC',
        );

        // If they are actually logged in, filter by their ID. 
        // If not, just show recent orders so they can see the JSON in Postman!
        if ($user_id > 0) {
            $args['customer_id'] = $user_id;
            $args['limit'] = -1; // Get all orders for real users
        }

        // Retrieve the orders
        $orders = wc_get_orders($args);

        $data = array();

        foreach ($orders as $order) {
            $data[] = array(
                'order_id' => $order->get_id(),
                'date'     => wc_format_datetime($order->get_date_created(), 'F j, Y'),
                'status'   => wc_get_order_status_name($order->get_status()),
                'total'    => $this->format_price($order->get_total(), $order),
                'actions'  => array(
                    'view' => '/my-account/orders/' . $order->get_id()
                )
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'data'    => $data,
        ));
    }

    /**
     * Get detailed information for a specific order.
     */
    public function get_order_details($request)
    {
        $order_id = $request->get_param('order_id');
        $order    = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('not_found', esc_html__('Order not found.', 'bfs-app-api'), array('status' => 404));
        }

        $user_id = get_current_user_id();

        // Verify the order belongs to the user
        // TEMPORARY BYPASS: If user is not logged in ($user_id == 0), let them see the JSON for testing!
        if ($user_id > 0 && $order->get_customer_id() !== $user_id) {
            return new WP_Error('rest_forbidden', esc_html__('You do not have permission to view this order.', 'bfs-app-api'), array('status' => 403));
        }

        // Prepare Order Items
        $order_items = array();
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $image   = '';
            
            // Generate product name string (append variation attributes if present)
            $product_name = $item->get_name();

            if ($product) {
                // Get full image URL
                $image_id = $product->get_image_id();
                if ($image_id) {
                    $image = wp_get_attachment_image_url($image_id, 'full');
                }
                
                // If it's a variation, the name might already include attributes, but let's be safe.
                // WooCommerce natively adds variation data to the item name, but we can also parse meta if needed.
            }

            $order_items[] = array(
                'product_name' => $product_name,
                'qty'          => $item->get_quantity(),
                'price'        => $this->format_price($order->get_item_total($item), $order),
                'image'        => $image ? $image : wc_placeholder_img_src('full'),
            );
        }

        // Prepare Price Details
        $shipping_total = (float) $order->get_shipping_total();
        $delivery_fee   = $shipping_total > 0 ? $this->format_price($shipping_total, $order) : 'FREE';

        $price_details = array(
            'subtotal'     => $this->format_price($order->get_subtotal(), $order),
            'delivery_fee' => $delivery_fee,
            'total'        => $this->format_price($order->get_total(), $order),
        );

        // Helper to format a clean address string safely
        $format_address = function($address_1, $address_2, $city, $state, $country, $postcode) {
            $states = WC()->countries->get_states($country);
            $state_name = (is_array($states) && isset($states[$state])) ? $states[$state] : $state;
            $country_name = isset(WC()->countries->countries[$country]) ? WC()->countries->countries[$country] : $country;

            $parts = array_filter(array(
                trim($address_1 . ' ' . $address_2),
                $city,
                $state_name,
                $country_name,
                $postcode
            ));
            return implode(', ', $parts);
        };

        // Prepare Billing Address
        $billing_address = array(
            'name'    => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'address' => $format_address(
                $order->get_billing_address_1(),
                $order->get_billing_address_2(),
                $order->get_billing_city(),
                $order->get_billing_state(),
                $order->get_billing_country(),
                $order->get_billing_postcode()
            ),
            'phone'   => $order->get_billing_phone(),
            'email'   => $order->get_billing_email(),
        );

        // Prepare Shipping Address
        $shipping_address = array(
            'name'    => trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),
            'address' => $format_address(
                $order->get_shipping_address_1(),
                $order->get_shipping_address_2(),
                $order->get_shipping_city(),
                $order->get_shipping_state(),
                $order->get_shipping_country(),
                $order->get_shipping_postcode()
            ),
        );

        // Fallback for shipping address if it's identical to billing or empty
        if (empty($shipping_address['name']) && empty($shipping_address['address'])) {
            $shipping_address['name']    = $billing_address['name'];
            $shipping_address['address'] = $billing_address['address'];
        }

        $data = array(
            'order_id'         => $order->get_id(),
            'order_items'      => $order_items,
            'price_details'    => $price_details,
            'billing_address'  => $billing_address,
            'shipping_address' => $shipping_address,
            'status'           => wc_get_order_status_name($order->get_status()),
        );

        return rest_ensure_response(array(
            'success' => true,
            'data'    => $data,
        ));
    }
}
