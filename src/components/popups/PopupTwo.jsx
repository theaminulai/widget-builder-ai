import { Check, Copy, Upload, X } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';
import { useEffect, useState } from 'react';
import { APP_ACTIONS } from '../../reducers/appReducer.js';
import { useAppContext } from '../../store/AppContext';
import './PopupTwo.scss';

/**
 * Prompt category labels used in the prompt library popup.
 *
 * @type {string[]}
 */
const categories = [
	'All prompts',
	'Elementor Widgets',
	'WordPress admin snippets',
	'Visual apps',
	'Interactive website snippets',
];

/**
 * Built-in prompt library data.
 *
 * @type {Array<{id: number, category: string, title: string, description: string}>}
 */
const prompts = [
	{
		id: 1,
		category: 'Elementor Widgets',
		title: 'Liquid Effect on Images',
		description:
			'Create a liquid distortion effect that follows mouse movement over images',
	},
	{
		id: 2,
		category: 'Elementor Widgets',
		title: 'Image Zoom on Scroll',
		description: 'Parallax image zoom effect triggered by scroll position',
	},
	{
		id: 3,
		category: 'WordPress admin snippets',
		title: 'Custom Dashboard Widget',
		description:
			'Add a custom analytics widget to WordPress admin dashboard',
	},
	{
		id: 4,
		category: 'Visual apps',
		title: 'Color Palette Generator',
		description: 'Generate harmonious color palettes from uploaded images',
	},
	{
		id: 5,
		category: 'Visual apps',
		title: 'SVG Pattern Creator',
		description: 'Interactive tool to create and customize SVG patterns',
	},
	{
		id: 6,
		category: 'Interactive website snippets',
		title: 'Animated Counter',
		description: 'Number counter with smooth animation on viewport enter',
	},
	{
		id: 7,
		category: 'Interactive website snippets',
		title: 'Magnetic Button Effect',
		description:
			'Button that follows cursor with magnetic attraction effect',
	},
	{
		id: 8,
		category: 'Elementor Widgets',
		title: '3D Card Hover',
		description: '3D tilt effect on cards based on mouse position',
	},
	{
		id: 9,
		category: 'Visual apps',
		title: 'Gradient Generator',
		description: 'Create and export CSS gradients with live preview',
	},
	{
		id: 10,
		category: 'WordPress admin snippets',
		title: 'Bulk Action Enhancer',
		description: 'Add custom bulk actions to post/page listings',
	},
	{
		id: 11,
		category: 'Interactive website snippets',
		title: 'Scroll Progress Bar',
		description: 'Animated progress bar showing reading progress',
	},
	{
		id: 12,
		category: 'Interactive website snippets',
		title: 'Text Reveal Animation',
		description: 'Smooth text reveal effect with staggered animation',
	},
];

/**
 * Renders the prompt library modal and insertion actions.
 *
 * @return {JSX.Element} Prompt library popup.
 */
const PopupTwo = () => {
	const { isPromptLibraryOpen, dispatch } = useAppContext();
	const [selectedCategory, setSelectedCategory] = useState('All prompts');
	const [copiedId, setCopiedId] = useState(null);

	useEffect(() => {
		if (isPromptLibraryOpen) {
			document.body.style.overflow = 'hidden';
		} else {
			document.body.style.overflow = 'unset';
		}
		return () => {
			document.body.style.overflow = 'unset';
		};
	}, [isPromptLibraryOpen]);

	useEffect(() => {
		/**
		 * Closes the prompt library when Escape is pressed.
		 *
		 * @param {KeyboardEvent} e Keyboard event.
		 * @return {void}
		 */
		const handleEscape = (e) => {
			if (e.key === 'Escape' && isPromptLibraryOpen) {
				dispatch({
					type: APP_ACTIONS.SET_PROMPT_LIBRARY_OPEN,
					payload: false,
				});
			}
		};
		window.addEventListener('keydown', handleEscape);
		return () => window.removeEventListener('keydown', handleEscape);
	}, [dispatch, isPromptLibraryOpen]);

	const filteredPrompts =
		selectedCategory === 'All prompts'
			? prompts
			: prompts.filter((p) => p.category === selectedCategory);

	/**
	 * Copies a prompt and appends it to chat as a user message.
	 *
	 * @param {{id: number, title: string, description: string}} prompt Prompt item.
	 * @return {void}
	 */
	const handleCopy = (prompt) => {
		navigator.clipboard.writeText(
			`${prompt.title}: ${prompt.description}`
		);
		setCopiedId(prompt.id);
		setTimeout(() => setCopiedId(null), 2000);

		dispatch({
			type: APP_ACTIONS.ADD_CHAT_MESSAGE,
			payload: {
				role: 'user',
				content: `${prompt.title}: ${prompt.description}`,
			},
		});

		setTimeout(() => {
			dispatch({
				type: APP_ACTIONS.ADD_CHAT_MESSAGE,
				payload: {
					role: 'assistant',
					content: `I'll help you create "${prompt.title}". Let me start building this for you...`,
				},
			});
		}, 1000);
	};

	/**
	 * Placeholder handler for prompt library JSON import.
	 *
	 * @return {void}
	 */
	const handleImportJSON = () => {
		console.log('Import JSON clicked');
	};

	return (
		<AnimatePresence>
			{isPromptLibraryOpen && (
				<>
					<motion.div
						className="popup-overlay"
						initial={{ opacity: 0 }}
						animate={{ opacity: 1 }}
						exit={{ opacity: 0 }}
						onClick={() =>
							dispatch({
								type: APP_ACTIONS.SET_PROMPT_LIBRARY_OPEN,
								payload: false,
							})
						}
					/>
					<motion.div
						className="popup-two"
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
						<div className="popup-two-header">
							<h2>Prompt Library</h2>
							<div className="header-actions">
								<button
									className="import-button"
									onClick={handleImportJSON}
								>
									<Upload size={18} />
									<span>Import JSON</span>
								</button>
								<button
									className="close-button"
									onClick={() =>
										dispatch({
											type: APP_ACTIONS.SET_PROMPT_LIBRARY_OPEN,
											payload: false,
										})
									}
								>
									<X size={20} />
								</button>
							</div>
						</div>

						<div className="popup-two-content">
							<div className="category-sidebar">
								<h3>Categories</h3>
								<div className="category-list">
									{categories.map((category) => (
										<button
											key={category}
											className={`category-item ${selectedCategory === category
													? 'active'
													: ''
												}`}
											onClick={() =>
												setSelectedCategory(category)
											}
										>
											{category}
										</button>
									))}
								</div>
							</div>

							<div className="prompts-main">
								<div className="prompts-header">
									<h3>{selectedCategory}</h3>
									<p>
										{filteredPrompts.length} prompts
										available
									</p>
								</div>

								<AnimatePresence mode="wait">
									<motion.div
										key={selectedCategory}
										className="prompts-grid"
										initial={{ opacity: 0 }}
										animate={{ opacity: 1 }}
										exit={{ opacity: 0 }}
										transition={{
											duration: 0.15,
											ease: 'easeInOut',
										}}
									>
										{filteredPrompts.map((prompt) => (
											<div
												key={prompt.id}
												className="prompt-card"
											>
												<div className="prompt-content">
													<h4>{prompt.title}</h4>
													<p>
														{prompt.description}
													</p>
												</div>
												<button
													className="copy-button"
													onClick={() =>
														handleCopy(prompt)
													}
												>
													{copiedId === prompt.id ? (
														<Check size={18} />
													) : (
														<Copy size={18} />
													)}
													<span>
														{copiedId === prompt.id
															? 'Copied!'
															: 'Copy'}
													</span>
												</button>
											</div>
										))}
									</motion.div>
								</AnimatePresence>
							</div>
						</div>
					</motion.div>
				</>
			)}
		</AnimatePresence>
	);
};

export default PopupTwo;
