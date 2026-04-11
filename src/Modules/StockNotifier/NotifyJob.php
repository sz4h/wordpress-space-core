<?php

namespace Space\Core\Modules\StockNotifier;

defined( 'ABSPATH' ) || exit;

use Space\Core\Modules\StockNotifier\Channels\EmailChannel;
use Space\Core\Modules\StockNotifier\Channels\SmsChannel;
use Space\Core\Modules\StockNotifier\Channels\WhatsappChannel;
use WP_Query;

/**
 * Handles the WP-Cron notification job.
 */
class NotifyJob {

	private array $opts;

	public function __construct( array $opts ) {
		$this->opts = $opts;
	}

	public function run(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Find products that are in stock and have pending subscribers.
		$in_stock_ids = $this->get_in_stock_product_ids();
		if ( empty( $in_stock_ids ) ) {
			return;
		}

		$subscribers = SubscriberDB::get_pending( $in_stock_ids );
		if ( empty( $subscribers ) ) {
			return;
		}

		foreach ( $subscribers as $row ) {
			$sent = $this->dispatch(
				(int) $row['product_id'],
				(string) $row['contact'],
				(string) $row['channel'],
				(string) $row['lang']
			);
			if ( $sent ) {
				SubscriberDB::mark_notified( (int) $row['id'] );
			}
		}
	}

	private function get_in_stock_product_ids(): array {
		$query = new WP_Query( [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'   => '_stock_status',
					'value' => 'instock',
				],
			],
		] );

		return $query->posts ?: [];
	}

	private function dispatch( int $product_id, string $contact, string $channel, string $lang ): bool {
		return match ( $channel ) {
			'email' => ( new EmailChannel( $this->opts ) )->send( $contact, $product_id, $lang ),
			'sms' => ( new SmsChannel( $this->opts ) )->send( $contact, $product_id, $lang ),
			'whatsapp' => ( new WhatsappChannel( $this->opts ) )->send( $contact, $product_id, $lang ),
			default => false,
		};
	}
}
