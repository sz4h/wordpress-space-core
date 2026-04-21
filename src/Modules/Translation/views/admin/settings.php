<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap sc-translation-wrap">

	<h2><?php esc_html_e( 'Translation API', 'space-core' ); ?></h2>

	<?php
	$current_url = add_query_arg( [], admin_url( 'admin.php' ) );
	$tabs        = [
		'config' => __( 'Configuration', 'space-core' ),
		'api'    => __( 'API Reference', 'space-core' ),
	];
	?>

	<nav class="nav-tab-wrapper sc-translation-tabs" style="margin-bottom:20px;">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( [ 'page' => 'sc-translation', 'sc_trans_tab' => $slug ], admin_url( 'admin.php' ) ) ); ?>"
			   class="nav-tab<?php echo $active_tab === $slug ? ' nav-tab-active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'api' === $active_tab ) : ?>
		<?php echo $this->view( 'admin/api/panel', compact( 'detected', 'languages' ) ); ?>
	<?php else : ?>
		<?php echo $this->view( 'admin/config/panel', compact( 'post_types', 'taxonomies', 'config', 'nonce' ) ); ?>
	<?php endif; ?>

</div>
