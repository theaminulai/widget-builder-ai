/**
 * URL utilities for widget editor actions.
 */

/**
 * Reads the widget post ID from a WordPress admin URL.
 *
 * @param {string} url URL to parse.
 * @return {number} Parsed post ID or 0 when unavailable.
 */
export const getWidgetIdFromUrl = (url) => {
	try {
		const parsed = new URL(url, window.location.origin);
		return Number(parsed.searchParams.get('post')) || 0;
	} catch (error) {
		return 0;
	}
};
