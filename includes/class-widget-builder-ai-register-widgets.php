<?php
/**
 * Registers generated Widget Builder AI widgets with Elementor.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the registration of generated widgets, their scripts and styles with Elementor.
 *
 * @since 1.0.0
 */
class Widget_Builder_AI_Register_Widgets {

	/**
	 * Meta key for file storage information.
	 */
	const META_FILE_STORAGE  = 'widget_builder_ai_file_storage';

	/**
	 * Meta key for widget configuration.
	 */
	const META_WIDGET_CONFIG = 'widget_builder_ai_widget_config';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_frontend_styles' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_frontend_scripts' ) );
	}

	/**
	 * Register all generated widgets stored under uploads/widget-builder-ai/widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ) {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		$widget_files = $this->get_widget_files();
		if ( empty( $widget_files ) ) {
			return;
		}

		foreach ( $widget_files as $widget_file ) {
			$class_name = $this->load_widget_class_from_file( $widget_file );
			if ( ! $class_name ) {
				continue;
			}

			if ( ! class_exists( $class_name ) || ! is_subclass_of( $class_name, '\\Elementor\\Widget_Base' ) ) {
				continue;
			}

			try {
				$widgets_manager->register( new $class_name() );
			} catch ( \Throwable $e ) {
				// Ignore invalid generated widget files to keep Elementor editor stable.
				continue;
			}
		}
	}

	/**
	 * Discover generated widget PHP files.
	 *
	 * @return array<int, string>
	 */
	private function get_widget_files() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return array();
		}

		$base_dir = trailingslashit( $uploads['basedir'] ) . 'widget-builder-ai/widgets';
		if ( ! is_dir( $base_dir ) ) {
			return array();
		}

		$pattern = trailingslashit( $base_dir ) . '*/*.widget.php';
		$files   = glob( $pattern );
		if ( ! is_array( $files ) ) {
			return array();
		}

		natsort( $files );
		return array_values( $files );
	}

	/**
	 * Load a generated class from file and return the widget class name.
	 *
	 * @param string $widget_file Widget file path.
	 *
	 * @return string
	 */
	private function load_widget_class_from_file( $widget_file ) {
		if ( ! is_readable( $widget_file ) ) {
			return '';
		}

		$declared_before = get_declared_classes();
		require_once $widget_file;
		$declared_after = get_declared_classes();
		$new_classes    = array_diff( $declared_after, $declared_before );

		foreach ( $new_classes as $class_name ) {
			if ( is_subclass_of( $class_name, '\\Elementor\\Widget_Base' ) ) {
				return $class_name;
			}
		}

		return '';
	}

	/**
	 * Register all generated widget styles and configured external CSS libraries.
	 *
	 * @return void
	 */
	public function register_frontend_styles() {
		$widgets = $this->collect_generated_widgets();

		foreach ( $widgets as $widget ) {
			if ( ! empty( $widget['storage']['files']['style.css'] ) ) {
				$url = esc_url_raw( (string) $widget['storage']['files']['style.css'] );
				if ( '' !== $url ) {
					wp_enqueue_style( 'wbai-widget-style-' . absint( $widget['id'] ), $url, array(), null );
				}
			}

			foreach ( $this->get_libraries_by_type( $widget['config'], 'css' ) as $url ) {
				$handle = $this->build_library_handle( $widget['id'], $url, 'css' );
				wp_enqueue_style( $handle, $url, array(), null );
			}
		}
	}

	/**
	 * Register all generated widget scripts and configured external JS libraries.
	 *
	 * @return void
	 */
	public function register_frontend_scripts() {
		$widgets = $this->collect_generated_widgets();

		foreach ( $widgets as $widget ) {
			if ( ! empty( $widget['storage']['files']['script.js'] ) ) {
				$url = esc_url_raw( (string) $widget['storage']['files']['script.js'] );
				if ( '' !== $url ) {
					wp_enqueue_script( 'wbai-widget-script-' . absint( $widget['id'] ), $url, array( 'jquery' ), null, true );
				}
			}

			foreach ( $this->get_libraries_by_type( $widget['config'], 'js' ) as $url ) {
				$handle = $this->build_library_handle( $widget['id'], $url, 'js' );
				wp_enqueue_script( $handle, $url, array(), null, true );
			}
		}
	}

	/**
	 * Collect storage and config metadata for all generated widgets.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_generated_widgets() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return array();
		}

		$base_dir = trailingslashit( $uploads['basedir'] ) . 'widget-builder-ai/widgets';
		if ( ! is_dir( $base_dir ) ) {
			return array();
		}

		$pattern = trailingslashit( $base_dir ) . '*/*.widget.php';
		$files   = glob( $pattern );
		if ( ! is_array( $files ) ) {
			return array();
		}

		$widgets = array();

		foreach ( $files as $file ) {
			if ( ! preg_match( '/-(\d+)\.widget\.php$/', (string) $file, $matches ) ) {
				continue;
			}

			$widget_id = absint( $matches[1] );
			if ( $widget_id <= 0 || isset( $widgets[ $widget_id ] ) ) {
				continue;
			}

			$storage = get_post_meta( $widget_id, self::META_FILE_STORAGE, true );
			$config  = get_post_meta( $widget_id, self::META_WIDGET_CONFIG, true );

			$widgets[ $widget_id ] = array(
				'id'      => $widget_id,
				'storage' => is_array( $storage ) ? $storage : array(),
				'config'  => is_array( $config ) ? $config : array(),
			);
		}

		return array_values( $widgets );
	}

	/**
	 * Get configured external libraries filtered by type.
	 *
	 * @param array  $config Widget config meta.
	 * @param string $type   css or js.
	 *
	 * @return array<int, string>
	 */
	private function get_libraries_by_type( $config, $type ) {
		$type      = sanitize_key( (string) $type );
		$libraries = array();

		if ( ! is_array( $config ) || empty( $config['libraries'] ) || ! is_array( $config['libraries'] ) ) {
			return $libraries;
		}

		foreach ( $config['libraries'] as $library ) {
			if ( ! is_array( $library ) ) {
				continue;
			}

			$url        = esc_url_raw( isset( $library['url'] ) ? (string) $library['url'] : '' );
			$library_type = sanitize_key( isset( $library['type'] ) ? (string) $library['type'] : '' );

			if ( '' === $url || $library_type !== $type ) {
				continue;
			}

			$libraries[] = $url;
		}

		return array_values( array_unique( $libraries ) );
	}

	/**
	 * Build a stable handle for external libraries.
	 *
	 * @param int    $widget_id Widget id.
	 * @param string $url       Library URL.
	 * @param string $type      css or js.
	 *
	 * @return string
	 */
	private function build_library_handle( $widget_id, $url, $type ) {
		$hash = substr( md5( absint( $widget_id ) . '|' . (string) $url . '|' . (string) $type ), 0, 12 );

		return 'wbai-library-' . sanitize_key( (string) $type ) . '-' . $hash;
	}
}
