<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width">
    <title><?php esc_html_e( 'Print Orders', 'space-core' ); ?></title>
    <?php wp_print_styles( [ 'space-core-admin-print' ] ); ?>
</head>
<body class="sc-print-body sc-print-format-<?php echo esc_attr( $format ); ?>" onload="window.print()">
<?php $last = count( $orders ) - 1; ?>
<?php foreach ( $orders as $i => $order ) : ?>
    <?php if ( $is_thermal ) : ?>
        <?php $this->render_thermal( $order, $i === $last ); ?>
    <?php else : ?>
        <?php $this->render_a4( $order, $i === $last ); ?>
    <?php endif; ?>
<?php endforeach; ?>
</body>
</html>
