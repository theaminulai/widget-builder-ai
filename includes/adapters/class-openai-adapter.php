<?php
/**
 * OpenAI adapter for Widget Builder AI.
 *
 * @package WidgetBuilderAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Widget_Builder_AI_OpenAI_Adapter {

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Chat completions endpoint.
	 *
	 * @var string
	 */
	private $endpoint;

	/**
	 * Responses endpoint (Codex models).
	 *
	 * @var string
	 */
	private $codex_endpoint;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_key        = AI_OPENAI_API_KEY;
		$this->endpoint       = AI_OPENAI_API_ENDPOINT;
		$this->codex_endpoint = AI_OPENAI_API_CODEX_ENDPOINT;
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
	 * Routes to the Responses API for Codex models,
	 * and to the Chat Completions API for all others.
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

		if ( $this->is_codex_model( $model ) ) {
			return $this->generate_spec_codex( $message, $context, $model );
		}

		return $this->generate_spec_chat( $message, $context, $model );
	}

	/**
	 * Generates a spec using the Chat Completions API (/v1/chat/completions).
	 *
	 * @param string $message User message.
	 * @param array  $context Widget context.
	 * @param string $model   Model slug.
	 * @return array|WP_Error
	 */
	private function generate_spec_chat( $message, $context, $model ) {
		$body = array(
			'model'               => $model,
			'max_completion_tokens' => 8000,
			'temperature'         => 1,
			'top_p'               => 1,
			'frequency_penalty'   => 0,
			'presence_penalty'    => 0,
			'store'               => false,
			'messages'            => $this->build_messages( $message, $context ),
		);

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'timeout' => 300,
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

		$code          = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'openai_request_failed',
				! empty( $response_body ) ? $response_body : __( 'OpenAI request failed.', 'widget-builder-ai' )
			);
		}

		$decoded = json_decode( $response_body, true );
		$content = isset( $decoded['choices'][0]['message']['content'] )
			? (string) $decoded['choices'][0]['message']['content']
			: '';

		return $this->parse_content( $content );
	}

	/**
	 * Generates a spec using the Responses API (/v1/responses) for Codex models.
	 *
	 * @param string $message User message.
	 * @param array  $context Widget context.
	 * @param string $model   Model slug.
	 * @return array|WP_Error
	 */
	private function generate_spec_codex( $message, $context, $model ) {
		$body = array(
			'model'     => $model,
			'store'     => false,
			'input'     => $this->build_codex_input( $message, $context ),
			'text'      => array(
				'format' => array( 'type' => 'text' ),
			),
			'reasoning' => array(
				'effort'  => 'high',
				'summary' => 'auto',
			),
			'tools'     => array(),
		);

		$response = wp_remote_post(
			$this->codex_endpoint,
			array(
				'timeout' => 300,
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

		$code          = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'openai_codex_request_failed',
				! empty( $response_body ) ? $response_body : __( 'OpenAI Codex request failed.', 'widget-builder-ai' )
			);
		}

		$decoded = json_decode( $response_body, true );
		$content = $this->extract_codex_content( $decoded );

		return $this->parse_content( $content );
	}

	/**
	 * Extracts text content from a Responses API response.
	 * The output array may contain reasoning blocks and text blocks.
	 *
	 * @param array $decoded Decoded response body.
	 * @return string Text content or empty string.
	 */
	private function extract_codex_content( $decoded ) {
		if ( empty( $decoded['output'] ) || ! is_array( $decoded['output'] ) ) {
			return '';
		}

		foreach ( $decoded['output'] as $item ) {
			if ( ! is_array( $item ) || ( isset( $item['type'] ) && 'reasoning' === $item['type'] ) ) {
				continue;
			}

			// message block with content array.
			if ( isset( $item['type'] ) && 'message' === $item['type'] && is_array( $item['content'] ) ) {
				foreach ( $item['content'] as $content_block ) {
					if ( isset( $content_block['type'] ) && 'output_text' === $content_block['type'] && isset( $content_block['text'] ) ) {
						return (string) $content_block['text'];
					}
				}
			}

			// Direct text block.
			if ( isset( $item['type'] ) && 'text' === $item['type'] && isset( $item['text'] ) ) {
				return (string) $item['text'];
			}
		}

		return '';
	}

	/**
	 * Builds the input array for the Responses API (Codex).
	 * Uses developer role instead of system, and input_text type.
	 *
	 * @param string $message User requirement text.
	 * @param array  $context Widget context.
	 * @return array Input array.
	 */
	private function build_codex_input( $message, $context ) {
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

		$config_payload = array(
			'widget_title'      => $title,
			'widget_slug'       => $slug,
			'widget_icon'       => $icon,
			'widget_categories' => $categories,
			'purpose'           => $purpose,
			'_instructions'     => array(
				'icon_is_fixed'       => 'You MUST use widget_icon exactly as provided in get_icon(). Do NOT change it.',
				'categories_is_fixed' => 'You MUST use widget_categories exactly as provided in get_categories(). Do NOT change or add categories.',
				'slug_usage'          => 'Use widget_slug as the return value of get_name() prefixed with wbai_ and for all CSS class names scoped under .wbai-{widget_slug}.',
			),
		);

		$config_text = "Generate a complete Elementor widget based on the following configuration.\n\n" .
			"Rules:\n" .
			"- Follow all _instructions strictly\n" .
			"- Use widget_slug with prefix wbai_\n" .
			"- Do not change icon or categories\n" .
			"- Generate PHP class + controls + render method\n\n" .
			"Configuration:\n" .
			wp_json_encode( $config_payload, JSON_PRETTY_PRINT );

		return array(
			array(
				'role'    => 'developer',
				'content' => array(
					array(
						'type' => 'input_text',
						'text' => $this->build_system_prompt(),
					),
				),
			),
			array(
				'role'    => 'user',
				'content' => array(
					array(
						'type' => 'input_text',
						'text' => $config_text,
					),
				),
			),
			array(
				'role'    => 'user',
				'content' => array(
					array(
						'type' => 'input_text',
						'text' => $purpose,
					),
				),
			),
		);
	}

	/**
	 * Builds the messages array for the Chat Completions API.
	 *
	 * @param string $message User requirement text.
	 * @param array  $context Widget context.
	 * @return array Messages array.
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
				'content' => $purpose,
			),
		);
	}

	/**
	 * Parses extracted text content into a widget spec array.
	 *
	 * @param string $content Raw text content from any API response.
	 * @return array|WP_Error
	 */
	private function parse_content( $content ) {
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'empty_ai_response', __( 'OpenAI returned an empty response.', 'widget-builder-ai' ) );
		}

		error_log( 'Raw AI content: ' . $content );
		$parsed = $this->extract_json( $content );
		error_log( 'Parsed AI content: ' . print_r( $parsed, true ) );
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
	 * Determines whether a model uses the Responses API.
	 *
	 * @param string $model Model slug.
	 * @return bool
	 */
	private function is_codex_model( $model ) {
		$model = strtolower( (string) $model );
		return false !== strpos( $model, 'codex' );
	}

	/**
	 * Builds the system prompt.
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
			'All controls you import from Elementor must be imported using the keyword use \Elementor followed by the name of the imported control, for example: use \Elementor\Controls_Manager' .
			'Add these "use \Elementor" statements after the namespace declaration. Available classes: ' .
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
	 * Extracts a JSON object from model output.
	 *
	 * @param string $content Model output content.
	 * @return array|null Decoded JSON array or null when extraction fails.
	 */
	private function extract_json( $content ) {
		return Widget_Builder_AI_JSON_Repair::extract( $content );
	}
}