import { AnimatePresence, motion } from 'motion/react';
import { SETUP_STEPS } from '../../reducers/appReducer';
import { useAppContext } from '../../store/AppContext';
import JSLibraryStep from './JSLibraryStep';
import './StepContent.scss';
import WidgetCategoryStep from './WidgetCategoryStep';
import WidgetIconStep from './WidgetIconStep';
import WidgetTitleStep from './WidgetTitleStep';

/**
 * Renders the currently selected setup step content.
 *
 * @return {JSX.Element} Step content container.
 */
const StepContent = () => {
	const { setupStep } = useAppContext();

	/**
	 * Resolves the component for the active setup step.
	 *
	 * @return {JSX.Element} Step component.
	 */
	const renderStep = () => {
		switch ( setupStep ) {
			case SETUP_STEPS.WIDGET_TITLE:
				return <WidgetTitleStep />;
			case SETUP_STEPS.WIDGET_ICON:
				return <WidgetIconStep />;
			case SETUP_STEPS.WIDGET_CATEGORY:
				return <WidgetCategoryStep />;
			case SETUP_STEPS.JS_LIBRARY:
				return <JSLibraryStep />;
			default:
				return <WidgetTitleStep />;
		}
	};

	return (
		<div className="step-content">
			<AnimatePresence mode="wait">
				<motion.div
					key={ setupStep }
					initial={ { opacity: 0 } }
					animate={ { opacity: 1 } }
					exit={ { opacity: 0 } }
					transition={ {
						duration: 0.15,
						ease: 'easeInOut',
					} }
					className="step-content-inner"
				>
					{ renderStep() }
				</motion.div>
			</AnimatePresence>
		</div>
	);
};

export default StepContent;
