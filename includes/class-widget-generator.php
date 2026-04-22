<?php
/**
 * Widget generator service with chat history and versioning.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Widget_Builder_AI_Widget_Generator {
	/**
	 * Post meta key for chat history.
	 *
	 * @var string
	 */

	const META_CHAT_HISTORY  = 'widget_builder_ai_chat_history';

	/**
	 * Post meta key for virtual file map.
	 *
	 * @var string
	 */
	const META_FILES         = 'widget_builder_ai_files';

	/**
	 * Post meta key for persisted storage information.
	 *
	 * @var string
	 */
	const META_FILE_STORAGE  = 'widget_builder_ai_file_storage';

	/**
	 * Post meta key for normalized widget config.
	 *
	 * @var string
	 */
	const META_WIDGET_CONFIG = 'widget_builder_ai_widget_config';

	/**
	 * AI generation handler.
	 *
	 * @var Widget_Builder_AI_Handler
	 */
	private $ai_handler;

	/**
	 * Widget version manager.
	 *
	 * @var Widget_Builder_AI_Version_Manager
	 */
	private $version_manager;

	/**
	 * Code normalizer instance.
	 *
	 * @var Widget_Builder_AI_Code_Validator
	 */
	private $normalizer;
	/**
	 * File system instance.
	 *
	 * @var Widget_Builder_AI_Filesystem
	 */
	private $file_system;

	/**
	 * Initializes generator dependencies.
	 */
	public function __construct() {
		$this->ai_handler      = new Widget_Builder_AI_Handler();
		$this->version_manager = new Widget_Builder_AI_Version_Manager();
		$this->normalizer      = new Widget_Builder_AI_Code_Validator();
		$this->file_system     = new Widget_Builder_AI_Filesystem();
	}

	/**
	 * Generates widget files from a user prompt.
	 *
	 * @param string $message       User prompt.
	 * @param string $model         AI model slug.
	 * @param int    $widget_id     Existing widget ID or 0 for create.
	 * @param array  $widget_config Widget configuration.
	 * @return array|WP_Error Result payload on success, or error on failure.
	 */
	public function generate( $message, $model = 'gpt-4.1-mini', $widget_id = 0, $widget_config = array() ) {
		set_time_limit( 300 );

		$widget_id     = absint( $widget_id );
		$message       = (string) $message;
		$widget_config = $this->normalizer->normalize_widget_config( $widget_config );

		if ( '' === trim( $message ) ) {
			return new WP_Error( 'empty_message', __( 'Message is required.', 'widget-builder-ai' ) );
		}

		if ( $widget_id > 0 && 'widget_builder_ai' !== get_post_type( $widget_id ) ) {
			return new WP_Error( 'invalid_widget', __( 'Invalid widget id.', 'widget-builder-ai' ) );
		}

		$chat_history = $this->get_chat_history( $widget_id );
		$spec         = $this->ai_handler->generate_widget_spec(
			$message,
			array(
				'widget_id'     => $widget_id,
				'chat_history'  => $chat_history,
				'widget_config' => $widget_config,
			),
			$model,
			$widget_config
		);

		if ( is_wp_error( $spec ) ) {
			return $spec;
		}

		$resolved_widget_config = $this->normalizer->normalize_widget_config(
			$widget_config,
			$spec['title'],
			$spec['icon'],
			isset( $spec['categories'][0] ) ? $spec['categories'][0] : 'basic'
		);

		$widget_id = $this->upsert_widget_post( $widget_id, $resolved_widget_config['title'] );
		if ( is_wp_error( $widget_id ) ) {
			return $widget_id;
		}

		update_post_meta( $widget_id, self::META_WIDGET_CONFIG, $resolved_widget_config );

		// Use Gemini's full PHP class directly — do NOT rebuild from markup.
		$files = array(
			'widget.php' => $this->normalizer->normalize_php( $spec['php'] ),
			'style.css'  => $this->normalizer->normalize_css_unminified( $spec['css'] ),
			'script.js'  => $this->normalizer->normalize_js_unminified( $spec['js'], $this->normalizer->build_widget_name_from_title( $resolved_widget_config['title'] ) ),
		);
		$files = $this->normalizer->filter_optional_files( $files );

		update_post_meta( $widget_id, self::META_FILES, wp_slash( $files ) );
		$storage = $this->file_system->persist_widget_files( $widget_id, $files );

		$this->add_message_to_history(
			$widget_id,
			array(
				'timestamp' => time(),
				'role'      => 'user',
				'content'   => $message,
				'model'     => sanitize_text_field( $model ),
			)
		);

		$version = $this->version_manager->create_version( $widget_id, $files, $model, $spec['summary'] );

		$this->add_message_to_history(
			$widget_id,
			array(
				'timestamp'       => time(),
				'role'            => 'assistant',
				'content'         => $spec['summary'],
				'version_created' => $version,
				'ai_model_used'   => sanitize_text_field( $model ),
			)
		);

		return array(
			'success'           => true,
			'widget_id'         => $widget_id,
			'title'             => $resolved_widget_config['title'],
			'runtime_widget_id' => 0,
			'version'           => $version,
			'files'             => $files,
			'storage'           => $storage,
			'widget_config'     => $resolved_widget_config,
			'summary'           => $spec['summary'],
			'preview_url'       => $this->get_preview_url( $widget_id ),
		);
	}

	/**
	 * Saves editor files and creates a new version snapshot.
	 *
	 * @param int    $widget_id    Existing widget ID or 0 for create.
	 * @param array  $files        File payload.
	 * @param string $model        Source/model label.
	 * @param string $summary      Version summary.
	 * @param string $widget_title Widget title for create/resolve.
	 * @param array  $widget_config Widget configuration payload.
	 * @return array|WP_Error Result payload on success, or error on failure.
	 */
	public function save_files( $widget_id, $files, $model = 'manual-save', $summary = 'Manual code update', $widget_title = '', $widget_config = array() ) {
		$widget_id    = absint( $widget_id );
		$widget_title = sanitize_text_field( (string) $widget_title );
		$widget_config = is_array( $widget_config ) ? $widget_config : array();

		if ( $widget_id > 0 && 'widget_builder_ai' !== get_post_type( $widget_id ) ) {
			return new WP_Error( 'invalid_widget', __( 'Invalid widget id.', 'widget-builder-ai' ) );
		}

		if ( $widget_id <= 0 ) {
			$widget_id = $this->resolve_widget_id_by_title( $widget_title );
			$widget_id = $this->upsert_widget_post( $widget_id, $widget_title ? $widget_title : 'Untitled Widget' );
			if ( is_wp_error( $widget_id ) ) {
				return $widget_id;
			}
		}

		if ( ! is_array( $files ) ) {
			return new WP_Error( 'invalid_files', __( 'Files must be an object.', 'widget-builder-ai' ) );
		}

		$normalized = array(
			'widget.php' => $this->extract_file_content_by_type( $files, 'php' ),
			'style.css'  => $this->extract_file_content_by_type( $files, 'css' ),
			'script.js'  => $this->extract_file_content_by_type( $files, 'js' ),
		);
		$normalized = $this->normalizer->filter_optional_files( $normalized );
		update_post_meta( $widget_id, self::META_FILES, wp_slash( $normalized ) );
		$storage = $this->file_system->persist_widget_files( $widget_id, $normalized );
		$post    = get_post( $widget_id );

		$existing_widget_config = get_post_meta( $widget_id, self::META_WIDGET_CONFIG, true );
		$existing_widget_config = is_array( $existing_widget_config ) ? $existing_widget_config : array();
		$merged_widget_config = ! empty( $widget_config )
			? array_merge( $existing_widget_config, $widget_config )
			: $existing_widget_config;
			
		$resolved_widget_config = $this->normalizer->normalize_widget_config(
			$merged_widget_config,
			$post ? $post->post_title : $widget_title,
			'eicon-code',
			'basic'
		);
		update_post_meta( $widget_id, self::META_WIDGET_CONFIG, $resolved_widget_config );

		$version = $this->version_manager->create_version( $widget_id, $normalized, $model, $summary );

		return array(
			'success'           => true,
			'widget_id'         => $widget_id,
			'title'             => $post ? $post->post_title : $widget_title,
			'runtime_widget_id' => 0,
			'preview_url'       => $this->get_preview_url( $widget_id ),
			'version'           => $version,
			'files'             => $normalized,
			'widget_config'     => $resolved_widget_config,
			'storage'           => $storage,
		);
	}

	/**
	 * Gets full widget payload for editor hydration.
	 *
	 * @param int $widget_id Widget ID.
	 * @return array|WP_Error Widget payload or not-found error.
	 */
	public function get_widget_payload( $widget_id ) {
		$widget_id = absint( $widget_id );
		$post      = get_post( $widget_id );

		if ( ! $post || 'widget_builder_ai' !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Widget not found.', 'widget-builder-ai' ) );
		}

		$files         = get_post_meta( $widget_id, self::META_FILES, true );
		$storage       = get_post_meta( $widget_id, self::META_FILE_STORAGE, true );
		$widget_config = get_post_meta( $widget_id, self::META_WIDGET_CONFIG, true );

		return array(
			'widget_id'         => $widget_id,
			'title'             => $post->post_title,
			'chat_history'      => $this->get_chat_history( $widget_id ),
			'current_version'   => $this->version_manager->get_current_version_number( $widget_id ),
			'versions'          => $this->version_manager->get_versions_list( $widget_id ),
			'files'             => is_array( $files ) ? $files : array(),
			'storage'           => is_array( $storage ) ? $storage : array(),
			'widget_config'     => is_array( $widget_config ) ? $widget_config : array(),
			'runtime_widget_id' => 0,
			'preview_url'       => $this->get_preview_url( $widget_id ),
		);
	}

	/**
	 * Restores files from a previous version.
	 *
	 * @param int $widget_id Widget ID.
	 * @param int $version   Version number to restore.
	 * @return array|WP_Error Rollback result or error.
	 */
	public function rollback_version( $widget_id, $version ) {
		$widget_id = absint( $widget_id );
		$version   = (int) $version;

		$version_data = $this->version_manager->get_version( $widget_id, $version );
		if ( ! $version_data || empty( $version_data['files'] ) ) {
			return new WP_Error( 'version_not_found', __( 'Version not found.', 'widget-builder-ai' ) );
		}

		update_post_meta( $widget_id, self::META_FILES, wp_slash( $version_data['files'] ) );
		update_post_meta( $widget_id, Widget_Builder_AI_Version_Manager::META_CURRENT_VERSION, $version );

		$this->add_message_to_history(
			$widget_id,
			array(
				'timestamp' => time(),
				'role'      => 'assistant',
				'content'   => 'Rolled back to version ' . $version . '.',
			)
		);

		return array(
			'success' => true,
			'version' => $version,
			'files'   => $version_data['files'],
		);
	}

	/**
	 * Gets widget chat history.
	 *
	 * @param int $widget_id Widget ID.
	 * @return array Chat history list.
	 */
	public function get_chat_history( $widget_id ) {
		$widget_id = absint( $widget_id );
		if ( $widget_id <= 0 ) {
			return array();
		}
		$history = get_post_meta( $widget_id, self::META_CHAT_HISTORY, true );
		return is_array( $history ) ? $history : array();
	}

	/**
	 * Adds a chat message to widget history.
	 *
	 * @param int   $widget_id Widget ID.
	 * @param array $message   Message payload.
	 */
	public function add_message_to_history( $widget_id, $message ) {
		$widget_id = absint( $widget_id );
		if ( $widget_id <= 0 ) {
			return;
		}
		$history   = $this->get_chat_history( $widget_id );
		$history[] = $message;
		update_post_meta( $widget_id, self::META_CHAT_HISTORY, $history );
	}

	/**
	 * Inserts or updates the widget post.
	 *
	 * @param int    $widget_id Existing widget ID.
	 * @param string $title     Widget title.
	 * @return int|WP_Error Post ID on success, or WP_Error on failure.
	 */
	private function upsert_widget_post( $widget_id, $title ) {
		$data = array(
			'post_title'    => sanitize_text_field( $title ),
			'post_status'   => 'publish',
			'post_type'     => 'widget_builder_ai',
			'page_template' => 'elementor_canvas',
		);

		if ( $widget_id > 0 ) {
			$data['ID'] = $widget_id;
			$result     = wp_update_post( $data, true );
		} else {
			$result = wp_insert_post( $data, true );
		}

		return $result;
	}

	/**
	 * Gets Elementor preview URL for a widget.
	 *
	 * @param int $widget_id Widget ID.
	 * @return string Preview URL or empty string.
	 */
	private function get_preview_url( $widget_id ) {
		$widget_id = absint( $widget_id );
		if ( $widget_id <= 0 ) {
			return '';
		}
		return admin_url( 'post.php?post=' . $widget_id . '&action=elementor' );
	}

	/**
	 * Finds an existing widget ID by title.
	 *
	 * @param string $widget_title Widget title.
	 * @return int Widget ID or 0.
	 */
	private function resolve_widget_id_by_title( $widget_title ) {
		$widget_title = sanitize_text_field( (string) $widget_title );
		if ( '' === $widget_title ) {
			return 0;
		}

		$existing = get_posts(
			array(
				'post_type'   => 'widget_builder_ai',
				'post_status' => 'any',
				'title'       => $widget_title,
				'numberposts' => 1,
				'orderby'     => 'ID',
				'order'       => 'ASC',
				'fields'      => 'ids',
			)
		);

		return ! empty( $existing[0] ) ? absint( $existing[0] ) : 0;
	}

	/**
	 * Extracts file content by canonical extension type.
	 *
	 * @param array  $files File payload.
	 * @param string $type  Target type: php, css, or js.
	 * @return string File contents or empty string.
	 */
	private function extract_file_content_by_type( $files, $type ) {
		if ( ! is_array( $files ) ) {
			return '';
		}

		$type = (string) $type;

		if ( 'php' === $type && isset( $files['widget.php'] ) ) {
			return (string) $files['widget.php'];
		}
		if ( 'css' === $type && isset( $files['style.css'] ) ) {
			return (string) $files['style.css'];
		}
		if ( 'js' === $type && isset( $files['script.js'] ) ) {
			return (string) $files['script.js'];
		}

		foreach ( $files as $name => $content ) {
			if ( ! is_string( $name ) ) {
				continue;
			}
			if ( 'php' === $type && $this->normalizer->ends_with( $name, '.php' ) ) {
				return (string) $content;
			}
			if ( 'css' === $type && $this->normalizer->ends_with( $name, '.css' ) ) {
				return (string) $content;
			}
			if ( 'js' === $type && $this->normalizer->ends_with( $name, '.js' ) ) {
				return (string) $content;
			}
		}

		return '';
	}
}