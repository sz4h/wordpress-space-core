<?php

defined( 'ABSPATH' ) || exit;

$active = null;
foreach ( $options as $opt ) {
    if ( $opt['selected'] ) {
        $active = $opt;
        break;
    }
}
if ( ! $active && ! empty( $options ) ) {
    $active = $options[0];
}
?>
<div class="sc-currency-switcher"
     data-ajax="<?php echo esc_url( $ajax_url ); ?>"
     data-nonce="<?php echo esc_attr( $nonce ); ?>">

    <?php if ( $active ) : ?>
    <button type="button" class="sc-cs-trigger" aria-haspopup="listbox" aria-expanded="false">
        <?php if ( $active['flag_code'] ) : ?>
            <span class="fi fi-<?php echo esc_attr( $active['flag_code'] ); ?>" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="sc-cs-label"><?php echo esc_html( $active['label'] ); ?></span>
        <span class="sc-cs-arrow" aria-hidden="true">&#9660;</span>
    </button>
    <?php endif; ?>

    <ul class="sc-cs-panel" role="listbox">
        <?php foreach ( $options as $opt ) : ?>
            <li class="sc-cs-option<?php echo $opt['selected'] ? ' sc-cs-active' : ''; ?>"
                role="option"
                data-value="<?php echo esc_attr( $opt['value'] ); ?>"
                aria-selected="<?php echo $opt['selected'] ? 'true' : 'false'; ?>">
                <?php if ( $opt['flag_code'] ) : ?>
                    <span class="fi fi-<?php echo esc_attr( $opt['flag_code'] ); ?>" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="sc-cs-option-label"><?php echo esc_html( $opt['label'] ); ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
