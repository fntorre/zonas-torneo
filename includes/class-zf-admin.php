<?php
/**
 * Administración: páginas, columnas, ajustes y aviso de dependencia.
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
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'ajustes' ) );
		add_filter( 'manage_zf_partido_posts_columns', array( __CLASS__, 'columnas_partido' ) );
		add_action( 'manage_zf_partido_posts_custom_column', array( __CLASS__, 'contenido_columna' ), 10, 2 );
		add_action( 'admin_post_zf_guardar_equipos_zonas', array( __CLASS__, 'guardar_equipos_zonas' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'css_admin' ) );
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
	 * Submenús bajo Campeonato (CPT Partido).
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . ZF_Install::CPT_PARTIDO,
			__( 'Tabla de posiciones', 'zonas-partidos-futbol' ),
			__( 'Posiciones', 'zonas-partidos-futbol' ),
			'manage_options',
			'zf-posiciones',
			array( __CLASS__, 'pagina_posiciones' )
		);
		add_submenu_page(
			'edit.php?post_type=' . ZF_Install::CPT_PARTIDO,
			__( 'Equipos por zona', 'zonas-partidos-futbol' ),
			__( 'Equipos por zona', 'zonas-partidos-futbol' ),
			'manage_options',
			'zf-equipos-zonas',
			array( __CLASS__, 'pagina_equipos' )
		);
		add_submenu_page(
			'edit.php?post_type=' . ZF_Install::CPT_PARTIDO,
			__( 'Ajustes del campeonato', 'zonas-partidos-futbol' ),
			__( 'Ajustes', 'zonas-partidos-futbol' ),
			'manage_options',
			'zf-ajustes',
			array( __CLASS__, 'pagina_ajustes' )
		);
	}

	/**
	 * Página: tabla de posiciones (vista previa).
	 */
	public static function pagina_posiciones() {
		$zonas = ZF_Helpers::zonas();
		echo '<div class="wrap zf-admin-page zf-admin-posiciones"><h1>' . esc_html__( 'Tablas de posiciones', 'zonas-partidos-futbol' ) . '</h1>';
		if ( ! $zonas ) {
			echo '<p>' . esc_html__( 'Todavía no hay zonas creadas. Crealas en Campeonato → Zonas.', 'zonas-partidos-futbol' ) . '</p></div>';
			return;
		}
		foreach ( $zonas as $zona ) {
			echo ZF_Helpers::render_tabla( $zona ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
		}
		echo '</div>';
	}

	/**
	 * Página: asignar equipos a zonas.
	 */
	public static function pagina_equipos() {
		$equipos = post_type_exists( 'if_equipo' ) ? ZF_Helpers::equipos() : array();
		$zonas   = ZF_Helpers::zonas();

		echo '<div class="wrap zf-admin-page"><h1>' . esc_html__( 'Equipos por zona', 'zonas-partidos-futbol' ) . '</h1>';

		$actualizada = isset( $_GET['zf_actualizadas'] ) ? absint( $_GET['zf_actualizadas'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $actualizada ) {
			echo '<div class="notice notice-success is-dismissible"><p>' .
				sprintf( esc_html__( '%d equipo(s) actualizados.', 'zonas-partidos-futbol' ), esc_html( $actualizada ) ) .
				'</p></div>';
		}

		if ( ! $equipos ) {
			echo '<p>' . esc_html__( 'No hay equipos inscriptos todavía.', 'zonas-partidos-futbol' ) . '</p></div>';
			return;
		}
		?>
		<p class="zf-admin-descripcion"><?php esc_html_e( 'Asigná cada equipo a su zona. Los partidos y las tablas de posiciones se agrupan por zona.', 'zonas-partidos-futbol' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="zf_guardar_equipos_zonas" />
			<?php wp_nonce_field( 'zf_equipos_zonas', 'zf_equipos_zonas_nonce' ); ?>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th style="width:40%"><?php esc_html_e( 'Equipo', 'zonas-partidos-futbol' ); ?></th>
					<th><?php esc_html_e( 'Zona', 'zonas-partidos-futbol' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $equipos as $equipo ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $equipo->post_title ); ?></strong></td>
						<td>
							<select name="zf_zona_equipo[<?php echo esc_attr( $equipo->ID ); ?>]">
								<option value=""><?php esc_html_e( '— Sin zona —', 'zonas-partidos-futbol' ); ?></option>
								<?php foreach ( $zonas as $zona ) : ?>
									<option value="<?php echo esc_attr( $zona->term_id ); ?>" <?php selected( has_term( $zona->term_id, ZF_Install::TAX_ZONA, $equipo ) ); ?>><?php echo esc_html( $zona->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( ! $zonas ) : ?>
				<p><em><?php echo wp_kses_post( __( 'No hay zonas creadas todavía. <a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=zf_zona&post_type=zf_partido' ) ) . '">Crear zonas</a>.', 'zonas-partidos-futbol' ) ); ?></em></p>
			<?php endif; ?>
			<p class="zf-admin-guardar"><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Guardar zonas', 'zonas-partidos-futbol' ); ?></button></p>
		</form>
		</div>
		<?php
	}

	/**
	 * Guarda la asignación de equipos a zonas (admin-post).
	 */
	public static function guardar_equipos_zonas() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'zonas-partidos-futbol' ) );
		}
		$nonce = isset( $_POST['zf_equipos_zonas_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['zf_equipos_zonas_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'zf_equipos_zonas' ) || ! isset( $_POST['zf_zona_equipo'] ) || ! is_array( $_POST['zf_zona_equipo'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=zf-equipos-zonas' ) );
			exit;
		}

		$total = 0;
		foreach ( wp_unslash( $_POST['zf_zona_equipo'] ) as $equipo_id => $zona_id ) {
			$equipo_id = absint( $equipo_id );
			$zona_id   = absint( $zona_id );
			if ( ! $equipo_id || 'if_equipo' !== get_post_type( $equipo_id ) ) {
				continue;
			}
			if ( $zona_id && get_term( $zona_id, ZF_Install::TAX_ZONA ) && ! is_wp_error( get_term( $zona_id, ZF_Install::TAX_ZONA ) ) ) {
				wp_set_object_terms( $equipo_id, (int) $zona_id, ZF_Install::TAX_ZONA, false );
			} else {
				wp_set_object_terms( $equipo_id, array(), ZF_Install::TAX_ZONA, false );
			}
			$total++;
		}

		wp_safe_redirect( add_query_arg( 'zf_actualizadas', $total, admin_url( 'admin.php?page=zf-equipos-zonas' ) ) );
		exit;
	}

	/**
	 * Registra los ajustes (puntos).
	 */
	public static function ajustes() {
		register_setting(
			'zf_ajustes',
			'zf_pts_ganado',
			array(
				'type'              => 'integer',
				'default'           => 3,
				'sanitize_callback' => 'absint',
			)
		);
		register_setting(
			'zf_ajustes',
			'zf_pts_empatado',
			array(
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
			)
		);
		register_setting(
			'zf_ajustes',
			'zf_pts_perdido',
			array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			)
		);
	}

	/**
	 * Página: ajustes + referencia de shortcodes.
	 */
	public static function pagina_ajustes() {
		list( $g, $e, $p ) = ZF_Helpers::puntos();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ajustes del campeonato', 'zonas-partidos-futbol' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'zf_ajustes' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Puntos por partido ganado', 'zonas-partidos-futbol' ); ?></th>
						<td><input type="number" name="zf_pts_ganado" value="<?php echo esc_attr( $g ); ?>" min="0" max="10" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Puntos por empate', 'zonas-partidos-futbol' ); ?></th>
						<td><input type="number" name="zf_pts_empatado" value="<?php echo esc_attr( $e ); ?>" min="0" max="10" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Puntos por derrota', 'zonas-partidos-futbol' ); ?></th>
						<td><input type="number" name="zf_pts_perdido" value="<?php echo esc_attr( $p ); ?>" min="0" max="10" class="small-text" /></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Shortcodes disponibles', 'zonas-partidos-futbol' ); ?></h2>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Shortcode', 'zonas-partidos-futbol' ); ?></th><th><?php esc_html_e( 'Muestra', 'zonas-partidos-futbol' ); ?></th></tr></thead>
				<tbody>
					<tr><td><code>[zf_zonas]</code></td><td><?php esc_html_e( 'Listado de todas las zonas con sus equipos.', 'zonas-partidos-futbol' ); ?></td></tr>
					<tr><td><code>[zf_tabla_posiciones]</code><br /><code>[zf_tabla_posiciones zona="zona-a"]</code></td><td><?php esc_html_e( 'Tabla(s) de posiciones. Con "zona" muestra una sola (slug o ID).', 'zonas-partidos-futbol' ); ?></td></tr>
					<tr><td><code>[zf_proximos_partidos limite="8"]</code><br /><code>[zf_proximos_partidos zona="zona-a" limite="5" futuros="1"]</code></td><td><?php esc_html_e( 'Partidos programados. "futuros=1" oculta los que ya pasaron de fecha.', 'zonas-partidos-futbol' ); ?></td></tr>
					<tr><td><code>[zf_resultados limite="8"]</code><br /><code>[zf_resultados zona="zona-a"]</code></td><td><?php esc_html_e( 'Últimos resultados cargados.', 'zonas-partidos-futbol' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<?php
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
	 * Contenido de las columnas personalizadas.
	 *
	 * @param string $column  Columna.
	 * @param int    $post_id ID.
	 */
	public static function contenido_columna( $column, $post_id ) {
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
					echo '—';
				}
				break;
			case 'zf_fecha':
				echo $d['fecha'] ? esc_html( ZF_Helpers::fecha( $d['fecha'] ) ) : '—';
				break;
			case 'zf_marcador':
				if ( ZF_Helpers::ESTADO_FINALIZADO === $d['estado'] ) {
					echo '<strong>' . esc_html( $d['gl'] . ' - ' . $d['gv'] ) . '</strong> ';
				}
				echo '<span class="zf-badge zf-badge-' . esc_attr( $d['estado'] ) . '">' . esc_html( ZF_Helpers::estado_label( $d['estado'] ) ) . '</span>';
				break;
		}
	}

	/**
	 * CSS del admin (pantallas del plugin).
	 *
	 * @param string $hook Hook actual.
	 */
	public static function css_admin( $hook ) {
		$screen     = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$page       = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$es_partido = $screen && ZF_Install::CPT_PARTIDO === $screen->post_type;
		$es_pagina  = $page && 0 === strpos( $page, 'zf-' );

		if ( ! $es_partido && ! $es_pagina ) {
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
