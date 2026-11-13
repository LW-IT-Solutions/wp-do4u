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

	// Confirmation before buttons that use credits, delete or overwrite.
	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest ? e.target.closest( '[data-do4u-shop-confirm]' ) : null;
		if ( b && ! window.confirm( b.getAttribute( 'data-do4u-shop-confirm' ) ) ) {
			e.preventDefault();
		}
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
