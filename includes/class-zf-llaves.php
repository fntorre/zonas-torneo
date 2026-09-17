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

	// Modo con fase clasificatoria previa a los 16avos de final:
	// 8 partidos por lado + 8 pre-clasificados por lado -> 16avos de 16 partidos.
	const MODO_CLASIFICATORIA = 'clasificatoria';
	const SIZE_CLASIFICATORIA = 64;
	const CLASI_POR_LADO      = 8;

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

		$error = get_transient( 'zf_error_' . $post->ID );
		if ( $error ) {
			delete_transient( 'zf_error_' . $post->ID );
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}

		$config = get_post_meta( $post->ID, '_zf_config', true );
		$clasi  = get_post_meta( $post->ID, '_zf_clasi', true );
		$modo   = self::modo_de_config( $config );
		$activo = self::MODO_CLASIFICATORIA === $modo
			|| ( is_array( $clasi ) && ( ! empty( $clasi['modo'] ) || ! empty( $clasi['clasif'] ) || ! empty( $clasi['directos'] ) ) );

		// El JS de admin corre en AMBOS modos (clásico y clasificatoria). Encargarlo
		// aca asegura que el filtrado de equipos por lado se aplique también en la
		// fase clasificatoria; antes solo se encolaba dentro de render_metabox_clasica.
		wp_enqueue_script(
			'zf-llave-admin',
			ZF_PLUGIN_URL . 'assets/js/llave-admin.js',
			array(),
			ZF_VERSION,
			true
		);

		echo '<div class="zf-form" id="zf-llave-admin-root">';

		self::render_toggle_clasificatoria( $activo );

		if ( $activo ) {
			self::render_metabox_clasificatoria( $post, $clasi );
		} else {
			self::render_metabox_clasica( $post );
		}

		echo '</div>';
	}

	/**
	 * Toggle que habilita la fase clasificatoria previa a los 16avos.
	 *
	 * @param bool $activo Si la fase ya está activa.
	 */
	private static function render_toggle_clasificatoria( $activo ) {
		?>
		<section class="zf-seccion zf-seccion--clasi-toggle">
			<header class="zf-seccion-head">
				<span class="zf-seccion-icono dashicons dashicons-flag" aria-hidden="true"></span>
				<div class="zf-seccion-textos">
					<h4><?php esc_html_e( 'Fase clasificatoria previa a los 16avos de final', 'zonas-partidos-futbol' ); ?></h4>
					<p><?php esc_html_e( '8 partidos por lado de la llave + 8 pre-clasificados por lado arman los 16avos de final (16 partidos).', 'zonas-partidos-futbol' ); ?></p>
				</div>
			</header>
			<div class="zf-toggle-clasi-body">
				<label class="zf-peligro-fila zf-toggle-clasi-fila">
					<input type="checkbox" name="zf_clasi_modo" id="zf_clasi_modo" value="1" <?php checked( $activo ); ?> />
					<span class="zf-peligro-texto">
						<strong class="zf-toggle-clasi-titulo"><?php esc_html_e( 'Activar fase clasificatoria', 'zonas-partidos-futbol' ); ?></strong>
						<?php esc_html_e( 'Se arman primero los 16 cruces de clasificatoria (8 por lado) y se eligen los 8 pre-clasificados de cada lado. Los ganadores de la clasificatoria se suman a los pre-clasificados en los 16avos de final.', 'zonas-partidos-futbol' ); ?>
					</span>
				</label>
				<p class="zf-toggle-clasi-nota"><?php esc_html_e( 'Para generar el fixture, completá los cruces, tildá "Regenerar fixture" y guardá.', 'zonas-partidos-futbol' ); ?></p>
			</div>
		</section>
		<?php
	}

	/**
	 * Formulario en modo clasificatoria: cruces + pre-clasificados.
	 *
	 * @param WP_Post $post  Llave.
	 * @param mixed   $clasi Meta _zf_clasi persistida.
	 */
	private static function render_metabox_clasificatoria( $post, $clasi ) {
		$clasi     = is_array( $clasi ) ? $clasi : array();
		$clasif    = isset( $clasi['clasif'] ) && is_array( $clasi['clasif'] ) ? $clasi['clasif'] : array();
		$directos  = isset( $clasi['directos'] ) && is_array( $clasi['directos'] ) ? $clasi['directos'] : array();

		$equipos = ZF_Helpers::equipos();

		if ( ! $equipos ) {
			echo '<p>' . esc_html__( 'No hay equipos inscriptos. Primero cargá equipos desde Inscripciones Fútbol.', 'zonas-partidos-futbol' ) . '</p>';
			return;
		}

		$opciones = self::opciones_equipos( $equipos );

		$lados = array(
			'izq' => __( 'Lado izquierdo', 'zonas-partidos-futbol' ),
			'der' => __( 'Lado derecho', 'zonas-partidos-futbol' ),
		);

		echo '<section class="zf-seccion zf-seccion--clasi">';
		echo '<header class="zf-seccion-head">';
		echo '<span class="zf-seccion-icono dashicons dashicons-shield-alt" aria-hidden="true"></span>';
		echo '<div class="zf-seccion-textos">';
		echo '<h4>' . esc_html__( 'Partidos de la fase clasificatoria', 'zonas-partidos-futbol' ) . '</h4>';
		echo '<p>' . esc_html__( 'Cada lado juega 8 partidos. Los ganadores son los "otros 8 equipos" del 16avos de ese lado.', 'zonas-partidos-futbol' ) . '</p>';
		echo '</div></header>';
		echo '<div class="zf-clasi-lados">';
		foreach ( $lados as $lado => $etiqueta_lado ) {
			echo self::render_lado_clasif( $lado, $etiqueta_lado, $clasif, $opciones ); // phpcs:ignore WordPress.Security.EscapeOutput -- contenido escapado pieza por pieza.
		}
		echo '</div></section>';

		echo '<section class="zf-seccion zf-seccion--preclasi">';
		echo '<header class="zf-seccion-head">';
		echo '<span class="zf-seccion-icono dashicons dashicons-awards" aria-hidden="true"></span>';
		echo '<div class="zf-seccion-textos">';
		echo '<h4>' . esc_html__( 'Pre-clasificados a los 16avos', 'zonas-partidos-futbol' ) . '</h4>';
		echo '<p>' . esc_html__( '8 equipos por lado que entran directo al 16avos y enfrentan a los ganadores de la clasificatoria del mismo lado.', 'zonas-partidos-futbol' ) . '</p>';
		echo '</div></header>';
		echo '<div class="zf-clasi-lados zf-clasi-lados--directos">';
		foreach ( $lados as $lado => $etiqueta_lado ) {
			echo self::render_lado_directos( $lado, $etiqueta_lado, $directos, $opciones ); // phpcs:ignore WordPress.Security.EscapeOutput -- contenido escapado pieza por pieza.
		}
		echo '</div></section>';

		echo '<div class="zf-peligro">';
		echo '<label class="zf-peligro-fila">';
		echo '<input type="checkbox" name="zf_regenerar" id="zf_regenerar" value="1" />';
		echo '<span class="zf-peligro-texto">';
		echo '<strong>' . esc_html__( 'Regenerar fixture', 'zonas-partidos-futbol' ) . '</strong>';
		echo esc_html__( 'Borra todos los partidos actuales de esta llave y vuelve a armar la fase clasificatoria, el 16avos y el resto del cuadro.', 'zonas-partidos-futbol' );
		echo '</span></label></div>';

		self::panel_estado( $post );
		echo '<p class="zf-metabox-nota">' . esc_html__( 'Los ganadores de la fase clasificatoria avanzan solos al 16avos contra los pre-clasificados. Los resultados se cargan desde cada partido.', 'zonas-partidos-futbol' ) . '</p>';
	}

	/**
	 * Renderiza un lado de la fase clasificatoria (8 partidos).
	 *
	 * @param string $lado          izq|der.
	 * @param string $etiqueta_lado Etiqueta del lado.
	 * @param array  $clasif        Datos persistidos de cruces.
	 * @param callable $opciones    Generador de <option>.
	 * @return string
	 */
	private static function render_lado_clasif( $lado, $etiqueta_lado, $clasif, $opciones ) {
		$partidos = isset( $clasif[ $lado ] ) ? (array) $clasif[ $lado ] : array();
		$html     = '<div class="zf-clasi-lado">';
		$html    .= '<h5 class="zf-clasi-lado-titulo">' . esc_html( $etiqueta_lado ) . '</h5>';
		for ( $i = 0; $i < self::CLASI_POR_LADO; $i++ ) {
			$m       = isset( $partidos[ $i ] ) ? (array) $partidos[ $i ] : array();
			$local   = isset( $m['local'] ) ? (int) $m['local'] : 0;
			$visita  = isset( $m['visitante'] ) ? (int) $m['visitante'] : 0;
			$html   .= '<div class="zf-clasi-partido">';
			$html   .= '<span class="zf-clasi-num">' . esc_html( $i + 1 ) . '</span>';
			$html   .= '<select name="zf_clasi_clasif[' . esc_attr( $lado ) . '][' . esc_attr( (string) $i ) . '][local]" class="zf-select-equipo" data-zf-side="' . esc_attr( $lado ) . '">'
				. call_user_func( $opciones, $local ) . '</select>'; // phpcs:ignore WordPress.Security.EscapeOutput -- opciones escapadas dentro.
			$html   .= '<span class="zf-clasi-vs">' . esc_html__( 'vs', 'zonas-partidos-futbol' ) . '</span>';
			$html   .= '<select name="zf_clasi_clasif[' . esc_attr( $lado ) . '][' . esc_attr( (string) $i ) . '][visitante]" class="zf-select-equipo" data-zf-side="' . esc_attr( $lado ) . '">'
				. call_user_func( $opciones, $visita ) . '</select>'; // phpcs:ignore WordPress.Security.EscapeOutput -- opciones escapadas dentro.
			$html   .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Renderiza un lado de los pre-clasificados (8 equipos).
	 *
	 * @param string $lado          izq|der.
	 * @param string $etiqueta_lado Etiqueta del lado.
	 * @param array  $directos      Datos persistidos de pre-clasificados.
	 * @param callable $opciones    Generador de <option>.
	 * @return string
	 */
	private static function render_lado_directos( $lado, $etiqueta_lado, $directos, $opciones ) {
		$ids  = isset( $directos[ $lado ] ) ? array_map( 'absint', (array) $directos[ $lado ] ) : array();
		$html = '<div class="zf-clasi-lado">';
		$html .= '<h5 class="zf-clasi-lado-titulo">' . esc_html( $etiqueta_lado ) . '</h5>';
		$html .= '<div class="zf-clasi-directos">';
		for ( $i = 0; $i < self::CLASI_POR_LADO; $i++ ) {
			$val = isset( $ids[ $i ] ) ? (int) $ids[ $i ] : 0;
			$html .= '<div class="zf-clasi-directo">';
			$html .= '<span class="zf-clasi-num">' . esc_html( $i + 1 ) . '</span>';
			$html   .= '<select name="zf_clasi_directos[' . esc_attr( $lado ) . '][]" class="zf-select-equipo" data-zf-side="' . esc_attr( $lado ) . '">'
				. call_user_func( $opciones, $val ) . '</select>'; // phpcs:ignore WordPress.Security.EscapeOutput -- opciones escapadas dentro.
			$html .= '</div>';
		}
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * Generador de <option> para los selects de equipos.
	 *
	 * @param WP_Post[] $equipos Equipos disponibles.
	 * @return callable
	 */
	private static function opciones_equipos( $equipos ) {
		return static function ( $seleccionado ) use ( $equipos ) {
			$html = '<option value="">' . esc_html__( '— Elegir equipo —', 'zonas-partidos-futbol' ) . '</option>';
			foreach ( $equipos as $equipo ) {
				$html .= '<option value="' . esc_attr( (string) $equipo->ID ) . '" ' . selected( (int) $seleccionado, (int) $equipo->ID, false ) . '>' . esc_html( $equipo->post_title ) . '</option>';
			}
			return $html;
		};
	}

	/**
	 * Formulario clásico (sin fase clasificatoria): drag & drop + preview.
	 *
	 * @param WP_Post $post Llave.
	 */
	private static function render_metabox_clasica( $post ) {
		$equipos_seleccionados = get_post_meta( $post->ID, '_zf_equipos_seleccionados', true );
		$equipos_seleccionados = is_array( $equipos_seleccionados ) ? array_map( 'absint', $equipos_seleccionados ) : array();

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
		$modo    = self::modo_de_config( $config );

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
		if ( self::MODO_CLASIFICATORIA === $modo ) {
			echo '<span class="zf-resumen-chip zf-chip-clasi">' . esc_html__( 'con fase clasificatoria', 'zonas-partidos-futbol' ) . '</span>';
		}
		echo '</header>';

		echo '<table class="zf-panel-rondas"><tbody>';
		$agrupados = self::partidos_de_llave( $post->ID );

		for ( $r = 0; $r < $rondas; $r++ ) {
			echo '<tr>';
			echo '<th scope="row"><span class="zf-ronda-badge zf-ronda-' . esc_attr( min( $r, 3 ) ) . '">' . esc_html( self::etiqueta_ronda( $size, $r, $modo ) ) . '</span></th>';
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

		$modo_clasi = ! empty( $_POST['zf_clasi_modo'] );
		$clasi_data = $modo_clasi ? self::leer_clasi_post( $post_id, $_POST ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se sanitiza pieza por pieza dentro.

		// En modo clásico, se limpia cualquier dato previo de clasificatoria.
		if ( ! $modo_clasi ) {
			delete_post_meta( $post_id, '_zf_clasi' );
		} else {
			update_post_meta( $post_id, '_zf_clasi', $clasi_data );
		}

		// Casilla "Regenerar fixture" (modo clásico).
		$regenerar = ! empty( $_POST['zf_regenerar'] );

		// En la fase clasificatoria, el guardado SIEMPRE (re)genera el fixture,
		// sin depender de la casilla "Regenerar": cada vez que se guarda la
		// configuración de la fase (cruces y directos) se actualizan los 16avos.
		if ( $modo_clasi ) {
			$regenerar = true;
		}

		// Sincroniza el modo en _zf_config con el toggle, para que al recargar
		// el metabox vuelva a mostrar la sección de la fase clasificatoria
		// incluso si todavía no se regeneró el fixture completo (o si falló
		// la validación de los cruces y el guardado retornó antes).
		$config         = get_post_meta( $post_id, '_zf_config', true );
		$config         = is_array( $config ) ? $config : array();
		$config['modo'] = $modo_clasi ? self::MODO_CLASIFICATORIA : '';
		update_post_meta( $post_id, '_zf_config', $config );

		if ( $regenerar ) {
			if ( $modo_clasi ) {
				foreach ( $clasi_data['errores'] as $err ) {
					set_transient( 'zf_error_' . $post_id, $err, 60 );
					return;
				}
				self::generar_fixture_clasificatoria( $post_id, $clasi_data['clasif'], $clasi_data['directos'] );
				return;
			}
			if ( count( $equipos ) < 2 ) {
				set_transient( 'zf_error_' . $post_id, __( 'Seleccioná al menos 2 equipos para generar el fixture.', 'zonas-partidos-futbol' ), 60 );
				return;
			}
			self::generar_fixture( $post_id, $equipos );
		}
	}

	/**
	 * Lee y sanitiza la fase clasificatoria del POST.
	 *
	 * @param int   $post_id ID de la llave.
	 * @param array $post    $_POST crudo.
	 * @return array{clasif: array<int, array<string,array<int,array{local:int,visitante:int}>>>, directos: array<string,array<int,int>>, errores: string[]}
	 */
	private static function leer_clasi_post( $post_id, $post ) {
		$clasif   = array( 'izq' => array(), 'der' => array() );
		$directos = array( 'izq' => array(), 'der' => array() );
		$errores  = array();

		$entrada_clasif   = ! empty( $post['zf_clasi_clasif'] ) && is_array( $post['zf_clasi_clasif'] ) ? $post['zf_clasi_clasif'] : array();
		$entrada_directos = ! empty( $post['zf_clasi_directos'] ) && is_array( $post['zf_clasi_directos'] ) ? $post['zf_clasi_directos'] : array();

		foreach ( array( 'izq', 'der' ) as $lado ) {
			for ( $i = 0; $i < self::CLASI_POR_LADO; $i++ ) {
				$raw = isset( $entrada_clasif[ $lado ][ $i ] ) ? $entrada_clasif[ $lado ][ $i ] : array();
				$clasif[ $lado ][ $i ] = array(
					'local'     => isset( $raw['local'] ) ? (int) $raw['local'] : 0,
					'visitante' => isset( $raw['visitante'] ) ? (int) $raw['visitante'] : 0,
				);
			}
			$directos[ $lado ] = array();
			if ( isset( $entrada_directos[ $lado ] ) && is_array( $entrada_directos[ $lado ] ) ) {
				foreach ( array_slice( $entrada_directos[ $lado ], 0, self::CLASI_POR_LADO ) as $equipo_id ) {
					$directos[ $lado ][] = (int) $equipo_id;
				}
			}
		}

		$usados = array();
		foreach ( $clasif as $lado => $partidos ) {
			foreach ( $partidos as $i => $m ) {
				$local  = (int) $m['local'];
				$visita = (int) $m['visitante'];
				if ( ! $local || ! $visita ) {
					$errores[] = __( 'Completá los dos equipos de todos los partidos de la fase clasificatoria.', 'zonas-partidos-futbol' );
					return array( 'clasif' => $clasif, 'directos' => $directos, 'errores' => $errores );
				}
				if ( $local === $visita ) {
					$errores[] = sprintf(
						/* translators: %s: lado de la llave. */
						__( 'En un partido de la fase clasificatoria (lado %s) no pueden jugar dos veces el mismo equipo.', 'zonas-partidos-futbol' ),
						'izq' === $lado ? __( 'izquierdo', 'zonas-partidos-futbol' ) : __( 'derecho', 'zonas-partidos-futbol' )
					);
					return array( 'clasif' => $clasif, 'directos' => $directos, 'errores' => $errores );
				}
				foreach ( array( $local, $visita ) as $equipo_id ) {
					if ( ! isset( $usados[ $equipo_id ] ) ) {
						$usados[ $equipo_id ] = array( 'campo' => sprintf( 'clasificatoria %s · partido %d', $lado, $i + 1 ) );
					}
				}
			}
		}

		foreach ( $directos as $lado => $ids ) {
			for ( $i = 0; $i < self::CLASI_POR_LADO; $i++ ) {
				$equipo_id = isset( $ids[ $i ] ) ? (int) $ids[ $i ] : 0;
				if ( ! $equipo_id ) {
					$errores[] = __( 'Elegí los 8 pre-clasificados de cada lado de la llave.', 'zonas-partidos-futbol' );
					return array( 'clasif' => $clasif, 'directos' => $directos, 'errores' => $errores );
				}
				if ( isset( $usados[ $equipo_id ] ) ) {
					$errores[] = sprintf(
						/* translators: %1$s: nombre del equipo. %2$s: primera aparición. */
						__( 'El equipo %1$s está repetido (ya figura en: %2$s). Cada equipo solo puede participar una vez.', 'zonas-partidos-futbol' ),
						ZF_Helpers::nombre_equipo( $equipo_id ),
						$usados[ $equipo_id ]['campo']
					);
				}
				$usados[ $equipo_id ] = array( 'campo' => __( 'pre-clasificados', 'zonas-partidos-futbol' ) );
			}
		}

		return array( 'clasif' => $clasif, 'directos' => $directos, 'errores' => $errores );
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
	 * Genera el cuadro en modo clasificatoria (cuadro de 64):
	 *  - Ronda 0 "Fase clasificatoria": 16 partidos reales (8 por lado).
	 *  - Byes en el resto de los cruces de la ronda 0 (pre-clasificados).
	 *  - Ronda 1 en adelante: 16avos (16), Octavos (8), Cuartos (4), Semis (2), Final (1).
	 *
	 * Cada 16avos enfrenta a un ganador de la clasificatoria con un pre-clasificado
	 * del mismo lado; en el lado derecho el orden de parejas está invertido para
	 * que las mitades queden espejadas en el render.
	 *
	 * @param int   $llave_id ID de la llave.
	 * @param array $clasif   ['izq'=>[[local,visitante],...8], 'der'=>[...]].
	 * @param array $directos ['izq'=>[8 ids], 'der'=>[8 ids]].
	 * @return bool
	 */
	public static function generar_fixture_clasificatoria( $llave_id, $clasif, $directos ) {
		$size = self::SIZE_CLASIFICATORIA;
		$l    = self::CLASI_POR_LADO;

		// Normalizar entradas.
		$lados = array( 'izq', 'der' );
		$pairs = array();
		foreach ( $lados as $lado ) {
			$partidos = isset( $clasif[ $lado ] ) ? (array) $clasif[ $lado ] : array();
			for ( $i = 0; $i < $l; $i++ ) {
				$m    = isset( $partidos[ $i ] ) ? (array) $partidos[ $i ] : array();
				$pairs[ $lado ][ $i ] = array(
					(int) ( $m['local'] ?? 0 ),
					(int) ( $m['visitante'] ?? 0 ),
				);
			}
			$arr = isset( $directos[ $lado ] ) ? array_map( 'absint', (array) $directos[ $lado ] ) : array();
			for ( $i = 0; $i < $l; $i++ ) {
				$seeds_directos[ $lado ][ $i ] = isset( $arr[ $i ] ) ? (int) $arr[ $i ] : 0;
			}
		}

		// Construcción de seeds: cada lado = 4 seeds por cruce de 16avos.
		$cuadro = array_fill( 0, $size, 0 );
		for ( $j = 0; $j < $l; $j++ ) {
			// Izquierda: clasificación => [local, visitante, directo, 0].
			$cuadro[ 4 * $j ]     = $pairs['izq'][ $j ][0];
			$cuadro[ 4 * $j + 1 ] = $pairs['izq'][ $j ][1];
			$cuadro[ 4 * $j + 2 ] = $seeds_directos['izq'][ $j ];
			$cuadro[ 4 * $j + 3 ] = 0;

			// Derecha: [directo, 0, local, visitante] para espejar las mitades.
			$base = $size / 2 + 4 * $j;
			$cuadro[ $base ]     = $seeds_directos['der'][ $j ];
			$cuadro[ $base + 1 ] = 0;
			$cuadro[ $base + 2 ] = $pairs['der'][ $j ][0];
			$cuadro[ $base + 3 ] = $pairs['der'][ $j ][1];
		}

		// Etiquetas y modo.
		$modo = self::MODO_CLASIFICATORIA;

		// Borrar partidos anteriores.
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
			'equipos' => array(),
			'size'    => (int) $size,
			'total'   => $l * 6,
			'seeds'   => array_map( 'intval', $cuadro ),
			'modo'    => $modo,
		) );

		update_post_meta( $llave_id, '_zf_clasi', array(
			'modo'     => $modo,
			'clasif'   => $clasif,
			'directos' => $directos,
		) );

		$num          = 0;
		$rondas_total = (int) round( log( $size, 2 ) );

		for ( $r = 0; $r < $rondas_total; $r++ ) {
			$cruces   = $size >> ( $r + 1 );
			$etiqueta = self::etiqueta_ronda( $size, $r, $modo );

			for ( $m = 0; $m < $cruces; $m++ ) {
				if ( 0 === $r ) {
					// Ronda 0: solo se crean los 16 cruces reales de clasificatoria;
					// los 16 cruces restantes son byes (pre-clasificados).
					$a = $cuadro[ 2 * $m ];
					$b = $cuadro[ 2 * $m + 1 ];
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
	 * Modo de la llave a partir de su configuración.
	 *
	 * @param mixed $config Meta _zf_config.
	 * @return string ''|'clasificatoria'
	 */
	public static function modo_de_config( $config ) {
		if ( is_array( $config ) && ! empty( $config['modo'] ) ) {
			return (string) $config['modo'];
		}
		return '';
	}

	/**
	 * Etiqueta de una ronda según el tamaño del cuadro.
	 * Con modo "clasificatoria", la ronda 0 es la fase previa a los 16avos
	 * y el resto conserva las etiquetas de un cuadro de 32 (Dieciseisavos en ronda 1).
	 *
	 * @param int    $size  Tamaño total.
	 * @param int    $ronda Índice desde 0.
	 * @param string $modo  Modo de la llave (''|'clasificatoria').
	 * @return string
	 */
	public static function etiqueta_ronda( $size, $ronda, $modo = '' ) {
		if ( self::MODO_CLASIFICATORIA === $modo && 0 === (int) $ronda ) {
			return __( 'Fase clasificatoria', 'zonas-partidos-futbol' );
		}
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
		$config     = get_post_meta( $llave->ID, '_zf_config', true );
		$hay_fixture = is_array( $config ) && ! empty( $config['size'] );
		ob_start();

		echo '<section class="zf-tabla-wrap zf-llave-wrap">';
		echo '<header class="zf-tabla-head">';
		echo '<h3 class="zf-tabla-titulo">' . ZF_Helpers::icono_trofeo() . esc_html( $llave->post_title ) . '</h3>';
		echo '<div class="zf-tabla-acciones">';
		if ( $hay_fixture ) {
			echo self::boton_pantalla_completa(); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML escapado pieza por pieza.
		}
		echo '<span class="zf-tabla-sub">' . esc_html__( 'Eliminatorias', 'zonas-partidos-futbol' ) . '</span>';
		echo '</div></header>';

		if ( ! is_array( $config ) || empty( $config['size'] ) ) {
			echo '<p class="zf-vacio">' . esc_html__( 'Esta llave todavía no tiene fixture generado.', 'zonas-partidos-futbol' ) . '</p>';
			echo '</section>';
			return ob_get_clean();
		}

		$size     = (int) $config['size'];
		$total    = (int) ( $config['total'] ?? 0 );
		$rondas   = (int) round( log( $size, 2 ) );
		$modo     = self::modo_de_config( $config );
		$agrupado = self::partidos_de_llave( $llave->ID );
		$campeon  = (int) get_post_meta( $llave->ID, '_zf_campeon', true );

		echo '<div class="zf-llave-scroll"><div class="zf-llave-fit"><div class="zf-llave-grid zf-llave-espejo">';

		// Lado izquierdo: primera mitad de cada ronda previa a la final.
		for ( $r = 0; $r < $rondas - 1; $r++ ) {
			$cruces = $size >> ( $r + 1 );
			$mitad  = (int) ( $cruces / 2 );
			$prev   = ( $r > 0 && isset( $agrupado[ $r - 1 ] ) ) ? $agrupado[ $r - 1 ] : array();
			$slots  = self::slots_ronda( $modo, $r, $agrupado, 0, $mitad );

			echo '<div class="zf-llave-ronda zf-lado-izq">';
			echo '<h4 class="zf-llave-ronda-titulo">' . esc_html( self::etiqueta_ronda( $size, $r, $modo ) ) . '</h4>';
			foreach ( $slots as $j ) {
				$partido = isset( $agrupado[ $r ][ $j ] ) ? $agrupado[ $r ][ $j ] : null;
				echo self::render_partido_llave( $partido, $r, $j, $prev, $total ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapado dentro.
			}
			echo '</div>';
		}

		// Centro: la final y el campeón.
		$r_final = $rondas - 1;
		$prev_f  = isset( $agrupado[ $r_final - 1 ] ) ? $agrupado[ $r_final - 1 ] : array();

		echo '<div class="zf-llave-ronda zf-llave-centro">';
		echo '<h4 class="zf-llave-ronda-titulo zf-titulo-final">' . esc_html( self::etiqueta_ronda( $size, $r_final, $modo ) ) . '</h4>';
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
			$slots  = self::slots_ronda( $modo, $r, $agrupado, $mitad, $cruces );

			echo '<div class="zf-llave-ronda zf-lado-der">';
			echo '<h4 class="zf-llave-ronda-titulo">' . esc_html( self::etiqueta_ronda( $size, $r, $modo ) ) . '</h4>';
			foreach ( $slots as $j ) {
				$partido = isset( $agrupado[ $r ][ $j ] ) ? $agrupado[ $r ][ $j ] : null;
				echo self::render_partido_llave( $partido, $r, $j, $prev, $total ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapado dentro.
			}
			echo '</div>';
		}

		echo '</div></div></div>';
		echo self::controles_pantalla_completa(); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML escapado pieza por pieza.
		echo '</section>';
		return ob_get_clean();
	}

	/**
	 * Controles de zoom de la vista de pantalla completa.
	 *
	 * @return string
	 */
	private static function controles_pantalla_completa() {
		return '<div class="zf-fs-controls">'
			. '<div class="zf-fs-panel">'
			. '<button type="button" class="zf-fs-zoom" data-zf-zoom="out" aria-label="' . esc_attr__( 'Alejar', 'zonas-partidos-futbol' ) . '">−</button>'
			. '<span class="zf-fs-nivel">100%</span>'
			. '<button type="button" class="zf-fs-zoom" data-zf-zoom="in" aria-label="' . esc_attr__( 'Acercar', 'zonas-partidos-futbol' ) . '">+</button>'
			. '<button type="button" class="zf-fs-restaurar" data-zf-zoom="fit">' . esc_html__( 'Ajustar', 'zonas-partidos-futbol' ) . '</button>'
			. '</div>'
			. '<p class="zf-fs-pista">' . esc_html__( 'Rueda: zoom · Ctrl+Rueda: zoom fuera de pantalla completa · Barras: mover', 'zonas-partidos-futbol' ) . '</p>'
			. '</div>';
	}

	/**
	 * Botón que alterna la vista de pantalla completa del cuadro.
	 *
	 * @return string
	 */
	private static function boton_pantalla_completa() {
		return '<button type="button" class="zf-fs-btn" aria-label="' . esc_attr__( 'Ver en pantalla completa', 'zonas-partidos-futbol' ) . '" title="' . esc_attr__( 'Pantalla completa', 'zonas-partidos-futbol' ) . '">'
			. '<svg class="zf-fs-icono zf-fs-entrar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>'
			. '<svg class="zf-fs-icono zf-fs-salir" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M8 3v3a2 2 0 0 1-2 2H3"/><path d="M21 8h-3a2 2 0 0 1-2-2V3"/><path d="M3 16h3a2 2 0 0 1 2 2v3"/><path d="M16 21v-3a2 2 0 0 1 2-2h3"/></svg>'
			. '<span class="zf-fs-texto zf-fs-entrar">' . esc_html__( 'Pantalla completa', 'zonas-partidos-futbol' ) . '</span>'
			. '<span class="zf-fs-texto zf-fs-salir">' . esc_html__( 'Salir', 'zonas-partidos-futbol' ) . '</span>'
			. '</button>';
	}

	/**
	 * Slots de una ronda a renderizar en el cuadro. En modo clasificatoria la
	 * ronda 0 solo muestra los cruces reales (los 8 por lado); el resto de las
	 * rondas conserva el recorrido continuo clásico.
	 *
	 * @param string $modo     Modo de la llave.
	 * @param int    $r        Ronda.
	 * @param array  $agrupado Partidos agrupados por ronda/slot.
	 * @param int    $desde    Índice inicial.
	 * @param int    $hasta    Índice final (sin incluir).
	 * @return int[]
	 */
	private static function slots_ronda( $modo, $r, $agrupado, $desde, $hasta ) {
		if ( self::MODO_CLASIFICATORIA === $modo && 0 === $r ) {
			$slots = array();
			if ( ! empty( $agrupado[0] ) ) {
				foreach ( array_keys( $agrupado[0] ) as $j ) {
					if ( $j >= $desde && $j < $hasta ) {
						$slots[] = (int) $j;
					}
				}
			}
			return $slots;
		}
		return range( $desde, $hasta - 1 );
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
