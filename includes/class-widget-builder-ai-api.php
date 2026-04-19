<?php
/**
 * REST API endpoints for Widget Builder AI.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the registration and callbacks for the plugin's REST API endpoints.
 *
 * @since 1.0.0
 */
class Widget_Builder_AI_API {

	/**
	 * Data generator instance.
	 *
	 * @var Widget_Builder_AI_Generator
	 */
	private $generator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->generator = new Widget_Builder_AI_Generator();
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'widget-builder-ai/v1',
			'/generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			'widget-builder-ai/v1',
			'/save',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			'widget-builder-ai/v1',
			'/save/(?P<id>\\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			'widget-builder-ai/v1',
			'/widget/(?P<id>\\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'widget' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			'widget-builder-ai/v1',
			'/widget/(?P<id>\\d+)/versions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'versions' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			'widget-builder-ai/v1',
			'/widget/(?P<id>\\d+)/rollback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rollback' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Checks if the current user has permission to manage options.
	 *
	 * @return bool True if the user can manage options, false otherwise.
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Generates a widget specification.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The REST response object.
	 */
	public function generate( WP_REST_Request $request ) {
		$params    = $request->get_json_params();
		$message   = isset( $params['message'] ) ? (string) $params['message'] : '';
		$model     = isset( $params['model'] ) ? (string) $params['model'] : 'gpt-4.1-mini';
		$widget_config = isset( $params['widget_config'] ) && is_array( $params['widget_config'] ) ? $params['widget_config'] : array();
		$widget_id = isset( $params['widget_id'] ) ? absint( $params['widget_id'] ) : 0;

		$result = $this->generator->generate( $message, $model, $widget_id, $widget_config );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
				),
				400
			);
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * Saves a widget's code and updates its version.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The REST response object.
	 */
	public function save( WP_REST_Request $request ) {
		$params    = $request->get_json_params();
		$widget_id = absint( $request->get_param( 'id' ) );
		if ( ! $widget_id && ! empty( $params['widget_id'] ) ) {
			$widget_id = absint( $params['widget_id'] );
		}
		$widget_title = isset( $params['widget_title'] ) ? sanitize_text_field( (string) $params['widget_title'] ) : '';
		$files     = isset( $params['files'] ) ? $params['files'] : array();
		$model     = isset( $params['model'] ) ? (string) $params['model'] : 'manual-save';
		$summary   = isset( $params['summary'] ) ? (string) $params['summary'] : 'Manual code update';
		$widget_config = isset( $params['widget_config'] ) && is_array( $params['widget_config'] ) ? $params['widget_config'] : array();

		$result = $this->generator->save_files( $widget_id, $files, $model, $summary, $widget_title, $widget_config );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
				),
				400
			);
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * Retrieves the payload for a specific widget.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The REST response object.
	 */
	public function widget( WP_REST_Request $request ) {
		$widget_id = absint( $request->get_param( 'id' ) );
		$result    = $this->generator->get_widget_payload( $widget_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
				),
				404
			);
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * Retrieves the version history of a widget.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The REST response object.
	 */
	public function versions( WP_REST_Request $request ) {
		$widget_id = absint( $request->get_param( 'id' ) );
		$result    = $this->generator->get_widget_payload( $widget_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
				),
				404
			);
		}

		return new WP_REST_Response(
			array(
				'success'         => true,
				'widget_id'       => $widget_id,
				'current_version' => $result['current_version'],
				'versions'        => $result['versions'],
			)
		);
	}

	/**
	 * Rolls back a widget to a specific version.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The REST response object.
	 */
	public function rollback( WP_REST_Request $request ) {
		$widget_id = absint( $request->get_param( 'id' ) );
		$params    = $request->get_json_params();
		$version   = isset( $params['version'] ) ? (int) $params['version'] : 0;

		$result = $this->generator->rollback_version( $widget_id, $version );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result->get_error_message(),
				),
				400
			);
		}

		return new WP_REST_Response( $result );
	}
}
