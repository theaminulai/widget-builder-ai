import {
	Award,
	Bell,
	Bookmark,
	BookOpen,
	Box,
	Calendar,
	Camera,
	Check,
	Cloud,
	Code,
	Coffee,
	Compass,
	Crown,
	Database,
	Edit,
	Eye,
	File,
	Flag,
	Gift,
	Globe,
	Hash,
	Heart,
	Home,
	Image,
	Info,
	Key,
	Layers,
	Lock,
	Mail,
	Map,
	Menu,
	Mic,
	Moon,
	Music,
	Package,
	Palette,
	Paperclip,
	Phone,
	Play,
	Plus,
	Power,
	Printer,
	Radio,
	Rocket,
	Save,
	Search,
	Send,
	Settings,
	Share,
	Shield,
	ShoppingCart,
	Smartphone,
	Sparkles,
	Speaker,
	Star,
	Sun,
	Tag,
	Target,
	Terminal,
	Trash,
	TrendingUp,
	Truck,
	Tv,
	Upload,
	User,
	Users,
	Video,
	Volume2,
	Watch,
	Wifi,
	Zap,
} from 'lucide-react';
import { useState } from 'react';
import { APP_ACTIONS } from '../../reducers/appReducer.js';
import { useAppContext } from '../../store/AppContext';

/**
 * Available icon options for widget selection.
 *
 * @type {Array<{id: string, Icon: Function}>}
 */
const icons = [
	{ id: 'sparkles', Icon: Sparkles },
	{ id: 'zap', Icon: Zap },
	{ id: 'star', Icon: Star },
	{ id: 'heart', Icon: Heart },
	{ id: 'award', Icon: Award },
	{ id: 'bell', Icon: Bell },
	{ id: 'book-open', Icon: BookOpen },
	{ id: 'box', Icon: Box },
	{ id: 'calendar', Icon: Calendar },
	{ id: 'camera', Icon: Camera },
	{ id: 'check', Icon: Check },
	{ id: 'cloud', Icon: Cloud },
	{ id: 'code', Icon: Code },
	{ id: 'coffee', Icon: Coffee },
	{ id: 'compass', Icon: Compass },
	{ id: 'crown', Icon: Crown },
	{ id: 'database', Icon: Database },
	{ id: 'edit', Icon: Edit },
	{ id: 'eye', Icon: Eye },
	{ id: 'file', Icon: File },
	{ id: 'flag', Icon: Flag },
	{ id: 'gift', Icon: Gift },
	{ id: 'globe', Icon: Globe },
	{ id: 'hash', Icon: Hash },
	{ id: 'home', Icon: Home },
	{ id: 'image', Icon: Image },
	{ id: 'info', Icon: Info },
	{ id: 'key', Icon: Key },
	{ id: 'layers', Icon: Layers },
	{ id: 'lock', Icon: Lock },
	{ id: 'mail', Icon: Mail },
	{ id: 'map', Icon: Map },
	{ id: 'menu', Icon: Menu },
	{ id: 'mic', Icon: Mic },
	{ id: 'moon', Icon: Moon },
	{ id: 'music', Icon: Music },
	{ id: 'package', Icon: Package },
	{ id: 'palette', Icon: Palette },
	{ id: 'paperclip', Icon: Paperclip },
	{ id: 'phone', Icon: Phone },
	{ id: 'play', Icon: Play },
	{ id: 'plus', Icon: Plus },
	{ id: 'power', Icon: Power },
	{ id: 'printer', Icon: Printer },
	{ id: 'radio', Icon: Radio },
	{ id: 'rocket', Icon: Rocket },
	{ id: 'save', Icon: Save },
	{ id: 'search', Icon: Search },
	{ id: 'send', Icon: Send },
	{ id: 'settings', Icon: Settings },
	{ id: 'share', Icon: Share },
	{ id: 'shield', Icon: Shield },
	{ id: 'shopping-cart', Icon: ShoppingCart },
	{ id: 'smartphone', Icon: Smartphone },
	{ id: 'speaker', Icon: Speaker },
	{ id: 'sun', Icon: Sun },
	{ id: 'tag', Icon: Tag },
	{ id: 'target', Icon: Target },
	{ id: 'terminal', Icon: Terminal },
	{ id: 'trash', Icon: Trash },
	{ id: 'trending-up', Icon: TrendingUp },
	{ id: 'truck', Icon: Truck },
	{ id: 'tv', Icon: Tv },
	{ id: 'upload', Icon: Upload },
	{ id: 'user', Icon: User },
	{ id: 'users', Icon: Users },
	{ id: 'video', Icon: Video },
	{ id: 'volume', Icon: Volume2 },
	{ id: 'watch', Icon: Watch },
	{ id: 'wifi', Icon: Wifi },
	{ id: 'bookmark', Icon: Bookmark },
];

/**
 * Renders the icon selection step with searchable icon list.
 *
 * @return {JSX.Element} Icon setup step.
 */
const WidgetIconStep = () => {
	const { widgetConfig, dispatch } = useAppContext();
	const [searchQuery, setSearchQuery] = useState('');

	const filteredIcons = icons.filter(({ id }) =>
		id.toLowerCase().includes(searchQuery.toLowerCase())
	);

	return (
		<div>
			<div className="step-header">
				<h3>Widget Icon</h3>
				<p>Choose an icon that represents your widget.</p>
			</div>

			<div className="icon-search-container">
				<div className="icon-search-wrapper">
					<Search size={20} className="search-icon" />
					<input
						type="text"
						className="icon-search-input"
						placeholder="Search icons..."
						value={searchQuery}
						onChange={(e) => setSearchQuery(e.target.value)}
					/>
					{searchQuery && (
						<button
							className="clear-search-btn"
							onClick={() => setSearchQuery('')}
							aria-label="Clear search"
						>
							×
						</button>
					)}
				</div>
				<p className="search-results-count">
					{filteredIcons.length} icon
					{filteredIcons.length !== 1 ? 's' : ''} found
				</p>
			</div>

			<div className="icon-grid">
				{filteredIcons.map(({ id, Icon }) => (
					<button
						key={id}
						className={`icon-item ${widgetConfig.icon === id ? 'selected' : ''
							}`}
						onClick={() =>
							dispatch({
								type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
								payload: { icon: id },
							})
						}
					>
						<Icon size={32} />
					</button>
				))}
			</div>

			{filteredIcons.length === 0 && (
				<div className="no-icons-found">
					<p>No icons found matching "{searchQuery}"</p>
				</div>
			)}
		</div>
	);
};

export default WidgetIconStep;
