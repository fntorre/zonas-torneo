<?php
/**
 * Hub del torneo: navegación entre zonas, tablas, partidos y perfiles de equipo.
 *
 * Uso: [zf_hub] o [zf_hub vista="posiciones" titulo="Campeonato"]
 * Navegación por query vars: ?zf_vista=equipos · ?zf_equipo=123
 *
 * @package ZonasFutbol
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase ZF_Hub
 */
class ZF_Hub {

	/**
	 * Vistas disponibles.
	 *
	 * @return array vista => etiqueta.
	 */
	public static function vistas() {
		return array(
			'posiciones' => __( 'Posiciones', 'zonas-partidos-futbol' ),
			'equipos'    => __( 'Equipos', 'zonas-partidos-futbol' ),
			'proximos'   => __( 'Próximos', 'zonas-partidos-futbol' ),
			'resultados' => __( 'Resultados', 'zonas-partidos-futbol' ),
		);
	}

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_shortcode( 'zf_hub', array( __CLASS__, 'render' ) );
	}

	/**
	 * Registra los assets del hub.
	 */
	public static function assets() {
		wp_register_style(
			'zf-hub',
			ZF_PLUGIN_URL . 'assets/css/hub.css',
			array( 'zf-frontend' ),
			ZF_VERSION
		);
		wp_register_script(
			'zf-hub',
			ZF_PLUGIN_URL . 'assets/js/hub.js',
			array(),
			ZF_VERSION,
			true
		);
	}

	/**
	 * Encola los assets del hub.
	 */
	private static function encolar_assets() {
		if ( ! wp_style_is( 'zf-frontend', 'enqueued' ) ) {
			wp_enqueue_style( 'zf-frontend' );
		}
		if ( ! wp_style_is( 'zf-hub', 'enqueued' ) ) {
			wp_enqueue_style( 'zf-hub' );
		}
		if ( ! wp_script_is( 'zf-hub', 'enqueued' ) ) {
			wp_enqueue_script( 'zf-hub' );
		}
	}

	// ============================ Shortcode ================================.

	/**
	 * Renderiza el hub completo o el perfil de un equipo.
	 *
	 * @param array $atts {
	 *     vista: vista inicial (posiciones|equipos|proximos|resultados).
	 *     titulo: marca opcional sobre la navegación.
	 * }
	 * @return string
	 */
	public static function render( $atts = array() ) {
		self::encolar_assets();

		$atts = shortcode_atts(
			array(
				'vista'  => 'posiciones',
				'titulo' => '',
			),
			$atts,
			'zf_hub'
		);

		// Perfil de equipo tiene prioridad.
		$equipo_id = isset( $_GET['zf_equipo'] ) ? absint( $_GET['zf_equipo'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lectura pública.
		if ( $equipo_id && 'if_equipo' === get_post_type( $equipo_id ) && 'publish' === get_post_status( $equipo_id ) ) {
			return self::render_equipo_perfil( $equipo_id );
		}

		$vistas   = self::vistas();
		$vista_qs = isset( $_GET['zf_vista'] ) ? sanitize_key( wp_unslash( $_GET['zf_vista'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$vista    = isset( $vistas[ $vista_qs ] ) ? $vista_qs : ( isset( $vistas[ $atts['vista'] ] ) ? $atts['vista'] : 'posiciones' );

		ob_start();
		echo '<div class="zf-hub">';

		if ( $atts['titulo'] ) {
			echo '<header class="zf-hub-marca">' . self::icono_pelota() . '<span>' . esc_html( $atts['titulo'] ) . '</span></header>';
		}

		self::render_nav( $vista, $vistas );

		echo '<section class="zf-hub-vista">';
		switch ( $vista ) {
			case 'equipos':
				echo '<div class="zf-buscador-wrap">';
				echo '<label class="screen-reader-text" for="zf-buscador-equipos">' . esc_html__( 'Buscar equipo', 'zonas-partidos-futbol' ) . '</label>';
				echo '<span class="zf-buscador-icono">' . self::icono_lupa() . '</span>';
				echo '<input type="search" id="zf-buscador-equipos" class="zf-buscador" placeholder="' . esc_attr__( 'Buscar equipo por nombre…', 'zonas-partidos-futbol' ) . '" autocomplete="off" />';
				echo '<p class="zf-sin-resultados" hidden>' . esc_html__( 'No se encontraron equipos con ese nombre.', 'zonas-partidos-futbol' ) . '</p>';
				echo '</div>';
				echo do_shortcode( '[zf_zonas]' );
				break;

			case 'proximos':
				echo ZF_Shortcodes::proximos( array( 'limite' => 12 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
				break;

			case 'resultados':
				echo ZF_Shortcodes::resultados( array( 'limite' => 12 ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
				break;

			case 'posiciones':
			default:
				echo ZF_Shortcodes::tabla(); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
				break;
		}
		echo '</section>';

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Barra de navegación entre vistas.
	 *
	 * @param string $activa Vista activa.
	 * @param array  $vistas Mapa vista => etiqueta.
	 */
	private static function render_nav( $activa, $vistas ) {
		$iconos = array(
			'posiciones' => 'ZF_Helpers::icono_trofeo',
			'equipos'    => array( __CLASS__, 'icono_escudo' ),
			'proximos'   => 'ZF_Helpers::icono_calendario',
			'resultados' => array( __CLASS__, 'icono_check' ),
		);

		echo '<nav class="zf-hub-nav" aria-label="' . esc_attr__( 'Secciones del torneo', 'zonas-partidos-futbol' ) . '">';
		foreach ( $vistas as $clave => $etiqueta ) {
			$url      = esc_url( add_query_arg( 'zf_vista', $clave, remove_query_arg( array( 'zf_vista', 'zf_equipo' ) ) ) );
			$clases   = array( 'zf-nav-item' );
			$clases[] = ( $clave === $activa ) ? 'is-active' : '';
			$callable = $iconos[ $clave ];
			$icono    = is_string( $callable ) ? call_user_func( $callable ) : call_user_func( $callable );

			echo '<a class="' . esc_attr( trim( implode( ' ', $clases ) ) ) . '" href="' . $url . '"' . ( $clave === $activa ? ' aria-current="page"' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- URL escapada arriba.
			echo $icono; // phpcs:ignore WordPress.Security.EscapeOutput -- SVG estático interno.
			echo '<span>' . esc_html( $etiqueta ) . '</span>';
			echo '</a>';
		}
		echo '</nav>';
	}

	// ========================= Perfil de equipo =============================.

	/**
	 * Perfil público de un equipo: racha, resultados y próximos partidos.
	 *
	 * @param int $equipo_id ID del equipo.
	 * @return string
	 */
	public static function render_equipo_perfil( $equipo_id ) {
		$nombre = ZF_Helpers::nombre_equipo( $equipo_id );

		ob_start();
		echo '<article class="zf-hub zf-equipo-perfil">';

		// Volver.
		echo '<a class="zf-volver" href="' . esc_url( remove_query_arg( array( 'zf_vista', 'zf_equipo' ) ) ) . '">' . self::icono_flecha() . esc_html__( 'Volver al torneo', 'zonas-partidos-futbol' ) . '</a>';

		// Hero del equipo.
		echo '<header class="zf-equipo-hero">';
		echo '<span class="zf-equipo-crest-grande">' . ZF_Helpers::render_avatar( $equipo_id, 'lg' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
		echo '<h2 class="zf-equipo-nombre-grande">' . esc_html( $nombre ) . '</h2>';

		$zonas_term = get_the_terms( $equipo_id, ZF_Install::TAX_ZONA );
		$chips      = array();
		$tabla_info = null;

		if ( $zonas_term && ! is_wp_error( $zonas_term ) ) {
			foreach ( $zonas_term as $zona ) {
				$pos_en_zona = self::posicion_en_zona( $equipo_id, $zona );
				if ( $pos_en_zona ) {
					$chips[]     = '<span class="zf-chip zf-chip-pos">' . sprintf(
						/* translators: 1: posición. 2: nombre de la zona. */
						esc_html__( '%1$s° en %2$s', 'zonas-partidos-futbol' ),
						esc_html( number_format_i18n( $pos_en_zona['pos'] ) ),
						esc_html( $zona->name )
					) . '</span>';
					$chips[]     = '<span class="zf-chip">' . sprintf(
						/* translators: %s: cantidad de puntos. */
						esc_html__( '%s pts', 'zonas-partidos-futbol' ),
						esc_html( number_format_i18n( $pos_en_zona['fila']['pts'] ) )
					) . '</span>';
					$chips[]     = '<span class="zf-chip">' . sprintf(
						/* translators: %s: partidos jugados. */
						esc_html__( 'PJ %s', 'zonas-partidos-futbol' ),
						esc_html( number_format_i18n( $pos_en_zona['fila']['pj'] ) )
					) . '</span>';
					$tabla_info  = $pos_en_zona;
					continue;
				}
				$chips[] = '<span class="zf-chip">' . esc_html( $zona->name ) . '</span>';
			}
		}

		if ( $chips ) {
			echo '<div class="zf-equipo-chips">' . implode( '', $chips ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado arriba.
		}
		echo '</header>';

		// Racha reciente.
		if ( $tabla_info && ! empty( $tabla_info['fila']['forma'] ) ) {
			echo '<section class="zf-perfil-seccion">';
			echo '<h3 class="zf-perfil-titulo">' . esc_html__( 'Racha reciente', 'zonas-partidos-futbol' ) . '</h3>';
			echo ZF_Helpers::render_forma( $tabla_info['fila']['forma'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
			echo '</section>';
		}

		// Últimos resultados.
		$jugados = ZF_Helpers::partidos_de_equipo( $equipo_id, array( 'estado' => ZF_Helpers::ESTADO_FINALIZADO, 'limite' => 5, 'orden' => 'desc' ) );
		echo '<section class="zf-perfil-seccion">';
		echo '<h3 class="zf-perfil-titulo">' . esc_html__( 'Últimos resultados', 'zonas-partidos-futbol' ) . '</h3>';
		echo '<div class="zf-lista zf-lista-resultados">';
		if ( ! $jugados ) {
			echo '<p class="zf-vacio">' . esc_html__( 'Todavía no hay partidos jugados.', 'zonas-partidos-futbol' ) . '</p>';
		}
		foreach ( $jugados as $partido ) {
			echo ZF_Helpers::render_partido( $partido, 'resultado' ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
		}
		echo '</div></section>';

		// Próximos partidos.
		$futuros = ZF_Helpers::partidos_de_equipo( $equipo_id, array( 'estado' => ZF_Helpers::ESTADO_PROGRAMADO, 'limite' => 5, 'orden' => 'asc' ) );
		echo '<section class="zf-perfil-seccion">';
		echo '<h3 class="zf-perfil-titulo">' . esc_html__( 'Próximos partidos', 'zonas-partidos-futbol' ) . '</h3>';
		echo '<div class="zf-lista zf-lista-proximo">';
		if ( ! $futuros ) {
			echo '<p class="zf-vacio">' . esc_html__( 'No hay partidos programados por ahora.', 'zonas-partidos-futbol' ) . '</p>';
		}
		foreach ( $futuros as $partido ) {
			echo ZF_Helpers::render_partido( $partido, 'proximo' ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
		}
		echo '</div></section>';

		echo '</article>';
		return ob_get_clean();
	}

	/**
	 * Posición y fila de un equipo dentro de la tabla de su zona.
	 *
	 * @param int     $equipo_id ID del equipo.
	 * @param WP_Term $zona      Zona.
	 * @return array|null {pos:int, fila:array}
	 */
	public static function posicion_en_zona( $equipo_id, $zona ) {
		$filas = ZF_Helpers::tabla_zona( $zona );
		foreach ( $filas as $i => $fila ) {
			if ( (int) $fila['id'] === (int) $equipo_id ) {
				return array(
					'pos'  => $i + 1,
					'fila' => $fila,
				);
			}
		}
		return null;
	}

	// ============================== Iconos =================================.

	/**
	 * SVG de pelota para la marca del hub.
	 *
	 * @return string
	 */
	public static function icono_pelota() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/><path d="M2 12h20"/></svg>';
	}

	/**
	 * SVG de escudo para la pestaña equipos.
	 *
	 * @return string
	 */
	public static function icono_escudo() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>';
	}

	/**
	 * SVG de check para la pestaña resultados.
	 *
	 * @return string
	 */
	public static function icono_check() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
	}

	/**
	 * SVG de lupa para el buscador.
	 *
	 * @return string
	 */
	public static function icono_lupa() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>';
	}

	/**
	 * SVG de flecha izquierda para volver.
	 *
	 * @return string
	 */
	public static function icono_flecha() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>';
	}
}
