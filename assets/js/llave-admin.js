/**
 * Zonas y Partidos de Fútbol · Admin Llave
 * Drag & drop, búsqueda, vista previa del bracket.
 * Sin dependencias externas.
 */
(function () {
	'use strict';

	var STATE = {
		disponibles: [],
		seleccionados: [],
		partidos: {}, // "ronda:slot" -> { local, visitante, gl, gv, pl, pv, ganador, final }
		dragItem: null,
		dragFrom: null
	};

	// ────────────────── Utilidades ──────────────────

	function normalizar(t) {
		return (t || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
	}

	function calcularCuadro(n) {
		var s = 2;
		while (s < n) s *= 2;
		return s;
	}

	function etiquetaRonda(size, ronda) {
		var restantes = Math.max(1, size >> (ronda + 1));
		var mapa = {
			1: 'Final',
			2: 'Semifinales',
			4: 'Cuartos de final',
			8: 'Octavos de final',
			16: 'Dieciseisavos',
			32: 'Treintaidosavos'
		};
		return mapa[restantes] || ('Ronda ' + (ronda + 1));
	}

	// ────────────────── Render ──────────────────

	function renderDisponibles() {
		var lista = document.getElementById('zf-ll-disponibles');
		if (!lista) return;
		var buscar = document.getElementById('zf-ll-buscar');
		var query = buscar ? normalizar(buscar.value.trim()) : '';

		lista.innerHTML = '';
		var visibles = 0;

		STATE.disponibles.forEach(function (eq) {
			if (query && normalizar(eq.nombre).indexOf(query) === -1) return;
			visibles++;
			var el = document.createElement('div');
			el.className = 'zf-ll-item';
			el.draggable = true;
			el.dataset.id = eq.id;

			el.innerHTML =
				'<span class="zf-ll-drag" title="Arrastrar">⠿</span>' +
				'<span class="zf-ll-avatar">' + eq.avatar + '</span>' +
				'<span class="zf-ll-nombre">' + eq.nombre + '</span>' +
				'<button type="button" class="zf-ll-btn zf-ll-btn-add" title="Agregar">+</button>';

			el.addEventListener('dragstart', onDragStart);
			el.addEventListener('dragend', onDragEnd);
			el.querySelector('.zf-ll-btn-add').addEventListener('click', function () {
			 moverASeleccionados(eq.id);
			});

			lista.appendChild(el);
		});

		if (visibles === 0 && query) {
			lista.innerHTML = '<div class="zf-ll-vacio">No se encontraron equipos</div>';
		}

		var badge = document.getElementById('zf-ll-disp-count');
		if (badge) badge.textContent = STATE.disponibles.length;
	}

	function renderSeleccionados() {
		var lista = document.getElementById('zf-ll-seleccionados');
		if (!lista) return;

		lista.innerHTML = '';

		STATE.seleccionados.forEach(function (eq, idx) {
			var el = document.createElement('div');
			el.className = 'zf-ll-item zf-ll-item-sel';
			el.draggable = true;
			el.dataset.id = eq.id;
			el.dataset.pos = idx;

			el.innerHTML =
				'<span class="zf-ll-drag" title="Reordenar">⠿</span>' +
				'<span class="zf-ll-pos">' + (idx + 1) + '</span>' +
				'<span class="zf-ll-avatar">' + eq.avatar + '</span>' +
				'<span class="zf-ll-nombre">' + eq.nombre + '</span>' +
				'<button type="button" class="zf-ll-btn zf-ll-btn-remove" title="Quitar">✕</button>';

			el.addEventListener('dragstart', onDragStart);
			el.addEventListener('dragend', onDragEnd);
			el.addEventListener('dragover', onDragOverSeleccionados);
			el.addEventListener('drop', onDropEnSeleccionados);
			el.querySelector('.zf-ll-btn-remove').addEventListener('click', function () {
				moverADisponibles(eq.id);
			});

			lista.appendChild(el);
		});

		// Drop zone: vaciar lista para drop al final
		lista.addEventListener('dragover', onDragOverSeleccionados);
		lista.addEventListener('drop', onDropEnSeleccionados);

		var badge = document.getElementById('zf-ll-sel-count');
		if (badge) badge.textContent = STATE.seleccionados.length;

		actualizarInfo();
		serializar();
		renderPreview();
	}

	function actualizarInfo() {
		var info = document.getElementById('zf_cuadro_info');
		var toggle = document.getElementById('zf_toggle_todos');
		var n = STATE.seleccionados.length;
		var total = STATE.disponibles.length + n;

		if (toggle) {
			var span = toggle.closest('.zf-equipos-toggle-all');
			if (span) {
				var spanText = span.querySelector('span');
				if (spanText) spanText.textContent = 'Seleccionar todos (' + n + ' / ' + total + ')';
			}
			toggle.checked = n > 0 && n === total;
		}

		if (info) {
			if (n >= 2) {
				var cuadro = calcularCuadro(n);
				var byes = cuadro - n;
				var textoByes = byes === 1 ? ' \u00b7 1 pasa directo' : byes > 1 ? ' \u00b7 ' + byes + ' pasan directo' : '';
				info.textContent = n + ' equipos \u2192 Cuadro de ' + cuadro + textoByes;
				info.classList.remove('zf-equipos-cuadro--warn');
			} else if (n === 1) {
				info.textContent = '1 equipo seleccionado';
				info.classList.add('zf-equipos-cuadro--warn');
			} else {
				info.textContent = 'Ningún equipo seleccionado';
				info.classList.add('zf-equipos-cuadro--warn');
			}
		}
	}

	function serializar() {
		var input = document.getElementById('zf-ll-serialized');
		if (!input) {
			input = document.createElement('input');
			input.type = 'hidden';
			input.name = 'zf_llave_equipos';
			input.id = 'zf-ll-serialized';
			var form = document.querySelector('.zf-llave-two-panel');
			if (form) form.parentNode.insertBefore(input, form);
		}
		var ids = STATE.seleccionados.map(function (eq) { return eq.id; });
		// Crear inputs individuales para cada ID (compatibilidad con array POST)
		var container = document.getElementById('zf-ll-hidden-container');
		if (!container) {
			container = document.createElement('div');
			container.id = 'zf-ll-hidden-container';
			container.style.display = 'none';
			var form2 = document.querySelector('.zf-llave-two-panel');
			if (form2) form2.parentNode.insertBefore(container, form2);
		}
		container.innerHTML = '';
		ids.forEach(function (id) {
			var h = document.createElement('input');
			h.type = 'hidden';
			h.name = 'zf_llave_equipos[]';
			h.value = id;
			container.appendChild(h);
		});
	}

	// ────────────────── Movimiento entre listas ──────────────────

	function moverASeleccionados(id) {
		var idx = -1;
		for (var i = 0; i < STATE.disponibles.length; i++) {
			if (STATE.disponibles[i].id === id) { idx = i; break; }
		}
		if (idx === -1) return;
		var eq = STATE.disponibles.splice(idx, 1)[0];
		STATE.seleccionados.push(eq);
		renderDisponibles();
		renderSeleccionados();
	}

	function moverADisponibles(id) {
		var idx = -1;
		for (var i = 0; i < STATE.seleccionados.length; i++) {
			if (STATE.seleccionados[i].id === id) { idx = i; break; }
		}
		if (idx === -1) return;
		var eq = STATE.seleccionados.splice(idx, 1)[0];
		STATE.disponibles.push(eq);
		STATE.disponibles.sort(function (a, b) { return a.nombre.localeCompare(b.nombre); });
		renderDisponibles();
		renderSeleccionados();
	}

	function seleccionarTodos() {
		STATE.disponibles.forEach(function (eq) { STATE.seleccionados.push(eq); });
		STATE.disponibles = [];
		renderDisponibles();
		renderSeleccionados();
	}

	function limpiarSeleccion() {
		STATE.seleccionados.forEach(function (eq) { STATE.disponibles.push(eq); });
		STATE.seleccionados = [];
		STATE.disponibles.sort(function (a, b) { return a.nombre.localeCompare(b.nombre); });
		renderDisponibles();
		renderSeleccionados();
	}

	// ────────────────── Drag & Drop ──────────────────

	function onDragStart(e) {
		STATE.dragItem = this.dataset.id;
		STATE.dragFrom = this.closest('.zf-ll-lista') ? this.closest('.zf-ll-lista').id : '';
		this.classList.add('is-dragging');
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', this.dataset.id);
	}

	function onDragEnd() {
		this.classList.remove('is-dragging');
		STATE.dragItem = null;
		STATE.dragFrom = null;
		document.querySelectorAll('.zf-ll-item.is-over').forEach(function (el) {
			el.classList.remove('is-over');
		});
	}

	function onDragOverSeleccionados(e) {
		e.preventDefault();
		e.dataTransfer.dropEffect = 'move';

		// Highlight del item debajo del cursor
		var target = e.target.closest('.zf-ll-item-sel');
		document.querySelectorAll('.zf-ll-item.is-over').forEach(function (el) {
			el.classList.remove('is-over');
		});
		if (target && target.dataset.id !== STATE.dragItem) {
			target.classList.add('is-over');
		}
	}

	function onDropEnSeleccionados(e) {
		e.preventDefault();
		var draggedId = parseInt(STATE.dragItem || e.dataTransfer.getData('text/plain'), 10);
		if (!draggedId) return;

		// Si viene de disponibles, mover a seleccionados
		if (STATE.dragFrom === 'zf-ll-disponibles') {
			var idxD = -1;
			for (var i = 0; i < STATE.disponibles.length; i++) {
				if (STATE.disponibles[i].id === draggedId) { idxD = i; break; }
			}
			if (idxD === -1) return;
			var eq = STATE.disponibles.splice(idxD, 1)[0];

			// Encontrar posición de inserción
			var target = e.target.closest('.zf-ll-item-sel');
			if (target) {
				var targetId = parseInt(target.dataset.id, 10);
				var targetPos = -1;
				for (var j = 0; j < STATE.seleccionados.length; j++) {
					if (STATE.seleccionados[j].id === targetId) { targetPos = j; break; }
				}
				if (targetPos >= 0) {
					STATE.seleccionados.splice(targetPos, 0, eq);
				} else {
					STATE.seleccionados.push(eq);
				}
			} else {
				STATE.seleccionados.push(eq);
			}
		} else if (STATE.dragFrom === 'zf-ll-seleccionados') {
			// Reordenar dentro de seleccionados
			var idxS = -1;
			for (var k = 0; k < STATE.seleccionados.length; k++) {
				if (STATE.seleccionados[k].id === draggedId) { idxS = k; break; }
			}
			if (idxS === -1) return;
			var eqMoved = STATE.seleccionados.splice(idxS, 1)[0];

			var target2 = e.target.closest('.zf-ll-item-sel');
			if (target2) {
				var tId = parseInt(target2.dataset.id, 10);
				var tPos = -1;
				for (var m = 0; m < STATE.seleccionados.length; m++) {
					if (STATE.seleccionados[m].id === tId) { tPos = m; break; }
				}
				if (tPos >= 0) {
					STATE.seleccionados.splice(tPos, 0, eqMoved);
				} else {
					STATE.seleccionados.push(eqMoved);
				}
			} else {
				STATE.seleccionados.push(eqMoved);
			}
		}

		renderDisponibles();
		renderSeleccionados();
	}

	// ────────────────── Bracket Preview ──────────────────

	var PV_GAP_MIN = 14; // separación mínima entre tarjetas
	var PV_PAD = 6; // padding vertical interno de cada columna

	// Alto de tarjeta según el tamaño del cuadro (los cuadros grandes se compactan).
	function alturaTarjeta(size) {
		if (size >= 32) return 58;
		if (size >= 16) return 64;
		return 72;
	}

	// Escapa texto para insertarlo como HTML.
	function esc(t) {
		return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function renderPreview() {
		var contenedor = document.getElementById('zf-ll-preview');
		if (!contenedor) return;

		var n = STATE.seleccionados.length;
		if (n < 2) {
			contenedor.innerHTML = '<p class="zf-ll-preview-vacio">Seleccioná al menos 2 equipos para ver la vista previa del cuadro.</p>';
			return;
		}

		var size = calcularCuadro(n);
		var rondas = Math.round(Math.log(size) / Math.log(2));
		var rondasData = generarRondas(STATE.seleccionados, size, rondas);

		contenedor.innerHTML = buildPreview(rondasData, size);
	}

	// Construye todas las rondas del cuadro. Los byes avanzan solos a la ronda siguiente.
	function generarRondas(equipos, size, rondas) {
		// Seedings estándar (orden espejo).
		var orden = [1];
		while (orden.length < size) {
			var sig = [];
			var espejo = orden.length * 2 + 1;
			for (var t = 0; t < orden.length; t++) {
				sig.push(orden[t]);
				sig.push(espejo - orden[t]);
			}
			orden = sig;
		}

		var slots = [];
		for (var i = 0; i < size; i++) {
			slots.push(i < equipos.length ? equipos[i] : null);
		}

		var rondasData = [];
		var primera = [];
		for (var e = 0; e < size; e += 2) {
			var a = slots[orden[e] - 1];
			var b = slots[orden[e + 1] - 1];
			if (a && b) {
				primera.push({ a: a, b: b });
			} else {
				primera.push({ a: a || b, b: null, bye: true });
			}
		}
		rondasData.push(primera);

		for (var r = 1; r < rondas; r++) {
			var prev = rondasData[r - 1];
			var cur = [];
			for (var j = 0; j < prev.length; j += 2) {
				var wA = prev[j].bye ? prev[j].a : null;
				var wB = prev[j + 1].bye ? prev[j + 1].a : null;
				cur.push({ a: wA, b: wB });
			}
			rondasData.push(cur);
		}
		return rondasData;
	}

	// Cuadro espejado: mitad izquierda, final + campeón al centro, mitad derecha.
	function buildPreview(rondasData, size) {
		var totalRondas = rondasData.length;
		var cardH = alturaTarjeta(size);

		// Dividir cada ronda previa en sus dos mitades.
		var mitades = [];
		for (var r = 0; r < totalRondas - 1; r++) {
			var mitad = Math.floor(rondasData[r].length / 2);
			mitades.push({ izq: rondasData[r].slice(0, mitad), der: rondasData[r].slice(mitad) });
		}

		// Altura común = la columna lateral más alta (las dos mitades se espejan).
		var maxH = cardH + PV_PAD * 2;
		mitades.forEach(function (m) {
			[m.izq, m.der].forEach(function (set) {
				var h = set.length * cardH + (set.length - 1) * PV_GAP_MIN + PV_PAD * 2;
				if (h > maxH) maxH = h;
			});
		});

		function gapDe(set) {
			if (set.length < 2) return 0;
			return (maxH - PV_PAD * 2 - set.length * cardH) / (set.length - 1);
		}

		var html = '<div class="zf-ll-preview-bracket zf-ll-preview-mirror">';

		// Lado izquierdo: primera mitad de cada ronda.
		for (var rI = 0; rI < totalRondas - 1; rI++) {
			html += buildRonda(mitades[rI].izq, gapDe(mitades[rI].izq), maxH, size, rI, false, false, 0);
		}

		// Centro: final + campeón.
		var fin = totalRondas - 1;
		html += '<div class="zf-ll-preview-center">';
		html += buildRonda(rondasData[fin], 0, 0, size, fin, true, false, 0);
		html += buildCampeon();
		html += '</div>';

		// Lado derecho: segunda mitad, espejada (de afuera hacia la final).
		for (var rD = totalRondas - 2; rD >= 0; rD--) {
			html += buildRonda(mitades[rD].der, gapDe(mitades[rD].der), maxH, size, rD, false, true, mitades[rD].izq.length);
		}

		html += '</div>';
		return html;
	}

	function buildRonda(set, gap, alto, size, r, esFinal, esDerecha, slotBase) {
		var cls = 'zf-ll-preview-round';
		cls += esFinal ? ' is-final' : (esDerecha ? ' is-right' : ' is-left');

		var html = '<div class="' + cls + '">';
		html += '<div class="zf-ll-preview-rhead">';
		html += '<span class="zf-ll-preview-rname">' + etiquetaRonda(size, r) + '</span>';
		html += '<span class="zf-ll-preview-rcount">' + set.length + (set.length === 1 ? ' cruce' : ' cruces') + '</span>';
		html += '</div>';

		var estilo = esFinal
			? 'style="justify-content:center;"'
			: 'style="height:' + alto + 'px; gap:' + gap.toFixed(1) + 'px; padding:' + PV_PAD + 'px 0;"';

		html += '<div class="zf-ll-preview-matches"' + estilo + '>';
		for (var m = 0; m < set.length; m++) {
			html += buildCard(set[m], esFinal, r, slotBase + m);
		}
		html += '</div></div>';
		return html;
	}

	function buildCard(match, esFinal, r, slot) {
		var key = r + ':' + slot;
		var res = STATE.partidos[key] || null;

		var cls = 'zf-ll-preview-card' + (esFinal ? ' is-final' : '');
		if (res && res.final) cls += ' is-played';

		var html = '<div class="' + cls + '">';

		if (res) {
			// Partido real cargado en el fixture oficial.
			var wL = res.ganador && res.local === res.ganador;
			var wV = res.ganador && res.visitante === res.ganador;
			html += buildResultTeam(res.local, res.gl, res.final, wL);
			html += buildResultTeam(res.visitante, res.gv, res.final, wV);

			if (res.pl !== res.pv && res.final && res.gl === res.gv) {
				var pen = Math.max(res.pl, res.pv);
				html += '<div class="zf-ll-preview-pen">P ' + pen + '</div>';
			}
		} else if (match && match.bye) {
			html += buildTeam(match.a);
			html += '<div class="zf-ll-preview-bye"><span>BYE</span></div>';
		} else if (match) {
			html += match.a ? buildTeam(match.a) : buildPending();
			html += match.b ? buildTeam(match.b) : buildPending();
		}
		html += '</div>';
		return html;
	}

	// Fila de equipo dentro de una tarjeta con resultado real.
	function buildResultTeam(equipoId, goles, finalizado, esGanador) {
		var nombre = '';
		var avatar = '';
		var pendiente = false;

		STATE.disponibles.concat(STATE.seleccionados).forEach(function (eq) {
			if (eq.id === equipoId) {
				nombre = eq.nombre;
				avatar = eq.avatar;
			}
		});

		if (!nombre) {
			nombre = '';
			pendiente = true;
		}

		var cls = 'zf-ll-preview-team';
		if (pendiente) cls += ' is-pending';
		else if (esGanador) {
			cls += ' is-winner';
		} else if (finalizado) {
			cls += ' is-loser';
		}

		var gol = (finalizado && !pendiente) ? '<b class="zf-ll-preview-score">' + goles + '</b>' : '';
		var av = pendiente ? '<span class="zf-ll-preview-avatar zf-ll-preview-avatar-empty" aria-hidden="true"></span>' : '<span class="zf-ll-preview-avatar">' + avatar + '</span>';

		return '<div class="' + cls + '">' + av + '<span class="zf-ll-preview-name">' + (pendiente ? 'A definir' : esc(nombre)) + '</span>' + gol + '</div>';
	}

	function buildTeam(eq) {
		return '<div class="zf-ll-preview-team">' +
			'<span class="zf-ll-preview-avatar">' + eq.avatar + '</span>' +
			'<span class="zf-ll-preview-name">' + esc(eq.nombre) + '</span>' +
			'</div>';
	}

	function buildPending() {
		return '<div class="zf-ll-preview-team is-pending">' +
			'<span class="zf-ll-preview-avatar zf-ll-preview-avatar-empty" aria-hidden="true"></span>' +
			'<span class="zf-ll-preview-name">Por definir</span>' +
			'</div>';
	}

	function buildCampeon() {
		return '<div class="zf-ll-preview-campeon">' +
			'<span class="zf-ll-preview-trofeo" aria-hidden="true">🏆</span>' +
			'<span class="zf-ll-preview-campeon-label">Campeón</span>' +
			'</div>';
	}

	// ────────────────── Inicialización ──────────────────

	function init() {
		var panel = document.getElementById('zf-llave-admin-root');
		if (!panel) return;

		// Read data from PHP-provided globals.
		var allTeams = (typeof ZF_LL_EQUIPOS !== 'undefined') ? ZF_LL_EQUIPOS : [];
		var selIds = (typeof ZF_LL_SELECCIONADOS !== 'undefined') ? ZF_LL_SELECCIONADOS : [];
		var selMap = {};

		// Resultados del fixture ya generado (llaves preexistentes).
		STATE.partidos = (typeof ZF_LL_PARTIDOS !== 'undefined') ? ZF_LL_PARTIDOS : {};
		selIds.forEach(function (id) { selMap[id] = true; });

		allTeams.forEach(function (eq) {
			var team = {
				id: eq.id,
				nombre: eq.nombre,
				avatar: eq.avatar
			};
			if (selMap[eq.id]) {
				// Preserve order from selIds.
				STATE.seleccionados.push(team);
			} else {
				STATE.disponibles.push(team);
			}
		});

		// Sort disponibles alphabetically.
		STATE.disponibles.sort(function (a, b) { return a.nombre.localeCompare(b.nombre); });

		// Event listeners
		var buscar = document.getElementById('zf-ll-buscar');
		if (buscar) {
			buscar.addEventListener('input', renderDisponibles);
		}

		var btnToggle = document.getElementById('zf_toggle_todos');
		if (btnToggle) {
			btnToggle.addEventListener('change', function () {
				if (btnToggle.checked) {
					seleccionarTodos();
				} else {
					limpiarSeleccion();
				}
			});
		}

		var btnLimpiar = document.getElementById('zf-ll-clear');
		if (btnLimpiar) {
			btnLimpiar.addEventListener('click', limpiarSeleccion);
		}

		// Render inicial
		renderDisponibles();
		renderSeleccionados();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();
