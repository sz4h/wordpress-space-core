<?php

namespace Space\Core\Abstracts;

defined( 'ABSPATH' ) || exit;

use ReflectionClass;
use Space\Core\Contracts\ModuleInterface;
use Throwable;

abstract class AbstractModule implements ModuleInterface {

	public function __construct( protected string $slug ) {
	}

	/** Override in a subclass to run on plugin activation. */
	public function on_activate(): void {
	}

	public function view( string $view, array $data = [] ): string {
		$normalized_view = $this->normalize_view_name( $view );

		if ( '' === $normalized_view ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf( 'Invalid view "%s" requested for module "%s".', $view, $this->get_slug() ),
				defined( 'SPACE_CORE_VERSION' ) ? SPACE_CORE_VERSION : '1.0.0'
			);

			return '';
		}

		$file = $this->locate_view_file( $normalized_view );

		if ( '' === $file ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf( 'View "%s" was not found for module "%s".', $view, $this->get_slug() ),
				defined( 'SPACE_CORE_VERSION' ) ? SPACE_CORE_VERSION : '1.0.0'
			);

			return '';
		}

		try {
			return $this->render_view_file( $file, $data );
		} catch ( Throwable $e ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf( 'View "%s" was not found for module "%s".', $view, $this->get_slug() ) . ", Render View Error: {$e->getMessage()}",
				defined( 'SPACE_CORE_VERSION' ) ? SPACE_CORE_VERSION : '1.0.0'
			);

			return '';
		}
	}

	private function normalize_view_name( string $view ): string {
		$view = trim( str_replace( '\\', '/', $view ) );

		if ( '' === $view || str_starts_with( $view, '/' ) || preg_match( '/^[A-Za-z]:\//', $view ) || str_contains( $view, '://' ) || str_contains( $view, '..' ) ) {
			return '';
		}

		$raw_segments = explode( '/', $view );

		foreach ( $raw_segments as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment || str_starts_with( $segment, '.' ) ) {
				return '';
			}
		}

		$view = preg_replace( '/\.php$/i', '', $view );
		$view = str_replace( '.', '/', $view );
		$view = preg_replace( '#/+#', '/', $view );
		$view = trim( $view, '/' );

		if ( '' === $view ) {
			return '';
		}

		$segments = explode( '/', $view );

		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return '';
			}
		}

		return $view . '.php';
	}

	public function get_slug(): string {
		return $this->slug;
	}

	private function locate_view_file( string $view ): string {
		$theme_template = locate_template( 'space-core/' . $this->get_slug() . '/' . $view, false, false );

		if ( is_string( $theme_template ) && '' !== $theme_template ) {
			return $theme_template;
		}

		$module_view_dir = $this->module_view_directory();

		if ( '' === $module_view_dir ) {
			return '';
		}

		$module_template = $module_view_dir . '/' . $view;

		return is_readable( $module_template ) ? $module_template : '';
	}

	private function module_view_directory(): string {
		$reflection = new ReflectionClass( $this );
		$file       = $reflection->getFileName();

		if ( ! is_string( $file ) || '' === $file ) {
			return '';
		}

		return dirname( $file ) . '/views';
	}

	private function render_view_file( string $file, array $data ): string {
		$__buffer_level = ob_get_level();
		$__output       = '';

		ob_start();

		try {
			extract( $data, EXTR_SKIP );
			include $file;

			while ( ob_get_level() > $__buffer_level ) {
				$__output = ob_get_clean() . $__output;
			}

			return $__output;
		} catch ( Throwable $exception ) {
			while ( ob_get_level() > $__buffer_level ) {
				ob_end_clean();
			}

			throw $exception;
		}
	}
}
