<?php
/**
 * Gemini adapter for Widget Builder AI.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adapter for communicating with the Google Gemini API.
 *
 * @since 1.0.0
 */
class Widget_Builder_AI_Gemini_Adapter {

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * API endpoint.
	 *
	 * @var string
	 */
	private $endpoint;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_key  = defined( 'AI_GEMINI_API_KEY' ) ? AI_GEMINI_API_KEY : '';
		$this->endpoint = defined( 'AI_GEMINI_API_ENDPOINT' ) ? AI_GEMINI_API_ENDPOINT : '';
	}

	/**
	 * Check if API is configured.
	 *
	 * @return bool True if configured, false otherwise.
	 */
	public function is_configured() {
		return ! empty( $this->api_key ) && ! empty( $this->endpoint );
	}

	/**
	 * Generate widget spec from Gemini.
	 *
	 * @param string $message The user message/prompt.
	 * @param array  $context The chat context.
	 * @param string $model   The Gemini model to use.
	 * @return array|WP_Error The generated spec or an error.
	 */
	public function generate_spec( $message, $context = array(), $model = 'gemini-3-flash' ) {

		if ( ! $this->is_configured() ) {
			return new WP_Error(
				'gemini_not_configured',
				__( 'Gemini API key is not configured.', 'widget-builder-ai' )
			);
		}

		$system_prompt = $this->build_system_prompt();
		$user_prompt   = $this->build_user_prompt( $message, $context );

		$url = trailingslashit( $this->endpoint ) . rawurlencode( (string) $model ) . ':generateContent?key=' . rawurlencode( $this->api_key );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 120,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'contents' => array(
							array(
								'role'  => 'user',
								'parts' => array(
									array(
										'text' => $system_prompt . "\n\n" . $user_prompt,
									),
								),
							),
						),
						'generationConfig' => array(
							'temperature'      => 0.2,
							'responseMimeType' => 'application/json',
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		error_log( 'Gemini raw response: ' . print_r( $data, true ) );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'gemini_request_failed',
				! empty( $body ) ? $body : __( 'Gemini request failed.', 'widget-builder-ai' )
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'gemini_invalid_json',
				__( 'Gemini response is not valid JSON.', 'widget-builder-ai' )
			);
		}

		// Extract text content
		$content = '';
		if ( isset( $data['candidates'][0]['content']['parts'] ) ) {
			foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) ) {
					$content .= (string) $part['text'];
				}
			}
		}

		$parsed = $this->extract_json( $content );

		// Strict validation
		if ( is_array( $parsed ) && isset( $parsed['markup'] ) ) {
			return $this->sanitize_ai_response( $parsed );
		}

		// Fallback (rare cases)
		if ( isset( $data['spec'] ) && is_array( $data['spec'] ) ) {
			return $this->sanitize_ai_response( $data['spec'] );
		}

		return new WP_Error(
			'gemini_invalid_structure',
			__( 'Invalid AI response structure.', 'widget-builder-ai' )
		);
	}

	/**
	 * System prompt (strict AI behavior).
	 *
	 * @return string Prompt text.
	 */
	private function build_system_prompt() {
		return 'You are a senior WordPress + Elementor widget developer.

		Return ONLY valid JSON. No explanation. No markdown.

		STRICT JSON SCHEMA:
		{
			"title":"",
			"icon":"eicon-code",
			"categories":["basic"],
			"markup":"",
			"css":"",
			"js":"",
			"summary":""
		}

		CORE TASK:
		- Convert the user purpose into a complete Elementor widget render output.

		DECISION RULES:
		- Infer layout and UI automatically.
		- If unclear, choose simplest useful version.
		- Do NOT ask questions.
		ELEMENTOR CONTROLS (VERY IMPORTANT):
		- Generate ALL necessary controls based on the widget purpose.
		- Controls must be usable in Elementor register_controls().
		- Each control should include:
			- name
			- label
			- type (text, textarea, number, switcher, repeater, select, media, url, etc.)
			- default value (if applicable)

		- Use REPEATER when multiple items are needed (e.g. testimonials, list, slider).
		- Keep controls minimal but complete.
		- Do NOT over-engineer.

		MARKUP:
		- Do NOT include content_template or a full PHP class. Only the render() output.
		- Only render() compatible HTML/PHP.
		- Escape dynamic output (esc_html, esc_attr, esc_url).

		CSS:
		- Only if needed
		- MUST scope: .wbai-{widget_name}

		JS:
		- Only if needed
		- MUST use:
		elementorFrontend.hooks.addAction("frontend/element_ready/{widget_name}.default", function($scope){});

		QUALITY:
		- Production-ready
		- No dependencies';
	}

	/**
	 * User prompt builder.
	 *
	 * @param string $message User request text.
	 * @param array  $context Conversation and widget context.
	 * @return string Prompt payload.
	 */
	private function build_user_prompt( $message, $context ) {

		$message = sanitize_textarea_field( (string) $message );

		if ( empty( $message ) ) {
			$message = 'Create a useful Elementor widget.';
		}

		$widget_config = isset( $context['widget_config'] ) && is_array( $context['widget_config'] )
			? $context['widget_config']
			: array();

		$title = ! empty( $widget_config['title'] )
			? sanitize_text_field( $widget_config['title'] )
			: 'auto-widget';
		$icon = ! empty( $widget_config['icon'] )
			? sanitize_text_field( $widget_config['icon'] )
			: 'eicon-code';
		$categories = ! empty( $widget_config['category'] ) && is_string( $widget_config['category'] )
			? array( sanitize_text_field( $widget_config['category'] ) )
			: array( 'basic' );

		return "Widget Name: {$title}
			Icon: {$icon}
			Categories: " . implode( ', ', $categories ) . "
			Purpose:
			{$message}

			Instructions:
			- Generate full widget render output";
	}

	/**
	 * Extract JSON safely from AI response.
	 *
	 * @param string $content Model output content.
	 * @return array|null Decoded JSON array or null when extraction fails.
	 */
	private function extract_json( $content ) {

		$trimmed = trim( (string) $content );

		if ( '' === $trimmed ) {
			return null;
		}

		// Direct JSON
		if ( str_starts_with( $trimmed, '{' ) ) {
			$decoded = json_decode( $trimmed, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		// Code block JSON
		if ( preg_match( '/```(?:json)?\s*(\{[\s\S]*\})\s*```/i', $trimmed, $matches ) ) {
			$decoded = json_decode( $matches[1], true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		// Partial extraction
		$start = strpos( $trimmed, '{' );
		$end   = strrpos( $trimmed, '}' );

		if ( false !== $start && false !== $end && $end > $start ) {
			$json = substr( $trimmed, $start, $end - $start + 1 );
			$decoded = json_decode( $json, true );

			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return null;
	}

	/**
	 * Sanitize AI response (WordPress safe).
	 *
	 * @param array $data Raw AI response.
	 * @return array Sanitized AI response.
	 */
	private function sanitize_ai_response( $data ) {

		$defaults = array(
			'title'        => '',
			'icon'         => 'eicon-code',
			'categories'   => array( 'basic' ),
			'markup'       => '',
			'css'          => '',
			'js'           => '',
			'summary'      => '',
		);

		$data = wp_parse_args( $data, $defaults );

		$data['title']   = sanitize_text_field( $data['title'] );
		$data['icon']    = sanitize_text_field( $data['icon'] );
		$data['summary'] = sanitize_textarea_field( $data['summary'] );

		$data['categories'] = is_array( $data['categories'] )
			? array_map( 'sanitize_text_field', $data['categories'] )
			: array( 'basic' );

		// Keep code raw
		$data['markup'] = (string) $data['markup'];
		$data['css']    = (string) $data['css'];
		$data['js']     = (string) $data['js'];

		return $data;
	}
}