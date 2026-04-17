import { useEffect } from 'react';
import { widgetApi } from './api/widgetApi';
import './App.scss';
import BuilderPage from './components/builder/BuilderPage';
import WidgetSetupPopup from './components/popups/WidgetSetupPopup';
import useAddNewButtonHandler from './hooks/useAddNewButtonHandler';
import useBuilderPageLayout from './hooks/useBuilderPageLayout';
import useEditWidgetButtonHandler from './hooks/useEditWidgetButtonHandler';
import { APP_ACTIONS } from './reducers/appReducer.js';
import { useAppContext } from './store/AppContext';
import { Fragment } from 'react';
/**
 * Renders the main application shell and hydrates existing widget state.
 *
 * @return {JSX.Element} Application UI.
 */
export default function App() {
	const { dispatch, isBuilderPageOpen, currentWidgetId } = useAppContext();

	useAddNewButtonHandler({ dispatch });
	useEditWidgetButtonHandler({ dispatch });
	useBuilderPageLayout(isBuilderPageOpen);

	useEffect(() => {
		const widgetId = Number(currentWidgetId) || 0;
		if (widgetId <= 0) {
			return;
		}

		let mounted = true;

		widgetApi
			.loadWidget(widgetId)
			.then((payload) => {
				if (!mounted) {
					return;
				}

				dispatch({
					type: APP_ACTIONS.HYDRATE_WIDGET_STATE,
					payload,
				});
				dispatch({
					type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
					payload: { title: payload.title || '' },
				});
			})
			.catch(() => {
				// Ignore load failures on first run.
			});

		return () => {
			mounted = false;
		};
	}, [currentWidgetId]);

	return (
		<Fragment>
			<WidgetSetupPopup />
			<BuilderPage />
		</Fragment>
	);
}
