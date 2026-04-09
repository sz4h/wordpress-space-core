<?php

namespace Space\Core\Modules\SafeSVG;

defined( 'ABSPATH' ) || exit;

use Space\Core\Abstracts\AbstractModule;

class Module extends AbstractModule {

    public function get_label(): string {
        return __( 'Safe SVG Upload', 'space-core' );
    }

    public function get_description(): string {
        return __( 'Allow SVG file uploads and sanitize them to prevent XSS attacks.', 'space-core' );
    }

    public function boot(): void {
        add_filter( 'upload_mimes', [ $this, 'allow_svg' ] );
        add_filter( 'wp_check_filetype_and_ext', [ $this, 'fix_mime_type' ], 10, 4 );
        add_filter( 'wp_handle_upload_prefilter', [ $this, 'sanitize_svg_on_upload' ] );
        add_filter( 'wp_prepare_attachment_for_js', [ $this, 'fix_svg_thumbnail' ], 10, 3 );
        add_action( 'admin_head', [ $this, 'fix_svg_admin_css' ] );
    }

    public function allow_svg( array $mimes ): array {
        $mimes['svg']  = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }

    public function fix_mime_type( array $data, string $file, string $filename, array $mimes ): array {
        if ( ! $data['ext'] && ! $data['type'] ) {
            $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
            if ( in_array( $ext, [ 'svg', 'svgz' ], true ) ) {
                $data['ext']  = $ext;
                $data['type'] = 'image/svg+xml';
            }
        }
        return $data;
    }

    public function sanitize_svg_on_upload( array $file ): array {
        if ( ! isset( $file['tmp_name'] ) || ! isset( $file['type'] ) ) {
            return $file;
        }
        if ( 'image/svg+xml' !== $file['type'] ) {
            return $file;
        }
        $sanitized = $this->sanitize_svg( $file['tmp_name'] );
        if ( false === $sanitized ) {
            $file['error'] = __( 'Invalid SVG file. Upload rejected.', 'space-core' );
            return $file;
        }
        file_put_contents( $file['tmp_name'], $sanitized ); // phpcs:ignore
        return $file;
    }

    /**
     * Sanitize SVG content using DOMDocument — strips scripts and event attrs.
     */
    private function sanitize_svg( string $filepath ): string|false {
        $svg = file_get_contents( $filepath ); // phpcs:ignore
        if ( false === $svg ) {
            return false;
        }

        $dom = new \DOMDocument();
        $dom->formatOutput = true;

        // Suppress libxml errors for malformed SVG.
        libxml_use_internal_errors( true );
        if ( ! $dom->loadXML( $svg ) ) {
            libxml_clear_errors();
            return false;
        }
        libxml_clear_errors();

        // Dangerous elements.
        $remove_tags = [ 'script', 'use', 'foreignObject' ];
        foreach ( $remove_tags as $tag ) {
            foreach ( $dom->getElementsByTagName( $tag ) as $node ) {
                $node->parentNode?->removeChild( $node );
            }
        }

        // Dangerous attributes (event handlers and xlink:href on <use>).
        $xpath = new \DOMXPath( $dom );
        $dangerous_attrs = $xpath->query( '//@*[starts-with(local-name(), "on")]' );
        if ( $dangerous_attrs ) {
            foreach ( $dangerous_attrs as $attr ) {
                $attr->ownerElement?->removeAttributeNode( $attr );
            }
        }

        return $dom->saveXML();
    }

    public function fix_svg_thumbnail( array $response, \WP_Post $attachment, $meta ): array {
        if ( 'image/svg+xml' !== $response['mime'] ) {
            return $response;
        }
        $svg_path = get_attached_file( $attachment->ID );
        if ( ! $svg_path || ! file_exists( $svg_path ) ) {
            return $response;
        }
        $dimensions = $this->get_svg_dimensions( $svg_path );
        if ( $dimensions ) {
            $response['width']  = $dimensions['width'];
            $response['height'] = $dimensions['height'];
        }
        if ( empty( $response['sizes'] ) ) {
            $url = wp_get_attachment_url( $attachment->ID );
            $response['sizes'] = [
                'full' => [
                    'url'         => $url,
                    'width'       => $response['width'] ?? 0,
                    'height'      => $response['height'] ?? 0,
                    'orientation' => 'landscape',
                ],
            ];
        }
        return $response;
    }

    private function get_svg_dimensions( string $filepath ): array|false {
        $svg = simplexml_load_file( $filepath );
        if ( false === $svg ) {
            return false;
        }
        $attrs = $svg->attributes();
        $width  = (int) ( $attrs->width ?? 0 );
        $height = (int) ( $attrs->height ?? 0 );

        if ( ! $width || ! $height ) {
            $viewbox = (string) ( $attrs->viewBox ?? '' );
            if ( $viewbox ) {
                $parts  = preg_split( '/[\s,]+/', $viewbox );
                $width  = (int) ( $parts[2] ?? 0 );
                $height = (int) ( $parts[3] ?? 0 );
            }
        }
        return ( $width && $height ) ? compact( 'width', 'height' ) : false;
    }

    public function fix_svg_admin_css(): void {
        echo '<style>
            .media-icon img[src$=".svg"],
            img[src$=".svg"].attachment-post-thumbnail {
                width: 100% !important;
                height: auto !important;
            }
        </style>';
    }
}
