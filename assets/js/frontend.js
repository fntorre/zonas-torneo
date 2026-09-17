/**
 * Zonas y Partidos de Fútbol · Frontend
 * Pantalla completa de las llaves (playoffs) con ajuste a pantalla y zoom por rueda del mouse.
 *
 * Todo se maneja por delegación en `document` para que funcione aunque el
 * contenido se renderice tarde (cache, page builders, scripts en head).
 */
( function () {
	'use strict';

	var ESCALA_MIN = 0.08;
	var ESCALA_MAX = 4;
	var PASO_BOTON = 1.25;
	var MARGEN_AJUSTE = 44;
	var FACTOR_RUEDA = 1.0015;

	function fullscreenElement() {
		var doc = document;
		return doc.fullscreenElement || doc.webkitFullscreenElement || doc.mozFullScreenElement || doc.msFullscreenElement || null;
	}

	function pedirFullscreen( el ) {
		var fn = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
		if ( ! fn ) {
			return;
		}
		try {
			var promesa = fn.call( el );
			if ( promesa && typeof promesa.catch === 'function' ) {
				promesa.catch( function () {} );
			}
		} catch ( err ) {} // eslint-disable-line no-empty
	}

	function salirFullscreen() {
		var doc = document;
		var fn = doc.exitFullscreen || doc.webkitExitFullscreen || doc.mozCancelFullScreen || doc.msExitFullscreen;
		if ( fn ) {
			try {
				fn.call( doc );
			} catch ( err ) {} // eslint-disable-line no-empty
		}
	}

	function estadoDe( wrap ) {
		if ( ! wrap.__zfFs ) {
			wrap.__zfFs = { escala: 1 };
		}
		return wrap.__zfFs;
	}

	function esActivo( wrap ) {
		return !! wrap && ( fullscreenElement() === wrap || wrap.classList.contains( 'zf-fs-activo' ) );
	}

	function encontrarWrap( nodo ) {
		if ( ! nodo || typeof nodo.closest !== 'function' ) {
			return null;
		}
		return nodo.closest( '.zf-llave-wrap' );
	}

	function calcularAjuste( wrap ) {
		var scroll = wrap.querySelector( '.zf-llave-scroll' );
		var grid = wrap.querySelector( '.zf-llave-grid' );
		if ( ! scroll || ! grid ) {
			return 1;
		}
		var ajuste = Math.min(
			( scroll.clientWidth - MARGEN_AJUSTE ) / grid.scrollWidth,
			( scroll.clientHeight - MARGEN_AJUSTE ) / grid.scrollHeight,
			1
		);
		return Math.max( ajuste, 0.03 );
	}

	function aplicar( wrap, escala ) {
		var grid = wrap.querySelector( '.zf-llave-grid' );
		var fit = wrap.querySelector( '.zf-llave-fit' );
		var scroll = wrap.querySelector( '.zf-llave-scroll' );
		if ( ! grid || ! fit ) {
			return;
		}

		escala = Math.min( ESCALA_MAX, Math.max( ESCALA_MIN, escala ) );
		estadoDe( wrap ).escala = escala;

		var ancho = Math.round( grid.scrollWidth * escala );
		var alto = Math.round( grid.scrollHeight * escala );
		var mH = 0;
		var mV = 0;
		if ( scroll ) {
			mH = Math.max( 0, Math.round( ( scroll.clientWidth - ancho ) / 2 ) );
			mV = Math.max( 0, Math.round( ( scroll.clientHeight - alto ) / 2 ) );
		}

		grid.style.transform = 'scale(' + escala + ')';
		grid.style.transformOrigin = 'top left';
		fit.style.width = ancho + 'px';
		fit.style.height = alto + 'px';
		fit.style.marginLeft = mH + 'px';
		fit.style.marginTop = mV + 'px';

		var nivel = wrap.querySelector( '.zf-fs-nivel' );
		if ( nivel ) {
			nivel.textContent = Math.round( escala * 100 ) + '%';
		}

		return escala;
	}

	function ajustarEscala( wrap ) {
		var escala = calcularAjuste( wrap );
		estadoDe( wrap ).escala = escala;
		aplicar( wrap, escala );
		var scroll = wrap.querySelector( '.zf-llave-scroll' );
		if ( scroll ) {
			scroll.scrollLeft = 0;
			scroll.scrollTop = 0;
		}
	}

	function limpiar( wrap ) {
		estadoDe( wrap ).escala = 1;

		var fit = wrap.querySelector( '.zf-llave-fit' );
		var grid = wrap.querySelector( '.zf-llave-grid' );
		var scroll = wrap.querySelector( '.zf-llave-scroll' );
		if ( fit ) {
			fit.style.width = '';
			fit.style.height = '';
			fit.style.marginLeft = '';
			fit.style.marginTop = '';
		}
		if ( grid ) {
			grid.style.transform = '';
			grid.style.transformOrigin = '';
		}
		if ( scroll ) {
			scroll.scrollLeft = 0;
			scroll.scrollTop = 0;
		}
		var nivel = wrap.querySelector( '.zf-fs-nivel' );
		if ( nivel ) {
			nivel.textContent = '100%';
		}
	}

	function zoomEn( wrap, px, py, factor ) {
		var scroll = wrap.querySelector( '.zf-llave-scroll' );
		var grid = wrap.querySelector( '.zf-llave-grid' );
		var fit = wrap.querySelector( '.zf-llave-fit' );
		if ( ! scroll || ! grid || ! fit ) {
			return;
		}

		var vieja = estadoDe( wrap ).escala;
		var nueva = aplicar( wrap, vieja * factor );
		if ( nueva === vieja ) {
			return;
		}

		var r = scroll.getBoundingClientRect();
		var offX = fit.getBoundingClientRect().left - r.left;
		var offY = fit.getBoundingClientRect().top - r.top;
		var cx = Math.min( Math.max( px - r.left, 0 ), scroll.clientWidth );
		var cy = Math.min( Math.max( py - r.top, 0 ), scroll.clientHeight );

		var xNatural = ( cx - offX ) / vieja;
		var yNatural = ( cy - offY ) / vieja;

		var r2 = scroll.getBoundingClientRect();
		var offX2 = fit.getBoundingClientRect().left - r2.left;
		var offY2 = fit.getBoundingClientRect().top - r2.top;
		scroll.scrollLeft = offX2 + xNatural * nueva - cx;
		scroll.scrollTop = offY2 + yNatural * nueva - cy;
	}

	function zoomCentrado( wrap, factor ) {
		var scroll = wrap.querySelector( '.zf-llave-scroll' );
		if ( ! scroll ) {
			return;
		}
		var r = scroll.getBoundingClientRect();
		zoomEn( wrap, r.left + scroll.clientWidth / 2, r.top + scroll.clientHeight / 2, factor );
	}

	function entrar( wrap ) {
		wrap.classList.add( 'zf-fs-activo' );
		ajustarEscala( wrap );
		pedirFullscreen( wrap );
	}

	function salir( wrap ) {
		// Limpieza síncrona y determinista: aunque el evento fullscreenchange
		// se retrase o no llegue, el cuadro vuelve a su tamaño natural.
		wrap.classList.remove( 'zf-fs-activo' );
		limpiar( wrap );
		if ( fullscreenElement() === wrap ) {
			salirFullscreen();
		}
	}

	function alternar( wrap ) {
		if ( esActivo( wrap ) ) {
			salir( wrap );
		} else {
			entrar( wrap );
		}
	}

	function sincronizarEstado() {
		try {
			var activo = fullscreenElement();
			document.querySelectorAll( '.zf-llave-wrap' ).forEach( function ( wrap ) {
				if ( activo === wrap ) {
					wrap.classList.add( 'zf-fs-activo' );
					ajustarEscala( wrap );
				} else if ( wrap.classList.contains( 'zf-fs-activo' ) ) {
					wrap.classList.remove( 'zf-fs-activo' );
					limpiar( wrap );
				}
			} );
		} catch ( err ) {} // eslint-disable-line no-empty
	}

	document.addEventListener( 'click', function ( evento ) {
		try {
			var objetivo = evento.target;

			if ( objetivo.closest( '.zf-fs-btn' ) ) {
				var wrapBtn = encontrarWrap( objetivo );
				if ( wrapBtn ) {
					evento.preventDefault();
					alternar( wrapBtn );
				}
				return;
			}

			var contenedor = encontrarWrap( objetivo );
			if ( ! contenedor || ! esActivo( contenedor ) ) {
				return;
			}

			var zoom = objetivo.closest( '.zf-fs-zoom' );
			if ( zoom ) {
				if ( zoom.getAttribute( 'data-zf-zoom' ) === 'in' ) {
					zoomCentrado( contenedor, PASO_BOTON );
				} else {
					zoomCentrado( contenedor, 1 / PASO_BOTON );
				}
				return;
			}

			if ( objetivo.closest( '.zf-fs-restaurar' ) ) {
				ajustarEscala( contenedor );
			}
		} catch ( err ) {} // eslint-disable-line no-empty
	} );

	document.addEventListener( 'wheel', function ( evento ) {
		var wrap = encontrarWrap( evento.target );
		if ( ! wrap ) {
			return;
		}

		var activo = esActivo( wrap );

		// Ctrl+rueda / pellizco de trackpad: zoom en cualquier estado.
		if ( ! activo && ! evento.ctrlKey && ! evento.metaKey ) {
			return;
		}

		evento.preventDefault();
		try {
			var factor = Math.pow( FACTOR_RUEDA, -evento.deltaY );
			zoomEn( wrap, evento.clientX, evento.clientY, factor );
		} catch ( err ) {} // eslint-disable-line no-empty
	}, { passive: false } );

	var eventos = [ 'fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange' ];
	eventos.forEach( function ( nombre ) {
		document.addEventListener( nombre, sincronizarEstado );
	} );

	window.addEventListener( 'resize', function () {
		document.querySelectorAll( '.zf-llave-wrap.zf-fs-activo' ).forEach( ajustarEscala );
	} );
} )();