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
		$this->api_key  = AI_GEMINI_API_KEY ;
		$this->endpoint =  AI_GEMINI_API_ENDPOINT ;
	}

	/**
	 * Checks if the adapter is properly configured.
	 *
	 * @return bool True if configured, false otherwise.
	 */
	public function is_configured() {
		return ! empty( $this->api_key ) && ! empty( $this->endpoint );
	}

	/**
	 * Generates a widget specification using Gemini.
	 *
	 * @param string $message The user message/prompt.
	 * @param array  $context The chat context.
	 * @param string $model   The Gemini model to use.
	 * @return array|WP_Error The generated spec or an error.
	 */
	public function generate_spec( $message, $context = array(), $model = 'gemini-3-flash' ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'gemini_not_configured', __( 'Gemini API key is not configured.', 'widget-builder-ai' ) );
		}

		$system_prompt = $this->build_system_prompt();
		$user_prompt   = $this->build_user_prompt( $message, $context );
		$url           = trailingslashit( $this->endpoint ) . rawurlencode( (string) $model ) . ':generateContent?key=' . rawurlencode( $this->api_key );

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
							'temperature' => 0.2,
							'responseMimeType' => 'application/json', // Force JSON output
						),
					),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'gemini_request_failed', ! empty( $body ) ? $body : __( 'Gemini request failed.', 'widget-builder-ai' ) );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'gemini_invalid_json', __( 'Gemini response is not valid JSON.', 'widget-builder-ai' ) );
		}

		$content = '';
		if ( isset( $data['candidates'][0]['content']['parts'] ) && is_array( $data['candidates'][0]['content']['parts'] ) ) {
			foreach ( $data['candidates'][0]['content']['parts'] as $part ) {
				if ( is_array( $part ) && isset( $part['text'] ) ) {
					$content .= (string) $part['text'];
				}
			}
		}

		$parsed = $this->extract_json( $content );
		if ( is_array( $parsed ) ) {
			return $parsed;
		}

		if ( isset( $data['spec'] ) && is_array( $data['spec'] ) ) {
			return $data['spec'];
		}

		return $data;
	}

	/**
	 * Builds the system prompt sent to Gemini.
	 *
	 * @return string Prompt text.
	 */
	private function build_system_prompt() {
		return 'You are an expert WordPress + Elementor developer. Return JSON only, no markdown. ' .
		'Use this response schema exactly: {"title":"","icon":"eicon-code","categories":["basic"],"markup":"","css":"","js":"","css_includes":[],"js_includes":[],"summary":""}. ' .
		'Task: generate the render markup. Do not return a full PHP class. Do not include content_template. ' .
		'Frontend Requirements: output clean, semantic, accessible HTML and escape dynamic output. ' .
		'Styling Requirements: provide CSS only when needed; otherwise return an empty css string. Keep selectors scoped with .wbai-{widget_name}. ' .
		'JavaScript Requirements: provide JS only when interaction is needed; otherwise return an empty js string. If JS is needed, use Elementor frontend init + widget ready hook pattern and scope selectors with .wbai-{widget_name}. ' .
		'Extra: follow WordPress and Elementor best practices and keep output minimal. ' .
		'Code Quality Rules: production-ready, clear organization, minimal useful comments, no unnecessary dependencies.';
	}

	/**
	 * Builds the user prompt payload for Gemini.
	 *
	 * @param string $message User request text.
	 * @param array  $context Conversation and widget context.
	 * @return string JSON payload.
	 */
	private function build_user_prompt( $message, $context ) {
		$message = trim( (string) $message );
		$purpose = preg_replace( '/^\s*purpose\s*:\s*/i', '', $message );

		$widget_config = isset( $context['widget_config'] ) && is_array( $context['widget_config'] )
			? $context['widget_config']
			: array();

		$title_hint = isset( $widget_config['title'] ) ? sanitize_text_field( (string) $widget_config['title'] ) : '';

		$final_purpose = '' !== trim( (string) $purpose ) ? $purpose : $message;

		if ( '' === trim( (string) $final_purpose ) ) {
			$final_purpose = 'Build a useful custom Elementor widget.';
		}

		$final_name = '' !== trim( (string) $title_hint ) ? $title_hint : 'Auto Generated Widget';

		$prompt_text = "Task:\n" .
			"Create the Elementor widget spec based on this user input:\n" .
			"- Widget Name: {$final_name}\n" .
			"- Purpose: {$final_purpose}\n\n" .
			"Output format (strict):\n" .
			
			"2. Markup only for render output (not a full class).\n" .
			"3. CSS code only if needed, otherwise empty css string.\n" .
			"4. JS code only if needed, otherwise empty js string.\n\n" .
			"Return in JSON schema fields:\n" .
			"- title: widget title\n" .
			
			"- markup: render HTML/PHP snippet only\n" .
			"- css: optional\n" .
			"- js: only if required\n" .
			"- summary: short implementation summary.";

		$payload = array(
			'widget_name'    => $final_name,
			'widget_purpose' => $final_purpose,
			'instructions'   => $prompt_text,
			'context'        => $context,
		);

		return wp_json_encode( $payload );
	}

	/**
	 * Extracts a JSON object from model output.
	 *
	 * @param string $content Model output content.
	 * @return array|null Decoded JSON array or null when extraction fails.
	 */
	private function extract_json( $content ) {
		$trimmed = trim( (string) $content );

		if ( '' === $trimmed ) {
			return null;
		}

		if ( '{' === substr( $trimmed, 0, 1 ) ) {
			$decoded = json_decode( $trimmed, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		if ( preg_match( '/```(?:json)?\s*(\{[\s\S]*\})\s*```/i', $trimmed, $matches ) ) {
			$decoded = json_decode( $matches[1], true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		$start = strpos( $trimmed, '{' );
		$end   = strrpos( $trimmed, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$decoded = json_decode( substr( $trimmed, $start, ( $end - $start + 1 ) ), true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return null;
	}
}



