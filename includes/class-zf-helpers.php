<?php
/**
 * Funciones auxiliares: consultas, tabla de posiciones y render parcial.
 *
 * @package ZonasFutbol
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase ZF_Helpers
 */
class ZF_Helpers {

	// Estados del partido.
	const ESTADO_PROGRAMADO = 'programado';
	const ESTADO_FINALIZADO = 'finalizado';
	const ESTADO_SUSPENDIDO = 'suspendido';

	/**
	 * Estados posibles de un partido.
	 *
	 * @return array
	 */
	public static function estados() {
		return array(
			self::ESTADO_PROGRAMADO => __( 'Programado', 'zonas-partidos-futbol' ),
			self::ESTADO_FINALIZADO => __( 'Finalizado', 'zonas-partidos-futbol' ),
			self::ESTADO_SUSPENDIDO => __( 'Suspendido', 'zonas-partidos-futbol' ),
		);
	}

	/**
	 * Etiqueta de un estado.
	 *
	 * @param string $estado Estado.
	 * @return string
	 */
	public static function estado_label( $estado ) {
		$estados = self::estados();
		return isset( $estados[ $estado ] ) ? $estados[ $estado ] : $estado;
	}

	/**
	 * Puntos configurados por resultado.
	 *
	 * @return array [ganado, empatado, perdido]
	 */
	public static function puntos() {
		return array(
			(int) get_option( 'zf_pts_ganado', 3 ),
			(int) get_option( 'zf_pts_empatado', 1 ),
			(int) get_option( 'zf_pts_perdido', 0 ),
		);
	}

	/**
	 * Todas las zonas.
	 *
	 * @return WP_Term[]
	 */
	public static function zonas() {
		$terminos = get_terms(
			array(
				'taxonomy'   => ZF_Install::TAX_ZONA,
				'hide_empty' => false,
			)
		);
		return is_array( $terminos ) ? $terminos : array();
	}

	/**
	 * Resuelve una zona por slug o ID.
	 *
	 * @param string|int $zona Slug o term_id.
	 * @return WP_Term|null
	 */
	public static function zona( $zona ) {
		if ( '' === $zona || null === $zona ) {
			return null;
		}
		if ( is_numeric( $zona ) ) {
			$term = get_term( (int) $zona, ZF_Install::TAX_ZONA );
		} else {
			$term = get_term_by( 'slug', sanitize_title( $zona ), ZF_Install::TAX_ZONA );
			if ( ! $term ) {
				$term = get_term_by( 'name', (string) $zona, ZF_Install::TAX_ZONA );
			}
		}
		return ( $term && ! is_wp_error( $term ) ) ? $term : null;
	}

	/**
	 * Equipos inscriptos.
	 *
	 * @return WP_Post[]
	 */
	public static function equipos() {
		return get_posts(
			array(
				'post_type'      => 'if_equipo',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Equipos de una zona.
	 *
	 * @param int $term_id ID de la zona.
	 * @return WP_Post[]
	 */
	public static function equipos_de_zona( $term_id ) {
		return get_posts(
			array(
				'post_type'      => 'if_equipo',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array(
					array(
						'taxonomy' => ZF_Install::TAX_ZONA,
						'field'    => 'term_id',
						'terms'    => (int) $term_id,
					),
				),
			)
		);
	}

	/**
	 * Consulta de partidos.
	 *
	 * @param array $args {
	 *     zona: int term_id. estado: string. futuros: bool solo fecha >= ahora.
	 *     limite: int (-1 todos). orden: asc|desc por fecha.
	 * }
	 * @return WP_Post[]
	 */
	public static function partidos( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'zona'    => 0,
				'estado'  => '',
				'futuros' => false,
				'limite'  => -1,
				'orden'   => 'asc',
			)
		);

		$meta_query = array();
		if ( $args['estado'] ) {
			$meta_query[] = array(
				'key'   => '_zf_estado',
				'value' => $args['estado'],
			);
		}
		if ( $args['futuros'] ) {
			$meta_query[] = array(
				'key'     => '_zf_fecha',
				'value'   => current_time( 'mysql', true ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			);
		}

		$tax_query = array();
		if ( $args['zona'] ) {
			$tax_query[] = array(
				'taxonomy' => ZF_Install::TAX_ZONA,
				'field'    => 'term_id',
				'terms'    => (int) $args['zona'],
			);
		}

		return get_posts(
			array(
				'post_type'      => ZF_Install::CPT_PARTIDO,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $args['limite'],
				'meta_key'       => '_zf_fecha',
				'orderby'        => 'meta_value',
				'order'          => 'desc' === $args['orden'] ? 'DESC' : 'ASC',
				'meta_query'     => $meta_query,
				'tax_query'      => $tax_query,
			)
		);
	}

	/**
	 * Datos de un partido como arreglo.
	 *
	 * @param WP_Post|int $post Post.
	 * @return array|null
	 */
	public static function datos_partido( $post ) {
		$post = get_post( $post );
		if ( ! $post || ZF_Install::CPT_PARTIDO !== $post->post_type ) {
			return null;
		}
		return array(
			'id'        => (int) $post->ID,
			'local'     => (int) get_post_meta( $post->ID, '_zf_local', true ),
			'visitante' => (int) get_post_meta( $post->ID, '_zf_visitante', true ),
			'lugar'     => (string) get_post_meta( $post->ID, '_zf_lugar', true ),
			'fecha'     => (string) get_post_meta( $post->ID, '_zf_fecha', true ),
			'jornada'   => (string) get_post_meta( $post->ID, '_zf_jornada', true ),
			'estado'    => (string) get_post_meta( $post->ID, '_zf_estado', true ),
			'gl'        => (int) get_post_meta( $post->ID, '_zf_goles_local', true ),
			'gv'        => (int) get_post_meta( $post->ID, '_zf_goles_visitante', true ),
		);
	}

	/**
	 * Escudo de un equipo (URL o vacío).
	 *
	 * @param int $equipo_id ID.
	 * @return string
	 */
	public static function escudo_equipo( $equipo_id ) {
		return (string) get_post_meta( (int) $equipo_id, '_if_escudo', true );
	}

	/**
	 * Nombre de un equipo.
	 *
	 * @param int $equipo_id ID.
	 * @return string
	 */
	public static function nombre_equipo( $equipo_id ) {
		$titulo = get_the_title( (int) $equipo_id );
		return $titulo ? $titulo : __( 'Equipo', 'zonas-partidos-futbol' );
	}

	/**
	 * Iniciales para el avatar sin escudo.
	 *
	 * @param string $texto Texto.
	 * @return string
	 */
	public static function iniciales( $texto ) {
		$partes = preg_split( '/\s+/', trim( (string) $texto ) );
		$ini    = '';
		foreach ( array_slice( $partes, 0, 2 ) as $p ) {
			if ( '' !== $p ) {
				$ini .= mb_strtoupper( mb_substr( $p, 0, 1 ) );
			}
		}
		return $ini ? $ini : '?';
	}

	/**
	 * Fecha formateada en horario del sitio.
	 *
	 * @param string $gmt     Fecha GMT (Y-m-d H:i:s).
	 * @param string $formato Formato deseado.
	 * @return string
	 */
	public static function fecha( $gmt, $formato = 'd/m/Y H:i' ) {
		if ( ! $gmt ) {
			return '';
		}
		return date_i18n( $formato, strtotime( get_date_from_gmt( $gmt, 'Y-m-d H:i:s' ) ) );
	}

	/**
	 * Calcula la tabla de posiciones de una zona.
	 *
	 * @param int|WP_Term $zona Zona.
	 * @return array Filas con equipo/pj/g/e/p/gf/gc/dif/pts.
	 */
	public static function tabla_zona( $zona ) {
		$term = ( $zona instanceof WP_Term ) ? $zona : get_term( (int) $zona, ZF_Install::TAX_ZONA );
		if ( ! $term || is_wp_error( $term ) ) {
			return array();
		}

		$filas = array();
		foreach ( self::equipos_de_zona( $term->term_id ) as $equipo ) {
			$filas[ $equipo->ID ] = array(
				'id'     => (int) $equipo->ID,
				'nombre' => $equipo->post_title,
				'escudo' => self::escudo_equipo( $equipo->ID ),
				'pj'     => 0,
				'g'      => 0,
				'e'      => 0,
				'p'      => 0,
				'gf'     => 0,
				'gc'     => 0,
				'dif'    => 0,
				'pts'    => 0,
				'forma'  => array(),
			);
		}

		list( $pts_g, $pts_e, $pts_p ) = self::puntos();

		$partidos = self::partidos(
			array(
				'zona'   => $term->term_id,
				'estado' => self::ESTADO_FINALIZADO,
			)
		);

		foreach ( $partidos as $partido ) {
			$d = self::datos_partido( $partido );
			if ( ! $d || ! $d['local'] || ! $d['visitante'] ) {
				continue;
			}
			foreach ( array( $d['local'], $d['visitante'] ) as $id ) {
				if ( ! isset( $filas[ $id ] ) ) {
					$filas[ $id ] = array(
						'id'     => (int) $id,
						'nombre' => self::nombre_equipo( $id ),
						'escudo' => self::escudo_equipo( $id ),
						'pj'     => 0,
						'g'      => 0,
						'e'      => 0,
						'p'      => 0,
						'gf'     => 0,
						'gc'     => 0,
						'dif'    => 0,
						'pts'    => 0,
						'forma'  => array(),
					);
				}
			}

			$filas[ $d['local'] ]['pj']++;
			$filas[ $d['visitante'] ]['pj']++;
			$filas[ $d['local'] ]['gf'] += $d['gl'];
			$filas[ $d['local'] ]['gc'] += $d['gv'];
			$filas[ $d['visitante'] ]['gf'] += $d['gv'];
			$filas[ $d['visitante'] ]['gc'] += $d['gl'];

			if ( $d['gl'] > $d['gv'] ) {
				$filas[ $d['local'] ]['g']++;
				$filas[ $d['local'] ]['pts'] += $pts_g;
				$filas[ $d['visitante'] ]['p']++;
				$filas[ $d['visitante'] ]['pts'] += $pts_p;
				// Resultado en letra para la forma reciente.
				$letra_local  = 'G';
				$letra_visita = 'P';
			} elseif ( $d['gl'] < $d['gv'] ) {
				$filas[ $d['visitante'] ]['g']++;
				$filas[ $d['visitante'] ]['pts'] += $pts_g;
				$filas[ $d['local'] ]['p']++;
				$filas[ $d['local'] ]['pts'] += $pts_p;
				$letra_local  = 'P';
				$letra_visita = 'G';
			} else {
				$filas[ $d['local'] ]['e']++;
				$filas[ $d['visitante'] ]['e']++;
				$filas[ $d['local'] ]['pts'] += $pts_e;
				$filas[ $d['visitante'] ]['pts'] += $pts_e;
				$letra_local  = 'E';
				$letra_visita = 'E';
			}
			$filas[ $d['local'] ]['forma'][]     = $letra_local;
			$filas[ $d['visitante'] ]['forma'][] = $letra_visita;
		}

		foreach ( $filas as $i => $fila ) {
			$filas[ $i ]['dif']   = $fila['gf'] - $fila['gc'];
			$filas[ $i ]['forma'] = array_slice( $fila['forma'], -5 );
		}

		usort(
			$filas,
			static function ( $a, $b ) {
				foreach ( array( 'pts', 'dif', 'gf' ) as $campo ) {
					if ( $a[ $campo ] !== $b[ $campo ] ) {
						return $b[ $campo ] <=> $a[ $campo ];
					}
				}
				return strnatcasecmp( $a['nombre'], $b['nombre'] );
			}
		);

		return array_values( $filas );
	}

	// ============================ Render ===================================

	/**
	 * Avatar de equipo (escudo o iniciales).
	 *
	 * @param int    $equipo_id ID.
	 * @param string $tamano    ''|'lg' para tarjetas de partido.
	 * @return string HTML.
	 */
	public static function render_avatar( $equipo_id, $tamano = '' ) {
		$extra  = 'lg' === $tamano ? ' zf-crest-lg' : '';
		$escudo = self::escudo_equipo( $equipo_id );
		if ( $escudo ) {
			return '<span class="zf-crest' . $extra . '"><img src="' . esc_url( $escudo ) . '" alt="' . esc_attr( self::nombre_equipo( $equipo_id ) ) . '" loading="lazy" /></span>';
		}
		return '<span class="zf-crest zf-crest-txt' . $extra . '">' . esc_html( self::iniciales( self::nombre_equipo( $equipo_id ) ) ) . '</span>';
	}

	/**
	 * Tabla de posiciones en HTML.
	 *
	 * @param WP_Term $term Zona.
	 * @param array   $args {
	 *     clasificados: int puestos destacados (0 desactiva). forma: bool mostrar columna.
	 * }
	 * @return string
	 */
	public static function render_tabla( $term, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'clasificados' => (int) apply_filters( 'zf_clasificados_por_zona', 2 ),
				'forma'        => true,
			)
		);
		$args['clasificados'] = max( 0, (int) $args['clasificados'] );

		$filas = self::tabla_zona( $term );

		ob_start();
		echo '<section class="zf-tabla-wrap">';
		echo '<header class="zf-tabla-head">';
		echo '<h3 class="zf-tabla-titulo">' . self::icono_trofeo() . esc_html( $term->name ) . '</h3>';
		echo '<span class="zf-tabla-sub">' . esc_html__( 'Posiciones', 'zonas-partidos-futbol' ) . '</span>';
		echo '</header>';

		if ( ! $filas ) {
			echo '<p class="zf-vacio">' . esc_html__( 'Todavía no hay equipos asignados a esta zona.', 'zonas-partidos-futbol' ) . '</p>';
			echo '</section>';
			return ob_get_clean();
		}

		echo '<div class="zf-tabla-scroll"><table class="zf-tabla"><thead><tr>';
		echo '<th class="zf-col-pos">#</th><th class="zf-col-equipo">' . esc_html__( 'Equipo', 'zonas-partidos-futbol' ) . '</th>';
		foreach ( array( 'pj' => 'PJ', 'g' => 'G', 'e' => 'E', 'p' => 'P', 'gf' => 'GF', 'gc' => 'GC', 'dif' => 'DIF', 'pts' => 'PTS' ) as $k => $label ) {
			echo '<th class="zf-col-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</th>';
		}
		if ( $args['forma'] ) {
			echo '<th class="zf-col-forma">' . esc_html__( 'Forma', 'zonas-partidos-futbol' ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		$pos          = 0;
		$total        = count( $filas );
		$hay_zona_clasi = $args['clasificados'] > 0 && $total > $args['clasificados'];

		foreach ( $filas as $fila ) {
			$pos++;
			$clases_fila = array();
			if ( $hay_zona_clasi && $pos <= $args['clasificados'] ) {
				$clases_fila[] = 'zf-clasifica';
			}
			echo '<tr class="' . esc_attr( implode( ' ', $clases_fila ) ) . '">';

			echo '<td class="zf-col-pos"><span class="zf-pos">' . esc_html( $pos ) . '</span></td>';
			echo '<td class="zf-col-equipo">' . self::render_avatar( $fila['id'] ) . '<span class="zf-equipo-nombre">' . esc_html( $fila['nombre'] ) . '</span></td>';

			foreach ( array( 'pj', 'g', 'e', 'p', 'gf', 'gc' ) as $k ) {
				echo '<td class="zf-col-' . esc_attr( $k ) . '">' . esc_html( $fila[ $k ] ) . '</td>';
			}

			$dif = (int) $fila['dif'];
			$clase_dif = $dif > 0 ? ' zf-dif-pos' : ( $dif < 0 ? ' zf-dif-neg' : '' );
			echo '<td class="zf-col-dif' . esc_attr( $clase_dif ) . '">' . esc_html( $dif > 0 ? '+' . $dif : $dif ) . '</td>';

			echo '<td class="zf-col-pts">' . esc_html( $fila['pts'] ) . '</td>';

			if ( $args['forma'] ) {
				echo '<td class="zf-col-forma">' . self::render_forma( isset( $fila['forma'] ) ? $fila['forma'] : array() ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML interno ya escapado.
				echo '</td>';
			}

			echo '</tr>';
		}
		echo '</tbody></table></div>';

		echo '<footer class="zf-tabla-pie">';
		if ( $hay_zona_clasi ) {
			echo '<span class="zf-leyenda"><i class="zf-dot"></i>' . esc_html__( 'Zona de clasificación', 'zonas-partidos-futbol' ) . '</span>';
		}
		if ( $args['forma'] ) {
			echo '<span class="zf-leyenda zf-leyenda-forma">';
			echo '<span class="zf-pill zf-pill-g">G</span> ' . esc_html__( 'Ganado', 'zonas-partidos-futbol' ) . ' · ';
			echo '<span class="zf-pill zf-pill-e">E</span> ' . esc_html__( 'Empatado', 'zonas-partidos-futbol' ) . ' · ';
			echo '<span class="zf-pill zf-pill-p">P</span> ' . esc_html__( 'Perdido', 'zonas-partidos-futbol' );
			echo '</span>';
		}
		echo '</footer>';

		echo '</section>';
		return ob_get_clean();
	}

	/**
	 * Píldoras de forma reciente (últimos resultados G/E/P).
	 *
	 * @param array $resultados Letras en orden cronológico.
	 * @return string HTML.
	 */
	public static function render_forma( $resultados ) {
		$mapa = array(
			'G' => array( 'zf-pill-g', __( 'Ganado', 'zonas-partidos-futbol' ) ),
			'E' => array( 'zf-pill-e', __( 'Empatado', 'zonas-partidos-futbol' ) ),
			'P' => array( 'zf-pill-p', __( 'Perdido', 'zonas-partidos-futbol' ) ),
		);

		$html    = '<span class="zf-forma" aria-label="' . esc_attr__( 'Forma reciente', 'zonas-partidos-futbol' ) . '">';
		$datos   = array_slice( array_values( (array) $resultados ), -5 );
		$mostrar = max( 5, count( $datos ) );

		for ( $i = 0; $i < $mostrar; $i++ ) {
			$letra = isset( $datos[ $i ] ) ? (string) $datos[ $i ] : '';
			if ( $letra && isset( $mapa[ $letra ] ) ) {
				$html .= '<span class="zf-pill ' . esc_attr( $mapa[ $letra ][0] ) . '" title="' . esc_attr( $mapa[ $letra ][1] ) . '">' . esc_html( $letra ) . '</span>';
			} else {
				$html .= '<span class="zf-pill zf-pill-vacio">&middot;</span>';
			}
		}
		return $html . '</span>';
	}

	/**
	 * SVG de trofeo para encabezados de tabla.
	 *
	 * @return string
	 */
	public static function icono_trofeo() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M8 21h8M12 17v4M7 4h10v6a5 5 0 0 1-10 0V4z"/><path d="M7 6H4a1 1 0 0 0-1 1 4 4 0 0 0 4 4"/><path d="M17 6h3a1 1 0 0 1 1 1 4 4 0 0 1-4 4"/></svg>';
	}

	/**
	 * Tarjeta de partido en HTML.
	 *
	 * @param WP_Post $post Partido.
	 * @param string  $modo proximo|resultado.
	 * @return string
	 */
	public static function render_partido( $post, $modo = 'proximo' ) {
		$d = self::datos_partido( $post );
		if ( ! $d ) {
			return '';
		}

		$es_resultado = ( 'resultado' === $modo || self::ESTADO_FINALIZADO === $d['estado'] );

		// Ganador / perdedor solo con resultado cargado.
		$clase_local   = '';
		$clase_visita  = '';
		$puntua_local  = '';
		$puntua_visita = '';
		if ( $es_resultado ) {
			if ( $d['gl'] > $d['gv'] ) {
				$clase_local  = ' zf-ganador';
				$clase_visita = ' zf-perdedor';
			} elseif ( $d['gl'] < $d['gv'] ) {
				$clase_local  = ' zf-perdedor';
				$clase_visita = ' zf-ganador';
			}
			$puntua_local  = $d['gl'] > $d['gv'] ? ' zf-ganador-score' : '';
			$puntua_visita = $d['gv'] > $d['gl'] ? ' zf-ganador-score' : '';
		}

		ob_start();
		echo '<article class="zf-partido zf-partido-' . esc_attr( $es_resultado ? 'resultado' : 'proximo' ) . '">';

		// Bloque de fecha: día de semana, número, mes y hora.
		echo '<div class="zf-partido-fecha">';
		if ( $d['fecha'] ) {
			echo '<span class="zf-fecha-dow">' . esc_html( self::fecha( $d['fecha'], 'D' ) ) . '</span>';
			echo '<span class="zf-fecha-num">' . esc_html( self::fecha( $d['fecha'], 'j' ) ) . '</span>';
			echo '<span class="zf-fecha-mes">' . esc_html( self::fecha( $d['fecha'], 'M' ) ) . '</span>';
			echo '<span class="zf-fecha-hora">' . esc_html( self::fecha( $d['fecha'], 'H:i' ) ) . '</span>';
		} else {
			echo '<span class="zf-fecha-mes">' . esc_html__( 'A confirmar', 'zonas-partidos-futbol' ) . '</span>';
		}
		echo '</div>';

		echo '<div class="zf-partido-cuerpo">';
		echo '<div class="zf-partido-fila">';

		// Local.
		echo '<div class="zf-partido-equipo zf-partido-equipo-local' . esc_attr( $clase_local ) . '">';
		echo self::render_avatar( $d['local'], 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
		echo '<span class="zf-equipo-nombre">' . esc_html( self::nombre_equipo( $d['local'] ) ) . '</span>';
		echo '</div>';

		// Centro: marcador o VS.
		echo '<div class="zf-partido-centro">';
		if ( $es_resultado ) {
			echo '<div class="zf-marcador" aria-label="' . esc_attr__( 'Resultado final', 'zonas-partidos-futbol' ) . '">';
			echo '<b class="' . esc_attr( ltrim( $puntua_local, ' ' ) ) . '">' . esc_html( $d['gl'] ) . '</b>';
			echo '<span class="zf-marcador-sep">&ndash;</span>';
			echo '<b class="' . esc_attr( ltrim( $puntua_visita, ' ' ) ) . '">' . esc_html( $d['gv'] ) . '</b>';
			echo '</div>';
			echo '<span class="zf-partido-tag">' . esc_html__( 'Final', 'zonas-partidos-futbol' ) . '</span>';
		} else {
			echo '<span class="zf-vs">' . esc_html__( 'VS', 'zonas-partidos-futbol' ) . '</span>';
		}
		echo '</div>';

		// Visitante.
		echo '<div class="zf-partido-equipo zf-partido-equipo-visita' . esc_attr( $clase_visita ) . '">';
		echo self::render_avatar( $d['visitante'], 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
		echo '<span class="zf-equipo-nombre">' . esc_html( self::nombre_equipo( $d['visitante'] ) ) . '</span>';
		echo '</div>';

		echo '</div>'; // zf-partido-fila.

		// Extras: lugar · jornada · estado.
		$extras = array();
		if ( $d['lugar'] ) {
			$extras[] = '<span class="zf-extra zf-lugar">' . self::icono_pin() . esc_html( $d['lugar'] ) . '</span>';
		}
		if ( $d['jornada'] ) {
			$extras[] = '<span class="zf-extra zf-jornada">' . self::icono_calendario() . sprintf(
				/* translators: %s: número o nombre de la jornada. */
				esc_html__( 'Fecha %s', 'zonas-partidos-futbol' ),
				'<strong>' . esc_html( $d['jornada'] ) . '</strong>'
			) . '</span>';
		}
		// Solo el suspendido lleva badge: el tag "Final" y el chip VS ya comunican el resto.
		if ( self::ESTADO_SUSPENDIDO === $d['estado'] ) {
			$extras[] = '<span class="zf-badge zf-badge-suspendido">' . esc_html( self::estado_label( $d['estado'] ) ) . '</span>';
		}

		if ( $extras ) {
			echo '<div class="zf-partido-extras">' . implode( ' ', $extras ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado arriba.
		}

		echo '</div>'; // zf-partido-cuerpo.
		echo '</article>';
		return ob_get_clean();
	}

	/**
	 * SVG de pin de ubicación para el lugar del partido.
	 *
	 * @return string
	 */
	public static function icono_pin() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>';
	}

	/**
	 * SVG de calendario para la jornada.
	 *
	 * @return string
	 */
	public static function icono_calendario() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
	}
}
