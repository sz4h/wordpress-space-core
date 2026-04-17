<?php

namespace Space\Core\Modules\MediaOffload;

defined( 'ABSPATH' ) || exit;

class DOSpacesAdapter extends AbstractAdapter {

	private const TEST_KEY = '___sc_media_offload_test_connection.txt';

	public function is_ready(): bool {
		return '' !== $this->bucket()
			&& '' !== $this->region()
			&& '' !== $this->access_key()
			&& '' !== $this->secret_key();
	}

	public function object_exists( string $key ): bool {
		$response = $this->signed_request( 'HEAD', $key );
		$status   = (int) ( $response['status'] ?? 0 );

		return $status >= 200 && $status < 300;
	}

	public function put_object( string $key, string $file_path, string $content_type ): bool {
		$file_size = @filesize( $file_path );

		if ( false === $file_size ) {
			return false;
		}

		$headers = [
			'Content-Type'   => $content_type,
			'Content-Length' => (string) $file_size,
		];

		if ( 'public' === $this->visibility() ) {
			$headers['x-amz-acl'] = 'public-read';
		}

		$response = $this->signed_request( 'PUT', $key, $headers, $file_path );
		$status   = (int) ( $response['status'] ?? 0 );

		return 200 === $status || 201 === $status;
	}

	public function delete_object( string $key ): bool {
		$response = $this->signed_request( 'DELETE', $key );
		$status   = (int) ( $response['status'] ?? 0 );

		return ( $status >= 200 && $status < 300 ) || 404 === $status;
	}

	public function public_url( string $key ): string {
		$key = $this->encoded_key_path( $key );

		if ( '' === $key ) {
			return '';
		}

		$base = $this->normalize_url_root( (string) ( $this->settings['base_url'] ?? '' ) );

		if ( '' !== $base ) {
			return $base . '/' . $key;
		}

		if ( '' === $this->bucket() || '' === $this->region() ) {
			return '';
		}

		return 'https://' . rawurlencode( $this->bucket() ) . '.' . $this->region() . '.digitaloceanspaces.com/' . $key;
	}

	public function test_connection(): array {
		$temp_file = $this->connection_test_file();

		if ( ! $temp_file ) {
			return [
				'success' => false,
				'message' => __( 'Unable to create a temporary file for the connection test.', 'space-core' ),
			];
		}

		$put = $this->put_object( self::TEST_KEY, $temp_file, 'text/plain' );
		@unlink( $temp_file );

		if ( ! $put ) {
			return [
				'success' => false,
				'message' => __( 'Upload test failed. Check the region, access key, secret key, and bucket/space name.', 'space-core' ),
			];
		}

		$this->delete_object( self::TEST_KEY );

		return [
			'success' => true,
			'message' => __( 'Connection test succeeded.', 'space-core' ),
		];
	}

	private function signed_request( string $method, string $key, array $headers = [], ?string $file_path = null ): array {
		if ( ! $this->is_ready() ) {
			return [
				'status'   => 0,
				'response' => '',
				'error'    => 'missing_settings',
			];
		}

		$method        = strtoupper( $method );
		$encoded_key   = $this->encoded_key_path( $key );
		$canonical_uri = '/' . rawurlencode( $this->bucket() ) . '/' . $encoded_key;
		$url           = 'https://' . $this->api_host() . $canonical_uri;
		$amz_date      = gmdate( 'Ymd\THis\Z' );
		$date_stamp    = gmdate( 'Ymd' );
		$payload_hash  = 'PUT' === $method
			? ( hash_file( 'sha256', $file_path ?: '' ) ?: hash( 'sha256', '' ) )
			: hash( 'sha256', '' );

		$signed_headers = [
			'host'                 => $this->api_host(),
			'x-amz-content-sha256' => $payload_hash,
			'x-amz-date'           => $amz_date,
		];

		ksort( $signed_headers );

		$canonical_headers = '';
		foreach ( $signed_headers as $header => $value ) {
			$canonical_headers .= $header . ':' . trim( $value ) . "\n";
		}

		$signed_header_names = implode( ';', array_keys( $signed_headers ) );
		$scope               = $date_stamp . '/' . $this->region() . '/s3/aws4_request';
		$canonical_request   = implode( "\n", [
			$method,
			$canonical_uri,
			'',
			$canonical_headers,
			$signed_header_names,
			$payload_hash,
		] );
		$string_to_sign      = implode( "\n", [
			'AWS4-HMAC-SHA256',
			$amz_date,
			$scope,
			hash( 'sha256', $canonical_request ),
		] );
		$signature           = hash_hmac( 'sha256', $string_to_sign, $this->signing_key( $date_stamp ) );
		$authorization       = 'AWS4-HMAC-SHA256 '
			. 'Credential=' . $this->access_key() . '/' . $scope . ', '
			. 'SignedHeaders=' . $signed_header_names . ', '
			. 'Signature=' . $signature;

		$request_headers = array_merge( $headers, [
			'Host'                 => $this->api_host(),
			'X-Amz-Date'           => $amz_date,
			'X-Amz-Content-Sha256' => $payload_hash,
			'Authorization'        => $authorization,
		] );

		return $this->execute_request( $method, $url, $request_headers, $file_path );
	}

	private function signing_key( string $date_stamp ): string {
		$k_date    = hash_hmac( 'sha256', $date_stamp, 'AWS4' . $this->secret_key(), true );
		$k_region  = hash_hmac( 'sha256', $this->region(), $k_date, true );
		$k_service = hash_hmac( 'sha256', 's3', $k_region, true );

		return hash_hmac( 'sha256', 'aws4_request', $k_service, true );
	}

	private function api_host(): string {
		$endpoint = trim( (string) ( $this->settings['endpoint'] ?? '' ) );

		if ( '' !== $endpoint ) {
			$host = $this->parsed_host( $endpoint );
			if ( '' !== $host ) {
				return $host;
			}
		}

		return $this->region() . '.digitaloceanspaces.com';
	}

	private function bucket(): string {
		return trim( (string) ( $this->settings['bucket'] ?? '' ) );
	}

	private function region(): string {
		return trim( (string) ( $this->settings['region'] ?? '' ) );
	}

	private function access_key(): string {
		return trim( (string) ( $this->settings['access_key'] ?? '' ) );
	}

	private function secret_key(): string {
		return trim( (string) ( $this->settings['secret_key'] ?? '' ) );
	}

	private function visibility(): string {
		$visibility = trim( (string) ( $this->settings['visibility'] ?? 'public' ) );

		return in_array( $visibility, [ 'public', 'private' ], true ) ? $visibility : 'public';
	}
}
