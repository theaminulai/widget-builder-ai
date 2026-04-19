/**
 * Shared request helpers for the Widget Builder AI REST API.
 */

/**
 * Gets the REST API base URL from localized data with fallback.
 *
 * @return {string} API base URL.
 */
const getBase = () => {
	if ( window.widgetBuilderAI?.restUrl ) {
		return window.widgetBuilderAI.restUrl;
	}
	return '/wp-json/widget-builder-ai/v1/';
};

/**
 * Builds default REST request headers.
 *
 * @return {Object<string, string>} Request header map.
 */
const getHeaders = () => ( {
	'Content-Type': 'application/json',
	'X-WP-Nonce': window.widgetBuilderAI?.nonce || '',
} );

/**
 * Sends a request to the plugin REST API and normalizes errors.
 *
 * @param {string} path Relative API route path.
 * @param {Object} [options={}] Fetch options.
 * @return {Promise<Object>} Parsed response payload.
 */
export const request = async ( path, options = {} ) => {
	const response = await fetch( `${ getBase() }${ path }`, {
		...options,
		headers: {
			...getHeaders(),
			...( options.headers || {} ),
		},
	} );

	const data = await response.json();
	if ( ! response.ok || data?.success === false ) {
		throw new Error( data?.error || 'Request failed' );
	}

	return data;
};
