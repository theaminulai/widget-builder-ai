import { Check, FolderKanban, Image, Library, Type } from 'lucide-react';
import { motion } from 'motion/react';
import { APP_ACTIONS, SETUP_STEPS } from '../../reducers/appReducer.js';
import { useAppContext } from '../../store/AppContext';
import './StepSidebar.scss';

/**
 * Ordered setup step metadata for sidebar rendering.
 *
 * @type {Array<{id: number, label: string, icon: Function, stepNumber: number}>}
 */
const stepsList = [
	{
		id: SETUP_STEPS.WIDGET_TITLE,
		label: 'Widget Title',
		icon: Type,
		stepNumber: 1,
	},
	{
		id: SETUP_STEPS.WIDGET_ICON,
		label: 'Widget Icon',
		icon: Image,
		stepNumber: 2,
	},
	{
		id: SETUP_STEPS.WIDGET_CATEGORY,
		label: 'Widget Category',
		icon: FolderKanban,
		stepNumber: 3,
	},
	{
		id: SETUP_STEPS.JS_LIBRARY,
		label: 'JS Library',
		icon: Library,
		stepNumber: 4,
	},
];

/**
 * Renders setup progress sidebar and handles step navigation.
 *
 * @return {JSX.Element} Step sidebar component.
 */
const StepSidebar = () => {
	const { setupStep, isStepComplete, dispatch } = useAppContext();

	/**
	 * Checks whether a step can be visited based on prior completion.
	 *
	 * @param {number} stepId Step identifier.
	 * @return {boolean} True when the step is accessible.
	 */
	const isStepAccessible = ( stepId ) => {
		// Step 1 is always accessible
		if ( stepId === SETUP_STEPS.WIDGET_TITLE ) return true;

		// For other steps, all previous steps must be complete
		const currentStepInfo = stepsList.find( ( s ) => s.id === stepId );
		if ( ! currentStepInfo ) return false;

		for ( let i = 0; i < stepsList.length; i++ ) {
			const s = stepsList[ i ];
			if ( s.stepNumber < currentStepInfo.stepNumber ) {
				if ( ! isStepComplete( s.id ) ) {
					return false;
				}
			}
		}
		return true;
	};

	/**
	 * Navigates to a setup step when it is accessible.
	 *
	 * @param {number} stepId Step identifier.
	 * @return {void}
	 */
	const handleStepClick = ( stepId ) => {
		if ( isStepAccessible( stepId ) ) {
			dispatch( { type: APP_ACTIONS.SET_SETUP_STEP, payload: stepId } );
		}
	};

	return (
		<div className="step-sidebar">
			<div className="step-sidebar-header">
				<h2>Create Widget</h2>
				<p>Complete the steps to build your widget</p>
			</div>

			<div className="step-list">
				{ stepsList.map( ( step ) => {
					const Icon = step.icon;
					const isActive = setupStep === step.id;
					const isCompleted =
						isStepComplete( step.id ) && setupStep > step.id;
					const isAccessible = isStepAccessible( step.id );

					return (
						<motion.button
							key={ step.id }
							className={ `step-item ${
								isActive ? 'active' : ''
							} ${ isCompleted ? 'completed' : '' } ${
								! isAccessible ? 'disabled' : ''
							}` }
							onClick={ () => handleStepClick( step.id ) }
							disabled={ ! isAccessible }
							whileHover={ isAccessible ? { x: 4 } : {} }
							whileTap={ isAccessible ? { scale: 0.98 } : {} }
						>
							<div className="step-number">
								{ isCompleted ? (
									<Check size={ 20 } />
								) : (
									<Icon size={ 20 } />
								) }
							</div>
							<div className="step-label">
								<span className="step-title">
									{ step.label }
								</span>
								<span className="step-subtitle">
									Step { step.stepNumber }
								</span>
							</div>
						</motion.button>
					);
				} ) }
			</div>
		</div>
	);
};

export default StepSidebar;
