/**
 * do4u.eu sidebar for the block editor. No build step: it uses the wp.* globals WordPress provides.
 */
( function ( wp ) {
	'use strict';

	var cfg = window.do4uWpEditor;
	if ( ! cfg || ! window.Do4uWp || ! wp || ! wp.plugins || ! wp.data ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	/* WordPress 6.6 moved these from wp.editPost to wp.editor; 6.4 and 6.5 only have the old place. */
	var Sidebar = ( wp.editor && wp.editor.PluginSidebar ) || ( wp.editPost && wp.editPost.PluginSidebar );
	var MoreItem = ( wp.editor && wp.editor.PluginSidebarMoreMenuItem ) || ( wp.editPost && wp.editPost.PluginSidebarMoreMenuItem );
	if ( ! Sidebar || ! MoreItem ) {
		return;
	}

	function store() {
		return wp.data.select( 'core/editor' );
	}

	function actions() {
		return wp.data.dispatch( 'core/editor' );
	}

	/* Everything goes through the editor store, so the user can still undo it and has to save it. */
	var adapters = {
		title: {
			read: function () {
				return store().getEditedPostAttribute( 'title' ) || '';
			},
			write: function ( value ) {
				actions().editPost( { title: value } );
			}
		},
		excerpt: {
			read: function () {
				return store().getEditedPostAttribute( 'excerpt' ) || '';
			},
			write: function ( value ) {
				actions().editPost( { excerpt: value } );
			}
		},
		content: {
			read: function () {
				return store().getEditedPostContent() || '';
			},
			write: function ( value ) {
				actions().resetEditorBlocks( wp.blocks.rawHandler( { HTML: value } ) );
			}
		},
		append: {
			read: function () {
				return '';
			},
			write: function ( value ) {
				wp.data.dispatch( 'core/block-editor' ).insertBlocks( wp.blocks.rawHandler( { HTML: value } ) );
			}
		}
	};

	window.Do4uWp.useEditor( function () {
		var title = store().getEditedPostAttribute( 'title' ) || '';
		var live = window.Do4uWp.liveFromHtml( store().getEditedPostContent() || '', title );
		live.title = title;
		return live;
	}, adapters );

	/* The dialog is closed by now. Show where the text went: the excerpt panel is often collapsed. */
	window.Do4uWp.onInserted = function ( target, text ) {
		wp.data.dispatch( 'core/notices' ).createSuccessNotice( text, { type: 'snackbar' } );
		if ( 'excerpt' === target ) {
			wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/document' );
			if ( ! store().isEditorPanelOpened( 'post-excerpt' ) ) {
				actions().toggleEditorPanelOpened( 'post-excerpt' );
			}
		}
	};

	function render() {
		return el(
			Fragment,
			null,
			el( MoreItem, { target: 'do4u-sidebar' }, 'do4u.eu' ),
			el(
				Sidebar,
				{ name: 'do4u-sidebar', title: 'do4u.eu', icon: 'superhero-alt' },
				el(
					wp.components.PanelBody,
					{ opened: true },
					el( 'p', { className: 'description' }, cfg.intro ),
					el(
						'div',
						{ className: 'do4u-actions' },
						cfg.actions.map( function ( action ) {
							return el(
								wp.components.Button,
								{
									key: action.preset,
									variant: 'secondary',
									onClick: function () {
										window.Do4uWp.openDialog( action );
									}
								},
								action.title
							);
						} )
					)
				)
			)
		);
	}

	wp.plugins.registerPlugin( 'do4u', { icon: 'superhero-alt', render: render } );
}( window.wp ) );
