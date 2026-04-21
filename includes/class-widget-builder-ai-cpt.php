<?php
/**
 * Widget Builder AI CPT registration.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the registration of the custom post type and admin menu.
 *
 * @since 1.0.0
 */
class Widget_Builder_AI_CPT {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'delete_post', array( $this, 'delete_widget_assets' ), 10, 1 );
		add_action( 'wp_trash_post', array( $this, 'delete_widget_assets' ), 10, 1 );
	}

	/**
	 * Registers the custom post type.
	 *
	 * @return void
	 */
	public function register_cpt() {
		$labels = array(
			'name'               => __( 'Widget Builder AI', 'widget-builder-ai' ),
			'singular_name'      => __( 'Widget Builder AI', 'widget-builder-ai' ),
			'menu_name'          => __( 'Widget Builder AI', 'widget-builder-ai' ),
			'name_admin_bar'     => __( 'Widget Builder AI', 'widget-builder-ai' ),
			'add_new'            => __( 'Add New', 'widget-builder-ai' ),
			'add_new_item'       => __( 'Add New Widget', 'widget-builder-ai' ),
			'new_item'           => __( 'New Widget', 'widget-builder-ai' ),
			'edit_item'          => __( 'Edit Widget', 'widget-builder-ai' ),
			'view_item'          => __( 'View Widget', 'widget-builder-ai' ),
			'all_items'          => __( 'All Widgets', 'widget-builder-ai' ),
			'search_items'       => __( 'Search Widgets', 'widget-builder-ai' ),
			'not_found'          => __( 'No widgets found.', 'widget-builder-ai' ),
			'not_found_in_trash' => __( 'No widgets found in Trash.', 'widget-builder-ai' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => true,
			'exclude_from_search' => true,
			'capability_type'     => 'page',
			'hierarchical'        => false,
			'supports'            => array( 'title', 'elementor' ),
		);

		register_post_type( 'widget_builder_ai', $args );
	}

	/**
	 * Registers the admin submenu page.
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( ! post_type_exists( 'widget_builder_ai' ) ) {
			return;
		}

		add_submenu_page(
			// 'elementskit',
			'eael-settings',
			__( 'Widget Builder AI', 'widget-builder-ai' ),
			__( 'Widget Builder AI', 'widget-builder-ai' ),
			'manage_options',
			'edit.php?post_type=widget_builder_ai'
		);
	}

	/**
	 * Deletes widget assets when a widget post is deleted.
	 *
	 * @param int $post_id The ID of the post being deleted.
	 * @return void
	 */
	public function delete_widget_assets( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || 'widget_builder_ai' !== get_post_type( $post_id ) ) {
			return;
		}

		if ( ! class_exists( 'Widget_Builder_AI_Generator' ) ) {
			return;
		}

		$generator = new Widget_Builder_AI_Generator();
		$generator->delete_widget_files( $post_id );
	}
}
