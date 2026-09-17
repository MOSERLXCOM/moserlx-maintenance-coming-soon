<?php
/**
 * Options helper — reads and writes all plugin settings as a single WP option.
 *
 * @package MoserLX_Maintenance_Coming_Soon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralises access to plugin settings stored under `mlx_mcs_settings`.
 */
class MLX_MCS_Options {

	/** WordPress option key that stores all settings. */
	const OPTION_KEY = 'mlx_mcs_settings';

	/**
	 * Returns the default value for every setting key.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults() {
		return array(
			'enabled'        => false,
			'mode'           => 'maintenance',
			'message'        => '',
			'allowed_roles'  => array(),
			'ip_whitelist'   => '',
			'passphrase'     => '',
			'block_rest_api' => false,
			'block_feeds'    => false,
		);
	}

	/**
	 * Returns all settings, merging saved values over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all() {
		$saved = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::get_defaults() );
	}

	/**
	 * Returns the value of a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key is not found.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$options = self::get_all();

		if ( array_key_exists( $key, $options ) ) {
			return $options[ $key ];
		}

		return $default;
	}

	/**
	 * Persists a full settings array to the database.
	 *
	 * @param array<string, mixed> $data Sanitized settings array.
	 * @return bool True if the value was updated, false otherwise.
	 */
	public static function update( $data ) {
		return update_option( self::OPTION_KEY, $data );
	}

	/**
	 * Deletes all plugin settings — called on uninstall.
	 *
	 * @return void
	 */
	public static function delete_all() {
		delete_option( self::OPTION_KEY );
	}
}
