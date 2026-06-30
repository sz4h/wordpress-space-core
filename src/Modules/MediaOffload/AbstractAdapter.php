<?php

namespace Space\Core\Modules\MediaOffload;

defined( 'ABSPATH' ) || exit;

abstract class AbstractAdapter implements StorageAdapterInterface {

	protected array $settings;

	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	protected function normalize_key_path( string $path ): string {
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '#/+#', '/', $path );

		return ltrim( (string) $path, '/' );
	}

	protected function encoded_key_path( string $key ): string {
		$key      = $this->normalize_key_path( $key );
		$segments = array_filter( explode( '/', $key ), 'strlen' );

		return implode( '/', array_map( 'rawurlencode', $segments ) );
	}

	protected function normalize_url_root( string $url ): string {
		$url = trim( $url );

		if ( '' === $url ) {
			return '';
		}

		if ( 0 === stripos( $url, '//' ) ) {
			$url = 'https:' . $url;
		} elseif ( 0 !== stripos( $url, 'http://' ) && 0 !== stripos( $url, 'https://' ) ) {
			$url = 'https://' . $url;
		}

		if ( 0 === stripos( $url, 'http://' ) ) {
			$url = 'https://' . substr( $url, 7 );
		}

		return rtrim( $url, '/' );
	}

	protected function parsed_host( string $endpoint ): string {
		$endpoint = $this->normalize_url_root( $endpoint );
		$parts    = wp_parse_url( $endpoint );

		return is_array( $parts ) && ! empty( $parts['host'] ) ? (string) $parts['host'] : '';
	}

	/**
	 * @param array<string, string> $headers
	 * @return array{status:int,response:string,error:string}
	 */
	protected function execute_request( string $method, string $url, array $headers, ?string $file_path = null ): array {
		$method = strtoupper( $method );

		if ( function_exists( 'curl_init' ) ) {
			$ch          = curl_init();
			$http_header = [];

			foreach ( $headers as $key => $value ) {
				$http_header[] = $key . ': ' . $value;
			}

			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_HEADER, false );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $http_header );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 20 );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 120 );

			$fp = null;

			if ( 'HEAD' === $method ) {
				curl_setopt( $ch, CURLOPT_NOBODY, true );
			} elseif ( 'PUT' === $method ) {
				$fp = @fopen( $file_path ?: '', 'rb' );

				if ( ! $fp ) {
					curl_close( $ch );

					return [
						'status'   => 0,
						'response' => '',
						'error'    => 'file_open_failed',
					];
				}

				curl_setopt( $ch, CURLOPT_UPLOAD, true );
				curl_setopt( $ch, CURLOPT_INFILE, $fp );

				$size = @filesize( $file_path ?: '' );
				if ( false !== $size ) {
					curl_setopt( $ch, CURLOPT_INFILESIZE, (int) $size );
				}
			}

			$response = curl_exec( $ch );
			$errno    = curl_errno( $ch );
			$error    = $errno ? (string) curl_error( $ch ) : '';
			$status   = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );

			if ( is_resource( $fp ) ) {
				fclose( $fp );
			}

			curl_close( $ch );

			return [
				'status'   => $status,
				'response' => is_string( $response ) ? $response : '',
				'error'    => $error,
			];
		}

		$args = [
			'method'      => $method,
			'headers'     => $headers,
			'timeout'     => 120,
			'redirection' => 0,
		];

		if ( 'PUT' === $method ) {
			$data = @file_get_contents( $file_path ?: '' );
			if ( false === $data ) {
				return [
					'status'   => 0,
					'response' => '',
					'error'    => 'file_read_failed',
				];
			}
			$args['body'] = $data;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'status'   => 0,
				'response' => '',
				'error'    => $response->get_error_message(),
			];
		}

		return [
			'status'   => (int) wp_remote_retrieve_response_code( $response ),
			'response' => (string) wp_remote_retrieve_body( $response ),
			'error'    => '',
		];
	}

	protected function store_body_to_temp( string $body ): ?string {
		$temp_file = wp_tempnam( 'space-core-media-offload-transfer' );

		if ( ! $temp_file ) {
			return null;
		}

		$written = @file_put_contents( $temp_file, $body );

		if ( false === $written ) {
			@unlink( $temp_file );

			return null;
		}

		return $temp_file;
	}

	protected function connection_test_file(): ?string {
		$temp_file = wp_tempnam( 'space-core-media-offload-test.txt' );

		if ( ! $temp_file ) {
			return null;
		}

		$result = @file_put_contents( $temp_file, 'Space Core Media Offload test ' . gmdate( 'Y-m-d H:i:s' ) );

		return false === $result ? null : $temp_file;
	}
}
