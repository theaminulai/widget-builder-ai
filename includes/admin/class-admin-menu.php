<?php
/**
 * Widget Builder AI menu registration.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the registration of the admin menu.
 *
 * @since 1.0.0
 */
class Widget_Builder_AI_Admin_Menu {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
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
}
