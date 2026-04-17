<?php
/**
 * AI orchestration service.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the orchestration of AI providers to generate widget specifications.
 */
class Widget_Builder_AI_Handler {

	/**
	 * Claude adapter instance.
	 *
	 * @var Widget_Builder_AI_Claude_Adapter
	 */
	private $claude_adapter;

	/**
	 * OpenAI adapter instance.
	 *
	 * @var Widget_Builder_AI_OpenAI_Adapter
	 */
	private $openai_adapter;

	/**
	 * Gemini adapter instance.
	 *
	 * @var Widget_Builder_AI_Gemini_Adapter
	 */
	private $gemini_adapter;

	/**
	 * DeepSeek adapter instance.
	 *
	 * @var Widget_Builder_AI_DeepSeek_Adapter
	 */
	private $deepseek_adapter;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->claude_adapter   = new Widget_Builder_AI_Claude_Adapter();
		$this->openai_adapter   = new Widget_Builder_AI_OpenAI_Adapter();
		$this->gemini_adapter   = new Widget_Builder_AI_Gemini_Adapter();
		$this->deepseek_adapter = new Widget_Builder_AI_DeepSeek_Adapter();
	}

	/**
	 * Generates a widget specification from a message and configuration.
	 *
	 * @param string $message       The user message/prompt.
	 * @param array  $context       The chat context.
	 * @param string $model         The requested AI model.
	 * @param array  $widget_config User-provided widget configuration.
	 * @return array|WP_Error The normalized widget spec or an error on failure.
	 */
	public function generate_widget_spec( $message, $context = array(), $model = 'gpt-4.1-mini', $widget_config = array() ) {
		$model             = (string) $model;
		$selected_provider = $this->detect_provider_from_model( $model );

		$providers = array( $selected_provider, 'openai', 'claude', 'gemini', 'deepseek' );
		$providers = array_values( array_unique( array_filter( $providers ) ) );

		foreach ( $providers as $provider ) {
			$result = $this->generate_from_provider( $provider, $message, $context, $model );
			if ( ! is_wp_error( $result ) ) {
				return $this->normalize_spec( $result, $message, $widget_config );
			}
		}

		return new WP_Error( 'ai_generation_failed', __( 'All configured AI providers failed to generate a response. Please try again.', 'widget-builder-ai' ) );
	}

	/**
	 * Detects the AI provider based on the model name.
	 *
	 * @param string $model The model name.
	 * @return string The provider name (claude, gemini, deepseek, or openai).
	 */
	private function detect_provider_from_model( $model ) {
		$model = strtolower( (string) $model );

		if ( 0 === strpos( $model, 'claude' ) ) {
			return 'claude';
		}

		if ( 0 === strpos( $model, 'gemini' ) ) {
			return 'gemini';
		}

		if ( 0 === strpos( $model, 'deepseek' ) ) {
			return 'deepseek';
		}

		if ( '' !== $model ) {
			return 'openai';
		}

		return '';
	}

	/**
	 * Generates a spec from a specific provider.
	 *
	 * @param string $provider The provider name.
	 * @param string $message  The user message.
	 * @param array  $context  The chat context.
	 * @param string $model    The requested model.
	 * @return array|WP_Error The generated spec or an error.
	 */
	private function generate_from_provider( $provider, $message, $context, $model ) {
		switch ( $provider ) {
			case 'claude':
				if ( ! $this->claude_adapter->is_configured() ) {
					return new WP_Error( 'claude_not_configured', __( 'Claude provider not configured.', 'widget-builder-ai' ) );
				}

				$claude_model = 0 === strpos( strtolower( (string) $model ), 'claude' ) ? $model : 'claude-3-5-sonnet-latest';
				return $this->claude_adapter->generate_spec( $message, $context, $claude_model );

			case 'gemini':
				if ( ! $this->gemini_adapter->is_configured() ) {
					return new WP_Error( 'gemini_not_configured', __( 'Gemini provider not configured.', 'widget-builder-ai' ) );
				}

				$gemini_model = 0 === strpos( strtolower( (string) $model ), 'gemini' ) ? $model : 'gemini-3-flash';
				return $this->gemini_adapter->generate_spec( $message, $context, $gemini_model );

			case 'deepseek':
				if ( ! $this->deepseek_adapter->is_configured() ) {
					return new WP_Error( 'deepseek_not_configured', __( 'DeepSeek provider not configured.', 'widget-builder-ai' ) );
				}

				$deepseek_model = 0 === strpos( strtolower( (string) $model ), 'deepseek' ) ? $model : 'deepseek-chat';
				return $this->deepseek_adapter->generate_spec( $message, $context, $deepseek_model );

			case 'openai':
			default:
				if ( ! $this->openai_adapter->has_api_key() ) {
					return new WP_Error( 'openai_not_configured', __( 'OpenAI provider not configured.', 'widget-builder-ai' ) );
				}

				$openai_model = $model;
				if ( 0 === strpos( strtolower( (string) $openai_model ), 'claude' ) || 0 === strpos( strtolower( (string) $openai_model ), 'gemini' ) || 0 === strpos( strtolower( (string) $openai_model ), 'deepseek' ) || '' === $openai_model ) {
					$openai_model = 'gpt-4.1-mini';
				}

				return $this->openai_adapter->generate_spec( $message, $context, $openai_model );
		}
	}

	/**
	 * Normalizes the AI response into a consistent format.
	 *
	 * @param array  $spec          The raw AI response.
	 * @param string $message       The user message.
	 * @param array  $widget_config The user-provided config.
	 * @return array The normalized spec.
	 */
	private function normalize_spec( $spec, $message, $widget_config = array() ) {
		$widget_config = is_array( $widget_config ) ? $widget_config : array();

		$config_title = isset( $widget_config['title'] ) ? sanitize_text_field( $widget_config['title'] ) : '';
		$title        = $config_title;

		if ( '' === $title && isset( $spec['title'] ) ) {
			$title = sanitize_text_field( $spec['title'] );
		}
		if ( '' === $title ) {
			$title = $this->title_from_message( $message );
		}
		if ( '' === $title ) {
			$title = 'AI Widget ' . gmdate( 'Y-m-d H:i:s' );
		}

		$config_icon = isset( $widget_config['icon'] ) ? sanitize_text_field( $widget_config['icon'] ) : '';
		$icon        = $config_icon;

		if ( '' === $icon && isset( $spec['icon'] ) ) {
			$icon = sanitize_text_field( $spec['icon'] );
		}
		if ( '' === $icon ) {
			$icon = 'eicon-code';
		}

		$config_categories = array();
		if ( ! empty( $widget_config['category'] ) && is_string( $widget_config['category'] ) ) {
			$config_categories[] = sanitize_text_field( $widget_config['category'] );
		}

		$categories = $config_categories;

		if ( empty( $categories ) && isset( $spec['categories'] ) && is_array( $spec['categories'] ) ) {
			$categories = array_values( array_filter( array_map( 'sanitize_text_field', $spec['categories'] ) ) );
		}

		if ( empty( $categories ) ) {
			$categories = array( 'basic' );
		}

		$library_includes = $this->extract_library_includes( $widget_config );
		$css_includes     = $library_includes['css_includes'];
		$js_includes      = $library_includes['js_includes'];
		$summary          = isset( $spec['summary'] ) ? sanitize_textarea_field( $spec['summary'] ) : 'Generated widget update.';

		return array(
			'title'        => $title,
			'icon'         => $icon,
			'categories'   => $categories,
			'markup'       => isset( $spec['markup'] ) ? (string) $spec['markup'] : '',
			'css'          => isset( $spec['css'] ) ? (string) $spec['css'] : '',
			'js'           => isset( $spec['js'] ) ? (string) $spec['js'] : '',
			'css_includes' => $css_includes,
			'js_includes'  => $js_includes,
			'summary'      => $summary,
		);
	}

	/**
	 * Extracts library includes from user configuration.
	 *
	 * @param array $widget_config The user config.
	 * @return array Array containing css_includes and js_includes.
	 */
	private function extract_library_includes( $widget_config ) {
		$result = array(
			'css_includes' => array(),
			'js_includes'  => array(),
		);

		if ( empty( $widget_config['libraries'] ) || ! is_array( $widget_config['libraries'] ) ) {
			return $result;
		}

		foreach ( $widget_config['libraries'] as $library ) {
			if ( ! is_array( $library ) || empty( $library['url'] ) || empty( $library['type'] ) ) {
				continue;
			}

			$url  = esc_url_raw( (string) $library['url'] );
			$type = sanitize_key( (string) $library['type'] );

			if ( '' === $url || ! in_array( $type, array( 'css', 'js' ), true ) ) {
				continue;
			}

			if ( 'css' === $type ) {
				$result['css_includes'][] = $url;
			}

			if ( 'js' === $type ) {
				$result['js_includes'][] = $url;
			}
		}

		$result['css_includes'] = array_values( array_unique( $result['css_includes'] ) );
		$result['js_includes']  = array_values( array_unique( $result['js_includes'] ) );

		return $result;
	}

	/**
	 * Generates a title from the user's message.
	 *
	 * @param string $message The user message.
	 * @return string The generated title.
	 */
	private function title_from_message( $message ) {
		$message = sanitize_text_field( (string) $message );
		if ( '' === $message ) {
			return '';
		}

		$short = mb_substr( $message, 0, 50 );
		return 'AI ' . $short;
	}
}
