import { ArrowLeft, Save } from 'lucide-react';
import { widgetApi } from '../../api/widgetApi';
import { APP_ACTIONS } from '../../reducers/appReducer.js';
import { useAppContext } from '../../store/AppContext';
import CodeEditor from './CodeEditor';
import './CodePreviewSection.scss';
import PreviewPane from './PreviewPane';

/**
 * Renders the code/preview workspace and widget save actions.
 *
 * @param {{activeView: string}} props Component props.
 * @return {JSX.Element} Code preview section.
 */
const CodePreviewSection = ( { activeView } ) => {
	const { widgetConfig, dispatch, files, currentWidgetId, selectedModel } =
		useAppContext();

	/**
	 * Returns to the widget list layout.
	 *
	 * @return {void}
	 */
	const handleBack = () => {
		dispatch( { type: APP_ACTIONS.SET_BUILDER_PAGE_OPEN, payload: false } );
		dispatch( {
			type: APP_ACTIONS.SET_WIDGET_SETUP_POPUP_OPEN,
			payload: false,
		} );
	};

	/**
	 * Saves current files to the server and updates local widget state.
	 *
	 * @return {Promise<void>} Promise that resolves after save flow completes.
	 */
	const handleSave = async () => {
		dispatch( { type: APP_ACTIONS.SET_AI_ERROR, payload: '' } );

		try {
			const response = await widgetApi.save( currentWidgetId, {
				widget_id: currentWidgetId || 0,
				widget_title: widgetConfig.title || 'Untitled Widget',
				files,
				model: selectedModel || 'manual-save',
				summary: `Manual save for ${
					widgetConfig.title || 'Untitled Widget'
				}`,
			} );

			dispatch( {
				type: APP_ACTIONS.SET_WIDGET_ID,
				payload: response.widget_id,
			} );
			dispatch( {
				type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
				payload: { title: response.title || widgetConfig.title },
			} );
			dispatch( {
				type: APP_ACTIONS.SET_PREVIEW_URL,
				payload: response.preview_url || '',
			} );

			dispatch( {
				type: APP_ACTIONS.ADD_CHAT_MESSAGE,
				payload: {
					role: 'assistant',
					content: `Saved successfully (version ${ response.version }).`,
				},
			} );
		} catch ( error ) {
			dispatch( {
				type: APP_ACTIONS.SET_AI_ERROR,
				payload: error.message || 'Save failed',
			} );
		}
	};

	return (
		<div className="code-preview-section">
			<div className="builder-header">
				<div className="header-title">
					<h1>{ widgetConfig.title || 'Untitled Widget' }</h1>
				</div>

				<div className="header-actions">
					<button className="header-button" onClick={ handleBack }>
						<ArrowLeft size={ 20 } />
						<span>Back</span>
					</button>

					<button
						className="header-button save-button"
						onClick={ handleSave }
					>
						<Save size={ 20 } />
						<span>Save</span>
					</button>
				</div>
			</div>

			<div className="code-preview-content">
				{ activeView === 'code' ? <CodeEditor /> : <PreviewPane /> }
			</div>
		</div>
	);
};

export default CodePreviewSection;
