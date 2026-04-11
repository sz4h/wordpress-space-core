<?php defined( 'ABSPATH' ) || exit; ?>
<div class="sc-seeder-wrap" style="max-width:600px;">
    <h2><?php esc_html_e( 'Seed Delivery Data', 'space-core' ); ?></h2>
    <p><?php esc_html_e( 'Quickly populate cities and areas with pre-built data for a country. This will add new entries without removing existing ones.', 'space-core' ); ?></p>

    <?php if ( $existing_cities > 0 ) : ?>
        <div class="notice notice-warning inline" style="margin:0 0 16px;">
            <p><?php printf( esc_html__( 'You already have %d city(ies) in the database. Running the seeder will add new entries on top — it will not overwrite existing data.', 'space-core' ), $existing_cities ); ?></p>
        </div>
    <?php endif; ?>

    <table class="form-table" style="max-width:500px;">
        <tr>
            <th scope="row"><label for="sc-seeder-country"><?php esc_html_e( 'Country', 'space-core' ); ?></label></th>
            <td>
                <select id="sc-seeder-country" style="min-width:220px;">
                    <?php foreach ( $countries as $code => $label ) : ?>
                        <option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>

    <p>
        <button type="button" id="sc-run-seeder" class="button button-primary" style="height:36px;line-height:34px;"><?php esc_html_e( 'Run Seeder', 'space-core' ); ?></button>
        <span id="sc-seeder-status" style="margin-left:12px;font-weight:600;"></span>
    </p>

    <div id="sc-seeder-result" style="display:none;margin-top:12px;padding:12px 16px;background:#f0f8f0;border:1px solid #b7dfb7;border-radius:4px;"></div>
</div>

<script>
jQuery(function ($) {
    $('#sc-run-seeder').on('click', function () {
        var $btn = $(this);
        var $status = $('#sc-seeder-status');
        var $result = $('#sc-seeder-result');
        var country = $('#sc-seeder-country').val();

        if (!confirm('<?php echo esc_js( __( 'Run the seeder for the selected country? This will add new cities and areas.', 'space-core' ) ); ?>')) return;

        $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Running…', 'space-core' ) ); ?>');
        $status.text('').css('color', '#888');
        $result.hide();

        $.post(scAdmin.ajaxUrl, { action: 'sc_run_ls_seeder', nonce: '<?php echo esc_js( $nonce ); ?>', country: country }, function (res) {
            $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Run Seeder', 'space-core' ) ); ?>');
            if (res.success) {
                $status.text('<?php echo esc_js( __( 'Done!', 'space-core' ) ); ?>').css('color', '#2e7d32');
                $result.html('<strong>' + res.data.message + '</strong>' + '<br><?php echo esc_js( __( 'Go to the', 'space-core' ) ); ?> ' + '<a href="<?php echo esc_url( admin_url( 'admin.php?page=sc-local-shipping&tab=cities' ) ); ?>"><?php echo esc_js( __( 'Cities tab', 'space-core' ) ); ?></a> ' + '<?php echo esc_js( __( 'or', 'space-core' ) ); ?> ' + '<a href="<?php echo esc_url( admin_url( 'admin.php?page=sc-local-shipping&tab=areas' ) ); ?>"><?php echo esc_js( __( 'Areas tab', 'space-core' ) ); ?></a> ' + '<?php echo esc_js( __( 'to review and adjust prices.', 'space-core' ) ); ?>').show();
            } else {
                $status.text((res.data && res.data.message) || '<?php echo esc_js( __( 'Error.', 'space-core' ) ); ?>').css('color', '#c62828');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Run Seeder', 'space-core' ) ); ?>');
            $status.text('<?php echo esc_js( __( 'Server error.', 'space-core' ) ); ?>').css('color', '#c62828');
        });
    });
});
</script>
