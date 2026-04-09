<?php

namespace Space\Core\Modules\StockNotifier\Channels;

defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp channel via Evolution API.
 *
 * POST {base_url}/message/sendText/{instance}
 * Header: apikey
 * Body:   { number, text }
 * Success: response JSON contains status === "PENDING"
 */
class WhatsappChannel {

    private array $opts;

    public function __construct( array $opts ) {
        $this->opts = $opts;
    }

    private function resolve_template( string $lang ): array {
        foreach ( (array) ( $this->opts['lang_templates'] ?? [] ) as $tpl ) {
            if ( isset( $tpl['lang'] ) && $tpl['lang'] === $lang ) {
                return $tpl;
            }
        }
        return [];
    }

    public function send( string $contact, int $product_id, string $lang ): bool {
        $base_url = trailingslashit( esc_url_raw( $this->opts['wa_evolution_url'] ?? '' ) );
        $api_key  = sanitize_text_field( $this->opts['wa_evolution_key'] ?? '' );
        $instance = sanitize_text_field( $this->opts['wa_evolution_instance'] ?? '' );

        if ( empty( $base_url ) || empty( $api_key ) || empty( $instance ) || empty( $contact ) ) {
            return false;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return false;
        }

        $tpl      = $this->resolve_template( $lang );
        $message  = $this->interpolate(
            ( ! empty( $tpl['wa_body'] ) ? $tpl['wa_body'] : null )
                ?? $this->opts['wa_body']
                ?? $this->default_body(),
            $product
        );
        $endpoint = $base_url . 'message/sendText/' . $instance;

        $response = wp_remote_post( $endpoint, [
            'timeout' => 15,
            'headers' => [
                'apikey'       => $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode( [
                'number' => $contact,
                'text'   => $message,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Evolution API returns { "status": "PENDING" } on success.
        return isset( $body['status'] ) && 'PENDING' === $body['status'];
    }

    private function interpolate( string $template, \WC_Product $product ): string {
        return str_replace(
            [ '{product_name}', '{product_url}', '{site_name}' ],
            [ $product->get_name(), get_permalink( $product->get_id() ), get_bloginfo( 'name' ) ],
            $template
        );
    }

    private function default_body(): string {
        return __(
            "🎉 *{product_name}* is back in stock!\n\nOrder now: {product_url}",
            'space-core'
        );
    }
}
