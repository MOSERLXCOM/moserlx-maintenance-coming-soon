<?php
/**
 * Admin settings page HTML.
 *
 * Variables available from MLX_MCS_Admin::render_settings_page():
 *   $options  array  Full settings array from MLX_MCS_Options::get_all()
 *
 * @package MoserLX_Maintenance_Coming_Soon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_active  = ! empty( $options['enabled'] );
$mode       = isset( $options['mode'] ) ? $options['mode'] : 'maintenance';
$all_roles  = wp_roles()->roles;
$saved_roles = isset( $options['allowed_roles'] ) && is_array( $options['allowed_roles'] )
	? $options['allowed_roles']
	: array();
$current_ip = MLX_MCS_IP_Helper::get_current_ip();
?>
<div class="wrap mlx-mcs-wrap">

	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="mlx-mcs-status-bar <?php echo $is_active ? 'mlx-mcs-status-bar--active' : 'mlx-mcs-status-bar--inactive'; ?>">
		<?php if ( $is_active ) : ?>
			<span class="mlx-mcs-dot mlx-mcs-dot--on"></span>
			<?php if ( 'maintenance' === $mode ) : ?>
				<?php esc_html_e( 'Maintenance mode is ON', 'moserlx-maintenance-coming-soon' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Coming Soon mode is ON', 'moserlx-maintenance-coming-soon' ); ?>
			<?php endif; ?>
		<?php else : ?>
			<span class="mlx-mcs-dot mlx-mcs-dot--off"></span>
			<?php esc_html_e( 'Plugin is disabled — site is publicly accessible', 'moserlx-maintenance-coming-soon' ); ?>
		<?php endif; ?>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="mlx_mcs_save">
		<?php wp_nonce_field( 'mlx_mcs_save_settings', 'mlx_mcs_nonce' ); ?>

		<?php /* ── Section: General ─────────────────────────────────────────── */ ?>
		<h2 class="mlx-mcs-section-title"><?php esc_html_e( 'General', 'moserlx-maintenance-coming-soon' ); ?></h2>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'moserlx-maintenance-coming-soon' ); ?></th>
				<td>
					<label>
						<input
							type="checkbox"
							name="mlx_mcs_enabled"
							value="1"
							<?php checked( $is_active ); ?>
						>
						<?php esc_html_e( 'Enable Maintenance / Coming Soon mode', 'moserlx-maintenance-coming-soon' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Mode', 'moserlx-maintenance-coming-soon' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input
								type="radio"
								name="mlx_mcs_mode"
								value="maintenance"
								<?php checked( $mode, 'maintenance' ); ?>
							>
							<?php esc_html_e( 'Maintenance', 'moserlx-maintenance-coming-soon' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Sends HTTP 503 — search engines treat this as a temporary outage and won\'t de-index the site.', 'moserlx-maintenance-coming-soon' ); ?></p>

						<br>

						<label>
							<input
								type="radio"
								name="mlx_mcs_mode"
								value="coming_soon"
								<?php checked( $mode, 'coming_soon' ); ?>
							>
							<?php esc_html_e( 'Coming Soon', 'moserlx-maintenance-coming-soon' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Sends HTTP 200 — the page is live but the site hasn\'t launched yet.', 'moserlx-maintenance-coming-soon' ); ?></p>
					</fieldset>
				</td>
			</tr>
		</table>

		<?php /* ── Section: Display ─────────────────────────────────────────── */ ?>
		<h2 class="mlx-mcs-section-title"><?php esc_html_e( 'Display Message', 'moserlx-maintenance-coming-soon' ); ?></h2>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="mlx_mcs_message"><?php esc_html_e( 'Message', 'moserlx-maintenance-coming-soon' ); ?></label>
				</th>
				<td>
					<?php
					wp_editor(
						isset( $options['message'] ) ? $options['message'] : '',
						'mlx_mcs_message',
						array(
							'textarea_name' => 'mlx_mcs_message',
							'textarea_rows' => 8,
							'media_buttons' => false,
							'teeny'         => true,
						)
					);
					?>
					<p class="description"><?php esc_html_e( 'Shown on the blocked page. HTML is allowed.', 'moserlx-maintenance-coming-soon' ); ?></p>
				</td>
			</tr>
		</table>

		<?php /* ── Section: Access Control ──────────────────────────────────── */ ?>
		<h2 class="mlx-mcs-section-title"><?php esc_html_e( 'Access Control', 'moserlx-maintenance-coming-soon' ); ?></h2>

		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><?php esc_html_e( 'Allowed Roles', 'moserlx-maintenance-coming-soon' ); ?></th>
				<td>
					<p class="description" style="margin-bottom:.75rem;">
						<?php esc_html_e( 'Users with these roles can access the site. Administrators are always allowed.', 'moserlx-maintenance-coming-soon' ); ?>
					</p>
					<?php foreach ( $all_roles as $role_key => $role_data ) : ?>
						<?php if ( 'administrator' === $role_key ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<label style="display:block;margin-bottom:.35rem;">
							<input
								type="checkbox"
								name="mlx_mcs_roles[]"
								value="<?php echo esc_attr( $role_key ); ?>"
								<?php checked( in_array( $role_key, $saved_roles, true ) ); ?>
							>
							<?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="mlx_mcs_ip_whitelist"><?php esc_html_e( 'IP Whitelist', 'moserlx-maintenance-coming-soon' ); ?></label>
				</th>
				<td>
					<textarea
						name="mlx_mcs_ip_whitelist"
						id="mlx_mcs_ip_whitelist"
						class="large-text code"
						rows="5"
					><?php echo esc_textarea( isset( $options['ip_whitelist'] ) ? $options['ip_whitelist'] : '' ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'One IP address or CIDR range per line. Example: 192.168.1.1 or 10.0.0.0/8.', 'moserlx-maintenance-coming-soon' ); ?>
						<br>
						<?php
						printf(
							/* translators: %s: the admin's current IP address */
							esc_html__( 'Your current IP: %s', 'moserlx-maintenance-coming-soon' ),
							'<code>' . esc_html( $current_ip ) . '</code>'
						);
						?>
						&nbsp;
						<button
							type="button"
							id="mlx-mcs-add-ip"
							class="button button-small"
							data-ip="<?php echo esc_attr( $current_ip ); ?>"
						>
							<?php esc_html_e( 'Add My IP', 'moserlx-maintenance-coming-soon' ); ?>
						</button>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="mlx_mcs_passphrase"><?php esc_html_e( 'Bypass Passphrase', 'moserlx-maintenance-coming-soon' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						name="mlx_mcs_passphrase"
						id="mlx_mcs_passphrase"
						class="regular-text"
						value="<?php echo esc_attr( isset( $options['passphrase'] ) ? $options['passphrase'] : '' ); ?>"
						autocomplete="off"
					>
					<p class="description"><?php esc_html_e( 'Leave empty to disable. Visitors who enter this passphrase get a bypass cookie valid for 24 hours.', 'moserlx-maintenance-coming-soon' ); ?></p>
				</td>
			</tr>

		</table>

		<?php /* ── Section: Advanced ─────────────────────────────────────────── */ ?>
		<h2 class="mlx-mcs-section-title"><?php esc_html_e( 'Advanced', 'moserlx-maintenance-coming-soon' ); ?></h2>

		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><?php esc_html_e( 'REST API', 'moserlx-maintenance-coming-soon' ); ?></th>
				<td>
					<label>
						<input
							type="checkbox"
							name="mlx_mcs_block_rest"
							value="1"
							<?php checked( ! empty( $options['block_rest_api'] ) ); ?>
						>
						<?php esc_html_e( 'Block REST API for visitors who are not allowed through', 'moserlx-maintenance-coming-soon' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Warning: some plugins rely on the REST API even on the frontend. Enable with care.', 'moserlx-maintenance-coming-soon' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Feeds', 'moserlx-maintenance-coming-soon' ); ?></th>
				<td>
					<label>
						<input
							type="checkbox"
							name="mlx_mcs_block_feeds"
							value="1"
							<?php checked( ! empty( $options['block_feeds'] ) ); ?>
						>
						<?php esc_html_e( 'Block XML feeds (RSS/Atom) for visitors who are not allowed through', 'moserlx-maintenance-coming-soon' ); ?>
					</label>
				</td>
			</tr>

		</table>

		<?php submit_button( __( 'Save Settings', 'moserlx-maintenance-coming-soon' ) ); ?>
	</form>

</div>
