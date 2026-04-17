<?php

namespace Space\Core\Modules\MediaOffload;

defined( 'ABSPATH' ) || exit;

class BunnyAdapter extends AbstractAdapter {

	private const TEST_KEY = '___sc_media_offload_test_connection.txt';

	public function is_ready(): bool {
		return '' !== $this->bucket() && '' !== $this->host() && '' !== $this->access_key();
	}

	public function object_exists( string $key ): bool {
		$response = $this->request( 'HEAD', $key );
		$status   = (int) ( $response['status'] ?? 0 );

		return $status >= 200 && $status < 300;
	}

	public function put_object( string $key, string $file_path, string $content_type ): bool {
		$file_size = @filesize( $file_path );

		if ( false === $file_size ) {
			return false;
		}

		$response = $this->request( 'PUT', $key, [
			'Content-Type'   => $content_type,
			'Content-Length' => (string) $file_size,
		], $file_path );
		$status   = (int) ( $response['status'] ?? 0 );

		return 200 === $status || 201 === $status;
	}

	public function delete_object( string $key ): bool {
		$response = $this->request( 'DELETE', $key );
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

		$host = $this->host();
		if ( '' === $host || '' === $this->bucket() ) {
			return '';
		}

		return 'https://' . $host . '/' . rawurlencode( $this->bucket() ) . '/' . $key;
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
				'message' => __( 'Upload test failed. Check the storage host, bucket/zone name, and access key.', 'space-core' ),
			];
		}

		$this->delete_object( self::TEST_KEY );

		return [
			'success' => true,
			'message' => __( 'Connection test succeeded.', 'space-core' ),
		];
	}

	private function request( string $method, string $key, array $headers = [], ?string $file_path = null ): array {
		if ( ! $this->is_ready() ) {
			return [
				'status'   => 0,
				'response' => '',
				'error'    => 'missing_settings',
			];
		}

		$headers['AccessKey'] = $this->access_key();

		return $this->execute_request( $method, $this->request_url( $key ), $headers, $file_path );
	}

	private function request_url( string $key ): string {
		return 'https://' . $this->host() . '/' . rawurlencode( $this->bucket() ) . '/' . $this->encoded_key_path( $key );
	}

	private function host(): string {
		return $this->parsed_host( (string) ( $this->settings['endpoint'] ?? '' ) );
	}

	private function bucket(): string {
		return trim( (string) ( $this->settings['bucket'] ?? '' ) );
	}

	private function access_key(): string {
		return trim( (string) ( $this->settings['access_key'] ?? '' ) );
	}
}
