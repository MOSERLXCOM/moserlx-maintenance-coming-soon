<?php
/**
 * IP detection and whitelist matching utilities.
 *
 * @package MoserLX_Maintenance_Coming_Soon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects the visitor's real IP and checks it against the configured whitelist.
 */
class MLX_MCS_IP_Helper {

	/**
	 * Returns the real IP of the current visitor.
	 *
	 * Trusts REMOTE_ADDR by default. Falls back to proxy headers only when
	 * REMOTE_ADDR is a private/loopback address, which indicates a reverse-proxy
	 * setup (e.g. Cloudflare, Nginx in front of PHP-FPM).
	 *
	 * @return string Valid IP address, or empty string on failure.
	 */
	public static function get_current_ip() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( $_SERVER['REMOTE_ADDR'] ) : '';

		if ( self::is_private_ip( $ip ) ) {
			// Trust forwarded headers only when the direct connection is from localhost/private range.
			$proxy_headers = array(
				'HTTP_CF_CONNECTING_IP', // Cloudflare — most trustworthy when CF is in use.
				'HTTP_X_REAL_IP',
				'HTTP_X_FORWARDED_FOR',  // Can be spoofed; last resort.
			);

			foreach ( $proxy_headers as $header ) {
				if ( empty( $_SERVER[ $header ] ) ) {
					continue;
				}

				// X-Forwarded-For is a comma-separated chain; the left-most entry is the client.
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$candidate = trim( explode( ',', $_SERVER[ $header ] )[0] );

				if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
					$ip = $candidate;
					break;
				}
			}
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Returns true when an IP falls within a private or reserved range.
	 *
	 * Used to decide whether to consult proxy headers.
	 *
	 * @param string $ip IP address string.
	 * @return bool
	 */
	public static function is_private_ip( $ip ) {
		if ( empty( $ip ) ) {
			return false;
		}

		// FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE returns false for private/reserved IPs.
		return ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Checks whether the current visitor's IP is in the given whitelist.
	 *
	 * Whitelist is a newline-separated string of plain IPs or CIDR ranges
	 * (both IPv4 and IPv6 supported).
	 *
	 * @param string $whitelist_raw Raw option value (one entry per line).
	 * @return bool
	 */
	public static function is_whitelisted( $whitelist_raw ) {
		$visitor_ip = self::get_current_ip();

		if ( empty( $visitor_ip ) || empty( $whitelist_raw ) ) {
			return false;
		}

		$entries = array_filter( array_map( 'trim', explode( "\n", $whitelist_raw ) ) );

		foreach ( $entries as $entry ) {
			if ( false !== strpos( $entry, '/' ) ) {
				if ( self::ip_in_cidr( $visitor_ip, $entry ) ) {
					return true;
				}
			} elseif ( $entry === $visitor_ip ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether an IP address falls within a CIDR range.
	 *
	 * Supports IPv4 (e.g. 192.168.1.0/24) and IPv6 (e.g. 2001:db8::/32).
	 *
	 * @param string $ip   IP address to test.
	 * @param string $cidr CIDR notation range.
	 * @return bool
	 */
	public static function ip_in_cidr( $ip, $cidr ) {
		$parts  = explode( '/', $cidr, 2 );
		$subnet = $parts[0];
		$bits   = isset( $parts[1] ) ? (int) $parts[1] : null;

		if ( null === $bits ) {
			return $ip === $subnet;
		}

		// IPv4.
		if (
			filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) &&
			filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )
		) {
			$mask        = $bits > 0 ? ( ~( ( 1 << ( 32 - $bits ) ) - 1 ) ) : 0;
			$ip_long     = ip2long( $ip );
			$subnet_long = ip2long( $subnet );

			return ( $ip_long & $mask ) === ( $subnet_long & $mask );
		}

		// IPv6 — inet_pton available on all platforms PHP 7.4+ supports.
		if (
			function_exists( 'inet_pton' ) &&
			filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) &&
			filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 )
		) {
			$ip_bin     = inet_pton( $ip );
			$subnet_bin = inet_pton( $subnet );

			if ( false === $ip_bin || false === $subnet_bin ) {
				return false;
			}

			$ip_bits     = self::binary_to_bits( $ip_bin );
			$subnet_bits = self::binary_to_bits( $subnet_bin );

			return substr( $ip_bits, 0, $bits ) === substr( $subnet_bits, 0, $bits );
		}

		return false;
	}

	/**
	 * Converts a binary string (from inet_pton) to a string of '0'/'1' characters.
	 *
	 * @param string $bin Binary IP string.
	 * @return string
	 */
	private static function binary_to_bits( $bin ) {
		$bits = '';
		$len  = strlen( $bin );

		for ( $i = 0; $i < $len; $i++ ) {
			$bits .= str_pad( decbin( ord( $bin[ $i ] ) ), 8, '0', STR_PAD_LEFT );
		}

		return $bits;
	}
}
