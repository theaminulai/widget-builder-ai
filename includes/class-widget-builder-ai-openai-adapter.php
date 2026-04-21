<?php
/**
 * OpenAI adapter for Widget Builder AI.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adapter for communicating with the OpenAI API.
 *
 * @since 1.0.0
 */
class Widget_Builder_AI_OpenAI_Adapter {

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
		$this->api_key  = AI_OPENAI_API_KEY;
		$this->endpoint = AI_OPENAI_API_ENDPOINT;
	}

	/**
	 * Checks if the API key is configured.
	 *
	 * @return bool True if configured, false otherwise.
	 */
	public function has_api_key() {
		return ! empty( $this->api_key ) && ! empty( $this->endpoint );
	}

	/**
	 * Generates a widget specification using OpenAI.
	 *
	 * @param string $message The user message/prompt.
	 * @param array  $context The chat context.
	 * @param string $model   The OpenAI model to use.
	 * @return array|WP_Error The generated spec or an error.
	 */
	public function generate_spec( $message, $context = array(), $model = 'gpt-4.1-mini' ) {
		if ( ! $this->has_api_key() ) {
			return new WP_Error( 'missing_api_key', __( 'OpenAI API key is missing.', 'widget-builder-ai' ) );
		}

		$body = array(
			'model'      => $model,
			'max_completion_tokens' => 8000,
			'temperature'       => 1,
			'top_p'             => 1,
			'frequency_penalty' => 0,
			'presence_penalty'  => 0,
			'store'             => false,
			'messages'   => $this->build_messages( $message, $context ),
		);

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'timeout' => 120,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'openai_request_failed',
				! empty( $body ) ? $body : __( 'OpenAI request failed.', 'widget-builder-ai' )
			);
		}

		$decoded = json_decode( $body, true );
		$content = isset( $decoded['choices'][0]['message']['content'] )
			? (string) $decoded['choices'][0]['message']['content']
			: '';

		if ( '' === trim( $content ) ) {
			return new WP_Error( 'empty_ai_response', __( 'OpenAI returned an empty response.', 'widget-builder-ai' ) );
		}

		$parsed = $this->extract_json( $content );

		if ( ! is_array( $parsed ) ) {
			return new WP_Error( 'invalid_ai_json', __( 'OpenAI did not return valid JSON.', 'widget-builder-ai' ) );
		}

		$js = isset( $parsed['js'] ) ? trim( (string) $parsed['js'] ) : '';

		return array(
			'php'     => isset( $parsed['php'] ) ? (string) $parsed['php'] : '',
			'css'     => isset( $parsed['css'] ) ? (string) $parsed['css'] : '',
			'js'      => $js,
			'summary' => isset( $parsed['summary'] ) ? (string) $parsed['summary'] : '',
		);
	}

	/**
	 * Builds the full messages array following the curl structure:
	 * system → user (config block) → user (natural language requirement).
	 *
	 * @param string $message User requirement text.
	 * @param array  $context Conversation and widget context.
	 * @return array Messages array for the API request.
	 */
	private function build_messages( $message, $context ) {
		$message = trim( (string) $message );

		$widget_config = isset( $context['widget_config'] ) && is_array( $context['widget_config'] )
			? $context['widget_config']
			: array();

		$title      = isset( $widget_config['title'] ) ? sanitize_text_field( (string) $widget_config['title'] ) : 'Custom Widget';
		$icon       = isset( $widget_config['icon'] ) ? sanitize_text_field( (string) $widget_config['icon'] ) : 'eicon-code';
		$categories = isset( $widget_config['categories'] ) && is_array( $widget_config['categories'] )
			? array_values( array_map( 'sanitize_text_field', $widget_config['categories'] ) )
			: array( 'basic' );

		$slug    = sanitize_title( $title );
		$purpose = '' !== $message ? sanitize_textarea_field( $message ) : 'Build a useful custom Elementor widget.';

		// Message 1 (user): widget identity config block — mirrors the curl example exactly.
		$config_payload = array(
			'widget_title'      => $title,
			'widget_slug'       => $slug,
			'widget_icon'       => $icon,
			'widget_categories' => $categories,
			'purpose'           => $purpose,
			'_instructions'     => array(
				'icon_is_fixed'       => 'You MUST use widget_icon exactly as provided in get_icon(). Do NOT change it or replace it with a different eicon.',
				'categories_is_fixed' => 'You MUST use widget_categories exactly as provided in get_categories(). Do NOT change or add categories.',
				'slug_usage'          => 'Use widget_slug as the return value of get_name() prefixed with wbai_ and for all CSS class names scoped under .wbai-{widget_slug}.',
			),
		);

		$config_message = "Generate a complete Elementor widget based on the following configuration.\n\n" .
			"Rules:\n" .
			"- Follow all _instructions strictly\n" .
			"- Use widget_slug with prefix wbai_\n" .
			"- Do not change icon or categories\n" .
			"- Generate PHP class + controls + render method\n\n" .
			"Configuration:\n" .
			wp_json_encode( $config_payload, JSON_PRETTY_PRINT );

		// Message 2 (user): the natural language requirement — kept separate as in the curl example.
		$requirement_message = $purpose;

		return array(
			array(
				'role'    => 'system',
				'content' => $this->build_system_prompt(),
			),
			array(
				'role'    => 'user',
				'content' => $config_message,
			),
			array(
				'role'    => 'user',
				'content' => $requirement_message,
			),
		);
	}

	/**
	 * Builds the system prompt sent to OpenAI.
	 *
	 * @return string Prompt text.
	 */
	private function build_system_prompt() {
		return 'You are an expert WordPress and Elementor widget developer. ' .
			'Return ONLY a JSON object with exactly four keys: "php", "css", "js", "summary". No markdown, no extra text. ' .

			'## PHP RULES ' .
			'The "php" value must be a complete, working Elementor widget PHP class as a string. like: class WBAI_{widget_name} extends \Elementor\Widget_Base { ... } ' .
			'It must extend \Elementor\Widget_Base and implement: get_name( wbai_{widget_slug} ), get_title({widget_title} ), get_icon({widget_icon}), get_categories({widget_categories}), register_controls(), render(). ' .
			'Declare namespace WBAI\Widgets at the top of the file. ' .
			'Add these "use" statements after the namespace declaration. Available classes: ' .
			'use Elementor\Widget_Base; ' .
			'use Elementor\Controls_Manager; ' .
			'use Elementor\Repeater; ' .
			'use Elementor\Utils; ' .
			'use Elementor\Plugin; ' .
			'use Elementor\Group_Control_Typography; ' .
			'use Elementor\Group_Control_Border; ' .
			'use Elementor\Group_Control_Background; ' .
			'use Elementor\Group_Control_Box_Shadow; ' .
			'use Elementor\Group_Control_Text_Shadow; ' .
			'use Elementor\Group_Control_Image_Size; ' .
			'use Elementor\Group_Control_Css_Filter; ' .
			'use Elementor\Icons_Manager;' .
				'NOTE on Icons_Manager: When rendering icons with Icons_Manager::render_icon(), Elementor outputs either an <i> tag or an <svg> tag depending on the icon library. ' .
				'Therefore all CSS selectors and style controls targeting icons MUST cover both elements. ' .
				'Example selector: "{{WRAPPER}} .wbai-{slug}__icon i, {{WRAPPER}} .wbai-{slug}__icon svg" ' .
				'Apply this dual selector pattern to every color, size, and style control that targets an icon. ' .
				'In CSS, always write rules for both: .wbai-{slug}__icon i { ... } and .wbai-{slug}__icon svg { ... } ' .
				'For SVG specifically, use "fill: currentColor;" so it inherits the CSS color property. ' .
				'Never target only the i tag alone when an icon control is present. ' .

			'After the use statements, reference all Elementor classes WITHOUT the \Elementor\ prefix. Example: extends Widget_Base, Controls_Manager::TEXT, new Repeater(), Group_Control_Typography::get_type(). ' .
			'Use $this->get_settings_for_display() inside render(). ' .
			'Do NOT use Plugin::instance()->frontend->print_generate_html(). ' .
			'Escape all output: esc_html(), esc_attr(), esc_url(), wp_kses_post() as appropriate. Text Domain: widget-builder-ai. ' .
			'Follow WordPress coding standards. Class name must be derived from the widget title. ' .

			'## CONDITIONAL DISPLAY RULES ' .
			'Use the "condition" key to show or hide a control based on another control\'s value. ' .
			'Simple exact match: "condition" => [ "control_name" => "value" ] ' .
			'Multiple allowed values (OR logic): "condition" => [ "control_name" => [ "value1", "value2" ] ] ' .
			'Multiple conditions (AND logic — all must be true): "condition" => [ "control_a" => "yes", "control_b" => "value" ] ' .
			'For advanced logic use "conditions" (with s) instead: ' .
			'"conditions" => [ "relation" => "or", "terms" => [ [ "name" => "control_name", "operator" => "!==", "value" => "" ], [ "name" => "other_control", "operator" => "===", "value" => "yes" ] ] ] ' .
			'Available operators for "conditions": ==, ===, !=, !==, in, !in, contains, !contains, <, <=, >, >= ' .
			'"relation" accepts "and" (default) or "or". ' .
			'The "condition" key works on individual controls, control sections, and style sections. ' .
			'ALWAYS use "condition" to hide dependent controls. Example: hide autoplay_delay unless autoplay === "yes". Hide style sections unless the related feature is enabled. ' .
			'Never show controls that are irrelevant when a feature is disabled. ' .

			'## CONTROLS RULES ' .
			'You MUST register both content controls (TAB_CONTENT) and style controls (TAB_STYLE) for every widget. ' .
			'Content tab: use \Elementor\Controls_Manager::TAB_CONTENT. ' .
			'Style tab: use \Elementor\Controls_Manager::TAB_STYLE. ' .
			'Always wrap controls in start_controls_section() / end_controls_section(). ' .
			'Choose controls based on what makes sense for the widget purpose. Available controls: ' .
			'TEXT, TEXTAREA, NUMBER, SELECT, CHOOSE, SWITCHER, COLOR, SLIDER, DIMENSIONS, URL, MEDIA, ICONS, CODE, REPEATER, HEADING, DIVIDER, HIDDEN. ' .
			'GROUP CONTROLS (use $this->add_group_control(Type::get_type(), [...])): ' .
			'Group_Control_Typography, Group_Control_Border, Group_Control_Background, Group_Control_Box_Shadow, Group_Control_Text_Shadow, Group_Control_Image_Size, Group_Control_Css_Filter. ' .
			'RESPONSIVE CONTROLS: use $this->add_responsive_control() for layout/spacing controls that differ per breakpoint. ' .
			'SELECTORS: style controls must include a "selectors" key mapping {{WRAPPER}} CSS selectors to CSS properties. ' .

			'## CSS RULES ' .
			'The "css" value must be scoped under .wbai-{widget-slug}. ' .
			'Only write CSS for base/default styles. Style controls with selectors handle the rest. ' .
			'Return an empty string "" if no base CSS is needed. ' .

			'## JS RULES ' .
			'Return an empty string "" for "js" if the widget is static. ' .
			'Only provide JavaScript if the widget requires real frontend interactivity (slider, tabs, accordion, modal, counter, AJAX). ' .
			'When JavaScript IS required use this exact pattern: ' .
			'elementorFrontend.hooks.addAction("frontend/element_ready/wbai_{widget_name}.default", function($scope){ /* logic using $scope.find() only */ }); ' .
			'Return ONLY the raw hook call. No <script> tags, no IIFE, no wrappers. ' .

			'## SUMMARY RULES ' .
			'The "summary" value must be 2-3 sentences written for a site editor describing what the widget displays, what controls it exposes, and any notable frontend behaviour. ' .

			'## QUALITY RULES ' .
			'Production-ready code only. No placeholders. No TODOs. Minimal useful comments. ' .
			'CRITICAL: Your response must begin with { and end with }. No markdown fences. Raw JSON only. ' .
			'Response schema: {"php":"...","css":"...","js":"","summary":"..."}';
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