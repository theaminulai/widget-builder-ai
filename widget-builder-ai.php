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


// API_KEY
define( 'AI_OPENAI_API_KEY', '' ); // Default to OpenAI, can be switched in settings
define( 'AI_CLAUDE_API_KEY', '' ); // Default to Claude, can be switched in settings
define( 'AI_GEMINI_API_KEY', '' ); // Default to Gemini, can be switched in settings


// API Endpoints
define( 'AI_CLAUDE_API_ENDPOINT', 'https://api.anthropic.com/v1/messages' );
define( 'AI_OPENAI_API_ENDPOINT', 'https://api.openai.com/v1/chat/completions' );
define( 'AI_OPENAI_API_CODEX_ENDPOINT', 'https://api.openai.com/v1/responses' );
define( 'AI_GEMINI_API_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models' );



/**
 * Loads the main plugin class and bootstraps the plugin on plugins_loaded.
 *
 * @since 1.0.0
 */
require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-plugin.php';
add_action( 'plugins_loaded', function() {
	Widget_Builder_AI_Plugin::init();
});