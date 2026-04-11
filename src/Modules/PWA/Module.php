<?php

namespace Space\Core\Modules\PWA;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;
use Space\Core\Admin\SettingsAPI;

class Module extends AbstractModule {

    public function get_label(): string {
        return __( 'PWA', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Enable PWA installation: web manifest, service worker, and caching strategy.', 'space-core' );
    }

    public function boot(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
        add_action( 'template_redirect', [ $this, 'handle_routes' ] );
        add_action( 'wp_head', [ $this, 'inject_head_tags' ] );
        add_action( 'wp_footer', [ $this, 'inject_sw_registration' ] );
    }

    public function on_activate(): void {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }

    private function get_options(): array {
        $defaults = [
            'name'             => get_bloginfo( 'name' ),
            'short_name'       => get_bloginfo( 'name' ),
            'theme_color'      => '#ffffff',
            'background_color' => '#ffffff',
            'icon_id'          => 0,
            'cache_strategy'   => 'network-first',
            'offline_url'      => home_url( '/' ),
        ];
        $saved = get_option( 'space_core_pwa', [] );
        if ( ! is_array( $saved ) ) {
            return $defaults;
        }
        return array_merge( $defaults, $saved );
    }

    public function register_settings(): void {
        register_setting( 'space_core_pwa_group', 'space_core_pwa', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_options' ],
            'default'           => [],
        ] );
    }

    public function sanitize_options( mixed $input ): array {
        if ( ! is_array( $input ) ) {
            return [];
        }
        return [
            'name'             => sanitize_text_field( $input['name'] ?? '' ),
            'short_name'       => sanitize_text_field( $input['short_name'] ?? '' ),
            'theme_color'      => sanitize_hex_color( $input['theme_color'] ?? '#ffffff' ) ?: '#ffffff',
            'background_color' => sanitize_hex_color( $input['background_color'] ?? '#ffffff' ) ?: '#ffffff',
            'icon_id'          => absint( $input['icon_id'] ?? 0 ),
            'cache_strategy'   => sanitize_key( $input['cache_strategy'] ?? 'network-first' ),
            'offline_url'      => esc_url_raw( $input['offline_url'] ?? home_url( '/' ) ),
        ];
    }

    public function add_rewrite_rules(): void {
        add_rewrite_rule( '^manifest\.json$', 'index.php?sc_pwa_route=manifest', 'top' );
        add_rewrite_rule( '^sw\.js$', 'index.php?sc_pwa_route=sw', 'top' );
    }

    public function add_query_vars( array $vars ): array {
        $vars[] = 'sc_pwa_route';
        return $vars;
    }

    public function handle_routes(): void {
        $route = get_query_var( 'sc_pwa_route' );
        if ( ! $route ) {
            return;
        }
        $o = $this->get_options();
        if ( 'manifest' === $route ) {
            $this->serve_manifest( $o );
        } elseif ( 'sw' === $route ) {
            $this->serve_service_worker( $o );
        }
    }

    private function serve_manifest( array $o ): void {
        $icons = [];
        if ( ! empty( $o['icon_id'] ) ) {
            foreach ( [ 192, 512 ] as $size ) {
                $src = wp_get_attachment_image_url( $o['icon_id'], [ $size, $size ] );
                if ( $src ) {
                    $icons[] = [
                        'src'   => $src,
                        'sizes' => "{$size}x{$size}",
                        'type'  => 'image/png',
                    ];
                }
            }
        }

        $manifest = [
            'name'             => $o['name'],
            'short_name'       => $o['short_name'],
            'start_url'        => home_url( '/' ),
            'display'          => 'standalone',
            'theme_color'      => $o['theme_color'],
            'background_color' => $o['background_color'],
            'icons'            => $icons,
        ];

        header( 'Content-Type: application/manifest+json; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        echo wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
        exit;
    }

    private function serve_service_worker( array $o ): void {
        $strategy    = sanitize_key( $o['cache_strategy'] );
        $offline_url = esc_js( $o['offline_url'] );
        $cache_name  = 'sc-cache-v' . SPACE_CORE_VERSION;

        header( 'Content-Type: application/javascript; charset=utf-8' );
        header( 'Service-Worker-Allowed: /' );

        $js = $this->get_sw_js( $strategy, $cache_name, $offline_url );
        echo $js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private function get_sw_js( string $strategy, string $cache_name, string $offline_url ): string {
        $cache_name  = esc_js( $cache_name );
        $offline_url = esc_js( $offline_url );

        $strategies = [
            'cache-first' => <<<JS
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;
    event.respondWith(
        caches.match(event.request).then(cached => {
            if (cached) return cached;
            return fetch(event.request).then(response => {
                return caches.open('{$cache_name}').then(cache => {
                    cache.put(event.request, response.clone());
                    return response;
                });
            }).catch(() => caches.match('{$offline_url}'));
        })
    );
});
JS,
            'stale-while-revalidate' => <<<JS
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;
    event.respondWith(
        caches.open('{$cache_name}').then(cache => {
            return cache.match(event.request).then(cached => {
                const fetched = fetch(event.request).then(response => {
                    cache.put(event.request, response.clone());
                    return response;
                }).catch(() => caches.match('{$offline_url}'));
                return cached || fetched;
            });
        })
    );
});
JS,
            // Default: network-first
            'network-first' => <<<JS
self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;
    event.respondWith(
        fetch(event.request).then(response => {
            return caches.open('{$cache_name}').then(cache => {
                cache.put(event.request, response.clone());
                return response;
            });
        }).catch(() => {
            return caches.match(event.request).then(cached => cached || caches.match('{$offline_url}'));
        })
    );
});
JS,
        ];

        $fetch_handler = $strategies[ $strategy ] ?? $strategies['network-first'];

        return <<<JS
const SC_CACHE_NAME = '{$cache_name}';

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(SC_CACHE_NAME).then(cache => cache.add('{$offline_url}'))
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(k => k !== SC_CACHE_NAME).map(k => caches.delete(k))
        ))
    );
    self.clients.claim();
});

{$fetch_handler}
JS;
    }

    public function inject_head_tags(): void {
        $o = $this->get_options();
        echo $this->view( 'front/head/tags', [
            'manifest_url' => home_url( '/manifest.json' ),
            'theme_color'  => $o['theme_color'],
        ] );
    }

    public function inject_sw_registration(): void {
        echo $this->view( 'front/head/sw-registration', [
            'sw_url' => home_url( '/sw.js' ),
        ] );
    }

    public function render_settings(): void {
        $o = $this->get_options();
        $cache_options = [
            'network-first'          => __( 'Network First', 'space-core' ),
            'cache-first'            => __( 'Cache First', 'space-core' ),
            'stale-while-revalidate' => __( 'Stale While Revalidate', 'space-core' ),
        ];

        echo $this->view( 'admin/settings', [
            'options'             => $o,
            'cache_options'       => $cache_options,
            'manifest_url'        => home_url( '/manifest.json' ),
            'service_worker_url'  => home_url( '/sw.js' ),
        ] );
    }
}
