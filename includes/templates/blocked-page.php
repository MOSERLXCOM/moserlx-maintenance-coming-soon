<?php
/**
 * Full-page template rendered for blocked visitors.
 *
 * Variables available from MLX_MCS_Frontend::show_page():
 *   $mode             string  'maintenance' | 'coming_soon'
 *   $message          string  HTML content (already sanitized via wp_kses_post on save)
 *   $passphrase_error bool    true when the visitor just submitted a wrong passphrase
 *   $passphrase_set   bool    true when a bypass passphrase is configured
 *   $current_url      string  URL to redirect back to after a successful passphrase
 *
 * This template intentionally skips wp_head()/wp_footer() to avoid loading
 * theme assets on a page that replaces the entire theme output.
 *
 * @package MoserLX_Maintenance_Coming_Soon
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mode_label = ( 'maintenance' === $mode )
	? __( 'Under Maintenance', 'moserlx-maintenance-coming-soon' )
	: __( 'Coming Soon', 'moserlx-maintenance-coming-soon' );

$page_title = get_bloginfo( 'name' ) . ' &mdash; ' . esc_html( $mode_label );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo wp_strip_all_tags( $page_title ); ?></title>
	<?php if ( 'maintenance' === $mode ) : ?>
	<meta name="robots" content="noindex, nofollow">
	<?php endif; ?>
	<style>
		*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

		body {
			background: #f4f6f9;
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 2rem 1rem;
			color: #333;
		}

		.mlx-mcs-wrap {
			width: 100%;
			max-width: 640px;
		}

		.mlx-mcs-box {
			background: #fff;
			border-radius: 10px;
			box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
			padding: 3rem 2.5rem;
			text-align: center;
		}

		.mlx-mcs-badge {
			display: inline-block;
			font-size: 0.7rem;
			font-weight: 700;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			padding: 0.3rem 0.75rem;
			border-radius: 20px;
			margin-bottom: 1.5rem;
		}

		.mlx-mcs-badge--maintenance { background: #fff4e0; color: #b45309; }
		.mlx-mcs-badge--coming-soon { background: #e0f2fe; color: #0369a1; }

		h1 {
			font-size: 2rem;
			font-weight: 700;
			color: #111;
			margin-bottom: 1.25rem;
			line-height: 1.2;
		}

		.mlx-mcs-message {
			font-size: 1rem;
			line-height: 1.75;
			color: #555;
		}

		.mlx-mcs-message p { margin-bottom: 0.75rem; }
		.mlx-mcs-message p:last-child { margin-bottom: 0; }

		.mlx-mcs-passphrase-wrap {
			margin-top: 2.5rem;
			padding-top: 2rem;
			border-top: 1px solid #eee;
		}

		.mlx-mcs-passphrase-wrap p {
			font-size: 0.85rem;
			color: #888;
			margin-bottom: 0.75rem;
		}

		.mlx-mcs-error {
			font-size: 0.85rem;
			color: #dc2626;
			margin-bottom: 0.75rem;
			font-weight: 500;
		}

		.mlx-mcs-passphrase-form {
			display: flex;
			gap: 0.5rem;
			justify-content: center;
			flex-wrap: wrap;
		}

		.mlx-mcs-passphrase-form input[type="password"] {
			flex: 1;
			min-width: 180px;
			max-width: 280px;
			padding: 0.6rem 0.875rem;
			border: 1px solid #d1d5db;
			border-radius: 6px;
			font-size: 0.9rem;
			outline: none;
			transition: border-color 0.15s;
		}

		.mlx-mcs-passphrase-form input[type="password"]:focus {
			border-color: #6366f1;
			box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
		}

		.mlx-mcs-passphrase-form button {
			padding: 0.6rem 1.25rem;
			background: #6366f1;
			color: #fff;
			border: none;
			border-radius: 6px;
			font-size: 0.9rem;
			font-weight: 600;
			cursor: pointer;
			transition: background 0.15s;
		}

		.mlx-mcs-passphrase-form button:hover { background: #4f46e5; }

		.mlx-mcs-site-name {
			margin-top: 2rem;
			font-size: 0.78rem;
			color: #bbb;
		}
	</style>
</head>
<body>
	<div class="mlx-mcs-wrap">
		<div class="mlx-mcs-box">

			<span class="mlx-mcs-badge mlx-mcs-badge--<?php echo esc_attr( 'maintenance' === $mode ? 'maintenance' : 'coming-soon' ); ?>">
				<?php echo esc_html( $mode_label ); ?>
			</span>

			<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>

			<?php if ( ! empty( $message ) ) : ?>
			<div class="mlx-mcs-message">
				<?php
				// Content was already run through wp_kses_post() on save — safe to output directly.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $message;
				?>
			</div>
			<?php endif; ?>

			<?php if ( $passphrase_set ) : ?>
			<div class="mlx-mcs-passphrase-wrap">
				<?php if ( $passphrase_error ) : ?>
				<p class="mlx-mcs-error"><?php esc_html_e( 'Incorrect passphrase. Please try again.', 'moserlx-maintenance-coming-soon' ); ?></p>
				<?php else : ?>
				<p><?php esc_html_e( 'Have access? Enter your passphrase below.', 'moserlx-maintenance-coming-soon' ); ?></p>
				<?php endif; ?>

				<form method="post" class="mlx-mcs-passphrase-form" action="">
					<?php wp_nonce_field( 'mlx_mcs_bypass', 'mlx_mcs_nonce' ); ?>
					<input type="hidden" name="mlx_mcs_redirect" value="<?php echo esc_url( $current_url ); ?>">
					<input
						type="password"
						name="mlx_mcs_passphrase"
						placeholder="<?php esc_attr_e( 'Passphrase', 'moserlx-maintenance-coming-soon' ); ?>"
						autocomplete="current-password"
					>
					<button type="submit" name="mlx_mcs_passphrase_submit" value="1">
						<?php esc_html_e( 'Enter', 'moserlx-maintenance-coming-soon' ); ?>
					</button>
				</form>
			</div>
			<?php endif; ?>

			<p class="mlx-mcs-site-name"><?php echo esc_html( get_bloginfo( 'url' ) ); ?></p>

		</div>
	</div>
</body>
</html>
