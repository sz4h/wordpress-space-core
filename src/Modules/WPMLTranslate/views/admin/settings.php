<?php defined( 'ABSPATH' ) || exit; ?>

<?php $badgeColor = $wpml_active ? '#007017' : '#cc1818'; ?>

<div class="wrap sc-translation-wrap">
	<h2><?php esc_html_e( 'WPMLTranslate API', 'space-core' ); ?></h2>

	<table class="form-table" role="presentation">
		<tr>
			<th><?php esc_html_e( 'WPML Status', 'space-core' ); ?></th>
			<td>
				<span style="display:inline-block;padding:2px 10px;border-radius:3px;background:<?php echo esc_attr( $badgeColor ); ?>;color:#fff;font-weight:600;">
					<?php echo esc_html( $wpml_active ? __( 'WPML active', 'space-core' ) : __( 'WPML inactive', 'space-core' ) ); ?>
				</span>
			</td>
		</tr>

		<?php if ( ! empty( $languages ) ) : ?>
			<tr>
				<th><?php esc_html_e( 'Active Languages', 'space-core' ); ?></th>
				<td>
					<ul style="margin:0;">
						<?php foreach ( $languages as $lang ) : ?>
							<li>
								<code><?php echo esc_html( $lang['slug'] ); ?></code>
								- <?php echo esc_html( $lang['name'] ); ?>
								<?php if ( ! empty( $lang['default'] ) ) : ?>
									<em style="color:#666;"><?php esc_html_e( '(default)', 'space-core' ); ?></em>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</td>
			</tr>
		<?php endif; ?>
	</table>

	<hr />

	<h3><?php esc_html_e( 'REST API Endpoints', 'space-core' ); ?></h3>
	<p style="color:#555;margin-top:0;">
		<?php esc_html_e( 'All endpoints require authentication with the manage_options capability.', 'space-core' ); ?>
	</p>

	<table class="widefat striped" style="max-width:900px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Method', 'space-core' ); ?></th>
				<th><?php esc_html_e( 'Endpoint', 'space-core' ); ?></th>
				<th><?php esc_html_e( 'Description', 'space-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$base = esc_url( rest_url( 'space-core/v1' ) );
			$rows = [
				[ 'GET', $base . '/wpml-translate/jobs?status={status}&post_type={post_type}&source_lang={source_lang}&target_lang={target_lang}&limit={limit}', __( 'Discover WPML post translation jobs with read-only filters.', 'space-core' ) ],
				[ 'GET', $base . '/wpml-translate/jobs/{job_id}/payload', __( 'Extract the normalized payload and raw XLIFF for a specific WPML job.', 'space-core' ) ],
			];
			foreach ( $rows as [ $method, $url, $desc ] ) :
				?>
				<tr>
					<td><span style="font-weight:700;color:#0071a1;"><?php echo esc_html( $method ); ?></span></td>
					<td><code style="word-break:break-all;"><?php echo esc_html( $url ); ?></code></td>
					<td><?php echo esc_html( $desc ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<hr />

	<h3><?php esc_html_e( 'Postman Collection', 'space-core' ); ?></h3>
	<p style="color:#555;margin-top:0;">
		<?php esc_html_e( 'Download a ready-to-import Postman collection for the WPMLTranslate routes with sample variables for this site.', 'space-core' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<?php wp_nonce_field( 'sc_wpml_translate_export_postman' ); ?>
		<input type="hidden" name="action" value="sc_wpml_translate_export_postman">
		<button type="submit" class="button button-secondary">
			&#11015; <?php esc_html_e( 'Export Postman Collection', 'space-core' ); ?>
		</button>
	</form>
</div>
