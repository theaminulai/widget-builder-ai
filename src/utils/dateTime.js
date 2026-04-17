/**
 * Date/time formatting helpers.
 */

/**
 * Formats a timestamp-like value into a localized time string.
 *
 * @param {Date|number|string} value Input timestamp value.
 * @return {string} Formatted time label.
 */
export const formatTimestamp = (value) => {
	if (value instanceof Date) {
		return Number.isNaN(value.getTime())
			? '--:--'
			: value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
	}

	if (typeof value === 'number') {
		const milliseconds = value < 1000000000000 ? value * 1000 : value;
		return formatTimestamp(new Date(milliseconds));
	}

	if (typeof value === 'string') {
		const numericValue = Number(value);
		if (!Number.isNaN(numericValue) && value.trim() !== '') {
			return formatTimestamp(numericValue);
		}
		return formatTimestamp(new Date(value));
	}

	return '--:--';
};
