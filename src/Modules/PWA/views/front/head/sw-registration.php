<?php defined( 'ABSPATH' ) || exit; ?>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?php echo esc_js( $sw_url ); ?>', { scope: '/' })
            .catch(function(err) { console.warn('[SpaceCore SW]', err); });
    });
}
</script>
