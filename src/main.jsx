/**
 * Bootstraps the Widget Builder AI React application inside wp-admin.
 */
import { createRoot } from 'react-dom/client';
import App from './App.jsx';
import { AppProvider } from './store/AppContext';

const mountNode = document.querySelector('.wrap');
if (mountNode) {
	let appRoot = document.getElementById('widget-builder-ai-root');

	if (!appRoot) {
		appRoot = document.createElement('div');
		appRoot.id = 'widget-builder-ai-root';
		appRoot.className = 'app-container';
		mountNode.appendChild(appRoot);
	}

	createRoot(appRoot).render(
		<AppProvider>
			<App />
		</AppProvider>
	);
}
