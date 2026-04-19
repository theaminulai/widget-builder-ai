<?php
/**
 * Claude adapter for Widget Builder AI.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adapter for communicating with the Anthropic Claude API.
 *
 * @since 1.0.0
 */
class Widget_Builder_AI_Claude_Adapter {

	/**
	 * API endpoint.
	 *
	 * @var string
	 */
	private $endpoint;

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Anthropic version header value.
	 *
	 * @var string
	 */
	private $api_version;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->endpoint = AI_CLAUDE_API_ENDPOINT;
		$this->api_key = AI_CLAUDE_API_KEY;
		$this->api_version = '2023-06-01';
	}

	/**
	 * Checks if the adapter is properly configured.
	 *
	 * @return bool True if configured, false otherwise.
	 */
	public function is_configured() {
		return ! empty( $this->endpoint ) && ! empty( $this->api_key );
	}

	/**
	 * Generates a widget specification using Claude.
	 *
	 * @param string $message The user message/prompt.
	 * @param array  $context The chat context.
	 * @param string $model   The Claude model to use.
	 * @return array|WP_Error The generated spec or an error.
	 */
	public function generate_spec( $message, $context = array(), $model = 'claude-3-5-sonnet-latest' ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'claude_not_configured', __( 'Claude API key is not configured.', 'widget-builder-ai' ) );
		}

		$system_prompt = $this->build_system_prompt();
		$user_prompt   = $this->build_user_prompt( $message, $context );

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'timeout' => 45,
				'headers' => array(
					'Content-Type'       => 'application/json',
					'x-api-key'          => $this->api_key,
					'anthropic-version'  => $this->api_version,
				),
				'body'    => wp_json_encode(
					array(
						'model'       => (string) $model,
						'max_tokens'  => 2000,
						'temperature' => 0.2,
						'system'      => $system_prompt,
						'messages'    => array(
							array(
								'role'    => 'user',
								'content' => $user_prompt,
							),
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
			return new WP_Error( 'claude_request_failed', ! empty( $body ) ? $body : __( 'Claude request failed.', 'widget-builder-ai' ) );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'claude_invalid_json', __( 'Claude response is not valid JSON.', 'widget-builder-ai' ) );
		}

		$content = '';
		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			foreach ( $data['content'] as $part ) {
				if ( is_array( $part ) && isset( $part['type'] ) && 'text' === $part['type'] && isset( $part['text'] ) ) {
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
	 * Builds the system prompt sent to Claude.
	 *
	 * @return string Prompt text.
	 */
	private function build_system_prompt() {
		return 'You are a senior Elementor widget generator. Return JSON only, no markdown. ' .
		'Use schema: {"title":"","icon":"eicon-code","categories":["basic"],"markup":"","css":"","js":"","css_includes":[],"js_includes":[],"summary":""}. ' .
		'' .
		'Do not return a full PHP class and do not include content_template. ' .
		'Keep output minimal and necessary. JS and CSS must be empty strings unless the widget behavior requires them. ' .
		'If JS is needed, use Elementor frontend init + widget ready hook pattern and scope selectors with .wbai-{widget_name}. ' .
		'If CSS is needed, scope selectors with .wbai-{widget_name}.';
	}

	/**
	 * Builds the user prompt payload for Claude.
	 *
	 * @param string $message User request text.
	 * @param array  $context Conversation and widget context.
	 * @return string JSON payload.
	 */
	private function build_user_prompt( $message, $context ) {
		$payload = array(
			'message' => (string) $message,
			'context' => $context,
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
		return Widget_Builder_AI_JSON_Repair::extract( $content );
	}
}


