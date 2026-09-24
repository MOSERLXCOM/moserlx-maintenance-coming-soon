<?php
/**
 * Plugin Name: Maintenance & Coming Soon by MOSERLX
 * Plugin URI:  https://moserlx.com
 * Description: Puts a WordPress site into maintenance or coming soon mode with role-based access, IP whitelist, passphrase bypass, and correct HTTP status codes.
 * Version:     1.1.1
 * Author:      MOSERLX Team
 * Author URI:  https://moserlx.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: moserlx-maintenance-coming-soon
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Update URI:        https://github.com/MOSERLXCOM/moserlx-maintenance-coming-soon
 *
 * @package MoserLX_Maintenance_Coming_Soon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MLX_MCS_VERSION', '1.1.1' );
define( 'MLX_MCS_PLUGIN_FILE', __FILE__ );
define( 'MLX_MCS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MLX_MCS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Initialize Plugin Update Checker — checks GitHub Releases for new versions.
if ( file_exists( MLX_MCS_PLUGIN_DIR . 'includes/update/load-v5p7.php' ) ) {
	require_once MLX_MCS_PLUGIN_DIR . 'includes/update/load-v5p7.php';
	$mlx_mcs_updater = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/MOSERLXCOM/moserlx-maintenance-coming-soon/',
		__FILE__,
		'moserlx-maintenance-coming-soon'
	);
	$mlx_mcs_updater->setBranch( 'main' );
	$mlx_mcs_updater->getVcsApi()->enableReleaseAssets();
}

require_once MLX_MCS_PLUGIN_DIR . 'includes/class-mlx-mcs-options.php';
require_once MLX_MCS_PLUGIN_DIR . 'includes/class-mlx-mcs-ip-helper.php';
require_once MLX_MCS_PLUGIN_DIR . 'includes/class-mlx-mcs-frontend.php';

if ( is_admin() ) {
	require_once MLX_MCS_PLUGIN_DIR . 'admin/class-mlx-mcs-admin.php';
}

/**
 * Bootstraps the plugin after all plugins are loaded.
 *
 * Using plugins_loaded ensures other plugins (e.g. custom role providers,
 * MU-plugins) are available before we read roles or check capabilities.
 *
 * @return void
 */
function mlx_mcs_init() {
	load_plugin_textdomain(
		'moserlx-maintenance-coming-soon',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	$frontend = new MLX_MCS_Frontend();
	$frontend->init();

	if ( is_admin() ) {
		$admin = new MLX_MCS_Admin();
		$admin->init();
	}
}
add_action( 'plugins_loaded', 'mlx_mcs_init' );
