<?php
declare(strict_types=1);

namespace CB\Docs\Frontend;

defined( 'ABSPATH' ) || exit;

final class RestSearch {
	public const NAMESPACE = 'cb-docs/v1';
	public const ROUTE     = '/search';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register' ] );
	}

	public static function register(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'search' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'q' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'limit' => [
						'default'           => 20,
						'sanitize_callback' => 'absint',
					],
					'category' => [
						'default'           => '',
						'sanitize_callback' => 'sanitize_title',
					],
					'tag' => [
						'default'           => '',
						'sanitize_callback' => 'sanitize_title',
					],
				],
			]
		);
	}

	public static function search( \WP_REST_Request $request ): \WP_REST_Response {
		$query    = sanitize_text_field( trim( (string) $request->get_param( 'q' ) ) );
		$limit    = max( 1, min( 50, absint( $request->get_param( 'limit' ) ) ?: 20 ) );
		$category = sanitize_title( (string) $request->get_param( 'category' ) );
		$tag      = sanitize_title( (string) $request->get_param( 'tag' ) );

		$result = Search::documents(
			$query,
			$limit,
			[
				'category' => $category,
				'tag'      => $tag,
			]
		);

		$items = [];
		foreach ( $result['items'] as $item ) {
			$categories = [];
			foreach ( (array) ( $item['categories'] ?? [] ) as $term ) {
				if ( ! is_array( $term ) ) {
					continue;
				}
				$categories[] = [
					'id'   => absint( $term['id'] ?? 0 ),
					'name' => sanitize_text_field( (string) ( $term['name'] ?? '' ) ),
					'slug' => sanitize_title( (string) ( $term['slug'] ?? '' ) ),
				];
			}

			$items[] = [
				'id'         => absint( $item['id'] ?? 0 ),
				'title'      => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'permalink'  => esc_url_raw( (string) ( $item['permalink'] ?? '' ) ),
				'excerpt'    => wp_strip_all_tags( (string) ( $item['excerpt'] ?? '' ), true ),
				'categories' => $categories,
			];
		}

		return new \WP_REST_Response(
			[
				'items'       => $items,
				'total'       => max( 0, (int) $result['total'] ),
				'total_pages' => max( 0, (int) $result['total_pages'] ),
			],
			200
		);
	}
}
