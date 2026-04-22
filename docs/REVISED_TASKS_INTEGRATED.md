# AI Widget Builder Tasks - Widget Builder AI Standalone Plugin

## Installation & Setup

### File Locations
- **WordPress Root:** `d:\wampserver\www\` (or your WordPress installation)
- **Plugin:** `/wp-content/plugins/widget-builder-ai/`
- **Includes:** `/wp-content/plugins/widget-builder-ai/includes/`
- **React Frontend:** `d:\wampserver\www\widgets-builder-chat/` (existing, stays as-is)

### API Integration
- REST API endpoints: `/wp-json/widget-builder-ai/v1/`
- Sessions managed by WordPress native methods
- Widget storage uses `widget_builder_ai` CPT

---

## PHASE 1: AI Module Infrastructure (Week 1)

### TASK P1.1: Plugin Entry Point & Bootstrap
**Priority:** P0 | **Estimated:** 2-3 hours
**Location:** `/wp-content/plugins/widget-builder-ai/widget-builder-ai.php`

**Deliverables:**
1. Plugin main file (widget-builder-ai.php)
2. Folder structure
3. Global class naming (Widget_Builder_AI_*)
4. Hook into plugins_loaded

**Code Template:**
```php
<?php
// /wp-content/plugins/widget-builder-ai/widget-builder-ai.php

defined('ABSPATH') || exit;

define('WIDGET_BUILDER_AI_VERSION', '1.0.0');
define('WIDGET_BUILDER_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WIDGET_BUILDER_AI_PLUGIN_URL', plugin_dir_url(__FILE__));

function widget_builder_ai_init_plugin() {
    require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-cpt.php';
    require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-assets.php';
    require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-handler.php';
    require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-version-manager.php';
    require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-generator.php';
    require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-register-widgets.php';
    require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-api.php';
    require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-filesystem.php';
    require_once WIDGET_BUILDER_AI_PLUGIN_DIR . 'includes/class-widget-builder-ai-normalizer.php';

    new Widget_Builder_AI_CPT();
    new Widget_Builder_AI_Assets();
    new Widget_Builder_AI_Register_Widgets();
    new Widget_Builder_AI_API();
}
add_action('plugins_loaded', 'widget_builder_ai_init_plugin');
```

**File Structure:**
```
widget-builder-ai/
├── widget-builder-ai.php
├── includes/
│   ├── class-widget-builder-ai-cpt.php
│   ├── class-widget-builder-ai-assets.php
│   ├── class-widget-builder-ai-handler.php
│   ├── class-widget-builder-ai-version-manager.php
│   ├── class-widget-builder-ai-generator.php
│   ├── class-widget-builder-ai-register-widgets.php
│   ├── class-widget-builder-ai-api.php
│   ├── class-widget-builder-ai-filesystem.php
│   ├── class-widget-builder-ai-normalizer.php
│   ├── class-widget-builder-ai-claude-adapter.php
│   ├── class-widget-builder-ai-openai-adapter.php
│   └── class-widget-builder-ai-gemini-adapter.php
├── build/
├── src/
└── docs/
```
**Feature Plan File Structure:**
```
widget-builder-ai/
├── widget-builder-ai.php
├── includes/
│	├── adapters/
│	│   ├── class-openai-adapter.php
│	│   └── class-claude-ai-adapter.php
│	├── api/
│	│   ├── class-rest-generate.php
│	│   ├── class-rest-validate.php
│	│   └── class-rest-preview.php
│	├── templates/
│	│   ├── admin-settings.php
│	│   └── prompts/
│	│       ├── system-prompt.txt
│	│       ├── widget-schema.json
│	│       └── examples.json
│   ├── class-ai-handler.php
│   ├── class-intent-parser.php
│   ├── class-prompt-builder.php
│   ├── class-code-parser.php
│   ├── class-widget-generator.php
│   └── class-code-validator.php
├── src/
│   ├── api/
│   ├── app.jsx
│   ├── components/
│   ├── index.jsx
│   └── main.jsx
├── readme.txt
└── README.md
```
---

### TASK P1.2: REST API Endpoints
**Priority:** P0 | **Estimated:** 3-4 hours  
**Depends On:** P1.1
**Location:** `/wp-content/plugins/widget-builder-ai/includes/class-widget-builder-ai-api.php`

**Routes:**
1. `POST /wp-json/widget-builder-ai/v1/generate` - Generate widget from prompt
2. `POST /wp-json/widget-builder-ai/v1/save` - Save widget files
3. `POST /wp-json/widget-builder-ai/v1/save/:id` - Save widget files by ID
4. `GET /wp-json/widget-builder-ai/v1/widget/:id` - Get widget payload
5. `GET /wp-json/widget-builder-ai/v1/widget/:id/versions` - Get version history
6. `POST /wp-json/widget-builder-ai/v1/widget/:id/rollback` - Rollback to version

**Code Template:**
```php
<?php
// /wp-content/plugins/widget-builder-ai/includes/class-widget-builder-ai-api.php

class Widget_Builder_AI_API {

    private $generator;

    public function __construct() {
        $this->generator = new Widget_Builder_AI_Generator();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('widget-builder-ai/v1', '/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generate'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route('widget-builder-ai/v1', '/widget/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'widget'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route('widget-builder-ai/v1', '/widget/(?P<id>\d+)/rollback', [
            'methods' => 'POST',
            'callback' => [$this, 'rollback'],
            'permission_callback' => [$this, 'can_manage'],
        ]);
    }

    public function generate(WP_REST_Request $request) {
        $params = $request->get_json_params();

        if (empty($params['message'])) {
            return new WP_REST_Response(['success' => false, 'error' => 'Message is required'], 400);
        }

        $result = $this->generator->generate(
            $params['message'],
            $params['model'] ?? 'gpt-4.1-mini',
            absint($params['widget_id'] ?? 0),
            $params['widget_config'] ?? []
        );

        if (is_wp_error($result)) {
            return new WP_REST_Response(['success' => false, 'error' => $result->get_error_message()], 400);
        }

        return new WP_REST_Response($result);
    }

    public function can_manage() {
        return current_user_can('manage_options');
    }
}
```

---

## PHASE 2: AI Services (Week 1-2)

### TASK P2.1: AI Handler with OpenAI Adapter
**Priority:** P0 | **Estimated:** 4-5 hours  
**Depends On:** P1.1
**Location:** `/wp-content/plugins/widget-builder-ai/includes/`

**Creates:**
- Abstract AI_Handler class
- OpenAI_Adapter class
- Proper error handling
- API key management

From previous detailed spec (see DEVELOPMENT_TASKS.md)

---

### TASK P2.2: Intent Parser (Natural Language Understanding)
**Priority:** P1 | **Estimated:** 3-4 hours  
**Depends On:** P1.1
**Location:** `/wp-content/plugins/widget-builder-ai/includes/class-widget-builder-ai-handler.php`

From previous detailed spec

---

### TASK P2.3: Prompt Builder & Parser
**Priority:** P1 | **Estimated:** 4-5 hours  
**Depends On:** P1.1
**Location:** `/wp-content/plugins/widget-builder-ai/includes/`

Creates:
- Prompt_Builder class
- Code_Parser class
- Template management

---

## PHASE 3: Widget Generation (Week 2-3)

### TASK P3.1: Widget Generator Service
**Priority:** P0 | **Estimated:** 4 hours  
**Depends On:** P2.1, P2.2, P2.3
**Location:** `/wp-content/plugins/widget-builder-ai/includes/class-widget-builder-ai-generator.php`

**Integration Points:**
- Uses `Widget_Builder_AI_Filesystem` for file writing
- Creates posts via `widget_builder_ai` CPT
- Stores files via `Widget_Builder_AI_Generator::META_FILES` post meta

**Key Code:**
```php
// /wp-content/plugins/widget-builder-ai/includes/class-widget-builder-ai-generator.php

class Widget_Builder_AI_Generator {

    const META_CHAT_HISTORY  = 'widget_builder_ai_chat_history';
    const META_FILES         = 'widget_builder_ai_files';
    const META_WIDGET_CONFIG = 'widget_builder_ai_widget_config';

    public function generate($message, $model = 'gpt-4.1-mini', $widget_id = 0, $widget_config = []) {
        // 1. Call AI handler
        // 2. Parse response into files
        // 3. Create or update widget post
        if (!$widget_id) {
            $widget_id = wp_insert_post([
                'post_title'  => $widget_config['title'] ?? 'AI Widget',
                'post_type'   => 'widget_builder_ai',
                'post_status' => 'publish',
            ]);
        }

        // 4. Save files to post meta & filesystem
        update_post_meta($widget_id, self::META_FILES, $files);
        update_post_meta($widget_id, self::META_WIDGET_CONFIG, $widget_config);

        // 5. Save chat history
        $this->add_message_to_history($widget_id, [
            'timestamp' => time(),
            'role'      => 'user',
            'content'   => $message,
            'model'     => $model,
        ]);

        return [
            'success'   => true,
            'widget_id' => $widget_id,
            'files'     => $files,
        ];
    }
}
```

---

### TASK P3.2: Code Validator
**Priority:** P1 | **Estimated:** 2-3 hours  
**Depends On:** P2.3
**Location:** `/wp-content/plugins/widget-builder-ai/includes/class-widget-builder-ai-normalizer.php`

From previous detailed spec

---

## PHASE 4: React Frontend Integration (Week 3-4)

### TASK P4.1: Create `useAIChat` React Hook
**Priority:** P0 | **Estimated:** 2-3 hours
**Depends On:** P1.2
**Location:** `/src/hooks/useAIChat.js` (your React app)

**Key Changes:**
- API endpoint: `http://localhost:8080/wp-json/widget-builder-ai/v1/generate`
- Nonce from WordPress global

**Code:**
```jsx
// /src/hooks/useAIChat.js
const API_BASE_URL = 'http://localhost:8080';

const response = await fetch(
  `${API_BASE_URL}/wp-json/widget-builder-ai/v1/generate`,
  {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': window.wpAIRest?.nonce || '',
    },
    body: JSON.stringify({
      message,
      widget_config: context.widgetConfig,
      model: context.selectedModel,
    }),
  }
);
```

From previous detailed spec (DEVELOPMENT_TASKS.md)

---

### TASK P4.2: Update ChatSection Component
**Priority:** P0 | **Estimated:** 1-2 hours  
**Depends On:** P4.1
**Location:** `/src/components/chat/ChatSection.jsx`

Replace simulated API calls with real API; add loading/error states.

---

### TASK P4.3: File Sync Hook
**Priority:** P1 | **Estimated:** 1-2 hours  
**Depends On:** P4.1
**Location:** `/src/hooks/useFileSync.js`

---

### TASK P4.4: Error Boundaries & Handling
**Priority:** P1 | **Estimated:** 1-2 hours  
**Depends On:** P4.2
**Location:** `/src/components/ErrorBoundary.jsx`

---

## PHASE 5: Testing & Deployment (Week 4)

### TASK P5.1: API Testing
**Priority:** P1 | **Estimated:** 3-4 hours

Test endpoints with:
- Postman/Insomnia
- Valid requests
- Error cases
- Rate limiting

---

### TASK P5.2: Integration Testing
**Priority:** P1 | **Estimated:** 3-4 hours

Test end-to-end flow:
- React → WordPress API
- Widget generation
- File creation
- Preview rendering

---

### TASK P5.3: Documentation & Deployment
**Priority:** P0 | **Estimated:** 2-3 hours

Create:
- Setup guide
- Configuration docs
- Admin settings guide
- Troubleshooting FAQ

---

## TOTAL EFFORT

| Phase | Tasks | Hours | Timeline |
|-------|-------|-------|----------|
| 1: Infrastructure | 2 | 5-7 | Day 1 |
| 2: AI Services | 3 | 11-14 | Days 2-4 |
| 3: Widget Gen | 2 | 6-7 | Days 4-5 |
| 4: React Integration | 4 | 5-7 | Days 5-6 |
| 5: Testing | 3 | 8-11 | Days 6-8 |
| **TOTAL** | **14** | **35-46 hrs** | **1.5-2 weeks** |

**With 1 backend dev + 1 frontend dev:** 1 week (concurrent)

---

## ARCHITECTURE OVERVIEW

✅ **Standalone plugin with:**
- Global class naming: `Widget_Builder_AI_*`
- REST API namespace: `widget-builder-ai/v1`
- Custom CPT: `widget_builder_ai`
- Own file system handler: `Widget_Builder_AI_Filesystem`
- Post meta keys prefixed: `widget_builder_ai_*`
- Admin submenu under Essential Addons (`eeal-settings`)

❌ **Does NOT depend on:**
- ElementsKit or ElementsKit Lite
- Elementor's Widget_Writer class
- `elementskit_widget` CPT
- `/wp-json/elementskit/v1/` namespace

---

## NEXT STEPS

1. **Confirm approach** ← You are here
2. **Start Task P1.1** (Module scaffold)
3. **Proceed through critical path:**  
   `P1.1 → P1.2 → P2.1 → P2.2 → P2.3 → P3.1 → P4.1 → P4.2 → P4.4`

Ready to code Task P1.1?
