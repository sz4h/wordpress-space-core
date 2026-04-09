<?php

namespace Space\Core\Modules\StockNotifier\Channels;

defined( 'ABSPATH' ) || exit;

/**
 * Email channel using the WooCommerce transactional email template layout.
 *
 * The body text is injected inside WooCommerce's standard email wrapper
 * (header + footer template), so it inherits the store's branding colours
 * and logo exactly like native WC emails.
 */
class EmailChannel {

    private array $opts;

    public function __construct( array $opts ) {
        $this->opts = $opts;
    }

    /**
     * Find the template values for the subscriber's language.
     * Falls back to the global default if no override exists.
     */
    private function resolve_template( string $lang ): array {
        foreach ( (array) ( $this->opts['lang_templates'] ?? [] ) as $tpl ) {
            if ( isset( $tpl['lang'] ) && $tpl['lang'] === $lang ) {
                return $tpl;
            }
        }
        return [];
    }

    public function send( string $contact, int $product_id, string $lang ): bool {
        if ( ! is_email( $contact ) ) {
            return false;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return false;
        }

        $tpl = $this->resolve_template( $lang );

        $subject = $this->interpolate(
            ( ! empty( $tpl['email_subject'] ) ? $tpl['email_subject'] : null )
                ?? $this->opts['email_subject']
                ?? __( '{product_name} is back in stock!', 'space-core' ),
            $product
        );

        $body_text = $this->interpolate(
            ( ! empty( $tpl['email_body'] ) ? $tpl['email_body'] : null )
                ?? $this->opts['email_body']
                ?? $this->default_body(),
            $product
        );

        $html = $this->wrap_in_wc_template( $subject, $body_text, $product );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->get_from_name() . ' <' . $this->get_from_address() . '>',
        ];

        return wp_mail( $contact, $subject, $html, $headers );
    }

    /**
     * Render the body inside the standard WooCommerce email header + footer.
     */
    private function wrap_in_wc_template( string $heading, string $body_text, \WC_Product $product ): string {
        // Ensure WC email styles are available.
        if ( ! class_exists( 'WC_Email' ) ) {
            // Fallback: plain HTML if WooCommerce is not available.
            return wpautop( wp_kses_post( $body_text ) );
        }

        $mailer = WC()->mailer();
        // WC_Mailer::get_emails() initialises the email objects which also
        // loads the inline CSS inliner etc.
        $mailer->get_emails();

        // Capture header template.
        ob_start();
        wc_get_template(
            'emails/email-header.php',
            [ 'email_heading' => $heading ],
            '',
            WC()->plugin_path() . '/templates/'
        );
        $header = ob_get_clean();

        // Build the inner content: introductory text + a CTA button.
        $inner = $this->build_inner_html( $body_text, $product );

        // Capture footer template.
        ob_start();
        wc_get_template(
            'emails/email-footer.php',
            [],
            '',
            WC()->plugin_path() . '/templates/'
        );
        $footer = ob_get_clean();

        $full_html = $header . $inner . $footer;

        // Apply WooCommerce inline CSS (same as native emails).
        return $mailer->style_inline( $full_html );
    }

    /**
     * Build the inner content block that sits between header and footer.
     *
     * Keeps the same DOM patterns WooCommerce uses in its own emails.
     */
    private function build_inner_html( string $body_text, \WC_Product $product ): string {
        $product_url = get_permalink( $product->get_id() );
        $button_text = __( 'Shop Now', 'space-core' );

        // Convert newlines to <p> tags for readability.
        $paragraphs = wpautop( wp_kses_post( $body_text ) );

        // WC email inner wrapper — matches the standard WC email structure.
        return sprintf(
            '<div style="margin:0;padding:0;">
                %1$s
                <p style="text-align:center;margin:24px 0;">
                    <a href="%2$s"
                       style="display:inline-block;padding:12px 28px;background-color:#7f54b3;color:#ffffff;
                              text-decoration:none;border-radius:3px;font-size:14px;font-weight:600;">
                        %3$s
                    </a>
                </p>
            </div>',
            $paragraphs,
            esc_url( $product_url ),
            esc_html( $button_text )
        );
    }

    private function interpolate( string $template, \WC_Product $product ): string {
        return str_replace(
            [ '{product_name}', '{product_url}', '{site_name}' ],
            [ $product->get_name(), get_permalink( $product->get_id() ), get_bloginfo( 'name' ) ],
            $template
        );
    }

    private function get_from_name(): string {
        return wp_specialchars_decode( get_option( 'woocommerce_email_from_name', get_bloginfo( 'name' ) ), ENT_QUOTES );
    }

    private function get_from_address(): string {
        return sanitize_email( get_option( 'woocommerce_email_from_address', get_bloginfo( 'admin_email' ) ) );
    }

    private function default_body(): string {
        return __(
            "Good news!\n\n{product_name} is now back in stock and ready to order.\n\nDon't miss out — quantities may be limited.",
            'space-core'
        );
    }
}
