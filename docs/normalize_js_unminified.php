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