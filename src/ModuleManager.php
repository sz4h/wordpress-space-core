<?php

namespace Space\Core;

defined( 'ABSPATH' ) || exit;

use Space\Core\Contracts\ModuleInterface;

/**
 * Registers and boots all feature modules.
 */
class ModuleManager {

	/** @var ModuleInterface[] */
	private array $modules = [];

	/** @var array<string, string> slug => FQCN */
	private array $registry = [
		'custom_post_types'   => Modules\CustomPostTypes\Module::class,
		'custom_taxonomies'   => Modules\CustomTaxonomies\Module::class,
		'custom_fields'       => Modules\CustomFields\Module::class,
		'woo_checkout_fields' => Modules\WooCheckoutFields\Module::class,
		'safe_svg'            => Modules\SafeSVG\Module::class,
		'whatsapp_float'      => Modules\WhatsAppFloat\Module::class,
		'pwa'                 => Modules\PWA\Module::class,
		'custom_code'         => Modules\CustomCode\Module::class,
		'stock_notifier'      => Modules\StockNotifier\Module::class,
		'admin_menu'          => Modules\AdminMenu\Module::class,
		'admin_widgets'       => Modules\AdminWidgets\Module::class,
		'local_shipping'      => Modules\LocalShipping\Module::class,
		'main_config'         => Modules\MainConfig\Module::class,
		'gift_wrap'           => Modules\GiftWrap\Module::class,
		'admin_nav'           => Modules\AdminNav\Module::class,
		'order_statuses'      => Modules\OrderStatuses\Module::class,
		'stats'               => Modules\Stats\Module::class,
		'guest_orders'        => Modules\GuestOrders\Module::class,
		'store_notices'       => Modules\StoreNotices\Module::class,
		'print_orders'        => Modules\PrintOrders\Module::class,
		'multi_currency'      => Modules\MultiCurrency\Module::class,
	];

	public function init(): void {
		// Always load the admin menu regardless of module toggles.
		( new Admin\AdminMenu( $this ) )->init();

		$enabled = $this->enabled_slugs();

		foreach ( $this->registry as $slug => $class ) {
			$module                 = new $class( $slug );
			$this->modules[ $slug ] = $module;

			if ( in_array( $slug, $enabled, true ) ) {
				$module->boot();
			}
		}
	}

	/** Return slugs of currently enabled modules. */
	public function enabled_slugs(): array {
		$option = get_option( 'space_core_modules', [] );
		if ( ! is_array( $option ) ) {
			return [];
		}

		return array_keys( array_filter( $option ) );
	}

	/** Return all module instances (instantiated but not necessarily booted). */
	public function all_modules(): array {
		if ( empty( $this->modules ) ) {
			foreach ( $this->registry as $slug => $class ) {
				$this->modules[ $slug ] = new $class( $slug );
			}
		}

		return $this->modules;
	}

	public function registry(): array {
		return $this->registry;
	}

	public function is_enabled( string $slug ): bool {
		return in_array( $slug, $this->enabled_slugs(), true );
	}
}
