<?php
/**
 * Widget generator service with chat history and versioning.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates the generation, saving, and versioning of AI-generated widgets.
 *
 * @since 1.0.0
 */
class Widget_Builder_AI_Generator {

	/**
	 * Meta key for chat history.
	 */
	const META_CHAT_HISTORY      = 'widget_builder_ai_chat_history';

	/**
	 * Meta key for current files.
	 */
	const META_FILES             = 'widget_builder_ai_files';

	/**
	 * Meta key for file storage locations.
	 */
	const META_FILE_STORAGE      = 'widget_builder_ai_file_storage';

	/**
	 * Meta key for widget configuration.
	 */
	const META_WIDGET_CONFIG     = 'widget_builder_ai_widget_config';

	/**
	 * AI handler instance.
	 *
	 * @var Widget_Builder_AI_AI_Handler
	 */
	private $ai_handler;

	/**
	 * Version manager instance.
	 *
	 * @var Widget_Builder_AI_Version_Manager
	 */
	private $version_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->ai_handler      = new Widget_Builder_AI_AI_Handler();
		$this->version_manager = new Widget_Builder_AI_Version_Manager();
	}

	/**
	 * Generates a widget spec and persists it.
	 *
	 * @param string $message       The user prompt.
	 * @param string $model         The AI model to use.
	 * @param int    $widget_id     Optional widget ID for updates.
	 * @param array  $widget_config Optional widget configuration.
	 * @return array|WP_Error Response data or error.
	 */
	public function generate( $message, $model = 'gpt-4.1-mini', $widget_id = 0, $widget_config = array() ) {
		$widget_id = absint( $widget_id );
		$message   = (string) $message;
		$widget_config = $this->normalize_widget_config( $widget_config );

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
				'widget_id'    => $widget_id,
				'chat_history' => $chat_history,
				'widget_config'=> $widget_config,
			),
			$model,
			$widget_config
		);

		$resolved_widget_config = $this->normalize_widget_config(
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

		$files = array(
			'widget.php' => $this->build_widget_php_from_spec( $widget_id, $spec, $resolved_widget_config ),
			'style.css'  => $this->normalize_css_unminified( $spec['css'] ),
			'script.js'  => $this->normalize_js_unminified( $spec['js'], $this->build_widget_name_from_title( $resolved_widget_config['title'] ) ),
		);
		$files = $this->filter_optional_files( $files );

		update_post_meta( $widget_id, self::META_FILES, $files );
		$storage = $this->persist_widget_files( $widget_id, $files );

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
	 * Manually saves widget files and creates a new version.
	 *
	 * @param int    $widget_id    The widget ID.
	 * @param array  $files        The files to save.
	 * @param string $model        The model name (or manual-save).
	 * @param string $summary      A summary of changes.
	 * @param string $widget_title The widget title.
	 * @return array|WP_Error Success data or error.
	 */
	public function save_files( $widget_id, $files, $model = 'manual-save', $summary = 'Manual code update', $widget_title = '' ) {
		$widget_id = absint( $widget_id );
		$widget_title = sanitize_text_field( (string) $widget_title );

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
		$normalized = $this->filter_optional_files( $normalized );

		update_post_meta( $widget_id, self::META_FILES, $normalized );
		$storage = $this->persist_widget_files( $widget_id, $normalized );
		$post = get_post( $widget_id );

		$existing_widget_config = get_post_meta( $widget_id, self::META_WIDGET_CONFIG, true );
		$resolved_widget_config = $this->normalize_widget_config(
			$existing_widget_config,
			$post ? $post->post_title : $widget_title,
			'eicon-code',
			'basic'
		);
		update_post_meta( $widget_id, self::META_WIDGET_CONFIG, $resolved_widget_config );

		$spec = $this->build_spec_from_saved_files(
			$widget_id,
			$normalized,
			$post ? $post->post_title : $widget_title
		);

		$version = $this->version_manager->create_version( $widget_id, $normalized, $model, $summary );

		return array(
			'success'   => true,
			'widget_id' => $widget_id,
			'title'     => $post ? $post->post_title : $widget_title,
			'runtime_widget_id' => 0,
			'preview_url' => $this->get_preview_url( $widget_id ),
			'version'   => $version,
			'files'     => $normalized,
			'widget_config' => $resolved_widget_config,
			'storage'   => $storage,
		);
	}

	/**
	 * Retrieves the full payload for a widget.
	 *
	 * @param int $widget_id The widget ID.
	 * @return array|WP_Error The widget payload or error.
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
			'widget_id'        => $widget_id,
			'title'            => $post->post_title,
			'chat_history'     => $this->get_chat_history( $widget_id ),
			'current_version'  => $this->version_manager->get_current_version_number( $widget_id ),
			'versions'         => $this->version_manager->get_versions_list( $widget_id ),
			'files'            => is_array( $files ) ? $files : array(),
			'storage'          => is_array( $storage ) ? $storage : array(),
			'widget_config'    => is_array( $widget_config ) ? $widget_config : array(),
			'runtime_widget_id'=> 0,
			'preview_url'      => $this->get_preview_url( $widget_id ),
		);
	}

	/**
	 * Rolls back a widget to a specific version.
	 *
	 * @param int $widget_id The widget ID.
	 * @param int $version   The version number.
	 * @return array|WP_Error Success data or error.
	 */
	public function rollback_version( $widget_id, $version ) {
		$widget_id = absint( $widget_id );
		$version   = (int) $version;

		$version_data = $this->version_manager->get_version( $widget_id, $version );
		if ( ! $version_data || empty( $version_data['files'] ) ) {
			return new WP_Error( 'version_not_found', __( 'Version not found.', 'widget-builder-ai' ) );
		}

		update_post_meta( $widget_id, self::META_FILES, $version_data['files'] );
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
	 * Retrieves the chat history for a widget.
	 *
	 * @param int $widget_id The widget ID.
	 * @return array The chat history array.
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
	 * Adds a message to the widget's chat history.
	 *
	 * @param int   $widget_id The widget ID.
	 * @param array $message   The message data.
	 * @return void
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
	 * Inserts or updates the widget custom post entry.
	 *
	 * @param int    $widget_id Existing widget post ID.
	 * @param string $title     Widget post title.
	 * @return int|WP_Error Created or updated post ID on success, WP_Error on failure.
	 */
	private function upsert_widget_post( $widget_id, $title ) {
		$data = array(
			'post_title'  => sanitize_text_field( $title ),
			'post_status' => 'publish',
			'post_type'   => 'widget_builder_ai',
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
	 * Builds the Elementor editor preview URL for a widget.
	 *
	 * @param int $widget_id Widget post ID.
	 * @return string Preview URL or an empty string when unavailable.
	 */
	private function get_preview_url( $widget_id ) {
		$widget_id = absint( $widget_id );
		if ( $widget_id <= 0 ) {
			return '';
		}

		return admin_url( 'post.php?post=' . $widget_id . '&action=elementor' );
	}

	/**
	 * Builds the main widget PHP file content from the generated spec.
	 *
	 * @param int   $widget_id     Widget post ID.
	 * @param array $spec          Generated widget specification.
	 * @param array $widget_config Widget configuration overrides.
	 * @return string Widget PHP source code.
	 */
	private function build_widget_php_from_spec( $widget_id, $spec, $widget_config = array() ) {
		$widget_config = $this->normalize_widget_config(
			$widget_config,
			isset( $spec['title'] ) ? $spec['title'] : '',
			isset( $spec['icon'] ) ? $spec['icon'] : '',
			isset( $spec['categories'][0] ) ? $spec['categories'][0] : 'basic'
		);

		$class_name = $this->build_widget_class_name_from_title( $widget_config['title'], $widget_id );
		$widget_name = $this->build_widget_name_from_title( $widget_config['title'] );
		$title_literal = var_export( $widget_config['title'], true );
		$icon_literal  = var_export( $widget_config['icon'], true );
		$category      = var_export( $widget_config['category'], true );
		$markup        = $this->normalize_markup_unminified( (string) $spec['markup'] );
		$markup        = $this->indent_multiline_block( $markup, "\t\t\t" );

		return "<?php\n" .
			"namespace Elementor;\n\n" .
			"defined( 'ABSPATH' ) || exit;\n\n" .
			"class {$class_name} extends Widget_Base {\n" .
			"\tpublic function get_name() {\n" .
			"\t\treturn '{$widget_name}';\n" .
			"\t}\n\n" .
			"\tpublic function get_title() {\n" .
			"\t\treturn {$title_literal};\n" .
			"\t}\n\n" .
			"\tpublic function get_icon() {\n" .
			"\t\treturn {$icon_literal};\n" .
			"\t}\n\n" .
			"\tpublic function get_categories() {\n" .
			"\t\treturn array( {$category} );\n" .
			"\t}\n\n" .
			"\tprotected function render() {\n" .
			"\t\t\$settings = \$this->get_settings_for_display();\n" .
			"\t\t?>\n{$markup}\n\t\t<?php\n" .
			"\t}\n" .
			"}\n";
	}


	/**
	 * Persists widget files to uploads and stores file metadata.
	 *
	 * @param int   $widget_id Widget post ID.
	 * @param array $files     Virtual widget files keyed by filename.
	 * @return array Storage metadata for persisted files.
	 */
	private function persist_widget_files( $widget_id, $files ) {
		$widget_id = absint( $widget_id );
		if ( $widget_id <= 0 ) {
			return array();
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return array();
		}

		$slug       = $this->get_widget_slug( $widget_id );
		$folder     = $slug . '-' . $widget_id;
		$base_dir   = trailingslashit( $uploads['basedir'] ) . 'widget-builder-ai/widgets/' . $folder;
		$base_url   = trailingslashit( $uploads['baseurl'] ) . 'widget-builder-ai/widgets/' . $folder;
		$file_names = array(
			'widget.php' => $slug . '-' . $widget_id . '.widget.php',
			'style.css'  => $slug . '-' . $widget_id . '.style.css',
			'script.js'  => $slug . '-' . $widget_id . '.script.js',
		);

		if ( ! wp_mkdir_p( $base_dir ) ) {
			return array();
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return array();
		}

		foreach ( $file_names as $virtual_name => $real_name ) {
			$target_path = trailingslashit( $base_dir ) . $real_name;

			if ( 'widget.php' !== $virtual_name && ! $this->has_meaningful_content( isset( $files[ $virtual_name ] ) ? $files[ $virtual_name ] : '' ) ) {
				if ( $wp_filesystem->exists( $target_path ) ) {
					$wp_filesystem->delete( $target_path );
				}
				continue;
			}

			$content = isset( $files[ $virtual_name ] ) ? (string) $files[ $virtual_name ] : '';
			$wp_filesystem->put_contents( $target_path, $content );
		}

		$storage_files = array(
			'widget.php' => trailingslashit( $base_url ) . $file_names['widget.php'],
		);

		if ( isset( $files['style.css'] ) && $this->has_meaningful_content( $files['style.css'] ) ) {
			$storage_files['style.css'] = trailingslashit( $base_url ) . $file_names['style.css'];
		}

		if ( isset( $files['script.js'] ) && $this->has_meaningful_content( $files['script.js'] ) ) {
			$storage_files['script.js'] = trailingslashit( $base_url ) . $file_names['script.js'];
		}

		$storage = array(
			'directory' => $base_dir,
			'url'       => $base_url,
			'files'     => $storage_files,
		);

		update_post_meta( $widget_id, self::META_FILE_STORAGE, $storage );

		return $storage;
	}

	/**
	 * Deletes all physical widget files from the uploads directory.
	 *
	 * @param int $widget_id The widget ID.
	 * @return bool True when cleanup succeeds, false otherwise.
	 */
	public function delete_widget_files( $widget_id ) {
		$widget_id = absint( $widget_id );
		if ( $widget_id <= 0 ) {
			return false;
		}

		$storage = get_post_meta( $widget_id, self::META_FILE_STORAGE, true );
		$directory = is_array( $storage ) && ! empty( $storage['directory'] ) ? (string) $storage['directory'] : '';

		if ( '' === $directory ) {
			$uploads = wp_upload_dir();
			if ( ! empty( $uploads['error'] ) ) {
				return false;
			}

			$directory = trailingslashit( $uploads['basedir'] ) . 'widget-builder-ai/widgets/' . $this->get_widget_slug( $widget_id ) . '-' . $widget_id;
		}

		$this->delete_directory_recursively( $directory );
		delete_post_meta( $widget_id, self::META_FILE_STORAGE );
		delete_post_meta( $widget_id, self::META_FILES );

		return true;
	}

	/**
	 * Builds a deterministic slug for the widget storage folder.
	 *
	 * @param int $widget_id Widget post ID.
	 * @return string Sanitized widget slug.
	 */
	private function get_widget_slug( $widget_id ) {
		$title = get_the_title( $widget_id );
		$slug  = sanitize_title( $title );

		if ( '' === $slug ) {
			$slug = 'widget-builder-ai';
		}

		return $slug;
	}

	/**
	 * Deletes a directory recursively.
	 *
	 * @param string $directory Absolute directory path.
	 * @return void
	 */
	private function delete_directory_recursively( $directory ) {
		$directory = trailingslashit( (string) $directory );
		if ( '' === $directory || ! file_exists( $directory ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		if ( $wp_filesystem && method_exists( $wp_filesystem, 'delete' ) ) {
			$wp_filesystem->delete( $directory, true );
			return;
		}

		$items = glob( $directory . '*' );
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( is_dir( $item ) ) {
					$this->delete_directory_recursively( $item );
					continue;
				}

				@unlink( $item );
			}
		}

		@rmdir( $directory );
	}

	/**
	 * Resolves a widget ID by matching the post title.
	 *
	 * @param string $widget_title Widget title to look up.
	 * @return int Matching widget ID or 0 when not found.
	 */
	private function resolve_widget_id_by_title( $widget_title ) {
		$widget_title = sanitize_text_field( (string) $widget_title );
		if ( '' === $widget_title ) {
			return 0;
		}

		$existing = get_page_by_title( $widget_title, OBJECT, 'widget_builder_ai' );
		if ( $existing && ! empty( $existing->ID ) ) {
			return absint( $existing->ID );
		}

		return 0;
	}

	/**
	 * Extracts a file payload by extension/type from a mixed files array.
	 *
	 * @param array  $files Available file map.
	 * @param string $type  File type to extract (php|css|js).
	 * @return string File content or empty string when not available.
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

			if ( 'php' === $type && $this->ends_with( $name, '.php' ) ) {
				return (string) $content;
			}

			if ( 'css' === $type && $this->ends_with( $name, '.css' ) ) {
				return (string) $content;
			}

			if ( 'js' === $type && $this->ends_with( $name, '.js' ) ) {
				return (string) $content;
			}
		}

		return '';
	}

	/**
	 * Builds a minimal spec array from manually saved widget files.
	 *
	 * @param int    $widget_id Widget post ID.
	 * @param array  $files     Saved file map.
	 * @param string $title     Widget title.
	 * @return array Normalized widget spec.
	 */
	private function build_spec_from_saved_files( $widget_id, $files, $title ) {
		$title  = sanitize_text_field( (string) $title );
		$markup = $this->extract_markup_from_widget_php( isset( $files['widget.php'] ) ? (string) $files['widget.php'] : '' );

		if ( '' === $markup ) {
			$markup = '<div class="wbai-widget"><h3><?php echo esc_html( $this->get_title() ); ?></h3></div>';
		}

		return array(
			'title'        => $title ? $title : 'Untitled Widget',
			'icon'         => 'eicon-code',
			'categories'   => array( 'basic' ),
			'markup'       => $markup,
			'css'          => isset( $files['style.css'] ) ? (string) $files['style.css'] : '',
			'js'           => isset( $files['script.js'] ) ? (string) $files['script.js'] : '',
			'css_includes' => array(),
			'js_includes'  => array(),
		);
	}

	/**
	 * Extracts markup between PHP open/close tags from a widget file.
	 *
	 * @param string $widget_php Widget PHP source.
	 * @return string Extracted markup or empty string when not found.
	 */
	private function extract_markup_from_widget_php( $widget_php ) {
		$widget_php = (string) $widget_php;
		if ( '' === trim( $widget_php ) ) {
			return '';
		}

		if ( preg_match( '/\?>\s*(.*?)\s*<\?php/s', $widget_php, $matches ) ) {
			return trim( $matches[1] );
		}

		return '';
	}

	/**
	 * Builds a PHP class name from the widget title and ID.
	 *
	 * @param string $title     Widget title.
	 * @param int    $widget_id Widget post ID.
	 * @return string Generated widget class name.
	 */
	private function build_widget_class_name_from_title( $title, $widget_id ) {
		$slug  = sanitize_title( (string) $title );
		$parts = array_filter( explode( '-', $slug ) );
		$name_parts = array();

		foreach ( $parts as $part ) {
			$clean = preg_replace( '/[^a-z0-9]/i', '', $part );
			if ( '' !== $clean ) {
				$name_parts[] = ucfirst( $clean );
			}
		}

		if ( empty( $name_parts ) ) {
			$name_parts = array( 'Widget' );
		}

		return 'WidgetBuilderAi_' . implode( '_', $name_parts ) . '_' . absint( $widget_id ) . '_Widget';
	}

	/**
	 * Builds a stable internal widget name from the widget title.
	 *
	 * @param string $title Widget title.
	 * @return string Sanitized widget name.
	 */
	private function build_widget_name_from_title( $title ) {
		$slug = sanitize_title( (string) $title );
		$slug = str_replace( '-', '_', $slug );

		if ( '' === $slug ) {
			return 'widget_builder_ai';
		}

		return $slug;
	}

	/**
	 * Normalizes generated CSS into a readable unminified format.
	 *
	 * @param string $css Raw CSS content.
	 * @return string Normalized CSS.
	 */
	private function normalize_css_unminified( $css ) {
		$css = trim( (string) $css );
		if ( '' === $css ) {
			return '';
		}

		$css = preg_replace( '/\}\s*/', "}\n\n", $css );
		$css = preg_replace( '/\{\s*/', " {\n\t", $css );
		$css = preg_replace( '/;\s*/', ";\n\t", $css );
		$css = preg_replace( '/\n\t\}/', "\n}", $css );

		return trim( $css ) . "\n";
	}

	/**
	 * Normalizes generated JS and wraps it for Elementor when needed.
	 *
	 * @param string $js          Raw JavaScript content.
	 * @param string $widget_name Widget slug used for scoping.
	 * @return string Normalized JavaScript.
	 */
	private function normalize_js_unminified( $js, $widget_name = 'widget_builder_ai' ) {
		$js = trim( (string) $js );
		if ( '' === $js ) {
			return '';
		}

		$widget_name = sanitize_key( str_replace( '-', '_', (string) $widget_name ) );
		if ( '' === $widget_name ) {
			$widget_name = 'widget_builder_ai';
		}

		if ( false === strpos( $js, 'elementor.hooks.addAction' ) || false === strpos( $js, 'elementor/frontend/init' ) ) {
			$widget_var = 'WB_AI_' . strtoupper( $widget_name );
			$wrapped_js = "( function( $, elementor ) {\n" .
				"\t'use strict';\n\n" .
				"\tvar {$widget_var} = {\n" .
				"\t\tinit: function() {\n" .
				"\t\t\telementor.hooks.addAction( 'frontend/element_ready/widget', {$widget_var}.ready );\n" .
				"\t\t},\n\n" .
				"\t\tready: function( \$scope ) {\n" .
				"\t\t\tlet widget = \$scope.find('.wbai-{$widget_name}');\n";

			foreach ( explode( "\n", $js ) as $line ) {
				$wrapped_js .= "\t\t\t" . rtrim( $line ) . "\n";
			}

			$wrapped_js .= "\t\t}\n" .
				"\t};\n\n" .
				"\t$( window ).on( 'elementor/frontend/init', {$widget_var}.init );\n" .
				"\t$( window ).on( 'elementor/editor/init', {$widget_var}.init );\n" .
				"}( jQuery, window.elementorFrontend ) );";

			$js = $wrapped_js;
		}

		$js = preg_replace( '/;\s*/', ";\n", $js );
		$js = preg_replace( '/\{\s*/', "{\n", $js );
		$js = preg_replace( '/\}\s*/', "}\n", $js );

		return trim( $js ) . "\n";
	}

	/**
	 * Normalizes generated HTML markup formatting.
	 *
	 * @param string $markup Raw markup content.
	 * @return string Normalized markup.
	 */
	private function normalize_markup_unminified( $markup ) {
		$markup = trim( (string) $markup );
		if ( '' === $markup ) {
			return '';
		}

		$markup = preg_replace( '/>\s+</', ">\n<", $markup );

		return trim( $markup );
	}

	/**
	 * Indents each non-empty line in a multiline string.
	 *
	 * @param string $content Multiline content.
	 * @param string $indent  Indentation string to prepend.
	 * @return string Indented multiline content.
	 */
	private function indent_multiline_block( $content, $indent = "\t" ) {
		$content = trim( (string) $content );
		if ( '' === $content ) {
			return '';
		}

		$lines = preg_split( "/\r\n|\r|\n/", $content );
		if ( ! is_array( $lines ) ) {
			return $indent . $content;
		}

		$formatted = array();
		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				$formatted[] = '';
				continue;
			}

			$formatted[] = $indent . rtrim( $line );
		}

		return implode( "\n", $formatted );
	}

	/**
	 * Determines whether a string ends with another string.
	 *
	 * @param string $haystack Full string.
	 * @param string $needle   Trailing substring to check.
	 * @return bool True when the haystack ends with the needle.
	 */
	private function ends_with( $haystack, $needle ) {
		$haystack = (string) $haystack;
		$needle   = (string) $needle;
		$len      = strlen( $needle );

		if ( 0 === $len ) {
			return true;
		}

		return substr( $haystack, -$len ) === $needle;
	}

	/**
	 * Removes optional files that do not contain meaningful content.
	 *
	 * @param array $files Virtual file map.
	 * @return array Filtered file map.
	 */
	private function filter_optional_files( $files ) {
		$files = is_array( $files ) ? $files : array();

		if ( isset( $files['style.css'] ) && ! $this->has_meaningful_content( $files['style.css'] ) ) {
			unset( $files['style.css'] );
		}

		if ( isset( $files['script.js'] ) && ! $this->has_meaningful_content( $files['script.js'] ) ) {
			unset( $files['script.js'] );
		}

		return $files;
	}

	/**
	 * Normalizes widget configuration and applies fallback values.
	 *
	 * @param array  $widget_config     Raw widget configuration.
	 * @param string $fallback_title    Fallback widget title.
	 * @param string $fallback_icon     Fallback icon class.
	 * @param string $fallback_category Fallback widget category.
	 * @return array Sanitized widget configuration.
	 */
	private function normalize_widget_config( $widget_config, $fallback_title = '', $fallback_icon = 'eicon-code', $fallback_category = 'basic' ) {
		$widget_config = is_array( $widget_config ) ? $widget_config : array();

		$title = isset( $widget_config['title'] ) ? sanitize_text_field( (string) $widget_config['title'] ) : '';
		if ( '' === $title ) {
			$title = sanitize_text_field( (string) $fallback_title );
		}
		if ( '' === $title ) {
			$title = 'Untitled Widget';
		}

		$icon = isset( $widget_config['icon'] ) ? sanitize_text_field( (string) $widget_config['icon'] ) : '';
		if ( '' === $icon ) {
			$icon = sanitize_text_field( (string) $fallback_icon );
		}
		if ( '' === $icon ) {
			$icon = 'eicon-code';
		}

		$category = isset( $widget_config['category'] ) ? sanitize_key( (string) $widget_config['category'] ) : '';
		if ( '' === $category ) {
			$category = sanitize_key( (string) $fallback_category );
		}
		if ( '' === $category ) {
			$category = 'basic';
		}

		$libraries = array();
		if ( isset( $widget_config['libraries'] ) && is_array( $widget_config['libraries'] ) ) {
			foreach ( $widget_config['libraries'] as $library ) {
				if ( ! is_array( $library ) ) {
					continue;
				}

				$url  = esc_url_raw( isset( $library['url'] ) ? (string) $library['url'] : '' );
				$type = sanitize_key( isset( $library['type'] ) ? (string) $library['type'] : '' );

				if ( '' === $url || ! in_array( $type, array( 'css', 'js' ), true ) ) {
					continue;
				}

				$libraries[] = array(
					'url'  => $url,
					'type' => $type,
				);
			}
		}

		return array(
			'title'            => $title,
			'description'      => isset( $widget_config['description'] ) ? sanitize_textarea_field( (string) $widget_config['description'] ) : '',
			'icon'             => $icon,
			'category'         => $category,
			'selectedLibrary'  => isset( $widget_config['selectedLibrary'] ) ? sanitize_text_field( (string) $widget_config['selectedLibrary'] ) : '',
			'libraries'        => $libraries,
		);
	}

	/**
	 * Checks whether content contains non-whitespace characters.
	 *
	 * @param string $content Content to evaluate.
	 * @return bool True when content is meaningful.
	 */
	private function has_meaningful_content( $content ) {
		return '' !== trim( (string) $content );
	}
}
