<?php
declare(strict_types=1);

namespace CB\Docs\Frontend\Components;

use CB\Docs\Content\Taxonomies;
use CB\Docs\Frontend\DocumentAccess;
use CB\Docs\Frontend\Queries;
use CB\Docs\Structure\CategoryOrder;

defined( 'ABSPATH' ) || exit;

final class Navigation {
	private const MAX_TERMS = 250;
	private const MAX_DOCS  = 500;
	private const MAX_DEPTH = 20;

	/** @param array{category?:mixed} $args */
	public static function render( array $args = [] ): string {
		$args = wp_parse_args( $args, [ 'category' => '' ] );
		$category = sanitize_title( (string) $args['category'] );
		$parent = 0;

		if ( '' !== $category ) {
			$term = get_term_by( 'slug', $category, Taxonomies::CATEGORY );
			if ( ! $term instanceof \WP_Term ) {
				return State::render( 'missing-category', __( 'Documentation category not found.', 'core-blueprint-docs' ) );
			}
			$parent = (int) $term->term_id;
		}

		$snapshot = self::snapshot( $parent );
		$visited  = [];
		$content  = self::render_category_level(
			$parent,
			$snapshot['terms_by_parent'],
			$snapshot['docs_by_term'],
			$visited
		);

		return '' !== $content
			? '<nav class="cb-docs-navigation" aria-label="' . esc_attr__( 'Documentation navigation', 'core-blueprint-docs' ) . '">' . $content . '</nav>'
			: State::render( 'empty', __( 'No documentation navigation is available.', 'core-blueprint-docs' ) );
	}

	/**
	 * @return array{
	 *     terms_by_parent:array<int,array<int,\WP_Term>>,
	 *     docs_by_term:array<int,array<int,\WP_Post>>
	 * }
	 */
	private static function snapshot( int $parent ): array {
		$term_args = [
			'taxonomy'   => Taxonomies::CATEGORY,
			'hide_empty' => false,
			'number'     => self::MAX_TERMS,
			'orderby'    => 'name',
			'order'      => 'ASC',
		];
		if ( $parent > 0 ) {
			$term_args['child_of'] = $parent;
		}

		$terms = get_terms( $term_args );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [ 'terms_by_parent' => [], 'docs_by_term' => [] ];
		}

		$terms_by_parent = [];
		$term_ids        = [];
		$allowed_terms   = [];
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$term_id = (int) $term->term_id;
			if ( $term_id <= 0 ) {
				continue;
			}
			$term_ids[] = $term_id;
			$allowed_terms[ $term_id ] = true;
			$terms_by_parent[ (int) $term->parent ][] = $term;
		}

		update_meta_cache( 'term', $term_ids );
		foreach ( $terms_by_parent as &$siblings ) {
			$siblings = CategoryOrder::sort_terms( $siblings );
		}
		unset( $siblings );

		if ( empty( $term_ids ) ) {
			return [ 'terms_by_parent' => $terms_by_parent, 'docs_by_term' => [] ];
		}

		$query = Queries::docs( [
			'posts_per_page' => self::MAX_DOCS,
			'no_found_rows'  => true,
			'tax_query'      => [
				[
					'taxonomy'         => Taxonomies::CATEGORY,
					'field'            => 'term_id',
					'terms'            => $term_ids,
					'operator'         => 'IN',
					'include_children' => false,
				],
			],
		] );

		$doc_ids = [];
		foreach ( $query->posts as $doc ) {
			if ( $doc instanceof \WP_Post && DocumentAccess::protected_content_allowed( $doc ) ) {
				$doc_ids[] = (int) $doc->ID;
			}
		}
		if ( empty( $doc_ids ) ) {
			wp_reset_postdata();
			return [ 'terms_by_parent' => $terms_by_parent, 'docs_by_term' => [] ];
		}

		$relations = wp_get_object_terms(
			$doc_ids,
			Taxonomies::CATEGORY,
			[ 'fields' => 'all_with_object_id' ]
		);
		$doc_terms = [];
		if ( ! is_wp_error( $relations ) ) {
			foreach ( $relations as $relation ) {
				if ( ! $relation instanceof \WP_Term || ! isset( $relation->object_id ) ) {
					continue;
				}
				$term_id = (int) $relation->term_id;
				$doc_id  = (int) $relation->object_id;
				if ( $doc_id > 0 && isset( $allowed_terms[ $term_id ] ) ) {
					$doc_terms[ $doc_id ][] = $term_id;
				}
			}
		}

		$docs_by_term = [];
		foreach ( $query->posts as $doc ) {
			if ( ! $doc instanceof \WP_Post || ! DocumentAccess::protected_content_allowed( $doc ) ) {
				continue;
			}
			foreach ( array_unique( $doc_terms[ (int) $doc->ID ] ?? [] ) as $term_id ) {
				$docs_by_term[ $term_id ][] = $doc;
			}
		}
		wp_reset_postdata();

		return [
			'terms_by_parent' => $terms_by_parent,
			'docs_by_term'    => $docs_by_term,
		];
	}

	/**
	 * @param array<int,array<int,\WP_Term>> $terms_by_parent
	 * @param array<int,array<int,\WP_Post>> $docs_by_term
	 * @param array<int,bool> $visited
	 */
	private static function render_category_level(
		int $parent,
		array $terms_by_parent,
		array $docs_by_term,
		array &$visited,
		int $depth = 0
	): string {
		if ( $depth >= self::MAX_DEPTH || empty( $terms_by_parent[ $parent ] ) ) {
			return '';
		}

		$items = '';
		foreach ( $terms_by_parent[ $parent ] as $term ) {
			$term_id = (int) $term->term_id;
			if ( $term_id <= 0 || isset( $visited[ $term_id ] ) ) {
				continue;
			}
			$visited[ $term_id ] = true;

			$docs_html = '';
			foreach ( $docs_by_term[ $term_id ] ?? [] as $doc ) {
				if ( $doc instanceof \WP_Post ) {
					$docs_html .= '<li><a href="' . esc_url( get_permalink( $doc ) ) . '">' . esc_html( get_the_title( $doc ) ) . '</a></li>';
				}
			}
			if ( '' !== $docs_html ) {
				$docs_html = '<ul class="cb-docs-navigation__docs">' . $docs_html . '</ul>';
			}

			$children_html = self::render_category_level(
				$term_id,
				$terms_by_parent,
				$docs_by_term,
				$visited,
				$depth + 1
			);
			if ( '' === $docs_html && '' === $children_html ) {
				continue;
			}

			$term_link = get_term_link( $term );
			$label = is_wp_error( $term_link )
				? '<span>' . esc_html( $term->name ) . '</span>'
				: '<a href="' . esc_url( $term_link ) . '">' . esc_html( $term->name ) . '</a>';

			$items .= '<li class="cb-docs-navigation__category">' . $label . $docs_html . $children_html . '</li>';
		}

		return '' !== $items ? '<ul class="cb-docs-navigation__categories">' . $items . '</ul>' : '';
	}
}
