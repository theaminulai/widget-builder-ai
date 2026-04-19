import { X } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';
import { useEffect, useState } from 'react';
import { widgetApi } from '../../api/widgetApi';
import { APP_ACTIONS, SETUP_STEPS } from '../../reducers/appReducer.js';
import { useAppContext } from '../../store/AppContext';
import StepContent from '../steps/StepContent';
import StepSidebar from '../steps/StepSidebar';
import './WidgetSetupPopup.scss';

/**
 * Setup wizard popup for initial widget metadata and library selection.
 *
 * @return {JSX.Element} Widget setup popup.
 */
const WidgetSetupPopup = () => {
	const {
		isWidgetSetupPopupOpen,
		dispatch,
		setupStep,
		widgetConfig,
		files,
		currentWidgetId,
		selectedModel,
	} =
		useAppContext();
	const [isSubmitting, setIsSubmitting] = useState(false);

	useEffect(() => {
		if (isWidgetSetupPopupOpen) {
			document.body.style.overflow = 'hidden';
		} else {
			document.body.style.overflow = 'unset';
		}
		return () => {
			document.body.style.overflow = 'unset';
		};
	}, [isWidgetSetupPopupOpen]);

	useEffect(() => {
		/**
		 * Closes the setup popup when Escape is pressed.
		 *
		 * @param {KeyboardEvent} e Keyboard event.
		 * @return {void}
		 */
		const handleEscape = (e) => {
			if (e.key === 'Escape' && isWidgetSetupPopupOpen) {
				dispatch({
					type: APP_ACTIONS.SET_WIDGET_SETUP_POPUP_OPEN,
					payload: false,
				});
			}
		};
		window.addEventListener('keydown', handleEscape);
		return () => window.removeEventListener('keydown', handleEscape);
	}, [isWidgetSetupPopupOpen]);

	/**
	 * Advances to the next setup step.
	 *
	 * @return {void}
	 */
	const handleNext = () => {
		if (setupStep < 4) {
			dispatch({
				type: APP_ACTIONS.SET_SETUP_STEP,
				payload: setupStep + 1,
			});
		}
	};

	/**
	 * Persists setup data and opens the builder workspace.
	 *
	 * @return {Promise<void>} Promise that resolves after save flow finishes.
	 */
	const handleContinue = async () => {
		if (isSubmitting) {
			return;
		}

		setIsSubmitting(true);;
		dispatch({ type: APP_ACTIONS.SET_AI_ERROR, payload: '' });

		try {
			const response = await widgetApi.save(currentWidgetId, {
				widget_id: currentWidgetId || 0,
				widget_title: widgetConfig.title || 'Untitled Widget',
				files,
				model: selectedModel || 'manual-save',
				summary: 'Initial save from setup wizard',
			});

			dispatch({
				type: APP_ACTIONS.SET_WIDGET_ID,
				payload: response.widget_id,
			});
			dispatch({
				type: APP_ACTIONS.SET_PREVIEW_URL,
				payload: response.preview_url || '',
			});
			dispatch({
				type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
				payload: { title: response.title || widgetConfig.title },
			});

			dispatch({ type: APP_ACTIONS.SET_WIDGET_SETUP_POPUP_OPEN, payload: false });
			dispatch({ type: APP_ACTIONS.SET_BUILDER_PAGE_OPEN, payload: true });
		} catch (error) {
			dispatch({
				type: APP_ACTIONS.SET_AI_ERROR,
				payload: error.message || 'Failed to save widget.',
			});
		} finally {
			setIsSubmitting(false);
		}
	};

	/**
	 * Validates required fields for the current setup step.
	 *
	 * @return {boolean} True when step requirements are satisfied.
	 */
	const isFormValid = () => {
		if (setupStep === SETUP_STEPS.WIDGET_TITLE) {
			return widgetConfig.title.trim() !== '';
		}
		if (setupStep === SETUP_STEPS.WIDGET_ICON) {
			return widgetConfig.icon !== null;
		}
		if (setupStep === SETUP_STEPS.WIDGET_CATEGORY) {
			return widgetConfig.category !== '';
		}
		return true;
	};

	/**
	 * Resolves the primary action button label for the current step.
	 *
	 * @return {string} Button label.
	 */
	const getButtonLabel = () => {
		if (setupStep === SETUP_STEPS.JS_LIBRARY) {
			return 'Continue';
		}
		return 'NEXT';
	};

	/**
	 * Executes the appropriate primary action for the current step.
	 *
	 * @return {void}
	 */
	const handleButtonClick = () => {
		if (setupStep === SETUP_STEPS.JS_LIBRARY) {
			handleContinue();
		} else {
			handleNext();
		}
	};

	return (
		<AnimatePresence>
			{isWidgetSetupPopupOpen && (
				<>
					<motion.div
						className="popup-overlay"
						initial={{ opacity: 0 }}
						animate={{ opacity: 1 }}
						exit={{ opacity: 0 }}
						onClick={() =>
							dispatch({
								type: APP_ACTIONS.SET_WIDGET_SETUP_POPUP_OPEN,
								payload: false,
							})
						}
					/>
					<motion.div
						className="popup-one"
						initial={{
							opacity: 0,
							scale: 0.95,
							x: '-50%',
							y: '-50%',
						}}
						animate={{
							opacity: 1,
							scale: 1,
							x: '-50%',
							y: '-50%',
						}}
						exit={{
							opacity: 0,
							scale: 0.95,
							x: '-50%',
							y: '-50%',
						}}
						transition={{ duration: 0.2 }}
					>
						<button
							className="popup-close"
							onClick={() =>
								dispatch({
									type: APP_ACTIONS.SET_WIDGET_SETUP_POPUP_OPEN,
									payload: false,
								})
							}
							aria-label="Close"
						>
							<X size={20} />
						</button>

						<div className="popup-one-content">
							<StepSidebar />
							<div className="popup-one-main">
								<StepContent />

								{ /* Bottom Footer */}
								<div className="popup-one-footer">
									<div className="step-progress">
										Step {setupStep} of 4
									</div>
									<button
										className="action-button"
										onClick={handleButtonClick}
										disabled={!isFormValid() || isSubmitting}
									>
										{isSubmitting && setupStep === 4
											? 'Saving...'
											: getButtonLabel()}
									</button>
								</div>
							</div>
						</div>
					</motion.div>
				</>
			)}
		</AnimatePresence>
	);
};

export default WidgetSetupPopup;
