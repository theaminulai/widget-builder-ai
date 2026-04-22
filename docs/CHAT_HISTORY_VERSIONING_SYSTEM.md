# NEW TASKS: Chat History & Widget Versioning
## P3.1B, P3.1C, P4.5, P4.6, P4.7 - Complete Specifications

---

## TASK P3.1B: Store Chat History with Widget
**Priority:** P0 | **Estimated:** 2-3 hours  
**Depends On:** P3.1 | **Blocks:** P4.5

**Location:** `/wp-content/plugins/widget-builder-ai/includes/class-widget-builder-ai-generator.php`

**Requirements:**
- Save chat conversation when widget is generated
- Persist entire message history in post_meta
- Include timestamps and model used
- Enable retrieval for editing

**Acceptance Criteria:**
- Chat history saved to post_meta
- All messages stored in chronological order
- Metadata includes timestamp, role, model
- Can retrieve history by widget_id
- History survives widget updates

**Data Structure:**
```json
{
  "widget_builder_ai_chat_history": [
    {
      "timestamp": 1712102400,
      "role": "user",
      "content": "Create a button widget with loading state",
      "model": "openai"
    },
    {
      "timestamp": 1712102405,
      "role": "assistant", 
      "content": "I've created a button widget with...",
      "version_created": 1,
      "ai_model_used": "gpt-4"
    },
    {
      "timestamp": 1712102410,
      "role": "user",
      "content": "Add a success state too",
      "model": "openai"
    }
  ]
}
```

**Implementation:**
- Update Widget_Generator::generate() to save chat
- Create helper: save_chat_history($widget_id, $message, $role, $version)
- Create helper: get_chat_history($widget_id)
- Handle both new widget creation and edits

**Code Template:**
```php
<?php
// In class-widget-generator.php

public function generate($message, $model = 'openai', $widget_id = null) {
    try {
        // ... existing generation code ...
        
        // NEW: Determine if creating or updating
        $is_update = !empty($widget_id);
        
        // NEW: Load chat history if updating
        $chat_history = [];
        if ($is_update) {
            $chat_history = $this->get_chat_history($widget_id);
        }
        
        // ... AI call happens ...
        
        // Create or update widget post
        if (!$is_update) {
            $widget_id = wp_insert_post([
                'post_title' => $config['title'],
                'post_type' => 'widget_builder_ai',
                'post_status' => 'publish',
            ]);
        }
        
        // NEW: Save user message to chat
        $this->add_message_to_history($widget_id, [
            'timestamp' => time(),
            'role' => 'user',
            'content' => $message,
            'model' => $model,
        ]);
        
        // ... generate files ...
        
        // NEW: Save AI response to chat
        $this->add_message_to_history($widget_id, [
            'timestamp' => time(),
            'role' => 'assistant',
            'content' => $ai_response,
            'version_created' => $new_version,
            'ai_model_used' => $model,
        ]);
        
        return [
            'success' => true,
            'widget_id' => $widget_id,
            'version' => $new_version,
        ];
        
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

private function get_chat_history($widget_id) {
    $history = get_post_meta($widget_id, 'widget_builder_ai_chat_history', true);
    return is_array($history) ? $history : [];
}

private function add_message_to_history($widget_id, $message) {
    $history = $this->get_chat_history($widget_id);
    $history[] = $message;
    update_post_meta($widget_id, 'widget_builder_ai_chat_history', $history);
}
```

---

## TASK P3.1C: Implement File Versioning
**Priority:** P0 | **Estimated:** 3-4 hours  
**Depends On:** P3.1 | **Blocks:** P4.5, P4.6

**Location:** `/wp-content/plugins/widget-builder-ai/includes/class-widget-builder-ai-generator.php`

**Requirements:**
- Track multiple versions of generated files
- Store version metadata (timestamp, AI model, changes)
- Keep current version pointer
- Enable rollback capability

**Acceptance Criteria:**
- Each generation creates new version
- Versions numbered sequentially
- Metadata includes timestamp, model, files
- Current version tracked
- Can retrieve any previous version
- Version count limited (keep last 10)

**Data Structure:**
```json
{
  "widget_builder_ai_versions": {
    "1": {
      "timestamp": 1712102405,
      "ai_model": "gpt-4",
      "files": {
        "widget.php": "class Ekit_Wb_123 extends Widget_Base {...}",
        "style.css": ".ekit-wb-123 {...}",
        "script.js": "jQuery('.ekit-wb-123').on(...)"
      },
      "file_hash": "abc123def456",
      "changes_summary": "Initial button widget with loading state"
    },
    "2": {
      "timestamp": 1712102500,
      "ai_model": "gpt-4",
      "files": {...},
      "file_hash": "xyz789uvw012",
      "changes_summary": "Added success/error states"
    }
  },
  "widget_builder_ai_current_version": 2,
  "widget_builder_ai_version_count": 2
}
```

**Implementation:**
- Create Version_Manager class
- Methods: create_version(), get_version(), get_all_versions()
- Hash files to detect actual changes
- Limit stored versions (keep 10 max, delete old)
- Track file changes in summary

**Code Template:**
```php
<?php
// New file: class-version-manager.php

class Widget_Builder_AI_Version_Manager {
    const MAX_VERSIONS = 10;

    public function create_version($widget_id, $files, $ai_model, $summary = '') {
        // Get next version number
        $versions = get_post_meta($widget_id, 'widget_builder_ai_versions', true) ?? [];
        $version_num = count($versions) + 1;
        
        // Create version data
        $version_data = [
            'timestamp' => time(),
            'ai_model' => $ai_model,
            'files' => $files,
            'file_hash' => $this->hash_files($files),
            'changes_summary' => $summary,
        ];
        
        // Store version
        $versions[$version_num] = $version_data;
        
        // Keep only last MAX_VERSIONS
        if (count($versions) > self::MAX_VERSIONS) {
            $versions = array_slice($versions, -self::MAX_VERSIONS, null, true);
        }
        
        // Save to post meta
        update_post_meta($widget_id, 'widget_builder_ai_versions', $versions);
        update_post_meta($widget_id, 'widget_builder_ai_current_version', $version_num);
        
        return $version_num;
    }

    public function get_version($widget_id, $version_num) {
        $versions = get_post_meta($widget_id, 'widget_builder_ai_versions', true) ?? [];
        return $versions[$version_num] ?? null;
    }

    public function get_current_version($widget_id) {
        $current = get_post_meta($widget_id, 'widget_builder_ai_current_version', true) ?? 1;
        return $this->get_version($widget_id, $current);
    }

    public function get_all_versions($widget_id) {
        return get_post_meta($widget_id, 'widget_builder_ai_versions', true) ?? [];
    }

    public function get_versions_list($widget_id) {
        $versions = $this->get_all_versions($widget_id);
        $list = [];
        
        foreach ($versions as $num => $version) {
            $list[] = [
                'version' => $num,
                'timestamp' => $version['timestamp'],
                'date' => wp_date('M j, Y H:i', $version['timestamp']),
                'model' => $version['ai_model'],
                'summary' => $version['changes_summary'],
                'is_current' => $num === get_post_meta($widget_id, 'widget_builder_ai_current_version', true),
            ];
        }
        
        return array_reverse($list); // Newest first
    }

    private function hash_files($files) {
        $content = '';
        foreach ($files as $name => $code) {
            $content .= $name . ':' . $code;
        }
        return md5($content);
    }
}
```

---

## TASK P4.5: Load Chat History When Editing
**Priority:** P0 | **Estimated:** 2-3 hours  
**Depends On:** P3.1B, P4.1 | **Blocks:** P4.6

**Location:** React files:
- `/src/hooks/useAIChat.js` (update)
- `/src/components/chat/ChatSection.jsx` (update)

**Requirements:**
- Detect if editing existing widget
- Load chat history from WordPress
- Populate UI with previous messages
- Pass context to AI for incremental changes

**Acceptance Criteria:**
- Chat history loads for existing widgets
- Messages display chronologically
- AI receives full context on new message
- Can continue conversation naturally
- Previous context used for generation

**API Endpoint Needed:**
```
GET /wp-json/widget-builder-ai/v1/widget/:id
Response: {
  "widget_id": 123,
  "title": "Button Widget",
  "chat_history": [...],
  "current_version": 2,
  "files": {...}
}
```

**PHP REST Endpoint:**
```php
<?php
// In class-rest-generate.php or new class-rest-widget.php

class REST_Widget {
    public function register() {
        register_rest_route('widget-builder-ai/v1', '/widget/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_widget'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function get_widget(\WP_REST_Request $request) {
        $widget_id = intval($request->get_param('id'));
        
        // Verify widget exists
        $post = get_post($widget_id);
        if (!$post || $post->post_type !== 'widget_builder_ai') {
            return new \WP_REST_Response(
                ['success' => false, 'error' => 'Widget not found'],
                404
            );
        }

        // Get chat history
        $chat_history = get_post_meta($widget_id, 'widget_builder_ai_chat_history', true) ?? [];
        
        // Get current version files
        $version_mgr = new Widget_Builder_AI_Version_Manager();
        $current = $version_mgr->get_current_version($widget_id);

        return new \WP_REST_Response([
            'success' => true,
            'widget_id' => $widget_id,
            'title' => $post->post_title,
            'chat_history' => $chat_history,
            'current_version' => get_post_meta($widget_id, 'widget_builder_ai_current_version', true) ?? 1,
            'files' => $current['files'] ?? [],
            'versions' => $version_mgr->get_versions_list($widget_id),
        ]);
    }

    public function check_permission() {
        return current_user_can('manage_options');
    }
}
```

**React Hook Update:**
```jsx
// /src/hooks/useAIChat.js

export const useAIChat = () => {
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);
  
  const { addMessage, updateFile } = useAppContext();

  const sendMessage = useCallback(
    async (message, context = {}, widgetId = null) => {
      setIsLoading(true);
      setError(null);

      try {
        addMessage({ role: 'user', content: message });

        const endpoint = widgetId
          ? `/wp-json/widget-builder-ai/v1/generate?widget_id=${widgetId}`
          : `/wp-json/widget-builder-ai/v1/generate`;

        const response = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.wpAIRest?.nonce || '',
          },
          body: JSON.stringify({
            message,
            widget_config: context.widgetConfig || {},
            model: context.selectedModel || 'openai',
            widget_id: widgetId,  // NEW: for updates
            previous_messages: context.chatMessages || [],
          }),
        });

        const data = await response.json();

        if (!data.success) {
          throw new Error(data.error || 'Generation failed');
        }

        addMessage({
          role: 'assistant',
          content: data.message,
        });

        if (data.files) {
          Object.entries(data.files).forEach(([name, content]) => {
            updateFile(name, content);
          });
        }

        return {
          success: true,
          widget_id: data.widget_id,
          version: data.version,
        };

      } catch (err) {
        const error_msg = err.message || 'Unknown error';
        setError(error_msg);
        addMessage({ role: 'assistant', content: `Error: ${error_msg}` });
        return { success: false, error: error_msg };

      } finally {
        setIsLoading(false);
      }
    },
    [addMessage, updateFile]
  );

  return { sendMessage, isLoading, error };
};
```

**React Component Update:**
```jsx
// /src/components/chat/ChatSection.jsx

const ChatSection = ({ widgetId = null }) => {
  const {
    chatMessages,
    addMessage,
    setChatMessages,
    widgetConfig,
    setWidgetConfig,
  } = useAppContext();

  const { sendMessage, isLoading } = useAIChat();

  // NEW: Load chat history if editing
  useEffect(() => {
    if (widgetId) {
      fetch(`/wp-json/widget-builder-ai/v1/widget/${widgetId}`)
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            // Populate chat with history
            setChatMessages(data.chat_history);
            // Update widget config
            setWidgetConfig({ title: data.title });
          }
        })
        .catch(console.error);
    }
  }, [widgetId]);

  const handleSend = async () => {
    if (!inputValue.trim()) return;

    const result = await sendMessage(inputValue, {
      widgetConfig,
      selectedModel,
      chatMessages,
    }, widgetId);  // Pass widgetId for update

    if (result.success) {
      setInputValue('');
      setActiveView('code');
      toast.success(
        widgetId 
          ? `Widget updated to version ${result.version}!`
          : `Widget #${result.widget_id} created!`
      );
    }
  };

  return (
    // ... JSX same as before ...
  );
};

export default ChatSection;
```

---

## TASK P4.6: Show Version History UI
**Priority:** P1 | **Estimated:** 3-4 hours  
**Depends On:** P3.1C, P4.5 | **Blocks:** P4.7

**Location:** `/src/components/editor/VersionHistory.jsx` (new)

**Requirements:**
- Display list of all widget versions
- Show timestamp, AI model, and summary
- Highlight current version
- Allow preview of any version
- Show file changes between versions

**Acceptance Criteria:**
- Version list displays chronologically (newest first)
- Current version visually indicated
- Can click to view version details
- Shows AI model and generation time
- File diff feature optional but nice
- Performance: loads instantly

**UI Component:**
```jsx
// /src/components/editor/VersionHistory.jsx

import React, { useState, useEffect } from 'react';
import { History, Eye, RotateCcw, Check } from 'lucide-react';
import './VersionHistory.scss';

const VersionHistory = ({ widgetId }) => {
  const [versions, setVersions] = useState([]);
  const [selectedVersion, setSelectedVersion] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!widgetId) return;

    fetch(`/wp-json/widget-builder-ai/v1/widget/${widgetId}`)
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          setVersions(data.versions);
          setSelectedVersion(data.versions[0]); // Current version
        }
      })
      .finally(() => setLoading(false));
  }, [widgetId]);

  if (loading) return <div>Loading versions...</div>;

  return (
    <div className="version-history">
      <div className="version-header">
        <History size={20} />
        <h3>Version History</h3>
      </div>

      <div className="version-list">
        {versions.map((version) => (
          <div
            key={version.version}
            className={`version-item ${
              version.is_current ? 'current' : ''
            }`}
            onClick={() => setSelectedVersion(version)}
          >
            <div className="version-info">
              <div className="version-number">
                Version {version.version}
                {version.is_current && <Check size={16} />}
              </div>
              <div className="version-date">{version.date}</div>
              <div className="version-model">{version.model}</div>
              <div className="version-summary">{version.summary}</div>
            </div>

            {!version.is_current && (
              <button
                className="version-action"
                onClick={(e) => {
                  e.stopPropagation();
                  // Trigger rollback (P4.7)
                }}
              >
                <RotateCcw size={16} />
              </button>
            )}
          </div>
        ))}
      </div>

      {selectedVersion && (
        <div className="version-details">
          <h4>Version {selectedVersion.version} Details</h4>
          <p>{selectedVersion.summary}</p>
          <p className="timestamp">
            Created: {selectedVersion.date}
          </p>
        </div>
      )}
    </div>
  );
};

export default VersionHistory;
```

**Integration into Layout:**
```jsx
// In CodePreviewSection.jsx or new sidebar
<VersionHistory widgetId={widgetConfig.id} />
```

---

## TASK P4.7: Rollback to Previous Version
**Priority:** P1 | **Estimated:** 2-3 hours  
**Depends On:** P3.1C, P4.6 | **Blocks:** None

**Location:** 
- Backend: `/wp-content/plugins/widget-builder-ai/includes/class-widget-builder-ai-version-manager.php`
- Frontend: `/src/components/editor/VersionHistory.jsx`

**Requirements:**
- Restore widget to previous version
- Update all files simultaneously
- Create new version entry for rollback
- Preserve chat history
- Confirmation before rollback

**Acceptance Criteria:**
- Can rollback to any previous version
- Files atomically updated
- New version created (shows as rollback)
- Confirmation dialog shown
- Toast notification on success
- Can't rollback to current version

**API Endpoint:**
```
POST /wp-json/widget-builder-ai/v1/widget/:id/rollback
Body: { "version": 2 }
Response: {
  "success": true,
  "message": "Rolled back to version 2",
  "new_version": 3,
  "files": {...}
}
```

**PHP Implementation:**
```php
<?php
// In init.php (register endpoint)
add_action('rest_api_init', function() {
    register_rest_route('widget-builder-ai/v1', '/widget/(?P<id>\d+)/rollback', [
        'methods' => 'POST',
        'callback' => [new Widget_Builder_AI_Version_Manager(), 'rollback_version'],
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'args' => [
            'version' => [
                'required' => true,
                'type' => 'integer',
                'validate_callback' => function($param) {
                    return $param > 0;
                },
            ],
        ],
    ]);
});

// In class-version-manager.php
public function rollback_version(\WP_REST_Request $request) {
    $widget_id = intval($request->get_route_params()['id']);
    $target_version = intval($request->get_param('version'));
    
    // Get target version
    $target = $this->get_version($widget_id, $target_version);
    if (!$target) {
        return new \WP_REST_Response(
            ['success' => false, 'error' => 'Version not found'],
            404
        );
    }
    
    // Create new version (rollback copy)
    $new_version = $this->create_version(
        $widget_id,
        $target['files'],
        $target['ai_model'],
        "Rolled back from version {$target_version}"
    );
    
    // Update files in filesystem
    $this->update_widget_files($widget_id, $target['files']);
    
    // Add to chat history
    add_post_meta_to_history($widget_id, [
        'timestamp' => time(),
        'role' => 'system',
        'content' => "Widget rolled back to version {$target_version}",
        'action' => 'rollback',
        'target_version' => $target_version,
        'new_version' => $new_version,
    ]);
    
    return new \WP_REST_Response([
        'success' => true,
        'message' => "Rolled back to version {$target_version}",
        'new_version' => $new_version,
        'files' => $target['files'],
    ]);
}

private function update_widget_files($widget_id, $files) {
    $writer = new Widget_Writer((object) [
        'css' => $files['style.css'] ?? '',
        'js' => $files['script.js'] ?? '',
        'markup' => '...',
        'tabs' => [],
    ], $widget_id);
    
    $wp_filesystem = Widget_File::get_wp_filesystem_pointer();
    $writer->start_backing($wp_filesystem);
    $writer->finish_backing($wp_filesystem);
}
```

**React Implementation:**
```jsx
// In VersionHistory.jsx

const handleRollback = async (version) => {
  if (!window.confirm(
    `Rollback to version ${version.version}? This will create a new version.`
  )) {
    return;
  }

  try {
    const response = await fetch(
      `/wp-json/widget-builder-ai/v1/widget/${widgetId}/rollback`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.wpAIRest?.nonce || '',
        },
        body: JSON.stringify({ version: version.version }),
      }
    );

    const data = await response.json();

    if (data.success) {
      toast.success(`Rolled back to version ${version.version}`);
      
      // Refresh version list
      window.location.reload();
      // Or better: fetch versions again and update state
    } else {
      toast.error(data.error || 'Rollback failed');
    }
  } catch (error) {
    toast.error('Rollback error: ' + error.message);
  }
};

return (
  <button
    className="rollback-btn"
    onClick={() => handleRollback(version)}
    title="Rollback to this version"
  >
    <RotateCcw size={16} /> Restore
  </button>
);
```

---

## SUMMARY: 5 New Tasks

| Task | What | Time | Complexity |
|------|------|------|-----------|
| **P3.1B** | Chat history storage | 2-3h | Low |
| **P3.1C** | File versioning system | 3-4h | Medium |
| **P4.5** | Load history for editing | 2-3h | Medium |
| **P4.6** | Version history UI | 3-4h | Medium |
| **P4.7** | Rollback functionality | 2-3h | Medium |
| **TOTAL** | All 5 | **12-17h** | |

**New Timeline:** 41-52 hours → 53-69 hours  
**New Duration:** 2.5-3.5 weeks

**Critical Path Addition:**
```
P3.1 → P3.1B → P3.1C → P4.5 → P4.6 → P4.7
      (2-3h)   (3-4h)   (2-3h)   (3-4h)  (2-3h)
```

These tasks are "safe" because:
✅ Modular (no cross-dependencies except sequential)
✅ Non-breaking (just adds features)
✅ Well-scoped (clear inputs/outputs)
✅ Reversible (versioning rollback)
✅ Scalable (handles unlimited versions with max 10 stored)
