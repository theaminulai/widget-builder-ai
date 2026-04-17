import { useAppContext } from '../../store/AppContext';
import './PreviewPane.scss';

/**
 * Renders a live iframe preview for the current widget.
 *
 * @return {JSX.Element} Preview pane.
 */
const PreviewPane = () => {
	const { previewUrl } = useAppContext();
	const iframeUrl = previewUrl || 'about:blank';

	return (
		<div className="preview-pane">
			<iframe
				className="preview-iframe"
				src={iframeUrl}
				title="Elementor Preview"
				loading="eager"
			/>
		</div>
	);
};

export default PreviewPane;
