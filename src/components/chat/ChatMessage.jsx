import { Bot, User } from 'lucide-react';
import { motion } from 'motion/react';
import { formatTimestamp } from '../../utils/dateTime.js';
import './ChatMessage.scss';

/**
 * Renders a single chat message row.
 *
 * @param {{message: Object}} props Component props.
 * @return {JSX.Element} Chat message element.
 */
const ChatMessage = ({ message }) => {
	const isUser = message.role === 'user';
	const messageTime = formatTimestamp(message.timestamp);

	return (
		<motion.div
			className={`chat-message ${isUser ? 'user' : 'assistant'}`}
			initial={{ opacity: 0, y: 10 }}
			animate={{ opacity: 1, y: 0 }}
			transition={{ duration: 0.3 }}
		>
			<div className="message-avatar">
				{isUser ? <User size={20} /> : <Bot size={20} />}
			</div>
			<div className="message-content">
				<div className="message-header">
					<span className="message-role">
						{isUser ? 'You' : 'AI Assistant'}
					</span>
					<span className="message-time">
						{messageTime}
					</span>
				</div>
				<div className="message-text">{message.content}</div>
			</div>
		</motion.div>
	);
};

export default ChatMessage;
