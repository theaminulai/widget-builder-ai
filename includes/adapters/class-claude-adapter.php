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
	public function generate_spec( $message, $context = array(), $model = 'claude-sonnet-4-6' ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'claude_not_configured', __( 'Claude API key is not configured.', 'widget-builder-ai' ) );
		}

		$model_lower   = strtolower( (string) $model );
		$system_prompt = $this->build_system_prompt();
		$user_prompt   = $this->build_user_prompt( $message, $context );

		$body = array(
			'model'      => (string) $model,
			'max_tokens' => 16000,
			'temperature' => 1,
			'system'     => $system_prompt,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => $user_prompt,
						),
					),
				),
			),
		);

		// Enable extended thinking for Opus and Sonnet 4+ models.
		if ( false !== strpos( $model_lower, 'opus' ) ) {
			$body['thinking'] = array(
				'type'          => 'adaptive',
				'budget_tokens' => 10000,
				'output_config' => array( 'effort' => 'medium' ),
			);
			// thinking requires max_tokens > budget_tokens and no temperature.
			$body['max_tokens'] = 20000;
		} elseif ( false !== strpos( $model_lower, 'sonnet' ) ) {
			$body['thinking'] = array(
				'type'          => 'enabled',
				'budget_tokens' => 10000,
			);
			// thinking requires max_tokens > budget_tokens and no temperature.
			$body['max_tokens'] = 20000;
		}

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'timeout' => 300,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $this->api_key,
					'anthropic-version' => $this->api_version,
				),
				'body' => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code         = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data         = json_decode( $response_body, true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'claude_request_failed',
				! empty( $response_body ) ? $response_body : __( 'Claude request failed.', 'widget-builder-ai' )
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'claude_invalid_json', __( 'Claude response is not valid JSON.', 'widget-builder-ai' ) );
		}

		// Extract only text blocks — skip thinking blocks.
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
			$js = isset( $parsed['js'] ) ? trim( (string) $parsed['js'] ) : '';

			return array(
				'php'     => isset( $parsed['php'] ) ? (string) $parsed['php'] : '',
				'css'     => isset( $parsed['css'] ) ? (string) $parsed['css'] : '',
				'js'      => $js,
				'summary' => isset( $parsed['summary'] ) ? (string) $parsed['summary'] : '',
			);
		}

		return new WP_Error( 'invalid_claude_json', __( 'Claude did not return valid JSON.', 'widget-builder-ai' ) );
	}

	/**
	 * Builds the system prompt sent to Claude.
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
			'Never show controls that are irrelevant when a feature is disabled. @see https://github.com/elementor/elementor-developers-docs/blob/master/src/editor-controls/conditional-display.md' .

			'## CONTROLS RULES ' .
			'You MUST register both content controls (TAB_CONTENT) and style controls (TAB_STYLE) for every widget. ' .
			'Content tab: use \Elementor\Controls_Manager::TAB_CONTENT. ' .
			'Style tab: use \Elementor\Controls_Manager::TAB_STYLE. ' .
			'Basic control use \Elementor\Controls_Manager ,  Group_Control use \Elementor\Group_Control_ , use \Elementor\Repeater .  ' .
			'Always wrap controls in start_controls_section() / end_controls_section(). ' .
			'Choose controls based on what makes sense for the widget purpose. Available controls: ' .


			'TEXT – single-line text input. ' .
			'TEXTAREA – multi-line text input. ' .
			'NUMBER – numeric input with min/max/step. ' .
			'SELECT – dropdown with options array (key => label). ' .
			'CHOOSE – icon button group for alignment/options (options array with title+icon). ' .
			'SWITCHER – yes/no toggle (returns "yes" or ""). ' .
			'COLOR – color picker (use with selectors). ' .
			'SLIDER – range slider with size_unit and range (use with selectors). ' .
			'DIMENSIONS – top/right/bottom/left fields with unit (use with selectors). ' .
			'URL – URL input with is_external and nofollow options. ' .
			'MEDIA – image/file picker (returns array with url and id). ' .
			'ICONS – icon picker (render with \Elementor\Icons_Manager::render_icon()). ' .
			'CODE – code editor (set language: html/css/js/php). ' .
			'REPEATER – repeatable group of controls. ' .
			'HEADING – decorative section heading (UI only, no value). ' .
			'DIVIDER – horizontal rule separator (UI only). ' .
			'HIDDEN – hidden field with a fixed default value. ' .

			'GROUP CONTROLS (use $this->add_group_control(Type::get_type(), [...])): ' .
			'Group_Control_Typography::get_type() – font family, size, weight, style, line-height, letter-spacing (selector required). ' .
			'Group_Control_Border::get_type() – border type, width, color (selector required). ' .
			'Group_Control_Background::get_type() – classic/gradient/video background (selector required). ' .
			'Group_Control_Box_Shadow::get_type() – box shadow (selector required). ' .
			'Group_Control_Text_Shadow::get_type() – text shadow (selector required). ' .
			'Group_Control_Image_Size::get_type() – image size with custom dimensions. ' .
			'Group_Control_Css_Filter::get_type() – CSS filters like blur/brightness (selector required). ' .

			'RESPONSIVE CONTROLS: use $this->add_responsive_control() for any layout/spacing control that should differ per breakpoint (typography, padding, margin, alignment). ' .

			'SELECTORS: style controls must include a "selectors" key mapping {{WRAPPER}} CSS selectors to CSS properties. Example: "selectors" => ["{{WRAPPER}} .wbai-{slug}__title" => "color: {{VALUE}};"] ' .

			'## CSS RULES ' .
			'The "css" value must be scoped under .wbai-{widget-slug}. ' .
			'Only write CSS for base/default styles. Style controls with selectors handle the rest. ' .
			'Return an empty string "" if no base CSS is needed. ' .

			'## JS RULES ' .
			'Return an empty string "" for "js" if the widget is static (text, image, headings, simple cards, or display-only UI). ' .
			'Only provide JavaScript if the widget requires real frontend interactivity (e.g. slider, tabs, toggle, accordion, modal, counter, AJAX). ' .
			'Do NOT generate JS for styling, layout, class toggling, or anything that CSS alone can handle. ' .
			'When JavaScript IS required, you MUST follow this exact Elementor pattern: ' .
			'elementorFrontend.hooks.addAction("frontend/element_ready/wbai{widget_name}.default", function($scope){ /* logic */ }); ' .
			'JS STRUCTURE RULES: ' .
			'- Do NOT wrap in IIFE, (function($){})(), or $(function(){}). Return only the raw hook call. ' .
			'- Do NOT nest elementorFrontend.hooks.addAction inside another hook. ' .
			'- Do NOT use $(document).ready(). ' .
			'- Do NOT use global selectors like $(".class"). Always use $scope.find(). ' .
			'- Do NOT attach duplicate event listeners. Ensure logic runs once per widget instance. ' .
			'- Keep all logic scoped inside $scope. ' .
			'SCOPE RULE: ' .
			'- All selectors MUST be scoped under .wbai-{widget-slug}. ' .
			'- Example: $scope.find(".wbai-{widget-slug} .item") ' .
			'OUTPUT RULE: ' .
			'- Return ONLY the raw elementorFrontend.hooks.addAction(...) call. No <script> tags, no IIFE, no wrappers, no explanations. ' .

			'## SUMMARY RULES ' .
			'The "summary" value must be 2-3 sentences written for a site editor. ' .
			'Describe: what the widget displays, what controls it exposes to the editor, and any notable frontend behaviour. ' .
			'Example: "A code reference widget that displays formatted code snippets with syntax highlighting. The editor can set a title, paste code content, and choose the language from a dropdown. A copy-to-clipboard button is included, powered by a small JavaScript snippet." ' .

			'## QUALITY RULES ' .
			'Production-ready code only. No placeholders. No TODOs. Minimal useful comments. ' .
			'CRITICAL: Your response must begin with { and end with }. No markdown fences. Raw JSON only. ' .
			'Response schema: {"php":"...","css":"...","js":"","summary":"..."}';
	}

	/**
	 * Builds the user prompt payload for Claude.
	 *
	 * @param string $message User request text.
	 * @param array  $context Conversation and widget context.
	 * @return string JSON payload.
	 */

	private function build_user_prompt( $message, $context ) {
		$widget_config = $context['widget_config'] ?? [];
		$title      = sanitize_text_field( $widget_config['title'] ?? 'Custom Widget' );
		$icon       = sanitize_text_field( $widget_config['icon'] ?? 'eicon-code' );
		$categories = $widget_config['categories'] ?? ['basic'];
		$slug       = sanitize_title( $title );

		return sprintf(
			"Generate an Elementor widget with these exact settings:\n\nWidget title: %s\nWidget slug (for get_name): wbai_%s\nWidget icon (use exactly): %s\nWidget categories (use exactly): [%s]\n\nPurpose: %s\n\nReturn ONLY the raw JSON object.",
			$title,
			$slug,
			$icon,
			implode( ', ', array_map( 'sanitize_text_field', $categories ) ),
			sanitize_textarea_field( $message )
		);
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


