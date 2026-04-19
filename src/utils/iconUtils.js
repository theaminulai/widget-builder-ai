import eicons from '../assets/icons/eicons.json';

/**
 * Builds normalized icon options from eicons JSON.
 *
 * @return {Array<{id: string, label: string, searchText: string, path: string, width: number, height: number}>}
 */
export const buildWidgetIcons = () =>
	Object.entries( eicons )
		.filter( ( [ , iconData ] ) => Boolean( iconData?.path ) )
		.map( ( [ key, iconData ] ) => {
			const id = `eicon-${ key }`;
			const label = key.replace( /-/g, ' ' );
			const width = Number( iconData?.width ) || 1000;
			const height = Number( iconData?.height ) || 1000;

			return {
				id,
				label,
				path: iconData.path,
				width,
				height,
				searchText: `${ key } ${ id } ${ label }`.toLowerCase(),
			};
		} );

/**
 * Cached icon options for widget icon step.
 *
 * @type {Array<{id: string, label: string, searchText: string, path: string, width: number, height: number}>}
 */
export const widgetIcons = buildWidgetIcons();
