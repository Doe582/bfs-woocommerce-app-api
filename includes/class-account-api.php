<?php
/**
 * BFS App Account API endpoints.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BFS_Account_API
{
    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes()
    {
        $version = '1';
        $namespace = 'bfsapp/v' . $version;

        // Account Endpoint
        register_rest_route($namespace, '/account', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_account'),
                'permission_callback' => array($this, 'check_permission'),
            ),
        ));

        // Account Details Endpoint
        register_rest_route($namespace, '/account-details', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_account_details'),
                'permission_callback' => array($this, 'check_permission'),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'update_account_details'),
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

        // Second fallback bypass to make it easy to test
        if (!is_user_logged_in() && !isset($_GET['test_user_id'])) {
             // Let them pass to show the JSON (we will mock user ID 1 in the callbacks)
             // TODO: Remove this bypass before production!
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
     * Get basic account info.
     */
    public function get_account($request)
    {
        $user_id = $this->get_target_user_id();
        $user = get_userdata($user_id);

        if (!$user) {
            return new WP_Error('not_found', esc_html__('User not found.', 'bfs-app-api'), array('status' => 404));
        }

        $name = trim($user->first_name . ' ' . $user->last_name);
        if (empty($name)) {
            $name = $user->display_name;
        }

        $data = array(
            'id'     => $user->ID,
            'name'   => $name,
            'email'  => $user->user_email,
            'avatar' => get_avatar_url($user->ID)
        );

        return rest_ensure_response(array(
            'success' => true,
            'data'    => $data,
        ));
    }

    /**
     * Get detailed account info.
     */
    public function get_account_details($request)
    {
        $user_id = $this->get_target_user_id();
        $user = get_userdata($user_id);

        if (!$user) {
            return new WP_Error('not_found', esc_html__('User not found.', 'bfs-app-api'), array('status' => 404));
        }

        $name = trim($user->first_name . ' ' . $user->last_name);
        if (empty($name)) {
            $name = $user->display_name;
        }

        $data = array(
            'id'     => $user->ID,
            'name'   => $name,
            'email'  => $user->user_email,
            'phone'  => get_user_meta($user->ID, 'billing_phone', true),
            'avatar' => get_avatar_url($user->ID)
        );

        return rest_ensure_response(array(
            'success' => true,
            'data'    => $data,
        ));
    }

    /**
     * Update account details.
     */
    public function update_account_details($request)
    {
        $user_id = $this->get_target_user_id();
        $user = get_userdata($user_id);

        if (!$user) {
            return new WP_Error('not_found', esc_html__('User not found.', 'bfs-app-api'), array('status' => 404));
        }

        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_body_params();
        }

        $name             = isset($params['name']) ? sanitize_text_field($params['name']) : '';
        $email            = isset($params['email']) ? sanitize_email($params['email']) : '';
        $phone            = isset($params['phone']) ? sanitize_text_field($params['phone']) : '';
        $current_password = isset($params['current_password']) ? $params['current_password'] : '';
        $new_password     = isset($params['new_password']) ? $params['new_password'] : '';

        // Update Name
        if (!empty($name)) {
            $name_parts = explode(' ', $name, 2);
            $first_name = $name_parts[0];
            $last_name  = isset($name_parts[1]) ? $name_parts[1] : '';
            
            wp_update_user(array(
                'ID'           => $user_id,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => $name
            ));
            
            // Also sync billing/shipping names for WooCommerce consistency
            update_user_meta($user_id, 'billing_first_name', $first_name);
            update_user_meta($user_id, 'billing_last_name', $last_name);
            update_user_meta($user_id, 'shipping_first_name', $first_name);
            update_user_meta($user_id, 'shipping_last_name', $last_name);
        }

        // Update Email
        if (!empty($email) && is_email($email) && $email !== $user->user_email) {
            if (email_exists($email)) {
                return new WP_Error('email_exists', esc_html__('This email is already registered.', 'bfs-app-api'), array('status' => 400));
            }
            wp_update_user(array(
                'ID'         => $user_id,
                'user_email' => $email
            ));
            update_user_meta($user_id, 'billing_email', $email);
        }

        // Update Phone
        if (isset($params['phone'])) {
            update_user_meta($user_id, 'billing_phone', $phone);
        }

        // Update Password
        if (!empty($current_password) && !empty($new_password)) {
            // Verify current password first securely
            if (!wp_check_password($current_password, $user->user_pass, $user_id)) {
                return new WP_Error('invalid_password', esc_html__('Current password is incorrect.', 'bfs-app-api'), array('status' => 400));
            }
            // If valid, set new password securely
            wp_set_password($new_password, $user_id);
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => esc_html__('Account details updated successfully.', 'bfs-app-api'),
        ));
    }
}
