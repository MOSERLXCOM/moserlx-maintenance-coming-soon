<?php
/**
 * Frontend request interceptor — blocks unauthorized visitors when the plugin is active.
 *
 * @package MoserLX_Maintenance_Coming_Soon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers all WordPress hooks needed to intercept and block frontend requests.
 */
class MLX_MCS_Frontend {

	/** Cookie name used for the passphrase bypass token. */
	const COOKIE_NAME = 'mlx_mcs_bypass';

	/**
	 * Tracks whether the current request had a wrong passphrase submission.
	 * Used to pass the error flag to the template without a redirect round-trip.
	 *
	 * @var bool
	 */
	private static $passphrase_error = false;

	/**
	 * Registers all WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Handle passphrase form submission at init (before any output / template loading).
		add_action( 'init', array( $this, 'handle_passphrase_submission' ), 1 );

		// Intercept regular page requests.
		add_action( 'template_redirect', array( $this, 'maybe_show_blocked_page' ), 1 );

		// REST API blocking (opt-in).
		add_filter( 'rest_authentication_errors', array( $this, 'maybe_block_rest' ), 1 );

		// Feed blocking (opt-in) — all feed hooks share the same callback.
		foreach ( array( 'do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom' ) as $hook ) {
			add_action( $hook, array( $this, 'maybe_block_feed' ), 1 );
		}
	}

	/**
	 * Processes a passphrase form POST submission.
	 *
	 * On success: sets a bypass cookie and redirects (PRG pattern).
	 * On failure: sets the static error flag so the template can show an error
	 * without an extra redirect.
	 *
	 * Runs at init priority 1 so headers are still writable.
	 *
	 * @return void
	 */
	public function handle_passphrase_submission() {
		if ( ! isset( $_POST['mlx_mcs_passphrase_submit'] ) ) {
			return;
		}

		if ( ! MLX_MCS_Options::get( 'enabled' ) ) {
			return;
		}

		// Verify nonce — wp_verify_nonce is preferred over check_admin_referer on the frontend
		// because it returns false on failure instead of calling wp_die().
		$nonce = isset( $_POST['mlx_mcs_nonce'] ) ? sanitize_key( wp_unslash( $_POST['mlx_mcs_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'mlx_mcs_bypass' ) ) {
			return;
		}

		$stored = (string) MLX_MCS_Options::get( 'passphrase', '' );

		if ( '' === $stored ) {
			return;
		}

		$submitted = isset( $_POST['mlx_mcs_passphrase'] )
			? sanitize_text_field( wp_unslash( $_POST['mlx_mcs_passphrase'] ) )
			: '';

		if ( hash_equals( $stored, $submitted ) ) {
			// Hash the passphrase with a WP salt so the cookie value can't be reverse-engineered.
			$token = wp_hash( $stored . wp_salt( 'auth' ) );

			setcookie(
				self::COOKIE_NAME,
				$token,
				time() + DAY_IN_SECONDS,
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				true // httpOnly — not accessible via JS.
			);

			$redirect = isset( $_POST['mlx_mcs_redirect'] )
				? esc_url_raw( wp_unslash( $_POST['mlx_mcs_redirect'] ) )
				: home_url( '/' );

			wp_safe_redirect( $redirect );
			exit;
		}

		self::$passphrase_error = true;
	}

	/**
	 * Outputs the blocked page when the current request should be intercepted.
	 *
	 * @return void
	 */
	public function maybe_show_blocked_page() {
		if ( ! $this->should_block() ) {
			return;
		}

		$this->show_page();
	}

	/**
	 * Blocks REST API requests for unauthorized visitors (opt-in).
	 *
	 * Hooked to `rest_authentication_errors` — returning a WP_Error short-circuits
	 * the REST request with that error.
	 *
	 * @param WP_Error|true|null $result Existing authentication result.
	 * @return WP_Error|true|null
	 */
	public function maybe_block_rest( $result ) {
		if ( ! MLX_MCS_Options::get( 'block_rest_api' ) ) {
			return $result;
		}

		if ( ! $this->should_block() ) {
			return $result;
		}

		return new WP_Error(
			'mlx_mcs_unavailable',
			__( 'Service temporarily unavailable.', 'moserlx-maintenance-coming-soon' ),
			array( 'status' => 503 )
		);
	}

	/**
	 * Blocks XML feed requests for unauthorized visitors (opt-in).
	 *
	 * @return void
	 */
	public function maybe_block_feed() {
		if ( ! MLX_MCS_Options::get( 'block_feeds' ) ) {
			return;
		}

		if ( ! $this->should_block() ) {
			return;
		}

		wp_die(
			esc_html__( 'Feed not available.', 'moserlx-maintenance-coming-soon' ),
			esc_html__( 'Service Unavailable', 'moserlx-maintenance-coming-soon' ),
			array( 'response' => 503 )
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Determines whether the current request should be blocked.
	 *
	 * Returns false (allow) when any of the following is true:
	 * - Plugin is disabled.
	 * - User is an administrator (manage_options).
	 * - Request is for wp-login / wp-admin / cron / admin AJAX.
	 * - Visitor's IP is in the whitelist.
	 * - Visitor has a valid bypass cookie.
	 * - Logged-in user has an explicitly allowed role.
	 *
	 * @return bool True = block, false = allow.
	 */
	private function should_block() {
		if ( ! MLX_MCS_Options::get( 'enabled' ) ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( $this->is_always_allowed_request() ) {
			return false;
		}

		if ( MLX_MCS_IP_Helper::is_whitelisted( (string) MLX_MCS_Options::get( 'ip_whitelist', '' ) ) ) {
			return false;
		}

		if ( $this->has_valid_bypass_cookie() ) {
			return false;
		}

		if ( $this->current_user_has_allowed_role() ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns true for requests that must never be blocked.
	 *
	 * Covers: wp-admin, wp-login.php, WP-Cron, admin AJAX.
	 *
	 * @return bool
	 */
	private function is_always_allowed_request() {
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}

		// is_admin() returns true for wp-admin/ AND admin-ajax.php.
		if ( is_admin() ) {
			return true;
		}

		// Direct access to wp-login.php.
		$self = isset( $_SERVER['PHP_SELF'] ) ? $_SERVER['PHP_SELF'] : '';
		if ( false !== strpos( $self, 'wp-login.php' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns true if the current visitor holds a valid passphrase bypass cookie.
	 *
	 * @return bool
	 */
	private function has_valid_bypass_cookie() {
		$stored = (string) MLX_MCS_Options::get( 'passphrase', '' );

		if ( '' === $stored || empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return false;
		}

		$expected = wp_hash( $stored . wp_salt( 'auth' ) );
		$provided = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );

		return hash_equals( $expected, $provided );
	}

	/**
	 * Returns true if the logged-in user belongs to at least one of the allowed roles.
	 *
	 * @return bool
	 */
	private function current_user_has_allowed_role() {
		$allowed = MLX_MCS_Options::get( 'allowed_roles', array() );

		if ( empty( $allowed ) || ! is_array( $allowed ) || ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();

		if ( ! ( $user instanceof WP_User ) ) {
			return false;
		}

		foreach ( $allowed as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sends the appropriate HTTP status code and renders the blocked page, then exits.
	 *
	 * HTTP 503 (Maintenance): tells search engines the outage is temporary.
	 * HTTP 200 (Coming Soon): standard response — the page is "live" but not launched.
	 *
	 * @return void
	 */
	private function show_page() {
		$mode    = (string) MLX_MCS_Options::get( 'mode', 'maintenance' );
		$message = (string) MLX_MCS_Options::get( 'message', '' );

		if ( 'maintenance' === $mode ) {
			status_header( 503 );
			header( 'Retry-After: 3600' );
		} else {
			status_header( 200 );
		}

		nocache_headers();

		$passphrase_error = self::$passphrase_error;
		$passphrase_set   = '' !== (string) MLX_MCS_Options::get( 'passphrase', '' );

		// Redirect after successful passphrase submission — send back to the original URL.
		$current_url = home_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' );

		require MLX_MCS_PLUGIN_DIR . 'includes/templates/blocked-page.php';
		exit;
	}
}
