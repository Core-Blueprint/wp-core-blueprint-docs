<?php
declare(strict_types=1);

namespace CB\Docs\Frontend;

use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

final class Queries {
	/**
	 * @param array<string,mixed> $args
	 */
	public static function docs( array $args = [] ): \WP_Query {
		$defaults = [
			'post_type'        => PostType::TYPE,
			'post_status'      => 'publish',
			'posts_per_page'   => 20,
			'orderby'          => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
			'order'               => 'ASC',
			'perm'                => 'readable',
			'suppress_filters'    => false,
			'ignore_sticky_posts' => true,
		];

		return new \WP_Query( wp_parse_args( $args, $defaults ) );
	}

	/** @return array<int,array<string,mixed>> */
	public static function taxonomy_filter( string $category = '', string $tag = '' ): array {
		$clauses = [];
		if ( '' !== $category ) {
			$clauses[] = [
				'taxonomy' => Taxonomies::CATEGORY,
				'field'    => 'slug',
				'terms'    => sanitize_title( $category ),
			];
		}
		if ( '' !== $tag ) {
			$clauses[] = [
				'taxonomy' => Taxonomies::TAG,
				'field'    => 'slug',
				'terms'    => sanitize_title( $tag ),
			];
		}

		if ( count( $clauses ) > 1 ) {
			$clauses = array_merge( [ 'relation' => 'AND' ], $clauses );
		}
		return $clauses;
	}
}
