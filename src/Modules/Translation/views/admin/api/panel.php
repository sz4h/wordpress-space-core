<?php defined( 'ABSPATH' ) || exit; ?>

<?php
$plugin_label = match ( $detected ) {
	'polylang' => __( 'Polylang', 'space-core' ),
	'wpml'     => __( 'WPML', 'space-core' ),
	default    => __( 'None detected', 'space-core' ),
};
$badge_color  = 'none' === $detected ? '#cc1818' : '#007017';
?>

<table class="form-table" role="presentation">
	<tr>
		<th><?php esc_html_e( 'Multilingual Plugin', 'space-core' ); ?></th>
		<td>
			<span style="display:inline-block;padding:2px 10px;border-radius:3px;background:<?php echo esc_attr( $badge_color ); ?>;color:#fff;font-weight:600;">
				<?php echo esc_html( $plugin_label ); ?>
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
						— <?php echo esc_html( $lang['name'] ); ?>
						<?php if ( $lang['default'] ) : ?>
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
			[ 'GET',  $base . '/{source_lang}/translation/{taxonomy}/terms?target_lang={target_lang}', __( 'List terms missing a translation in target_lang', 'space-core' ) ],
			[ 'POST', $base . '/{target_lang}/translation/{taxonomy}/terms', __( 'Bulk create term translations — body: [{id, name, slug}]', 'space-core' ) ],
			[ 'GET',  $base . '/{source_lang}/translation/menus?target_lang={target_lang}', __( 'List menu items missing a translation', 'space-core' ) ],
			[ 'GET',  $base . '/{source_lang}/translation/menus/{menu_id}?target_lang={target_lang}', __( 'List menu items for a specific menu missing a translation', 'space-core' ) ],
			[ 'POST', $base . '/{target_lang}/translation/menus', __( 'Bulk create menu item translations — body: [{id, title, url, menu_id}]', 'space-core' ) ],
			[ 'GET',  $base . '/{source_lang}/translation/{post_type}/posts?target_lang={target_lang}', __( 'List posts/pages/products missing a translation (includes translatable meta)', 'space-core' ) ],
			[ 'POST', $base . '/{target_lang}/translation/{post_type}/posts', __( 'Bulk create post translations — body: [{id, title, slug, excerpt, content, meta{}}]', 'space-core' ) ],
		];
		foreach ( $rows as [ $method, $url, $desc ] ) :
			$color = 'POST' === $method ? '#c8590a' : '#0071a1';
			?>
			<tr>
				<td><span style="font-weight:700;color:<?php echo esc_attr( $color ); ?>;"><?php echo esc_html( $method ); ?></span></td>
				<td><code style="word-break:break-all;"><?php echo esc_html( $url ); ?></code></td>
				<td><?php echo esc_html( $desc ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<hr />

<h3><?php esc_html_e( 'Postman Collection', 'space-core' ); ?></h3>
<p style="color:#555;margin-top:0;">
	<?php esc_html_e( 'Download a ready-to-import Postman collection with all endpoints pre-filled for the active languages on this site.', 'space-core' ); ?>
</p>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
	<?php wp_nonce_field( 'sc_translation_export_postman' ); ?>
	<input type="hidden" name="action" value="sc_translation_export_postman">
	<button type="submit" class="button button-secondary">
		&#11015; <?php esc_html_e( 'Export Postman Collection', 'space-core' ); ?>
	</button>
</form>
