<?php
/**
 * DeepSeek adapter for Widget Builder AI.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adapter for communicating with the DeepSeek API.
 *
 * @since 1.0.0
 */
class Widget_Builder_AI_DeepSeek_Adapter {

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
		$this->api_key  = AI_DEEPSEEK_API_KEY ;
		$this->endpoint = AI_DEEPSEEK_API_ENDPOINT;
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
	 * Generates a widget specification using DeepSeek.
	 *
	 * @param string $message The user message/prompt.
	 * @param array  $context The chat context.
	 * @param string $model   The DeepSeek model to use.
	 * @return array|WP_Error The generated spec or an error.
	 */
	public function generate_spec( $message, $context = array(), $model = 'deepseek-chat' ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'deepseek_not_configured', __( 'DeepSeek API key is not configured.', 'widget-builder-ai' ) );
		}

		$system_prompt = $this->build_system_prompt();
		$user_prompt   = $this->build_user_prompt( $message, $context );

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'timeout' => 45,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => (string) $model,
						'temperature' => 0.2,
						'messages'    => array(
							array(
								'role'    => 'system',
								'content' => $system_prompt,
							),
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

		$code    = wp_remote_retrieve_response_code( $response );
		$body    = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'deepseek_request_failed', ! empty( $body ) ? $body : __( 'DeepSeek request failed.', 'widget-builder-ai' ) );
		}

		$content = isset( $decoded['choices'][0]['message']['content'] ) ? (string) $decoded['choices'][0]['message']['content'] : '';
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'empty_deepseek_response', __( 'DeepSeek returned an empty response.', 'widget-builder-ai' ) );
		}

		$parsed = $this->extract_json( $content );
		if ( ! is_array( $parsed ) ) {
			return new WP_Error( 'invalid_deepseek_json', __( 'DeepSeek did not return valid JSON.', 'widget-builder-ai' ) );
		}

		return $parsed;
	}

	/**
	 * Builds the system prompt sent to DeepSeek.
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
	 * Builds the user prompt payload for DeepSeek.
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
		$trimmed = trim( (string) $content );

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


