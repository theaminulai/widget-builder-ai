import { Check, Edit2, FileCode, Palette, Plus, Trash2, X } from 'lucide-react';
import React from 'react';
import { APP_ACTIONS } from '../../reducers/appReducer.js';
import { useAppContext } from '../../store/AppContext';
import './StepContent.scss';

/**
 * Renders the optional external JS/CSS library management step.
 *
 * @return {JSX.Element} Library setup step.
 */
const JSLibraryStep = () => {
	const { widgetConfig, dispatch } = useAppContext();
	const [ editingIndex, setEditingIndex ] = React.useState( null );
	const [ editUrl, setEditUrl ] = React.useState( '' );
	const [ editType, setEditType ] = React.useState( '' );

	/**
	 * Adds a new editable library row.
	 *
	 * @return {void}
	 */
	const addLibrary = () => {
		dispatch( {
			type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
			payload: {
				libraries: [ ...widgetConfig.libraries, { url: '', type: '' } ],
			},
		} );
		setEditingIndex( widgetConfig.libraries.length );
		setEditUrl( '' );
		setEditType( '' );
	};

	/**
	 * Removes a library by index.
	 *
	 * @param {number} index Library index.
	 * @return {void}
	 */
	const removeLibrary = ( index ) => {
		const newLibraries = widgetConfig.libraries.filter(
			( _, i ) => i !== index
		);
		dispatch( {
			type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
			payload: { libraries: newLibraries },
		} );
		if ( editingIndex === index ) {
			setEditingIndex( null );
		}
	};

	/**
	 * Starts edit mode for an existing library row.
	 *
	 * @param {number} index Library index.
	 * @param {{url: string, type: string}} library Library data.
	 * @return {void}
	 */
	const startEditing = ( index, library ) => {
		setEditingIndex( index );
		setEditUrl( library.url );
		setEditType( library.type );
	};

	/**
	 * Saves the currently edited library row.
	 *
	 * @param {number} index Library index.
	 * @return {void}
	 */
	const saveEdit = ( index ) => {
		// Validate that both URL and type are provided
		if ( ! editUrl.trim() || ! editType ) {
			return;
		}
		// Update both url and type in a single operation to avoid race conditions
		const newLibraries = [ ...widgetConfig.libraries ];
		newLibraries[ index ] = {
			...newLibraries[ index ],
			url: editUrl,
			type: editType,
		};
		dispatch( {
			type: APP_ACTIONS.UPDATE_WIDGET_CONFIG,
			payload: { libraries: newLibraries },
		} );
		setEditingIndex( null );
	};

	/**
	 * Exits edit mode without persisting changes.
	 *
	 * @return {void}
	 */
	const cancelEdit = () => {
		setEditingIndex( null );
	};

	// Group libraries by type
	const jsLibraries = widgetConfig.libraries
		.map( ( lib, index ) => ( { ...lib, originalIndex: index } ) )
		.filter( ( lib ) => lib.type === 'js' );

	const cssLibraries = widgetConfig.libraries
		.map( ( lib, index ) => ( { ...lib, originalIndex: index } ) )
		.filter( ( lib ) => lib.type === 'css' );

	// Check if we're adding a new library (no type yet)
	const newLibrary = widgetConfig.libraries
		.map( ( lib, index ) => ( { ...lib, originalIndex: index } ) )
		.find( ( lib ) => ! lib.type && editingIndex === lib.originalIndex );

	// Check if we can save (for the new library section)
	const canSaveNewLibrary = editUrl.trim() && editType;

	/**
	 * Renders one library tree item in view or edit mode.
	 *
	 * @param {{originalIndex: number, url: string, type: string}} library Library row data.
	 * @param {number} index Display index within grouped libraries.
	 * @return {JSX.Element} Library item element.
	 */
	const renderLibraryItem = ( library, index ) => {
		const originalIndex = library.originalIndex;
		const isEditing = editingIndex === originalIndex;
		const canSave = editUrl.trim() && editType;

		return (
			<div key={ originalIndex } className="library-tree-item">
				<div className="library-tree-content">
					<div className="library-item-header">
						<div className="library-name">
							<span className="library-label">
								{ library.type === 'js' ? 'JS' : 'CSS' } Library{ ' ' }
								{ index + 1 }
							</span>
						</div>
						<div className="library-actions">
							{ isEditing ? (
								<>
									<button
										type="button"
										className="save-library-btn"
										onClick={ () =>
											saveEdit( originalIndex )
										}
										disabled={ ! canSave }
										aria-label="Save library"
									>
										<Check size={ 20 } />
									</button>
									<button
										type="button"
										className="cancel-edit-btn"
										onClick={ cancelEdit }
										aria-label="Cancel edit"
									>
										<X size={ 20 } />
									</button>
								</>
							) : (
								<>
									<button
										type="button"
										className="edit-library-btn"
										onClick={ () =>
											startEditing(
												originalIndex,
												library
											)
										}
										aria-label="Edit library"
									>
										<Edit2 size={ 20 } />
									</button>
									<button
										type="button"
										className="remove-library-btn"
										onClick={ () =>
											removeLibrary( originalIndex )
										}
										aria-label="Remove library"
									>
										<Trash2 size={ 24 } />
									</button>
								</>
							) }
						</div>
					</div>

					{ isEditing ? (
						<div className="library-url-group">
							<input
								type="text"
								className="library-url-input"
								value={ editUrl }
								onChange={ ( e ) =>
									setEditUrl( e.target.value )
								}
								placeholder="Enter the CDN or hosted URL."
							/>
							<div className="library-type-dropdown">
								<select
									className="type-select"
									value={ editType }
									onChange={ ( e ) =>
										setEditType( e.target.value )
									}
								>
									<option value="" disabled>
										Select Library Type
									</option>
									<option value="js">JS Library</option>
									<option value="css">CSS Library</option>
								</select>
							</div>
						</div>
					) : (
						<div className="library-preview">
							<code className="library-code">
								{ library.url }
							</code>
						</div>
					) }
				</div>
			</div>
		);
	};

	return (
		<div>
			<div className="step-header">
				<h3>JavaScript Library OR CSS Library</h3>
				<p>
					Add external JavaScript or CSS libraries via URL. Choose the
					library type and paste the CDN or hosted URL.
				</p>
			</div>

			<div className="libraries-container">
				{ widgetConfig.libraries.length > 0 ? (
					<div className="libraries-tree">
						{ jsLibraries.length > 0 && (
							<div className="library-group">
								<div className="library-group-header">
									<FileCode size={ 24 } />
									<span>
										JavaScript Libraries (
										{ jsLibraries.length })
									</span>
								</div>
								<div className="library-group-items">
									{ jsLibraries.map( ( library, index ) =>
										renderLibraryItem( library, index )
									) }
								</div>
							</div>
						) }

						{ cssLibraries.length > 0 && (
							<div className="library-group">
								<div className="library-group-header">
									<Palette size={ 24 } />
									<span>
										CSS Libraries ({ cssLibraries.length })
									</span>
								</div>
								<div className="library-group-items">
									{ cssLibraries.map( ( library, index ) =>
										renderLibraryItem( library, index )
									) }
								</div>
							</div>
						) }

						{ /* Show new library being added */ }
						{ newLibrary && (
							<div className="library-group">
								<div className="library-group-header">
									<Plus size={ 24 } />
									<span>New Library</span>
								</div>
								<div className="library-group-items">
									<div className="library-tree-item">
										<div className="library-tree-content">
											<div className="library-item-header">
												<div className="library-name">
													<span className="library-label">
														New Library
													</span>
												</div>
												<div className="library-actions">
													<button
														type="button"
														className="save-library-btn"
														onClick={ () =>
															saveEdit(
																newLibrary.originalIndex
															)
														}
														disabled={
															! canSaveNewLibrary
														}
														aria-label="Save library"
													>
														<Check size={ 20 } />
													</button>
													<button
														type="button"
														className="cancel-edit-btn"
														onClick={ () => {
															removeLibrary(
																newLibrary.originalIndex
															);
															cancelEdit();
														} }
														aria-label="Cancel"
													>
														<X size={ 20 } />
													</button>
												</div>
											</div>

											<div className="library-url-group">
												<input
													type="text"
													className="library-url-input"
													value={ editUrl }
													onChange={ ( e ) =>
														setEditUrl(
															e.target.value
														)
													}
													placeholder="Enter the CDN or hosted URL."
													autoFocus
												/>
												<div className="library-type-dropdown">
													<select
														className="type-select"
														value={ editType }
														onChange={ ( e ) =>
															setEditType(
																e.target.value
															)
														}
													>
														<option value="" disabled > Select Library Type </option>
														<option value="js"> JS Library </option>
														<option value="css"> CSS Library</option>
													</select>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						) }
					</div>
				) : (
					<div className="empty-state">
						<div className="empty-icon">
							<FileCode size={ 48 } />
						</div>
						<p className="empty-text">No libraries added yet</p>
						<p className="empty-subtext">
							Click the button below to add your first library
						</p>
					</div>
				) }

				<button
					type="button"
					className="add-library-btn"
					onClick={ addLibrary }
				>
					<Plus size={ 24 } />
					<span>Add Library</span>
				</button>
			</div>
		</div>
	);
};

export default JSLibraryStep;
