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
		add_filter( 'manage_zf_llave_posts_columns', array( __CLASS__, 'columnas_llave' ) );
		add_action( 'manage_zf_llave_posts_custom_column', array( __CLASS__, 'contenido_columna' ), 10, 2 );
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
		echo '<div class="wrap zf-admin-page zf-admin-posiciones">';
		echo '<div class="zf-admin-hero"><h1>' . esc_html__( 'Tablas de posiciones', 'zonas-partidos-futbol' ) . '</h1><p>' . esc_html__( 'Vista previa exacta de lo que ven tus visitantes. El orden se calcula con los resultados cargados en cada partido.', 'zonas-partidos-futbol' ) . '</p></div>';
		if ( ! $zonas ) {
			echo '<div class="zf-admin-card"><p>' . esc_html__( 'Todavía no hay zonas creadas.', 'zonas-partidos-futbol' ) . ' <a class="button button-primary" href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=zf_zona&post_type=zf_partido' ) ) . '">' . esc_html__( 'Crear la primera zona', 'zonas-partidos-futbol' ) . '</a></p></div>';
			echo '</div>';
			return;
		}
		echo '<div class="zf-tablas">';
		foreach ( $zonas as $zona ) {
			echo ZF_Helpers::render_tabla( $zona ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML ya escapado dentro.
		}
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Página: asignar equipos a zonas.
	 */
	public static function pagina_equipos() {
		$equipos = post_type_exists( 'if_equipo' ) ? ZF_Helpers::equipos() : array();
		$zonas   = ZF_Helpers::zonas();

		echo '<div class="wrap zf-admin-page"><div class="zf-admin-hero"><h1>' . esc_html__( 'Equipos por zona', 'zonas-partidos-futbol' ) . '</h1><p>' . esc_html__( 'Asigná cada equipo a su zona: los partidos y las tablas de posiciones se agrupan a partir de esta asignación.', 'zonas-partidos-futbol' ) . '</p></div>';

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
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="zf_guardar_equipos_zonas" />
			<?php wp_nonce_field( 'zf_equipos_zonas', 'zf_equipos_zonas_nonce' ); ?>
			<div class="zf-admin-card">
			<table class="wp-list-table widefat fixed striped zf-tabla-equipos">
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
			</div>
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
		<div class="wrap zf-admin-page">
			<div class="zf-admin-hero">
				<h1><?php esc_html_e( 'Ajustes del campeonato', 'zonas-partidos-futbol' ); ?></h1>
				<p><?php esc_html_e( 'Reglas de puntuación y referencia rápida de shortcodes para armar las páginas del sitio.', 'zonas-partidos-futbol' ); ?></p>
			</div>
			<div class="zf-admin-columnas">
			<form method="post" action="options.php" class="zf-admin-card zf-card-ajustes">
				<?php settings_fields( 'zf_ajustes' ); ?>
				<h2><?php esc_html_e( 'Puntaje', 'zonas-partidos-futbol' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="zf_pts_ganado"><?php esc_html_e( 'Partido ganado', 'zonas-partidos-futbol' ); ?></label></th>
						<td><input type="number" id="zf_pts_ganado" name="zf_pts_ganado" value="<?php echo esc_attr( $g ); ?>" min="0" max="10" class="small-text" /> <span class="zf-punto-ejemplo"><?php esc_html_e( 'puntos', 'zonas-partidos-futbol' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><label for="zf_pts_empatado"><?php esc_html_e( 'Empate', 'zonas-partidos-futbol' ); ?></label></th>
						<td><input type="number" id="zf_pts_empatado" name="zf_pts_empatado" value="<?php echo esc_attr( $e ); ?>" min="0" max="10" class="small-text" /> <span class="zf-punto-ejemplo"><?php esc_html_e( 'puntos', 'zonas-partidos-futbol' ); ?></span></td>
					</tr>
					<tr>
						<th scope="row"><label for="zf_pts_perdido"><?php esc_html_e( 'Derrota', 'zonas-partidos-futbol' ); ?></label></th>
						<td><input type="number" id="zf_pts_perdido" name="zf_pts_perdido" value="<?php echo esc_attr( $p ); ?>" min="0" max="10" class="small-text" /> <span class="zf-punto-ejemplo"><?php esc_html_e( 'puntos', 'zonas-partidos-futbol' ); ?></span></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<div class="zf-admin-card zf-card-shortcodes">
				<h2><?php esc_html_e( 'Shortcodes disponibles', 'zonas-partidos-futbol' ); ?></h2>
				<div class="zf-sc-lista">
					<div class="zf-sc-item">
						<code>[zf_hub]</code>
						<p><?php esc_html_e( 'Hub completo: posiciones, equipos, partidos y playoffs con navegación por pestañas.', 'zonas-partidos-futbol' ); ?></p>
					</div>
					<div class="zf-sc-item">
						<code>[zf_zonas]</code>
						<p><?php esc_html_e( 'Listado de todas las zonas con sus equipos.', 'zonas-partidos-futbol' ); ?></p>
					</div>
					<div class="zf-sc-item">
						<code>[zf_tabla_posiciones zona="zona-a"]</code>
						<p><?php esc_html_e( 'Tabla(s) de posiciones. Atributos: zona (slug o ID), clasificados (resalta los primeros N; si no se indica, usa el valor de la llave) y forma (oculta la racha con forma="0").', 'zonas-partidos-futbol' ); ?></p>
					</div>
					<div class="zf-sc-item">
						<code>[zf_proximos_partidos limite="8"]</code>
						<p><?php esc_html_e( 'Partidos programados. Con futuros="1" oculta los que ya pasaron de fecha.', 'zonas-partidos-futbol' ); ?></p>
					</div>
					<div class="zf-sc-item">
						<code>[zf_resultados limite="8"]</code>
						<p><?php esc_html_e( 'Últimos resultados cargados. Acepta zona para filtrar.', 'zonas-partidos-futbol' ); ?></p>
					</div>
					<div class="zf-sc-item">
						<code>[zf_playoffs llave=""]</code>
						<p><?php esc_html_e( 'Cuadro de eliminatorias. Sin "llave" muestra la primera publicada; el campeón se destaca al definirse.', 'zonas-partidos-futbol' ); ?></p>
					</div>
				</div>
			</div>
			</div>
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
				/* translators: %d: cantidad de equipos del cuadro. */
				__( 'Cuadro de %d', 'zonas-partidos-futbol' ),
				(int) $config['size']
			)
		) . '</span>';

		if ( $campeon ) {
			echo '<span class="zf-chip-info zf-chip-campeon">' . esc_html( ZF_Helpers::nombre_equipo( $campeon ) ) . '</span>';
		} else {
			$jugados   = 0;
			$totales   = (int) ( $config['total'] ?? 0 );
			$partidos  = ZF_Llaves::partidos_de_llave( $post_id );
			foreach ( $partidos as $grupo ) {
				foreach ( $grupo as $partido ) {
					if ( ZF_Helpers::ESTADO_FINALIZADO === get_post_meta( $partido->ID, '_zf_estado', true ) ) {
						$jugados++;
					}
				}
			}
			echo '<span class="zf-chip-info zf-chip-progreso">' . esc_html(
				sprintf(
					/* translators: 1: jugados. 2: totales. */
					__( '%1$d / %2$d jugados', 'zonas-partidos-futbol' ),
					$jugados,
					$totales
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
		$page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_type = $screen ? (string) $screen->post_type : '';
		$es_cpt    = in_array( $post_type, array( ZF_Install::CPT_PARTIDO, ZF_Install::CPT_LLAVE ), true );
		$es_tax    = $screen && 'edit-tags' === $screen->base && ZF_Install::TAX_ZONA === $screen->taxonomy;
		$es_pagina = $page && 0 === strpos( $page, 'zf-' );

		if ( ! $es_cpt && ! $es_tax && ! $es_pagina && 'toplevel_page_zf_zona' !== $hook ) {
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
