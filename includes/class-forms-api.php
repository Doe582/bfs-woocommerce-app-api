<?php
/**
 * Forms API endpoints.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BFS_Forms_API {

    /**
     * Register the routes for the objects of the controller.
     */
    public function register_routes() {
        $version   = '1';
        $namespace = 'bfsapp/v' . $version;
        $base      = 'fluentform-submit';

        register_rest_route($namespace, '/' . $base, [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'submit_fluentform'],
                'permission_callback' => '__return_true',
            ],
        ]);
        
        // Also register the generic 'forms' endpoint mentioned in earlier conversations if needed
        register_rest_route($namespace, '/forms', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'submit_fluentform'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    /**
     * Submit a FluentForm.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function submit_fluentform($request) {
        // Verify Fluent Forms is active
        if (!function_exists('wpFluentForm')) {
            return new WP_Error('fluentform_missing', 'Fluent Forms plugin is not active.', ['status' => 500]);
        }

        $form_id = $request->get_param('form_id');
        $fields  = $request->get_param('fields');

        if (empty($form_id) || empty($fields) || !is_array($fields)) {
            return new WP_Error('invalid_data', 'Form ID and fields are required.', ['status' => 400]);
        }

        try {
            $app = wpFluentForm();
            
            // We set $_POST because some internal methods/hooks might rely on it
            $original_post = $_POST;
            $original_request = $_REQUEST;
            
            $fluent_data = $fields;
            $fluent_data['form_id'] = $form_id;
            
            $_POST = array_merge($_POST, $fluent_data);
            $_REQUEST = array_merge($_REQUEST, $fluent_data);
            
            // Create a FluentForm Request object
            $requestObj = $app->request;
            $requestObj->merge($fluent_data);
            
            // Call the SubmissionHandlerService directly
            $service = $app->make(\FluentForm\App\Services\Form\SubmissionHandlerService::class);
            $response = $service->handleSubmission($fields, $form_id);
            
            // Restore $_POST
            $_POST = $original_post;
            $_REQUEST = $original_request;

            // Format the success response to match the requested output
            $formatted_response = [
                'success' => true,
                'message' => 'Form has been successfully submitted.',
            ];

            return rest_ensure_response($formatted_response);
            
        } catch (\FluentForm\Framework\Validator\ValidationException $e) {
            // Restore $_POST
            if (isset($original_post)) {
                $_POST = $original_post;
                $_REQUEST = $original_request;
            }
            $code = $e->getCode() ?: 422;
            
            // ValidationException->errors() usually returns an array of errors.
            // Some versions of FluentForm nest it in an 'errors' key, some don't.
            $validation_errors = $e->errors();
            if (!isset($validation_errors['errors'])) {
                $validation_errors = ['errors' => $validation_errors];
            }
            
            return new WP_REST_Response($validation_errors, $code);
        } catch (\Exception $e) {
            // Restore $_POST
            if (isset($original_post)) {
                $_POST = $original_post;
                $_REQUEST = $original_request;
            }
            return new WP_Error('fluentform_error', $e->getMessage(), ['status' => 500]);
        }
    }
}
