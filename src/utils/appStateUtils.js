/**
 * Utility helpers for transforming and normalizing app state values.
 */

/**
 * Builds deterministic generated filenames from a widget title.
 *
 * @param {string} title Widget title.
 * @return {{php: string, css: string, js: string}} Generated file names.
 */
export const buildFileNamesFromTitle = ( title ) => {
	const slug = title
		.toString()
		.toLowerCase()
		.trim()
		.replace( /[^a-z0-9]+/g, '-' )
		.replace( /^-+|-+$/g, '' );

	return {
		php: `${ slug }.widget.php`,
		css: `${ slug }.style.css`,
		js: `${ slug }.script.js`,
	};
};

/**
 * Maps canonical API file keys to title-based generated file names.
 *
 * @param {Object<string, string>} files Raw file map.
 * @param {string} title Widget title.
 * @return {Object<string, string>} Renamed file map.
 */
export const mapCanonicalToNamedFiles = ( files, title ) => {
	const names = buildFileNamesFromTitle( title );
	const input = files || {};

	const phpContent =
		input[ names.php ] ??
		input[ 'widget.php' ] ??
		Object.entries( input ).find( ( [ key ] ) =>
			key.endsWith( '.php' )
		)?.[ 1 ] ??
		'';
	const cssContent =
		input[ names.css ] ??
		input[ 'style.css' ] ??
		Object.entries( input ).find( ( [ key ] ) =>
			key.endsWith( '.css' )
		)?.[ 1 ] ??
		'';
	const jsContent =
		input[ names.js ] ??
		input[ 'script.js' ] ??
		Object.entries( input ).find( ( [ key ] ) =>
			key.endsWith( '.js' )
		)?.[ 1 ] ??
		'';
	const mapped = {
		[ names.php ]: phpContent,
		[ names.css ]: cssContent,
	};

	if ( jsContent && jsContent.toString().trim() !== '' ) {
		mapped[ names.js ] = jsContent;
	}

	return mapped;
};

/**
 * Maps current file selections to the latest title-based filenames.
 *
 * @param {string} currentFile Current selected file key.
 * @param {string} title Widget title.
 * @return {string} Remapped file key.
 */
export const remapCurrentFile = ( currentFile, title ) => {
	const names = buildFileNamesFromTitle( title );
	if ( currentFile === 'widget.php' || currentFile.endsWith( '.php' ) ) {
		return names.php;
	}
	if ( currentFile === 'style.css' || currentFile.endsWith( '.css' ) ) {
		return names.css;
	}
	if ( currentFile === 'script.js' || currentFile.endsWith( '.js' ) ) {
		return names.js;
	}
	return names.php;
};

/**
 * Normalizes a widget ID value into a positive integer.
 *
 * @param {*} value Unknown widget ID value.
 * @return {number} Valid widget ID or 0.
 */
export const normalizeWidgetId = ( value ) => {
	const parsed = Number( value );
	return Number.isFinite( parsed ) && parsed > 0 ? parsed : 0;
};

/**
 * Normalizes timestamp-like input into a valid Date object.
 *
 * @param {Date|number|string} timestamp Timestamp input.
 * @return {Date} Normalized Date instance.
 */
export const normalizeTimestamp = ( timestamp ) => {
	if ( timestamp instanceof Date ) {
		return Number.isNaN( timestamp.getTime() ) ? new Date() : timestamp;
	}

	if ( typeof timestamp === 'number' ) {
		const milliseconds =
			timestamp < 1000000000000 ? timestamp * 1000 : timestamp;
		const parsedDate = new Date( milliseconds );
		return Number.isNaN( parsedDate.getTime() ) ? new Date() : parsedDate;
	}

	if ( typeof timestamp === 'string' ) {
		const numericValue = Number( timestamp );
		if ( ! Number.isNaN( numericValue ) && timestamp.trim() !== '' ) {
			return normalizeTimestamp( numericValue );
		}

		const parsedDate = new Date( timestamp );
		return Number.isNaN( parsedDate.getTime() ) ? new Date() : parsedDate;
	}

	return new Date();
};

/**
 * Normalizes a chat message object with fallback ID and timestamp.
 *
 * @param {Object} message Raw message object.
 * @param {number} [index=0] Message index fallback for ID generation.
 * @return {Object} Normalized message object.
 */
export const normalizeChatMessage = ( message, index = 0 ) => {
	const safeMessage = message || {};
	return {
		...safeMessage,
		id:
			safeMessage.id ||
			`msg-${ Date.now() }-${ index }-${ Math.random()
				.toString( 36 )
				.slice( 2, 8 ) }`,
		timestamp: normalizeTimestamp( safeMessage.timestamp ),
	};
};
