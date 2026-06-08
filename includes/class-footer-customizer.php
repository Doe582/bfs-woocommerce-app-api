<?php
/**
 * Footer Customizer Settings Class
 * 
 * Registers the Customizer sections and settings for the Footer Contact Information.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class BFS_Footer_Customizer {

    /**
     * Initialize the class and set hooks.
     */
    public function init() {
        add_action( 'customize_register', array( $this, 'register_footer_customizer_settings' ), 20 );
    }

    /**
     * Register Customizer settings.
     * 
     * @param WP_Customize_Manager $wp_customize Theme Customizer object.
     */
    public function register_footer_customizer_settings( $wp_customize ) {
        
        // Add Footer Section inside the existing 'bfs_panel'
        $wp_customize->add_section( 'bfs_footer_section', array(
            'title'       => __( 'Footer', 'bfs-app-api' ),
            'description' => __( 'Manage your footer branding and contact information here.', 'bfs-app-api' ),
            'panel'       => 'bfs_panel', // This panel was created in class-bfs-customizer.php
            'priority'    => 20,
        ) );

        // Footer Logo Setting
        $wp_customize->add_setting( 'bfs_footer_logo', array(
            'default'           => '',
            'type'              => 'option',
            'sanitize_callback' => 'esc_url_raw',
            'transport'         => 'refresh',
        ) );

        $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'bfs_footer_logo', array(
            'label'       => __( 'Footer Logo', 'bfs-app-api' ),
            'section'     => 'bfs_footer_section',
        ) ) );

        // Footer Description Setting
        $wp_customize->add_setting( 'bfs_footer_description', array(
            'default'           => '',
            'type'              => 'option',
            'sanitize_callback' => 'sanitize_textarea_field',
            'transport'         => 'refresh',
        ) );

        $wp_customize->add_control( 'bfs_footer_description', array(
            'label'       => __( 'Footer Description', 'bfs-app-api' ),
            'section'     => 'bfs_footer_section',
            'type'        => 'textarea',
        ) );

        // Address Setting
        $wp_customize->add_setting( 'bfs_footer_address', array(
            'default'           => '',
            'type'              => 'option',
            'sanitize_callback' => 'sanitize_textarea_field',
            'transport'         => 'refresh',
        ) );

        $wp_customize->add_control( 'bfs_footer_address', array(
            'label'       => __( 'Address', 'bfs-app-api' ),
            'section'     => 'bfs_footer_section',
            'type'        => 'textarea',
        ) );

        // Email Setting
        $wp_customize->add_setting( 'bfs_footer_email', array(
            'default'           => '',
            'type'              => 'option',
            'sanitize_callback' => 'sanitize_email',
            'transport'         => 'refresh',
        ) );

        $wp_customize->add_control( 'bfs_footer_email', array(
            'label'       => __( 'Email', 'bfs-app-api' ),
            'section'     => 'bfs_footer_section',
            'type'        => 'email',
        ) );

        // Phone Setting
        $wp_customize->add_setting( 'bfs_footer_phone', array(
            'default'           => '',
            'type'              => 'option',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'refresh',
        ) );

        $wp_customize->add_control( 'bfs_footer_phone', array(
            'label'       => __( 'Phone Number', 'bfs-app-api' ),
            'section'     => 'bfs_footer_section',
            'type'        => 'text',
        ) );
    }
}
