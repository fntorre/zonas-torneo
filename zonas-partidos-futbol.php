<?php
/**
 * Plugin Name: Zonas y Partidos de Fútbol
 * Description: Agrupa los equipos de "Inscripciones Fútbol" en zonas, gestiona el fixture (partidos con local, visitante, lugar y horario), carga resultados y muestra tablas de posiciones en el frontend mediante shortcodes.
 * Version:     1.6.1
 * Author:      Nicolás Torre
 * License:     GPL-2.0-or-later
 * Text Domain: zonas-partidos-futbol
 *
 * @package ZonasFutbol
 */

defined( 'ABSPATH' ) || exit;

define( 'ZF_VERSION', '1.6.1' );
define( 'ZF_PLUGIN_FILE', __FILE__ );
define( 'ZF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ZF_PLUGIN_DIR . 'includes/class-zf-install.php';
require_once ZF_PLUGIN_DIR . 'includes/class-zf-helpers.php';
require_once ZF_PLUGIN_DIR . 'includes/class-zf-admin.php';
require_once ZF_PLUGIN_DIR . 'includes/class-zf-metaboxes.php';
require_once ZF_PLUGIN_DIR . 'includes/class-zf-shortcodes.php';
require_once ZF_PLUGIN_DIR . 'includes/class-zf-hub.php';
require_once ZF_PLUGIN_DIR . 'includes/class-zf-llaves.php';

/**
 * Arranque del plugin.
 */
function zf_init() {
	ZF_Install::hooks();
	ZF_Admin::hooks();
	ZF_Metaboxes::hooks();
	ZF_Shortcodes::hooks();
	ZF_Hub::hooks();
	ZF_Llaves::hooks();

	add_action( 'admin_notices', array( 'ZF_Admin', 'aviso_dependencia' ) );
}
add_action( 'plugins_loaded', 'zf_init' );

/**
 * Activación: registrar CPT/taxonomía y refrescar rewrites.
 */
function zf_activar() {
	ZF_Install::register();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'zf_activar' );

/**
 * Desactivación.
 */
function zf_desactivar() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'zf_desactivar' );
