<?php

namespace Space\Core\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Helper for rendering common settings fields.
 */
class SettingsAPI {

	/** @var array<string,string> */
	private static array $view_path_cache = [];

	/**
	 * Render a text input field.
	 */
	public static function text( string $option_group, string $option_name, string $field_name, mixed $value, string $placeholder = '', array $attributes = [] ): void {
		self::render( 'fields/text', [
			'option_name' => $option_name,
			'field_name'  => $field_name,
			'value'       => $value,
			'placeholder' => $placeholder,
			'attrs'       => self::stringify_attributes( $attributes ),
		] );
	}

	private static function render( string $view, array $data = [] ): void {
		$file = self::view_path( $view );

		if ( '' === $file || ! is_readable( $file ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf( 'SettingsAPI view "%s" was not found.', $view ),
				defined( 'SPACE_CORE_VERSION' ) ? SPACE_CORE_VERSION : '1.0.0'
			);

			return;
		}

		extract( $data, EXTR_SKIP );
		include $file;
	}

	private static function view_path( string $view ): string {
		if ( isset( self::$view_path_cache[ $view ] ) ) {
			return self::$view_path_cache[ $view ];
		}

		$path                           = __DIR__ . '/views/' . trim( str_replace( '\\', '/', $view ), '/' ) . '.php';
		self::$view_path_cache[ $view ] = $path;

		return $path;
	}

	private static function stringify_attributes( array $attributes ): string {
		$html = '';

		foreach ( $attributes as $name => $value ) {
			if ( '' === (string) $name || null === $value || false === $value ) {
				continue;
			}

			if ( true === $value ) {
				$html .= ' ' . esc_attr( (string) $name );
				continue;
			}

			$html .= sprintf(
				' %s="%s"',
				esc_attr( (string) $name ),
				esc_attr( (string) $value )
			);
		}

		return $html;
	}

	/**
	 * Render a textarea field.
	 */
	public static function textarea( string $option_name, string $field_name, mixed $value, int $rows = 6, array $attributes = [] ): void {
		self::render( 'fields/textarea', [
			'option_name' => $option_name,
			'field_name'  => $field_name,
			'value'       => $value,
			'rows'        => $rows,
			'attrs'       => self::stringify_attributes( $attributes ),
		] );
	}

	/**
	 * Render a select dropdown.
	 */
	public static function select( string $option_name, string $field_name, mixed $value, array $options, array $attributes = [] ): void {
		self::render( 'fields/select', [
			'option_name' => $option_name,
			'field_name'  => $field_name,
			'value'       => $value,
			'options'     => $options,
			'attrs'       => self::stringify_attributes( $attributes ),
		] );
	}

	/**
	 * Render a checkbox toggle.
	 */
	public static function checkbox( string $option_name, string $field_name, mixed $value, string $label = '', array $attributes = [] ): void {
		self::render( 'fields/checkbox', [
			'option_name' => $option_name,
			'field_name'  => $field_name,
			'value'       => $value,
			'label'       => $label,
			'attrs'       => self::stringify_attributes( $attributes ),
		] );
	}

	/**
	 * Render a color picker input.
	 */
	public static function color( string $option_name, string $field_name, mixed $value, array|string $attributes = [] ): void {
		if ( is_string( $attributes ) ) {
			$attributes = (array) $attributes;
		}
		self::render( 'fields/color', [
			'option_name' => $option_name,
			'field_name'  => $field_name,
			'value'       => $value,
			'attrs'       => self::stringify_attributes( $attributes ),
		] );
	}

	/**
	 * Render a number input.
	 */
	public static function number( string $option_name, string $field_name, mixed $value, int $min = 0, int $max = 9999, array $attributes = [] ): void {
		self::render( 'fields/number', [
			'option_name' => $option_name,
			'field_name'  => $field_name,
			'value'       => $value,
			'min'         => $min,
			'max'         => $max,
			'attrs'       => self::stringify_attributes( $attributes ),
		] );
	}

	public static function url( string $option_group, string $option_name, string $field_name, mixed $value, string $placeholder = '', array $attributes = [] ): void {
		self::render( 'fields/url', [
			'option_name' => $option_name,
			'field_name'  => $field_name,
			'value'       => $value,
			'placeholder' => $placeholder,
			'attrs'       => self::stringify_attributes( $attributes ),
		] );
	}

	public static function hidden( string $option_name, string $field_name, mixed $value, array $attributes = [] ): void {
		self::render( 'fields/hidden', [
			'option_name' => $option_name,
			'field_name'  => $field_name,
			'value'       => $value,
			'attrs'       => self::stringify_attributes( $attributes ),
		] );
	}

	public static function multiselect( string $option_name, string $field_name, array $value, array $options, int $size = 4, array $attributes = [] ): void {
		self::render( 'fields/multiselect', [
			'option_name' => $option_name,
			'field_name'  => $field_name,
			'value'       => $value,
			'options'     => $options,
			'size'        => $size,
			'attrs'       => self::stringify_attributes( $attributes ),
		] );
	}

	/**
	 * Wrap a settings form with the standard WP form + submit button.
	 */
	public static function open_form( string $option_group ): void {
		self::render( 'form/open', [
			'option_group' => $option_group,
		] );
	}

	public static function close_form( string $submit_label = '' ): void {
		self::render( 'form/close', [
			'submit_label' => $submit_label,
		] );
	}
}
