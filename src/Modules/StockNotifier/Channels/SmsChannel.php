<?php

namespace Space\Core\Modules\StockNotifier\Channels;

defined( 'ABSPATH' ) || exit;

/**
 * SMS channel via SMSBox.com API.
 *
 * API: GET https://smsbox.com/SMSGateway/Services/Messaging.asmx/Http_SendSMS
 * Returns XML: <Result>true</Result><messageId>...</messageId>
 */
class SmsChannel {

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
        $username    = sanitize_text_field( $this->opts['sms_username'] ?? '' );
        $password    = sanitize_text_field( $this->opts['sms_password'] ?? '' );
        $customer_id = sanitize_text_field( $this->opts['sms_customer_id'] ?? '' );
        $sender_text = sanitize_text_field( $this->opts['sms_sender'] ?? '' );

        if ( empty( $username ) || empty( $password ) || empty( $customer_id ) || empty( $contact ) ) {
            return false;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return false;
        }

        $tpl     = $this->resolve_template( $lang );
        $message = $this->interpolate(
            ( ! empty( $tpl['sms_body'] ) ? $tpl['sms_body'] : null )
                ?? $this->opts['sms_body']
                ?? $this->default_body(),
            $product
        );

        $endpoint = add_query_arg( [
            'username'         => urlencode( $username ),
            'password'         => urlencode( $password ),
            'customerId'       => $customer_id,
            'senderText'       => $sender_text,
            'defDate'          => '',
            'isBlink'          => 'false',
            'isFlash'          => 'false',
            'recipientNumbers' => $contact,
            'messageBody'      => urlencode( $message ),
        ], 'https://smsbox.com/SMSGateway/Services/Messaging.asmx/Http_SendSMS' );

        $response = wp_remote_get( $endpoint, [ 'timeout' => 15 ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return false;
        }

        $parsed = $this->parse_xml( $body );
        return isset( $parsed['Result'] ) && 'true' === (string) $parsed['Result'];
    }

    /**
     * Parse the SMSBox XML response into an associative array.
     */
    private function parse_xml( string $xml_string ): array {
        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $xml_string, 'SimpleXMLElement', LIBXML_NOCDATA );
        libxml_clear_errors();

        if ( false === $xml ) {
            return [];
        }

        // json_encode → json_decode trick to get a plain array (same as the reference impl).
        $arr = json_decode( wp_json_encode( (array) $xml ), true );
        return is_array( $arr ) ? $arr : [];
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
            '{product_name} is back in stock! Order now: {product_url}',
            'space-core'
        );
    }
}
