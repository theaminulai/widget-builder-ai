<?php
/**
 * File system utilities for Widget Builder AI.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Widget_Builder_AI_Filesystem {

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

		$storage   = get_post_meta( $widget_id, Widget_Builder_AI_Generator::META_FILE_STORAGE, true );
		$directory = '';

		if ( is_array( $storage ) && ! empty( $storage['directory'] ) ) {
			$directory = wp_normalize_path( (string) $storage['directory'] );
		}

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
			delete_post_meta( $widget_id, Widget_Builder_AI_Generator::META_FILE_STORAGE );
			delete_post_meta( $widget_id, Widget_Builder_AI_Generator::META_FILES );
		}

		return $deleted;
	}

	/**
	 * Deletes a directory recursively.
	 *
	 * @param string $directory Absolute directory path.
	 * @return bool True when deleted successfully, otherwise false.
	 */
	public function delete_directory_recursively( $directory ) {
		$directory = wp_normalize_path( trailingslashit( (string) $directory ) );

		if ( empty( $directory ) || ! file_exists( $directory ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! WP_Filesystem() ) {
			return false;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return false;
		}

		if ( method_exists( $wp_filesystem, 'delete' ) ) {
			return $wp_filesystem->delete( $directory, true );
		}

		return false;
	}

	/**
	 * Gets a filesystem-safe slug for widget storage.
	 *
	 * @param int $widget_id Widget ID.
	 * @return string Widget slug.
	 */
	public function get_widget_slug( $widget_id ) {
		$title = get_the_title( $widget_id );
		$slug  = sanitize_title( $title );
		return '' !== $slug ? $slug : 'widget-builder-ai';
	}

	/**
	 * Writes widget files into uploads storage and stores metadata.
	 *
	 * @param int   $widget_id Widget ID.
	 * @param array $files     Canonical file map.
	 * @return array Storage payload, or empty array on failure.
	 */
	public function persist_widget_files( $widget_id, $files ) {
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

		update_post_meta( $widget_id, Widget_Builder_AI_Generator::META_FILE_STORAGE, $storage );

		return $storage;
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