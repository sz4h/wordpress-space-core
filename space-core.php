<?php
/**
 * Plugin Name:       Space Core
 * Plugin URI:        https://sz4h.com
 * Description:       A comprehensive, modular core plugin for WordPress sites by Space Zone.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Ahmed Safaa
 * Author URI:        https://sz4h.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       space-core
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'SPACE_CORE_VERSION', '1.0.0' );
define( 'SPACE_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPACE_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'SPACE_CORE_FILE', __FILE__ );

// Debug helpers (dd, dump) — load before everything else.
require_once __DIR__ . '/helpers/helpers.php';

// Autoloader.
if ( file_exists( SPACE_CORE_DIR . 'vendor/autoload.php' ) ) {
    require_once SPACE_CORE_DIR . 'vendor/autoload.php';
} else {
    // Fallback manual PSR-4 autoloader for development without composer install.
    spl_autoload_register( function ( string $class ): void {
        $prefix = 'Space\\Core\\';
        if ( ! str_starts_with( $class, $prefix ) ) {
            return;
        }
        $relative = str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class, strlen( $prefix ) ) );
        $file = SPACE_CORE_DIR . 'src' . DIRECTORY_SEPARATOR . $relative . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    } );
}

// Activation / deactivation / uninstall hooks.
register_activation_hook( __FILE__, [ 'Space\\Core\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Space\\Core\\Plugin', 'deactivate' ] );
register_uninstall_hook( __FILE__, [ 'Space\\Core\\Plugin', 'uninstall' ] );

// Bootstrap.
add_action( 'plugins_loaded', function (): void {
    Space\Core\Plugin::instance();
} );
