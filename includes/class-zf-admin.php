<?php
/**
 * Administración: columnas y aviso de dependencia.
 *
 * @package ZonasFutbol
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase ZF_Admin
 */
class ZF_Admin {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_filter( 'manage_zf_partido_posts_columns', array( __CLASS__, 'columnas_partido' ) );
		add_action( 'manage_zf_partido_posts_custom_column', array( __CLASS__, 'contenido_columna' ), 10, 2 );
		add_filter( 'manage_zf_llave_posts_columns', array( __CLASS__, 'columnas_llave' ) );
		add_action( 'manage_zf_llave_posts_custom_column', array( __CLASS__, 'contenido_columna' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'css_admin' ) );
		add_action( 'admin_head', array( __CLASS__, 'submenu_llave' ) );
	}

	/**
	 * Destaca la opción "Llaves" en el submenú del plugin.
	 * Las llaves son la instancia final del torneo, así que se
	 * resaltan siempre (estrella dorada + acento esmeralda).
	 */
	public static function submenu_llave() {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = $screen ? (string) $screen->post_type : '';
		$activa    = ZF_Install::CPT_LLAVE === $post_type;
		?>
		<style>
		#adminmenu #menu-posts-zf_partido .wp-submenu a[href="edit.php?post_type=zf_llave"] {
			position: relative;
			padding-left: 24px;
			color: #a7f3d0;
			font-weight: 700;
		}
		#adminmenu #menu-posts-zf_partido .wp-submenu a[href="edit.php?post_type=zf_llave"]::before {
			content: "\2605";
			position: absolute;
			left: 9px;
			top: 50%;
			transform: translateY(-48%);
			font-size: 10px;
			line-height: 1;
			color: #fbbf24;
		}
		#adminmenu #menu-posts-zf_partido .wp-submenu a[href="edit.php?post_type=zf_llave"]:hover,
		#adminmenu #menu-posts-zf_partido .wp-submenu a[href="edit.php?post_type=zf_llave"].current {
			background: rgba(34, 197, 94, .16);
			color: #f0fdf4;
		}
		<?php if ( $activa ) : ?>
		#adminmenu #menu-posts-zf_partido .wp-submenu a[href="edit.php?post_type=zf_llave"].current {
			border-left: 3px solid #22c55e;
			margin-left: -3px;
			color: #fff;
		}
		<?php endif; ?>
		</style>
		<?php
	}

	/**
	 * Aviso si falta el plugin de inscripciones.
	 */
	public static function aviso_dependencia() {
		if ( post_type_exists( 'if_equipo' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		printf(
			wp_kses_post( __( '<strong>Zonas y Partidos de Fútbol:</strong> el plugin <em>Inscripciones Fútbol</em> no está activo. Sin él no vas a ver los equipos inscriptos.', 'zonas-partidos-futbol' ) )
		);
		echo '</p></div>';
	}

	/**
	 * Columnas personalizadas en la lista de partidos.
	 *
	 * @param array $columns Columnas.
	 * @return array
	 */
	public static function columnas_partido( $columns ) {
		unset( $columns['date'] );
		$nuevas = array();
		foreach ( $columns as $clave => $titulo ) {
			$nuevas[ $clave ] = $titulo;
			if ( 'title' === $clave ) {
				$nuevas['zf_zona']     = __( 'Zona', 'zonas-partidos-futbol' );
				$nuevas['zf_fecha']    = __( 'Fecha y hora', 'zonas-partidos-futbol' );
				$nuevas['zf_marcador'] = __( 'Resultado / Estado', 'zonas-partidos-futbol' );
			}
		}
		return $nuevas;
	}

	/**
	 * Columnas personalizadas en la lista de llaves.
	 *
	 * @param array $columns Columnas.
	 * @return array
	 */
	public static function columnas_llave( $columns ) {
		unset( $columns['date'] );
		$nuevas = array();
		foreach ( $columns as $clave => $titulo ) {
			$nuevas[ $clave ] = $titulo;
			if ( 'title' === $clave ) {
				$nuevas['zf_llave_info'] = __( 'Fixture', 'zonas-partidos-futbol' );
			}
		}
		return $nuevas;
	}

	/**
	 * Contenido de las columnas personalizadas.
	 *
	 * @param string $column  Columna.
	 * @param int    $post_id ID.
	 */
	public static function contenido_columna( $column, $post_id ) {
		if ( 'zf_llave_info' === $column ) {
			self::celda_llave( $post_id );
			return;
		}

		$d = ZF_Helpers::datos_partido( $post_id );
		if ( ! $d ) {
			return;
		}
		switch ( $column ) {
			case 'zf_zona':
				$terminos = get_the_terms( $post_id, ZF_Install::TAX_ZONA );
				if ( $terminos && ! is_wp_error( $terminos ) ) {
					echo esc_html( implode( ', ', wp_list_pluck( $terminos, 'name' ) ) );
				} else {
					$llave_id = (int) get_post_meta( $post_id, '_zf_llave', true );
					echo $llave_id ? '<span class="zf-badge zf-badge-llave">' . esc_html__( 'Playoffs', 'zonas-partidos-futbol' ) . '</span>' : '—';
				}
				break;
			case 'zf_fecha':
				echo $d['fecha'] ? esc_html( ZF_Helpers::fecha( $d['fecha'] ) ) : '—';
				break;
			case 'zf_marcador':
				if ( ZF_Helpers::ESTADO_FINALIZADO === $d['estado'] ) {
					echo '<strong class="zf-celda-marcador">' . esc_html( $d['gl'] . ' - ' . $d['gv'] ) . '</strong> ';
				}
				echo '<span class="zf-badge zf-badge-' . esc_attr( $d['estado'] ) . '">' . esc_html( ZF_Helpers::estado_label( $d['estado'] ) ) . '</span>';
				if ( ZF_Helpers::definido_por_penales( $d ) ) {
					echo ' <span class="zf-ronda-mini" title="' . esc_attr__( 'Definido por penales', 'zonas-partidos-futbol' ) . '">' . esc_html(
						sprintf(
							/* translators: 1: penales del local. 2: penales del visitante. */
							__( 'Pen. %1$d–%2$d', 'zonas-partidos-futbol' ),
							$d['pl'],
							$d['pv']
						)
					) . '</span>';
				}
				$ronda = (int) get_post_meta( $post_id, '_zf_ronda', true );
				if ( get_post_meta( $post_id, '_zf_llave', true ) ) {
					$llave_id  = (int) get_post_meta( $post_id, '_zf_llave', true );
					$config    = get_post_meta( $llave_id, '_zf_config', true );
					$size      = is_array( $config ) && ! empty( $config['size'] ) ? (int) $config['size'] : 0;
					$etiqueta  = $size ? ZF_Llaves::etiqueta_ronda( $size, max( 0, $ronda ) ) : '';
					if ( $etiqueta ) {
						echo ' <span class="zf-ronda-mini">' . esc_html( $etiqueta ) . '</span>';
					}
				}
				break;
		}
	}

	/**
	 * Celda de resumen para la lista de llaves.
	 *
	 * @param int $post_id ID de la llave.
	 */
	private static function celda_llave( $post_id ) {
		$config = get_post_meta( $post_id, '_zf_config', true );
		if ( ! is_array( $config ) || empty( $config['size'] ) ) {
			echo '<em>' . esc_html__( 'Sin generar — abrí y guardá para crear el cuadro.', 'zonas-partidos-futbol' ) . '</em>';
			return;
		}

		$campeon = (int) get_post_meta( $post_id, '_zf_campeon', true );
		echo '<div class="zf-llave-resumen">';
		echo '<span class="zf-chip-info">' . esc_html(
			sprintf(
				/* translators: 1: number of teams. 2: bracket size. */
				__( '%1$d equipos · cuadro de %2$d', 'zonas-partidos-futbol' ),
				(int) ( $config['total'] ?? 0 ),
				(int) $config['size']
			)
		) . '</span>';

		if ( $campeon ) {
			echo '<span class="zf-chip-info zf-chip-campeon">' . esc_html( ZF_Helpers::nombre_equipo( $campeon ) ) . '</span>';
		} else {
			$jugados  = 0;
			$partidos = ZF_Llaves::partidos_de_llave( $post_id );
			foreach ( $partidos as $grupo ) {
				foreach ( $grupo as $partido ) {
					if ( ZF_Helpers::ESTADO_FINALIZADO === get_post_meta( $partido->ID, '_zf_estado', true ) ) {
						$jugados++;
					}
				}
			}
			$total_partidos = 0;
			foreach ( $partidos as $grupo ) {
				$total_partidos += count( $grupo );
			}
			echo '<span class="zf-chip-info zf-chip-progreso">' . esc_html(
				sprintf(
					/* translators: 1: jugados. 2: totales. */
					__( '%1$d / %2$d jugados', 'zonas-partidos-futbol' ),
					$jugados,
					$total_partidos
				)
			) . '</span>';
		}
		echo '</div>';
	}

	/**
	 * CSS del admin (pantallas del plugin).
	 *
	 * @param string $hook Hook actual.
	 */
	public static function css_admin( $hook ) {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = $screen ? (string) $screen->post_type : '';
		$es_cpt    = in_array( $post_type, array( ZF_Install::CPT_PARTIDO, ZF_Install::CPT_LLAVE ), true );
		$es_tax    = $screen && 'edit-tags' === $screen->base && ZF_Install::TAX_ZONA === $screen->taxonomy;

		if ( ! $es_cpt && ! $es_tax && 'toplevel_page_zf_zona' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'zf-frontend',
			ZF_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			ZF_VERSION
		);
		wp_enqueue_style(
			'zf-admin',
			ZF_PLUGIN_URL . 'assets/css/admin.css',
			array( 'zf-frontend' ),
			ZF_VERSION
		);
	}
}