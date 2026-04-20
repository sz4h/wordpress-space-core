<?php

namespace Space\Core\Modules\Translation;

defined( 'ABSPATH' ) || exit;

class PostmanExporter {

	private string $source_lang;
	private string $target_lang;
	private string $taxonomy;

	/**
	 * @param array<int, array{slug: string, name: string, default: bool}> $languages
	 */
	public function __construct( private array $languages ) {
		$default = $this->find_default_lang();
		$other   = $this->find_non_default_lang();

		$this->source_lang = $default ?? 'en';
		$this->target_lang = $other  ?? 'ar';
		$this->taxonomy    = 'product_cat';
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

	// -------------------------------------------------------------------------

	private function info(): array {
		return [
			'_postman_id' => wp_generate_uuid4(),
			'name'        => 'Space Core — Translation Routes',
			'schema'      => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
		];
	}

	private function items(): array {
		return [
			$this->get_terms(),
			$this->post_terms(),
			$this->get_menus(),
			$this->get_menus_by_id(),
			$this->post_menus(),
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
		$empty_script = [
			'type'     => 'text/javascript',
			'packages' => (object) [],
			'exec'     => [ '' ],
		];

		return [
			[ 'listen' => 'prerequest', 'script' => $empty_script ],
			[ 'listen' => 'test',       'script' => $empty_script ],
		];
	}

	private function variables(): array {
		return [
			[ 'key' => 'site',     'value' => get_site_url(), 'type' => 'string' ],
			[ 'key' => 'username', 'value' => '',              'type' => 'string' ],
			[ 'key' => 'password', 'value' => '',              'type' => 'string' ],
		];
	}

	// -------------------------------------------------------------------------
	// Individual request builders
	// -------------------------------------------------------------------------

	private function get_terms(): array {
		$path  = [ 'wp-json', 'space-core', 'v1', $this->source_lang, 'translation', $this->taxonomy, 'terms' ];
		$query = [ [ 'key' => 'target_lang', 'value' => $this->target_lang ] ];
		$raw   = '{{site}}/wp-json/space-core/v1/' . $this->source_lang . '/translation/' . $this->taxonomy . '/terms?target_lang=' . $this->target_lang;

		return $this->get_item(
			'Get Terms — missing ' . strtoupper( $this->target_lang ) . ' translation',
			'GET',
			$path,
			$query,
			$raw
		);
	}

	private function post_terms(): array {
		$path = [ 'wp-json', 'space-core', 'v1', $this->target_lang, 'translation', $this->taxonomy, 'terms' ];
		$raw  = '{{site}}/wp-json/space-core/v1/' . $this->target_lang . '/translation/' . $this->taxonomy . '/terms';

		$body_items = $this->example_term_body();

		return $this->post_item(
			'Set Terms Translation — ' . strtoupper( $this->target_lang ),
			$path,
			$raw,
			$body_items
		);
	}

	private function get_menus(): array {
		$path  = [ 'wp-json', 'space-core', 'v1', $this->source_lang, 'translation', 'menus' ];
		$query = [ [ 'key' => 'target_lang', 'value' => $this->target_lang ] ];
		$raw   = '{{site}}/wp-json/space-core/v1/' . $this->source_lang . '/translation/menus?target_lang=' . $this->target_lang;

		return $this->get_item(
			'Get Menu Items — missing ' . strtoupper( $this->target_lang ) . ' translation',
			'GET',
			$path,
			$query,
			$raw
		);
	}

	private function get_menus_by_id(): array {
		$path  = [ 'wp-json', 'space-core', 'v1', $this->source_lang, 'translation', 'menus', ':menu_id' ];
		$query = [ [ 'key' => 'target_lang', 'value' => $this->target_lang ] ];
		$raw   = '{{site}}/wp-json/space-core/v1/' . $this->source_lang . '/translation/menus/:menu_id?target_lang=' . $this->target_lang;

		$item = $this->get_item(
			'Get Menu Items by Menu ID — missing ' . strtoupper( $this->target_lang ) . ' translation',
			'GET',
			$path,
			$query,
			$raw
		);

		$item['request']['url']['variable'] = [
			[ 'key' => 'menu_id', 'value' => '1', 'description' => 'Nav menu term ID' ],
		];

		return $item;
	}

	private function post_menus(): array {
		$path = [ 'wp-json', 'space-core', 'v1', $this->target_lang, 'translation', 'menus' ];
		$raw  = '{{site}}/wp-json/space-core/v1/' . $this->target_lang . '/translation/menus';

		$body_items = [
			[
				'id'        => 1,
				'title'     => 'الرئيسية',
				'url'       => get_site_url() . '/' . $this->target_lang . '/',
				'menu_id'   => 1,
				'menu_name' => 'Primary',
			],
		];

		return $this->post_item(
			'Set Menu Items Translation — ' . strtoupper( $this->target_lang ),
			$path,
			$raw,
			$body_items
		);
	}

	// -------------------------------------------------------------------------
	// Structural helpers
	// -------------------------------------------------------------------------

	private function get_item( string $name, string $method, array $path, array $query, string $raw ): array {
		return [
			'name'     => $name,
			'request'  => [
				'method' => $method,
				'header' => [],
				'url'    => [
					'raw'   => $raw,
					'host'  => [ '{{site}}' ],
					'path'  => $path,
					'query' => $query,
				],
			],
			'response' => [],
		];
	}

	private function post_item( string $name, array $path, string $raw, array $body_items ): array {
		return [
			'name'     => $name,
			'request'  => [
				'method' => 'POST',
				'header' => [],
				'body'   => [
					'mode'    => 'raw',
					'raw'     => wp_json_encode( $body_items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
					'options' => [
						'raw' => [ 'language' => 'json' ],
					],
				],
				'url'    => [
					'raw'  => $raw,
					'host' => [ '{{site}}' ],
					'path' => $path,
				],
			],
			'response' => [],
		];
	}

	private function example_term_body(): array {
		return [
			[
				'id'   => 1,
				'name' => 'غير مصنف',
				'slug' => 'غير-مصنف',
			],
		];
	}

	// -------------------------------------------------------------------------

	private function find_default_lang(): ?string {
		foreach ( $this->languages as $lang ) {
			if ( $lang['default'] ) {
				return $lang['slug'];
			}
		}
		return null;
	}

	private function find_non_default_lang(): ?string {
		foreach ( $this->languages as $lang ) {
			if ( ! $lang['default'] ) {
				return $lang['slug'];
			}
		}
		return null;
	}
}
