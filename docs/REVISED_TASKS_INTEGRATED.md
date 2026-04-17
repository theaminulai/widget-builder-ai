# REVISED: AI Widget Builder Tasks - Inside ElementsKit Widget-Builder Module

## Installation & Setup

### File Locations
- **WordPress Root:** `d:\wampserver\www\` (or your WordPress installation)
- **ElementsKit Plugin:** `/wp-content/plugins/elementskit-lite/`
- **Widget Builder Module:** `/wp-content/plugins/elementskit-lite/modules/widget-builder/`
- **AI Extension (NEW):** `/wp-content/plugins/elementskit-lite/modules/widget-builder-ai/`
- **React Frontend:** `d:\wampserver\www\widgets-builder-chat/` (existing, stays as-is)

### API Integration
- Rest API endpoints in ElementsKit namespace: `/wp-json/elementskit/v1/widget-builder-ai/`
- Reuse existing ElementsKit REST API structure
- Sessions managed by WordPress native methods
- Widget storage uses existing `elementskit_widget` CPT

---

## PHASE 1: AI Module Infrastructure (Week 1)

### TASK P1.1: Create Widget-Builder-AI Module Scaffold
**Priority:** P0 | **Estimated:** 2-3 hours
**Location:** `/wp-content/plugins/elementskit-lite/modules/widget-builder-ai/`

**Deliverables:**
1. Module initialization file (init.php)
2. Folder structure
3. Namespace setup (ElementsKit_Lite\Modules\Widget_Builder_AI)
4. Hook into ElementsKit's module loader
5. Settings in ElementsKit admin

**Code Template:**
```php
<?php
// /wp-content/plugins/elementskit-lite/modules/widget-builder-ai/init.php

namespace ElementsKit_Lite\Modules\Widget_Builder_AI;

use ElementsKit_Lite\Modules\Widget_Builder_AI\{
    Includes\AI_Handler,
    API\REST_Generate,
    API\REST_Validate,
};

defined('ABSPATH') || exit;

class Init {
    private static $instance;

    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->include_files();
        $this->init_hooks();
    }

    private function include_files() {
        require_once __DIR__ . '/includes/class-ai-handler.php';
        require_once __DIR__ . '/includes/class-intent-parser.php';
        require_once __DIR__ . '/includes/class-prompt-builder.php';
        require_once __DIR__ . '/includes/class-code-parser.php';
        require_once __DIR__ . '/includes/class-widget-generator.php';
        require_once __DIR__ . '/api/class-rest-generate.php';
        require_once __DIR__ . '/api/class-rest-validate.php';
    }

    private function init_hooks() {
        // Register REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Add admin settings
        add_action('admin_menu', [$this, 'add_settings_page']);
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function register_rest_routes() {
        $rest_generate = new REST_Generate();
        $rest_generate->register();
        
        $rest_validate = new REST_Validate();
        $rest_validate->register();
    }

    public function add_settings_page() {
        add_submenu_page(
            'elementor',
            'AI Widget Builder Settings',
            'AI Widget Settings',
            'manage_options',
            'elementskit-ai-settings',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_scripts() {
        $screen = get_current_screen();
        if ($screen->id !== 'elementskit_page_elementskit-ai-settings') {
            return;
        }
        
        wp_enqueue_script(
            'elementskit-ai-settings',
            \ElementsKit_Lite::plugin_url() . 
                'modules/widget-builder-ai/assets/js/settings.js',
            [],
            \ElementsKit_Lite::version()
        );
    }

    public function render_settings_page() {
        include __DIR__ . '/templates/admin-settings.php';
    }
}

// Initialize on plugins_loaded
add_action('elementskit/modules/loaded', function() {
    Init::instance();
});
```

**File Structure to Create:**
```
widget-builder-ai/
├── init.php
├── includes/
│   ├── class-ai-handler.php
│   ├── class-intent-parser.php
│   ├── class-prompt-builder.php
│   ├── class-code-parser.php
│   ├── class-widget-generator.php
│   └── class-code-validator.php
├── adapters/
│   ├── class-openai-adapter.php
│   └── class-claude-ai-adapter.php
├── api/
│   ├── class-rest-generate.php
│   ├── class-rest-validate.php
│   └── class-rest-preview.php
├── templates/
│   ├── admin-settings.php
│   └── prompts/
│       ├── system-prompt.txt
│       ├── widget-schema.json
│       └── examples.json
├── assets/
│   └── js/
│       └── settings.js
└── README.md
```

---

### TASK P1.2: Create REST API Endpoints in ElementsKit Namespace
**Priority:** P0 | **Estimated:** 3-4 hours  
**Depends On:** P1.1
**Location:** `/wp-content/plugins/elementskit-lite/modules/widget-builder-ai/api/`

**Routes to Create:**
1. `POST /wp-json/elementskit/v1/ai-generate` - Generate widget from prompt
2. `POST /wp-json/elementskit/v1/ai-validate` - Validate generated code
3. `GET /wp-json/elementskit/v1/ai-preview/:id` - Preview widget

**Code Template:**
```php
<?php
// /wp-content/plugins/elementskit-lite/modules/widget-builder-ai/api/class-rest-generate.php

namespace ElementsKit_Lite\Modules\Widget_Builder_AI\API;

use ElementsKit_Lite\Modules\Widget_Builder_AI\Includes\Widget_Generator;

class REST_Generate {
    public function register() {
        register_rest_route('elementskit/v1', '/ai-generate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_generate'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'message' => [
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function($param) {
                        return is_string($param) && strlen($param) > 0;
                    },
                ],
                'widget_config' => [
                    'required' => false,
                    'type' => 'object',
                ],
                'model' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['openai'],
                    'default' => 'openai',
                ],
            ],
        ]);
    }

    public function handle_generate(\WP_REST_Request $request) {
        try {
            $params = $request->get_json_params();
            
            // Validate
            if (empty($params['message'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => 'Message is required',
                ], 400);
            }

            // Generate widget
            $generator = new Widget_Generator();
            $result = $generator->generate($params['message'], $params['model'] ?? 'openai');

            if (!$result['success']) {
                return new \WP_REST_Response($result, 500);
            }

            return new \WP_REST_Response([
                'success' => true,
                'widget_id' => $result['widget_id'],
                'message' => $result['message'],
                'files' => $result['files'] ?? [],
                'preview_url' => admin_url('post.php?post=' . $result['widget_id'] . '&action=elementor'),
            ]);

        } catch (\Exception $e) {
            error_log('Widget generation error: ' . $e->getMessage());
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function check_permission() {
        return current_user_can('manage_options');
    }
}
```

---

## PHASE 2: AI Services (Week 1-2)

### TASK P2.1: AI Handler with OpenAI Adapter
**Priority:** P0 | **Estimated:** 4-5 hours  
**Depends On:** P1.1
**Location:** `/wp-content/plugins/elementskit-lite/modules/widget-builder-ai/includes/`

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
**Location:** `/wp-content/plugins/elementskit-lite/modules/widget-builder-ai/includes/class-intent-parser.php`

From previous detailed spec

---

### TASK P2.3: Prompt Builder & Parser
**Priority:** P1 | **Estimated:** 4-5 hours  
**Depends On:** P1.1
**Location:** `/wp-content/plugins/elementskit-lite/modules/widget-builder-ai/includes/`

Creates:
- Prompt_Builder class
- Code_Parser class
- Template management

---

## PHASE 3: Widget Generation (Week 2-3)

### TASK P3.1: Widget Generator Service
**Priority:** P0 | **Estimated:** 4 hours  
**Depends On:** P2.1, P2.2, P2.3
**Location:** `/wp-content/plugins/elementskit-lite/modules/widget-builder-ai/includes/class-widget-generator.php`

**Integration Points:**
- Use ElementsKit's existing Widget_Writer class
- Create posts via existing Widget_Builder CPT
- Reuse file generation

**Key Code:**
```php
// Reuse ElementsKit's Widget_Writer
use ElementsKit_Lite\Modules\Widget_Builder\Controls\Widget_Writer;

public function generate($message, $model = 'openai') {
    try {
        // 1-4. Parse, prompt, call AI, parse response
        // ... (from earlier spec)
        
        // 5. Create widget post
        $widget_id = wp_insert_post([
            'post_title' => $config['title'],
            'post_type' => 'elementskit_widget',
            'post_status' => 'publish',
        ]);

        // 6. Save metadata
        update_post_meta(
            $widget_id,
            'elementskit_custom_widget_data',
            $config
        );

        // 7. Generate files using ElementsKit's Widget_Writer
        $writer = new Widget_Writer($config, $widget_id);
        $wp_filesystem = \ElementsKit_Lite\Modules\Widget_Builder\Widget_File::get_wp_filesystem_pointer();
        
        $writer->start_backing($wp_filesystem);
        $writer->finish_backing($wp_filesystem);

        return [
            'success' => true,
            'widget_id' => $widget_id,
            'message' => $ai_response,
            'files' => $parsed,
        ];
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
```

---

### TASK P3.2: Code Validator
**Priority:** P1 | **Estimated:** 2-3 hours  
**Depends On:** P2.3
**Location:** `/wp-content/plugins/elementskit-lite/modules/widget-builder-ai/includes/class-code-validator.php`

From previous detailed spec

---

## PHASE 4: React Frontend Integration (Week 3-4)

### TASK P4.1: Create `useAIChat` React Hook
**Priority:** P0 | **Estimated:** 2-3 hours
**Depends On:** P1.2
**Location:** `/src/hooks/useAIChat.js` (your React app)

**Key Changes:**
- API endpoint: `http://localhost:8080/wp-json/elementskit/v1/ai-generate`
- Request format matches ElementsKit endpoint
- Nonce from WordPress global

**Code:**
```jsx
// /src/hooks/useAIChat.js
const API_BASE_URL = 'http://localhost:8080';

const response = await fetch(
  `${API_BASE_URL}/wp-json/elementskit/v1/ai-generate`,
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

## KEY DIFFERENCES FROM SEPARATE PLUGIN

✅ **Uses ElementsKit's existing:**
- Namespace: `ElementsKit_Lite\Modules\Widget_Builder_AI`
- REST API structure: `/wp-json/elementskit/v1/`
- Widget storage: `elementskit_widget` CPT
- File generation: Widget_Writer class
- Admin panels

❌ **NOT creating:**
- New plugin file
- Separate plugin directory
- Duplicate REST API structure
- New CPT or tables

---

## NEXT STEPS

1. **Confirm approach** ← You are here
2. **Start Task P1.1** (Module scaffold)
3. **Proceed through critical path:**  
   `P1.1 → P1.2 → P2.1 → P2.2 → P2.3 → P3.1 → P4.1 → P4.2 → P4.4`

Ready to code Task P1.1?
