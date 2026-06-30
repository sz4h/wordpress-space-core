<?php

namespace Space\Core\Modules\MediaOffload;

defined( 'ABSPATH' ) || exit;

interface StorageAdapterInterface {

	public function is_ready(): bool;

	public function object_exists( string $key ): bool;

	public function put_object( string $key, string $file_path, string $content_type ): bool;

	/**
	 * Download an object to a local temporary file.
	 *
	 * @return string|null Absolute path to the downloaded temp file, or null on failure.
	 */
	public function get_object( string $key ): ?string;

	public function delete_object( string $key ): bool;

	public function public_url( string $key ): string;

	/**
	 * @return array{success: bool, message: string}
	 */
	public function test_connection(): array;
}
