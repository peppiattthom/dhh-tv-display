<?php
/**
 * Plugin Name:       DHH TV Display
 * Plugin URI:        https://www.dhhpanelproducts.co.uk/
 * Description:       Self-contained reception TV kiosk display for DHH Panel Products. Provides the REST API and the full-screen slideshow at /tv-display/.
 * Version:           1.6.7
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            NP Consulting Group
 * Author URI:        https://npc-group.co.uk
 * License:           GPL-2.0-or-later
 * Text Domain:       dhh-tv-display
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'DHH_DISPLAY_VERSION', '1.6.7' );
define( 'DHH_DISPLAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'DHH_DISPLAY_URL', plugin_dir_url( __FILE__ ) );

$dhh_puc_file = DHH_DISPLAY_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
$dhh_puc_repo = get_option( 'dhh_display_settings', array() );
$dhh_puc_repo = isset( $dhh_puc_repo['github_repo'] ) ? $dhh_puc_repo['github_repo'] : '';

if ( $dhh_puc_repo && file_exists( $dhh_puc_file ) ) {
	require_once $dhh_puc_file;
	$dhh_updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		$dhh_puc_repo,
		__FILE__,
		'dhh-tv-display'
	);

	if ( defined( 'DHH_DISPLAY_GITHUB_TOKEN' ) && DHH_DISPLAY_GITHUB_TOKEN ) {
		$dhh_updater->setAuthentication( DHH_DISPLAY_GITHUB_TOKEN );
	}
}

require_once DHH_DISPLAY_DIR . 'includes/class-dhh-rest.php';
require_once DHH_DISPLAY_DIR . 'includes/class-dhh-display.php';
require_once DHH_DISPLAY_DIR . 'includes/class-dhh-admin.php';

function dhh_display_init() {
	new DHH_Display_REST();
	new DHH_Display_Render();
	if ( is_admin() ) {
		new DHH_Display_Admin();
	}
}
add_action( 'plugins_loaded', 'dhh_display_init' );

function dhh_display_activate() {
	require_once DHH_DISPLAY_DIR . 'includes/class-dhh-display.php';
	$render = new DHH_Display_Render();
	$render->add_rewrite();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'dhh_display_activate' );

function dhh_display_deactivate() {
	flush_rewrite_rules();
	for ( $i = 1; $i <= 10; $i++ ) {
		delete_option( 'dhh_display_posts_' . $i );
	}
}
register_deactivation_hook( __FILE__, 'dhh_display_deactivate' );
