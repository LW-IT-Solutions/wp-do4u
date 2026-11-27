/**
 * do4u.eu - shop tools page: confirmations, selection helpers and the progress of a running batch.
 * The page works without this script; it only adds comfort.
 */
( function () {
	'use strict';

	var cfg = window.do4uShopAdmin || {};
	var i18n = cfg.i18n || {};
	// Fills %d, %1$d and %2$d of a translated text.
	function fill( text, a, b ) {
		return String( text || '' ).replace( '%1$d', a ).replace( '%2$d', b ).replace( '%d', a );
	}

	/*
	 * Confirmation before buttons that use credits, delete or overwrite: a native modal dialog of the page (focus kept
	 * inside, Escape cancels, Enter on the focused button confirms). After "Continue" the button is pressed again.
	 */
	function confirmDialog( message, onConfirm ) {
		var dialog = document.createElement( 'dialog' );
		dialog.className = 'do4u-shop-dialog';
		dialog.setAttribute( 'aria-labelledby', 'do4u-shop-dialog-text' );
		var text = document.createElement( 'p' );
		text.id = 'do4u-shop-dialog-text';
		text.textContent = message;
		var actions = document.createElement( 'p' );
		actions.className = 'do4u-shop-dialog-actions';
		var cancel = document.createElement( 'button' );
		cancel.type = 'button';
		cancel.className = 'button';
		cancel.textContent = i18n.cancel || 'Cancel';
		var ok = document.createElement( 'button' );
		ok.type = 'button';
		ok.className = 'button button-primary';
		ok.textContent = i18n.confirm || 'Continue';
		actions.append( cancel, ok );
		dialog.append( text, actions );
		document.body.append( dialog );
		dialog.addEventListener( 'close', function () {
			dialog.remove();
			if ( 'ok' === dialog.returnValue ) {
				onConfirm();
			}
		} );
		cancel.addEventListener( 'click', function () {
			dialog.close( 'cancel' );
		} );
		ok.addEventListener( 'click', function () {
			dialog.close( 'ok' );
		} );
		dialog.showModal();
		ok.focus();
	}

	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest ? e.target.closest( '[data-do4u-shop-confirm]' ) : null;
		if ( ! b || b.getAttribute( 'data-do4u-shop-confirmed' ) ) {
			return;
		}
		e.preventDefault();
		confirmDialog( b.getAttribute( 'data-do4u-shop-confirm' ), function () {
			b.setAttribute( 'data-do4u-shop-confirmed', '1' );
			b.click();
			b.removeAttribute( 'data-do4u-shop-confirmed' );
		} );
	} );

	// Product choice: select all, count against the batch size.
	var all = document.querySelector( '.do4u-shop-check-all' );
	var count = document.querySelector( '.do4u-shop-count' );
	var boxes = document.querySelectorAll( '.do4u-shop-products input[name="products[]"]' );
	function updateCount() {
		if ( ! count ) {
			return;
		}
		var n = 0;
		boxes.forEach( function ( b ) {
			if ( b.checked ) {
				n++;
			}
		} );
		var max = parseInt( count.getAttribute( 'data-max' ), 10 ) || 0;
		count.textContent = fill( i18n.selected, n );
		count.classList.toggle( 'is-over', max > 0 && n > max );
	}
	if ( all ) {
		all.addEventListener( 'change', function () {
			boxes.forEach( function ( b ) {
				b.checked = all.checked;
			} );
			updateCount();
		} );
	}
	boxes.forEach( function ( b ) {
		b.addEventListener( 'change', updateCount );
	} );
	updateCount();

	// Tool cards: mark the chosen one.
	document.querySelectorAll( '.do4u-shop-tool input' ).forEach( function ( r ) {
		r.addEventListener( 'change', function () {
			document.querySelectorAll( '.do4u-shop-tool' ).forEach( function ( l ) {
				l.classList.toggle( 'is-selected', l.contains( r ) && r.checked );
			} );
			// Fields and options depend on the tool: show them for the new choice.
			if ( r.form ) {
				r.form.submit();
			}
		} );
	} );

	// Review: tick all without warnings, untick all.
	document.querySelectorAll( '[data-do4u-shop-tick]' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var mode = btn.getAttribute( 'data-do4u-shop-tick' );
			document.querySelectorAll( '.do4u-shop-review input[type="checkbox"][name^="apply["]' ).forEach( function ( b ) {
				b.checked = 'safe' === mode && '1' === b.getAttribute( 'data-do4u-shop-safe' );
			} );
		} );
	} );

	// Product choice: the buttons of the text overview tick the products whose field is empty.
	document.querySelectorAll( '[data-do4u-pick]' ).forEach( function ( btn ) {
		btn.hidden = false;
		btn.addEventListener( 'click', function () {
			var field = btn.getAttribute( 'data-do4u-pick' );
			document.querySelectorAll( '.do4u-shop-products tbody tr' ).forEach( function ( tr ) {
				var box = tr.querySelector( 'input[name="products[]"]' );
				if ( box ) {
					box.checked = ( ' ' + ( tr.getAttribute( 'data-do4u-gaps' ) || '' ) + ' ' ).indexOf( ' ' + field + ' ' ) !== -1;
					tr.classList.toggle( 'is-picked', box.checked );
				}
			} );
			updateCount();
		} );
	} );

	/*
	 * Review: one chip per entry (state at a glance, jumps to the entry), a live count of ticked fields and the
	 * number of words of each suggestion against the current text.
	 */
	var review = document.querySelector( '.do4u-shop-review' );
	var overview = document.querySelector( '[data-do4u-shop-overview]' );
	var ticked = document.querySelector( '[data-do4u-shop-ticked]' );
	function words( node ) {
		if ( ! node || node.classList.contains( 'is-empty' ) ) {
			return 0;
		}
		var copy = node.cloneNode( true );
		copy.querySelectorAll( '.do4u-shop-meta' ).forEach( function ( m ) {
			m.remove();
		} );
		return copy.textContent.split( /\s+/ ).filter( Boolean ).length;
	}
	function refresh() {
		var fields = 0;
		var entries = 0;
		var list = overview ? overview.querySelector( 'ol' ) : null;
		if ( list ) {
			list.textContent = '';
		}
		review.querySelectorAll( '.do4u-shop-item' ).forEach( function ( item ) {
			var boxes = item.querySelectorAll( 'input[type="checkbox"][name^="apply["]' );
			var on = 0;
			boxes.forEach( function ( b ) {
				on += b.checked ? 1 : 0;
			} );
			fields += on;
			entries += on ? 1 : 0;
			if ( ! list ) {
				return;
			}
			var state = 'open';
			var label = i18n.ovOpen;
			if ( item.querySelector( '.do4u-shop-error' ) ) {
				state = 'fail';
				label = i18n.ovFail;
			} else if ( item.classList.contains( 'is-discarded' ) ) {
				state = 'off';
				label = i18n.ovOff;
			} else if ( ! boxes.length ) {
				state = 'done';
				label = i18n.ovDone;
			} else if ( on ) {
				state = item.querySelector( '.do4u-shop-flag' ) && item.querySelector( 'input[data-do4u-shop-safe="0"]:checked' ) ? 'warn' : 'tick';
				label = 'warn' === state ? i18n.ovWarn : fill( i18n.ovTicked, on );
			} else if ( item.querySelector( '.do4u-shop-flag' ) ) {
				state = 'warn';
				label = i18n.ovWarn;
			}
			var heading = item.querySelector( 'h3' );
			var li = document.createElement( 'li' );
			var a = document.createElement( 'a' );
			a.href = '#' + item.id;
			a.className = 'do4u-shop-ov is-' + state;
			var dot = document.createElement( 'span' );
			dot.className = 'do4u-shop-ov-dot';
			dot.setAttribute( 'aria-hidden', 'true' );
			var name = document.createElement( 'span' );
			name.className = 'do4u-shop-ov-name';
			name.textContent = heading ? heading.textContent : '';
			var st = document.createElement( 'span' );
			st.className = 'do4u-shop-ov-state';
			st.textContent = label;
			a.append( dot, name, st );
			li.append( a );
			list.append( li );
		} );
		if ( overview && list && list.children.length > 1 ) {
			overview.hidden = false;
		}
		if ( ticked ) {
			ticked.textContent = fill( i18n.ticked, fields, entries );
		}
	}
	if ( review ) {
		review.querySelectorAll( '.do4u-shop-compare' ).forEach( function ( cmp ) {
			var cols = cmp.querySelectorAll( ':scope > div' );
			if ( cols.length < 2 || ! i18n.words ) {
				return;
			}
			var before = words( cols[ 0 ].querySelector( '.do4u-shop-value' ) );
			var after = words( cols[ 1 ].querySelector( '.do4u-shop-value' ) );
			var diff = after - before;
			var head = cols[ 1 ].querySelector( '.do4u-shop-col' );
			if ( head ) {
				var badge = document.createElement( 'span' );
				badge.className = 'do4u-shop-words' + ( diff > 0 ? ' is-up' : ( diff < 0 ? ' is-down' : '' ) );
				badge.textContent = fill( i18n.words, after, 0 ).replace( '%2$s', ( diff > 0 ? '+' : ( diff < 0 ? '\u2212' : '\u00b1' ) ) + Math.abs( diff ) );
				head.append( ' ', badge );
			}
		} );
		review.addEventListener( 'change', refresh );
		document.querySelectorAll( '[data-do4u-shop-tick]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', refresh );
		} );
		refresh();
	}

	// Progress of a running batch: ask every 15 seconds, reload when the suggestions are ready.
	var box = document.querySelector( '[data-do4u-shop-poll]' );
	if ( box && cfg.ajax && window.fetch && window.FormData ) {
		var bar = box.querySelector( '.do4u-shop-progress' );
		var text = box.querySelector( '.do4u-shop-progress-text' );
		var poll = function () {
			var data = new window.FormData();
			data.append( 'action', 'do4u_shop_status' );
			data.append( '_ajax_nonce', cfg.nonce );
			data.append( 'batch', box.getAttribute( 'data-do4u-shop-poll' ) );
			window.fetch( cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( r ) {
					if ( ! r || ! r.success ) {
						if ( r && r.data && r.data.message && text ) {
							text.textContent = r.data.message;
						}
						window.setTimeout( poll, 30000 );
						return;
					}
					if ( 'running' !== r.data.status && 'starting' !== r.data.status ) {
						window.location.reload();
						return;
					}
					if ( bar && r.data.total ) {
						bar.max = r.data.total;
						bar.value = r.data.done;
					}
					if ( text ) {
						text.textContent = fill( i18n.progress, r.data.done, r.data.total );
					}
					window.setTimeout( poll, 15000 );
				} )
				.catch( function () {
					window.setTimeout( poll, 30000 );
				} );
		};
		window.setTimeout( poll, 5000 );
	}
}() );
