import { APP_ACTIONS } from '../../reducers/appReducer.js';
import { useAppContext } from '../../store/AppContext';

/**
 * Category options available in the widget setup wizard.
 *
 * @type {Array<{id: string, name: string, description: string}>}
 */
const categories = [
	{
		id: 'basic',
		name: 'Basic',
		description: 'Basic widgets for Elementor page builder',
	},
	{
		id: 'general',
		name: 'General',
		description: 'General purpose widgets for any use case',
	},
	{
		id: 'link-in-bio',
		name: 'Link In Bio',
		description: 'Link in bio widgets for social media integration',
	},
	{
		id: 'theme-elements',
		name: 'Theme Elements',
		description:
			"Widgets that enhance the theme's appearance and functionality",
	},
	{
		id: 'woocommerce-elements',
		name: 'Woo Elements',
		description:
			'Widgets specifically designed for WooCommerce integration',
	},
	{
		id: 'site',
		name: 'Site Elements',
		description:
			'Widgets that enhance overall site functionality and user experience',
	},
	{
		id: 'single',
		name: 'Single',
		description: 'Widgets designed for single use on a page.',
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
				{ categories.map( ( category ) => (
					<button
						key={ category.id }
						className={ `category-card ${
							widgetConfig.category === category.id
								? 'selected'
								: ''
						}` }
						onClick={ () =>
							dispatch( {
								type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
								payload: { category: category.id },
							} )
						}
					>
						<h4>{ category.name }</h4>
						<p>{ category.description }</p>
					</button>
				) ) }
			</div>
		</div>
	);
};

export default WidgetCategoryStep;
