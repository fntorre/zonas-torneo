<?php
/**
 * Metabox del partido: zona, equipos, lugar, horario y resultado.
 *
 * @package ZonasFutbol
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase ZF_Metaboxes
 */
class ZF_Metaboxes {

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'registrar' ) );
		add_action( 'save_post_' . ZF_Install::CPT_PARTIDO, array( __CLASS__, 'guardar' ), 10, 2 );
	}

	/**
	 * Registra el metabox.
	 */
	public static function registrar() {
		add_meta_box(
			'zf_datos_partido',
			__( 'Datos del partido', 'zonas-partidos-futbol' ),
			array( __CLASS__, 'render' ),
			ZF_Install::CPT_PARTIDO,
			'normal',
			'high'
		);
	}

	/**
	 * Renderiza el formulario.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render( $post ) {
		wp_nonce_field( 'zf_partido_meta', 'zf_partido_nonce' );

		$d = ZF_Helpers::datos_partido( $post );

		$local_id     = $d ? $d['local'] : 0;
		$visitante_id = $d ? $d['visitante'] : 0;
		$lugar        = $d ? $d['lugar'] : '';
		$jornada      = $d ? $d['jornada'] : '';
		$estado       = $d && $d['estado'] ? $d['estado'] : ZF_Helpers::ESTADO_PROGRAMADO;
		$gl           = $d ? $d['gl'] : 0;
		$gv           = $d ? $d['gv'] : 0;

		// Fecha en formato datetime-local (horario del sitio).
		$fecha_input = '';
		if ( $d && $d['fecha'] ) {
			$fecha_input = get_date_from_gmt( $d['fecha'], 'Y-m-d\TH:i' );
		}

		$error = get_transient( 'zf_error_' . $post->ID );
		if ( $error ) {
			delete_transient( 'zf_error_' . $post->ID );
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}

		$equipos = post_type_exists( 'if_equipo' ) ? ZF_Helpers::equipos() : array();
		$zonas   = ZF_Helpers::zonas();

		if ( ! $equipos ) {
			echo '<p>' . esc_html__( 'No hay equipos inscriptos todavía. Cargá equipos desde el menú Inscripciones.', 'zonas-partidos-futbol' ) . '</p>';
			return;
		}
		?>
		<div class="zf-form">

			<section class="zf-seccion zf-seccion--cruce">
				<header class="zf-seccion-head">
					<span class="zf-seccion-icono dashicons dashicons-shield" aria-hidden="true"></span>
					<div class="zf-seccion-textos">
						<h4><?php esc_html_e( 'Cruce', 'zonas-partidos-futbol' ); ?></h4>
						<p><?php esc_html_e( 'Zona del torneo y equipos que se enfrentan.', 'zonas-partidos-futbol' ); ?></p>
					</div>
				</header>
				<div class="zf-seccion-grid">
					<p class="zf-campo zf-campo--completo">
						<label for="zf_zona"><?php esc_html_e( 'Zona', 'zonas-partidos-futbol' ); ?></label>
						<select name="zf_zona" id="zf_zona">
							<option value=""><?php esc_html_e( '— Sin zona —', 'zonas-partidos-futbol' ); ?></option>
							<?php foreach ( $zonas as $zona ) : ?>
								<option value="<?php echo esc_attr( $zona->term_id ); ?>" <?php selected( has_term( $zona->term_id, ZF_Install::TAX_ZONA, $post ) ); ?>><?php echo esc_html( $zona->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( ! $zonas ) : ?>
							<span class="zf-campo-aviso"><?php esc_html_e( 'No hay zonas creadas: crealas en Campeonato → Zonas.', 'zonas-partidos-futbol' ); ?></span>
						<?php endif; ?>
					</p>
					<p class="zf-campo">
						<label for="zf_local"><?php esc_html_e( 'Equipo local', 'zonas-partidos-futbol' ); ?> *</label>
						<select name="zf_local" id="zf_local" required>
							<option value=""><?php esc_html_e( '— Elegir equipo —', 'zonas-partidos-futbol' ); ?></option>
							<?php foreach ( $equipos as $equipo ) : ?>
								<option value="<?php echo esc_attr( $equipo->ID ); ?>" <?php selected( $local_id, $equipo->ID ); ?>><?php echo esc_html( $equipo->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p class="zf-campo">
						<label for="zf_visitante"><?php esc_html_e( 'Equipo visitante', 'zonas-partidos-futbol' ); ?> *</label>
						<select name="zf_visitante" id="zf_visitante" required>
							<option value=""><?php esc_html_e( '— Elegir equipo —', 'zonas-partidos-futbol' ); ?></option>
							<?php foreach ( $equipos as $equipo ) : ?>
								<option value="<?php echo esc_attr( $equipo->ID ); ?>" <?php selected( $visitante_id, $equipo->ID ); ?>><?php echo esc_html( $equipo->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				</div>
			</section>
			<section class="zf-seccion zf-seccion--agenda">
				<header class="zf-seccion-head">
					<span class="zf-seccion-icono dashicons dashicons-clock" aria-hidden="true"></span>
					<div class="zf-seccion-textos">
						<h4><?php esc_html_e( 'Agenda', 'zonas-partidos-futbol' ); ?></h4>
						<p><?php esc_html_e( 'Cuándo y dónde se juega. La fecha ordena las listas del sitio.', 'zonas-partidos-futbol' ); ?></p>
					</div>
				</header>
				<div class="zf-seccion-grid">
					<p class="zf-campo">
						<label for="zf_fecha"><?php esc_html_e( 'Fecha y hora', 'zonas-partidos-futbol' ); ?></label>
						<input type="datetime-local" name="zf_fecha" id="zf_fecha" value="<?php echo esc_attr( $fecha_input ); ?>" />
					</p>
					<p class="zf-campo">
						<label for="zf_lugar"><?php esc_html_e( 'Lugar / cancha', 'zonas-partidos-futbol' ); ?></label>
						<input type="text" name="zf_lugar" id="zf_lugar" value="<?php echo esc_attr( $lugar ); ?>" />
					</p>
					<p class="zf-campo">
						<label for="zf_jornada"><?php esc_html_e( 'Jornada / fecha (número o nombre)', 'zonas-partidos-futbol' ); ?></label>
						<input type="text" name="zf_jornada" id="zf_jornada" value="<?php echo esc_attr( $jornada ); ?>" placeholder="1, 2, 3…" />
					</p>
				</div>
			</section>
			<section class="zf-seccion zf-seccion--resultado">
				<header class="zf-seccion-head">
					<span class="zf-seccion-icono dashicons dashicons-awards" aria-hidden="true"></span>
					<div class="zf-seccion-textos">
						<h4><?php esc_html_e( 'Resultado', 'zonas-partidos-futbol' ); ?></h4>
						<p><?php esc_html_e( 'Marcá el estado y, si finalizó, cargá los goles.', 'zonas-partidos-futbol' ); ?></p>
					</div>
				</header>
				<div class="zf-seccion-grid">
					<p class="zf-campo zf-campo--completo">
						<label for="zf_estado"><?php esc_html_e( 'Estado del partido', 'zonas-partidos-futbol' ); ?></label>
						<select name="zf_estado" id="zf_estado" class="zf-select-estado">
							<?php foreach ( ZF_Helpers::estados() as $clave => $etiqueta ) : ?>
								<option value="<?php echo esc_attr( $clave ); ?>" <?php selected( $estado, $clave ); ?>><?php echo esc_html( $etiqueta ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<div class="zf-goles zf-campo--completo">
						<p class="zf-campo">
							<label for="zf_goles_local"><?php esc_html_e( 'Goles local', 'zonas-partidos-futbol' ); ?></label>
							<input type="number" class="zf-input-gol" name="zf_goles_local" id="zf_goles_local" min="0" max="99" value="<?php echo esc_attr( $gl ); ?>" />
						</p>
						<span class="zf-goles-sep" aria-hidden="true">&ndash;</span>
						<p class="zf-campo">
							<label for="zf_goles_visitante"><?php esc_html_e( 'Goles visitante', 'zonas-partidos-futbol' ); ?></label>
							<input type="number" class="zf-input-gol" name="zf_goles_visitante" id="zf_goles_visitante" min="0" max="99" value="<?php echo esc_attr( $gv ); ?>" />
						</p>
					</div>
				</div>
			</section>

			<p class="zf-metabox-nota"><?php esc_html_e( 'Para cargar un resultado: marcá el estado "Finalizado" y completá los goles. En partidos de una llave, el ganador avanza automáticamente al cruce siguiente.', 'zonas-partidos-futbol' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Guarda los datos del partido.
	 *
	 * @param int     $post_id ID.
	 * @param WP_Post $post    Post.
	 */
	public static function guardar( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		$nonce = isset( $_POST['zf_partido_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['zf_partido_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'zf_partido_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$local_id     = isset( $_POST['zf_local'] ) ? absint( $_POST['zf_local'] ) : 0;
		$visitante_id = isset( $_POST['zf_visitante'] ) ? absint( $_POST['zf_visitante'] ) : 0;
		$lugar        = isset( $_POST['zf_lugar'] ) ? sanitize_text_field( wp_unslash( $_POST['zf_lugar'] ) ) : '';
		$jornada      = isset( $_POST['zf_jornada'] ) ? sanitize_text_field( wp_unslash( $_POST['zf_jornada'] ) ) : '';
		$estado       = isset( $_POST['zf_estado'] ) ? sanitize_key( wp_unslash( $_POST['zf_estado'] ) ) : ZF_Helpers::ESTADO_PROGRAMADO;
		$gl           = isset( $_POST['zf_goles_local'] ) ? absint( $_POST['zf_goles_local'] ) : 0;
		$gv           = isset( $_POST['zf_goles_visitante'] ) ? absint( $_POST['zf_goles_visitante'] ) : 0;

		if ( ! in_array( $estado, array_keys( ZF_Helpers::estados() ), true ) ) {
			$estado = ZF_Helpers::ESTADO_PROGRAMADO;
		}

		// Validaciones.
		if ( ! $local_id || ! $visitante_id || 'if_equipo' !== get_post_type( $local_id ) || 'if_equipo' !== get_post_type( $visitante_id ) ) {
			set_transient( 'zf_error_' . $post_id, __( 'Elegí el equipo local y el visitante.', 'zonas-partidos-futbol' ), 60 );
			return;
		}
		if ( $local_id === $visitante_id ) {
			set_transient( 'zf_error_' . $post_id, __( 'El local y el visitante no pueden ser el mismo equipo.', 'zonas-partidos-futbol' ), 60 );
			return;
		}

		update_post_meta( $post_id, '_zf_local', $local_id );
		update_post_meta( $post_id, '_zf_visitante', $visitante_id );
		update_post_meta( $post_id, '_zf_lugar', $lugar );
		update_post_meta( $post_id, '_zf_jornada', $jornada );
		update_post_meta( $post_id, '_zf_estado', $estado );
		update_post_meta( $post_id, '_zf_goles_local', $gl );
		update_post_meta( $post_id, '_zf_goles_visitante', $gv );

		// Fecha/hora: se guarda en GMT para ordenar y mostrar correctamente.
		$fecha_input = isset( $_POST['zf_fecha'] ) ? sanitize_text_field( wp_unslash( $_POST['zf_fecha'] ) ) : '';
		$fecha_local = str_replace( 'T', ' ', $fecha_input );
		if ( $fecha_local && date_create_from_format( 'Y-m-d H:i', $fecha_local, wp_timezone() ) instanceof DateTime ) {
			update_post_meta( $post_id, '_zf_fecha', get_gmt_from_date( $fecha_local . ':00' ) );
		} else {
			update_post_meta( $post_id, '_zf_fecha', '' );
		}

		// Zona: sincroniza el término.
		$zona_id = isset( $_POST['zf_zona'] ) ? absint( $_POST['zf_zona'] ) : 0;
		if ( $zona_id && get_term( $zona_id, ZF_Install::TAX_ZONA ) && ! is_wp_error( get_term( $zona_id, ZF_Install::TAX_ZONA ) ) ) {
			wp_set_object_terms( $post_id, (int) $zona_id, ZF_Install::TAX_ZONA, false );
		} else {
			wp_set_object_terms( $post_id, array(), ZF_Install::TAX_ZONA, false );
		}

		// Título automático "Local vs Visitante".
		$titulo = ZF_Helpers::nombre_equipo( $local_id ) . ' vs ' . ZF_Helpers::nombre_equipo( $visitante_id );
		if ( $titulo !== $post->post_title ) {
			remove_action( 'save_post_' . ZF_Install::CPT_PARTIDO, array( __CLASS__, 'guardar' ), 10 );
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => $titulo,
				)
			);
			add_action( 'save_post_' . ZF_Install::CPT_PARTIDO, array( __CLASS__, 'guardar' ), 10, 2 );
		}
	}
}
