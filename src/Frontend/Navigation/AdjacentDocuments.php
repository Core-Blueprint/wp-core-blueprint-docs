<?php
declare(strict_types=1);

namespace CB\Docs\Frontend\Navigation;

use CB\Docs\Content\PostType;
use CB\Docs\Frontend\DocumentAccess;
use CB\Docs\Structure\DocumentLocation;

defined( 'ABSPATH' ) || exit;

/**
 * Adapt WordPress native adjacent-post navigation to Docs Organizer order.
 *
 * Builders and themes that use get_previous_post()/get_next_post() inherit
 * this behavior without a builder-specific integration.
 */
final class AdjacentDocuments {
	/** @var array<string,int[]> */
	private static array $readable_sets = [];
	public static function init(): void {
		add_filter( 'get_previous_post_where', [ self::class, 'previous_where' ], 20, 5 );
		add_filter( 'get_next_post_where', [ self::class, 'next_where' ], 20, 5 );
		add_filter( 'get_previous_post_sort', [ self::class, 'sort' ], 20, 3 );
		add_filter( 'get_next_post_sort', [ self::class, 'sort' ], 20, 3 );
	}

	public static function previous_where(
		string $where,
		bool $in_same_term,
		array|string $excluded_terms,
		string $taxonomy,
		\WP_Post $post
	): string {
		unset( $in_same_term, $excluded_terms, $taxonomy );
		return self::where( $where, $post, '<' );
	}

	public static function next_where(
		string $where,
		bool $in_same_term,
		array|string $excluded_terms,
		string $taxonomy,
		\WP_Post $post
	): string {
		unset( $in_same_term, $excluded_terms, $taxonomy );
		return self::where( $where, $post, '>' );
	}

	public static function sort( string $order_by, \WP_Post $post, string $order ): string {
		if ( PostType::TYPE !== $post->post_type ) {
			return $order_by;
		}

		$order = strtoupper( $order );
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			return $order_by;
		}

		return "ORDER BY p.menu_order {$order}, p.ID {$order} LIMIT 1";
	}

	private static function where( string $where, \WP_Post $post, string $operator ): string {
		if ( PostType::TYPE !== $post->post_type ) {
			return $where;
		}

		$sibling_ids = self::readable_sibling_ids(
			DocumentLocation::sibling_document_ids( (int) $post->ID )
		);
		if ( empty( $sibling_ids ) ) {
			return self::fail_closed( $where );
		}

		$marker = ' AND p.post_type = ';
		$marker_position = strpos( $where, $marker );
		if (
			false === $marker_position
			|| ! str_starts_with( ltrim( $where ), 'WHERE ' )
			|| ! str_contains( substr( $where, 0, $marker_position ), 'p.post_date' )
			|| ! str_contains( substr( $where, 0, $marker_position ), 'p.ID' )
		) {
			return self::fail_closed( $where );
		}

		global $wpdb;
		if ( ! $wpdb instanceof \wpdb ) {
			return self::fail_closed( $where );
		}

		$menu_order = (int) $post->menu_order;
		$post_id    = (int) $post->ID;
		$boundary   = $wpdb->prepare(
			"WHERE (p.menu_order {$operator} %d OR (p.menu_order = %d AND p.ID {$operator} %d))",
			$menu_order,
			$menu_order,
			$post_id
		);

		$ids = implode( ',', array_map( 'absint', $sibling_ids ) );
		if ( '' === $ids ) {
			return self::fail_closed( $where );
		}

		return $boundary . substr( $where, $marker_position ) . ' AND p.ID IN (' . $ids . ')';
	}

	/**
	 * Apply the same filtered WordPress read boundary used by Docs frontend
	 * queries before candidate IDs reach the native adjacent-post SQL.
	 *
	 * @param int[] $document_ids
	 * @return int[]
	 */
	private static function readable_sibling_ids( array $document_ids ): array {
		$document_ids = array_values( array_unique( array_filter( array_map( 'absint', $document_ids ) ) ) );
		if ( empty( $document_ids ) ) {
			return [];
		}

		$key = implode( ',', $document_ids );
		if ( isset( self::$readable_sets[ $key ] ) ) {
			return self::$readable_sets[ $key ];
		}

		$statuses = array_values( array_unique( array_merge(
			[ 'publish' ],
			array_values( get_post_stati( [ 'private' => true ] ) )
		) ) );

		$query = new \WP_Query(
			[
				'post_type'           => PostType::TYPE,
				'post_status'         => $statuses,
				'post__in'            => $document_ids,
				'posts_per_page'      => -1,
				'orderby'             => 'post__in',
				'perm'                => 'readable',
				'suppress_filters'    => false,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			]
		);

		$readable = [];
		foreach ( $query->posts as $candidate ) {
			if (
				$candidate instanceof \WP_Post
				&& DocumentAccess::protected_content_allowed( $candidate )
			) {
				$readable[] = (int) $candidate->ID;
			}
		}

		self::$readable_sets[ $key ] = $readable;
		return $readable;
	}

	private static function fail_closed( string $where ): string {
		return $where . ' AND 1=0';
	}
}
