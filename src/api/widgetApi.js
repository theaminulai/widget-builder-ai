import { request } from '../utils/request.js';

/**
 * REST client for Widget Builder AI endpoints.
 */
export const widgetApi = {
	/**
	 * Generates widget code from a prompt.
	 *
	 * @param {Object} payload Generation payload.
	 * @return {Promise<Object>} Generation response.
	 */
	generate: ( payload ) =>
		request( 'generate', {
			method: 'POST',
			body: JSON.stringify( payload ),
		} ),
	/**
	 * Saves widget files and creates a version.
	 *
	 * @param {number} widgetId Widget ID.
	 * @param {Object} payload Save payload.
	 * @return {Promise<Object>} Save response.
	 */
	save: ( widgetId, payload ) =>
		request( widgetId ? `save/${ widgetId }` : 'save', {
			method: 'POST',
			body: JSON.stringify( payload ),
		} ),
	/**
	 * Loads complete widget payload by ID.
	 *
	 * @param {number} widgetId Widget ID.
	 * @return {Promise<Object>} Widget payload.
	 */
	loadWidget: ( widgetId ) =>
		request( `widget/${ widgetId }`, { method: 'GET' } ),
	/**
	 * Rolls back a widget to a previous version.
	 *
	 * @param {number} widgetId Widget ID.
	 * @param {number} version Version number.
	 * @return {Promise<Object>} Rollback response.
	 */
	rollback: ( widgetId, version ) =>
		request( `widget/${ widgetId }/rollback`, {
			method: 'POST',
			body: JSON.stringify( { version } ),
		} ),
};
