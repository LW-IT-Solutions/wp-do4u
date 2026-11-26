/**
 * do4u.eu - widget loader, shipped with the plugin (same behaviour as https://do4u.eu/js/do4u-widget.js).
 *
 * Turns <div data-do4u-widget="w_..." data-tool="..." data-token="..." data-base="https://do4u.eu"> into the
 * iframe of the do4u.eu tool, adjusts its height and reports results as the event do4u:result on that element.
 * Messages are only accepted from that iframe and from the do4u.eu origin. Nothing is loaded from do4u.eu
 * before an element with data-do4u-widget is on the page (do4u-admin.js adds it when a tool is opened).
 */
( function () {
	'use strict';

	var BASE_PATTERN = /^https:\/\/[a-z0-9.-]+$/;

	/** Builds the iframe for one element. */
	function build( element ) {
		if ( element.dataset.do4uReady ) {
			return;
		}
		// The address of the do4u.eu service comes from the plugin (Do4u_Api::site()); without it nothing is loaded.
		var base = element.dataset.base || '';
		if ( ! BASE_PATTERN.test( base ) ) {
			return;
		}
		var widgetId = element.getAttribute( 'data-do4u-widget' );
		var tool = element.dataset.tool || '';
		var token = element.dataset.token || '';
		if ( ! /^w_[0-9a-f]{16}$/.test( widgetId ) || ! /^[a-z0-9_]{2,40}$/.test( tool ) ) {
			return;
		}
		element.dataset.do4uReady = '1';

		var query = new URLSearchParams();
		if ( element.dataset.lang ) {
			query.set( 'lang', element.dataset.lang );
		}
		if ( element.dataset.theme ) {
			query.set( 'theme', element.dataset.theme );
		}
		var frame = document.createElement( 'iframe' );
		frame.title = element.dataset.title || 'do4u.eu';
		frame.allow = 'clipboard-write';
		frame.referrerPolicy = 'origin';
		frame.style.cssText = 'display:block;width:100%;border:0;min-height:' + ( parseInt( element.dataset.minHeight, 10 ) || 480 ) + 'px';
		frame.src = base + '/widget/' + widgetId + '/' + tool + ( query.toString() ? '?' + query : '' ) + ( token ? '#t=' + encodeURIComponent( token ) : '' );
		// From now on the session token is only in the address fragment of the iframe.
		element.removeAttribute( 'data-token' );
		element.appendChild( frame );

		function report( type, detail ) {
			element.dispatchEvent( new CustomEvent( type, { bubbles: true, detail: detail } ) );
		}
		function send( message ) {
			frame.contentWindow.postMessage( Object.assign( { source: 'do4u-host' }, message ), base );
		}
		element.do4uWidget = {
			fill: function ( fields ) {
				send( { type: 'fill', fields: fields } );
			},
			setToken: function ( newToken ) {
				send( { type: 'token', token: newToken } );
			},
			frame: frame
		};

		window.addEventListener( 'message', function ( event ) {
			if ( event.origin !== base || event.source !== frame.contentWindow || ! event.data || event.data.source !== 'do4u-widget' ) {
				return;
			}
			var data = event.data;
			if ( data.type === 'height' && typeof data.height === 'number' ) {
				frame.style.height = Math.max( 120, Math.min( data.height, 20000 ) ) + 'px';
			} else if ( data.type === 'ready' ) {
				report( 'do4u:ready', { widget: data.widget, tool: data.tool } );
			} else if ( data.type === 'result' ) {
				report( 'do4u:result', { widget: data.widget, tool: data.tool, result: data.result, credits: data.credits } );
			} else if ( data.type === 'expired' ) {
				report( 'do4u:expired', { widget: data.widget, tool: data.tool } );
				// A new session token comes from this WordPress site (admin-ajax, action do4u_wp_session).
				if ( element.dataset.sessionUrl ) {
					fetch( element.dataset.sessionUrl, { method: 'POST', credentials: 'same-origin' } )
						.then( function ( response ) {
							return response.ok ? response.json() : null;
						} )
						.then( function ( json ) {
							if ( json && json.token ) {
								send( { type: 'token', token: json.token } );
							}
						} );
				}
			}
		} );
	}

	function scanAll() {
		document.querySelectorAll( '[data-do4u-widget]' ).forEach( build );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', scanAll );
	} else {
		scanAll();
	}
	window.Do4UWidgets = { scan: scanAll };
}() );
