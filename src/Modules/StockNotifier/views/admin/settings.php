<?php

defined( 'ABSPATH' ) || exit;

use Space\Core\Admin\SettingsAPI;

SettingsAPI::open_form( 'space_core_sn_group' );
?>
<h2><?php esc_html_e( 'Stock Notifier', 'space-core' ); ?></h2>
<p><?php esc_html_e( 'Template tags: {product_name}, {product_url}, {site_name}', 'space-core' ); ?></p>

<h3><?php esc_html_e( 'General', 'space-core' ); ?></h3>
<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e( 'Collect Email', 'space-core' ); ?></th>
        <td><?php SettingsAPI::checkbox( 'space_core_stock_notifier', 'collect_email', $options['collect_email'], __( 'Allow email subscriptions', 'space-core' ) ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Collect Phone', 'space-core' ); ?></th>
        <td><?php SettingsAPI::checkbox( 'space_core_stock_notifier', 'collect_phone', $options['collect_phone'], __( 'Allow phone/WhatsApp subscriptions', 'space-core' ) ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Form Position', 'space-core' ); ?></th>
        <td><?php SettingsAPI::select( 'space_core_stock_notifier', 'form_position', $options['form_position'], $position_options ); ?></td>
    </tr>
</table>

<h3><?php esc_html_e( 'Email Channel', 'space-core' ); ?></h3>
<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e( 'Subject', 'space-core' ); ?></th>
        <td><?php SettingsAPI::text( 'space_core_sn_group', 'space_core_stock_notifier', 'email_subject', $options['email_subject'] ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Body', 'space-core' ); ?></th>
        <td><?php SettingsAPI::textarea( 'space_core_stock_notifier', 'email_body', $options['email_body'], 6 ); ?></td>
    </tr>
</table>

<h3><?php esc_html_e( 'SMS Channel (SMSBox.com)', 'space-core' ); ?></h3>
<p class="description">
    <?php esc_html_e( 'Uses the SMSBox HTTP API. Credentials are available in your SMSBox account.', 'space-core' ); ?>
</p>
<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e( 'Username', 'space-core' ); ?></th>
        <td><?php SettingsAPI::text( 'space_core_sn_group', 'space_core_stock_notifier', 'sms_username', $options['sms_username'] ?? '' ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Password', 'space-core' ); ?></th>
        <td><?php SettingsAPI::text( 'space_core_sn_group', 'space_core_stock_notifier', 'sms_password', $options['sms_password'] ?? '' ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Customer ID', 'space-core' ); ?></th>
        <td><?php SettingsAPI::text( 'space_core_sn_group', 'space_core_stock_notifier', 'sms_customer_id', $options['sms_customer_id'] ?? '' ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Sender Text', 'space-core' ); ?></th>
        <td><?php SettingsAPI::text( 'space_core_sn_group', 'space_core_stock_notifier', 'sms_sender', $options['sms_sender'] ); ?>
        <p class="description"><?php esc_html_e( 'The sender name shown on the recipient\'s phone (e.g. MyStore).', 'space-core' ); ?></p></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Message Body', 'space-core' ); ?></th>
        <td><?php SettingsAPI::textarea( 'space_core_stock_notifier', 'sms_body', $options['sms_body'], 4 ); ?></td>
    </tr>
</table>

<h3><?php esc_html_e( 'WhatsApp Channel (Evolution API)', 'space-core' ); ?></h3>
<table class="form-table" role="presentation">
    <tr>
        <th><?php esc_html_e( 'API Base URL', 'space-core' ); ?></th>
        <td><?php SettingsAPI::text( 'space_core_sn_group', 'space_core_stock_notifier', 'wa_evolution_url', $options['wa_evolution_url'], 'https://api.example.com/' ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'API Key', 'space-core' ); ?></th>
        <td><?php SettingsAPI::text( 'space_core_sn_group', 'space_core_stock_notifier', 'wa_evolution_key', $options['wa_evolution_key'] ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Instance Name', 'space-core' ); ?></th>
        <td><?php SettingsAPI::text( 'space_core_sn_group', 'space_core_stock_notifier', 'wa_evolution_instance', $options['wa_evolution_instance'] ); ?></td>
    </tr>
    <tr>
        <th><?php esc_html_e( 'Message Body', 'space-core' ); ?></th>
        <td><?php SettingsAPI::textarea( 'space_core_stock_notifier', 'wa_body', $options['wa_body'], 4 ); ?></td>
    </tr>
</table>
<?php
SettingsAPI::close_form();
?>

<hr style="margin:32px 0;" />

<div class="sc-module-section">
    <h2><?php esc_html_e( 'Per-Language Templates', 'space-core' ); ?></h2>
    <p>
        <?php esc_html_e( 'Override the default subject/body for specific subscriber languages. The language code is stored when the customer subscribes (e.g. ar, fr, de).', 'space-core' ); ?>
        <br />
        <?php esc_html_e( 'Template tags: {product_name}, {product_url}, {site_name}', 'space-core' ); ?>
        <br />
        <?php esc_html_e( 'Leave a field empty to fall back to the default template above.', 'space-core' ); ?>
    </p>

    <div class="sc-table-wrap">
        <table class="widefat striped sc-ajax-table sc-responsive-table" id="sc-lang-tpl-table">
            <thead>
                <tr>
                    <th style="width:80px;"><?php esc_html_e( 'Lang Code', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Email Subject', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Email Body', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'SMS Body', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'WhatsApp Body', 'space-core' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'space-core' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $lang_templates as $i => $tpl ) : ?>
                <tr class="sc-table-row" data-index="<?php echo esc_attr( (string) $i ); ?>">
                    <td data-label="<?php esc_attr_e( 'Lang', 'space-core' ); ?>">
                        <input type="text" class="sc-field" data-field="lang" value="<?php echo esc_attr( $tpl['lang'] ); ?>" placeholder="ar" style="width:60px;" />
                    </td>
                    <td data-label="<?php esc_attr_e( 'Email Subject', 'space-core' ); ?>">
                        <input type="text" class="sc-field" data-field="email_subject" value="<?php echo esc_attr( $tpl['email_subject'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Default', 'space-core' ); ?>" />
                    </td>
                    <td data-label="<?php esc_attr_e( 'Email Body', 'space-core' ); ?>">
                        <textarea class="sc-field" data-field="email_body" rows="3" placeholder="<?php esc_attr_e( 'Default', 'space-core' ); ?>"><?php echo esc_textarea( $tpl['email_body'] ?? '' ); ?></textarea>
                    </td>
                    <td data-label="<?php esc_attr_e( 'SMS Body', 'space-core' ); ?>">
                        <textarea class="sc-field" data-field="sms_body" rows="3" placeholder="<?php esc_attr_e( 'Default', 'space-core' ); ?>"><?php echo esc_textarea( $tpl['sms_body'] ?? '' ); ?></textarea>
                    </td>
                    <td data-label="<?php esc_attr_e( 'WhatsApp Body', 'space-core' ); ?>">
                        <textarea class="sc-field" data-field="wa_body" rows="3" placeholder="<?php esc_attr_e( 'Default', 'space-core' ); ?>"><?php echo esc_textarea( $tpl['wa_body'] ?? '' ); ?></textarea>
                    </td>
                    <td class="sc-row-actions">
                        <button type="button" class="button button-small sc-delete-row"
                                data-action="sc_delete_lang_template"
                                data-nonce="<?php echo esc_attr( $lang_nonce ); ?>"
                                data-lang="<?php echo esc_attr( $tpl['lang'] ); ?>">
                            <?php esc_html_e( 'Delete', 'space-core' ); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="sc-table-footer">
            <button type="button" class="button sc-add-row" data-table="sc-lang-tpl-table" data-template="sc-lang-tpl-row-tpl">
                + <?php esc_html_e( 'Add Language', 'space-core' ); ?>
            </button>
            <button type="button" class="button button-primary sc-save-table"
                    data-table="sc-lang-tpl-table"
                    data-action="sc_save_lang_templates"
                    data-nonce="<?php echo esc_attr( $lang_nonce ); ?>">
                <?php esc_html_e( 'Save Templates', 'space-core' ); ?>
            </button>
            <span class="sc-save-status"></span>
        </div>
    </div>
</div>

<script type="text/html" id="sc-lang-tpl-row-tpl">
<tr class="sc-table-row sc-new-row" data-index="__INDEX__">
    <td data-label="<?php esc_attr_e( 'Lang', 'space-core' ); ?>">
        <input type="text" class="sc-field" data-field="lang" value="" placeholder="ar" style="width:60px;" />
    </td>
    <td data-label="<?php esc_attr_e( 'Email Subject', 'space-core' ); ?>">
        <input type="text" class="sc-field" data-field="email_subject" value="" placeholder="<?php esc_attr_e( 'Default', 'space-core' ); ?>" />
    </td>
    <td data-label="<?php esc_attr_e( 'Email Body', 'space-core' ); ?>">
        <textarea class="sc-field" data-field="email_body" rows="3" placeholder="<?php esc_attr_e( 'Default', 'space-core' ); ?>"></textarea>
    </td>
    <td data-label="<?php esc_attr_e( 'SMS Body', 'space-core' ); ?>">
        <textarea class="sc-field" data-field="sms_body" rows="3" placeholder="<?php esc_attr_e( 'Default', 'space-core' ); ?>"></textarea>
    </td>
    <td data-label="<?php esc_attr_e( 'WhatsApp Body', 'space-core' ); ?>">
        <textarea class="sc-field" data-field="wa_body" rows="3" placeholder="<?php esc_attr_e( 'Default', 'space-core' ); ?>"></textarea>
    </td>
    <td class="sc-row-actions">
        <button type="button" class="button button-small sc-remove-new-row"><?php esc_html_e( 'Remove', 'space-core' ); ?></button>
    </td>
</tr>
</script>
