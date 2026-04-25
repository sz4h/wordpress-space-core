<?php

namespace Space\Core\Modules\WPMLTranslate;

defined( 'ABSPATH' ) || exit;

class Watcher {

	/** @var array<string, true> */
	private array $sentJobs = [];

	public function capture_sent_post_jobs( mixed $batch, mixed $type = 'post', mixed $send_from = null ): void {
		unset( $send_from );

		if ( 'post' !== $type || ! is_object( $batch ) || ! method_exists( $batch, 'get_elements' ) ) {
			return;
		}

		foreach ( (array) $batch->get_elements() as $element ) {
			if ( ! is_object( $element ) || ! method_exists( $element, 'get_element_id' ) || ! method_exists( $element, 'get_source_lang' ) || ! method_exists( $element, 'get_target_langs' ) ) {
				continue;
			}

			$sourcePostId = (int) $element->get_element_id();
			$sourceLang   = (string) $element->get_source_lang();
			$targetLangs  = $element->get_target_langs();

			if ( ! is_array( $targetLangs ) ) {
				continue;
			}

			foreach ( array_keys( $targetLangs ) as $targetLang ) {
				$this->sentJobs[ $this->buildKey( $sourcePostId, $sourceLang, (string) $targetLang ) ] = true;
			}
		}
	}

	public function was_sent_in_request( int $sourcePostId, string $sourceLang, string $targetLang ): bool {
		return isset( $this->sentJobs[ $this->buildKey( $sourcePostId, $sourceLang, $targetLang ) ] );
	}

	private function buildKey( int $sourcePostId, string $sourceLang, string $targetLang ): string {
		return $sourcePostId . ':' . $sourceLang . ':' . $targetLang;
	}
}
