<?php
/**
 * BFS Customizer Settings Class
 * 
 * Registers the Customizer panels, sections, and settings for BFS App API.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class BFS_Customizer
{

    /**
     * Option key for social media settings
     */
    const OPTION_NAME = 'bfs_social_settings';

    /**
     * Initialize the class and set hooks.
     */
    public function init()
    {
        add_action('customize_register', array($this, 'register_customizer_settings'));
    }

    /**
     * Register Customizer settings.
     * 
     * @param WP_Customize_Manager $wp_customize Theme Customizer object.
     */
    public function register_customizer_settings($wp_customize)
    {

        // Add BFS Panel
        $wp_customize->add_panel('bfs_panel', array(
            'title' => __('Our App Settings', 'bfs-app-api'),
            'description' => __('Manage settings for BFS App and Theme integration.', 'bfs-app-api'),
            'priority' => 30, // High priority to appear near the top
        ));

        // Add Social Links Section
        $wp_customize->add_section('bfs_social_links_section', array(
            'title' => __('Social Links', 'bfs-app-api'),
            'description' => __('Manage your social media profile URLs. Leave a field blank to hide its corresponding icon on the website and app.', 'bfs-app-api'),
            'panel' => 'bfs_panel',
        ));

        // Define the platforms
        $platforms = array(
            'facebook' => __('Facebook URL', 'bfs-app-api'),
            'instagram' => __('Instagram URL', 'bfs-app-api'),
            'youtube' => __('YouTube URL', 'bfs-app-api'),
            'linkedin' => __('LinkedIn URL', 'bfs-app-api'),
            'twitter' => __('Twitter/X URL', 'bfs-app-api'),
        );

        // Register settings and controls for each platform
        foreach ($platforms as $id => $label) {

            // Register Setting using array notation to save inside the 'bfs_social_settings' option array.
            $setting_id = self::OPTION_NAME . '[' . $id . ']';

            $wp_customize->add_setting($setting_id, array(
                'default' => '',
                'type' => 'option',
                'sanitize_callback' => 'esc_url_raw',
                'transport' => 'refresh', // Refresh the preview window when changed
            ));

            // Register Control
            $wp_customize->add_control($setting_id, array(
                'label' => $label,
                'section' => 'bfs_social_links_section',
                'type' => 'url',
                'input_attrs' => array(
                    'placeholder' => 'https://',
                ),
            ));
        }

	}
}
