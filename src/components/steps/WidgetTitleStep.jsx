import { APP_ACTIONS } from '../../reducers/appReducer.js';
import { useAppContext } from '../../store/AppContext';

/**
 * Renders widget title and description inputs.
 *
 * @return {JSX.Element} Title setup step.
 */
const WidgetTitleStep = () => {
	const { widgetConfig, dispatch } = useAppContext();

	return (
		<div>
			<div className="step-header">
				<h3>Widget Title</h3>
				<p>
					Give your widget a name and description to help identify its
					purpose.
				</p>
			</div>

			<div className="form-group">
				<label htmlFor="widget-name">Widget Name *</label>
				<input
					id="widget-name"
					type="text"
					placeholder="Enter widget name..."
					value={widgetConfig.title}
					onChange={(e) =>
						dispatch({
							type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
							payload: { title: e.target.value },
						})
					}
				/>
			</div>

			<div className="form-group">
				<label htmlFor="widget-description">Description</label>
				<textarea
					id="widget-description"
					placeholder="Describe what your widget does..."
					value={widgetConfig.description}
					onChange={(e) =>
						dispatch({
							type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
							payload: { description: e.target.value },
						})
					}
				/>
			</div>
		</div>
	);
};

export default WidgetTitleStep;
