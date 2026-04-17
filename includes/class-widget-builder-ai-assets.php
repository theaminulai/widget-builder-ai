<?php
/**
 * Widget Builder AI asset loading.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles enqueuing of scripts and styles for the plugin's admin interface.
 *
 * @since 1.0.0
 */
class Widget_Builder_AI_Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueues the plugin assets on the appropriate admin screens.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || empty( $screen->post_type ) || 'widget_builder_ai' !== $screen->post_type ) {
			return;
		}

		$file_path = WIDGET_BUILDER_AI_PLUGIN_DIR . 'build/index.asset.php';

		if ( ! file_exists( $file_path ) ) {
			return;
		}

		$asset = include $file_path;

		wp_enqueue_script(
			'widget-builder-ai',
			WIDGET_BUILDER_AI_PLUGIN_URL . 'build/index.js',
			isset( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
			isset( $asset['version'] ) ? $asset['version'] : WIDGET_BUILDER_AI_VERSION,
			array(
				'in_footer' => true,
			)
		);

		wp_enqueue_style(
			'widget-builder-ai',
			WIDGET_BUILDER_AI_PLUGIN_URL . 'build/index.css',
			array(),
			isset( $asset['version'] ) ? $asset['version'] : WIDGET_BUILDER_AI_VERSION
		);

		$current_post_id = 0;
		if ( ! empty( $_GET['post'] ) ) {
			$current_post_id = absint( wp_unslash( $_GET['post'] ) );
		}

		wp_localize_script(
			'widget-builder-ai',
			'widgetBuilderAI',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'widget-builder-ai/v1/' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'currentPostId'=> $current_post_id,
			)
		);
	}
}