/* Admin JS for Maintenance & Coming Soon by MOSERLX */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var btn      = document.getElementById( 'mlx-mcs-add-ip' );
		var textarea = document.getElementById( 'mlx_mcs_ip_whitelist' );

		if ( ! btn || ! textarea ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			var ip      = ( btn.dataset.ip || '' ).trim();
			var current = textarea.value.trim();

			if ( ! ip ) {
				return;
			}

			// Avoid adding the same IP twice.
			var lines = current ? current.split( '\n' ).map( function ( l ) { return l.trim(); } ) : [];
			if ( lines.indexOf( ip ) !== -1 ) {
				return;
			}

			textarea.value = current ? current + '\n' + ip : ip;
			textarea.focus();
		} );
	} );
}() );
