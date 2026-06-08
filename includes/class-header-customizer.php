<?php
/**
 * Header Customizer Settings Class
 * 
 * Registers the Customizer sections and settings for the Header.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class BFS_Header_Customizer {

    /**
     * Initialize the class and set hooks.
     */
    public function init() {
        add_action( 'customize_register', array( $this, 'register_header_customizer_settings' ), 15 );
    }

    /**
     * Register Customizer settings.
     * 
     * @param WP_Customize_Manager $wp_customize Theme Customizer object.
     */
    public function register_header_customizer_settings( $wp_customize ) {
        
        // Add Header Section inside the existing 'bfs_panel'
        $wp_customize->add_section( 'bfs_header_section', array(
            'title'       => __( 'Header', 'bfs-app-api' ),
            'description' => __( 'Manage your header settings here.', 'bfs-app-api' ),
            'panel'       => 'bfs_panel', // This panel was created in class-bfs-customizer.php
            'priority'    => 10,
        ) );

        // Top Bar Text Setting
        $wp_customize->add_setting( 'bfs_topbar_text', array(
            'default'           => 'FREE shipping on US$39.00+',
            'type'              => 'option',
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'refresh',
        ) );

        $wp_customize->add_control( 'bfs_topbar_text', array(
            'label'       => __( 'Top Bar Text', 'bfs-app-api' ),
            'section'     => 'bfs_header_section',
            'type'        => 'text',
        ) );

        // Show Search Icon
        $wp_customize->add_setting( 'bfs_header_show_search', array(
            'default'           => '1',
            'type'              => 'option',
            'sanitize_callback' => 'absint',
            'transport'         => 'refresh',
        ) );

        $wp_customize->add_control( 'bfs_header_show_search', array(
            'label'       => __( 'Show Search Icon', 'bfs-app-api' ),
            'section'     => 'bfs_header_section',
            'type'        => 'checkbox',
        ) );

        // Show Login Icon
        $wp_customize->add_setting( 'bfs_header_show_login', array(
            'default'           => '1',
            'type'              => 'option',
            'sanitize_callback' => 'absint',
            'transport'         => 'refresh',
        ) );

        $wp_customize->add_control( 'bfs_header_show_login', array(
            'label'       => __( 'Show Login Icon', 'bfs-app-api' ),
            'section'     => 'bfs_header_section',
            'type'        => 'checkbox',
        ) );

        // Show Wishlist Icon
        $wp_customize->add_setting( 'bfs_header_show_wishlist', array(
            'default'           => '1',
            'type'              => 'option',
            'sanitize_callback' => 'absint',
            'transport'         => 'refresh',
        ) );

        $wp_customize->add_control( 'bfs_header_show_wishlist', array(
            'label'       => __( 'Show Wishlist Icon', 'bfs-app-api' ),
            'section'     => 'bfs_header_section',
            'type'        => 'checkbox',
        ) );

        // Show Cart Icon
        $wp_customize->add_setting( 'bfs_header_show_cart', array(
            'default'           => '1',
            'type'              => 'option',
            'sanitize_callback' => 'absint',
            'transport'         => 'refresh',
        ) );

        $wp_customize->add_control( 'bfs_header_show_cart', array(
            'label'       => __( 'Show Cart Icon', 'bfs-app-api' ),
            'section'     => 'bfs_header_section',
            'type'        => 'checkbox',
        ) );
    }
}
