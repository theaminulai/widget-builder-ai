import { AnimatePresence, motion } from 'motion/react';
import { useAppContext } from '../../store/AppContext';
import ChatSection from '../chat/ChatSection';
import CodePreviewSection from '../editor/CodePreviewSection';
import PopupTwo from '../popups/PopupTwo';
import './BuilderPage.scss';

/**
 * Renders the main builder workspace with chat and code/preview panels.
 *
 * @return {JSX.Element} Builder page UI.
 */
const BuilderPage = () => {
	const { isBuilderPageOpen, activeView } = useAppContext();

	return (
		<AnimatePresence>
			{ isBuilderPageOpen && (
				<motion.div
					className="builder-page"
					initial={ { opacity: 0 } }
					animate={ { opacity: 1 } }
					exit={ { opacity: 0 } }
				>
					<PopupTwo />
					<div className="builder-content">
						<ChatSection />
						<CodePreviewSection activeView={ activeView } />
					</div>
				</motion.div>
			) }
		</AnimatePresence>
	);
};

export default BuilderPage;
