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

		$equipos_seleccionados = get_post_meta( $post->ID, '_zf_equipos_seleccionados', true );
		$equipos_seleccionados = is_array( $equipos_seleccionados ) ? array_map( 'absint', $equipos_seleccionados ) : array();

		$error = get_transient( 'zf_error_' . $post->ID );
		if ( $error ) {
			delete_transient( 'zf_error_' . $post->ID );
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}

		$equipos = ZF_Helpers::equipos();

		if ( ! $equipos ) {
			echo '<p>' . esc_html__( 'No hay equipos inscriptos. Primero cargá equipos desde Inscripciones Fútbol.', 'zonas-partidos-futbol' ) . '</p>';
			return;
		}

		$count      = count( $equipos_seleccionados );
		$cuadro_tmp = self::calcular_cuadro( $count );

		// Enqueue the admin llave JS.
		wp_enqueue_script(
			'zf-llave-admin',
			ZF_PLUGIN_URL . 'assets/js/llave-admin.js',
			array(),
			ZF_VERSION,
			true
		);
		?>
		<div class="zf-form" id="zf-llave-admin-root">

			<section class="zf-seccion zf-seccion--cruce">
				<header class="zf-seccion-head">
					<span class="zf-seccion-icono dashicons dashicons-shield" aria-hidden="true"></span>
					<div class="zf-seccion-textos">
						<h4><?php esc_html_e( 'Equipos participantes', 'zonas-partidos-futbol' ); ?></h4>
						<p><?php esc_html_e( 'Buscá y agregá equipos. Arrastrá para reordenar el seeding (1 = cabeza de serie).', 'zonas-partidos-futbol' ); ?></p>
					</div>
				</header>

				<div class="zf-llave-buscar-wrap">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<input type="search" id="zf-ll-buscar" class="zf-llave-buscar" placeholder="<?php esc_attr_e( 'Buscar equipo por nombre…', 'zonas-partidos-futbol' ); ?>" autocomplete="off" />
				</div>

				<div class="zf-llave-two-panel">

					<div class="zf-llave-col">
						<div class="zf-ll-col-head">
							<strong><?php esc_html_e( 'Disponibles', 'zonas-partidos-futbol' ); ?></strong>
							<span class="zf-ll-count" id="zf-ll-disp-count"><?php echo esc_html( count( $equipos ) - $count ); ?></span>
						</div>
						<div class="zf-ll-lista zf-ll-lista-disp" id="zf-ll-disponibles"></div>
					</div>

					<div class="zf-llave-col zf-llave-col-sel">
						<div class="zf-ll-col-head">
							<div class="zf-ll-col-head-left">
								<strong><?php esc_html_e( 'Seleccionados', 'zonas-partidos-futbol' ); ?></strong>
								<span class="zf-ll-count" id="zf-ll-sel-count"><?php echo esc_html( $count ); ?></span>
							</div>
							<div class="zf-ll-col-head-right">
								<label class="zf-equipos-toggle-all">
									<input type="checkbox" id="zf_toggle_todos" <?php checked( $count === count( $equipos ) ); ?> />
									<span><?php
										printf(
											/* translators: 1: number selected. 2: total teams. */
											esc_html__( 'Todos (%1$d/%2$d)', 'zonas-partidos-futbol' ),
											$count,
											count( $equipos )
										);
									?></span>
								</label>
								<button type="button" class="button button-small" id="zf-ll-clear"><?php esc_html_e( 'Limpiar', 'zonas-partidos-futbol' ); ?></button>
							</div>
						</div>
						<div class="zf-ll-lista zf-ll-lista-sel" id="zf-ll-seleccionados">
							<?php if ( 0 === $count ) : ?>
								<div class="zf-ll-placeholder"><?php esc_html_e( 'Arrastrá equipos aquí o hacé clic en +', 'zonas-partidos-futbol' ); ?></div>
							<?php endif; ?>
						</div>
						<div class="zf-llave-cuadro-info">
							<span class="zf-equipos-cuadro" id="zf_cuadro_info">
								<?php
								if ( $count >= 2 ) {
									$byes = $cuadro_tmp - $count;
									$texto_byes = 1 === $byes ? ' · ' . esc_html__( '1 pasa directo', 'zonas-partidos-futbol' ) : ( $byes > 1 ? ' · ' . sprintf( /* translators: %d: number of byes. */ esc_html__( '%d pasan directo', 'zonas-partidos-futbol' ), $byes ) : '' );
									printf(
										/* translators: 1: number of teams. 2: bracket size. 3: byes info. */
										esc_html__( '%1$d equipos → Cuadro de %2$d%3$s', 'zonas-partidos-futbol' ),
										$count,
										$cuadro_tmp,
										$texto_byes
									);
								} elseif ( 1 === $count ) {
									esc_html_e( '1 equipo seleccionado', 'zonas-partidos-futbol' );
								} else {
									esc_html_e( 'Ningún equipo seleccionado', 'zonas-partidos-futbol' );
								}
								?>
							</span>
						</div>
					</div>

				</div>
			</section>

			<section class="zf-seccion zf-seccion--preview">
				<header class="zf-seccion-head">
					<span class="zf-seccion-icono dashicons dashicons-slides" aria-hidden="true"></span>
					<div class="zf-seccion-textos">
						<h4><?php esc_html_e( 'Vista previa del cuadro', 'zonas-partidos-futbol' ); ?></h4>
						<p><?php esc_html_e( 'Cómo quedarían los cruces según el orden de seeding actual.', 'zonas-partidos-futbol' ); ?></p>
					</div>
				</header>
				<div class="zf-llave-preview" id="zf-ll-preview"></div>
			</section>

			<div class="zf-peligro">
				<label class="zf-peligro-fila">
					<input type="checkbox" name="zf_regenerar" id="zf_regenerar" value="1" />
					<span class="zf-peligro-texto">
						<strong><?php esc_html_e( 'Regenerar fixture', 'zonas-partidos-futbol' ); ?></strong>
						<?php esc_html_e( 'Borra todos los partidos actuales de esta llave y los vuelve a crear con los equipos seleccionados.', 'zonas-partidos-futbol' ); ?>
					</span>
				</label>
			</div>

			<?php self::panel_estado( $post ); ?>
			<p class="zf-metabox-nota"><?php esc_html_e( 'Al regenerar se crean todos los partidos del cuadro. Los resultados cargados hacen avanzar a los equipos automáticamente hasta definir al campeón.', 'zonas-partidos-futbol' ); ?></p>
		</div>
		<script>
		var ZF_LL_EQUIPOS=<?php
			$equipo_data = array();
			foreach ( $equipos as $eq ) {
				$equipo_data[] = array(
					'id'     => (int) $eq->ID,
					'nombre' => $eq->post_title,
					'avatar' => ZF_Helpers::render_avatar( $eq->ID ),
				);
			}
			echo wp_json_encode( $equipo_data );
		?>;
		var ZF_LL_SELECCIONADOS=<?php echo wp_json_encode( $equipos_seleccionados ); ?>;
		var ZF_LL_PARTIDOS=<?php
			$partidos_data = self::partidos_data_preview( $post->ID );
			echo wp_json_encode( $partidos_data );
		?>;
		</script>
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
				foreach ( $agrupados[ $r ] as $partido ) {
					echo self::panel_partido_resultado( $partido );
				}
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
	 * Mini tarjeta de partido con marcador para el panel "Fixture generado".
	 * Muestra local, resultado y visitante, como en el frontend.
	 *
	 * @param WP_Post $partido Partido.
	 * @return string
	 */
	private static function panel_partido_resultado( $partido ) {
		$d = ZF_Helpers::datos_partido( $partido );
		if ( ! $d ) {
			return '';
		}

		$finalizado = ZF_Helpers::ESTADO_FINALIZADO === $d['estado'];
		$ganador    = self::ganador_de( $partido );

		// Pie: penales si se definió por penales, o estado no finalizado.
		$pie = '';
		if ( ZF_Helpers::definido_por_penales( $d ) ) {
			$pie = sprintf(
				/* translators: 1: penales local. 2: penales visitante. */
				__( 'Penales %1$d–%2$d', 'zonas-partidos-futbol' ),
				$d['pl'],
				$d['pv']
			);
		} elseif ( ! $finalizado ) {
			$pie = ZF_Helpers::estado_label( $d['estado'] );
		}

		$marcador = $finalizado ? esc_html( $d['gl'] ) . '&ndash;' . esc_html( $d['gv'] ) : esc_html__( 'vs', 'zonas-partidos-futbol' );
		$html = '<div class="zf-pp-resultado">'
			. self::panel_pp_equipo( $d['local'], $ganador, $finalizado, $d['gl'] )
			. '<span class="zf-pp-marcador">' . $marcador . '</span>'
			. self::panel_pp_equipo( $d['visitante'], $ganador, $finalizado, $d['gv'] )
			. '<a class="zf-pp-enlace" href="' . esc_url( get_edit_post_link( $partido->ID ) ) . '" title="' . esc_attr__( 'Editar partido', 'zonas-partidos-futbol' ) . '">' . esc_html__( 'Editar', 'zonas-partidos-futbol' ) . '</a>'
			. ( $pie ? '<span class="zf-pp-pie">' . esc_html( $pie ) . '</span>' : '' )
			. '</div>';

		return $html; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML escapado pieza por pieza.
	}

	/**
	 * Render de cada lado (equipo) de la mini tarjeta de partido.
	 *
	 * @param int  $equipo_id     ID de equipo (0 = pendiente).
	 * @param int  $ganador       ID del ganador.
	 * @param bool $finalizado    Si el partido finalizó.
	 * @param int  $goles         Goles del lado.
	 * @return string
	 */
	private static function panel_pp_equipo( $equipo_id, $ganador, $finalizado, $goles ) {
		$clase = 'zf-pp-pp';
		if ( $ganador && $equipo_id === $ganador ) {
			$clase .= ' is-winner';
		} elseif ( $finalizado && $equipo_id !== $ganador ) {
			$clase .= ' is-loser';
		}

		if ( $equipo_id ) {
			$nombre = ZF_Helpers::nombre_equipo( $equipo_id );
		} else {
			$nombre = __( 'A definir', 'zonas-partidos-futbol' );
			$clase .= ' is-pendiente';
			$goles  = 0;
		}

		return '<span class="' . esc_attr( $clase ) . '">' . esc_html( $nombre ) . '</span>';
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

		// Equipos seleccionados.
		$equipos_in = isset( $_POST['zf_llave_equipos'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['zf_llave_equipos'] ) ) : array();
		$equipos    = array();
		foreach ( $equipos_in as $equipo_id ) {
			if ( $equipo_id && 'if_equipo' === get_post_type( $equipo_id ) ) {
				$equipos[] = (int) $equipo_id;
			}
		}

		update_post_meta( $post_id, '_zf_equipos_seleccionados', $equipos );

		$regenerar = ! empty( $_POST['zf_regenerar'] );
		if ( $regenerar ) {
			if ( count( $equipos ) < 2 ) {
				set_transient( 'zf_error_' . $post_id, __( 'Seleccioná al menos 2 equipos para generar el fixture.', 'zonas-partidos-futbol' ), 60 );
				return;
			}
			self::generar_fixture( $post_id, $equipos );
		}
	}

	// ========================= Generación ==================================.

	/**
	 * Calcula la potencia de 2 inmediata superior para una cantidad dada.
	 *
	 * @param int $count Cantidad de equipos.
	 * @return int Tamaño del cuadro (potencia de 2).
	 */
	public static function calcular_cuadro( $count ) {
		$size = 2;
		while ( $size < $count ) {
			$size *= 2;
		}
		return $size;
	}

	/**
	 * Genera el cuadro completo a partir de una lista manual de equipos.
	 *
	 * @param int   $llave_id  ID de la llave.
	 * @param int[] $equipos_ids IDs de los equipos (if_equipo).
	 * @return bool
	 */
	public static function generar_fixture( $llave_id, $equipos_ids ) {
		if ( count( $equipos_ids ) < 2 ) {
			set_transient( 'zf_error_' . $llave_id, __( 'Se necesitan al menos 2 equipos para armar la llave.', 'zonas-partidos-futbol' ), 60 );
			return false;
		}

		$seeds = array_map( 'intval', $equipos_ids );

		// Tamaño del cuadro: potencia de 2 inmediata superior.
		$size = self::calcular_cuadro( count( $seeds ) );

		// Relleno con byes (0) y orden estándar de siembras.
		while ( count( $seeds ) < $size ) {
			$seeds[] = 0;
		}
		$orden  = self::orden_seeds( $size );
		$cuadro = array();
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
			'equipos' => array_map( 'intval', $equipos_ids ),
			'size'    => (int) $size,
			'total'   => count( array_filter( $cuadro ) ),
			'seeds'   => array_map( 'intval', $cuadro ),
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
	 * Datos de partidos de la llave listos para el preview JS del admin.
	 * Cada partido se indexa por "ronda:slot" para que el cuadro espejado
	 * pueda pintar el marcador y el ganador reales.
	 *
	 * @param int $llave_id ID.
	 * @return array
	 */
	public static function partidos_data_preview( $llave_id ) {
		$agrupado = self::partidos_de_llave( $llave_id );
		$data     = array();
		foreach ( $agrupado as $ronda => $fila ) {
			foreach ( $fila as $slot => $partido ) {
				$d          = ZF_Helpers::datos_partido( $partido );
				$ganador    = $d ? self::ganador_de( $partido ) : 0;
				$finalizado = $d && ZF_Helpers::ESTADO_FINALIZADO === $d['estado'];
				$data[ $ronda . ':' . $slot ] = array(
					'local'     => $d ? (int) $d['local'] : 0,
					'visitante' => $d ? (int) $d['visitante'] : 0,
					'gl'        => $d ? (int) $d['gl'] : 0,
					'gv'        => $d ? (int) $d['gv'] : 0,
					'pl'        => $d ? (int) $d['pl'] : 0,
					'pv'        => $d ? (int) $d['pv'] : 0,
					'ganador'   => (int) $ganador,
					'final'     => $finalizado,
				);
			}
		}
		return $data;
	}

	/**
	 * Ganador de un partido (0 si no finalizó o empató sin penales).
	 * Un empate definido por penales tiene ganador y avanza de ronda.
	 *
	 * @param WP_Post|int $partido Partido.
	 * @return int ID de equipo o 0.
	 */
	public static function ganador_de( $partido ) {
		return ZF_Helpers::ganador_de_datos( ZF_Helpers::datos_partido( $partido ) );
	}

	/**
	 * Etiqueta de una ronda según el tamaño del cuadro.
	 *
	 * @param int $size  Tamaño total.
	 * @param int $ronda Índice desde 0.
	 * @return string
	 */
	public static function etiqueta_ronda( $size, $ronda ) {
		// Equipos que quedan DESPUÉS de la ronda: 8 equipos → Cuartos → Semis → Final.
		$restantes = max( 1, (int) $size >> ( (int) $ronda + 1 ) );
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

		echo '<div class="zf-llave-scroll"><div class="zf-llave-grid zf-llave-espejo">';

		// Lado izquierdo: primera mitad de cada ronda previa a la final.
		for ( $r = 0; $r < $rondas - 1; $r++ ) {
			$cruces = $size >> ( $r + 1 );
			$mitad  = (int) ( $cruces / 2 );
			$prev   = ( $r > 0 && isset( $agrupado[ $r - 1 ] ) ) ? $agrupado[ $r - 1 ] : array();

			echo '<div class="zf-llave-ronda zf-lado-izq">';
			echo '<h4 class="zf-llave-ronda-titulo">' . esc_html( self::etiqueta_ronda( $size, $r ) ) . '</h4>';
			for ( $j = 0; $j < $mitad; $j++ ) {
				$partido = isset( $agrupado[ $r ][ $j ] ) ? $agrupado[ $r ][ $j ] : null;
				echo self::render_partido_llave( $partido, $r, $j, $prev, $total ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapado dentro.
			}
			echo '</div>';
		}

		// Centro: la final y el campeón.
		$r_final = $rondas - 1;
		$prev_f  = isset( $agrupado[ $r_final - 1 ] ) ? $agrupado[ $r_final - 1 ] : array();

		echo '<div class="zf-llave-ronda zf-llave-centro">';
		echo '<h4 class="zf-llave-ronda-titulo zf-titulo-final">' . esc_html( self::etiqueta_ronda( $size, $r_final ) ) . '</h4>';
		$partido_final = isset( $agrupado[ $r_final ][0] ) ? $agrupado[ $r_final ][0] : null;
		echo self::render_partido_llave( $partido_final, $r_final, 0, $prev_f, $total ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapado dentro.

		if ( $campeon ) {
			echo '<div class="zf-campeon-col">';
			echo '<span class="zf-campeon-etiqueta">' . esc_html__( 'Campeón', 'zonas-partidos-futbol' ) . '</span>';
			echo '<div class="zf-campeon-card">';
			echo ZF_Helpers::icono_trofeo();
			echo ZF_Helpers::render_avatar( $campeon, 'lg' ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
			echo '<strong>' . esc_html( ZF_Helpers::nombre_equipo( $campeon ) ) . '</strong>';
			echo '</div></div>';
		}
		echo '</div>';

		// Lado derecho: segunda mitad de cada ronda, de la semifinal hacia afuera.
		for ( $r = $rondas - 2; $r >= 0; $r-- ) {
			$cruces = $size >> ( $r + 1 );
			$mitad  = (int) ( $cruces / 2 );
			$prev   = ( $r > 0 && isset( $agrupado[ $r - 1 ] ) ) ? $agrupado[ $r - 1 ] : array();

			echo '<div class="zf-llave-ronda zf-lado-der">';
			echo '<h4 class="zf-llave-ronda-titulo">' . esc_html( self::etiqueta_ronda( $size, $r ) ) . '</h4>';
			for ( $j = $mitad; $j < $cruces; $j++ ) {
				$partido = isset( $agrupado[ $r ][ $j ] ) ? $agrupado[ $r ][ $j ] : null;
				echo self::render_partido_llave( $partido, $r, $j, $prev, $total ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapado dentro.
			}
			echo '</div>';
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
		} elseif ( ZF_Helpers::definido_por_penales( $d ) ) {
			$extra = '<div class="zf-lp-pie zf-lp-pen">' . esc_html(
				sprintf(
					/* translators: 1: penales del local. 2: penales del visitante. */
					__( 'Penales %1$d–%2$d', 'zonas-partidos-futbol' ),
					$d['pl'],
					$d['pv']
				)
			) . '</div>';
		}

		return '<div class="zf-llave-partido">' . implode( '', $lados ) . $extra . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- contenido escapado arriba.
	}
}
