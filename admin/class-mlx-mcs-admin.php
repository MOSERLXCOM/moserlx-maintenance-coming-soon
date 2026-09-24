<?php
/**
 * Admin — settings page registration, asset enqueueing, form handling.
 *
 * @package MoserLX_Maintenance_Coming_Soon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin settings page and handles form saves.
 */
class MLX_MCS_Admin {

	/** Slug used for the settings page in the WP admin menu. */
	const MENU_SLUG = 'mlx-mcs-settings';

	/**
	 * Registers all WordPress hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'show_saved_notice' ) );
		add_action( 'admin_post_mlx_mcs_save', array( $this, 'handle_save' ) );

		// Admin bar status indicator.
		add_action( 'admin_bar_menu',        array( $this, 'add_admin_bar_notice' ), 100 );
		add_action( 'wp_enqueue_scripts',    array( $this, 'enqueue_admin_bar_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_bar_styles' ) );

		// "Settings" link on the plugins list page.
		add_filter(
			'plugin_action_links_' . plugin_basename( MLX_MCS_PLUGIN_FILE ),
			array( $this, 'add_action_links' )
		);
	}

	/**
	 * Registers the settings page under Settings > Maintenance.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'Maintenance & Coming Soon', 'moserlx-maintenance-coming-soon' ),
			__( 'Maintenance', 'moserlx-maintenance-coming-soon' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueues admin stylesheet and script only on the plugin settings page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'mlx-mcs-admin',
			MLX_MCS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			MLX_MCS_VERSION
		);

		wp_enqueue_script(
			'mlx-mcs-admin',
			MLX_MCS_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			MLX_MCS_VERSION,
			true
		);

		wp_localize_script(
			'mlx-mcs-admin',
			'mlxMcsAdmin',
			array(
				'currentIp' => MLX_MCS_IP_Helper::get_current_ip(),
			)
		);

		// TinyMCE editor for the message field.
		wp_enqueue_editor();
	}

	/**
	 * Shows a success notice after a successful settings save.
	 *
	 * The notice is scoped to the plugin settings page only.
	 *
	 * @return void
	 */
	public function show_saved_notice() {
		$screen = get_current_screen();

		if ( null === $screen || 'settings_page_' . self::MENU_SLUG !== $screen->id ) {
			return;
		}

		if ( isset( $_GET['mlx_mcs_saved'] ) && '1' === $_GET['mlx_mcs_saved'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Settings saved.', 'moserlx-maintenance-coming-soon' )
				. '</p></div>';
		}
	}

	/**
	 * Processes the settings form POST and redirects back.
	 *
	 * Hooked to admin_post_mlx_mcs_save — only fires for logged-in users.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'moserlx-maintenance-coming-soon' ) );
		}

		check_admin_referer( 'mlx_mcs_save_settings', 'mlx_mcs_nonce' );

		$mode = isset( $_POST['mlx_mcs_mode'] ) ? sanitize_key( wp_unslash( $_POST['mlx_mcs_mode'] ) ) : 'maintenance';
		if ( ! in_array( $mode, array( 'maintenance', 'coming_soon' ), true ) ) {
			$mode = 'maintenance';
		}

		// Validate submitted roles against roles that actually exist on this site.
		$allowed_roles = array();
		if ( isset( $_POST['mlx_mcs_roles'] ) && is_array( $_POST['mlx_mcs_roles'] ) ) {
			$valid_roles   = array_keys( wp_roles()->roles );
			$submitted     = array_map( 'sanitize_key', wp_unslash( $_POST['mlx_mcs_roles'] ) );
			$allowed_roles = array_values( array_intersect( $submitted, $valid_roles ) );
		}

		$settings = array(
			'enabled'        => isset( $_POST['mlx_mcs_enabled'] ),
			'mode'           => $mode,
			// wp_kses_post allows standard HTML (headings, links, lists, etc.) but strips scripts.
			'message'        => isset( $_POST['mlx_mcs_message'] )
				? wp_kses_post( wp_unslash( $_POST['mlx_mcs_message'] ) )
				: '',
			'allowed_roles'  => $allowed_roles,
			'ip_whitelist'   => isset( $_POST['mlx_mcs_ip_whitelist'] )
				? sanitize_textarea_field( wp_unslash( $_POST['mlx_mcs_ip_whitelist'] ) )
				: '',
			'passphrase'     => isset( $_POST['mlx_mcs_passphrase'] )
				? sanitize_text_field( wp_unslash( $_POST['mlx_mcs_passphrase'] ) )
				: '',
			'block_rest_api' => isset( $_POST['mlx_mcs_block_rest'] ),
			'block_feeds'    => isset( $_POST['mlx_mcs_block_feeds'] ),
		);

		MLX_MCS_Options::update( $settings );

		wp_safe_redirect(
			add_query_arg(
				'mlx_mcs_saved',
				'1',
				admin_url( 'options-general.php?page=' . self::MENU_SLUG )
			)
		);
		exit;
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = MLX_MCS_Options::get_all();

		require MLX_MCS_PLUGIN_DIR . 'admin/partials/settings-page.php';
	}

	/**
	 * Adds a status indicator to the admin bar when the plugin is active.
	 *
	 * Fires on both the frontend (when admin bar is shown) and in wp-admin.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
	 * @return void
	 */
	public function add_admin_bar_notice( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = MLX_MCS_Options::get_all();

		if ( ! $options['enabled'] ) {
			return;
		}

		$label = 'coming_soon' === $options['mode']
			? __( 'Coming Soon: ON', 'moserlx-maintenance-coming-soon' )
			: __( 'Maintenance: ON', 'moserlx-maintenance-coming-soon' );

		$wp_admin_bar->add_node(
			array(
				'id'    => 'mlx-mcs-status',
				'title' => esc_html( $label ),
				'href'  => admin_url( 'options-general.php?page=' . self::MENU_SLUG ),
				'meta'  => array(
					'title' => esc_attr__( 'Maintenance & Coming Soon — click to manage settings', 'moserlx-maintenance-coming-soon' ),
				),
			)
		);
	}

	/**
	 * Enqueues inline CSS for the admin bar status node.
	 *
	 * Hooked to both wp_enqueue_scripts (frontend) and admin_enqueue_scripts (backend)
	 * so the styles load wherever the admin bar appears.
	 *
	 * @return void
	 */
	public function enqueue_admin_bar_styles() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = MLX_MCS_Options::get_all();

		if ( ! $options['enabled'] ) {
			return;
		}

		// Red for maintenance (urgency), blue for coming soon (informational).
		$bg_color = 'coming_soon' === $options['mode'] ? '#2271b1' : '#d63638';

		$css = sprintf(
			'#wp-admin-bar-mlx-mcs-status > .ab-item {
				background-color: %1$s !important;
				color: #fff !important;
				font-weight: 600;
			}
			#wp-admin-bar-mlx-mcs-status > .ab-item:hover {
				background-color: %1$s !important;
				opacity: 0.85;
			}',
			sanitize_hex_color( $bg_color )
		);

		wp_add_inline_style( 'admin-bar', $css );
	}

	/**
	 * Adds a "Settings" link to the plugin row on the Plugins screen.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array
	 */
	public function add_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::MENU_SLUG ) ),
			esc_html__( 'Settings', 'moserlx-maintenance-coming-soon' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
