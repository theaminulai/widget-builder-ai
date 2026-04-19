import {
	buildFileNamesFromTitle,
	mapCanonicalToNamedFiles,
	normalizeChatMessage,
	normalizeWidgetId,
	remapCurrentFile,
} from '../utils/appStateUtils.js';

/**
 * Action identifiers used by the app reducer.
 *
 * @type {Object<string, string>}
 */
export const APP_ACTIONS = {
	SET_WIDGET_SETUP_POPUP_OPEN: 'SET_WIDGET_SETUP_POPUP_OPEN',
	SET_BUILDER_PAGE_OPEN: 'SET_BUILDER_PAGE_OPEN',
	SET_PROMPT_LIBRARY_OPEN: 'SET_PROMPT_LIBRARY_OPEN',
	SET_SETUP_STEP: 'SET_SETUP_STEP',
	SET_ACTIVE_VIEW: 'SET_ACTIVE_VIEW',
	ADD_CHAT_MESSAGE: 'ADD_CHAT_MESSAGE',
	UPDATE_WIDGET_CONFIG: 'UPDATE_WIDGET_CONFIG',
	SET_CURRENT_FILE: 'SET_CURRENT_FILE',
	UPDATE_FILE: 'UPDATE_FILE',
	SET_SELECTED_MODEL: 'SET_SELECTED_MODEL',
	SET_AI_ERROR: 'SET_AI_ERROR',
	SET_WIDGET_ID: 'SET_WIDGET_ID',
	HYDRATE_WIDGET_STATE: 'HYDRATE_WIDGET_STATE',
	SET_PREVIEW_URL: 'SET_PREVIEW_URL',
	SET_REQUEST_LATENCY: 'SET_REQUEST_LATENCY',
	SET_STATUS_MESSAGE: 'SET_STATUS_MESSAGE',
};

/**
 * Setup wizard step constants.
 *
 * @type {Object<string, number>}
 */
export const SETUP_STEPS = {
	WIDGET_TITLE: 1,
	WIDGET_ICON: 2,
	WIDGET_CATEGORY: 3,
	JS_LIBRARY: 4,
};

/**
 * Initial global app state.
 *
 * @type {Object}
 */
export const initialState = {
	isWidgetSetupPopupOpen: false,
	isBuilderPageOpen: false,
	isPromptLibraryOpen: false,
	setupStep: SETUP_STEPS.WIDGET_TITLE,
	activeView: 'code',
	chatMessages: [],
	widgetConfig: {
		title: '',
		description: '',
		icon: null,
		category: '',
		selectedLibrary: null,
		libraries: [],
	},
	currentFile: buildFileNamesFromTitle( '' ).php,
	files: mapCanonicalToNamedFiles( {}, '' ),
	selectedModel: 'gpt-4.1-mini',
	aiError: '',
	currentWidgetId: normalizeWidgetId( window.widgetBuilderAI?.currentPostId ),
	previewUrl: '',
	statusMessage: '', // For displaying transient status updates (e.g. "Thanking...", "Generating...", "Processing..."), not critical errors
};

/**
 * Handles all global state transitions for the widget builder app.
 *
 * @param {Object} state Current reducer state.
 * @param {{type: string, payload: *}} action Reducer action.
 * @return {Object} Next reducer state.
 */
export const appReducer = ( state, action ) => {
	switch ( action.type ) {
		case APP_ACTIONS.SET_WIDGET_SETUP_POPUP_OPEN:
			return {
				...state,
				isWidgetSetupPopupOpen: action.payload,
			};

		case APP_ACTIONS.SET_BUILDER_PAGE_OPEN:
			return {
				...state,
				isBuilderPageOpen: action.payload,
			};

		case APP_ACTIONS.SET_PROMPT_LIBRARY_OPEN:
			return {
				...state,
				isPromptLibraryOpen: action.payload,
			};

		case APP_ACTIONS.SET_SETUP_STEP:
			return {
				...state,
				setupStep: action.payload,
			};

		case APP_ACTIONS.SET_ACTIVE_VIEW:
			return {
				...state,
				activeView: action.payload,
			};

		case APP_ACTIONS.ADD_CHAT_MESSAGE:
			const appendedMessage = normalizeChatMessage(
				action.payload,
				state.chatMessages.length
			);
			return {
				...state,
				chatMessages: [ ...state.chatMessages, appendedMessage ],
			};

		case APP_ACTIONS.UPDATE_WIDGET_CONFIG:
			if (
				Object.prototype.hasOwnProperty.call(
					action.payload || {},
					'title'
				)
			) {
				const nextTitle =
					action.payload.title || state.widgetConfig.title;
				return {
					...state,
					widgetConfig: {
						...state.widgetConfig,
						...action.payload,
					},
					files: mapCanonicalToNamedFiles( state.files, nextTitle ),
					currentFile: remapCurrentFile(
						state.currentFile,
						nextTitle
					),
				};
			}

			return {
				...state,
				widgetConfig: {
					...state.widgetConfig,
					...action.payload,
				},
			};

		case APP_ACTIONS.SET_CURRENT_FILE:
			if (
				action.payload === 'widget.php' ||
				action.payload === 'style.css' ||
				action.payload === 'script.js'
			) {
				return {
					...state,
					currentFile: remapCurrentFile(
						action.payload,
						state.widgetConfig.title
					),
				};
			}

			return {
				...state,
				currentFile: action.payload,
			};

		case APP_ACTIONS.UPDATE_FILE:
			const names = buildFileNamesFromTitle( state.widgetConfig.title );
			let filename = action.payload.filename;
			const content = action.payload.content;

			if ( filename === 'widget.php' ) filename = names.php;
			if ( filename === 'style.css' ) filename = names.css;
			if ( filename === 'script.js' ) filename = names.js;

			if (
				filename === names.js &&
				( ! content || content.toString().trim() === '' )
			) {
				const nextFiles = { ...state.files };
				delete nextFiles[ filename ];
				return {
					...state,
					files: nextFiles,
					currentFile:
						state.currentFile === filename
							? nextFiles[ names.php ]
								? names.php
								: Object.keys( nextFiles )[ 0 ] || names.css
							: state.currentFile,
				};
			}

			return {
				...state,
				files: {
					...state.files,
					[ filename ]: content,
				},
			};

		case APP_ACTIONS.SET_SELECTED_MODEL:
			return {
				...state,
				selectedModel: action.payload,
			};

		case APP_ACTIONS.SET_AI_ERROR:
			return {
				...state,
				aiError: action.payload,
			};

		case APP_ACTIONS.SET_WIDGET_ID:
			return {
				...state,
				currentWidgetId: normalizeWidgetId( action.payload ),
			};

		case APP_ACTIONS.SET_PREVIEW_URL:
			return {
				...state,
				previewUrl: action.payload,
			};

		case APP_ACTIONS.SET_REQUEST_LATENCY:
			return {
				...state,
				requestLatencyMs: action.payload,
			};

		case APP_ACTIONS.SET_STATUS_MESSAGE:
			return {
				...state,
				statusMessage: action.payload,
			};

		case APP_ACTIONS.HYDRATE_WIDGET_STATE:
			const hydratedTitle =
				action.payload.title || state.widgetConfig.title;
			const hydratedMessages = Array.isArray(
				action.payload.chat_history
			)
				? action.payload.chat_history.map( ( message, index ) =>
						normalizeChatMessage( message, index )
				  )
				: state.chatMessages;
			return {
				...state,
				currentWidgetId:
					normalizeWidgetId( action.payload.widget_id ) ||
					state.currentWidgetId,
				widgetConfig: {
					...state.widgetConfig,
					title: hydratedTitle,
				},
				files: mapCanonicalToNamedFiles(
					action.payload.files || state.files,
					hydratedTitle
				),
				currentFile: remapCurrentFile(
					state.currentFile,
					hydratedTitle
				),
				chatMessages: hydratedMessages,
				previewUrl: action.payload.preview_url || state.previewUrl,
			};

		default:
			return state;
	}
};
