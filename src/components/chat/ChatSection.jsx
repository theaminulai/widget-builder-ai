import {
	Code2,
	CornerDownRight,
	Edit3,
	Eye,
	Plus,
	RotateCcw,
	Sidebar,
	Sparkles,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { widgetApi } from '../../api/widgetApi';
import { APP_ACTIONS } from '../../reducers/appReducer.js';
import { useAppContext } from '../../store/AppContext';
import ChatMessage from './ChatMessage';
import './ChatSection.scss';

/**
 * Quick prompt suggestions displayed in the empty chat state.
 *
 * @type {Array<{text: string, color: string}>}
 */
const suggestions = [
	{ text: 'Create custom Elementor widget', color: 'default' },
	{ text: 'Create custom code snippet', color: 'default' },
	{ text: 'Extend Elementor widget', color: 'default' },
];

/**
 * Renders the chat panel, model controls, and generation actions.
 *
 * @return {JSX.Element} Chat section UI.
 */
const ChatSection = () => {
	const {
		chatMessages,
		statusMessage,
		dispatch,
		activeView,
		selectedModel,
		widgetConfig,
		currentWidgetId,
	} = useAppContext();

	const [ inputValue, setInputValue ] = useState( '' );
	const messagesEndRef = useRef( null );

	/**
	 * Scrolls the chat list to the newest message.
	 *
	 * @return {void}
	 */
	const scrollToBottom = () => {
		messagesEndRef.current?.scrollIntoView( { behavior: 'smooth' } );
	};

	useEffect( () => {
		scrollToBottom();
	}, [ chatMessages ] );

	/**
	 * Sends the current prompt to the generate endpoint and applies response.
	 *
	 * @return {Promise<void>} Promise that resolves when send flow finishes.
	 */
	const handleSend = async () => {
		if ( ! inputValue.trim() ) return;
		const message = inputValue;
		const start = performance.now();

		dispatch( {
			type: APP_ACTIONS.ADD_CHAT_MESSAGE,
			payload: {
				role: 'user',
				content: message,
			},
		} );

		setInputValue( '' );
		// Reset textarea height
		const textarea = document.querySelector( '.chat-input' );
		if ( textarea ) textarea.style.height = 'auto';

		dispatch( { type: APP_ACTIONS.SET_AI_ERROR, payload: '' } );
		dispatch( {
			type: APP_ACTIONS.SET_STATUS_MESSAGE,
			payload: 'Tinkering...',
		} );

		try {
			const result = await widgetApi.generate( {
				message,
				model: selectedModel,
				widget_config: widgetConfig,
				widget_id: currentWidgetId || 0,
			} );

			dispatch( {
				type: APP_ACTIONS.ADD_CHAT_MESSAGE,
				payload: {
					role: 'assistant',
					content: result.summary || 'Generation completed.',
				},
			} );

			dispatch( {
				type: APP_ACTIONS.SET_WIDGET_ID,
				payload: result.widget_id,
			} );
			dispatch( {
				type: APP_ACTIONS.SET_PREVIEW_URL,
				payload: result.preview_url || '',
			} );
			dispatch( {
				type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
				payload: { title: result?.title || '' },
			} );

			if ( result.files ) {
				dispatch( {
					type: APP_ACTIONS.SET_STATUS_MESSAGE,
					payload: 'Applying generated...',
				} );
				Object.entries( result.files ).forEach(
					( [ filename, content ] ) => {
						dispatch( {
							type: APP_ACTIONS.UPDATE_FILE,
							payload: { filename, content: content || '' },
						} );
					}
				);
				dispatch( {
					type: APP_ACTIONS.SET_CURRENT_FILE,
					payload: 'widget.php',
				} );
			}
		} catch ( error ) {
			dispatch( {
				type: APP_ACTIONS.SET_AI_ERROR,
				payload:
					error.message || 'Generation failed, Please try again.',
			} );
			dispatch( {
				type: APP_ACTIONS.ADD_CHAT_MESSAGE,
				payload: {
					role: 'assistant',
					content: `Generation failed, Please try again: ${ error.message }`,
				},
			} );
		} finally {
			dispatch( {
				type: APP_ACTIONS.SET_REQUEST_LATENCY,
				payload: Math.round( performance.now() - start ),
			} );
			dispatch( { type: APP_ACTIONS.SET_STATUS_MESSAGE, payload: '' } );
		}
	};

	/**
	 * Triggers send on Enter while allowing Shift+Enter for multiline input.
	 *
	 * @param {KeyboardEvent} e Keyboard event.
	 * @return {void}
	 */
	const handleKeyDown = ( e ) => {
		if ( e.key === 'Enter' && ! e.shiftKey ) {
			e.preventDefault();
			handleSend();
		}
	};

	/**
	 * Applies a suggestion to the chat input.
	 *
	 * @param {{text: string}} suggestion Suggested prompt item.
	 * @return {void}
	 */
	const handleSuggestionClick = ( suggestion ) => {
		setInputValue( suggestion.text );
	};

	const showEmptyState = chatMessages.length === 0;

	return (
		<div className="chat-section">
			{ /* Top Toolbar */ }
			<div className="chat-toolbar">
				<div className="toolbar-left">
					<button className="toolbar-icon-button" title="Edit">
						<Edit3 size={ 20 } />
					</button>
					<button className="toolbar-icon-button" title="History">
						<RotateCcw size={ 20 } />
					</button>
					<button
						className={ `toolbar-icon-button ${
							activeView === 'code' ? 'active' : ''
						}` }
						onClick={ () =>
							dispatch( {
								type: APP_ACTIONS.SET_ACTIVE_VIEW,
								payload: 'code',
							} )
						}
						title="Code View"
					>
						<Code2 size={ 20 } />
					</button>
					<button
						className={ `toolbar-icon-button ${
							activeView === 'preview' ? 'active' : ''
						}` }
						onClick={ () =>
							dispatch( {
								type: APP_ACTIONS.SET_ACTIVE_VIEW,
								payload: 'preview',
							} )
						}
						title="Preview"
					>
						<Eye size={ 20 } />
					</button>
				</div>
				<div className="toolbar-right">
					<button
						className="toolbar-icon-button"
						title="Toggle Sidebar"
					>
						<Sidebar size={ 20 } />
					</button>
				</div>
			</div>

			{ /* Chat Content */ }
			<div className="chat-content">
				{ showEmptyState ? (
					<div className="chat-empty-state">
						<h1 className="chat-welcome-heading">
							What would you like to work on today?
						</h1>

						<div className="chat-suggestions">
							<p className="suggestions-label">
								Here are some things you can try
							</p>
							<div className="suggestions-list">
								{ suggestions.map( ( suggestion, index ) => (
									<button
										key={ index }
										className={ `suggestion-pill ${ suggestion.color }` }
										onClick={ () =>
											handleSuggestionClick( suggestion )
										}
									>
										<CornerDownRight size={ 18 } />
										<span>{ suggestion.text }</span>
									</button>
								) ) }
							</div>
						</div>
					</div>
				) : (
					<div className="chat-messages">
						{ chatMessages.map( ( message, index ) => (
							<ChatMessage
								key={ message.id || `chat-message-${ index }` }
								message={ message }
							/>
						) ) }
						{ statusMessage ? (
							<div
								className="chat-status"
								role="status"
								aria-live="polite"
							>
								<span className="chat-status-text">
									{ statusMessage }
								</span>
								<span
									className="chat-status-dots"
									aria-hidden="true"
								>
									<span className="dot" />
									<span className="dot" />
									<span className="dot" />
								</span>
							</div>
						) : null }
						<div ref={ messagesEndRef } />
					</div>
				) }
			</div>

			{ /* Bottom Input Area */ }
			<div className="chat-input-area">
				<div className="working-on-label">
					<span className="label-text">Working on:</span>
					<span className="label-value">Elementor #7</span>
				</div>

				<div className="chat-input-container">
					<textarea
						className="chat-input"
						placeholder="Ask Angie to..."
						value={ inputValue }
						rows={ 1 }
						onChange={ ( e ) => {
							setInputValue( e.target.value );
							e.target.style.height = 'auto';
							e.target.style.height = `${ e.target.scrollHeight }px`;
						} }
						onKeyDown={ handleKeyDown }
					/>

					<div className="input-toolbar">
						<div className="input-toolbar-left">
							<button
								className="input-icon-button"
								onClick={ () =>
									dispatch( {
										type: APP_ACTIONS.SET_PROMPT_LIBRARY_OPEN,
										payload: true,
									} )
								}
								title="Add Prompt"
							>
								<Plus size={ 20 } />
							</button>
							<button
								className="input-icon-button"
								title="AI Enhance"
							>
								<Sparkles size={ 20 } />
							</button>
							<select
								className="input-model-select"
								value={ selectedModel }
								onChange={ ( e ) =>
									dispatch( {
										type: APP_ACTIONS.SET_SELECTED_MODEL,
										payload: e.target.value,
									} )
								}
								title="Select AI model"
							>
								<option value="">Select a model</option>
								{ /* <option value="gemini-3-flash">Gemini 3 Flash</option> */ }
								<option value="gemini-2.5-flash">
									{ ' ' }
									Gemini 2.5 Flash
								</option>
								{ /* <option value="gemini-3.1-pro"> Gemini 3.1 Pro</option> */ }
								<option value="claude-opus-4-7">
									Claude 4.5 Opus{ ' ' }
								</option>
								<option value="claude-sonnet-4-6">
									Claude 4.6 Sonnet{ ' ' }
								</option>
								<option value="gpt-4o">GPT-4o</option>
								<option value="gpt-5-codex">GPT-5.3 Codex</option> 
							</select>
						</div>

						<button
							className="input-send-button"
							onClick={ handleSend }
							title="Send Message"
						>
							<svg
								width="20"
								height="20"
								viewBox="0 0 24 24"
								fill="none"
								stroke="currentColor"
								strokeWidth="2"
							>
								<path d="M12 19V5M5 12l7-7 7 7" />
							</svg>
						</button>
					</div>
				</div>

				<div className="chat-footer">
					<span className="footer-text">
						AI can make mistakes - verify results
					</span>
				</div>
			</div>
		</div>
	);
};

export default ChatSection;
