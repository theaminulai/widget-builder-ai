=== Widget Builder AI ===
Contributors: theaminuldev
Tags: elementor, ai, widget builder, code generation, admin tools
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Build custom Elementor widgets with AI. Generate, edit, preview, version, and roll back widget code directly from wp-admin.

== Description ==

Widget Builder AI helps administrators create Elementor widgets using prompt-driven generation.

Key capabilities:

* AI-assisted widget generation from prompt text.
* Multi-provider support with adapter fallback (OpenAI, Claude, Gemini, DeepSeek).
* Step-based setup wizard (title, icon, category, libraries).
* Built-in code editor for widget.php, style.css, and script.js.
* Widget preview URL support in Elementor editor.
* Version history with rollback.
* REST API endpoints for generate, save, load, versions, and rollback.
* Optional external CSS/JS library configuration per widget.

The plugin stores generated widget files in uploads and registers them as Elementor widgets.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/widget-builder-ai/.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open the Widget Builder AI section in wp-admin.
4. Create a widget and use the builder UI to generate and edit files.

== Frequently Asked Questions ==

= Does this plugin require Elementor? =

Yes. Generated widgets are registered through Elementor hooks, so Elementor is required for frontend widget usage.

= Where are generated files stored? =

Files are written to:
wp-content/uploads/widget-builder-ai/widgets/{slug-id}/

= How is access controlled? =

REST endpoints use a permission callback that requires the manage_options capability.

= Can I roll back widget changes? =

Yes. The plugin maintains version history and supports rollback to a specific version.

= Where do AI keys come from? =

Current code reads provider keys/endpoints from constants defined in the plugin bootstrap. For production, do not hardcode secrets in plugin source.

== Screenshots ==

1. Setup wizard for widget metadata.
2. Builder workspace with chat and code editor.
3. Preview panel in builder.
4. Prompt library popup.
5. Version history and rollback workflow.

== Changelog ==

= 1.0.0 =
* Initial release.
* Added setup wizard and React-based builder UI.
* Added AI generation flow with provider adapters.
* Added widget file persistence and Elementor registration.
* Added versioning and rollback support.
* Added REST API endpoints for generation and widget lifecycle.

== Upgrade Notice ==

= 1.0.0 =
Initial release.