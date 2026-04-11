<?php

defined( 'ABSPATH' ) || exit;

use Space\Core\Admin\SettingsAPI;
?>
<div style="max-width:700px;">
    <table class="form-table">
        <tr>
            <th><?php esc_html_e( 'Rate API URL', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::url( 'space_core_multi_currency_group', 'space_core_multi_currency', 'rate_api_url', $config['rate_api_url'] ?? '', 'https://api.exchangeratesapi.io/v1/latest', [ 'id' => 'sc-mc-api-url' ] ); ?>
                <p class="description"><?php esc_html_e( 'API must return JSON with a "rates" object. Leave blank to disable auto-fetch.', 'space-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Rate API Key', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::text( 'space_core_multi_currency_group', 'space_core_multi_currency', 'rate_api_key', $config['rate_api_key'] ?? '', __( 'Your API key', 'space-core' ), [ 'id' => 'sc-mc-api-key' ] ); ?>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'API Base Currency', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::text( 'space_core_multi_currency_group', 'space_core_multi_currency', 'rate_api_base_currency', $config['rate_api_base_currency'] ?? 'USD', 'USD', [ 'id' => 'sc-mc-api-base', 'maxlength' => '3', 'style' => 'text-transform:uppercase;width:60px;' ] ); ?>
                <p class="description"><?php esc_html_e( 'The base currency the API rates are relative to. Used for cross-rate calculation.', 'space-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Sync Frequency', 'space-core' ); ?></th>
            <td>
                <?php SettingsAPI::select( 'space_core_multi_currency', 'rate_cron_period', $config['rate_cron_period'] ?? 'daily', $periods, [ 'id' => 'sc-mc-cron-period' ] ); ?>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Switcher Defaults', 'space-core' ); ?></th>
            <td>
                <span style="margin-right:16px;"><?php SettingsAPI::checkbox( 'space_core_multi_currency', 'shortcode_show_flag', $config['shortcode_show_flag'] ?? 0, __( 'Flag', 'space-core' ), [ 'id' => 'sc-mc-show-flag' ] ); ?></span>
                <span style="margin-right:16px;"><?php SettingsAPI::checkbox( 'space_core_multi_currency', 'shortcode_show_code', $config['shortcode_show_code'] ?? 0, __( 'Code', 'space-core' ), [ 'id' => 'sc-mc-show-code' ] ); ?></span>
                <span style="margin-right:16px;"><?php SettingsAPI::checkbox( 'space_core_multi_currency', 'shortcode_show_name', $config['shortcode_show_name'] ?? 0, __( 'Name', 'space-core' ), [ 'id' => 'sc-mc-show-name' ] ); ?></span>
                <span><?php SettingsAPI::checkbox( 'space_core_multi_currency', 'shortcode_show_symbol', $config['shortcode_show_symbol'] ?? 0, __( 'Symbol', 'space-core' ), [ 'id' => 'sc-mc-show-symbol' ] ); ?></span>
                <p class="description">
                    <?php esc_html_e( 'Default display options for the [sc_currency_switcher] shortcode. Can be overridden per-shortcode.', 'space-core' ); ?>
                </p>
            </td>
        </tr>
    </table>

    <p>
        <button type="button" id="sc-mc-save-settings" class="button button-primary">
            <?php esc_html_e( 'Save Settings', 'space-core' ); ?>
        </button>
        <span id="sc-mc-settings-msg" style="margin-left:10px;font-weight:600;"></span>
    </p>

    <hr>
    <h3><?php esc_html_e( 'Shortcode', 'space-core' ); ?></h3>
    <p>
        <code>[sc_currency_switcher]</code> —
        <?php esc_html_e( 'Displays a currency selector dropdown. Optional parameters:', 'space-core' ); ?>
        <code>show_flag="1"</code>, <code>show_code="1"</code>, <code>show_name="1"</code>, <code>show_symbol="1"</code>.
    </p>
    <p class="description">
        <?php esc_html_e( 'Example:', 'space-core' ); ?>
        <code>[sc_currency_switcher show_flag="1" show_code="1" show_name="0" show_symbol="0"]</code>
    </p>
</div>
