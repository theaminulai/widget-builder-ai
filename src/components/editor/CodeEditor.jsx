import Editor from '@monaco-editor/react';
import { APP_ACTIONS } from '../../reducers/appReducer.js';
import { useAppContext } from '../../store/AppContext';
import './CodeEditor.scss';
import FileExplorer from './FileExplorer';

/**
 * Renders file explorer and Monaco editor for widget files.
 *
 * @return {JSX.Element} Code editor section.
 */
const CodeEditor = () => {
	const { currentFile, files, dispatch } = useAppContext();

	/**
	 * Persists editor content into app state.
	 *
	 * @param {string | undefined} value Editor content.
	 * @return {void}
	 */
	const handleEditorChange = ( value ) => {
		if ( value !== undefined ) {
			dispatch( {
				type: APP_ACTIONS.UPDATE_FILE,
				payload: {
					filename: currentFile,
					content: value,
				},
			} );
		}
	};

	/**
	 * Maps file extension to Monaco language identifier.
	 *
	 * @param {string} filename File name.
	 * @return {string} Editor language key.
	 */
	const getLanguage = ( filename ) => {
		if ( filename.endsWith( '.js' ) ) return 'javascript';
		if ( filename.endsWith( '.css' ) ) return 'css';
		if ( filename.endsWith( '.php' ) ) return 'php';
		return 'plaintext';
	};

	return (
		<div className="code-editor-container">
			<FileExplorer />
			<div className="editor-wrapper">
				<div className="editor-header">
					<span className="editor-filename">{ currentFile }</span>
				</div>
				<Editor
					height="100%"
					defaultLanguage={ getLanguage( currentFile ) }
					language={ getLanguage( currentFile ) }
					value={ files[ currentFile ] || '' }
					onChange={ handleEditorChange }
					theme="vs-dark"
					options={ {
						minimap: { enabled: false },
						fontSize: 14,
						lineNumbers: 'on',
						scrollBeyondLastLine: false,
						automaticLayout: true,
						tabSize: 2,
						wordWrap: 'on',
					} }
				/>
			</div>
		</div>
	);
};

export default CodeEditor;
