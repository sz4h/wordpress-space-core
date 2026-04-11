<?php

defined( 'ABSPATH' ) || exit;
?>
<div class="sc-stat-card <?php echo esc_attr( $extra_class ); ?>">
    <div class="sc-stat-number"><?php echo esc_html( (string) $value ); ?></div>
    <div class="sc-stat-label"><?php echo esc_html( $label ); ?></div>
</div>
