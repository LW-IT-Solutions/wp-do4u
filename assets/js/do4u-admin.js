/**
 * do4u.eu for WordPress: opens tools on an admin page or in a dialog, fills them in and takes results over.
 *
 * Results are untrusted. They are only ever shown with textContent, and everything that goes into
 * WordPress passes the do4u_wp_apply endpoint first, where the server checks rights and cleans the value.
 * Nothing here starts a job: the do4u.eu form only gets prefilled, the user starts it.
 */
( function () {
	'use strict';

	var cfg = window.do4uWpData;
	if ( ! cfg ) {
		return;
	}

	function t( key ) {
		return ( cfg.i18n && cfg.i18n[ key ] ) || key;
	}

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined && text !== null ) {
			node.textContent = String( text );
		}
		return node;
	}

	function post( action, data ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( '_ajax_nonce', cfg.nonce );
		Object.keys( data || {} ).forEach( function ( name ) {
			body.append( name, data[ name ] );
		} );
		return fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( response ) {
			return response.json().then( function ( json ) {
				return { ok: !! ( response.ok && json && json.success ), data: ( json && json.data ) || {} };
			} );
		} );
	}

	/* The widget loader comes with the plugin (assets/js/do4u-widget.js); it contacts do4u.eu only for an opened tool. */
	function loadLoader() {
		return window.Do4UWidgets ? Promise.resolve() : Promise.reject( new Error( 'loader' ) );
	}

	/* --- Text from the editor ----------------------------------------------------------------------------- */

	function stripShortcodes( html ) {
		var tags = ( cfg.shortcodes || [] ).map( function ( tag ) {
			return tag.replace( /[^a-zA-Z0-9_-]/g, '' );
		} ).filter( Boolean );
		if ( ! tags.length ) {
			return html;
		}
		return html.replace( new RegExp( '\\[\\/?(?:' + tags.join( '|' ) + ')\\b[^\\]]*\\]', 'g' ), '' );
	}

	function cleanHtml( html ) {
		return stripShortcodes( String( html || '' ).replace( /<!--\s*\/?wp:[\s\S]*?-->/g, '' ) ).trim();
	}

	/* DOMParser builds an inert document: no script runs and no image loads, whatever the content is. */
	function plainText( html ) {
		var doc = new DOMParser().parseFromString( cleanHtml( html ), 'text/html' );
		doc.querySelectorAll( 'script,style,noscript,iframe,object,embed' ).forEach( function ( node ) {
			node.remove();
		} );
		doc.querySelectorAll( 'p,div,li,h1,h2,h3,h4,h5,h6,blockquote,pre,tr,figure,section,article,ul,ol,table,br' ).forEach( function ( node ) {
			node.after( '\n\n' );
		} );
		return ( doc.body.textContent || '' ).replace( /[ \t]*\n[ \t]*/g, '\n' ).replace( /\n{3,}/g, '\n\n' ).trim();
	}

	function htmlToPreview( html ) {
		return plainText( html );
	}

	/* What an editor can report live; the server only knows the last saved state. */
	function liveFromHtml( html, title ) {
		var clean = cleanHtml( html );
		var heading = el( 'h1', '', title ).outerHTML;
		return { plain: plainText( html ), html: clean, html_title: heading + '\n' + clean };
	}

	/* --- Adapters: how a target field is read and written on the current screen ----------------------------- */

	var editor = { live: null, adapters: {} };

	function tinyEditor( id ) {
		if ( ! window.tinymce ) {
			return null;
		}
		var instance = window.tinymce.get( id );
		return instance && ! instance.isHidden() ? instance : null;
	}

	function textFieldAdapter( id ) {
		return {
			read: function () {
				var field = document.getElementById( id );
				return field ? field.value : '';
			},
			write: function ( value ) {
				var field = document.getElementById( id );
				if ( field ) {
					field.value = value;
					field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
					field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			}
		};
	}

	function richAdapter( id ) {
		return {
			read: function () {
				var instance = tinyEditor( id );
				if ( instance ) {
					return instance.getContent();
				}
				var field = document.getElementById( id );
				return field ? field.value : '';
			},
			write: function ( value ) {
				var instance = tinyEditor( id );
				if ( instance ) {
					instance.setContent( value );
					instance.fire( 'change' );
					return;
				}
				var field = document.getElementById( id );
				if ( field ) {
					field.value = value;
				}
			}
		};
	}

	function setupClassicEditor() {
		if ( ! document.getElementById( 'post_ID' ) || ! document.getElementById( 'content' ) ) {
			return;
		}
		var content = richAdapter( 'content' );
		var title = textFieldAdapter( 'title' );
		editor.adapters.title = title;
		editor.adapters.content = content;
		editor.adapters.product_content = content;
		editor.adapters.append = {
			read: function () {
				return '';
			},
			write: function ( value ) {
				content.write( content.read() + '\n' + value );
			}
		};
		if ( document.getElementById( 'excerpt' ) ) {
			editor.adapters.excerpt = textFieldAdapter( 'excerpt' );
			editor.adapters.product_short = richAdapter( 'excerpt' );
		}
		editor.live = function () {
			var live = liveFromHtml( content.read(), title.read() );
			live.title = title.read();
			return live;
		};
	}

	/* The alt field in the media dialog and on the edit page, only if it belongs to this image. */
	function altField( id ) {
		var postId = document.getElementById( 'post_ID' );
		var page = document.getElementById( 'attachment_alt' );
		if ( page && postId && String( postId.value ) === String( id ) ) {
			return page;
		}
		/* The media dialog names its image only through the button of this plugin in the visible details. */
		var found = null;
		document.querySelectorAll( '.media-modal' ).forEach( function ( modal ) {
			var button = modal.offsetParent !== null || 'fixed' === window.getComputedStyle( modal ).position ? modal.querySelector( '[data-do4u-open]' ) : null;
			var data = button ? parse( button.getAttribute( 'data-do4u-open' ) ) : null;
			if ( data && data.context && Number( data.context.id ) === Number( id ) ) {
				found = modal.querySelector( '[data-setting="alt"] textarea, [data-setting="alt"] input' );
			}
		} );
		return found;
	}

	/*
	 * SEO fields of Yoast SEO and Rank Math. Both keep them in their own editor store and save them together
	 * with the post, in the block editor and in the classic editor. So this changes nothing until the user saves.
	 */
	function seoAdapter( target ) {
		var isTitle = 'seo_title' === target;
		var data = window.wp && window.wp.data;
		var parts = [];
		var yoast = data && data.select && data.select( 'yoast-seo/editor' );
		var rank = data && data.select && data.select( 'rank-math' );
		if ( yoast ) {
			var hidden = document.getElementById( isTitle ? 'yoast_wpseo_title' : 'yoast_wpseo_metadesc' );
			parts.push( {
				read: function () {
					return String( ( isTitle ? yoast.getSnippetEditorTitle() : yoast.getSnippetEditorDescription() ) || '' );
				},
				write: function ( value ) {
					data.dispatch( 'yoast-seo/editor' ).updateData( isTitle ? { title: value } : { description: value } );
					if ( hidden ) {
						hidden.value = value;
					}
				}
			} );
		}
		if ( rank ) {
			parts.push( {
				read: function () {
					return String( ( isTitle ? rank.getTitle() : rank.getDescription() ) || '' );
				},
				write: function ( value ) {
					var act = data.dispatch( 'rank-math' );
					if ( isTitle ) {
						act.updateSerpTitle( value );
						act.updateTitle( value );
					} else {
						act.updateSerpDescription( value );
						act.updateDescription( value );
					}
				}
			} );
		}
		if ( ! parts.length ) {
			return null;
		}
		return {
			read: function () {
				// With two SEO plugins active, a value in either one counts as already filled.
				for ( var i = 0; i < parts.length; i++ ) {
					var value = parts[ i ].read();
					if ( value ) {
						return value;
					}
				}
				return '';
			},
			write: function ( value ) {
				parts.forEach( function ( part ) {
					part.write( value );
				} );
			}
		};
	}

	function adapterFor( target, opened ) {
		if ( 'seo_title' === target || 'seo_description' === target ) {
			return seoAdapter( target );
		}
		if ( 'alt' === target ) {
			var field = altField( opened.object_id );
			if ( ! field ) {
				return null;
			}
			return {
				read: function () {
					return field.value;
				},
				write: function ( value ) {
					field.value = value;
					field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
					field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				}
			};
		}
		if ( 'comment_reply' === target ) {
			if ( ! window.commentReply || ! document.getElementById( 'replycontent' ) ) {
				return null;
			}
			return {
				closes: true,
				read: function () {
					return document.getElementById( 'replycontent' ).value;
				},
				write: function ( value ) {
					window.commentReply.open( opened.object_id, opened.post_id );
					var box = document.getElementById( 'replycontent' );
					box.value = value;
					box.focus();
				}
			};
		}
		return editor.adapters[ target ] || null;
	}

	/* --- Result ---------------------------------------------------------------------------------------------- */

	/* Resolves paths such as "summary", "variants.*.title" or "products.0.long". */
	function valuesAt( data, path ) {
		var current = [ data ];
		path.split( '.' ).forEach( function ( part ) {
			var next = [];
			current.forEach( function ( item ) {
				if ( ! item || 'object' !== typeof item ) {
					return;
				}
				if ( '*' === part ) {
					if ( Array.isArray( item ) ) {
						next = next.concat( item );
					}
				} else if ( Object.prototype.hasOwnProperty.call( item, part ) ) {
					next.push( item[ part ] );
				}
			} );
			current = next;
		} );
		return current;
	}

	function copyText( value ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( value );
		}
		return new Promise( function ( resolve, reject ) {
			var area = el( 'textarea' );
			area.value = value;
			area.setAttribute( 'readonly', '' );
			area.style.position = 'fixed';
			area.style.opacity = '0';
			( document.querySelector( 'dialog[open]' ) || document.body ).appendChild( area );
			area.select();
			var done = false;
			try {
				done = document.execCommand( 'copy' );
			} catch ( error ) {
				done = false;
			}
			area.remove();
			if ( done ) {
				resolve();
			} else {
				reject( new Error( 'copy' ) );
			}
		} );
	}

	/* Second step before replacing a field: names the current text and leaves the decision to the user. */
	function askReplace( row, current, onReplace ) {
		var ask = el( 'div', 'do4u-confirm' );
		ask.setAttribute( 'role', 'group' );
		var shown = current.length > 160 ? current.slice( 0, 160 ) + ' …' : current;
		ask.appendChild( el( 'p', '', t( 'conflict' ).replace( '%s', shown ) ) );
		var replace = el( 'button', 'button button-primary', t( 'replace' ) );
		replace.type = 'button';
		var keep = el( 'button', 'button', t( 'keep' ) );
		keep.type = 'button';
		replace.addEventListener( 'click', function () {
			ask.remove();
			onReplace();
		} );
		keep.addEventListener( 'click', function () {
			ask.remove();
			say( row, t( 'kept' ) );
		} );
		ask.appendChild( replace );
		ask.appendChild( keep );
		row.appendChild( ask );
		replace.focus();
	}

	function say( row, text, isError ) {
		var line = row.querySelector( '.do4u-row-status' );
		line.textContent = text;
		line.classList.toggle( 'do4u-error', !! isError );
	}

	function applyOffer( offer, value, row, opened, closeHost ) {
		var adapter = adapterFor( offer.target, opened );
		var serverWrites = 'draft' === offer.target || 'new_draft' === offer.target || ( 'alt' === offer.target && ! adapter ) || ( 0 === offer.target.indexOf( 'seo_' ) && ! adapter && !! opened.object_id );
		if ( ! adapter && ! serverWrites ) {
			say( row, t( 'noField' ), true );
			return;
		}
		var send = function ( overwrite ) {
			post( 'do4u_wp_apply', {
				target: offer.target,
				object_id: opened.object_id || 0,
				value: value,
				format: offer.format || 'text',
				store: serverWrites ? '1' : '0',
				overwrite: overwrite ? '1' : '0',
				split_title: offer.split_title ? '1' : '0'
			} ).then( function ( answer ) {
				if ( ! answer.ok ) {
					say( row, answer.data.message || t( 'network' ), true );
					return;
				}
				if ( answer.data.conflict ) {
					askReplace( row, String( answer.data.current || '' ), function () {
						send( true );
					} );
					return;
				}
				if ( answer.data.edit_link ) {
					say( row, t( 'draftSaved' ) );
					var open = el( 'a', 'button', t( 'openDraft' ) );
					open.href = answer.data.edit_link;
					open.target = '_blank';
					open.rel = 'noopener';
					row.querySelector( '.do4u-row-status' ).after( open );
					return;
				}
				if ( answer.data.stored ) {
					say( row, t( 'stored' ) );
					return;
				}
				var current = adapter.read().trim();
				var clean = String( answer.data.value || '' );
				if ( ! overwrite && current && current !== clean.trim() ) {
					askReplace( row, htmlToPreview( current ), function () {
						send( true );
					} );
					return;
				}
				adapter.write( clean );
				say( row, t( 'applied' ) );
				/* Title and description of an SEO result are taken over one after the other from the same dialog. */
				if ( 0 !== offer.target.indexOf( 'seo_' ) ) {
					closeHost();
				}
				if ( window.Do4uWp.onInserted ) {
					window.Do4uWp.onInserted( offer.target, t( 'applied' ) );
				}
			} ).catch( function () {
				say( row, t( 'network' ), true );
			} );
		};
		send( false );
	}

	function renderOffers( box, opened, result, closeHost ) {
		box.replaceChildren();
		var rows = [];
		( opened.offers || [] ).forEach( function ( offer ) {
			valuesAt( result, offer.path ).slice( 0, 12 ).forEach( function ( raw ) {
				var value = 'string' === typeof raw ? raw : ( raw && 'object' === typeof raw ? JSON.stringify( raw, null, 2 ) : '' );
				if ( ! value.trim() ) {
					return;
				}
				rows.push( { offer: offer, value: value, isText: 'string' === typeof raw } );
			} );
		} );
		if ( ! rows.length ) {
			box.hidden = true;
			return;
		}
		box.hidden = false;
		box.appendChild( el( 'h2', '', t( 'resultHeading' ) ) );
		rows.forEach( function ( item ) {
			var row = el( 'div', 'do4u-offer' );
			if ( item.offer.heading ) {
				row.appendChild( el( 'p', 'do4u-offer-heading', item.offer.heading ) );
			}
			var preview = 'html' === item.offer.format ? htmlToPreview( item.value ) : item.value;
			row.appendChild( el( 'p', 'do4u-offer-text', preview.length > 600 ? preview.slice( 0, 600 ) + ' …' : preview ) );
			var buttons = el( 'div', 'do4u-offer-buttons' );
			if ( 'copy' !== item.offer.target && item.offer.label ) {
				var apply = el( 'button', 'button button-primary', item.offer.label );
				apply.type = 'button';
				apply.addEventListener( 'click', function () {
					applyOffer( item.offer, item.value, row, opened, closeHost );
				} );
				buttons.appendChild( apply );
			}
			var copy = el( 'button', 'button', t( 'copy' ) );
			copy.type = 'button';
			copy.addEventListener( 'click', function () {
				copyText( item.value ).then( function () {
					say( row, t( 'copied' ) );
				} ).catch( function () {
					say( row, t( 'copyFailed' ), true );
				} );
			} );
			buttons.appendChild( copy );
			row.appendChild( buttons );
			var status = el( 'p', 'do4u-row-status' );
			status.setAttribute( 'role', 'status' );
			row.appendChild( status );
			box.appendChild( row );
		} );
	}

	/* --- Opening a tool ---------------------------------------------------------------------------------------- */

	function fillFor( opened ) {
		var fill = Object.assign( {}, opened.fill );
		var live = editor.live ? editor.live() : null;
		if ( live ) {
			Object.keys( opened.sources || {} ).forEach( function ( field ) {
				var source = opened.sources[ field ];
				if ( Object.prototype.hasOwnProperty.call( live, source ) ) {
					fill[ field ] = live[ source ];
				}
			} );
		}
		return fill;
	}

	/* Alt text: no iframe, because the form there cannot be given a file. The file goes from the server, after this click. */
	function mountJob( host, status, opened, closeHost ) {
		status.textContent = t( 'jobHint' );
		var figure = el( 'div', 'do4u-job' );
		if ( opened.thumb ) {
			var image = el( 'img' );
			image.src = opened.thumb;
			image.alt = '';
			figure.appendChild( image );
		}
		var facts = el( 'div' );
		facts.appendChild( el( 'p', 'do4u-offer-heading', opened.name || '' ) );
		facts.appendChild( el( 'p', '', opened.current ? t( 'jobCurrent' ) + ' ' + opened.current : t( 'jobNoCurrent' ) ) );
		if ( opened.price ) {
			facts.appendChild( el( 'p', '', t( 'jobPrice' ) + ' ' + opened.price ) );
		}
		var start = el( 'button', 'button button-primary', t( 'jobStart' ) );
		start.type = 'button';
		facts.appendChild( start );
		figure.appendChild( facts );
		host.appendChild( figure );
		var line = el( 'p', 'do4u-status' );
		line.setAttribute( 'role', 'status' );
		host.appendChild( line );
		var offers = el( 'section', 'do4u-offers' );
		offers.hidden = true;
		offers.setAttribute( 'aria-live', 'polite' );
		host.appendChild( offers );

		var problem = function ( text ) {
			line.textContent = text;
			line.classList.add( 'do4u-error' );
			start.disabled = false;
		};
		var poll = function ( job, round ) {
			if ( ! host.isConnected ) {
				return;
			}
			if ( round > 90 ) {
				problem( t( 'jobLost' ) );
				return;
			}
			// With some spread, so that many open windows do not ask at the same moment.
			var wait = Math.min( 3000 + round * 250, 10000 ) + Math.floor( Math.random() * 800 );
			setTimeout( function () {
				post( 'do4u_wp_job_status', { job: job } ).then( function ( answer ) {
					if ( ! answer.ok ) {
						problem( answer.data.message || t( 'network' ) );
						return;
					}
					if ( 'done' === answer.data.status ) {
						line.textContent = t( 'jobDone' );
						renderOffers( offers, opened, answer.data.result, closeHost );
						return;
					}
					if ( 'failed' === answer.data.status ) {
						opened.request = '';
						problem( answer.data.message || t( 'failed' ) );
						return;
					}
					poll( job, round + 1 );
				} ).catch( function () {
					poll( job, round + 1 );
				} );
			}, wait );
		};
		var fresh = function () {
			var bytes = new Uint8Array( 16 );
			window.crypto.getRandomValues( bytes );
			return Array.prototype.map.call( bytes, function ( b ) {
				return ( '0' + b.toString( 16 ) ).slice( -2 );
			} ).join( '' );
		};
		start.addEventListener( 'click', function () {
			start.disabled = true;
			line.classList.remove( 'do4u-error' );
			line.textContent = t( 'jobRunning' );
			offers.hidden = true;
			// The same id after a network error makes do4u.eu answer with the same job; a new id only after a real result.
			opened.request = opened.request || fresh();
			post( 'do4u_wp_job_start', { object_id: opened.object_id, request: opened.request } ).then( function ( answer ) {
				if ( ! answer.ok ) {
					problem( answer.data.message || t( 'failed' ) );
					return;
				}
				poll( answer.data.job, 0 );
			} ).catch( function () {
				problem( t( 'network' ) );
			} );
		} );
	}

	function mount( host, request, closeHost ) {
		host.replaceChildren();
		var status = el( 'p', 'do4u-status', t( 'loading' ) );
		status.setAttribute( 'role', 'status' );
		host.appendChild( status );
		var context = request.context || {};
		post( 'do4u_wp_open', {
			tool: request.tool || '',
			preset: request.preset || '',
			context_id: context.id || 0,
			context_ids: context.ids || ''
		} ).then( function ( answer ) {
			if ( ! answer.ok ) {
				status.textContent = answer.data.message || t( 'failed' );
				status.classList.add( 'do4u-error' );
				return;
			}
			var opened = answer.data;
			if ( 'job' === opened.mode ) {
				mountJob( host, status, opened, closeHost );
				return;
			}
			return loadLoader().then( function () {
				var filled = Object.keys( opened.fill || {} ).some( function ( name ) {
					return !! opened.fill[ name ];
				} );
				status.textContent = t( filled || ( opened.sources && Object.keys( opened.sources ).length ) ? 'hint' : 'hintEmpty' );
				( opened.notices || [] ).forEach( function ( notice ) {
					host.appendChild( el( 'p', 'do4u-notice', notice ) );
				} );
				var widget = el( 'div', 'do4u-widget' );
				widget.setAttribute( 'data-do4u-widget', opened.widget );
				widget.dataset.tool = opened.tool;
				widget.dataset.token = opened.token;
				widget.dataset.lang = opened.lang;
				widget.dataset.theme = opened.theme;
				widget.dataset.title = opened.title || 'do4u.eu';
				widget.dataset.sessionUrl = opened.session_url;
				widget.dataset.base = opened.base;
				widget.addEventListener( 'do4u:ready', function () {
					var fill = fillFor( opened );
					// Filling happens only here, with what the page knows now: a selection made while the form was loading counts too.
					if ( host.do4uPending ) {
						fill = host.do4uPending;
						host.do4uPending = null;
					}
					host.do4uReady = true;
					if ( widget.do4uWidget && Object.keys( fill ).length ) {
						widget.do4uWidget.fill( fill );
					}
				} );
				host.do4uFill = function ( fields ) {
					if ( host.do4uReady && widget.do4uWidget ) {
						widget.do4uWidget.fill( fields );
					} else {
						host.do4uPending = fields;
					}
				};
				var offers = el( 'section', 'do4u-offers' );
				offers.hidden = true;
				offers.setAttribute( 'aria-live', 'polite' );
				widget.addEventListener( 'do4u:result', function ( event ) {
					renderOffers( offers, opened, event.detail && event.detail.result, closeHost );
				} );
				widget.addEventListener( 'do4u:expired', function () {
					status.textContent = t( 'expired' );
				} );
				host.appendChild( widget );
				host.appendChild( offers );
				window.Do4UWidgets.scan();
			}, function () {
				status.textContent = t( 'loaderFailed' );
				status.classList.add( 'do4u-error' );
			} );
		} ).catch( function () {
			status.textContent = t( 'failed' );
			status.classList.add( 'do4u-error' );
		} );
	}

	/* A native dialog brings the focus trap, Escape and the return of focus to the button that opened it. */
	function openDialog( request ) {
		var dialog = el( 'dialog', 'do4u-dialog' );
		var heading = el( 'h2', '', request.title || 'do4u.eu' );
		heading.id = 'do4u-dialog-title';
		dialog.setAttribute( 'aria-labelledby', heading.id );
		var head = el( 'div', 'do4u-dialog-head' );
		head.appendChild( heading );
		var close = el( 'button', 'button', t( 'close' ) );
		close.type = 'button';
		close.addEventListener( 'click', function () {
			dialog.close();
		} );
		head.appendChild( close );
		var body = el( 'div', 'do4u-dialog-body' );
		dialog.appendChild( head );
		dialog.appendChild( body );
		dialog.addEventListener( 'close', function () {
			dialog.remove();
		} );
		document.body.appendChild( dialog );
		dialog.showModal();
		mount( body, request, function () {
			dialog.close();
		} );
	}

	/* --- Page wiring ---------------------------------------------------------------------------------------------- */

	function parse( text ) {
		try {
			return JSON.parse( text );
		} catch ( error ) {
			return null;
		}
	}

	function setupCatalogFilter() {
		var input = document.querySelector( '[data-do4u-filter]' );
		var list = document.querySelector( '[data-do4u-catalog]' );
		var count = document.querySelector( '[data-do4u-count]' );
		if ( ! input || ! list ) {
			return;
		}
		var total = count ? count.textContent : '';
		var none = document.querySelector( '[data-do4u-none]' );
		input.addEventListener( 'input', function () {
			var query = input.value.trim().toLowerCase();
			var shown = 0;
			list.querySelectorAll( 'li' ).forEach( function ( item ) {
				var match = ! query || item.getAttribute( 'data-do4u-search' ).indexOf( query ) !== -1;
				item.hidden = ! match;
				shown += match ? 1 : 0;
			} );
			if ( count ) {
				count.textContent = query ? t( 'matches' ).replace( '%d', String( shown ) ) : total;
			}
			if ( none ) {
				none.classList.toggle( 'is-hidden', shown > 0 );
			}
		} );
	}

	/* Selection of posts and pages for the newsletter. Titles are shown with textContent; the lines for the form come from the server. */
	function setupPicker() {
		var box = document.querySelector( '[data-do4u-picker]' );
		var panel = document.querySelector( '[data-do4u-panel]' );
		if ( ! box || ! panel ) {
			return;
		}
		var results = box.querySelector( '[data-do4u-picker-results]' );
		var chosen = box.querySelector( '[data-do4u-picker-selected]' );
		var line = box.querySelector( '[data-do4u-picker-status]' );
		var query = box.querySelector( '[data-do4u-picker-q]' );
		var type = box.querySelector( '[data-do4u-picker-type]' );
		var selected = [];
		var main = 0;
		var found = [];

		function isSelected( id ) {
			return selected.some( function ( item ) {
				return item.id === id;
			} );
		}

		function drawResults() {
			results.replaceChildren();
			if ( ! found.length ) {
				results.appendChild( el( 'li', 'do4u-meta', t( 'pickNone' ) ) );
			}
			found.forEach( function ( item ) {
				var row = el( 'li' );
				var label = el( 'label' );
				var box2 = el( 'input' );
				box2.type = 'checkbox';
				box2.checked = isSelected( item.id );
				box2.addEventListener( 'change', function () {
					if ( box2.checked ) {
						if ( selected.length >= cfg.maxPosts ) {
							box2.checked = false;
							line.textContent = t( 'pickFull' ).replace( '%d', String( cfg.maxPosts ) );
							return;
						}
						selected.push( item );
						main = main || item.id;
					} else {
						remove( item.id );
					}
					line.textContent = '';
					drawSelected();
				} );
				label.appendChild( box2 );
				label.appendChild( document.createTextNode( ' ' + item.title ) );
				row.appendChild( label );
				row.appendChild( el( 'span', 'do4u-meta', item.type + ' · ' + item.date ) );
				results.appendChild( row );
			} );
		}

		function remove( id ) {
			selected = selected.filter( function ( item ) {
				return item.id !== id;
			} );
			if ( main === id ) {
				main = selected.length ? selected[ 0 ].id : 0;
			}
		}

		var counter = box.querySelector( '[data-do4u-picker-count]' );
		var slots = box.querySelector( '[data-do4u-picker-slots]' );
		function drawCount() {
			if ( counter ) {
				counter.textContent = selected.length + ' / ' + cfg.maxPosts;
				counter.classList.toggle( 'is-full', selected.length >= cfg.maxPosts );
			}
			if ( slots ) {
				slots.replaceChildren();
				for ( var i = 0; i < cfg.maxPosts; i++ ) {
					slots.appendChild( el( 'span', 'do4u-slot' + ( i < selected.length ? ' is-used' : '' ) ) );
				}
			}
		}

		function drawSelected() {
			drawCount();
			chosen.replaceChildren();
			if ( ! selected.length ) {
				chosen.appendChild( el( 'li', 'do4u-meta', t( 'pickEmpty' ) ) );
			}
			selected.forEach( function ( item ) {
				var row = el( 'li', main === item.id ? 'is-main' : '' );
				row.appendChild( el( 'span', 'do4u-offer-heading', item.title ) );
				var label = el( 'label' );
				var radio = el( 'input' );
				radio.type = 'radio';
				radio.name = 'do4u-picker-main';
				radio.checked = main === item.id;
				radio.addEventListener( 'change', function () {
					main = item.id;
					chosen.querySelectorAll( 'li' ).forEach( function ( li ) {
						li.classList.remove( 'is-main' );
					} );
					row.classList.add( 'is-main' );
				} );
				label.appendChild( radio );
				label.appendChild( document.createTextNode( ' ' + t( 'pickMain' ) ) );
				row.appendChild( label );
				var drop = el( 'button', 'button-link do4u-picker-remove', t( 'pickRemove' ) + ': ' + item.title );
				drop.type = 'button';
				drop.addEventListener( 'click', function () {
					remove( item.id );
					drawSelected();
					drawResults();
				} );
				row.appendChild( drop );
				chosen.appendChild( row );
			} );
		}

		var searchTimer = null;
		function search() {
			post( 'do4u_wp_find_posts', { q: query.value, type: type.value } ).then( function ( answer ) {
				found = answer.ok ? answer.data.items : [];
				drawResults();
			} ).catch( function () {
				line.textContent = t( 'network' );
			} );
		}
		query.addEventListener( 'input', function () {
			clearTimeout( searchTimer );
			searchTimer = setTimeout( search, 300 );
		} );
		type.addEventListener( 'change', search );

		box.querySelector( '[data-do4u-picker-apply]' ).addEventListener( 'click', function () {
			if ( ! selected.length ) {
				line.textContent = t( 'pickEmpty' );
				return;
			}
			post( 'do4u_wp_newsletter_fill', {
				context_ids: selected.map( function ( item ) {
					return item.id;
				} ).join( ',' ),
				context_main: main
			} ).then( function ( answer ) {
				if ( ! answer.ok ) {
					line.textContent = answer.data.message || t( 'network' );
					return;
				}
				if ( panel.do4uFill ) {
					panel.do4uFill( answer.data.fill );
				} else {
					panel.do4uPending = answer.data.fill;
				}
				line.textContent = ( answer.data.notices || [] ).concat( [ t( 'pickFilled' ) ] ).join( ' ' );
			} ).catch( function () {
				line.textContent = t( 'network' );
			} );
		} );

		var initial = box.getAttribute( 'data-do4u-picker' );
		if ( initial && /[1-9]/.exec( initial ) ) {
			post( 'do4u_wp_newsletter_fill', { context_ids: initial } ).then( function ( answer ) {
				if ( answer.ok ) {
					selected = answer.data.selected || [];
					main = selected.length ? selected[ 0 ].id : 0;
				}
				drawSelected();
				drawResults();
			} );
		} else {
			drawSelected();
		}
		search();
	}

	function setupBalance() {
		var box = document.querySelector( '[data-do4u-balance]' );
		if ( box ) {
			post( 'do4u_wp_balance', {} ).then( function ( answer ) {
				box.textContent = answer.data.text || answer.data.message || t( 'network' );
			} ).catch( function () {
				box.textContent = t( 'network' );
			} );
		}
	}

	/* Buttons with data-do4u-confirm ask in an own dialog before their form is sent. */
	function confirmFirst( button ) {
		var dialog = el( 'dialog', 'do4u-confirm-dialog' );
		var text = el( 'p', '', button.getAttribute( 'data-do4u-confirm' ) );
		var actions = el( 'p', 'do4u-confirm-actions' );
		var cancel = el( 'button', 'button', t( 'cancel' ) );
		var ok = el( 'button', 'button button-primary', button.getAttribute( 'data-do4u-confirm-ok' ) || button.value || t( 'confirm' ) );
		text.id = 'do4u-confirm-text';
		dialog.setAttribute( 'aria-modal', 'true' );
		dialog.setAttribute( 'aria-labelledby', text.id );
		cancel.type = 'button';
		ok.type = 'button';
		if ( button.classList.contains( 'delete' ) ) {
			ok.classList.add( 'is-destructive' );
		}
		actions.appendChild( cancel );
		actions.appendChild( ok );
		dialog.appendChild( text );
		dialog.appendChild( actions );
		cancel.addEventListener( 'click', function () {
			dialog.close( 'cancel' );
		} );
		ok.addEventListener( 'click', function () {
			dialog.close( 'ok' );
		} );
		dialog.addEventListener( 'keydown', function ( event ) {
			if ( 'Tab' !== event.key ) {
				return;
			}
			if ( event.shiftKey && document.activeElement === cancel ) {
				event.preventDefault();
				ok.focus();
			} else if ( ! event.shiftKey && document.activeElement === ok ) {
				event.preventDefault();
				cancel.focus();
			}
		} );
		dialog.addEventListener( 'close', function () {
			var yes = 'ok' === dialog.returnValue;
			dialog.remove();
			if ( yes ) {
				button.setAttribute( 'data-do4u-confirmed', '1' );
				button.click();
			} else {
				button.focus();
			}
		} );
		document.body.appendChild( dialog );
		dialog.showModal();
		cancel.focus();
	}

	document.addEventListener( 'click', function ( event ) {
		var ask = event.target.closest && event.target.closest( '[data-do4u-confirm]' );
		if ( ask && 'function' === typeof HTMLDialogElement ) {
			if ( ask.getAttribute( 'data-do4u-confirmed' ) ) {
				ask.removeAttribute( 'data-do4u-confirmed' );
				return;
			}
			event.preventDefault();
			confirmFirst( ask );
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest && event.target.closest( '[data-do4u-open]' );
		if ( ! trigger ) {
			return;
		}
		var request = parse( trigger.getAttribute( 'data-do4u-open' ) );
		if ( request ) {
			event.preventDefault();
			openDialog( request );
		}
	} );

	function init() {
		setupClassicEditor();
		setupCatalogFilter();
		setupBalance();
		document.querySelectorAll( '[data-do4u-panel]' ).forEach( function ( panel ) {
			var request = parse( panel.getAttribute( 'data-do4u-panel' ) );
			if ( request ) {
				mount( panel, request, function () {} );
			}
		} );
		setupPicker();
	}

	/* For do4u-editor.js. */
	window.Do4uWp = {
		openDialog: openDialog,
		liveFromHtml: liveFromHtml,
		useEditor: function ( live, adapters ) {
			editor.live = live;
			editor.adapters = adapters;
		}
	};

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
