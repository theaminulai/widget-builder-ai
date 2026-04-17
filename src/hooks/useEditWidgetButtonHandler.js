import { useEffect } from 'react';
import { APP_ACTIONS } from '../reducers/appReducer.js';
import { getWidgetIdFromUrl } from '../utils/url.js';

/**
 * Intercepts edit row action clicks to open widgets in the builder page.
 *
 * @param {{dispatch: Function}} params Hook params.
 * @return {void}
 */
export default function useEditWidgetButtonHandler({ dispatch }) {
	useEffect(() => {
		const wrapNode = document.querySelector('.wrap');
		if (!wrapNode) {
			return;
		}

		/**
		 * Intercepts list-table edit clicks to open the builder directly.
		 *
		 * @param {MouseEvent} event Click event.
		 * @return {void}
		 */
		const handleClick = (event) => {
			const editLink = event.target.closest('.row-actions .edit a');
			if (!editLink) {
				return;
			}

			const widgetId = getWidgetIdFromUrl(editLink.href);
			if (!widgetId) {
				return;
			}

			event.preventDefault();
			dispatch({ type: APP_ACTIONS.SET_WIDGET_SETUP_POPUP_OPEN, payload: false });
			dispatch({ type: APP_ACTIONS.SET_WIDGET_ID, payload: widgetId });
			dispatch({ type: APP_ACTIONS.SET_BUILDER_PAGE_OPEN, payload: true });
		};

		wrapNode.addEventListener('click', handleClick);
		return () => wrapNode.removeEventListener('click', handleClick);
	}, [dispatch]);
}
