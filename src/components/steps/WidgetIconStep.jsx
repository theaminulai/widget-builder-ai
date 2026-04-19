import { Search, X } from 'lucide-react';
import { useState } from 'react';
import { APP_ACTIONS } from '../../reducers/appReducer.js';
import { useAppContext } from '../../store/AppContext';
import { widgetIcons } from '../../utils/iconUtils';

/**
 * Renders the icon selection step with searchable icon list.
 *
 * @return {JSX.Element} Icon setup step.
 */
const WidgetIconStep = () => {
	const { widgetConfig, dispatch } = useAppContext();
	const [ searchQuery, setSearchQuery ] = useState( '' );
	const normalizedSearch = searchQuery.trim().toLowerCase();
	const selectedIcon = widgetConfig.icon || '';

	const filteredIcons = widgetIcons.filter( ( { searchText } ) =>
		searchText.includes( normalizedSearch )
	);

	return (
		<div>
			<div className="step-header">
				<h3>Widget Icon</h3>
				<p>Choose an icon that represents your widget.</p>
			</div>

			<div className="icon-search-container">
				<div className="icon-search-wrapper">
					<Search size={ 20 } className="search-icon" />
					<input
						type="text"
						className="icon-search-input"
						placeholder="Search icons..."
						value={ searchQuery }
						onChange={ ( e ) => setSearchQuery( e.target.value ) }
					/>
					{ searchQuery && (
						<button
							className="clear-search-btn"
							onClick={ () => setSearchQuery( '' ) }
							aria-label="Clear search"
						>
							<X size={ 16 } />
						</button>
					) }
				</div>
				<p className="search-results-count">
					{ filteredIcons.length } icon
					{ filteredIcons.length !== 1 ? 's' : '' } found
				</p>
			</div>

			<div className="icon-grid">
				{ filteredIcons.map( ( { id, label, path, width, height } ) => (
					<button
						key={ id }
						className={ `icon-item ${
							selectedIcon === id ||
							selectedIcon === id.replace( 'eicon-', '' )
								? 'selected'
								: ''
						}` }
						onClick={ () =>
							dispatch( {
								type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
								payload: { icon: id },
							} )
						}
						title={ id }
						aria-label={ id }
					>
						<svg
							width="32"
							height="32"
							viewBox={ `0 0 ${ width } ${ height }` }
							aria-hidden="true"
							fill="currentColor"
						>
							<path d={ path } />
						</svg>
						<span className="screen-reader-text">{ label }</span>
					</button>
				) ) }
			</div>

			{ filteredIcons.length === 0 && (
				<div className="no-icons-found">
					<p>No icons found matching "{ searchQuery }"</p>
				</div>
			) }
		</div>
	);
};

export default WidgetIconStep;
