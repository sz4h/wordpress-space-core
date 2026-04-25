<?php

namespace Space\Core\Modules\WPMLTranslate;

defined( 'ABSPATH' ) || exit;

class PostmanExporter {

	/**
	 * @param array<int, array{slug: string, name: string, default: bool}> $languages
	 */
	public function __construct( private array $languages ) {
	}

	public function build(): array {
		return [
			'info'     => $this->info(),
			'item'     => $this->items(),
			'auth'     => $this->auth(),
			'event'    => $this->empty_events(),
			'variable' => $this->variables(),
		];
	}

	public function json(): string {
		return wp_json_encode( $this->build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	private function info(): array {
		return [
			'_postman_id' => wp_generate_uuid4(),
			'name'        => 'Space Core — WPMLTranslate Routes',
			'schema'      => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
		];
	}

	private function items(): array {
		return [
			$this->get_jobs(),
			$this->get_payload(),
		];
	}

	private function auth(): array {
		return [
			'type'  => 'basic',
			'basic' => [
				[ 'key' => 'username', 'value' => '{{username}}', 'type' => 'string' ],
				[ 'key' => 'password', 'value' => '{{password}}', 'type' => 'string' ],
			],
		];
	}

	private function empty_events(): array {
		$emptyScript = [
			'type'     => 'text/javascript',
			'packages' => (object) [],
			'exec'     => [ '' ],
		];

		return [
			[ 'listen' => 'prerequest', 'script' => $emptyScript ],
			[ 'listen' => 'test', 'script' => $emptyScript ],
		];
	}

	private function variables(): array {
		$source = $this->find_default_lang() ?? 'en';
		$target = $this->find_non_default_lang() ?? 'ar';

		return [
			[ 'key' => 'site', 'value' => get_site_url(), 'type' => 'string' ],
			[ 'key' => 'username', 'value' => '', 'type' => 'string' ],
			[ 'key' => 'password', 'value' => '', 'type' => 'string' ],
			[ 'key' => 'job_id', 'value' => '1', 'type' => 'string' ],
			[ 'key' => 'status', 'value' => 'waiting_for_translator,in_progress,translation_ready_to_download', 'type' => 'string' ],
			[ 'key' => 'post_type', 'value' => post_type_exists( 'product' ) ? 'product' : 'post', 'type' => 'string' ],
			[ 'key' => 'source_lang', 'value' => $source, 'type' => 'string' ],
			[ 'key' => 'target_lang', 'value' => $target, 'type' => 'string' ],
			[ 'key' => 'limit', 'value' => '25', 'type' => 'string' ],
		];
	}

	private function get_jobs(): array {
		return [
			'name'     => 'Get WPML Jobs',
			'request'  => [
				'method' => 'GET',
				'header' => [],
				'url'    => [
					'raw'   => '{{site}}/wp-json/space-core/v1/wpml-translate/jobs?status={{status}}&post_type={{post_type}}&source_lang={{source_lang}}&target_lang={{target_lang}}&limit={{limit}}',
					'host'  => [ '{{site}}' ],
					'path'  => [ 'wp-json', 'space-core', 'v1', 'wpml-translate', 'jobs' ],
					'query' => [
						[ 'key' => 'status', 'value' => '{{status}}' ],
						[ 'key' => 'post_type', 'value' => '{{post_type}}' ],
						[ 'key' => 'source_lang', 'value' => '{{source_lang}}' ],
						[ 'key' => 'target_lang', 'value' => '{{target_lang}}' ],
						[ 'key' => 'limit', 'value' => '{{limit}}' ],
					],
				],
			],
			'response' => [],
		];
	}

	private function get_payload(): array {
		return [
			'name'     => 'Get WPML Job Payload',
			'request'  => [
				'method' => 'GET',
				'header' => [],
				'url'    => [
					'raw'      => '{{site}}/wp-json/space-core/v1/wpml-translate/jobs/:job_id/payload',
					'host'     => [ '{{site}}' ],
					'path'     => [ 'wp-json', 'space-core', 'v1', 'wpml-translate', 'jobs', ':job_id', 'payload' ],
					'variable' => [
						[ 'key' => 'job_id', 'value' => '{{job_id}}', 'description' => 'WPML translation job ID' ],
					],
				],
			],
			'response' => [],
		];
	}

	private function find_default_lang(): ?string {
		foreach ( $this->languages as $language ) {
			if ( ! empty( $language['default'] ) ) {
				return (string) $language['slug'];
			}
		}

		return null;
	}

	private function find_non_default_lang(): ?string {
		foreach ( $this->languages as $language ) {
			if ( empty( $language['default'] ) ) {
				return (string) $language['slug'];
			}
		}

		return null;
	}
}
