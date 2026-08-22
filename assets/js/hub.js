/**
 * Zonas y Partidos de Fútbol · Hub
 * Buscador de equipos en vivo (sin dependencias).
 */
( function () {
	'use strict';

	function normalizar( texto ) {
		return ( texto || '' )
			.toString()
			.toLowerCase()
			.normalize( 'NFD' )
			.replace( /[\u0300-\u036f]/g, '' );
	}

	document.addEventListener( 'input', function ( evento ) {
		var entrada = evento.target;
		if ( ! entrada || ! entrada.classList || ! entrada.classList.contains( 'zf-buscador' ) ) {
			return;
		}

		var consulta = normalizar( entrada.value.trim() );
		var contenedor = document.querySelector( '.zf-zonas' );
		if ( ! contenedor ) {
			return;
		}

		var visibles = 0;

		contenedor.querySelectorAll( '.zf-zona-equipos li[data-nombre]' ).forEach( function ( item ) {
			var coincide = ! consulta || normalizar( item.getAttribute( 'data-nombre' ) ).indexOf( consulta ) !== -1;
			item.hidden = ! coincide;
			if ( coincide ) {
				visibles++;
			}
		} );

		contenedor.querySelectorAll( '.zf-zona-card' ).forEach( function ( tarjeta ) {
			var conEquipos = tarjeta.querySelector( '.zf-zona-equipos li[data-nombre]:not([hidden])' );
			tarjeta.hidden = !! consulta && ! conEquipos;
		} );

		var aviso = document.querySelector( '.zf-sin-resultados' );
		if ( aviso ) {
			aviso.hidden = ! ( consulta && 0 === visibles );
		}
	} );
} )();
