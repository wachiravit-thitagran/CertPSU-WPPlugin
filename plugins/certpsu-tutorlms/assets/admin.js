/* global document */
( function () {
	'use strict';

	/**
	 * Endorser repeater: add/remove rows. Each repeater stores a row template in
	 * a <script type="text/html"> element using the placeholder __i__ for the
	 * row index.
	 */
	function initRepeaters() {
		var repeaters = document.querySelectorAll( '.certpsu-endorsers' );

		repeaters.forEach(
			function ( repeater ) {
				var rows   = repeater.querySelector( '.certpsu-endorsers-rows' );
				var tmpl   = repeater.querySelector( '.certpsu-endorser-template' );
				var addBtn = repeater.querySelector( '.certpsu-add-endorser' );

				if ( ! rows || ! tmpl || ! addBtn ) {
						return;
				}

				addBtn.addEventListener(
					'click',
					function () {
						var index         = rows.querySelectorAll( '.certpsu-endorser-row' ).length;
						var html          = tmpl.textContent.replace( /__i__/g, String( index ) );
						var wrapper       = document.createElement( 'div' );
						wrapper.innerHTML = html.trim();
						var node          = wrapper.firstChild;
						if ( node ) {
							rows.appendChild( node );
						}
					}
				);

				rows.addEventListener(
					'click',
					function ( event ) {
						var target = event.target;
						if ( target && target.classList.contains( 'certpsu-remove-endorser' ) ) {
								event.preventDefault();
								var row = target.closest( '.certpsu-endorser-row' );
							if ( row ) {
								row.parentNode.removeChild( row );
							}
						}
					}
				);
			}
		);
	}

	if ( document.readyState !== 'loading' ) {
		initRepeaters();
	} else {
		document.addEventListener( 'DOMContentLoaded', initRepeaters );
	}
} )();
