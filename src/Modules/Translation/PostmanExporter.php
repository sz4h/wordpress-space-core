<?php

namespace Space\Core\Modules\Translation;

defined( 'ABSPATH' ) || exit;

class PostmanExporter {

	/**
	 * @param array<int, array{slug: string, name: string, default: bool}> $languages
	 */
	public function __construct( private array $languages ) {}

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
			$this->get_schema(),
			$this->folder( 'Terms',                   [ $this->get_terms(),  $this->post_terms()  ] ),
			$this->folder( 'Menus',                   [ $this->get_menus(),  $this->get_menus_by_id(), $this->post_menus() ] ),
			$this->folder( 'Posts / Pages / Products', [ $this->get_posts(), $this->post_posts()  ] ),
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
		$default = $this->find_default_lang()     ?? 'en';
		$other   = $this->find_non_default_lang() ?? 'ar';
		$pt      = post_type_exists( 'product' ) ? 'product' : 'post';

		return [
			[ 'key' => 'site',        'value' => get_site_url(), 'type' => 'string' ],
			[ 'key' => 'username',    'value' => '',              'type' => 'string' ],
			[ 'key' => 'password',    'value' => '',              'type' => 'string' ],
			[ 'key' => 'source_lang', 'value' => $default,        'type' => 'string' ],
			[ 'key' => 'target_lang', 'value' => $other,          'type' => 'string' ],
			[ 'key' => 'taxonomy',    'value' => 'product_cat',   'type' => 'string' ],
			[ 'key' => 'post_type',   'value' => $pt,             'type' => 'string' ],
			[ 'key' => 'menu_id',     'value' => '1',             'type' => 'string' ],
		];
	}

	// -------------------------------------------------------------------------
	// Individual request builders
	// -------------------------------------------------------------------------

	private function get_schema(): array {
		return $this->get_item(
			'Get Translation Schema',
			'GET',
			[ 'wp-json', 'space-core', 'v1', 'translation', 'schema' ],
			[],
			'{{site}}/wp-json/space-core/v1/translation/schema'
		);
	}

	private function get_terms(): array {
		$path = [ 'wp-json', 'space-core', 'v1', ':source_lang', 'translation', ':taxonomy', 'terms' ];
		$raw  = '{{site}}/wp-json/space-core/v1/:source_lang/translation/:taxonomy/terms?target_lang={{target_lang}}';

		$item = $this->get_item( 'Get Terms — missing translation', 'GET', $path, [ [ 'key' => 'target_lang', 'value' => '{{target_lang}}' ] ], $raw );

		$item['request']['url']['variable'] = [
			[ 'key' => 'source_lang', 'value' => '{{source_lang}}', 'description' => 'Language slug of the source terms' ],
			[ 'key' => 'taxonomy',    'value' => '{{taxonomy}}',    'description' => 'Taxonomy slug, e.g. product_cat' ],
		];

		return $item;
	}

	private function post_terms(): array {
		$path = [ 'wp-json', 'space-core', 'v1', ':target_lang', 'translation', ':taxonomy', 'terms' ];
		$raw  = '{{site}}/wp-json/space-core/v1/:target_lang/translation/:taxonomy/terms';

		$item = $this->post_item( 'Set Terms Translation', $path, $raw, $this->example_term_body() );

		$item['request']['url']['variable'] = [
			[ 'key' => 'target_lang', 'value' => '{{target_lang}}', 'description' => 'Language slug to create translations in' ],
			[ 'key' => 'taxonomy',    'value' => '{{taxonomy}}',    'description' => 'Taxonomy slug, e.g. product_cat' ],
		];

		return $item;
	}

	private function get_menus(): array {
		$path = [ 'wp-json', 'space-core', 'v1', ':source_lang', 'translation', 'menus' ];
		$raw  = '{{site}}/wp-json/space-core/v1/:source_lang/translation/menus?target_lang={{target_lang}}';

		$item = $this->get_item( 'Get Menu Items — missing translation', 'GET', $path, [ [ 'key' => 'target_lang', 'value' => '{{target_lang}}' ] ], $raw );

		$item['request']['url']['variable'] = [
			[ 'key' => 'source_lang', 'value' => '{{source_lang}}', 'description' => 'Language slug of the source menu items' ],
		];

		return $item;
	}

	private function get_menus_by_id(): array {
		$path = [ 'wp-json', 'space-core', 'v1', ':source_lang', 'translation', 'menus', ':menu_id' ];
		$raw  = '{{site}}/wp-json/space-core/v1/:source_lang/translation/menus/:menu_id?target_lang={{target_lang}}';

		$item = $this->get_item( 'Get Menu Items by Menu ID — missing translation', 'GET', $path, [ [ 'key' => 'target_lang', 'value' => '{{target_lang}}' ] ], $raw );

		$item['request']['url']['variable'] = [
			[ 'key' => 'source_lang', 'value' => '{{source_lang}}', 'description' => 'Language slug of the source menu items' ],
			[ 'key' => 'menu_id',     'value' => '{{menu_id}}',     'description' => 'Nav menu term ID' ],
		];

		return $item;
	}

	private function post_menus(): array {
		$path = [ 'wp-json', 'space-core', 'v1', ':target_lang', 'translation', 'menus' ];
		$raw  = '{{site}}/wp-json/space-core/v1/:target_lang/translation/menus';

		$body = [
			[
				'id'        => 1,
				'title'     => 'الرئيسية',
				'url'       => get_site_url() . '/{{target_lang}}/',
				'menu_id'   => 1,
				'menu_name' => 'Primary',
			],
		];

		$item = $this->post_item( 'Set Menu Items Translation', $path, $raw, $body );

		$item['request']['url']['variable'] = [
			[ 'key' => 'target_lang', 'value' => '{{target_lang}}', 'description' => 'Language slug to create translations in' ],
		];

		return $item;
	}

	private function get_posts(): array {
		$path = [ 'wp-json', 'space-core', 'v1', ':source_lang', 'translation', ':post_type', 'posts' ];
		$raw  = '{{site}}/wp-json/space-core/v1/:source_lang/translation/:post_type/posts?target_lang={{target_lang}}';

		$item = $this->get_item( 'Get Posts — missing translation', 'GET', $path, [ [ 'key' => 'target_lang', 'value' => '{{target_lang}}' ] ], $raw );

		$item['request']['url']['variable'] = [
			[ 'key' => 'source_lang', 'value' => '{{source_lang}}', 'description' => 'Language slug of the source posts' ],
			[ 'key' => 'post_type',   'value' => '{{post_type}}',   'description' => 'Post type slug, e.g. product, post, page' ],
		];

		return $item;
	}

	private function post_posts(): array {
		$path = [ 'wp-json', 'space-core', 'v1', ':target_lang', 'translation', ':post_type', 'posts' ];
		$raw  = '{{site}}/wp-json/space-core/v1/:target_lang/translation/:post_type/posts';

		$body = [
			[
				'id'      => 1,
				'title'   => 'اسم المنتج',
				'slug'    => 'asm-almntj',
				'excerpt' => 'وصف قصير للمنتج',
				'content' => 'المحتوى الكامل للمنتج هنا',
				'meta'    => [
					'_yoast_wpseo_title'    => 'عنوان SEO',
					'_yoast_wpseo_metadesc' => 'وصف ميتا للمنتج',
				],
			],
		];

		$item = $this->post_item( 'Set Post Translation', $path, $raw, $body );

		$item['request']['url']['variable'] = [
			[ 'key' => 'target_lang', 'value' => '{{target_lang}}', 'description' => 'Language slug to create translations in' ],
			[ 'key' => 'post_type',   'value' => '{{post_type}}',   'description' => 'Post type slug, e.g. product, post, page' ],
		];

		return $item;
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
					'options' => [ 'raw' => [ 'language' => 'json' ] ],
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

	private function folder( string $name, array $items ): array {
		return [ 'name' => $name, 'item' => $items ];
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
