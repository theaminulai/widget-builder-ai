/**
 * Bootstraps the Widget Builder AI React application inside wp-admin.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { Fragment, StrictMode } from 'react';
import App from './App.jsx';
import { AppProvider } from './store/AppContext';

const initAdminApp = () => {
	const mountNode = document.querySelector( '.wrap' );
	const isDevelopment = window?.widgetBuilderAI?.isDevelopment;
	const AppWrapper = Boolean( isDevelopment ) ? StrictMode : Fragment;

	if ( ! mountNode ) {
		console.error( 'Mount node not found. Expected .wrap in the DOM.' );
		return;
	}

	let appRoot = document.getElementById( 'widget-builder-ai-root' );

	if ( ! appRoot ) {
		appRoot = document.createElement( 'div' );
		appRoot.id = 'widget-builder-ai-root';
		appRoot.className = 'app-container';
		mountNode.appendChild( appRoot );
	}

	const root = createRoot( appRoot );

	root.render(
		<AppWrapper>
			<AppProvider>
				<App />
			</AppProvider>
		</AppWrapper>
	);
};

domReady( () => {
	initAdminApp();
} );
