<?php

defined( 'ABSPATH' ) || exit;

$id         = $currency['id'] ?? '';
$code       = esc_attr( $currency['currency_code'] ?? '' );
$name       = $currency['name'] ?? '{}';
$name_arr   = \Space\Core\Modules\MultiCurrency\CurrencyDB::decode_json( $name );
$name_en    = esc_attr( $name_arr['en'] ?? '' );
$name_ar    = esc_attr( $name_arr['ar'] ?? '' );
$symbol     = $currency['symbol'] ?? '{}';
$symbol_arr = \Space\Core\Modules\MultiCurrency\CurrencyDB::decode_json( $symbol );
$symbol_en  = esc_attr( $symbol_arr['en'] ?? '' );
$symbol_ar  = esc_attr( $symbol_arr['ar'] ?? '' );
$rate       = isset( $currency['rate'] ) ? (float) $currency['rate'] : 1;
$modifier   = isset( $currency['rate_modifier'] ) ? (float) $currency['rate_modifier'] : 0;
$effective  = round( $rate + $modifier, 6 );
$decimals   = $currency['decimal_digits'] ?? 2;
$position   = (int) ( $currency['currency_position'] ?? \Space\Core\Modules\MultiCurrency\CurrencyPosition::AfterSpace->value );
$is_default = ! empty( $currency['is_default'] );
$is_active  = isset( $currency['is_active'] ) ? (int) $currency['is_active'] : 1;
$countries  = ! empty( $currency['country_codes'] ) ? implode( ', ', json_decode( $currency['country_codes'], true ) ?? [] ) : '';
$gateways   = ! empty( $currency['payment_gateways'] ) ? implode( ', ', json_decode( $currency['payment_gateways'], true ) ?? [] ) : '';

?>
<tr data-id="<?php echo esc_attr( (string) $id ); ?>" class="sc-mc-row<?php echo $is_default ? ' sc-mc-default-row' : ''; ?>">
	<td class="sc-mc-sort-handle" style="cursor:move;text-align:center;">⠿</td>
	<td><input type="text" class="sc-mc-field small-text" name="currency_code" value="<?php echo $code; ?>" placeholder="USD" maxlength="3" style="text-transform:uppercase;width:50px;"></td>
	<td>
		<input type="text" class="sc-mc-field" name="name_en" value="<?php echo $name_en; ?>" placeholder="<?php esc_attr_e( 'English', 'space-core' ); ?>" style="width:110px;">
		<input type="text" class="sc-mc-field" name="name_ar" value="<?php echo $name_ar; ?>" placeholder="<?php esc_attr_e( 'Arabic', 'space-core' ); ?>" dir="rtl" style="width:110px;margin-top:2px;">
	</td>
	<td>
		<input type="text" class="sc-mc-field small-text" name="symbol_en" value="<?php echo $symbol_en; ?>" placeholder="$" style="width:50px;">
		<input type="text" class="sc-mc-field small-text" name="symbol_ar" value="<?php echo $symbol_ar; ?>" placeholder="＄" dir="rtl" style="width:50px;margin-top:2px;">
	</td>
	<td><input type="number" class="sc-mc-field sc-mc-rate small-text" name="rate" value="<?php echo esc_attr( (string) $rate ); ?>" step="0.000001" min="0" style="width:80px;"></td>
	<td><input type="number" class="sc-mc-field sc-mc-modifier small-text" name="rate_modifier" value="<?php echo esc_attr( (string) $modifier ); ?>" step="0.000001" style="width:80px;"></td>
	<td class="sc-mc-effective" style="font-weight:600;"><?php echo esc_html( number_format( $effective, 4 ) ); ?></td>
	<td>
		<select class="sc-mc-field" name="decimal_digits" style="width:55px;">
			<?php foreach ( [ 0, 2, 3 ] as $decimal_option ) : ?>
				<option value="<?php echo esc_attr( (string) $decimal_option ); ?>" <?php selected( (int) $decimals, $decimal_option ); ?>><?php echo esc_html( (string) $decimal_option ); ?></option>
			<?php endforeach; ?>
		</select>
	</td>
	<td>
		<select class="sc-mc-field" name="currency_position" style="width:130px;">
			<?php foreach ( $positions as $value => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $position, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</td>
	<td><input type="text" class="sc-mc-field" name="country_codes" value="<?php echo esc_attr( $countries ); ?>" placeholder="KW, SA" style="width:80px;" title="<?php esc_attr_e( 'Comma-separated ISO2 codes', 'space-core' ); ?>"></td>
	<td><input type="text" class="sc-mc-field" name="payment_gateways" value="<?php echo esc_attr( $gateways ); ?>" placeholder="stripe" style="width:80px;" title="<?php esc_attr_e( 'Comma-separated gateway IDs', 'space-core' ); ?>"></td>
	<td style="text-align:center;">
		<?php if ( $is_default ) : ?>
			<span class="sc-mc-default-badge" title="<?php esc_attr_e( 'Default', 'space-core' ); ?>">★</span>
		<?php else : ?>
			<button type="button" class="button sc-mc-set-default-btn" data-id="<?php echo esc_attr( (string) $id ); ?>" title="<?php esc_attr_e( 'Set as default', 'space-core' ); ?>">☆</button>
		<?php endif; ?>
	</td>
	<td style="text-align:center;">
		<select class="sc-mc-field" name="is_active" style="width:70px;">
			<option value="1" <?php selected( $is_active, 1 ); ?>><?php esc_html_e( 'Yes', 'space-core' ); ?></option>
			<option value="0" <?php selected( $is_active, 0 ); ?>><?php esc_html_e( 'No', 'space-core' ); ?></option>
		</select>
	</td>
	<td style="white-space:nowrap;">
		<button type="button" class="button button-primary sc-mc-save-btn" data-id="<?php echo esc_attr( (string) $id ); ?>">
			<?php esc_html_e( 'Save', 'space-core' ); ?>
		</button>
		<?php if ( ! $is_default ) : ?>
			<button type="button" class="button sc-mc-delete-btn" data-id="<?php echo esc_attr( (string) $id ); ?>" style="margin-left:4px;">
				<?php esc_html_e( 'Delete', 'space-core' ); ?>
			</button>
		<?php endif; ?>
	</td>
</tr>
