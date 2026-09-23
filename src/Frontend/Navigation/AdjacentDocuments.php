<?php
declare(strict_types=1);

namespace CB\Docs\Frontend\Navigation;

use CB\Docs\Content\PostType;
use CB\Docs\Structure\DocumentLocation;

defined( 'ABSPATH' ) || exit;

/**
 * Adapt WordPress native adjacent-post navigation to Docs Organizer order.
 *
 * Builders and themes that use get_previous_post()/get_next_post() inherit
 * this behavior without a builder-specific integration.
 */
final class AdjacentDocuments {
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

		$sibling_ids = DocumentLocation::sibling_document_ids( (int) $post->ID );
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

	private static function fail_closed( string $where ): string {
		return $where . ' AND 1=0';
	}
}
