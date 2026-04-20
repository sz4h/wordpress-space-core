<?php

namespace Space\Core\Modules\MediaOffload;

defined( 'ABSPATH' ) || exit;

use __PHP_Incomplete_Class;
use Space\Core\Abstracts\AbstractModule;
use Throwable;

class Module extends AbstractModule {

	public const OPTION_KEY = 'space_core_media_offload';
	public const META_OFFLOADED = '_sc_media_offloaded';
	public const META_KEY = '_sc_media_key';
	public const META_FILES = '_sc_media_files';

	private string $rewrite_new_base_url = '';
	private ?array $settings_cache = null;

	public function get_label(): string {
		return __( 'Media Offload', 'space-core' );
	}

	public function get_description(): string {
		return __( 'Offload media uploads to external object storage and manage URL migration tools.', 'space-core' );
	}

	public function boot(): void {
		add_action( 'wp_ajax_sc_save_media_offload_settings', [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_sc_media_offload_test_connection', [ $this, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_sc_media_offload_offload_batch', [ $this, 'ajax_offload_batch' ] );
		add_action( 'wp_ajax_sc_media_offload_regenerate_batch', [ $this, 'ajax_regenerate_batch' ] );
		add_action( 'wp_ajax_sc_media_offload_migrate_urls', [ $this, 'ajax_migrate_urls' ] );
		add_action( 'wp_ajax_sc_media_offload_restore_urls', [ $this, 'ajax_restore_urls' ] );
		add_action( 'wp_ajax_sc_media_offload_fix_broken_urls', [ $this, 'ajax_fix_broken_urls' ] );
		add_action( 'wp_ajax_sc_media_offload_find_replace_text', [ $this, 'ajax_find_replace_text' ] );

		add_filter( 'wp_unique_filename', [ $this, 'filter_unique_filename' ], 10, 4 );
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'filter_generate_attachment_metadata' ], 10, 2 );
		add_filter( 'wp_get_attachment_url', [ $this, 'filter_attachment_url' ], 10, 2 );
		add_filter( 'image_downsize', [ $this, 'filter_image_downsize' ], 10, 3 );
		add_filter( 'wp_calculate_image_srcset', [ $this, 'filter_calculate_image_srcset' ], 10, 5 );
		add_action( 'delete_attachment', [ $this, 'action_delete_attachment' ], 10, 1 );

		$options = $this->get_settings();
		if ( ! empty( $options['sanitize_output'] ) ) {
			add_filter( 'the_content', [ $this, 'filter_sanitize_broken_urls_output' ], 9999 );
			add_filter( 'the_excerpt', [ $this, 'filter_sanitize_broken_urls_output' ], 9999 );
			add_filter( 'get_the_excerpt', [ $this, 'filter_sanitize_broken_urls_output' ], 9999 );
			add_filter( 'content_save_pre', [ $this, 'filter_sanitize_broken_urls_output' ], 9999 );
			add_filter( 'excerpt_save_pre', [ $this, 'filter_sanitize_broken_urls_output' ], 9999 );
			if ( class_exists( 'WooCommerce' ) ) {
				add_filter( 'woocommerce_short_description', [ $this, 'filter_sanitize_broken_urls_output' ], 9999 );
				add_filter( 'woocommerce_product_get_short_description', [
					$this,
					'filter_sanitize_broken_urls_output'
				], 9999, 2 );
			}
		}
	}

	private function get_settings(): array {
		if ( $this->settings_cache === null ) {
			$saved                = get_option( self::OPTION_KEY, [] );
			$this->settings_cache = array_merge( $this->defaults(), is_array( $saved ) ? $saved : [] );
		}

		return $this->settings_cache;
	}

	private function defaults(): array {
		return [
			'enabled'          => 0,
			'adapter'          => 'bunny',
			'bucket'           => '',
			'visibility'       => 'public',
			'endpoint'         => '',
			'region'           => '',
			'access_key'       => '',
			'secret_key'       => '',
			'bunny_endpoint'   => '',
			'bunny_access_key' => '',
			'do_endpoint'      => '',
			'do_region'        => '',
			'do_access_key'    => '',
			'do_secret_key'    => '',
			'base_url'         => '',
			'prefix'           => '',
			'delete_local'     => 0,
			'sanitize_output'  => 0,
		];
	}

	public function render_settings(): void {
		$options = $this->get_settings();

		echo $this->view( 'admin/settings', [
			'options'        => $options,
			'adapters'       => [
				'bunny'     => __( 'Bunny Storage / BunnyCDN', 'space-core' ),
				'do_spaces' => __( 'DO Spaces', 'space-core' ),
			],
			'old_base_url'   => $this->get_old_base_url(),
			'new_base_url'   => $this->get_public_base_url( $options ),
			'is_ready'       => $this->is_ready( $options ),
			'selected'       => (string) ( $options['adapter'] ?? 'bunny' ),
			'page_status_id' => 'sc-mo-status',
		] );
	}

	private function get_old_base_url(): string {
		$uploads = wp_upload_dir();

		return empty( $uploads['baseurl'] ) ? '' : rtrim( (string) $uploads['baseurl'], '/' );
	}

	private function get_public_base_url( ?array $settings = null ): string {
		$settings = $settings ?: $this->get_settings();
		$root     = $this->get_public_base_root_url( $settings );
		if ( '' === $root ) {
			return '';
		}

		$prefix = $this->get_object_prefix( $settings );

		return '' === $prefix ? $root : $root . '/' . $prefix;
	}

	private function get_public_base_root_url( ?array $settings = null ): string {
		$settings = $settings ?: $this->get_settings();
		$base_url = trim( (string) ( $settings['base_url'] ?? '' ) );

		if ( '' !== $base_url ) {
			return rtrim( $this->ensure_https( $base_url ), '/' );
		}

		$adapter = (string) ( $settings['adapter'] ?? 'bunny' );

		if ( 'bunny' === $adapter ) {
			$endpoint = (string) ( $settings['bunny_endpoint'] ?? $settings['endpoint'] ?? '' );
			$host     = is_string( wp_parse_url( $this->ensure_https( $endpoint ), PHP_URL_HOST ) ) ? (string) wp_parse_url( $this->ensure_https( $endpoint ), PHP_URL_HOST ) : '';
			$bucket   = trim( (string) ( $settings['bucket'] ?? '' ) );

			return ( '' !== $host && '' !== $bucket )
				? 'https://' . $host . '/' . rawurlencode( $bucket )
				: '';
		}

		return match ( $adapter ) {
			'do_spaces' => ( (string) ( $settings['bucket'] ?? '' ) !== '' && (string) ( $settings['do_region'] ?? $settings['region'] ?? '' ) !== '' )
				? 'https://' . rawurlencode( (string) $settings['bucket'] ) . '.' . sanitize_text_field( (string) ( $settings['do_region'] ?? $settings['region'] ?? '' ) ) . '.digitaloceanspaces.com'
				: '',
			default => '',
		};
	}

	private function ensure_https( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 === stripos( $url, '//' ) ) {
			return 'https:' . $url;
		}
		if ( 0 === stripos( $url, 'http://' ) ) {
			return 'https://' . substr( $url, 7 );
		}

		return $url;
	}

	private function get_object_prefix( ?array $settings = null ): string {
		$settings = $settings ?: $this->get_settings();

		return $this->normalize_prefix( (string) ( $settings['prefix'] ?? '' ) );
	}

	private function normalize_prefix( string $prefix ): string {
		$prefix = str_replace( '\\', '/', $prefix );
		$prefix = preg_replace( '#/+#', '/', $prefix );
		$prefix = trim( $prefix );

		return trim( (string) $prefix, '/' );
	}

	private function is_ready( ?array $settings = null ): bool {
		$settings = $settings ?: $this->get_settings();
		$adapter  = $this->adapter_from_settings( $settings );

		return ! empty( $settings['enabled'] ) && $adapter instanceof StorageAdapterInterface && $adapter->is_ready();
	}

	private function adapter_from_settings( array $settings ): ?StorageAdapterInterface {
		$adapter = (string) ( $settings['adapter'] ?? 'bunny' );

		$effective = $settings;
		if ( 'bunny' === $adapter ) {
			$effective['endpoint']   = (string) ( $settings['bunny_endpoint'] ?? $settings['endpoint'] ?? '' );
			$effective['access_key'] = (string) ( $settings['bunny_access_key'] ?? $settings['access_key'] ?? '' );
			$effective['secret_key'] = '';
		} elseif ( 'do_spaces' === $adapter ) {
			$effective['endpoint']   = (string) ( $settings['do_endpoint'] ?? $settings['endpoint'] ?? '' );
			$effective['region']     = (string) ( $settings['do_region'] ?? $settings['region'] ?? '' );
			$effective['access_key'] = (string) ( $settings['do_access_key'] ?? $settings['access_key'] ?? '' );
			$effective['secret_key'] = (string) ( $settings['do_secret_key'] ?? $settings['secret_key'] ?? '' );
		}

		return match ( $adapter ) {
			'bunny' => new BunnyAdapter( $effective ),
			'do_spaces' => new DOSpacesAdapter( $effective ),
			default => null,
		};
	}

	public function ajax_save_settings(): void {
		check_ajax_referer( 'space_core_admin', 'nonce' );
		if ( ! $this->can_manage() ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'space-core' ) ], 403 );
		}

		$raw  = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '{}'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$data = json_decode( (string) $raw, true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid payload.', 'space-core' ) ], 400 );
		}

		update_option( self::OPTION_KEY, $this->sanitize_settings( $data, true ), true );
		$this->settings_cache = null;

		wp_send_json_success( [ 'message' => __( 'Settings saved.', 'space-core' ) ] );
	}

	private function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	private function sanitize_settings( mixed $raw, bool $preserve_blank_secrets = true ): array {
		$raw      = is_array( $raw ) ? $raw : [];
		$existing = $this->get_settings();
		$adapter  = in_array( (string) ( $raw['adapter'] ?? '' ), [
			'bunny',
			'do_spaces'
		], true ) ? (string) $raw['adapter'] : 'bunny';

		$bunny_endpoint = sanitize_text_field( (string) ( $raw['bunny_endpoint'] ?? $raw['endpoint'] ?? '' ) );
		$bunny_access   = sanitize_text_field( (string) ( $raw['bunny_access_key'] ?? $raw['access_key'] ?? '' ) );
		$do_endpoint    = sanitize_text_field( (string) ( $raw['do_endpoint'] ?? $raw['endpoint'] ?? '' ) );
		$do_region      = sanitize_text_field( strtolower( (string) ( $raw['do_region'] ?? $raw['region'] ?? '' ) ) );
		$do_access      = sanitize_text_field( (string) ( $raw['do_access_key'] ?? $raw['access_key'] ?? '' ) );
		$do_secret      = sanitize_text_field( (string) ( $raw['do_secret_key'] ?? $raw['secret_key'] ?? '' ) );

		if ( $preserve_blank_secrets ) {
			if ( '' === $bunny_access ) {
				$bunny_access = (string) ( $existing['bunny_access_key'] ?? '' );
			}
			if ( '' === $do_access ) {
				$do_access = (string) ( $existing['do_access_key'] ?? '' );
			}
			if ( '' === $do_secret ) {
				$do_secret = (string) ( $existing['do_secret_key'] ?? '' );
			}
		}

		$generic_endpoint = 'bunny' === $adapter ? $bunny_endpoint : $do_endpoint;
		$generic_region   = 'do_spaces' === $adapter ? $do_region : '';
		$generic_access   = 'bunny' === $adapter ? $bunny_access : $do_access;
		$generic_secret   = 'do_spaces' === $adapter ? $do_secret : '';
		$visibility       = in_array( (string) ( $raw['visibility'] ?? '' ), [
			'public',
			'private'
		], true ) ? (string) $raw['visibility'] : 'public';

		return [
			'enabled'          => ! empty( $raw['enabled'] ) ? 1 : 0,
			'adapter'          => $adapter,
			'bucket'           => sanitize_text_field( (string) ( $raw['bucket'] ?? '' ) ),
			'visibility'       => $visibility,
			'endpoint'         => $generic_endpoint,
			'region'           => $generic_region,
			'access_key'       => $generic_access,
			'secret_key'       => $generic_secret,
			'bunny_endpoint'   => $bunny_endpoint,
			'bunny_access_key' => $bunny_access,
			'do_endpoint'      => $do_endpoint,
			'do_region'        => $do_region,
			'do_access_key'    => $do_access,
			'do_secret_key'    => $do_secret,
			'base_url'         => esc_url_raw( (string) ( $raw['base_url'] ?? '' ) ),
			'prefix'           => $this->normalize_prefix( sanitize_text_field( (string) ( $raw['prefix'] ?? '' ) ) ),
			'delete_local'     => ! empty( $raw['delete_local'] ) ? 1 : 0,
			'sanitize_output'  => ! empty( $raw['sanitize_output'] ) ? 1 : 0,
		];
	}

	public function filter_sanitize_broken_urls_output( mixed $content ): string {
		$changed = false;

		return $this->sanitize_urls_in_string( (string) $content, $changed );
	}

	private function sanitize_urls_in_string( string $value, bool &$changed ): string {
		$out        = $value;
		$normalized = str_replace( [ "\xC2\xA0", '&nbsp;', '&#160;' ], ' ', $out );
		if ( $normalized !== $out ) {
			$out = $normalized;
		}

		$json_normalized = $this->preg_replace_safe( '~\\\\+u00a0~i', ' ', $out );
		if ( is_string( $json_normalized ) ) {
			$json_normalized = $this->preg_replace_safe( '~\\\\+u0060~i', '`', $json_normalized );
			$json_normalized = $this->preg_replace_safe( '~\\\\+x60~i', '`', $json_normalized );
			if ( $json_normalized !== $out ) {
				$out = $json_normalized;
			}
		}

		$fixed_attrs = $this->preg_replace_safe( '~((?:src|href|data-[a-z0-9_-]*src)\s*=\s*)([\'"])\s*(?:`|&#96;|&#x60;|&#x0060;|&grave;|&DiacriticalGrave;|´|‘|’|&lsquo;|&rsquo;)?\s*(https?://[^`"\'\s<>]+)\s*(?:`|&#96;|&#x60;|&#x0060;|&grave;|&DiacriticalGrave;|´|‘|’|&lsquo;|&rsquo;)?\s*\2~iu', '$1$2$3$2', $out );
		if ( is_string( $fixed_attrs ) && $fixed_attrs !== $out ) {
			$out     = $fixed_attrs;
			$changed = true;
		}

		$fixed_ticks = $this->preg_replace_safe( '~(?:`|&#96;|&#x60;|&#x0060;|&grave;|&DiacriticalGrave;|´|‘|’|&lsquo;|&rsquo;)\s*(https?://[^`"\'\s<>]+)\s*(?:`|&#96;|&#x60;|&#x0060;|&grave;|&DiacriticalGrave;|´|‘|’|&lsquo;|&rsquo;)~iu', '$1', $out );
		if ( is_string( $fixed_ticks ) && $fixed_ticks !== $out ) {
			$out     = $fixed_ticks;
			$changed = true;
		}

		$fixed_ticks_escaped = $this->preg_replace_safe( '~(?:`|&#96;|&#x60;|&#x0060;|&grave;|&DiacriticalGrave;|´|‘|’|&lsquo;|&rsquo;)\s*(https?:\\\\?/\\\\?/[^`"\'\s<>]+)\s*(?:`|&#96;|&#x60;|&#x0060;|&grave;|&DiacriticalGrave;|´|‘|’|&lsquo;|&rsquo;)~iu', '$1', $out );
		if ( is_string( $fixed_ticks_escaped ) && $fixed_ticks_escaped !== $out ) {
			$out     = $fixed_ticks_escaped;
			$changed = true;
		}

		$prev_concat = null;
		while ( $prev_concat !== $out ) {
			$prev_concat = $out;
			$out         = (string) $this->preg_replace_callback_safe( '~(https?://[^"\'\s<>]*?)(https?://)~i', static fn( $m ) => $m[2], $out );
			$out         = (string) $this->preg_replace_callback_safe( '~(https?:\\\\/\\\\/[^"\'\s<>]*?)(https?:\\\\/\\\\/)~i', static fn( $m ) => $m[2], $out );
			$out         = (string) $this->preg_replace_callback_safe( '~(https?://[^"\'\s<>]*?)(https?:\\\\/\\\\/)~i', static fn( $m ) => $m[2], $out );
			$out         = (string) $this->preg_replace_callback_safe( '~(https?:\\\\/\\\\/[^"\'\s<>]*?)(https?://)~i', static fn( $m ) => $m[2], $out );
		}

		$fixed_spaces = $this->preg_replace_safe( '~((?:src|href|data-[a-z0-9_-]*src)\s*=\s*)([\'"])\s*(https?://[^"\'\s<>]+)\s*\2~iu', '$1$2$3$2', $out );
		if ( is_string( $fixed_spaces ) && $fixed_spaces !== $out ) {
			$out = $fixed_spaces;
		}

		if ( $value !== $out ) {
			$changed = true;
		}

		return $out;
	}

	private function preg_replace_safe( string $pattern, string $replacement, string $subject ): mixed {
		$result = preg_replace( $pattern, $replacement, $subject );
		if ( null === $result ) {
			$pattern2 = (string) preg_replace( '~u(?=[a-z]*$)~', '', $pattern );
			if ( $pattern2 !== $pattern ) {
				$result = preg_replace( $pattern2, $replacement, $subject );
			}
		}

		return $result;
	}

	private function preg_replace_callback_safe( string $pattern, callable $callback, string $subject ): mixed {
		$result = preg_replace_callback( $pattern, $callback, $subject );
		if ( null === $result ) {
			$pattern2 = (string) preg_replace( '~u(?=[a-z]*$)~', '', $pattern );
			if ( $pattern2 !== $pattern ) {
				$result = preg_replace_callback( $pattern2, $callback, $subject );
			}
		}

		return $result;
	}

	public function filter_unique_filename( string $filename, string $ext, string $dir, mixed $unique_filename_callback ): string {
		if ( ! $this->is_ready() ) {
			return $filename;
		}

		$uploads  = wp_upload_dir();
		$base_dir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
		if ( '' === $base_dir ) {
			return $filename;
		}

		$adapter = $this->runtime_adapter();
		if ( ! $adapter ) {
			return $filename;
		}

		$subdir = str_replace( [ $base_dir, '\\' ], [ '', '/' ], (string) $dir );
		$subdir = ltrim( $subdir, '/' );

		$path_info  = pathinfo( $filename );
		$name       = $path_info['filename'] ?? $filename;
		$extension  = ! empty( $path_info['extension'] ) ? '.' . $path_info['extension'] : '';
		$candidate  = $filename;
		$iterations = 0;

		while ( $iterations < 50 ) {
			$key = $this->build_storage_key( $this->join_key( $subdir, $candidate ) );
			if ( ! $adapter->object_exists( $key ) ) {
				return $candidate;
			}
			++ $iterations;
			$candidate = $name . '-' . $iterations . $extension;
		}

		return $candidate;
	}

	private function runtime_adapter(): ?StorageAdapterInterface {
		return $this->adapter_from_settings( $this->get_settings() );
	}

	private function build_storage_key( string $relative_key, ?array $settings = null ): string {
		$relative_key = $this->normalize_key_path( $relative_key );
		$prefix       = $this->get_object_prefix( $settings );

		if ( '' === $prefix ) {
			return $relative_key;
		}
		if ( '' === $relative_key ) {
			return $prefix;
		}

		return $prefix . '/' . $relative_key;
	}

	private function normalize_key_path( string $path ): string {
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '#/+#', '/', $path );

		return ltrim( (string) $path, '/' );
	}

	private function join_key( string $prefix, string $file ): string {
		$prefix = $this->normalize_key_path( $prefix );
		$file   = $this->normalize_key_path( $file );

		if ( '' === $prefix || '.' === $prefix ) {
			return $file;
		}
		if ( '' === $file ) {
			return $prefix;
		}

		return $prefix . '/' . $file;
	}

	public function filter_attachment_url( string $url, int $post_id ): string {
		if ( ! $this->is_ready() ) {
			return $url;
		}

		$key     = (string) get_post_meta( $post_id, self::META_KEY, true );
		$adapter = $this->runtime_adapter();

		if ( '' !== $key && $adapter ) {
			return $adapter->public_url( $key );
		}

		return $url;
	}

	public function filter_image_downsize( mixed $out, int $id, mixed $size ): mixed {
		if ( ! $this->is_ready() ) {
			return $out;
		}

		$adapter = $this->runtime_adapter();
		$meta    = wp_get_attachment_metadata( $id );

		if ( ! $adapter || empty( $meta ) || ! is_array( $meta ) ) {
			return $out;
		}

		$main_key = (string) get_post_meta( $id, self::META_KEY, true );
		if ( '' === $main_key ) {
			$main_key = $this->infer_storage_key_from_attachment( $id );
			if ( '' === $main_key ) {
				return $out;
			}
		}

		$prefix = $this->normalize_key_path( dirname( $main_key ) );
		if ( '.' === $prefix ) {
			$prefix = '';
		}

		if ( 'full' === $size || empty( $size ) ) {
			return [
				$adapter->public_url( $main_key ),
				(int) ( $meta['width'] ?? 0 ),
				(int) ( $meta['height'] ?? 0 ),
				false,
			];
		}

		$intermediate = image_get_intermediate_size( $id, $size );
		if ( empty( $intermediate ) || empty( $intermediate['file'] ) ) {
			return $out;
		}

		return [
			$adapter->public_url( $this->join_key( $prefix, (string) $intermediate['file'] ) ),
			(int) ( $intermediate['width'] ?? 0 ),
			(int) ( $intermediate['height'] ?? 0 ),
			true,
		];
	}

	private function infer_storage_key_from_attachment( int $attachment_id ): string {
		$file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( ! is_string( $file ) || '' === $file ) {
			return '';
		}

		return $this->build_storage_key( $this->normalize_key_path( $file ) );
	}

	public function filter_calculate_image_srcset( mixed $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id ): mixed {
		if ( ! $this->is_ready() || empty( $sources ) || ! $attachment_id ) {
			return $sources;
		}

		$adapter = $this->runtime_adapter();
		if ( ! $adapter || ! is_array( $sources ) ) {
			return $sources;
		}

		$main_key = (string) get_post_meta( $attachment_id, self::META_KEY, true );
		if ( '' === $main_key ) {
			$main_key = $this->infer_storage_key_from_attachment( $attachment_id );
			if ( '' === $main_key ) {
				return $sources;
			}
		}

		$prefix = $this->normalize_key_path( dirname( $main_key ) );
		if ( '.' === $prefix ) {
			$prefix = '';
		}

		foreach ( $sources as $width => $source ) {
			if ( empty( $source['url'] ) ) {
				continue;
			}

			$file                     = basename( (string) wp_parse_url( (string) $source['url'], PHP_URL_PATH ) );
			$sources[ $width ]['url'] = $adapter->public_url( $this->join_key( $prefix, $file ) );
		}

		return $sources;
	}

	public function action_delete_attachment( int $attachment_id ): void {
		if ( ! $this->is_ready() ) {
			return;
		}

		if ( ! get_post_meta( $attachment_id, self::META_OFFLOADED, true ) ) {
			return;
		}

		$adapter = $this->runtime_adapter();
		if ( ! $adapter ) {
			return;
		}

		$keys = get_post_meta( $attachment_id, self::META_FILES, true );
		if ( ! is_array( $keys ) || empty( $keys ) ) {
			$main_key = (string) get_post_meta( $attachment_id, self::META_KEY, true );
			$keys     = '' !== $main_key ? [ $main_key ] : [];
		}

		foreach ( $keys as $key ) {
			$key = (string) $key;
			if ( '' === $key ) {
				continue;
			}
			$adapter->delete_object( $key );
		}
	}

	public function ajax_test_connection(): void {
		$this->register_ajax_fatal_handler();
		check_ajax_referer( 'space_core_admin', 'nonce' );

		if ( ! $this->can_manage() ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'space-core' ) ], 403 );
		}

		$post_data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$settings  = $this->sanitize_settings( [
			'enabled'    => 1,
			'adapter'    => $post_data['adapter'] ?? 'bunny',
			'bucket'     => $post_data['bucket'] ?? '',
			'endpoint'   => $post_data['endpoint'] ?? '',
			'region'     => $post_data['region'] ?? '',
			'access_key' => $post_data['access_key'] ?? '',
			'secret_key' => $post_data['secret_key'] ?? '',
			'base_url'   => $post_data['base_url'] ?? '',
			'prefix'     => $post_data['prefix'] ?? '',
			'visibility' => $post_data['visibility'] ?? 'public',
		], true );
		$adapter   = $this->adapter_from_settings( $settings );

		if ( ! $adapter || ! $adapter->is_ready() ) {
			wp_send_json_error( [ 'message' => __( 'The adapter settings are incomplete.', 'space-core' ) ], 400 );
		}

		$result = $adapter->test_connection();

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( [ 'message' => (string) $result['message'] ] );
		}

		wp_send_json_error( [ 'message' => (string) ( $result['message'] ?? __( 'Connection test failed.', 'space-core' ) ) ], 400 );
	}

	private function register_ajax_fatal_handler(): void {
		$already = isset( $GLOBALS['sc_media_offload_ajax_fatal_handler'] ) ? (bool) $GLOBALS['sc_media_offload_ajax_fatal_handler'] : false;
		if ( $already ) {
			return;
		}
		$GLOBALS['sc_media_offload_ajax_fatal_handler'] = true;

		if ( ! defined( 'WP_SANDBOX_SCRAPING' ) ) {
			define( 'WP_SANDBOX_SCRAPING', true );
		}

		@ini_set( 'display_errors', '0' );

		register_shutdown_function( function () {
			$error = error_get_last();
			if ( ! $error || empty( $error['type'] ) ) {
				return;
			}

			$type        = (int) $error['type'];
			$fatal_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ];
			if ( ! in_array( $type, $fatal_types, true ) ) {
				return;
			}

			while ( ob_get_level() > 0 ) {
				@ob_end_clean();
			}

			$message = isset( $error['message'] ) ? (string) $error['message'] : 'fatal';
			$file    = isset( $error['file'] ) ? basename( (string) $error['file'] ) : '';
			$line    = isset( $error['line'] ) ? (int) $error['line'] : 0;
			$detail  = $message;
			if ( '' !== $file ) {
				$detail .= ' in ' . $file;
				if ( $line > 0 ) {
					$detail .= ':' . $line;
				}
			}

			error_log( 'Space Core Media Offload fatal: ' . $detail ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			if ( ! headers_sent() ) {
				header_remove();
				header( 'HTTP/1.1 500 Internal Server Error' );
				header( 'Content-Type: application/json; charset=UTF-8' );
			}

			echo wp_json_encode( [
				'success' => false,
				'data'    => [
					'message' => $detail,
					'detail'  => $detail,
				],
			] );
			exit;
		} );
	}

	public function ajax_offload_batch(): void {
		$this->register_ajax_fatal_handler();
		ob_start();

		try {
			$this->ensure_tool_request_ready();

			$last_id = isset( $_POST['last_id'] ) ? (int) $_POST['last_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$limit   = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 30; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $limit < 1 || $limit > 100 ) {
				$limit = 30;
			}

			global $wpdb;
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit' AND ID > %d ORDER BY ID ASC LIMIT %d",
					$last_id,
					$limit
				)
			);

			if ( empty( $ids ) ) {
				ob_end_clean();
				wp_send_json_success( [
					'done'            => true,
					'last_id'         => $last_id,
					'processed'       => 0,
					'offloaded'       => 0,
					'skipped'         => 0,
					'skipped_already' => 0,
					'skipped_missing' => 0,
					'retrying'        => 0,
					'failed'          => 0,
					'files_detail'    => [],
				] );
			}

			$adapter         = $this->runtime_adapter();
			$processed       = 0;
			$offloaded       = 0;
			$skipped         = 0;
			$skipped_already = 0;
			$skipped_missing = 0;
			$retrying        = 0;
			$failed          = 0;
			$new_last_id     = $last_id;
			$files_detail    = [];

			foreach ( $ids as $id ) {
				$id          = (int) $id;
				$new_last_id = $id;
				++ $processed;

				$current_file_info = [
					'id'     => $id,
					'file'   => '',
					'key'    => '',
					'status' => '',
				];

				if ( get_post_meta( $id, self::META_OFFLOADED, true ) ) {
					$key = (string) get_post_meta( $id, self::META_KEY, true );
					if ( '' !== $key && $adapter && $adapter->object_exists( $key ) ) {
						++ $skipped;
						++ $skipped_already;
						$current_file_info['key']    = $key;
						$current_file_info['status'] = 'skipped';
						$current_file_info['file']   = basename( $key );
						$files_detail[]              = $current_file_info;
						continue;
					}

					delete_post_meta( $id, self::META_OFFLOADED );
					++ $retrying;
				}

				$file_path = get_attached_file( $id, true );
				if ( ! $file_path || ! file_exists( $file_path ) ) {
					++ $skipped;
					++ $skipped_missing;
					$current_file_info['file']   = $file_path ? basename( $file_path ) : 'unknown';
					$current_file_info['status'] = 'missing';
					$files_detail[]              = $current_file_info;
					continue;
				}

				$file_size                 = @filesize( $file_path );
				$current_file_info['file'] = basename( $file_path ) . ' (' . round( (float) $file_size / 1024, 1 ) . 'KB)';

				$metadata = wp_get_attachment_metadata( $id );
				if ( empty( $metadata ) || ! is_array( $metadata ) ) {
					$metadata   = [ 'file' => $file_path ];
					$image_size = @getimagesize( $file_path );
					if ( $image_size ) {
						$metadata['width']  = $image_size[0];
						$metadata['height'] = $image_size[1];
					}
				}

				$uploads                  = wp_upload_dir();
				$relative                 = ! empty( $uploads['basedir'] ) ? $this->relative_to_uploads( $file_path, (string) $uploads['basedir'] ) : '';
				$storage_key              = $relative ? $this->build_storage_key( $this->normalize_key_path( $relative ) ) : basename( $file_path );
				$current_file_info['key'] = $storage_key;

				$before = get_post_meta( $id, self::META_OFFLOADED, true );
				$this->filter_generate_attachment_metadata( $metadata, $id );
				$after = get_post_meta( $id, self::META_OFFLOADED, true );

				if ( ! $before && $after ) {
					++ $offloaded;
					$current_file_info['status'] = 'offloaded';
				} else {
					++ $failed;
					$current_file_info['status'] = 'failed';
				}

				$files_detail[] = $current_file_info;
			}

			ob_end_clean();
			wp_send_json_success( [
				'done'            => false,
				'last_id'         => $new_last_id,
				'processed'       => $processed,
				'offloaded'       => $offloaded,
				'skipped'         => $skipped,
				'skipped_already' => $skipped_already,
				'skipped_missing' => $skipped_missing,
				'retrying'        => $retrying,
				'failed'          => $failed,
				'files_detail'    => $files_detail,
			] );
		} catch ( Throwable $e ) {
			while ( ob_get_level() > 0 ) {
				@ob_end_clean();
			}

			wp_send_json_error( [
				'message' => 'exception',
				'detail'  => substr( $e->getMessage(), 0, 400 ),
			], 500 );
		}
	}

	private function ensure_tool_request_ready(): void {
		check_ajax_referer( 'space_core_admin', 'nonce' );
		if ( ! $this->can_manage() ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		if ( ! $this->is_ready() ) {
			wp_send_json_error( [ 'message' => 'module_not_ready' ], 400 );
		}
	}

	private function relative_to_uploads( string $absolute_path, string $uploads_base_dir ): string {
		$abs  = str_replace( '\\', '/', $absolute_path );
		$base = rtrim( str_replace( '\\', '/', $uploads_base_dir ), '/' );

		if ( '' === $base || 0 !== strpos( $abs, $base . '/' ) ) {
			return '';
		}

		return ltrim( substr( $abs, strlen( $base ) ), '/' );
	}

	public function filter_generate_attachment_metadata( mixed $metadata, int $attachment_id ): mixed {
		if ( ! $this->is_ready() ) {
			return $metadata;
		}

		$adapter   = $this->runtime_adapter();
		$file_path = get_attached_file( $attachment_id, true );

		if ( ! $adapter || ! $file_path || ! file_exists( $file_path ) ) {
			return $metadata;
		}

		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return $metadata;
		}

		$relative = $this->relative_to_uploads( $file_path, (string) $uploads['basedir'] );
		if ( '' === $relative ) {
			return $metadata;
		}

		$dir_rel = $this->normalize_key_path( dirname( $relative ) );
		if ( '.' === $dir_rel ) {
			$dir_rel = '';
		}

		$files   = [];
		$files[] = [
			'abs' => $file_path,
			'key' => $this->build_storage_key( $this->normalize_key_path( $relative ) ),
		];

		if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size ) {
				if ( empty( $size['file'] ) ) {
					continue;
				}
				$files[] = [
					'abs' => trailingslashit( dirname( $file_path ) ) . $size['file'],
					'key' => $this->build_storage_key( $this->join_key( $dir_rel, (string) $size['file'] ) ),
				];
			}
		}

		if ( is_array( $metadata ) && ! empty( $metadata['original_image'] ) ) {
			$files[] = [
				'abs' => trailingslashit( dirname( $file_path ) ) . $metadata['original_image'],
				'key' => $this->build_storage_key( $this->join_key( $dir_rel, (string) $metadata['original_image'] ) ),
			];
		}

		$uploaded_keys = [];
		foreach ( $files as $entry ) {
			if ( empty( $entry['abs'] ) || empty( $entry['key'] ) || ! file_exists( $entry['abs'] ) ) {
				continue;
			}

			$ok = $adapter->put_object( (string) $entry['key'], (string) $entry['abs'], $this->detect_mime( (string) $entry['abs'] ) );
			if ( ! $ok ) {
				return $metadata;
			}
			$uploaded_keys[] = (string) $entry['key'];
		}

		update_post_meta( $attachment_id, self::META_OFFLOADED, 1 );
		update_post_meta( $attachment_id, self::META_KEY, $this->build_storage_key( $this->normalize_key_path( $relative ) ) );
		update_post_meta( $attachment_id, self::META_FILES, $uploaded_keys );

		$options = $this->get_settings();
		if ( ! empty( $options['delete_local'] ) ) {
			foreach ( $files as $entry ) {
				if ( ! empty( $entry['abs'] ) && file_exists( (string) $entry['abs'] ) ) {
					@unlink( (string) $entry['abs'] );
				}
			}
		}

		return $metadata;
	}

	private function detect_mime( string $file_path ): string {
		$check = wp_check_filetype( $file_path );

		return ! empty( $check['type'] ) ? (string) $check['type'] : 'application/octet-stream';
	}

	public function ajax_regenerate_batch(): void {
		$this->register_ajax_fatal_handler();
		ob_start();

		try {
			$this->ensure_tool_request_ready();

			$dry_run     = ! empty( $_POST['dry_run'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$last_id     = isset( $_POST['last_id'] ) ? (int) $_POST['last_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$limit       = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 10; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$max_seconds = isset( $_POST['max_seconds'] ) ? (int) $_POST['max_seconds'] : 15; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $limit < 1 || $limit > 50 ) {
				$limit = 10;
			}
			if ( $max_seconds < 5 || $max_seconds > 60 ) {
				$max_seconds = 15;
			}

			global $wpdb;
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit' AND post_mime_type LIKE 'image/%%' AND ID > %d ORDER BY ID ASC LIMIT %d",
					$last_id,
					$limit
				)
			);

			if ( empty( $ids ) ) {
				ob_end_clean();
				wp_send_json_success( [
					'done'            => true,
					'last_id'         => $last_id,
					'processed'       => 0,
					'regenerated'     => 0,
					'skipped_missing' => 0,
					'failed'          => 0,
				] );
			}

			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			$processed       = 0;
			$regenerated     = 0;
			$skipped_missing = 0;
			$failed          = 0;
			$new_last_id     = $last_id;
			$started_at      = microtime( true );

			foreach ( $ids as $id ) {
				if ( microtime( true ) - $started_at > $max_seconds && $processed > 0 ) {
					break;
				}

				$id          = (int) $id;
				$new_last_id = $id;
				++ $processed;

				$file_path = get_attached_file( $id, true );
				if ( ! $file_path || ! file_exists( $file_path ) ) {
					++ $skipped_missing;
					continue;
				}

				if ( $dry_run ) {
					continue;
				}

				$metadata = wp_generate_attachment_metadata( $id, $file_path );
				if ( empty( $metadata ) || ! is_array( $metadata ) ) {
					++ $failed;
					continue;
				}

				wp_update_attachment_metadata( $id, $metadata );
				++ $regenerated;
			}

			ob_end_clean();
			wp_send_json_success( [
				'done'            => false,
				'last_id'         => $new_last_id,
				'processed'       => $processed,
				'regenerated'     => $regenerated,
				'skipped_missing' => $skipped_missing,
				'failed'          => $failed,
			] );
		} catch ( Throwable $e ) {
			while ( ob_get_level() > 0 ) {
				@ob_end_clean();
			}

			wp_send_json_error( [
				'message' => 'exception',
				'detail'  => substr( $e->getMessage(), 0, 400 ),
			], 500 );
		}
	}

	public function ajax_restore_urls(): void {
		$this->register_ajax_fatal_handler();
		$this->ensure_tool_request_ready();

		$old_base_url = $this->get_public_base_url();
		$new_base_url = $this->get_old_base_url();

		if ( '' === $old_base_url || '' === $new_base_url ) {
			wp_send_json_error( [ 'message' => 'missing_base_url' ], 400 );
		}

		$this->execute_migration_logic( $old_base_url, $new_base_url );
	}

	private function execute_migration_logic( string $old_base_url, string $new_base_url ): void {
		$dry_run          = ! empty( $_POST['dry_run'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_post_id     = isset( $_POST['last_post_id'] ) ? (int) $_POST['last_post_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_meta_id     = isset( $_POST['last_meta_id'] ) ? (int) $_POST['last_meta_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_option_id   = isset( $_POST['last_option_id'] ) ? (int) $_POST['last_option_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_termtax_id  = isset( $_POST['last_termtax_id'] ) ? (int) $_POST['last_termtax_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_termmeta_id = isset( $_POST['last_termmeta_id'] ) ? (int) $_POST['last_termmeta_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$limit            = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 50; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $limit < 1 || $limit > 500 ) {
			$limit = 50;
		}

		$new_base_url               = $this->ensure_https( $new_base_url );
		$pairs                      = $this->build_replacement_pairs( $old_base_url, $new_base_url );
		$this->rewrite_new_base_url = $new_base_url;
		$old_needles                = [];

		foreach ( $pairs as $pair ) {
			$old_needles[] = $pair['search'];
		}

		$old_needles[] = '/wp-content/uploads';
		$old_needles[] = '\/wp-content\/uploads';

		$old_parts = wp_parse_url( $old_base_url );
		$old_host  = is_array( $old_parts ) && ! empty( $old_parts['host'] ) ? (string) $old_parts['host'] : '';
		if ( '' !== $old_host ) {
			$old_needles[] = 'https://' . $old_host . 'https://';
			$old_needles[] = 'http://' . $old_host . 'https://';
			$old_needles[] = 'https:\\/\\/' . $old_host . 'https:\\/\\/';
			$old_needles[] = 'http:\\/\\/' . $old_host . 'https:\\/\\/';
		}

		$old_needles = array_values( array_unique( $old_needles ) );

		global $wpdb;
		$posts_scanned        = 0;
		$posts_updated        = 0;
		$meta_scanned         = 0;
		$meta_updated         = 0;
		$options_scanned      = 0;
		$options_updated      = 0;
		$termtax_scanned      = 0;
		$termtax_updated      = 0;
		$termmeta_scanned     = 0;
		$termmeta_updated     = 0;
		$new_last_post_id     = $last_post_id;
		$new_last_meta_id     = $last_meta_id;
		$new_last_option_id   = $last_option_id;
		$new_last_termtax_id  = $last_termtax_id;
		$new_last_termmeta_id = $last_termmeta_id;

		$post_likes = [];
		foreach ( $old_needles as $needle ) {
			$post_likes[] = '%' . $wpdb->esc_like( $needle ) . '%';
		}
		$post_likes     = array_values( array_unique( $post_likes ) );
		$post_where_arr = [];
		foreach ( $post_likes as $like ) {
			$post_where_arr[] = '(post_content LIKE %s OR post_excerpt LIKE %s)';
		}
		$post_where = ! empty( $post_where_arr ) ? implode( ' OR ', $post_where_arr ) : '1=0';
		$post_sql   = "SELECT ID, post_content, post_excerpt FROM {$wpdb->posts} WHERE ID > %d AND ({$post_where}) ORDER BY ID ASC LIMIT %d";
		$post_args  = [ $last_post_id ];
		foreach ( $post_likes as $like ) {
			$post_args[] = $like;
			$post_args[] = $like;
		}
		$post_args[] = $limit;
		$post_rows   = $wpdb->get_results( $this->prepare_variadic( $post_sql, $post_args ) );

		foreach ( $post_rows as $row ) {
			++ $posts_scanned;
			$id               = (int) $row->ID;
			$new_last_post_id = $id;
			$changed          = false;
			$new_content      = $this->replace_in_string( (string) $row->post_content, $pairs, $changed );
			$new_excerpt      = $this->replace_in_string( (string) $row->post_excerpt, $pairs, $changed );
			if ( $changed ) {
				++ $posts_updated;
				if ( ! $dry_run ) {
					$wpdb->update( $wpdb->posts, [
						'post_content' => $new_content,
						'post_excerpt' => $new_excerpt
					], [ 'ID' => $id ], [ '%s', '%s' ], [ '%d' ] );
				}
			}
		}

		$meta_likes = [
			'%' . $wpdb->esc_like( 'wp-content/uploads' ) . '%',
			'%' . $wpdb->esc_like( 'wp-content\/uploads' ) . '%'
		];
		foreach ( array_slice( $old_needles, 0, 2 ) as $needle ) {
			$meta_likes[] = '%' . $wpdb->esc_like( $needle ) . '%';
		}
		$meta_likes = array_values( array_unique( $meta_likes ) );
		$meta_where = ! empty( $meta_likes ) ? implode( ' OR ', array_fill( 0, count( $meta_likes ), 'meta_value LIKE %s' ) ) : '1=0';
		$meta_sql   = "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_id > %d AND ({$meta_where}) ORDER BY meta_id ASC LIMIT %d";
		$meta_args  = array_merge( [ $last_meta_id ], $meta_likes, [ $limit ] );
		$meta_rows  = $wpdb->get_results( $this->prepare_variadic( $meta_sql, $meta_args ) );

		foreach ( $meta_rows as $row ) {
			++ $meta_scanned;
			$meta_id          = (int) $row->meta_id;
			$new_last_meta_id = $meta_id;
			$changed          = false;
			$new_value        = $this->replace_in_maybe_serialized( (string) $row->meta_value, $pairs, $changed );
			if ( $changed ) {
				++ $meta_updated;
				if ( ! $dry_run ) {
					$wpdb->update( $wpdb->postmeta, [ 'meta_value' => $new_value ], [ 'meta_id' => $meta_id ], [ '%s' ], [ '%d' ] );
				}
			}
		}

		$option_likes = $meta_likes;
		$option_where = ! empty( $option_likes ) ? implode( ' OR ', array_fill( 0, count( $option_likes ), 'option_value LIKE %s' ) ) : '1=0';
		$option_sql   = "SELECT option_id, option_value FROM {$wpdb->options} WHERE option_id > %d AND ({$option_where}) ORDER BY option_id ASC LIMIT %d";
		$option_args  = array_merge( [ $last_option_id ], $option_likes, [ $limit ] );
		$option_rows  = $wpdb->get_results( $this->prepare_variadic( $option_sql, $option_args ) );

		foreach ( $option_rows as $row ) {
			++ $options_scanned;
			$option_id          = (int) $row->option_id;
			$new_last_option_id = $option_id;
			$changed            = false;
			$new_value          = $this->replace_in_maybe_serialized( (string) $row->option_value, $pairs, $changed );
			if ( $changed ) {
				++ $options_updated;
				if ( ! $dry_run ) {
					$wpdb->update( $wpdb->options, [ 'option_value' => $new_value ], [ 'option_id' => $option_id ], [ '%s' ], [ '%d' ] );
				}
			}
		}

		$termtax_likes = $meta_likes;
		$termtax_where = ! empty( $termtax_likes ) ? implode( ' OR ', array_fill( 0, count( $termtax_likes ), 'description LIKE %s' ) ) : '1=0';
		$termtax_sql   = "SELECT term_taxonomy_id, description FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id > %d AND ({$termtax_where}) ORDER BY term_taxonomy_id ASC LIMIT %d";
		$termtax_args  = array_merge( [ $last_termtax_id ], $termtax_likes, [ $limit ] );
		$termtax_rows  = $wpdb->get_results( $this->prepare_variadic( $termtax_sql, $termtax_args ) );

		foreach ( $termtax_rows as $row ) {
			++ $termtax_scanned;
			$tt_id               = (int) $row->term_taxonomy_id;
			$new_last_termtax_id = $tt_id;
			$changed             = false;
			$new_desc            = $this->replace_in_string( (string) $row->description, $pairs, $changed );
			if ( $changed ) {
				++ $termtax_updated;
				if ( ! $dry_run ) {
					$wpdb->update( $wpdb->term_taxonomy, [ 'description' => $new_desc ], [ 'term_taxonomy_id' => $tt_id ], [ '%s' ], [ '%d' ] );
				}
			}
		}

		$termmeta_rows   = [];
		$termmeta_table  = isset( $wpdb->termmeta ) ? $wpdb->termmeta : $wpdb->prefix . 'termmeta';
		$termmeta_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $termmeta_table ) );
		if ( $termmeta_exists ) {
			$tm_where      = ! empty( $meta_likes ) ? implode( ' OR ', array_fill( 0, count( $meta_likes ), 'meta_value LIKE %s' ) ) : '1=0';
			$tm_sql        = "SELECT meta_id, meta_value FROM {$termmeta_table} WHERE meta_id > %d AND ({$tm_where}) ORDER BY meta_id ASC LIMIT %d";
			$tm_args       = array_merge( [ $last_termmeta_id ], $meta_likes, [ $limit ] );
			$termmeta_rows = $wpdb->get_results( $this->prepare_variadic( $tm_sql, $tm_args ) );
			foreach ( $termmeta_rows as $row ) {
				++ $termmeta_scanned;
				$meta_id              = (int) $row->meta_id;
				$new_last_termmeta_id = $meta_id;
				$changed              = false;
				$new_value            = $this->replace_in_maybe_serialized( (string) $row->meta_value, $pairs, $changed );
				if ( $changed ) {
					++ $termmeta_updated;
					if ( ! $dry_run ) {
						$wpdb->update( $termmeta_table, [ 'meta_value' => $new_value ], [ 'meta_id' => $meta_id ], [ '%s' ], [ '%d' ] );
					}
				}
			}
		}

		$done = empty( $post_rows ) && empty( $meta_rows ) && empty( $option_rows ) && empty( $termtax_rows ) && empty( $termmeta_rows );

		wp_send_json_success( [
			'done'             => $done,
			'last_post_id'     => $new_last_post_id,
			'last_meta_id'     => $new_last_meta_id,
			'last_option_id'   => $new_last_option_id,
			'last_termtax_id'  => $new_last_termtax_id,
			'last_termmeta_id' => $new_last_termmeta_id,
			'posts_scanned'    => $posts_scanned,
			'posts_updated'    => $posts_updated,
			'meta_scanned'     => $meta_scanned,
			'meta_updated'     => $meta_updated,
			'options_scanned'  => $options_scanned,
			'options_updated'  => $options_updated,
			'termtax_scanned'  => $termtax_scanned,
			'termtax_updated'  => $termtax_updated,
			'termmeta_scanned' => $termmeta_scanned,
			'termmeta_updated' => $termmeta_updated,
		] );
	}

	private function build_replacement_pairs( string $old_base_url, string $new_base_url ): array {
		$old_base_url = rtrim( $old_base_url, '/' );
		$new_base_url = rtrim( $new_base_url, '/' );
		$pairs        = [];
		$candidates   = [ $old_base_url ];

		if ( 0 === stripos( $old_base_url, 'https://' ) ) {
			$candidates[] = 'http://' . substr( $old_base_url, 8 );
		} elseif ( 0 === stripos( $old_base_url, 'http://' ) ) {
			$candidates[] = 'https://' . substr( $old_base_url, 7 );
		}

		$parts    = wp_parse_url( $old_base_url );
		$old_host = is_array( $parts ) && ! empty( $parts['host'] ) ? (string) $parts['host'] : '';
		$old_path = is_array( $parts ) && ! empty( $parts['path'] ) ? rtrim( (string) $parts['path'], '/' ) : '';
		if ( '' !== $old_host && '' !== $old_path ) {
			$candidates[] = '//' . $old_host . $old_path;
		}

		$candidates = array_values( array_unique( array_filter( $candidates ) ) );
		foreach ( $candidates as $old ) {
			$pairs[] = [ 'search' => $old, 'replace' => $new_base_url ];
			$pairs[] = [
				'search'  => str_replace( '/', '\/', $old ),
				'replace' => str_replace( '/', '\/', $new_base_url )
			];
		}

		$unique = [];
		$out    = [];
		foreach ( $pairs as $pair ) {
			$key = $pair['search'] . "\n" . $pair['replace'];
			if ( isset( $unique[ $key ] ) ) {
				continue;
			}
			$unique[ $key ] = true;
			$out[]          = $pair;
		}

		return $out;
	}

	private function prepare_variadic( string $query, array $args ): string {
		global $wpdb;

		return (string) call_user_func_array( [ $wpdb, 'prepare' ], array_merge( [ $query ], $args ) );
	}

	private function replace_in_string( string $value, array $pairs, bool &$changed ): string {
		$out = $value;
		foreach ( $pairs as $pair ) {
			$search = (string) $pair['search'];
			if ( '' === $search || false === strpos( $out, $search ) ) {
				continue;
			}
			$out     = str_replace( $search, (string) $pair['replace'], $out );
			$changed = true;
		}

		if ( '' !== $this->rewrite_new_base_url ) {
			$new = $this->rewrite_uploads_to_new_base( $out, $this->rewrite_new_base_url );
			if ( $new !== $out ) {
				$out     = $new;
				$changed = true;
			}
		}

		return $this->sanitize_urls_in_string( $out, $changed );
	}

	private function rewrite_uploads_to_new_base( string $value, string $new_base_url ): string {
		if ( '' === $new_base_url ) {
			return $value;
		}

		$new_base_url = $this->ensure_https( $new_base_url );
		$out          = preg_replace_callback(
			'~(?:(?:https?:)?//[^/]+)?(/wp-content/uploads(?:/[^"\'\s<]*)?)~i',
			static function ( array $matches ) use ( $new_base_url ) {
				$rest           = (string) $matches[1];
				$uploads_prefix = '/wp-content/uploads';
				$suffix         = 0 === strpos( $rest, $uploads_prefix ) ? substr( $rest, strlen( $uploads_prefix ) ) : $rest;

				return rtrim( $new_base_url, '/' ) . $suffix;
			},
			$value
		);

		$escaped_base = str_replace( '/', '\/', rtrim( $new_base_url, '/' ) );

		return (string) preg_replace_callback(
			'~(?:(?:https?:)?\\\\/\\\\/[^\\\\]+)?(\\\\/wp-content\\\\/uploads(?:\\\\/[^"\'\s<]*)?)~i',
			static function ( array $matches ) use ( $escaped_base ) {
				$rest           = (string) $matches[1];
				$uploads_prefix = '\/wp-content\/uploads';
				$suffix         = 0 === strpos( $rest, $uploads_prefix ) ? substr( $rest, strlen( $uploads_prefix ) ) : $rest;

				return $escaped_base . $suffix;
			},
			(string) $out
		);
	}

	private function replace_in_maybe_serialized( string $value, array $pairs, bool &$changed ): string {
		if ( '' === $value ) {
			return $value;
		}
		if ( is_serialized( $value ) ) {
			if ( preg_match( '~(^|;)[OC]:\d+:"~', $value ) ) {
				return $value;
			}
			$data         = maybe_unserialize( $value );
			$data_changed = false;
			$data         = $this->deep_replace( $data, $pairs, $data_changed );
			if ( $data_changed ) {
				$changed = true;

				return maybe_serialize( $data );
			}

			return $value;
		}

		return $this->replace_in_string( $value, $pairs, $changed );
	}

	private function deep_replace( mixed $data, array $pairs, bool &$changed ): mixed {
		if ( is_string( $data ) ) {
			return $this->replace_in_string( $data, $pairs, $changed );
		}
		if ( is_array( $data ) ) {
			$out = [];
			foreach ( $data as $key => $value ) {
				$out[ $key ] = $this->deep_replace( $value, $pairs, $changed );
			}

			return $out;
		}
		if ( is_object( $data ) ) {
			foreach ( get_object_vars( $data ) as $key => $value ) {
				$data->{$key} = $this->deep_replace( $value, $pairs, $changed );
			}
		}

		return $data;
	}

	public function ajax_migrate_urls(): void {
		$this->register_ajax_fatal_handler();
		$this->ensure_tool_request_ready();

		$old_base_url = $this->get_old_base_url();
		$new_base_url = $this->get_public_base_url();

		if ( '' === $old_base_url || '' === $new_base_url ) {
			wp_send_json_error( [ 'message' => 'missing_base_url' ], 400 );
		}

		$this->execute_migration_logic( $old_base_url, $new_base_url );
	}

	public function ajax_fix_broken_urls(): void {
		$this->register_ajax_fatal_handler();
		$this->ensure_tool_request_ready();
		$this->execute_fix_broken_urls_logic();
	}

	private function execute_fix_broken_urls_logic(): void {
		$dry_run          = ! empty( $_POST['dry_run'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_post_id     = isset( $_POST['last_post_id'] ) ? (int) $_POST['last_post_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_meta_id     = isset( $_POST['last_meta_id'] ) ? (int) $_POST['last_meta_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_option_id   = isset( $_POST['last_option_id'] ) ? (int) $_POST['last_option_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_termtax_id  = isset( $_POST['last_termtax_id'] ) ? (int) $_POST['last_termtax_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_termmeta_id = isset( $_POST['last_termmeta_id'] ) ? (int) $_POST['last_termmeta_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$limit            = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 50; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$max_seconds      = isset( $_POST['max_seconds'] ) ? (int) $_POST['max_seconds'] : 10; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $limit < 1 || $limit > 500 ) {
			$limit = 50;
		}
		if ( $max_seconds < 1 || $max_seconds > 25 ) {
			$max_seconds = 10;
		}

		global $wpdb;
		$t0                  = microtime( true );
		$failures            = 0;
		$failure_samples     = [];
		$failure_samples_max = 5;
		$examples            = [];
		$examples_max        = 5;

		$posts_scanned        = 0;
		$posts_updated        = 0;
		$meta_scanned         = 0;
		$meta_updated         = 0;
		$options_scanned      = 0;
		$options_updated      = 0;
		$termtax_scanned      = 0;
		$termtax_updated      = 0;
		$termmeta_scanned     = 0;
		$termmeta_updated     = 0;
		$new_last_post_id     = $last_post_id;
		$new_last_meta_id     = $last_meta_id;
		$new_last_option_id   = $last_option_id;
		$new_last_termtax_id  = $last_termtax_id;
		$new_last_termmeta_id = $last_termmeta_id;

		$post_rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content, post_excerpt FROM {$wpdb->posts} WHERE ID > %d ORDER BY ID ASC LIMIT %d", $last_post_id, $limit ) );
		foreach ( $post_rows as $row ) {
			if ( microtime( true ) - $t0 > $max_seconds ) {
				break;
			}
			++ $posts_scanned;
			$id               = (int) $row->ID;
			$new_last_post_id = $id;
			$changed          = false;
			$old_content      = (string) $row->post_content;
			$old_excerpt      = (string) $row->post_excerpt;
			$new_content      = $this->sanitize_urls_in_string( $old_content, $changed );
			$new_excerpt      = $this->sanitize_urls_in_string( $old_excerpt, $changed );
			if ( $changed ) {
				++ $posts_updated;
				if ( count( $examples ) < $examples_max ) {
					$examples[] = [
						'table'  => 'posts',
						'id'     => $id,
						'before' => $this->extract_urls_for_debug( $old_content . "\n" . $old_excerpt, 4 ),
						'after'  => $this->extract_urls_for_debug( $new_content . "\n" . $new_excerpt, 4 ),
					];
				}
				if ( ! $dry_run ) {
					$res = $wpdb->update( $wpdb->posts, [
						'post_content' => $new_content,
						'post_excerpt' => $new_excerpt
					], [ 'ID' => $id ], [ '%s', '%s' ], [ '%d' ] );
					if ( false === $res ) {
						++ $failures;
						if ( count( $failure_samples ) < $failure_samples_max && '' !== $wpdb->last_error ) {
							$failure_samples[] = [
								'table' => 'posts',
								'id'    => $id,
								'error' => (string) $wpdb->last_error
							];
						}
					}
				}
			}
		}

		$meta_rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_id > %d ORDER BY meta_id ASC LIMIT %d", $last_meta_id, $limit ) );
		foreach ( $meta_rows as $row ) {
			if ( microtime( true ) - $t0 > $max_seconds ) {
				break;
			}
			++ $meta_scanned;
			$meta_id          = (int) $row->meta_id;
			$new_last_meta_id = $meta_id;
			$changed          = false;
			$old_value        = (string) $row->meta_value;
			$new_value        = $this->sanitize_in_maybe_serialized( $old_value, $changed );
			if ( $changed ) {
				++ $meta_updated;
				if ( count( $examples ) < $examples_max ) {
					$examples[] = [
						'table'  => 'postmeta',
						'id'     => $meta_id,
						'before' => $this->extract_urls_for_debug( $old_value, 4 ),
						'after'  => $this->extract_urls_for_debug( $new_value, 4 ),
					];
				}
				if ( ! $dry_run ) {
					$res = $wpdb->update( $wpdb->postmeta, [ 'meta_value' => $new_value ], [ 'meta_id' => $meta_id ], [ '%s' ], [ '%d' ] );
					if ( false === $res ) {
						++ $failures;
						if ( count( $failure_samples ) < $failure_samples_max && '' !== $wpdb->last_error ) {
							$failure_samples[] = [
								'table' => 'postmeta',
								'id'    => $meta_id,
								'error' => (string) $wpdb->last_error
							];
						}
					}
				}
			}
		}

		$option_rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_id, option_value FROM {$wpdb->options} WHERE option_id > %d ORDER BY option_id ASC LIMIT %d", $last_option_id, $limit ) );
		foreach ( $option_rows as $row ) {
			if ( microtime( true ) - $t0 > $max_seconds ) {
				break;
			}
			++ $options_scanned;
			$option_id          = (int) $row->option_id;
			$new_last_option_id = $option_id;
			$changed            = false;
			$old_value          = (string) $row->option_value;
			$new_value          = $this->sanitize_in_maybe_serialized( $old_value, $changed );
			if ( $changed ) {
				++ $options_updated;
				if ( count( $examples ) < $examples_max ) {
					$examples[] = [
						'table'  => 'options',
						'id'     => $option_id,
						'before' => $this->extract_urls_for_debug( $old_value, 4 ),
						'after'  => $this->extract_urls_for_debug( $new_value, 4 ),
					];
				}
				if ( ! $dry_run ) {
					$res = $wpdb->update( $wpdb->options, [ 'option_value' => $new_value ], [ 'option_id' => $option_id ], [ '%s' ], [ '%d' ] );
					if ( false === $res ) {
						++ $failures;
						if ( count( $failure_samples ) < $failure_samples_max && '' !== $wpdb->last_error ) {
							$failure_samples[] = [
								'table' => 'options',
								'id'    => $option_id,
								'error' => (string) $wpdb->last_error
							];
						}
					}
				}
			}
		}

		$termtax_rows = $wpdb->get_results( $wpdb->prepare( "SELECT term_taxonomy_id, description FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id > %d ORDER BY term_taxonomy_id ASC LIMIT %d", $last_termtax_id, $limit ) );
		foreach ( $termtax_rows as $row ) {
			if ( microtime( true ) - $t0 > $max_seconds ) {
				break;
			}
			++ $termtax_scanned;
			$tt_id               = (int) $row->term_taxonomy_id;
			$new_last_termtax_id = $tt_id;
			$changed             = false;
			$old_desc            = (string) $row->description;
			$new_desc            = $this->sanitize_urls_in_string( $old_desc, $changed );
			if ( $changed ) {
				++ $termtax_updated;
				if ( count( $examples ) < $examples_max ) {
					$examples[] = [
						'table'  => 'term_taxonomy',
						'id'     => $tt_id,
						'before' => $this->extract_urls_for_debug( $old_desc, 4 ),
						'after'  => $this->extract_urls_for_debug( $new_desc, 4 ),
					];
				}
				if ( ! $dry_run ) {
					$res = $wpdb->update( $wpdb->term_taxonomy, [ 'description' => $new_desc ], [ 'term_taxonomy_id' => $tt_id ], [ '%s' ], [ '%d' ] );
					if ( false === $res ) {
						++ $failures;
						if ( count( $failure_samples ) < $failure_samples_max && '' !== $wpdb->last_error ) {
							$failure_samples[] = [
								'table' => 'term_taxonomy',
								'id'    => $tt_id,
								'error' => (string) $wpdb->last_error
							];
						}
					}
				}
			}
		}

		$termmeta_rows   = [];
		$termmeta_table  = isset( $wpdb->termmeta ) ? $wpdb->termmeta : $wpdb->prefix . 'termmeta';
		$termmeta_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $termmeta_table ) );
		if ( $termmeta_exists ) {
			$termmeta_rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$termmeta_table} WHERE meta_id > %d ORDER BY meta_id ASC LIMIT %d", $last_termmeta_id, $limit ) );
			foreach ( $termmeta_rows as $row ) {
				if ( microtime( true ) - $t0 > $max_seconds ) {
					break;
				}
				++ $termmeta_scanned;
				$meta_id              = (int) $row->meta_id;
				$new_last_termmeta_id = $meta_id;
				$changed              = false;
				$old_value            = (string) $row->meta_value;
				$new_value            = $this->sanitize_in_maybe_serialized( $old_value, $changed );
				if ( $changed ) {
					++ $termmeta_updated;
					if ( count( $examples ) < $examples_max ) {
						$examples[] = [
							'table'  => 'termmeta',
							'id'     => $meta_id,
							'before' => $this->extract_urls_for_debug( $old_value, 4 ),
							'after'  => $this->extract_urls_for_debug( $new_value, 4 ),
						];
					}
					if ( ! $dry_run ) {
						$res = $wpdb->update( $termmeta_table, [ 'meta_value' => $new_value ], [ 'meta_id' => $meta_id ], [ '%s' ], [ '%d' ] );
						if ( false === $res ) {
							++ $failures;
							if ( count( $failure_samples ) < $failure_samples_max && '' !== $wpdb->last_error ) {
								$failure_samples[] = [
									'table' => 'termmeta',
									'id'    => $meta_id,
									'error' => (string) $wpdb->last_error
								];
							}
						}
					}
				}
			}
		}

		$timed_out = microtime( true ) - $t0 > $max_seconds;
		$done      = ! $timed_out && empty( $post_rows ) && empty( $meta_rows ) && empty( $option_rows ) && empty( $termtax_rows ) && empty( $termmeta_rows );

		wp_send_json_success( [
			'done'             => $done,
			'last_post_id'     => $new_last_post_id,
			'last_meta_id'     => $new_last_meta_id,
			'last_option_id'   => $new_last_option_id,
			'last_termtax_id'  => $new_last_termtax_id,
			'last_termmeta_id' => $new_last_termmeta_id,
			'posts_scanned'    => $posts_scanned,
			'posts_updated'    => $posts_updated,
			'meta_scanned'     => $meta_scanned,
			'meta_updated'     => $meta_updated,
			'options_scanned'  => $options_scanned,
			'options_updated'  => $options_updated,
			'termtax_scanned'  => $termtax_scanned,
			'termtax_updated'  => $termtax_updated,
			'termmeta_scanned' => $termmeta_scanned,
			'termmeta_updated' => $termmeta_updated,
			'examples'         => $examples,
			'failures'         => $failures,
			'failure_samples'  => $failure_samples,
		] );
	}

	private function extract_urls_for_debug( string $text, int $max = 4 ): array {
		$out  = [];
		$seen = [];

		if ( preg_match_all( '~https?://[^"\'\s<>]+~iu', $text, $matches ) ) {
			foreach ( $matches[0] as $url ) {
				if ( isset( $seen[ $url ] ) ) {
					continue;
				}
				$seen[ $url ] = true;
				$out[]        = $url;
				if ( count( $out ) >= $max ) {
					return $out;
				}
			}
		}

		if ( preg_match_all( '~https?:\\\\/\\\\/[^"\'\s<>]+~iu', $text, $matches ) ) {
			foreach ( $matches[0] as $url ) {
				if ( isset( $seen[ $url ] ) ) {
					continue;
				}
				$seen[ $url ] = true;
				$out[]        = $url;
				if ( count( $out ) >= $max ) {
					return $out;
				}
			}
		}

		return $out;
	}

	private function sanitize_in_maybe_serialized( string $value, bool &$changed ): string {
		if ( '' === $value ) {
			return $value;
		}
		if ( is_serialized( $value ) ) {
			if ( preg_match( '~(^|;)[OC]:\d+:"~', $value ) ) {
				return $value;
			}
			$data         = maybe_unserialize( $value );
			$data_changed = false;
			$data         = $this->deep_sanitize( $data, $data_changed );
			if ( $data_changed ) {
				$changed = true;

				return maybe_serialize( $data );
			}

			return $value;
		}

		return $this->sanitize_urls_in_string( $value, $changed );
	}

	private function deep_sanitize( mixed $data, bool &$changed ): mixed {
		if ( is_string( $data ) ) {
			return $this->sanitize_urls_in_string( $data, $changed );
		}
		if ( is_array( $data ) ) {
			$out = [];
			foreach ( $data as $key => $value ) {
				$out[ $key ] = $this->deep_sanitize( $value, $changed );
			}

			return $out;
		}
		if ( is_object( $data ) ) {
			if ( $data instanceof __PHP_Incomplete_Class ) {
				return $data;
			}
			foreach ( get_object_vars( $data ) as $key => $value ) {
				$data->{$key} = $this->deep_sanitize( $value, $changed );
			}
		}

		return $data;
	}

	public function ajax_find_replace_text(): void {
		$this->register_ajax_fatal_handler();
		$this->ensure_tool_request_ready();

		$post_data    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$find_text    = isset( $post_data['find_text'] ) ? (string) $post_data['find_text'] : '';
		$replace_text = isset( $post_data['replace_text'] ) ? (string) $post_data['replace_text'] : '';
		if ( '' === $find_text ) {
			wp_send_json_error( [ 'message' => 'missing_find_text' ], 400 );
		}

		$this->execute_find_replace_text_logic( $find_text, $replace_text );
	}

	private function execute_find_replace_text_logic( string $find_text, string $replace_text ): void {
		$dry_run          = ! empty( $_POST['dry_run'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_post_id     = isset( $_POST['last_post_id'] ) ? (int) $_POST['last_post_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_meta_id     = isset( $_POST['last_meta_id'] ) ? (int) $_POST['last_meta_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_option_id   = isset( $_POST['last_option_id'] ) ? (int) $_POST['last_option_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_termtax_id  = isset( $_POST['last_termtax_id'] ) ? (int) $_POST['last_termtax_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_termmeta_id = isset( $_POST['last_termmeta_id'] ) ? (int) $_POST['last_termmeta_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$limit            = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 100; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$max_seconds      = isset( $_POST['max_seconds'] ) ? (int) $_POST['max_seconds'] : 12; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $limit < 1 || $limit > 500 ) {
			$limit = 100;
		}
		if ( $max_seconds < 1 || $max_seconds > 25 ) {
			$max_seconds = 12;
		}

		$pairs   = $this->build_text_replacement_pairs( $find_text, $replace_text );
		$needles = array_values( array_unique( array_filter( [ $find_text, str_replace( '/', '\/', $find_text ) ] ) ) );

		global $wpdb;
		$t0                   = microtime( true );
		$posts_scanned        = 0;
		$posts_updated        = 0;
		$meta_scanned         = 0;
		$meta_updated         = 0;
		$options_scanned      = 0;
		$options_updated      = 0;
		$termtax_scanned      = 0;
		$termtax_updated      = 0;
		$termmeta_scanned     = 0;
		$termmeta_updated     = 0;
		$new_last_post_id     = $last_post_id;
		$new_last_meta_id     = $last_meta_id;
		$new_last_option_id   = $last_option_id;
		$new_last_termtax_id  = $last_termtax_id;
		$new_last_termmeta_id = $last_termmeta_id;

		$post_likes = [];
		foreach ( array_slice( $needles, 0, 2 ) as $needle ) {
			$post_likes[] = '%' . $wpdb->esc_like( $needle ) . '%';
		}
		$post_where_arr = [];
		foreach ( $post_likes as $like ) {
			$post_where_arr[] = '(post_content LIKE %s OR post_excerpt LIKE %s)';
		}
		$post_where = ! empty( $post_where_arr ) ? implode( ' OR ', $post_where_arr ) : '1=0';
		$post_sql   = "SELECT ID, post_content, post_excerpt FROM {$wpdb->posts} WHERE ID > %d AND ({$post_where}) ORDER BY ID ASC LIMIT %d";
		$post_args  = [ $last_post_id ];
		foreach ( $post_likes as $like ) {
			$post_args[] = $like;
			$post_args[] = $like;
		}
		$post_args[] = $limit;
		$post_rows   = $wpdb->get_results( $this->prepare_variadic( $post_sql, $post_args ) );
		foreach ( $post_rows as $row ) {
			if ( microtime( true ) - $t0 > $max_seconds ) {
				break;
			}
			++ $posts_scanned;
			$id               = (int) $row->ID;
			$new_last_post_id = $id;
			$changed          = false;
			$new_content      = $this->replace_text_in_string( (string) $row->post_content, $pairs, $changed );
			$new_excerpt      = $this->replace_text_in_string( (string) $row->post_excerpt, $pairs, $changed );
			if ( $changed ) {
				++ $posts_updated;
				if ( ! $dry_run ) {
					$wpdb->update( $wpdb->posts, [
						'post_content' => $new_content,
						'post_excerpt' => $new_excerpt
					], [ 'ID' => $id ], [ '%s', '%s' ], [ '%d' ] );
				}
			}
		}

		$meta_where = ! empty( $post_likes ) ? implode( ' OR ', array_fill( 0, count( $post_likes ), 'meta_value LIKE %s' ) ) : '1=0';
		$meta_sql   = "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_id > %d AND ({$meta_where}) ORDER BY meta_id ASC LIMIT %d";
		$meta_args  = array_merge( [ $last_meta_id ], $post_likes, [ $limit ] );
		$meta_rows  = $wpdb->get_results( $this->prepare_variadic( $meta_sql, $meta_args ) );
		foreach ( $meta_rows as $row ) {
			if ( microtime( true ) - $t0 > $max_seconds ) {
				break;
			}
			++ $meta_scanned;
			$id               = (int) $row->meta_id;
			$new_last_meta_id = $id;
			$changed          = false;
			$new_value        = $this->replace_text_in_maybe_serialized( (string) $row->meta_value, $pairs, $changed );
			if ( $changed ) {
				++ $meta_updated;
				if ( ! $dry_run ) {
					$wpdb->update( $wpdb->postmeta, [ 'meta_value' => $new_value ], [ 'meta_id' => $id ], [ '%s' ], [ '%d' ] );
				}
			}
		}

		$option_where = ! empty( $post_likes ) ? implode( ' OR ', array_fill( 0, count( $post_likes ), 'option_value LIKE %s' ) ) : '1=0';
		$option_sql   = "SELECT option_id, option_value FROM {$wpdb->options} WHERE option_id > %d AND ({$option_where}) ORDER BY option_id ASC LIMIT %d";
		$option_args  = array_merge( [ $last_option_id ], $post_likes, [ $limit ] );
		$option_rows  = $wpdb->get_results( $this->prepare_variadic( $option_sql, $option_args ) );
		foreach ( $option_rows as $row ) {
			if ( microtime( true ) - $t0 > $max_seconds ) {
				break;
			}
			++ $options_scanned;
			$id                 = (int) $row->option_id;
			$new_last_option_id = $id;
			$changed            = false;
			$new_value          = $this->replace_text_in_maybe_serialized( (string) $row->option_value, $pairs, $changed );
			if ( $changed ) {
				++ $options_updated;
				if ( ! $dry_run ) {
					$wpdb->update( $wpdb->options, [ 'option_value' => $new_value ], [ 'option_id' => $id ], [ '%s' ], [ '%d' ] );
				}
			}
		}

		$termtax_where = ! empty( $post_likes ) ? implode( ' OR ', array_fill( 0, count( $post_likes ), 'description LIKE %s' ) ) : '1=0';
		$termtax_sql   = "SELECT term_taxonomy_id, description FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id > %d AND ({$termtax_where}) ORDER BY term_taxonomy_id ASC LIMIT %d";
		$termtax_args  = array_merge( [ $last_termtax_id ], $post_likes, [ $limit ] );
		$termtax_rows  = $wpdb->get_results( $this->prepare_variadic( $termtax_sql, $termtax_args ) );
		foreach ( $termtax_rows as $row ) {
			if ( microtime( true ) - $t0 > $max_seconds ) {
				break;
			}
			++ $termtax_scanned;
			$id                  = (int) $row->term_taxonomy_id;
			$new_last_termtax_id = $id;
			$changed             = false;
			$new_desc            = $this->replace_text_in_string( (string) $row->description, $pairs, $changed );
			if ( $changed ) {
				++ $termtax_updated;
				if ( ! $dry_run ) {
					$wpdb->update( $wpdb->term_taxonomy, [ 'description' => $new_desc ], [ 'term_taxonomy_id' => $id ], [ '%s' ], [ '%d' ] );
				}
			}
		}

		$termmeta_rows   = [];
		$termmeta_table  = isset( $wpdb->termmeta ) ? $wpdb->termmeta : $wpdb->prefix . 'termmeta';
		$termmeta_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $termmeta_table ) );
		if ( $termmeta_exists ) {
			$termmeta_where = ! empty( $post_likes ) ? implode( ' OR ', array_fill( 0, count( $post_likes ), 'meta_value LIKE %s' ) ) : '1=0';
			$termmeta_sql   = "SELECT meta_id, meta_value FROM {$termmeta_table} WHERE meta_id > %d AND ({$termmeta_where}) ORDER BY meta_id ASC LIMIT %d";
			$termmeta_args  = array_merge( [ $last_termmeta_id ], $post_likes, [ $limit ] );
			$termmeta_rows  = $wpdb->get_results( $this->prepare_variadic( $termmeta_sql, $termmeta_args ) );
			foreach ( $termmeta_rows as $row ) {
				if ( microtime( true ) - $t0 > $max_seconds ) {
					break;
				}
				++ $termmeta_scanned;
				$id                   = (int) $row->meta_id;
				$new_last_termmeta_id = $id;
				$changed              = false;
				$new_value            = $this->replace_text_in_maybe_serialized( (string) $row->meta_value, $pairs, $changed );
				if ( $changed ) {
					++ $termmeta_updated;
					if ( ! $dry_run ) {
						$wpdb->update( $termmeta_table, [ 'meta_value' => $new_value ], [ 'meta_id' => $id ], [ '%s' ], [ '%d' ] );
					}
				}
			}
		}

		$timed_out = microtime( true ) - $t0 > $max_seconds;
		$done      = ! $timed_out && count( $post_rows ) < $limit && count( $meta_rows ) < $limit && count( $option_rows ) < $limit && count( $termtax_rows ) < $limit && count( $termmeta_rows ) < $limit;

		wp_send_json_success( [
			'done'             => $done,
			'last_post_id'     => $new_last_post_id,
			'last_meta_id'     => $new_last_meta_id,
			'last_option_id'   => $new_last_option_id,
			'last_termtax_id'  => $new_last_termtax_id,
			'last_termmeta_id' => $new_last_termmeta_id,
			'posts_scanned'    => $posts_scanned,
			'posts_updated'    => $posts_updated,
			'meta_scanned'     => $meta_scanned,
			'meta_updated'     => $meta_updated,
			'options_scanned'  => $options_scanned,
			'options_updated'  => $options_updated,
			'termtax_scanned'  => $termtax_scanned,
			'termtax_updated'  => $termtax_updated,
			'termmeta_scanned' => $termmeta_scanned,
			'termmeta_updated' => $termmeta_updated,
		] );
	}

	private function build_text_replacement_pairs( string $find_text, string $replace_text ): array {
		return [
			[ 'search' => $find_text, 'replace' => $replace_text ],
			[ 'search' => str_replace( '/', '\/', $find_text ), 'replace' => str_replace( '/', '\/', $replace_text ) ],
		];
	}

	private function replace_text_in_string( string $value, array $pairs, bool &$changed ): string {
		$out = $value;
		foreach ( $pairs as $pair ) {
			$search = (string) $pair['search'];
			if ( '' === $search || false === strpos( $out, $search ) ) {
				continue;
			}
			$out     = str_replace( $search, (string) $pair['replace'], $out );
			$changed = true;
		}

		return $out;
	}

	private function replace_text_in_maybe_serialized( string $value, array $pairs, bool &$changed ): string {
		if ( '' === $value ) {
			return $value;
		}
		if ( is_serialized( $value ) ) {
			if ( preg_match( '~(^|;)[OC]:\d+:"~', $value ) ) {
				return $value;
			}
			$data         = maybe_unserialize( $value );
			$data_changed = false;
			$data         = $this->deep_replace_text( $data, $pairs, $data_changed );
			if ( $data_changed ) {
				$changed = true;

				return maybe_serialize( $data );
			}

			return $value;
		}

		return $this->replace_text_in_string( $value, $pairs, $changed );
	}

	private function deep_replace_text( mixed $data, array $pairs, bool &$changed ): mixed {
		if ( is_string( $data ) ) {
			return $this->replace_text_in_string( $data, $pairs, $changed );
		}
		if ( is_array( $data ) ) {
			$out = [];
			foreach ( $data as $key => $value ) {
				$out[ $key ] = $this->deep_replace_text( $value, $pairs, $changed );
			}

			return $out;
		}
		if ( is_object( $data ) ) {
			if ( $data instanceof __PHP_Incomplete_Class ) {
				return $data;
			}
			foreach ( get_object_vars( $data ) as $key => $value ) {
				$data->{$key} = $this->deep_replace_text( $value, $pairs, $changed );
			}
		}

		return $data;
	}

	private function normalize_base_url_root( string $url ): string {
		$url    = rtrim( $url, '/' );
		$prefix = $this->get_object_prefix();
		if ( '' !== $prefix ) {
			$suffix = '/' . $prefix;
			if ( str_ends_with( $url, $suffix ) ) {
				$url = rtrim( substr( $url, 0, - strlen( $suffix ) ), '/' );
			}
		}

		return $url;
	}
}
