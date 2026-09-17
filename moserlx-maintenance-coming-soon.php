<?php
/**
 * Plugin Name: Maintenance & Coming Soon by MOSERLX
 * Plugin URI:  https://moserlx.com
 * Description: Puts a WordPress site into maintenance or coming soon mode with role-based access, IP whitelist, passphrase bypass, and correct HTTP status codes.
 * Version:     1.0.0
 * Author:      MOSERLX Team
 * Author URI:  https://moserlx.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: moserlx-maintenance-coming-soon
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP:      7.4
 *
 * @package MoserLX_Maintenance_Coming_Soon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MLX_MCS_VERSION', '1.0.0' );
define( 'MLX_MCS_PLUGIN_FILE', __FILE__ );
define( 'MLX_MCS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MLX_MCS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

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
