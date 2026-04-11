<?php

namespace Space\Core\Abstracts;

defined( 'ABSPATH' ) || exit;

use Space\Core\Contracts\ModuleInterface;

abstract class AbstractModule implements ModuleInterface {

	public function __construct( protected string $slug ) {
	}

	public function get_slug(): string {
		return $this->slug;
	}

	/** Override in subclass to run on plugin activation. */
	public function on_activate(): void {
	}


	public function view( string $file, array $data = [], bool $return = false ): string|null {
		extract( $data );
		$file = dirname( self::class ) . '/views/' . $file;
		if ( $return ) {
			ob_start();
			include $file;

			return ob_get_clean();
		} else {
			include $file;

			return null;
		}
	}
}
