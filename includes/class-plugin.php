<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Widget_Builder_AI_Plugin {

	public static function init() {
		require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/admin/class-cpt.php';
		require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/admin/class-assets.php';
		require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/admin/class-admin-menu.php';
		require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/adapters/class-claude-adapter.php';
		require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/adapters/class-openai-adapter.php';
		require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/adapters/class-gemini-adapter.php';
		require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-json-repair.php';
		require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-ai-handler.php';
		require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-version-manager.php';
		require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-generator.php';
		require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-register-widgets.php';
		require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/api/class-rest-api.php';
		require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-filesystem.php';
		require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-code-validator.php';

		new Widget_Builder_AI_CPT();
		new Widget_Builder_AI_Admin_Menu();
		new Widget_Builder_AI_Assets();
		new Widget_Builder_AI_Register_Widgets();
		new Widget_Builder_AI_REST_API();
	}
}
