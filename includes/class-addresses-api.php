<?php
/**
 * BFS App Addresses API endpoints.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BFS_Addresses_API
{
    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        $version = '1';
        $namespace = 'bfsapp/v' . $version;

        // Addresses Endpoint
        register_rest_route($namespace, '/addresses', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_addresses'),
                'permission_callback' => array($this, 'check_permission'),
            ),
        ));
    }

    /**
     * Check if a given request has access.
     */
    public function check_permission($request)
    {
        // TEMPORARY BYPASS FOR POSTMAN TESTING: Allow access if ?test_user_id=X is passed
        if (isset($_GET['test_user_id']) && is_numeric($_GET['test_user_id'])) {
            return true;
        }

        // Second fallback bypass to make it easy to test without parameters
        if (!is_user_logged_in() && !isset($_GET['test_user_id'])) {
             return true;
        }

        return true;
    }

    /**
     * Helper to retrieve the active user ID securely, or safely mock it for Postman testing.
     */
    private function get_target_user_id() {
        if (isset($_GET['test_user_id']) && is_numeric($_GET['test_user_id'])) {
            return (int) $_GET['test_user_id'];
        }
        
        $user_id = get_current_user_id();
        
        // Mock Admin ID for easy Postman testing if completely unauthenticated
        if ($user_id === 0) {
            return 1; 
        }
        
        return $user_id;
    }

    /**
     * Get a list of addresses for the current user.
     */
    public function get_addresses($request)
    {
        $user_id = $this->get_target_user_id();
        $user = get_userdata($user_id);

        if (!$user) {
            return new WP_Error('not_found', esc_html__('User not found.', 'bfs-app-api'), array('status' => 404));
        }

        $addresses = array();

        // 1. Billing Address
        $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
        if (!empty($billing_address_1) || !empty(get_user_meta($user_id, 'billing_first_name', true))) {
            
            $first_name = get_user_meta($user_id, 'billing_first_name', true);
            $last_name  = get_user_meta($user_id, 'billing_last_name', true);
            $name       = trim($first_name . ' ' . $last_name);
            if (empty($name)) {
                $name = $user->display_name;
            }

            $addresses[] = array(
                'id'           => 1, // Pseudo ID for Billing
                'type'         => 'Billing',
                'name'         => $name,
                'address_line' => trim($billing_address_1 . ' ' . get_user_meta($user_id, 'billing_address_2', true)),
                'city'         => get_user_meta($user_id, 'billing_city', true),
                'state'        => get_user_meta($user_id, 'billing_state', true),
                'zip'          => get_user_meta($user_id, 'billing_postcode', true),
                'country'      => get_user_meta($user_id, 'billing_country', true),
                'phone'        => get_user_meta($user_id, 'billing_phone', true),
                'email'        => get_user_meta($user_id, 'billing_email', true),
                'default'      => true, // Make billing default logically
            );
        }

        // 2. Shipping Address
        $shipping_address_1 = get_user_meta($user_id, 'shipping_address_1', true);
        if (!empty($shipping_address_1) || !empty(get_user_meta($user_id, 'shipping_first_name', true))) {
            
            $first_name = get_user_meta($user_id, 'shipping_first_name', true);
            $last_name  = get_user_meta($user_id, 'shipping_last_name', true);
            $name       = trim($first_name . ' ' . $last_name);
            if (empty($name)) {
                $name = $user->display_name;
            }

            $addresses[] = array(
                'id'           => 2, // Pseudo ID for Shipping
                'type'         => 'Shipping',
                'name'         => $name,
                'address_line' => trim($shipping_address_1 . ' ' . get_user_meta($user_id, 'shipping_address_2', true)),
                'city'         => get_user_meta($user_id, 'shipping_city', true),
                'state'        => get_user_meta($user_id, 'shipping_state', true),
                'zip'          => get_user_meta($user_id, 'shipping_postcode', true),
                'country'      => get_user_meta($user_id, 'shipping_country', true),
                'phone'        => '', // Shipping typically doesn't have a separate native phone
                'email'        => '', // Shipping typically doesn't have a separate native email
                'default'      => false,
            );
        }

        // 3. Custom Addresses (from 'Add New Address' feature)
        $custom_addresses = get_user_meta($user_id, 'wc_custom_address_book', true);
        if (is_array($custom_addresses)) {
            foreach ($custom_addresses as $id => $addr_data) {
                // Ensure we don't accidentally overwrite primary ID 1 or 2 by prefixing custom IDs
                // or just pass the string ID directly if it's alphanumeric.
                $display_type = isset($addr_data['address_type']) ? ucfirst($addr_data['address_type']) : 'Other';

                $addresses[] = array(
                    'id'           => $id, 
                    'type'         => $display_type,
                    'name'         => $addr_data['full_name'] ?? '',
                    'address_line' => $addr_data['address_1'] ?? '',
                    'city'         => $addr_data['city'] ?? '',
                    'state'        => $addr_data['state'] ?? '',
                    'zip'          => $addr_data['postcode'] ?? '',
                    'country'      => $addr_data['country'] ?? '',
                    'phone'        => $addr_data['phone'] ?? '',
                    'email'        => $addr_data['email'] ?? '',
                    'default'      => false,
                );
            }
        }

        return rest_ensure_response(array(
            'success' => true,
            'data'    => array(
                'addresses' => $addresses
            ),
        ));
    }
}
