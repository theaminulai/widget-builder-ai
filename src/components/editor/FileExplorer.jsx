import { File, FileCode, FileJson } from 'lucide-react';
import { motion } from 'motion/react';
import { APP_ACTIONS } from '../../reducers/appReducer.js';
import { useAppContext } from '../../store/AppContext';
import './FileExplorer.scss';

/**
 * Renders the generated file list and current file selection.
 *
 * @return {JSX.Element} File explorer UI.
 */
const FileExplorer = () => {
	const { files, currentFile, dispatch } = useAppContext();

	/**
	 * Selects an icon component for a file extension.
	 *
	 * @param {string} filename File name.
	 * @return {Function} Icon component.
	 */
	const getFileIcon = (filename) => {
		if (filename.endsWith('.json')) return FileJson;
		if (
			filename.endsWith('.js') ||
			filename.endsWith('.css') ||
			filename.endsWith('.php') ||
			filename.endsWith('.jsx')
		)
			return FileCode;
		return File;
	};

	return (
		<div className="file-explorer">
			<div className="file-explorer-header">
				<span>Files</span>
			</div>
			<div className="file-list">
				{Object.keys(files).map((filename) => {
					const Icon = getFileIcon(filename);
					const isActive = currentFile === filename;

					return (
						<motion.button
							key={filename}
							className={`file-item ${isActive ? 'active' : ''
								}`}
							onClick={() =>
								dispatch({
									type: APP_ACTIONS.SET_CURRENT_FILE,
									payload: filename,
								})
							}
							whileHover={{ x: 4 }}
							whileTap={{ scale: 0.98 }}
						>
							<Icon size={16} />
							<span>{filename}</span>
						</motion.button>
					);
				})}
			</div>
		</div>
	);
};

export default FileExplorer;
