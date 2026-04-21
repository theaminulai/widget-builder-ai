import { Check, Copy, Upload, X } from 'lucide-react';
import { AnimatePresence, motion } from 'motion/react';
import { useEffect, useState } from 'react';
import { widgetApi } from '../../api/widgetApi';
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
	'Content & Display',
	'Navigation & Layout',
	'Marketing & Conversion',
	'Interactive & Effects',
	'Media & Portfolio',
];

/**
 * Built-in prompt library data.
 *
 * @type {Array<{id: number, category: string, title: string, description: string, libraries?: Array<{url: string, type: string}>}>}
 */
const prompts = [
	//Content & Display

	{
		id: 1,
		category: 'Content & Display',
		title: 'Testimonial Slider',
		libraries: [
			{
				url: 'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.js',
				type: 'js',
			},
			{
				url: 'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.css',
				type: 'css',
			},
		],
		description:
			'I need a testimonial widget to show client reviews on my website. Each testimonial should have a client photo, name, position, company, star rating, and review text. Use Swiper JS for the slider with autoplay, navigation arrows, and pagination dots. The card design should be clean and modern with a subtle shadow.',
	},
	{
		id: 2,
		category: 'Content & Display',
		title: 'Team Members Grid',
		description:
			'I need a Team Member Card widget for my website. It should display a staff profile with a photo or auto-generated initials avatar, name, job title, bio, and an optional department tag. Include social links for LinkedIn, Twitter/X, GitHub, and Email as optional URL fields. The card should have a centered layout with the avatar on top and text centered, a circle avatar shape, subtle border style, and a lift hover effect. Include a switcher to show or hide the department tag. Make it fully responsive.',
	},
	{
		id: 3,
		category: 'Content & Display',
		title: 'FAQ Accordion',
		description:
			'I need a FAQ widget where each question expands and collapses smoothly when clicked to reveal the answer. Only one item should be open at a time. Include a plus/minus icon that animates on toggle. Support rich text in the answer area and give full typography and color controls.',
	},
	{
		id: 4,
		category: 'Content & Display',
		title: 'Animated Statistics Counter',
		description:
			'I need a statistics counter widget that displays key metrics like total clients, completed projects, years of experience, and team members. Each stat should have an icon, a large animated number that counts up when scrolled into view, and a label below it. Make the layout a responsive row of stat boxes.',
	},
	{
		id: 5,
		category: 'Content & Display',
		title: 'Timeline',
		description:
			'I need a vertical timeline widget to showcase a company history, project milestones, or process steps. Each entry should have a date or year, a title, a description, and an optional icon. Alternate items left and right on desktop and stack them on mobile. Add a connecting line with dot markers between entries.',
	},
	{
		id: 6,
		category: 'Content & Display',
		title: 'Progress Bars',
		description:
			'I need a skills or progress bar widget that displays multiple items, each with a label and a percentage value. The bars should animate and fill from left to right when they scroll into view. Include controls for bar color, height, border radius, label typography, and percentage display position.',
	},
	{
		id: 7,
		category: 'Content & Display',
		title: 'Icon Box Grid',
		description:
			'I need an icon box widget that displays a grid of feature cards. Each card should have an Elementor icon, a heading, and a short description. Support hover effects like background color change or icon animation. Give full controls for icon size, color, card background, padding, and border radius.',
	},
	{
		id: 8,
		category: 'Content & Display',
		title: 'Flip Card',
		description:
			'I need a flip card widget where the card rotates 3D on hover to reveal a back side. The front side should show an image and title. The back side should show a description and an optional button. Support both horizontal and vertical flip directions with smooth CSS transition.',
	},

	//Navigation & Layout

	{
		id: 9,
		category: 'Navigation & Layout',
		title: 'Tabs Content Widget',
		description:
			'I need a tabs widget where clicking each tab label switches the visible content panel below it. Support both horizontal and vertical tab orientations. Include an active indicator line or background highlight on the selected tab. Allow rich content inside each panel and give full style controls for tab labels and panels.',
	},
	{
		id: 12,
		category: 'Navigation & Layout',
		title: 'Off-Canvas Sidebar Panel',
		description:
			'I need an off-canvas panel widget with a trigger button that slides in a sidebar from the left or right when clicked. The panel should overlay the page with a dark backdrop and close when clicking outside or on a close button. The panel content area should accept any widget. Animate the slide smoothly.',
	},

	// Marketing & Conversion

	{
		id: 13,
		category: 'Marketing & Conversion',
		title: 'Pricing Table',
		description:
			'I need a pricing table widget that displays multiple plans side by side. Each plan should have a plan name, price with billing period, a highlighted feature list with check icons, and a call to action button. Support a featured or recommended badge on one plan. Add a monthly/yearly toggle switcher that updates prices dynamically.',
	},
	{
		id: 14,
		category: 'Marketing & Conversion',
		title: 'Call to Action Banner',
		description:
			'I need a call to action widget with a bold headline, a subheadline, a short description, and up to two buttons side by side. Support a full background image with overlay color or a gradient background. Center-align the content and make the layout stack cleanly on mobile.',
	},
	{
		id: 15,
		category: 'Marketing & Conversion',
		title: 'Countdown Timer',
		description:
			'I need a countdown timer widget that counts down to a specific date and time. Display days, hours, minutes, and seconds in styled boxes with labels. When the timer reaches zero, show a custom message or redirect to a URL. Give full controls for box style, typography, colors, and separator character.',
	},
	{
		id: 17,
		category: 'Marketing & Conversion',
		title: 'Social Proof Popup',
		description:
			'I need a social proof notification widget that shows small popup toasts in the bottom corner of the page, cycling through messages like recent purchases or sign-ups. Each toast should have an avatar, a name, a message, a location, and a time ago label. Animate them in and out with a slide-up effect and configurable display interval.',
	},
	{
		id: 18,
		category: 'Marketing & Conversion',
		title: 'Review Stars Summary',
		description:
			'I need a review summary widget that displays an overall star rating with a score like 4.8 out of 5, a total review count, and a breakdown bar chart showing how many reviews gave each star level from 5 to 1. Give full style controls for stars, bars, and typography.',
	},

	//Interactive & Effects

	{
		id: 19,
		category: 'Interactive & Effects',
		title: 'Before & After Image Comparison',
		description:
			'I need a before and after image comparison widget with a vertical draggable divider that the visitor can slide left or right to reveal the two images. Show labels on each side like "Before" and "After". Make the drag handle visually prominent with an icon. Support touch dragging on mobile.',
	},
	{
		id: 21,
		category: 'Interactive & Effects',
		title: 'Magnetic Hover Button',
		description:
			'I need a button widget with a magnetic hover effect where the button follows the cursor slightly as the mouse moves near it, creating an attraction effect. The button should have a smooth spring-like animation and return to its original position when the cursor leaves. Give full controls for button style, size, color, and label.',
	},
	{
		id: 23,
		category: 'Interactive & Effects',
		title: 'Text Typing Animation',
		description:
			'I need a text widget that types out multiple phrases one character at a time, pauses, then deletes and types the next phrase in a loop — like a typewriter effect. Give controls for the list of phrases, typing speed, delete speed, pause duration, cursor style, and all typography options.',
	},
	{
		id: 24,
		category: 'Interactive & Effects',
		title: 'Scroll Progress Bar',
		description:
			'I need a reading progress bar widget that displays a thin bar fixed at the top of the page that fills from left to right as the visitor scrolls down the page. Give controls for bar height, color or gradient, z-index, and an option to show or hide the percentage as a label.',
	},

	//  Media & Portfolio
	{
		id: 25,
		category: 'Media & Portfolio',
		title: 'Portfolio Filter Grid',
		description:
			'I need a portfolio widget with a category filter toolbar above a masonry or grid layout of project cards. Each card should show a thumbnail, project title, category tag, and a hover overlay with a view button. Clicking a filter button should animate the grid to show only matching items. Make it fully responsive.',
	},
	{
		id: 26,
		category: 'Media & Portfolio',
		title: 'Logo Carousel',
		libraries: [
			{
				url: 'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.js',
				type: 'js',
			},
			{
				url: 'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.css',
				type: 'css',
			},
		],
		description:
			'I need a logo showcase widget that displays client or partner logos in a continuously auto-scrolling marquee strip using Swiper JS with loop mode. Logos should be grayscale by default and turn to full color on hover. Give controls for logo size, scroll speed, spacing, and number of visible logos per breakpoint.',
	},
	{
		id: 27,
		category: 'Media & Portfolio',
		title: 'Image Lightbox Gallery',
		description:
			'I need an image gallery widget that displays photos in a responsive grid. Clicking any image should open it in a fullscreen lightbox overlay with prev/next navigation arrows and a close button. Support keyboard navigation with arrow keys and Escape to close. Give controls for grid columns, gap, image border radius, and overlay color.',
	},
	{
		id: 29,
		category: 'Media & Portfolio',
		title: 'Product Showcase Slider',
		libraries: [
			{
				url: 'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.js',
				type: 'js',
			},
			{
				url: 'https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.css',
				type: 'css',
			},
		],
		description:
			'I need a product showcase widget using Swiper JS that displays product cards in a slider. Each card should show a product image, name, short description, price, and an add to cart or view button. Support thumbnail navigation below the main slider. Give full style controls for card layout, typography, button, and colors.',
	},
	{
		id: 30,
		category: 'Media & Portfolio',
		title: 'Instagram Feed Grid',
		description:
			'I need an Instagram-style photo feed widget that displays images in a clean square grid layout. Each image should show a hover overlay with a like count, comment count, and a link icon. Clicking the image opens it in a lightbox. Give controls for grid columns per breakpoint, gap size, overlay color, and image border radius.',
	},
];

/**
 * Renders the prompt library modal and insertion actions.
 *
 * @return {JSX.Element} Prompt library popup.
 */
const PopupTwo = () => {
	const {
		isPromptLibraryOpen,
		dispatch,
		currentWidgetId,
		widgetConfig,
		files,
		selectedModel,
	} = useAppContext();
	const [ selectedCategory, setSelectedCategory ] = useState( 'All prompts' );
	const [ copiedId, setCopiedId ] = useState( null );

	useEffect( () => {
		if ( isPromptLibraryOpen ) {
			document.body.style.overflow = 'hidden';
		} else {
			document.body.style.overflow = 'unset';
		}
		return () => {
			document.body.style.overflow = 'unset';
		};
	}, [ isPromptLibraryOpen ] );

	useEffect( () => {
		/**
		 * Closes the prompt library when Escape is pressed.
		 *
		 * @param {KeyboardEvent} e Keyboard event.
		 * @return {void}
		 */
		const handleEscape = ( e ) => {
			if ( e.key === 'Escape' && isPromptLibraryOpen ) {
				dispatch( {
					type: APP_ACTIONS.SET_PROMPT_LIBRARY_OPEN,
					payload: false,
				} );
			}
		};
		window.addEventListener( 'keydown', handleEscape );
		return () => window.removeEventListener( 'keydown', handleEscape );
	}, [ isPromptLibraryOpen ] );

	const filteredPrompts =
		selectedCategory === 'All prompts'
			? prompts
			: prompts.filter( ( p ) => p.category === selectedCategory );

	/**
	 * Copies a prompt and persists prompt libraries in widget configuration.
	 *
	 * @param {{id: number, title: string, description: string, libraries?: Array<{url: string, type: string}>}} prompt Prompt item.
	 * @return {Promise<void>}
	 */
	const handleCopy = async ( prompt ) => {
		const promptText = `${ prompt.title }: ${ prompt.description }`;
		const promptLibraries = Array.isArray( prompt.libraries )
			? prompt.libraries
			: [];
		const hasPromptLibraries = promptLibraries.length > 0;

		try {
			await navigator.clipboard.writeText( promptText );
		} catch ( error ) {
			dispatch( {
				type: APP_ACTIONS.SET_AI_ERROR,
				payload: error?.message || 'Failed to copy prompt text.',
			} );
		}

		setCopiedId( prompt.id );
		setTimeout( () => setCopiedId( null ), 1000 );

		const nextWidgetConfig = {
			...widgetConfig,
			libraries: promptLibraries,
			selectedLibrary: prompt.title,
		};

		dispatch( {
			type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
			payload: {
				libraries: promptLibraries,
				selectedLibrary: prompt.title,
			},
		} );

		if ( ! hasPromptLibraries ) {
			return;
		}

		try {
			const response = await widgetApi.save( currentWidgetId, {
				widget_id: currentWidgetId || 0,
				widget_title: widgetConfig.title || 'Untitled Widget',
				files,
				model: selectedModel || 'manual-save',
				summary: `Updated prompt libraries from "${ prompt.title }"`,
				widget_config: nextWidgetConfig,
			} );

			dispatch( {
				type: APP_ACTIONS.SET_WIDGET_ID,
				payload: response.widget_id,
			} );

			dispatch( {
				type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
				payload: response.widget_config || {
					libraries: promptLibraries,
					selectedLibrary: prompt.title,
				},
			} );
		} catch ( error ) {
			dispatch( {
				type: APP_ACTIONS.SET_AI_ERROR,
				payload: error?.message || 'Failed to save prompt libraries.',
			} );
		}
	};

	/**
	 * Placeholder handler for prompt library JSON import.
	 *
	 * @return {void}
	 */
	const handleImportJSON = () => {
		console.log( 'Import JSON clicked' );
	};

	return (
		<AnimatePresence>
			{ isPromptLibraryOpen && (
				<>
					<motion.div
						className="popup-overlay"
						initial={ { opacity: 0 } }
						animate={ { opacity: 1 } }
						exit={ { opacity: 0 } }
						onClick={ () =>
							dispatch( {
								type: APP_ACTIONS.SET_PROMPT_LIBRARY_OPEN,
								payload: false,
							} )
						}
					/>
					<motion.div
						className="popup-two"
						initial={ {
							opacity: 0,
							scale: 0.95,
							x: '-50%',
							y: '-50%',
						} }
						animate={ {
							opacity: 1,
							scale: 1,
							x: '-50%',
							y: '-50%',
						} }
						exit={ {
							opacity: 0,
							scale: 0.95,
							x: '-50%',
							y: '-50%',
						} }
						transition={ { duration: 0.2 } }
					>
						<div className="popup-two-header">
							<h2>Prompt Library</h2>
							<div className="header-actions">
								<button
									className="import-button"
									onClick={ handleImportJSON }
								>
									<Upload size={ 18 } />
									<span>Import JSON</span>
								</button>
								<button
									className="close-button"
									onClick={ () =>
										dispatch( {
											type: APP_ACTIONS.SET_PROMPT_LIBRARY_OPEN,
											payload: false,
										} )
									}
								>
									<X size={ 20 } />
								</button>
							</div>
						</div>

						<div className="popup-two-content">
							<div className="category-sidebar">
								<h3>Categories</h3>
								<div className="category-list">
									{ categories.map( ( category ) => (
										<button
											key={ category }
											className={ `category-item ${
												selectedCategory === category
													? 'active'
													: ''
											}` }
											onClick={ () =>
												setSelectedCategory( category )
											}
										>
											{ category }
										</button>
									) ) }
								</div>
							</div>

							<div className="prompts-main">
								<div className="prompts-header">
									<h3>{ selectedCategory }</h3>
									<p>
										{ filteredPrompts.length } prompts
										available
									</p>
								</div>

								<AnimatePresence mode="wait">
									<motion.div
										key={ selectedCategory }
										className="prompts-grid"
										initial={ { opacity: 0 } }
										animate={ { opacity: 1 } }
										exit={ { opacity: 0 } }
										transition={ {
											duration: 0.15,
											ease: 'easeInOut',
										} }
									>
										{ filteredPrompts.map( ( prompt ) => (
											<div
												key={ prompt.id }
												className="prompt-card"
											>
												<div className="prompt-content">
													<h4>{ prompt.title }</h4>
													<p>
														{ prompt.description }
													</p>
												</div>
												<button
													className={ `copy-button ${
														copiedId === prompt.id
															? 'copied'
															: ''
													}` }
													onClick={ () =>
														handleCopy( prompt )
													}
												>
													{ copiedId === prompt.id ? (
														<Check size={ 18 } />
													) : (
														<Copy size={ 18 } />
													) }
													<span>
														{ copiedId === prompt.id
															? 'Copied!'
															: 'Copy' }
													</span>
												</button>
											</div>
										) ) }
									</motion.div>
								</AnimatePresence>
							</div>
						</div>
					</motion.div>
				</>
			) }
		</AnimatePresence>
	);
};

export default PopupTwo;
