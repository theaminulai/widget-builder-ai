import { useEffect } from 'react';

/**
 * Toggles wp-admin layout classes while the builder view is open.
 *
 * @param {boolean} isBuilderPageOpen Whether the builder is visible.
 * @return {void}
 */
export default function useBuilderPageLayout(isBuilderPageOpen) {
	useEffect(() => {
		const wrapNode = document.querySelector('.wrap');
		if (!wrapNode) return;

		const screenMetaLinks =
			wrapNode.parentElement?.querySelector('#screen-meta-links');

		wrapNode.classList.toggle(
			'widget-builder-ai-open',
			isBuilderPageOpen
		);
		screenMetaLinks?.classList.toggle('hidden', isBuilderPageOpen);

		return () => {
			wrapNode.classList.remove('widget-builder-ai-open');
			screenMetaLinks?.classList.remove('hidden');
		};
	}, [isBuilderPageOpen]);
}
