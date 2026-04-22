<?php
/**
 * Code normalizer utilities for Widget Builder AI.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Widget_Builder_AI_Code_Validator {

	/**
	 * Normalizes generated PHP source.
	 *
	 * @param string $php Raw PHP source.
	 * @return string Normalized PHP source.
	 */
	public function normalize_php( $php ) {
		$php = trim( (string) $php );
		if ( '' === $php ) {
			return '';
		}

		if ( 0 !== strpos( $php, '<?php' ) ) {
			$php = "<?php\n" . $php;
		}

		if ( false === strpos( $php, 'namespace WBAI\Widgets' ) &&
			false === strpos( $php, 'namespace WBAI\\Widgets' ) ) {
			$php = preg_replace( '/^<\?php\s*/i', "<?php\nnamespace WBAI\\Widgets;\n\n", $php, 1 );
		}

		return $php . "\n";
	}

	/**
	 * Formats CSS into readable unminified output.
	 *
	 * @param string $css Raw CSS source.
	 * @return string Normalized CSS source.
	 */
	public function normalize_css_unminified( $css ) {
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
	public function normalize_js_unminified( $js, $widget_name = 'widget_builder_ai' ) {
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

		// Already well-structured — return as-is.
		if ( $has_iife_wrapper && $has_init_hook ) {
			return trim( $js ) . "\n";
		}

		$hook_widget_name = 0 === strpos( $widget_name, 'wbai_' ) ? $widget_name : 'wbai_' . $widget_name;

		// If AI returned a raw addAction call, wrap it in the IIFE shell.
		// Do NOT try to unwrap the callback body — nested braces break regex extraction.
		if ( false !== strpos( $js, 'elementorFrontend.hooks.addAction' ) ) {
			$wrapped_js = "(function ($, elementor) {\n" .
				"\t'use strict';\n\n" .
				"\t$(window).on('elementor/frontend/init', function () {\n" .
				"\t\t" . trim( $js ) . "\n" .
				"\t});\n" .
				"})(jQuery, window.elementorFrontend);";

			return trim( $wrapped_js ) . "\n";
		}

		// Raw handler body — wrap fully.
		$handler_body = $js;
		$wrapped_js   = "(function ($, elementor) {\n" .
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
	 * Normalizes widget configuration with validated fallbacks.
	 *
	 * @param array  $widget_config     Raw widget configuration.
	 * @param string $fallback_title    Fallback title.
	 * @param string $fallback_icon     Fallback icon.
	 * @param string $fallback_category Fallback category.
	 * @return array Normalized configuration payload.
	 */
	public function normalize_widget_config( $widget_config, $fallback_title = '', $fallback_icon = 'eicon-code', $fallback_category = 'basic' ) {
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
	 * Builds a normalized widget slug name from title.
	 *
	 * @param string $title Widget title.
	 * @return string Sanitized underscore slug.
	 */
	public function build_widget_name_from_title( $title ) {
		$slug = sanitize_title( (string) $title );
		$slug = str_replace( '-', '_', $slug );
		return '' !== $slug ? $slug : 'widget_builder_ai';
	}

	/**
	 * Removes optional assets when empty.
	 *
	 * @param array $files File payload.
	 * @return array Filtered files.
	 */
	public function filter_optional_files( $files ) {
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
	 * Checks whether provided content is non-empty after trimming.
	 *
	 * @param string $content Raw content.
	 * @return bool True when content has meaningful characters.
	 */
	public function has_meaningful_content( $content ) {
		return '' !== trim( (string) $content );
	}

	/**
	 * Checks whether a string ends with another string.
	 *
	 * @param string $haystack Full input string.
	 * @param string $needle   Ending string.
	 * @return bool True when haystack ends with needle.
	 */
	public function ends_with( $haystack, $needle ) {
		$haystack = (string) $haystack;
		$needle   = (string) $needle;
		$len      = strlen( $needle );
		if ( 0 === $len ) {
			return true;
		}
		return substr( $haystack, -$len ) === $needle;
	}
}