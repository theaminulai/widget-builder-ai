<?php
/**
 * Widget generator service with chat history and versioning.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Widget_Builder_AI_Generator {
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
	 * Initializes generator dependencies.
	 */
	public function __construct() {
		$this->ai_handler      = new Widget_Builder_AI_Handler();
		$this->version_manager = new Widget_Builder_AI_Version_Manager();
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
		$widget_id     = absint( $widget_id );
		$message       = (string) $message;
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

		// Use Gemini's full PHP class directly — do NOT rebuild from markup.
		$files = array(
			'widget.php' => $this->normalize_php( $spec['php'] ),
			'style.css'  => $this->normalize_css_unminified( $spec['css'] ),
			'script.js'  => $this->normalize_js_unminified( $spec['js'], $this->build_widget_name_from_title( $resolved_widget_config['title'] ) ),
		);
		$files = $this->filter_optional_files( $files );

		update_post_meta( $widget_id, self::META_FILES, wp_slash( $files ) );
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
		$normalized = $this->filter_optional_files( $normalized );
		update_post_meta( $widget_id, self::META_FILES, wp_slash( $normalized ) );
		$storage = $this->persist_widget_files( $widget_id, $normalized );
		$post    = get_post( $widget_id );

		$existing_widget_config = get_post_meta( $widget_id, self::META_WIDGET_CONFIG, true );
		$existing_widget_config = is_array( $existing_widget_config ) ? $existing_widget_config : array();
		$merged_widget_config = ! empty( $widget_config )
			? array_merge( $existing_widget_config, $widget_config )
			: $existing_widget_config;
			
		$resolved_widget_config = $this->normalize_widget_config(
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
	 * Deletes persisted widget files and storage metadata.
	 *
	 * @param int $widget_id Widget ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_widget_files( $widget_id ) {
		$widget_id = absint( $widget_id );

		if ( $widget_id <= 0 ) {
			return false;
		}

		$storage   = get_post_meta( $widget_id, self::META_FILE_STORAGE, true );
		$directory = '';

		// ✅ Prefer stored directory
		if ( is_array( $storage ) && ! empty( $storage['directory'] ) ) {
			$directory = wp_normalize_path( (string) $storage['directory'] );
		}

		// ⚠️ Fallback (only if missing)
		if ( empty( $directory ) ) {
			$uploads = wp_upload_dir();

			if ( ! empty( $uploads['error'] ) ) {
				return false;
			}

			$directory = trailingslashit( $uploads['basedir'] ) . 'widget-builder-ai/widgets/' . $this->get_widget_slug( $widget_id ) . '-' . $widget_id;
			$directory = wp_normalize_path( $directory );
		}

		$uploads = wp_upload_dir();
		$base    = wp_normalize_path( $uploads['basedir'] );

		if ( strpos( $directory, $base ) !== 0 ) {
			return false;
		}

		$deleted = $this->delete_directory_recursively( $directory );

		if ( $deleted ) {
			delete_post_meta( $widget_id, self::META_FILE_STORAGE );
			delete_post_meta( $widget_id, self::META_FILES );
		}

		return $deleted;
	}

	/**
	 * Normalizes generated PHP source.
	 *
	 * @param string $php Raw PHP source.
	 * @return string Normalized PHP source.
	 */
	private function normalize_php( $php ) {
		$php = trim( (string) $php );
		if ( '' === $php ) {
			return '';
		}

		if ( 0 !== strpos( $php, '<?php' ) ) {
			$php = "<?php\n" . $php;
		}

		// Check for single backslash namespace (post json_decode) OR double backslash.
		if ( false === strpos( $php, 'namespace WBAI\Widgets' ) &&
			false === strpos( $php, 'namespace WBAI\\Widgets' ) ) {
			$php = preg_replace( '/^<\?php\s*/i', "<?php\nnamespace WBAI\\Widgets;\n\n", $php, 1 );
		}

		return $php . "\n";
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
	 * Writes widget files into uploads storage and stores metadata.
	 *
	 * @param int   $widget_id Widget ID.
	 * @param array $files     Canonical file map.
	 * @return array Storage payload, or empty array on failure.
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

		$slug     = $this->get_widget_slug( $widget_id );
		$folder   = $slug . '-' . $widget_id;
		$base_dir = trailingslashit( $uploads['basedir'] ) . 'widget-builder-ai/widgets/' . $folder;
		$base_url = trailingslashit( $uploads['baseurl'] ) . 'widget-builder-ai/widgets/' . $folder;

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
	 * Deletes a directory recursively.
	 *
	 * @param string $directory Absolute directory path.
	 * @return bool True when deleted successfully, otherwise false.
	 */
	private function delete_directory_recursively( $directory ) {
		$directory = wp_normalize_path( trailingslashit( (string) $directory ) );
		if ( empty( $directory ) || ! file_exists( $directory ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$creds = request_filesystem_credentials( '', '', false, false, null );

		if ( WP_Filesystem( $creds ) ) {
			global $wp_filesystem;

			if ( $wp_filesystem && method_exists( $wp_filesystem, 'delete' ) ) {
				$result = $wp_filesystem->delete( $directory, true );

				if ( $result ) {
					return true;
				}
			}
		}

		// ✅ Fallback: native PHP delete (more reliable in many servers)
		$items = scandir( $directory );
		if ( ! is_array( $items ) ) {
			return false;
		}

		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}

			$path = $directory . $item;
			if ( is_dir( $path ) ) {
				$this->delete_directory_recursively( $path );
			} else {
				if ( file_exists( $path ) ) {
					if ( ! @unlink( $path ) ) {
						error_log( 'Failed to delete file: ' . $path );
					}
				}
			}
		}

		// Remove main directory
		if ( ! @rmdir( $directory ) ) {
			error_log( 'Failed to remove directory: ' . $directory );
			return false;
		}

		return true;
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
				'post_type'        => 'widget_builder_ai',
				'post_status'      => 'any',
				'title'            => $widget_title,
				'numberposts'      => 1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'suppress_filters' => true,
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
	 * Builds a normalized widget slug name from title.
	 *
	 * @param string $title Widget title.
	 * @return string Sanitized underscore slug.
	 */
	private function build_widget_name_from_title( $title ) {
		$slug = sanitize_title( (string) $title );
		$slug = str_replace( '-', '_', $slug );
		return '' !== $slug ? $slug : 'widget_builder_ai';
	}

	/**
	 * Formats CSS into readable unminified output.
	 *
	 * @param string $css Raw CSS source.
	 * @return string Normalized CSS source.
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
	 * Formats JavaScript into Elementor-ready output.
	 *
	 * Preserves existing IIFE + init-hook wrappers when already present,
	 * otherwise wraps handler code with the required Elementor hook bootstrap.
	 *
	 * @param string $js          Raw JavaScript source.
	 * @param string $widget_name Widget name used for Elementor hook namespace.
	 * @return string Normalized JavaScript source.
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

		$has_iife_wrapper = false !== strpos( $js, '(function' ) || false !== strpos( $js, '( function' );
		$has_init_hook    = false !== strpos( $js, 'elementor/frontend/init' );

		// Keep already well-structured IIFE + init hook code untouched.
		if ( $has_iife_wrapper && $has_init_hook ) {
			return trim( $js ) . "\n";
		}

		$handler_body = $js;

		// If AI returned a raw addAction wrapper, unwrap only the callback body.
		if ( preg_match( '/elementorFrontend\.hooks\.addAction\s*\(\s*["\'][^"\']+["\']\s*,\s*function\s*\(\s*\$scope\s*\)\s*\{([\s\S]*?)\}\s*\)\s*;?/m', $js, $matches ) ) {
			$handler_body = trim( (string) $matches[1] );
		}

		$hook_widget_name = 0 === strpos( $widget_name, 'wbai_' ) ? $widget_name : 'wbai_' . $widget_name;

		$wrapped_js = "(function ($, elementor) {\n" .
			"\t'use strict';\n\n" .
			"\tvar WidgetHandler = function (\$scope) {\n";

		foreach ( explode( "\n", $handler_body ) as $line ) {
			$wrapped_js .= "\t\t" . rtrim( $line ) . "\n";
		}

		$wrapped_js .= "\t};\n\n" .
			"\t$(window).on('elementor/frontend/init', function () {\n" .
			"\t\telementorFrontend.hooks.addAction(\n" .
			"\t\t\t'frontend/element_ready/{$hook_widget_name}.default',\n" .
			"\t\t\tWidgetHandler\n" .
			"\t\t);\n" .
			"\t});\n" .
			"})(jQuery, window.elementorFrontend);";

		return trim( $wrapped_js ) . "\n";
	}

	/**
	 * Checks whether a string ends with another string.
	 *
	 * @param string $haystack Full input string.
	 * @param string $needle   Ending string.
	 * @return bool True when haystack ends with needle.
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
	 * Removes optional assets when empty.
	 *
	 * @param array $files File payload.
	 * @return array Filtered files.
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
	 * Normalizes widget configuration with validated fallbacks.
	 *
	 * @param array  $widget_config     Raw widget configuration.
	 * @param string $fallback_title    Fallback title.
	 * @param string $fallback_icon     Fallback icon.
	 * @param string $fallback_category Fallback category.
	 * @return array Normalized configuration payload.
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
			'title'           => $title,
			'description'     => isset( $widget_config['description'] ) ? sanitize_textarea_field( (string) $widget_config['description'] ) : '',
			'icon'            => $icon,
			'category'        => $category,
			'selectedLibrary' => isset( $widget_config['selectedLibrary'] ) ? sanitize_text_field( (string) $widget_config['selectedLibrary'] ) : '',
			'libraries'       => $libraries,
		);
	}

	/**
	 * Gets a filesystem-safe slug for widget storage.
	 *
	 * @param int $widget_id Widget ID.
	 * @return string Widget slug.
	 */
	private function get_widget_slug( $widget_id ) {
		$title = get_the_title( $widget_id );
		$slug  = sanitize_title( $title );
		return '' !== $slug ? $slug : 'widget-builder-ai';
	}

	/**
	 * Checks whether provided content is non-empty after trimming.
	 *
	 * @param string $content Raw content.
	 * @return bool True when content has meaningful characters.
	 */
	private function has_meaningful_content( $content ) {
		return '' !== trim( (string) $content );
	}
}