<?php

namespace Space\Core\Modules\Translation;

defined( 'ABSPATH' ) || exit;

class PluginDetector {

	public static function detect(): string {
		if ( function_exists( 'pll_languages_list' ) ) {
			return 'polylang';
		}

		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return 'wpml';
		}

		return 'none';
	}

	/** @throws \RuntimeException When no multilingual plugin is active. */
	public static function adapter(): AdapterInterface {
		return match ( self::detect() ) {
			'polylang' => new PolylangAdapter(),
			'wpml'     => new WpmlAdapter(),
			default    => throw new \RuntimeException( __( 'No multilingual plugin is active.', 'space-core' ) ),
		};
	}
}
