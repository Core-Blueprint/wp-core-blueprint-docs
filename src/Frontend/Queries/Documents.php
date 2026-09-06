<?php
declare(strict_types=1);

namespace CB\Docs\Frontend\Queries;

use CB\Docs\Content\PostType;
use CB\Docs\Frontend\Data\Document;
use CB\Docs\Frontend\Queries as NativeQueries;

defined( 'ABSPATH' ) || exit;

final class Documents {
	private const DEFAULT_PER_PAGE = 20;
	private const MAX_PER_PAGE     = 100;

	/**
	 * Public bounded Docs query.
	 *
	 * @param array{
	 *     category?:mixed,
	 *     tag?:mixed,
	 *     search?:mixed,
	 *     include_ids?:mixed,
	 *     page?:mixed,
	 *     per_page?:mixed
	 * } $args
	 * @return array{items:array<int,array<string,mixed>>,page:int,per_page:int,total:int,total_pages:int}
	 */
	public static function query( array $args = [] ): array {
		$page     = self::positive_int( $args['page'] ?? 1, 1 );
		$per_page = self::positive_int( $args['per_page'] ?? self::DEFAULT_PER_PAGE, self::DEFAULT_PER_PAGE );
		$per_page = min( self::MAX_PER_PAGE, $per_page );

		$query_args = [
			'post_type'           => PostType::TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'orderby'             => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
			'order'               => 'ASC',
			'perm'                => 'readable',
			'suppress_filters'    => false,
			'ignore_sticky_posts' => true,
		];

		$search = sanitize_text_field( trim( self::scalar_string( $args['search'] ?? '' ) ) );
		if ( '' !== $search ) {
			$query_args['s']       = $search;
			$query_args['orderby'] = 'relevance';
			$query_args['order']   = 'DESC';
		}

		$category = sanitize_title( self::scalar_string( $args['category'] ?? '' ) );
		$tag      = sanitize_title( self::scalar_string( $args['tag'] ?? '' ) );
		$tax      = NativeQueries::taxonomy_filter( $category, $tag );
		if ( [] !== $tax ) {
			$query_args['tax_query'] = $tax;
		}

		$include_ids = self::include_ids( $args['include_ids'] ?? [] );
		if ( [] !== $include_ids ) {
			$query_args['post__in'] = $include_ids;
		}

		$query = new \WP_Query( $query_args );
		$items = [];
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$document = Document::project( $post );
			if ( ! is_wp_error( $document ) ) {
				$items[] = $document;
			}
		}

		return [
			'items'       => $items,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => max( 0, (int) $query->found_posts ),
			'total_pages' => max( 0, (int) $query->max_num_pages ),
		];
	}

	private static function positive_int( mixed $value, int $default ): int {
		if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
			return $default;
		}

		$value = absint( $value );
		return $value > 0 ? $value : $default;
	}

	private static function scalar_string( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** @return int[] */
	private static function include_ids( mixed $raw ): array {
		$values = is_array( $raw ) ? $raw : ( is_scalar( $raw ) && '' !== (string) $raw ? [ $raw ] : [] );
		$ids    = [];
		foreach ( $values as $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$id = absint( $value );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}
}
