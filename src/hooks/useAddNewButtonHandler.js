import { useEffect } from 'react';
import { APP_ACTIONS, SETUP_STEPS } from '../reducers/appReducer.js';

/**
 * Attaches the Add New button handler to open the setup popup.
 *
 * @param {{dispatch: Function}} params Hook params.
 * @return {void}
 */
export default function useAddNewButtonHandler( { dispatch } ) {
	useEffect( () => {
		const addNewButton = document.querySelector( '.page-title-action' );
		if ( ! addNewButton ) return;

		/**
		 * Opens the setup wizard instead of the default post creation flow.
		 *
		 * @param {MouseEvent} e Click event.
		 * @return {void}
		 */
		const handleClick = ( e ) => {
			e.preventDefault();
			dispatch( {
				type: APP_ACTIONS.SET_BUILDER_PAGE_OPEN,
				payload: false,
			} );
			dispatch( {
				type: APP_ACTIONS.SET_SETUP_STEP,
				payload: SETUP_STEPS.WIDGET_TITLE,
			} );
			dispatch( {
				type: APP_ACTIONS.SET_WIDGET_SETUP_POPUP_OPEN,
				payload: true,
			} );
		};

		addNewButton.addEventListener( 'click', handleClick );
		return () => addNewButton.removeEventListener( 'click', handleClick );
	}, [ dispatch ] );
}
