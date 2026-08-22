<?php
/**
 * Registro de tipos de contenido y taxonomías.
 *
 * @package ZonasFutbol
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clase ZF_Install
 */
class ZF_Install {

	const CPT_PARTIDO = 'zf_partido';
	const TAX_ZONA    = 'zf_zona';

	/**
	 * Hooks.
	 */
	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
	}

	/**
	 * Registra CPT y taxonomía.
	 */
	public static function register() {
		self::register_taxonomia();
		self::register_partido();
	}

	/**
	 * Tipos de contenido a los que se adjunta la taxonomía Zona.
	 *
	 * @return array
	 */
	private static function objetos_zona() {
		$objetos = array( self::CPT_PARTIDO );
		if ( post_type_exists( 'if_equipo' ) ) {
			$objetos[] = 'if_equipo';
		}
		return $objetos;
	}

	/**
	 * Taxonomía Zona: agrupa equipos y partidos.
	 */
	private static function register_taxonomia() {
		$labels = array(
			'name'          => __( 'Zonas', 'zonas-partidos-futbol' ),
			'singular_name' => __( 'Zona', 'zonas-partidos-futbol' ),
			'add_new_item'  => __( 'Agregar zona nueva', 'zonas-partidos-futbol' ),
			'edit_item'     => __( 'Editar zona', 'zonas-partidos-futbol' ),
			'menu_name'     => __( 'Zonas', 'zonas-partidos-futbol' ),
			'search_items'  => __( 'Buscar zonas', 'zonas-partidos-futbol' ),
		);
		register_taxonomy(
			self::TAX_ZONA,
			self::objetos_zona(),
			array(
				'labels'            => $labels,
				'hierarchical'      => false,
				'public'            => true,
				'show_ui'           => true,
				'show_in_menu'      => 'edit.php?post_type=' . self::CPT_PARTIDO,
				'show_in_rest'      => false,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'zona' ),
			)
		);
	}

	/**
	 * CPT Partido.
	 */
	private static function register_partido() {
		$labels = array(
			'name'          => __( 'Partidos', 'zonas-partidos-futbol' ),
			'singular_name' => __( 'Partido', 'zonas-partidos-futbol' ),
			'menu_name'     => __( 'Campeonato', 'zonas-partidos-futbol' ),
			'add_new'       => __( 'Nuevo partido', 'zonas-partidos-futbol' ),
			'add_new_item'  => __( 'Agregar partido nuevo', 'zonas-partidos-futbol' ),
			'edit_item'     => __( 'Editar partido', 'zonas-partidos-futbol' ),
			'search_items'  => __( 'Buscar partidos', 'zonas-partidos-futbol' ),
			'not_found'     => __( 'No hay partidos cargados todavía.', 'zonas-partidos-futbol' ),
		);
		register_post_type(
			self::CPT_PARTIDO,
			array(
				'labels'          => $labels,
				'public'          => true,
				'publicly_queryable' => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_position'   => 27,
				'menu_icon'       => 'dashicons-calendar-alt',
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'rewrite'         => false,
			)
		);
	}
}
