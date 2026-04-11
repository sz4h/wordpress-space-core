<?php

defined( 'ABSPATH' ) || exit;

?>
<div class="sc-view-probe">
	<?php echo esc_html( (string) ( $title ?? '' ) ); ?>
	|
	<?php echo esc_html( $this->get_slug() ); ?>
</div>
