import { APP_ACTIONS } from '../../reducers/appReducer.js';
import { useAppContext } from '../../store/AppContext';

/**
 * Category options available in the widget setup wizard.
 *
 * @type {Array<{id: string, name: string, description: string}>}
 */
const categories = [
	{
		id: 'elementor',
		name: 'Elementor Widgets',
		description: 'Custom widgets for Elementor page builder',
	},
	{
		id: 'wordpress-admin',
		name: 'WordPress Admin',
		description: 'Admin panel customizations and tools',
	},
	{
		id: 'visual-apps',
		name: 'Visual Apps',
		description: 'Interactive visual applications',
	},
	{
		id: 'interactive-snippets',
		name: 'Interactive Snippets',
		description: 'Reusable interactive website components',
	},
	{
		id: 'data-visualization',
		name: 'Data Visualization',
		description: 'Charts, graphs, and data display widgets',
	},
	{
		id: 'forms',
		name: 'Forms & Input',
		description: 'Custom form elements and validators',
	},
];

/**
 * Renders the widget category selection step.
 *
 * @return {JSX.Element} Category setup step.
 */
const WidgetCategoryStep = () => {
	const { widgetConfig, dispatch } = useAppContext();

	return (
		<div>
			<div className="step-header">
				<h3>Widget Category</h3>
				<p>Select the category that best fits your widget.</p>
			</div>

			<div className="category-grid">
				{categories.map((category) => (
					<button
						key={category.id}
						className={`category-card ${widgetConfig.category === category.id
							? 'selected'
							: ''
							}`}
						onClick={() =>
							dispatch({
								type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
								payload: { category: category.id },
							})
						}
					>
						<h4>{category.name}</h4>
						<p>{category.description}</p>
					</button>
				))}
			</div>
		</div>
	);
};

export default WidgetCategoryStep;
