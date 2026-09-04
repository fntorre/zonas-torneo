<?php
/**
 * Shortcodes del frontend.
 *
 * @package ZonasFutbol
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase ZF_Shortcodes
 */
class ZF_Shortcodes {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );

		add_shortcode( 'zf_zonas', array( __CLASS__, 'zonas' ) );
		add_shortcode( 'zf_tabla_posiciones', array( __CLASS__, 'tabla' ) );
		add_shortcode( 'zf_proximos_partidos', array( __CLASS__, 'proximos' ) );
		add_shortcode( 'zf_resultados', array( __CLASS__, 'resultados' ) );
		add_shortcode( 'zf_playoffs', array( __CLASS__, 'playoffs' ) );
		add_shortcode( 'zf_equipos', array( __CLASS__, 'equipos' ) );
	}

	/**
	 * CSS del frontend (solo cuando hay un shortcode en el contenido).
	 */
	public static function assets() {
		wp_register_style(
			'zf-frontend',
			ZF_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			ZF_VERSION
		);
	}

	/**
	 * Encola el CSS si aún no está.
	 */
	private static function css() {
		if ( ! wp_style_is( 'zf-frontend', 'enqueued' ) ) {
			wp_enqueue_style( 'zf-frontend' );
		}
	}

	// ============================ [zf_zonas] ================================

	/**
	 * Listado de zonas con sus equipos.
	 *
	 * @return string
	 */
	public static function zonas() {
		self::css();
		$zonas = ZF_Helpers::zonas();
		ob_start();
		echo '<div class="zf-zonas">';
		if ( ! $zonas ) {
			echo '<p class="zf-vacio">' . esc_html__( 'Todavía no se definieron las zonas.', 'zonas-partidos-futbol' ) . '</p>';
		} else {
		foreach ( $zonas as $zona ) {
			$equipos = ZF_Helpers::equipos_de_zona( $zona->term_id );
			echo '<section class="zf-zona-card">';
			echo '<header class="zf-zona-head">';
			echo '<h3 class="zf-zona-titulo">' . esc_html( $zona->name ) . '</h3>';
			if ( $equipos ) {
				echo '<span class="zf-zona-count">' . esc_html(
					sprintf(
						/* translators: %s: cantidad de equipos. */
						_n( '%s equipo', '%s equipos', count( $equipos ), 'zonas-partidos-futbol' ),
						number_format_i18n( count( $equipos ) )
					)
				) . '</span>';
			}
			echo '</header>';
			echo '<ul class="zf-zona-equipos">';
			if ( $equipos ) {
				foreach ( $equipos as $equipo ) {
					$url_perfil = add_query_arg( 'zf_equipo', (int) $equipo->ID );
					echo '<li data-nombre="' . esc_attr( $equipo->post_title ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '<a class="zf-equipo-link" href="' . esc_url( $url_perfil ) . '">';
					echo ZF_Helpers::render_avatar( $equipo->ID ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo '<span>' . esc_html( $equipo->post_title ) . '</span>';
					echo '</a></li>';
				}
			} else {
				echo '<li class="zf-vacio">' . esc_html__( 'Sin equipos asignados.', 'zonas-partidos-futbol' ) . '</li>';
			}
			echo '</ul></section>';
		}
		}
		echo '</div>';
		return ob_get_clean();
	}

	// =========================== [zf_equipos] ===============================

	/**
	 * Grid de todos los equipos inscriptos (sin agrupar por zona).
	 *
	 * @return string
	 */
	public static function equipos() {
		self::css();
		$equipos = ZF_Helpers::equipos();
		ob_start();
		echo '<div class="zf-zonas">';
		echo '<section class="zf-zona-card">';
		echo '<header class="zf-zona-head">';
		echo '<h3 class="zf-zona-titulo">' . esc_html__( 'Equipos', 'zonas-partidos-futbol' ) . '</h3>';
		if ( $equipos ) {
			echo '<span class="zf-zona-count">' . esc_html(
				sprintf(
					/* translators: %s: cantidad de equipos. */
					_n( '%s equipo', '%s equipos', count( $equipos ), 'zonas-partidos-futbol' ),
					number_format_i18n( count( $equipos ) )
				)
			) . '</span>';
		}
		echo '</header>';
		echo '<ul class="zf-zona-equipos">';
		if ( $equipos ) {
			foreach ( $equipos as $equipo ) {
				$url_perfil = add_query_arg( 'zf_equipo', (int) $equipo->ID );
				echo '<li data-nombre="' . esc_attr( $equipo->post_title ) . '">';
				echo '<a class="zf-equipo-link" href="' . esc_url( $url_perfil ) . '">';
				echo ZF_Helpers::render_avatar( $equipo->ID ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
				echo '<span>' . esc_html( $equipo->post_title ) . '</span>';
				echo '</a></li>';
			}
		} else {
			echo '<li class="zf-vacio">' . esc_html__( 'No hay equipos inscriptos todavía.', 'zonas-partidos-futbol' ) . '</li>';
		}
		echo '</ul></section>';
		echo '</div>';
		return ob_get_clean();
	}

	// ======================= [zf_tabla_posiciones] ==========================

	/**
	 * Tabla(s) de posiciones.
	 *
	 * @param array $atts {
	 *     zona: slug|ID (opcional). clasificados: puestos destacados (por defecto 2).
	 *     forma: 1|0 mostrar columna de forma reciente.
	 * }
	 * @return string
	 */
	public static function tabla( $atts = array() ) {
		self::css();
		$atts = shortcode_atts(
			array(
				'zona'         => '',
				'clasificados' => '',
				'forma'        => '1',
			),
			$atts,
			'zf_tabla_posiciones'
		);

		$args = array( 'forma' => (bool) absint( $atts['forma'] ) );
		if ( '' !== $atts['clasificados'] ) {
			$args['clasificados'] = absint( $atts['clasificados'] );
		}

		ob_start();
		echo '<div class="zf-tablas">';
		if ( $atts['zona'] ) {
			$term = ZF_Helpers::zona( $atts['zona'] );
			if ( ! $term ) {
				echo '<p class="zf-vacio">' . esc_html__( 'La zona indicada no existe.', 'zonas-partidos-futbol' ) . '</p>';
			} else {
				echo ZF_Helpers::render_tabla( $term, $args ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
			}
		} else {
			$zonas = ZF_Helpers::zonas();
			if ( ! $zonas ) {
				echo '<p class="zf-vacio">' . esc_html__( 'Todavía no se definieron las zonas.', 'zonas-partidos-futbol' ) . '</p>';
			}
			foreach ( $zonas as $zona ) {
				echo ZF_Helpers::render_tabla( $zona, $args ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
			}
		}
		echo '</div>';
		return ob_get_clean();
	}

	// ====================== [zf_proximos_partidos] ==========================

	/**
	 * Próximos partidos.
	 *
	 * @param array $atts zona, limite, futuros.
	 * @return string
	 */
	public static function proximos( $atts = array() ) {
		return self::lista_partidos(
			$atts,
			'zf_proximos_partidos',
			array(
				'estado' => ZF_Helpers::ESTADO_PROGRAMADO,
				'modo'   => 'proximo',
				'vacio'  => __( 'No hay partidos programados por ahora.', 'zonas-partidos-futbol' ),
			)
		);
	}

	// ========================= [zf_resultados] ==============================

	/**
	 * Resultados cargados.
	 *
	 * @param array $atts zona, limite.
	 * @return string
	 */
	public static function resultados( $atts = array() ) {
		return self::lista_partidos(
			$atts,
			'zf_resultados',
			array(
				'estado' => ZF_Helpers::ESTADO_FINALIZADO,
				'modo'   => 'resultado',
				'orden'  => 'desc',
				'vacio'  => __( 'Todavía no hay resultados cargados.', 'zonas-partidos-futbol' ),
			)
		);
	}

	/**
	 * Render genérico de lista de partidos.
	 *
	 * @param array  $atts   Atributos del shortcode.
	 * @param string $tag    Tag del shortcode.
	 * @param array  $config Configuración (estado, modo, orden, vacio).
	 * @return string
	 */
	private static function lista_partidos( $atts, $tag, $config ) {
		self::css();
		$atts = shortcode_atts(
			array(
				'zona'    => '',
				'limite'  => 8,
				'futuros' => 0,
			),
			$atts,
			$tag
		);

		$args = array(
			'estado' => $config['estado'],
			'limite' => max( -1, (int) $atts['limite'] ? (int) $atts['limite'] : -1 ),
			'orden'  => isset( $config['orden'] ) ? $config['orden'] : 'asc',
		);
		if ( $atts['futuros'] ) {
			$args['futuros'] = true;
		}
		if ( $atts['zona'] ) {
			$term = ZF_Helpers::zona( $atts['zona'] );
			if ( ! $term ) {
				return '<p class="zf-vacio">' . esc_html__( 'La zona indicada no existe.', 'zonas-partidos-futbol' ) . '</p>';
			}
			$args['zona'] = $term->term_id;
		}

		$partidos = ZF_Helpers::partidos( $args );

		ob_start();
		echo '<div class="zf-lista zf-lista-' . esc_attr( $config['modo'] ) . '">';
		if ( ! $partidos ) {
			echo '<p class="zf-vacio">' . esc_html( $config['vacio'] ) . '</p>';
		}
		foreach ( $partidos as $partido ) {
			echo ZF_Helpers::render_partido( $partido, $config['modo'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
		}
		echo '</div>';
		return ob_get_clean();
	}

	// ========================= [zf_playoffs] ================================.

	/**
	 * Cuadros de fase eliminatoria.
	 *
	 * @param array $atts llave: slug|ID (opcional; vacío = todas).
	 * @return string
	 */
	public static function playoffs( $atts = array() ) {
		self::css();
		$atts = shortcode_atts(
			array( 'llave' => '' ),
			$atts,
			'zf_playoffs'
		);

		$query = array(
			'post_type'      => ZF_Install::CPT_LLAVE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( $atts['llave'] ) {
			if ( is_numeric( $atts['llave'] ) ) {
				$query['p'] = absint( $atts['llave'] );
			} else {
				$query['name'] = sanitize_title( $atts['llave'] );
			}
		}

		$llaves = get_posts( $query );

		ob_start();
		echo '<div class="zf-tablas zf-llaves">';
		if ( ! $llaves ) {
			echo '<p class="zf-vacio">' . esc_html__( 'Todavía no hay llaves generadas.', 'zonas-partidos-futbol' ) . '</p>';
		}
		foreach ( $llaves as $llave ) {
			echo ZF_Llaves::render_llave( $llave ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
		}
		echo '</div>';
		return ob_get_clean();
	}
}
