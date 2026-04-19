import { createContext, useContext, useReducer } from 'react';
import {
	appReducer,
	initialState,
	SETUP_STEPS,
} from '../reducers/appReducer.js';

const AppContext = createContext( null );

/**
 * Returns the widget builder app context.
 *
 * @return {Object} App context value.
 */
export const useAppContext = () => {
	const context = useContext( AppContext );
	if ( ! context ) {
		throw new Error( 'useAppContext must be used within AppProvider' );
	}
	return context;
};

/**
 * Provides global app state and actions to child components.
 *
 * @param {{children: React.ReactNode}} props Component props.
 * @return {JSX.Element} Context provider wrapper.
 */
export const AppProvider = ( { children } ) => {
	const [ state, dispatch ] = useReducer( appReducer, initialState );

	/**
	 * Determines whether a setup step currently satisfies completion rules.
	 *
	 * @param {number} step Step identifier.
	 * @return {boolean} True when the step is complete.
	 */
	const isStepComplete = ( step ) => {
		switch ( step ) {
			case SETUP_STEPS.WIDGET_TITLE:
				return state.widgetConfig.title.trim() !== '';
			case SETUP_STEPS.WIDGET_ICON:
				return state.widgetConfig.icon !== null;
			case SETUP_STEPS.WIDGET_CATEGORY:
				return state.widgetConfig.category !== '';
			case SETUP_STEPS.JS_LIBRARY:
				return true; // Optional step
			default:
				return false;
		}
	};

	const value = {
		...state,
		dispatch,
		isStepComplete,
	};

	return (
		<AppContext.Provider value={ value }>{ children }</AppContext.Provider>
	);
};

export default AppProvider;
