<?php
/**
 * Runs when the plugin is deleted from the WordPress admin.
 *
 * Removes all plugin options from the database. No other data is stored.
 *
 * @package MoserLX_Maintenance_Coming_Soon
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'mlx_mcs_settings' );
