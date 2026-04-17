<?php
/**
 * Plugin Name: Widget Builder AI
 * Plugin URI: https://theaminul.com
 * Description: Widget Builder AI is a powerful WordPress plugin that allows you to create custom widgets using artificial intelligence. With its user-friendly interface and advanced AI capabilities, you can easily design and customize widgets to enhance your website's functionality and appearance.
 * Version: 1.0.0
 * Author: Aminul Islam
 * Author URI: https://theaminul.com
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: widget-builder-ai
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
define( 'WIDGET_BUILDER_AI_VERSION', '1.0.0' );
define( 'WIDGET_BUILDER_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WIDGET_BUILDER_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

define( 'AI_CLAUDE_API_KEY', '' );
define( 'AI_CLAUDE_API_ENDPOINT', 'https://api.anthropic.com/v1/messages' );

define( 'AI_OPENAI_API_KEY', '' );
define( 'AI_OPENAI_API_ENDPOINT', 'https://api.openai.com/v1/chat/completions' );

define( 'AI_GEMINI_API_KEY', '' );
define( 'AI_GEMINI_API_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models' );

define( 'AI_DEEPSEEK_API_KEY', '' );
define( 'AI_DEEPSEEK_API_ENDPOINT', 'https://api.deepseek.com/chat/completions' );

/**
 * Initializes the plugin by requiring necessary files and instantiating classes.
 *
 * @since 1.0.0
 * @return void
 */
function widget_builder_ai_init_plugin() {
	require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-cpt.php';
	require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-assets.php';
	require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-claude-adapter.php';
	require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-openai-adapter.php';
	require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-gemini-adapter.php';
	require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-deepseek-adapter.php';
	require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-ai-handler.php';
	require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-version-manager.php';
	require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-generator.php';
	require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-register-widgets.php';
	require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-api.php';
	
	new Widget_Builder_AI_CPT();
	new Widget_Builder_AI_Assets();
	new Widget_Builder_AI_Register_Widgets();
	new Widget_Builder_AI_API();
}
add_action( 'plugins_loaded', 'widget_builder_ai_init_plugin' );