# Developer Quick Start Guide

## 🚀 5-Minute Setup

### Prerequisites
- Node.js 18+
- Package manager (npm/pnpm)

### Installation
```bash
# Already installed packages:
# - @monaco-editor/react (code editor)
# - sass (SCSS support)
# - motion (animations)
# - lucide-react (icons)
# - All dependencies ready!
```

### Run the App
```bash
npm run build  # Build for production
```

---

## 📂 Project Structure (Quick Reference)

```
src/app/
├── store/
│   └── AppContext.jsx          # Global state
├── components/
│   ├── layout/                 # Landing page
│   ├── popups/                 # Modal dialogs
│   ├── steps/                  # Wizard steps
│   ├── builder/                # Main builder UI
│   ├── chat/                   # Chat interface
│   └── editor/                 # Code editor
├── data/
│   └── demoData.js            # Mock data
└── App.tsx                     # Root component
```

---

## 🎯 Key Files to Know

### State Management
**`/src/app/store/AppContext.jsx`**
- All global state lives here
- Use `useAppContext()` hook to access
- Handles: popups, steps, chat, files, config

### Main Components
1. **Landing**: `/src/app/components/layout/LandingPage.jsx`
2. **Popup One**: `/src/app/components/popups/PopupOne.jsx`
3. **Popup Two**: `/src/app/components/popups/PopupTwo.jsx`
4. **Builder**: `/src/app/components/builder/BuilderPage.jsx`

---

## 🔧 Common Tasks

### Add a New Step

1. Create step component:
```jsx
// /src/app/components/steps/Step5NewFeature.jsx
import React from 'react';
import { useAppContext } from '../../store/AppContext';

const Step5NewFeature = () => {
  const { widgetConfig, updateWidgetConfig } = useAppContext();
  
  return (
    <div>
      <div className="step-header">
        <h3>New Feature</h3>
        <p>Description here</p>
      </div>
      {/* Your content */}
    </div>
  );
};

export default Step5NewFeature;
```

2. Add to `StepContent.jsx`:
```jsx
case 5:
  return <Step5NewFeature />;
```

3. Add to `StepSidebar.jsx`:
```jsx
{ id: 5, label: 'New Feature', icon: YourIcon },
```

### Add a New Prompt

Edit `/src/app/components/popups/PopupTwo.jsx`:
```jsx
const prompts = [
  // ... existing prompts
  {
    id: 13,
    category: 'Your Category',
    title: 'Your Prompt Title',
    description: 'Description here',
  },
];
```

### Add a New Category

Edit `/src/app/components/steps/Step3WidgetCategory.jsx`:
```jsx
const categories = [
  // ... existing
  {
    id: 'new-category',
    name: 'New Category Name',
    description: 'Category description',
  },
];
```

### Add a New File to Editor

Edit `/src/app/store/AppContext.jsx`:
```jsx
const [files, setFiles] = useState({
  // ... existing files
  'newfile.js': `// Your default content`,
});
```

---

## 🎨 Styling Quick Tips

### Component SCSS Pattern
```scss
.component-name {
  // Base styles
  
  &:hover {
    // Hover state
  }
  
  &.active {
    // Active state
  }
}

@media (prefers-color-scheme: dark) {
  .component-name {
    // Dark mode overrides
  }
}
```

### Using Animations
```jsx
import { motion } from 'motion/react';

<motion.div
  initial={{ opacity: 0, y: 20 }}
  animate={{ opacity: 1, y: 0 }}
  exit={{ opacity: 0 }}
  transition={{ duration: 0.3 }}
>
  Content
</motion.div>
```

---

## 🔌 State Management Patterns

### Reading State
```jsx
const { chatMessages, widgetConfig } = useAppContext();
```

### Updating State
```jsx
const { updateWidgetConfig, addMessage } = useAppContext();

// Update config
updateWidgetConfig({ title: 'New Title' });

// Add message
addMessage({
  role: 'user',
  content: 'Hello!',
});
```

### Modal Control
```jsx
const { setIsPopupOneOpen } = useAppContext();

setIsPopupOneOpen(true);  // Open
setIsPopupOneOpen(false); // Close
```

---

## 🎯 Component Communication

### Flow: Landing → Wizard → Builder
```jsx
// Landing
setIsPopupOneOpen(true);

// Wizard (on continue)
setIsPopupOneOpen(false);
setIsBuilderPageOpen(true);

// Builder (back button)
setIsBuilderPageOpen(false);
setIsPopupOneOpen(true);
```

### Flow: Chat → Prompt Library
```jsx
// Chat (+ button)
setIsPromptLibraryOpen(true);

// Prompt Library (copy)
addMessage({ role: 'user', content: promptText });
setIsPromptLibraryOpen(false); // optional
```

---

## 📝 Code Editor Integration

### Basic Usage
```jsx
import Editor from '@monaco-editor/react';

<Editor
  height="100%"
  language="javascript"
  value={code}
  onChange={(value) => updateFile(filename, value)}
  theme="vs-dark"
  options={{
    minimap: { enabled: false },
    fontSize: 14,
    lineNumbers: 'on',
  }}
/>
```

### Language Detection
```javascript
const getLanguage = (filename) => {
  if (filename.endsWith('.json')) return 'json';
  if (filename.endsWith('.js')) return 'javascript';
  if (filename.endsWith('.ts')) return 'typescript';
  // ... etc
};
```

---

## 🎭 Animation Patterns

### Modal Overlay
```jsx
<AnimatePresence>
  {isOpen && (
    <>
      <motion.div
        className="overlay"
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        exit={{ opacity: 0 }}
      />
      <motion.div
        className="modal"
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        exit={{ opacity: 0, scale: 0.95 }}
      >
        {/* Content */}
      </motion.div>
    </>
  )}
</AnimatePresence>
```

### Button Hover
```jsx
<motion.button
  whileHover={{ scale: 1.05 }}
  whileTap={{ scale: 0.95 }}
>
  Click Me
</motion.button>
```

### List Stagger
```jsx
<motion.div
  initial={{ opacity: 0, y: 20 }}
  animate={{ opacity: 1, y: 0 }}
  transition={{ duration: 0.3, delay: index * 0.1 }}
>
  Item {index}
</motion.div>
```

---

## 🐛 Common Gotchas

### 1. Context Must Be Inside Provider
```jsx
// ❌ Wrong
function App() {
  return <MyComponent />;
}

// ✅ Right
function App() {
  return (
    <AppProvider>
      <MyComponent />
    </AppProvider>
  );
}
```

### 2. Modal Background Scroll
Already handled! Each modal includes:
```jsx
useEffect(() => {
  if (isOpen) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = 'unset';
  }
  return () => {
    document.body.style.overflow = 'unset';
  };
}, [isOpen]);
```

### 3. AnimatePresence Needs Key
```jsx
<AnimatePresence mode="wait">
  <motion.div key={activeStep}>
    {/* Content */}
  </motion.div>
</AnimatePresence>
```

---

## 📦 Mock Data Location

All demo data: `/src/app/data/demoData.js`
- Sample widgets
- Prompts
- Categories
- Chat history
- Models
- Files

---

## 🎨 Icons Reference

Using Lucide React:
```jsx
import { IconName } from 'lucide-react';

<IconName size={24} color="#2563eb" />
```

70+ icons available - check `/src/app/components/steps/Step2WidgetIcon.jsx`

---

## 🔍 Testing Tips

### Test Modal Flow
1. Click "Create Widget"
2. Fill Step 1 (required)
3. Select icon (Step 2)
4. Choose category (Step 3)
5. Click Continue
6. Check builder opens
7. Click Back
8. Verify wizard reopens

### Test Chat
1. Type message
2. Press Enter
3. Check mock response
4. Click "+"
5. Copy prompt
6. Verify auto-insert

### Test Code Editor
1. Click file in explorer
2. Edit code
3. Switch files
4. Return to first file
5. Verify changes saved

---

## 🚀 Performance Tips

1. **Memoize expensive computations**
```jsx
const filteredItems = useMemo(
  () => items.filter(condition),
  [items]
);
```

2. **Lazy load components**
```jsx
const HeavyComponent = lazy(() => import('./HeavyComponent'));
```

3. **Virtualize long lists** (future)
```jsx
// Use react-window or react-virtualized
```

---

## 📚 Useful Snippets

### Keyboard Shortcut Handler
```jsx
useEffect(() => {
  const handleKeyDown = (e) => {
    if (e.key === 'Escape') {
      closeModal();
    }
  };
  window.addEventListener('keydown', handleKeyDown);
  return () => window.removeEventListener('keydown', handleKeyDown);
}, []);
```

### Click Outside Handler
```jsx
const ref = useRef();

useEffect(() => {
  const handleClickOutside = (e) => {
    if (ref.current && !ref.current.contains(e.target)) {
      closeModal();
    }
  };
  document.addEventListener('mousedown', handleClickOutside);
  return () => document.removeEventListener('mousedown', handleClickOutside);
}, []);

return <div ref={ref}>{/* Content */}</div>;
```

### Debounce Input
```jsx
const [value, setValue] = useState('');
const [debouncedValue, setDebouncedValue] = useState('');

useEffect(() => {
  const timer = setTimeout(() => {
    setDebouncedValue(value);
  }, 500);
  return () => clearTimeout(timer);
}, [value]);
```

---

## 🎓 Learning Resources

### Official Docs
- React: https://react.dev
- Motion: https://motion.dev
- Monaco Editor: https://microsoft.github.io/monaco-editor/
- SCSS: https://sass-lang.com
- Lucide: https://lucide.dev

### Project-Specific
- `/README.md` - Overview
- `/USAGE_GUIDE.md` - End-user guide
- `/FEATURES.md` - Complete feature list
- Component files - Inline comments

---

## 🤝 Contributing

### Before You Code
1. Read existing code patterns
2. Match existing style
3. Test in both light and dark mode
4. Check mobile responsiveness
5. Add comments for complex logic

### Naming Conventions
- Components: PascalCase
- Functions: camelCase
- Constants: UPPER_SNAKE_CASE
- CSS classes: kebab-case
- Files: PascalCase for components

---

## ⚡ Quick Commands

```bash
# Development
npm run dev          # Not configured (Figma Make handles this)

# Production
npm run build        # Build for production

# Linting (if configured)
npm run lint         # Check code quality
```

---

## 🎉 You're Ready!

Key takeaways:
1. State lives in `AppContext.jsx`
2. Components are modular and reusable
3. SCSS for styling, Motion for animations
4. Mock data in `/data/demoData.js`
5. Follow existing patterns

**Happy coding! 🚀**
