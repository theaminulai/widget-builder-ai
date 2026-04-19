<?php
/**
 * Widget version storage manager.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the version history and storage of generated widget specifications.
 *
 * @since 1.0.0
 */
class Widget_Builder_AI_Version_Manager {

	/**
	 * Meta key for version history.
	 */
	const META_VERSIONS        = 'widget_builder_ai_versions';

	/**
	 * Meta key for the current version number.
	 */
	const META_CURRENT_VERSION = 'widget_builder_ai_current_version';

	/**
	 * Maximum number of versions to retain.
	 */
	const MAX_VERSIONS         = 10;

	/**
	 * Creates a new version for a widget.
	 *
	 * @param int    $widget_id The widget ID.
	 * @param array  $files     The files configuration.
	 * @param string $ai_model  The AI model used.
	 * @param string $summary   A summary of the changes.
	 * @return int The new version number.
	 */
	public function create_version( $widget_id, $files, $ai_model, $summary = '' ) {
		$widget_id = absint( $widget_id );
		$versions  = $this->get_all_versions( $widget_id );
		$next      = $this->next_version_number( $versions );

		$versions[ $next ] = array(
			'timestamp'       => time(),
			'ai_model'        => sanitize_text_field( (string) $ai_model ),
			'files'           => is_array( $files ) ? $files : array(),
			'file_hash'       => $this->hash_files( $files ),
			'changes_summary' => sanitize_textarea_field( (string) $summary ),
		);

		if ( count( $versions ) > self::MAX_VERSIONS ) {
			$keys = array_keys( $versions );
			sort( $keys );
			$remove_keys = array_slice( $keys, 0, count( $versions ) - self::MAX_VERSIONS );
			foreach ( $remove_keys as $remove_key ) {
				unset( $versions[ $remove_key ] );
			}
		}

		update_post_meta( $widget_id, self::META_VERSIONS, wp_slash( $versions ) );
		update_post_meta( $widget_id, self::META_CURRENT_VERSION, $next );

		return $next;
	}

	/**
	 * Retrieves all versions for a widget.
	 *
	 * @param int $widget_id The widget ID.
	 * @return array The list of versions.
	 */
	public function get_all_versions( $widget_id ) {
		$versions = get_post_meta( absint( $widget_id ), self::META_VERSIONS, true );
		return is_array( $versions ) ? $versions : array();
	}

	/**
	 * Retrieves a specific version for a widget.
	 *
	 * @param int $widget_id       The widget ID.
	 * @param int $version_number The version number.
	 * @return array|null The version data or null if not found.
	 */
	public function get_version( $widget_id, $version_number ) {
		$versions = $this->get_all_versions( $widget_id );
		$key      = (int) $version_number;
		return isset( $versions[ $key ] ) ? $versions[ $key ] : null;
	}

	/**
	 * Retrieves the current version number for a widget.
	 *
	 * @param int $widget_id The widget ID.
	 * @return int The current version number.
	 */
	public function get_current_version_number( $widget_id ) {
		$current = get_post_meta( absint( $widget_id ), self::META_CURRENT_VERSION, true );
		return $current ? (int) $current : 0;
	}

	/**
	 * Retrieves a formatted list of versions for a widget.
	 *
	 * @param int $widget_id The widget ID.
	 * @return array The formatted versions list.
	 */
	public function get_versions_list( $widget_id ) {
		$versions = $this->get_all_versions( $widget_id );
		$current  = $this->get_current_version_number( $widget_id );
		$list     = array();

		foreach ( $versions as $num => $version ) {
			$list[] = array(
				'version'    => (int) $num,
				'timestamp'  => isset( $version['timestamp'] ) ? (int) $version['timestamp'] : 0,
				'date'       => isset( $version['timestamp'] ) ? wp_date( 'M j, Y H:i', (int) $version['timestamp'] ) : '',
				'model'      => isset( $version['ai_model'] ) ? $version['ai_model'] : '',
				'summary'    => isset( $version['changes_summary'] ) ? $version['changes_summary'] : '',
				'is_current' => (int) $num === $current,
			);
		}

		usort(
			$list,
			function ( $a, $b ) {
				return $b['version'] <=> $a['version'];
			}
		);

		return $list;
	}

	/**
	 * Calculates the next version number.
	 *
	 * @param array $versions Existing versions.
	 * @return int The next version number.
	 */
	private function next_version_number( $versions ) {
		if ( empty( $versions ) ) {
			return 1;
		}

		$keys = array_map( 'intval', array_keys( $versions ) );
		return max( $keys ) + 1;
	}

	/**
	 * Generates a hash for the file set.
	 *
	 * @param array $files The file content array.
	 * @return string The MD5 hash.
	 */
	private function hash_files( $files ) {
		if ( ! is_array( $files ) ) {
			return '';
		}

		ksort( $files );
		return md5( wp_json_encode( $files ) );
	}
}
