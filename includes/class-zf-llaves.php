<?php
/**
 * Fase eliminatoria: generación de llaves, avance dinámico y render.
 *
 * @package ZonasFutbol
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase ZF_Llaves
 */
class ZF_Llaves {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'registrar_metabox' ) );
		add_action( 'save_post_' . ZF_Install::CPT_LLAVE, array( __CLASS__, 'guardar_llave' ), 10, 2 );

		// Recalcula la llave cuando se guarda un partido (después del metabox, prioridad 10).
		add_action( 'save_post_' . ZF_Install::CPT_PARTIDO, array( __CLASS__, 'al_guardar_partido' ), 20, 2 );
	}

	// ============================ Admin ====================================.

	/**
	 * Registra el metabox de configuración de la llave.
	 */
	public static function registrar_metabox() {
		add_meta_box(
			'zf_datos_llave',
			__( 'Configuración de la llave', 'zonas-partidos-futbol' ),
			array( __CLASS__, 'render_metabox' ),
			ZF_Install::CPT_LLAVE,
			'normal',
			'high'
		);
	}

	/**
	 * Renderiza el formulario de la llave.
	 *
	 * @param WP_Post $post Llave.
	 */
	public static function render_metabox( $post ) {
		wp_nonce_field( 'zf_llave_meta', 'zf_llave_nonce' );

		$zonas_elegidas = get_post_meta( $post->ID, '_zf_zonas', true );
		$zonas_elegidas = is_array( $zonas_elegidas ) ? array_map( 'absint', $zonas_elegidas ) : array();
		$clasificados   = (int) get_post_meta( $post->ID, '_zf_clasificados', true );
		if ( $clasificados < 1 ) {
			$clasificados = (int) apply_filters( 'zf_clasificados_por_zona', 2 );
		}

		$error = get_transient( 'zf_error_' . $post->ID );
		if ( $error ) {
			delete_transient( 'zf_error_' . $post->ID );
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}

		$zonas = ZF_Helpers::zonas();

		if ( ! $zonas ) {
			echo '<p>' . esc_html__( 'Primero creá zonas en Campeonato → Zonas.', 'zonas-partidos-futbol' ) . '</p>';
			return;
		}
		?>
		<div class="zf-form">
			<section class="zf-seccion zf-seccion--cruce">
				<header class="zf-seccion-head">
					<span class="zf-seccion-icono dashicons dashicons-shield" aria-hidden="true"></span>
					<div class="zf-seccion-textos">
						<h4><?php esc_html_e( 'Zonas que clasifican', 'zonas-partidos-futbol' ); ?></h4>
						<p><?php esc_html_e( 'Elegí de qué zonas se toman los mejores equipos.', 'zonas-partidos-futbol' ); ?></p>
					</div>
				</header>
				<div class="zf-chips">
					<?php foreach ( $zonas as $zona ) : ?>
						<label class="zf-chip">
							<input type="checkbox" name="zf_llave_zonas[]" value="<?php echo esc_attr( $zona->term_id ); ?>" <?php checked( in_array( (int) $zona->term_id, $zonas_elegidas, true ) ); ?> />
							<span><?php echo esc_html( $zona->name ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="zf-seccion zf-seccion--agenda">
				<header class="zf-seccion-head">
					<span class="zf-seccion-icono dashicons dashicons-awards" aria-hidden="true"></span>
					<div class="zf-seccion-textos">
						<h4><?php esc_html_e( 'Formato del cuadro', 'zonas-partidos-futbol' ); ?></h4>
						<p><?php esc_html_e( 'Cuántos equipos clasifica cada zona. El tamaño del cuadro se ajusta solo.', 'zonas-partidos-futbol' ); ?></p>
					</div>
				</header>
				<div class="zf-seccion-grid">
					<p class="zf-campo">
						<label for="zf_clasificados"><?php esc_html_e( 'Clasificados por zona', 'zonas-partidos-futbol' ); ?></label>
						<input type="number" name="zf_clasificados" id="zf_clasificados" min="1" max="16" value="<?php echo esc_attr( $clasificados ); ?>" class="zf-input-num" />
					</p>
				</div>
			</section>

			<div class="zf-peligro">
				<label class="zf-peligro-fila">
					<input type="checkbox" name="zf_regenerar" id="zf_regenerar" value="1" />
					<span class="zf-peligro-texto">
						<strong><?php esc_html_e( 'Regenerar fixture', 'zonas-partidos-futbol' ); ?></strong>
						<?php esc_html_e( 'Borra todos los partidos actuales de esta llave y los vuelve a crear desde las tablas de posiciones.', 'zonas-partidos-futbol' ); ?>
					</span>
				</label>
			</div>

			<?php self::panel_estado( $post ); ?>
			<p class="zf-metabox-nota"><?php esc_html_e( 'Al regenerar, los clasificados se toman de las tablas (primeros puestos). Los resultados cargados hacen avanzar a los equipos automáticamente hasta definir al campeón.', 'zonas-partidos-futbol' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Muestra el estado actual del fixture generado.
	 *
	 * @param WP_Post $post Llave.
	 */
	private static function panel_estado( $post ) {
		$config = get_post_meta( $post->ID, '_zf_config', true );
		if ( ! is_array( $config ) || empty( $config['size'] ) ) {
			echo '<div class="zf-panel-vacio">' . esc_html__( 'Todavía no se generó el fixture. Tildá "Regenerar fixture" y guardá para crearlo.', 'zonas-partidos-futbol' ) . '</div>';
			return;
		}

		$campeon = (int) get_post_meta( $post->ID, '_zf_campeon', true );
		$size    = (int) $config['size'];
		$total   = (int) ( $config['total'] ?? 0 );
		$rondas  = (int) round( log( max( 2, $size ), 2 ) );

		echo '<section class="zf-panel-estado">';
		echo '<header class="zf-panel-head">';
		echo '<h4>' . esc_html__( 'Fixture generado', 'zonas-partidos-futbol' ) . '</h4>';
		echo '<span class="zf-resumen-chip">' . esc_html(
			sprintf(
				/* translators: 1: clasificados reales. 2: tamaño del cuadro. */
				__( '%1$d equipos · cuadro de %2$d', 'zonas-partidos-futbol' ),
				$total,
				$size
			)
		) . '</span>';
		echo '</header>';

		echo '<table class="zf-panel-rondas"><tbody>';
		$agrupados = self::partidos_de_llave( $post->ID );

		for ( $r = 0; $r < $rondas; $r++ ) {
			echo '<tr>';
			echo '<th scope="row"><span class="zf-ronda-badge zf-ronda-' . esc_attr( min( $r, 3 ) ) . '">' . esc_html( self::etiqueta_ronda( $size, $r ) ) . '</span></th>';
			echo '<td>';
			if ( empty( $agrupados[ $r ] ) ) {
				echo '<em>' . esc_html__( 'sin partidos', 'zonas-partidos-futbol' ) . '</em>';
			} else {
				$items = array();
				foreach ( $agrupados[ $r ] as $partido ) {
					$estado  = (string) get_post_meta( $partido->ID, '_zf_estado', true );
					$clase   = ZF_Helpers::ESTADO_FINALIZADO === $estado ? ' zf-listo' : '';
					$items[] = '<a class="zf-enlace-partido' . esc_attr( $clase ) . '" href="' . esc_url( get_edit_post_link( $partido->ID ) ) . '">' . esc_html( $partido->post_title ) . '</a>';
				}
				echo implode( '<span class="zf-sep-puntos">·</span>', $items ); // phpcs:ignore WordPress.Security.EscapeOutput -- piezas escapadas arriba.
			}
			echo '</td></tr>';
		}

		echo '<tr class="zf-fila-campeon"><th scope="row"><span class="zf-ronda-badge zf-ronda-campeon">' . esc_html__( 'Campeón', 'zonas-partidos-futbol' ) . '</span></th><td>';
		if ( $campeon ) {
			echo '<strong>' . esc_html( ZF_Helpers::nombre_equipo( $campeon ) ) . '</strong>';
		} else {
			echo '<em>' . esc_html__( 'se define al terminar la final', 'zonas-partidos-futbol' ) . '</em>';
		}
		echo '</td></tr>';

		echo '</tbody></table></section>';
	}

	/**
	 * Guarda la configuración y regenera si corresponde.
	 *
	 * @param int     $post_id ID.
	 * @param WP_Post $post    Llave.
	 */
	public static function guardar_llave( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		$nonce = isset( $_POST['zf_llave_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['zf_llave_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'zf_llave_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Zonas seleccionadas.
		$zonas_in = isset( $_POST['zf_llave_zonas'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['zf_llave_zonas'] ) ) : array();
		$zonas    = array();
		foreach ( $zonas_in as $term_id ) {
			$term = get_term( $term_id, ZF_Install::TAX_ZONA );
			if ( $term && ! is_wp_error( $term ) ) {
				$zonas[] = (int) $term_id;
			}
		}

		$n = isset( $_POST['zf_clasificados'] ) ? absint( $_POST['zf_clasificados'] ) : 2;
		$n = min( 16, max( 1, $n ) );

		update_post_meta( $post_id, '_zf_zonas', $zonas );
		update_post_meta( $post_id, '_zf_clasificados', $n );

		$regenerar = ! empty( $_POST['zf_regenerar'] );
		if ( $regenerar ) {
			if ( ! $zonas ) {
				set_transient( 'zf_error_' . $post_id, __( 'Elegí al menos una zona para generar el fixture.', 'zonas-partidos-futbol' ), 60 );
				return;
			}
			self::generar_fixture( $post_id, $zonas, $n );
		}
	}

	// ========================= Generación ==================================.

	/**
	 * Genera el cuadro completo a partir de las tablas de posiciones.
	 *
	 * @param int   $llave_id ID de la llave.
	 * @param int[] $zonas_ids Term IDs de zonas.
	 * @param int   $n        Clasificados por zona.
	 * @return bool
	 */
	public static function generar_fixture( $llave_id, $zonas_ids, $n ) {
		// Semillas intercalando posiciones entre zonas: A1, B1, A2, B2…
		$seeds = array();
		for ( $pos = 0; $pos < $n; $pos++ ) {
			foreach ( $zonas_ids as $zona_id ) {
				$term  = get_term( $zona_id, ZF_Install::TAX_ZONA );
				$filas = ( $term && ! is_wp_error( $term ) ) ? ZF_Helpers::tabla_zona( $term ) : array();
				if ( isset( $filas[ $pos ] ) ) {
					$seeds[] = (int) $filas[ $pos ]['id'];
				}
			}
		}

		if ( count( $seeds ) < 2 ) {
			set_transient( 'zf_error_' . $llave_id, __( 'No hay suficientes equipos con resultados para armar la llave.', 'zonas-partidos-futbol' ), 60 );
			return false;
		}

		// Tamaño del cuadro: potencia de 2 inmediata superior.
		$size = 2;
		while ( $size < count( $seeds ) ) {
			$size *= 2;
		}

		// Relleno con byes (0) y orden estándar de siembras.
		while ( count( $seeds ) < $size ) {
			$seeds[] = 0;
		}
		$orden   = self::orden_seeds( $size );
		$cuadro  = array();
		foreach ( $orden as $seed_num ) {
			$cuadro[] = isset( $seeds[ $seed_num - 1 ] ) ? (int) $seeds[ $seed_num - 1 ] : 0;
		}

		// Borrar los partidos anteriores de esta llave.
		foreach ( get_posts(
			array(
				'post_type'      => ZF_Install::CPT_PARTIDO,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_zf_llave',
				'meta_value'     => (int) $llave_id,
			)
		) as $viejo ) {
			wp_delete_post( (int) $viejo, true );
		}

		update_post_meta( $llave_id, '_zf_config', array(
			'zonas'  => array_map( 'intval', $zonas_ids ),
			'n'      => (int) $n,
			'size'   => (int) $size,
			'total'  => count( array_filter( $cuadro ) ),
			'seeds'  => array_map( 'intval', $cuadro ),
		) );

		// Crear el árbol completo: todas las rondas existen desde el inicio;
		// los equipos pendientes se completan solos al cargarse resultados.
		$num          = 0;
		$rondas_total = (int) round( log( $size, 2 ) );

		for ( $r = 0; $r < $rondas_total; $r++ ) {
			$cruces   = $size >> ( $r + 1 );
			$etiqueta = self::etiqueta_ronda( $size, $r );

			for ( $m = 0; $m < $cruces; $m++ ) {
				if ( 0 === $r ) {
					$a = $cuadro[ 2 * $m ];
					$b = $cuadro[ 2 * $m + 1 ];
					// Ronda inicial: solo se crea el cruce si juegan dos equipos reales (el resto son byes).
					if ( $a && $b ) {
						self::crear_partido_llave( $llave_id, $r, $m, ++$num, $etiqueta, $a, $b );
					}
				} else {
					self::crear_partido_llave( $llave_id, $r, $m, ++$num, $etiqueta );
				}
			}
		}

		self::recalcular( $llave_id );
		return true;
	}

	/**
	 * Orden estándar de siembras para un cuadro de $size (1 vs último).
	 *
	 * @param int $size Potencia de 2.
	 * @return int[] Números de semilla en orden de llave.
	 */
	private static function orden_seeds( $size ) {
		$orden = array( 1 );
		while ( count( $orden ) < $size ) {
			$siguiente = array();
			$espejo    = count( $orden ) * 2 + 1;
			foreach ( $orden as $s ) {
				$siguiente[] = $s;
				$siguiente[] = $espejo - $s;
			}
			$orden = $siguiente;
		}
		return $orden;
	}

	/**
	 * Crea un partido perteneciente a una llave.
	 *
	 * @param int    $llave_id ID de llave.
	 * @param int    $ronda    Índice de ronda.
	 * @param int    $slot     Posición dentro de la ronda.
	 * @param int    $num      Numeración global dentro de la llave.
	 * @param string $etiqueta Etiqueta de la ronda.
	 * @param int    $local    Equipo local (0 = pendiente).
	 * @param int    $visita   Equipo visitante (0 = pendiente).
	 * @return int ID del partido.
	 */
	private static function crear_partido_llave( $llave_id, $ronda, $slot, $num, $etiqueta, $local = 0, $visita = 0 ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => ZF_Install::CPT_PARTIDO,
				'post_status' => 'publish',
				'post_title'  => sprintf( '%s · Partido %d', $etiqueta, $num ),
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return 0;
		}

		update_post_meta( $post_id, '_zf_local', (int) $local );
		update_post_meta( $post_id, '_zf_visitante', (int) $visita );
		update_post_meta( $post_id, '_zf_lugar', '' );
		update_post_meta( $post_id, '_zf_fecha', '' );
		update_post_meta( $post_id, '_zf_jornada', '' );
		update_post_meta( $post_id, '_zf_estado', ZF_Helpers::ESTADO_PROGRAMADO );
		update_post_meta( $post_id, '_zf_goles_local', 0 );
		update_post_meta( $post_id, '_zf_goles_visitante', 0 );
		update_post_meta( $post_id, '_zf_llave', (int) $llave_id );
		update_post_meta( $post_id, '_zf_ronda', (int) $ronda );
		update_post_meta( $post_id, '_zf_slot', (int) $slot );
		update_post_meta( $post_id, '_zf_num', (int) $num );

		return (int) $post_id;
	}

	/**
	 * Recalcula todos los cruces de la llave según los resultados cargados:
	 * avanza ganadores ronda por ronda hasta definir el campeón.
	 *
	 * @param int $llave_id ID de la llave.
	 */
	public static function recalcular( $llave_id ) {
		$config = get_post_meta( $llave_id, '_zf_config', true );
		if ( ! is_array( $config ) || empty( $config['size'] ) || ! isset( $config['seeds'] ) || ! is_array( $config['seeds'] ) ) {
			return;
		}

		$size     = (int) $config['size'];
		$seeds    = array_map( 'intval', $config['seeds'] );
		$rondas   = (int) round( log( $size, 2 ) );
		$agrupado = self::partidos_de_llave( $llave_id );

		// Ronda 0: byes avanzan solos; los cruces reales dependen de su resultado.
		$ganadores = array();
		$cant_r0   = $size >> 1;
		for ( $m = 0; $m < $cant_r0; $m++ ) {
			$a = isset( $seeds[ 2 * $m ] ) ? $seeds[ 2 * $m ] : 0;
			$b = isset( $seeds[ 2 * $m + 1 ] ) ? $seeds[ 2 * $m + 1 ] : 0;
			if ( $a && $b ) {
				$partido      = isset( $agrupado[0][ $m ] ) ? $agrupado[0][ $m ] : null;
				$ganadores[ $m ] = $partido ? self::ganador_de( $partido ) : 0;
			} else {
				$ganadores[ $m ] = $a ? $a : $b;
			}
		}

		// Rondas siguientes: poblar equipos y calcular ganadores en cadena.
		for ( $r = 1; $r < $rondas; $r++ ) {
			$cruces    = $size >> ( $r + 1 );
			$siguiente = array();
			for ( $j = 0; $j < $cruces; $j++ ) {
				$local   = isset( $ganadores[ 2 * $j ] ) ? (int) $ganadores[ 2 * $j ] : 0;
				$visita  = isset( $ganadores[ 2 * $j + 1 ] ) ? (int) $ganadores[ 2 * $j + 1 ] : 0;
				$partido = isset( $agrupado[ $r ][ $j ] ) ? $agrupado[ $r ][ $j ] : null;

				if ( $partido ) {
					if ( (int) get_post_meta( $partido->ID, '_zf_local', true ) !== $local ) {
						update_post_meta( $partido->ID, '_zf_local', $local );
					}
					if ( (int) get_post_meta( $partido->ID, '_zf_visitante', true ) !== $visita ) {
						update_post_meta( $partido->ID, '_zf_visitante', $visita );
					}
					$siguiente[ $j ] = self::ganador_de( $partido );
				} else {
					$siguiente[ $j ] = 0;
				}
			}
			$ganadores = $siguiente;
		}

		// Campeón: ganador del partido final.
		$final    = isset( $agrupado[ $rondas - 1 ][0] ) ? $agrupado[ $rondas - 1 ][0] : null;
		$campeon  = $final ? self::ganador_de( $final ) : 0;
		if ( $campeon ) {
			update_post_meta( $llave_id, '_zf_campeon', $campeon );
		} else {
			delete_post_meta( $llave_id, '_zf_campeon' );
		}
	}

	/**
	 * Al guardarse un partido con llave asignada, recalcula esa llave.
	 *
	 * @param int     $post_id ID del partido.
	 * @param WP_Post $post    Post.
	 */
	public static function al_guardar_partido( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$llave_id = (int) get_post_meta( $post_id, '_zf_llave', true );
		if ( $llave_id && ZF_Install::CPT_LLAVE === get_post_type( $llave_id ) ) {
			self::recalcular( $llave_id );
		}
		unset( $post );
	}

	// ============================ Consultas ================================.

	/**
	 * Partidos de la llave agrupados por ronda y ordenados por slot.
	 *
	 * @param int $llave_id ID.
	 * @return array [ronda][slot] => WP_Post
	 */
	public static function partidos_de_llave( $llave_id ) {
		$partidos = get_posts(
			array(
				'post_type'      => ZF_Install::CPT_PARTIDO,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => '_zf_llave',
				'meta_value'     => (int) $llave_id,
			)
		);

		$agrupado = array();
		foreach ( $partidos as $partido ) {
			$ronda = (int) get_post_meta( $partido->ID, '_zf_ronda', true );
			$slot  = (int) get_post_meta( $partido->ID, '_zf_slot', true );
			$agrupado[ $ronda ][ $slot ] = $partido;
		}
		foreach ( $agrupado as $r => $fila ) {
			ksort( $agrupado[ $r ] );
		}
		ksort( $agrupado );
		return $agrupado;
	}

	/**
	 * Ganador de un partido (0 si no finalizó o empató).
	 *
	 * @param WP_Post|int $partido Partido.
	 * @return int ID de equipo o 0.
	 */
	public static function ganador_de( $partido ) {
		$d = ZF_Helpers::datos_partido( $partido );
		if ( ! $d || ZF_Helpers::ESTADO_FINALIZADO !== $d['estado'] ) {
			return 0;
		}
		if ( $d['gl'] > $d['gv'] ) {
			return (int) $d['local'];
		}
		if ( $d['gv'] > $d['gl'] ) {
			return (int) $d['visitante'];
		}
		return 0;
	}

	/**
	 * Etiqueta de una ronda según el tamaño del cuadro.
	 *
	 * @param int $size  Tamaño total.
	 * @param int $ronda Índice desde 0.
	 * @return string
	 */
	public static function etiqueta_ronda( $size, $ronda ) {
		$restantes = max( 1, (int) $size >> (int) $ronda );
		$mapa      = array(
			1   => __( 'Final', 'zonas-partidos-futbol' ),
			2   => __( 'Semifinales', 'zonas-partidos-futbol' ),
			4   => __( 'Cuartos de final', 'zonas-partidos-futbol' ),
			8   => __( 'Octavos de final', 'zonas-partidos-futbol' ),
			16  => __( 'Dieciseisavos de final', 'zonas-partidos-futbol' ),
			32  => __( 'Treintaidosavos de final', 'zonas-partidos-futbol' ),
		);
		return isset( $mapa[ $restantes ] ) ? $mapa[ $restantes ] : sprintf( __( 'Ronda %d', 'zonas-partidos-futbol' ), $ronda + 1 );
	}

	// ============================ Frontend =================================.

	/**
	 * Render del cuadro en el frontend.
	 *
	 * @param WP_Post $llave Post de la llave.
	 * @return string
	 */
	public static function render_llave( $llave ) {
		$config = get_post_meta( $llave->ID, '_zf_config', true );
		ob_start();

		echo '<section class="zf-tabla-wrap zf-llave-wrap">';
		echo '<header class="zf-tabla-head">';
		echo '<h3 class="zf-tabla-titulo">' . ZF_Helpers::icono_trofeo() . esc_html( $llave->post_title ) . '</h3>';
		echo '<span class="zf-tabla-sub">' . esc_html__( 'Eliminatorias', 'zonas-partidos-futbol' ) . '</span>';
		echo '</header>';

		if ( ! is_array( $config ) || empty( $config['size'] ) ) {
			echo '<p class="zf-vacio">' . esc_html__( 'Esta llave todavía no tiene fixture generado.', 'zonas-partidos-futbol' ) . '</p>';
			echo '</section>';
			return ob_get_clean();
		}

		$size     = (int) $config['size'];
		$total    = (int) ( $config['total'] ?? 0 );
		$rondas   = (int) round( log( $size, 2 ) );
		$agrupado = self::partidos_de_llave( $llave->ID );
		$campeon  = (int) get_post_meta( $llave->ID, '_zf_campeon', true );

		echo '<div class="zf-llave-scroll"><div class="zf-llave-grid">';

		$prev = array();
		for ( $r = 0; $r < $rondas; $r++ ) {
			$cruces = $size >> ( $r + 1 );
			echo '<div class="zf-llave-ronda">';
			echo '<h4 class="zf-llave-ronda-titulo">' . esc_html( self::etiqueta_ronda( $size, $r ) ) . '</h4>';

			for ( $j = 0; $j < $cruces; $j++ ) {
				$partido = isset( $agrupado[ $r ][ $j ] ) ? $agrupado[ $r ][ $j ] : null;
				echo self::render_partido_llave( $partido, $r, $j, $prev, $total ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapado dentro.
			}
			echo '</div>';

			$prev = isset( $agrupado[ $r ] ) ? $agrupado[ $r ] : array();
		}

		if ( $campeon ) {
			echo '<div class="zf-campeon-col">';
			echo '<span class="zf-campeon-etiqueta">' . esc_html__( 'Campeón', 'zonas-partidos-futbol' ) . '</span>';
			echo '<div class="zf-campeon-card">';
			echo ZF_Helpers::icono_trofeo();
			echo ZF_Helpers::render_avatar( $campeon, 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
			echo '<strong>' . esc_html( ZF_Helpers::nombre_equipo( $campeon ) ) . '</strong>';
			echo '</div></div>';
		}

		echo '</div></div></section>';
		return ob_get_clean();
	}

	/**
	 * Mini tarjeta de cruce para el cuadro.
	 *
	 * @param WP_Post|null $partido Partido (null = no creado aún).
	 * @param int          $ronda   Ronda.
	 * @param int          $j       Índice del cruce.
	 * @param array        $prev    Partidos de la ronda anterior (para placeholders).
	 * @return string
	 */
	private static function render_partido_llave( $partido, $ronda, $j, $prev, $total ) {
		if ( ! $partido ) {
			return '<div class="zf-llave-partido"><div class="zf-lp-fila"><span class="zf-lp-placeholder">' . esc_html__( 'A definir', 'zonas-partidos-futbol' ) . '</span></div><div class="zf-lp-fila"><span class="zf-lp-placeholder">' . esc_html__( 'A definir', 'zonas-partidos-futbol' ) . '</span></div></div>';
		}

		$d       = ZF_Helpers::datos_partido( $partido );
		$ganador = self::ganador_de( $partido );

		$lados = array();
		foreach ( array( 'local', 'visitante' ) as $idx => $lado ) {
			$id   = (int) $d[ $lado ];
			$goles = (int) $d[ ( 'local' === $lado ) ? 'gl' : 'gv' ];
			$clase = 'zf-lp-fila';
			if ( $ganador && $id === $ganador ) {
				$clase .= ' zf-ganador';
			} elseif ( $ganador && ZF_Helpers::ESTADO_FINALIZADO === $d['estado'] ) {
				$clase .= ' zf-perdedor';
			}

			if ( $id ) {
				$nombre = ZF_Helpers::nombre_equipo( $id );
				$avatar = ZF_Helpers::render_avatar( $id );
			} else {
				// Placeholder: "Ganador P#" del cruce origen de la ronda anterior.
				$origen  = isset( $prev[ 2 * $j + $idx ] ) ? $prev[ 2 * $j + $idx ] : null;
				$num_src = $origen ? (string) get_post_meta( $origen->ID, '_zf_num', true ) : '';
				$nombre  = $num_src ? sprintf( /* translators: %s: número de partido. */ __( 'Ganador P%s', 'zonas-partidos-futbol' ), $num_src ) : __( 'A definir', 'zonas-partidos-futbol' );
				$avatar  = '';
			}

			$lados[] = '<div class="' . esc_attr( $clase ) . '">'
				. $avatar
				. '<span class="zf-lp-equipo">' . esc_html( $nombre ) . '</span>'
				. ( ZF_Helpers::ESTADO_FINALIZADO === $d['estado'] ? '<b class="zf-lp-goles">' . esc_html( $goles ) . '</b>' : '' )
				. '</div>';
		}

		$extra = '';
		if ( ZF_Helpers::ESTADO_SUSPENDIDO === $d['estado'] ) {
			$extra = '<div class="zf-lp-pie">' . esc_html( ZF_Helpers::estado_label( $d['estado'] ) ) . '</div>';
		}

		return '<div class="zf-llave-partido">' . implode( '', $lados ) . $extra . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- contenido escapado arriba.
	}
}
