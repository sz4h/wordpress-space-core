<?php

defined( 'ABSPATH' ) || exit;

?>
<div class="sc-mc-currencies-wrap">
	<p style="margin-bottom:12px;">
		<button type="button" id="sc-mc-seed-btn" class="button">
			<?php esc_html_e( 'Seed GCC Currencies', 'space-core' ); ?>
		</button>
		<button type="button" id="sc-mc-add-btn" class="button button-primary" style="margin-left:6px;">
			<?php esc_html_e( '+ Add Currency', 'space-core' ); ?>
		</button>
		<span id="sc-mc-msg" style="margin-left:12px;font-weight:600;"></span>
	</p>

	<table class="widefat striped sc-mc-table" id="sc-mc-currencies-table">
		<thead>
			<tr>
				<th style="width:30px;"></th>
				<th><?php esc_html_e( 'Code', 'space-core' ); ?></th>
				<th><?php esc_html_e( 'Name EN / AR', 'space-core' ); ?></th>
				<th><?php esc_html_e( 'Symbol EN / AR', 'space-core' ); ?></th>
				<th><?php esc_html_e( 'Rate', 'space-core' ); ?></th>
				<th><?php esc_html_e( 'Modifier', 'space-core' ); ?></th>
				<th><?php esc_html_e( 'Effective', 'space-core' ); ?></th>
				<th><?php esc_html_e( 'Dec', 'space-core' ); ?></th>
				<th><?php esc_html_e( 'Position', 'space-core' ); ?></th>
				<th><?php esc_html_e( 'Countries', 'space-core' ); ?></th>
				<th><?php esc_html_e( 'Gateways', 'space-core' ); ?></th>
				<th><?php esc_html_e( 'Default', 'space-core' ); ?></th>
				<th><?php esc_html_e( 'Active', 'space-core' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'space-core' ); ?></th>
			</tr>
		</thead>
		<tbody id="sc-mc-tbody">
			<?php foreach ( $currencies as $currency ) : ?>
				<?php
				echo $this->view( 'admin/currencies/row', [
					'currency'    => $currency,
					'positions'   => $positions,
					'is_template' => false,
				] );
				?>
			<?php endforeach; ?>
			<?php if ( empty( $currencies ) ) : ?>
				<tr id="sc-mc-empty-row">
					<td colspan="14" style="text-align:center;padding:20px;color:#888;">
						<?php esc_html_e( 'No currencies yet. Click "Seed GCC Currencies" to get started.', 'space-core' ); ?>
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<template id="sc-mc-row-template">
		<?php
		echo $this->view( 'admin/currencies/row', [
			'currency'    => [],
			'positions'   => $positions,
			'is_template' => true,
		] );
		?>
	</template>
</div>
