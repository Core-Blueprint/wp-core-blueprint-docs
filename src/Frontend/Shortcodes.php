<?php
declare(strict_types=1);

namespace CB\Docs\Frontend;

use CB\Docs\Content\Meta;
use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;
use CB\Docs\Structure\CategoryOrder;
use CB\Docs\Structure\StructuralCategory;

defined( 'ABSPATH' ) || exit;

final class Shortcodes {
	private const NAVIGATION_MAX_TERMS = 250;
	private const NAVIGATION_MAX_DOCS  = 500;
	private const NAVIGATION_MAX_DEPTH = 20;

	public static function init(): void {
		add_shortcode( 'cb_docs_list', [ __CLASS__, 'list_shortcode' ] );
		add_shortcode( 'cb_docs_navigation', [ __CLASS__, 'navigation_shortcode' ] );
		add_shortcode( 'cb_docs_search', [ __CLASS__, 'search_shortcode' ] );
		add_shortcode( 'cb_docs_breadcrumbs', [ __CLASS__, 'breadcrumbs_shortcode' ] );
		add_shortcode( 'cb_docs_meta', [ __CLASS__, 'meta_shortcode' ] );
	}

	public static function list_shortcode( array|string $atts = [] ): string {
		$atts = shortcode_atts(
			[ 'category' => '', 'tag' => '', 'limit' => '20', 'excerpt' => 'true' ],
			(array) $atts,
			'cb_docs_list'
		);

		$limit = max( 1, min( 100, absint( $atts['limit'] ) ?: 20 ) );
		$args  = [ 'posts_per_page' => $limit ];
		$tax_query = Queries::taxonomy_filter( (string) $atts['category'], (string) $atts['tag'] );
		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		$query = Queries::docs( $args );
		if ( ! $query->have_posts() ) {
			return self::state( 'empty', __( 'No documentation found.', 'core-blueprint-docs' ) );
		}

		$show_excerpt = filter_var( $atts['excerpt'], FILTER_VALIDATE_BOOLEAN );
		$items = '';
		foreach ( $query->posts as $doc ) {
			if ( ! $doc instanceof \WP_Post || ! DocumentAccess::protected_content_allowed( $doc ) ) {
				continue;
			}
			$items .= '<article class="cb-docs-list__item">';
			$items .= '<h3 class="cb-docs-list__title"><a href="' . esc_url( get_permalink( $doc ) ) . '">' . esc_html( get_the_title( $doc ) ) . '</a></h3>';
			if ( $show_excerpt ) {
				$excerpt = get_the_excerpt( $doc );
				if ( '' !== trim( $excerpt ) ) {
					$items .= '<p class="cb-docs-list__excerpt">' . esc_html( $excerpt ) . '</p>';
				}
			}
			$items .= '</article>';
		}
		wp_reset_postdata();

		return '' !== $items
			? '<div class="cb-docs-list">' . $items . '</div>'
			: self::state( 'empty', __( 'No documentation found.', 'core-blueprint-docs' ) );
	}

	public static function navigation_shortcode( array|string $atts = [] ): string {
		$atts = shortcode_atts( [ 'category' => '' ], (array) $atts, 'cb_docs_navigation' );
		$parent = 0;
		if ( '' !== (string) $atts['category'] ) {
			$term = get_term_by( 'slug', sanitize_title( (string) $atts['category'] ), Taxonomies::CATEGORY );
			if ( ! $term instanceof \WP_Term ) {
				return self::state( 'missing-category', __( 'Documentation category not found.', 'core-blueprint-docs' ) );
			}
			$parent = (int) $term->term_id;
		}

		$snapshot = self::navigation_snapshot( $parent );
		$visited  = [];
		$content  = self::render_category_level(
			$parent,
			$snapshot['terms_by_parent'],
			$snapshot['docs_by_term'],
			$visited
		);

		return '' !== $content
			? '<nav class="cb-docs-navigation" aria-label="' . esc_attr__( 'Documentation navigation', 'core-blueprint-docs' ) . '">' . $content . '</nav>'
			: self::state( 'empty', __( 'No documentation navigation is available.', 'core-blueprint-docs' ) );
	}

	public static function search_shortcode( array|string $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'placeholder'   => __( 'Search documentation…', 'core-blueprint-docs' ),
				'limit'         => '20',
				'min_chars'     => '2',
				'excerpt'       => 'true',
				'show_category' => 'true',
				'category'      => '',
				'tag'           => '',
			],
			(array) $atts,
			'cb_docs_search'
		);

		return Components\Search::render( [
			'placeholder'   => (string) $atts['placeholder'],
			'limit'         => $atts['limit'],
			'min_chars'     => $atts['min_chars'],
			'show_excerpt'  => $atts['excerpt'],
			'show_category' => $atts['show_category'],
			'category'      => (string) $atts['category'],
			'tag'           => (string) $atts['tag'],
		] );
	}

	public static function breadcrumbs_shortcode( array|string $atts = [] ): string {
		unset( $atts );
		$items = [];
		$archive = get_post_type_archive_link( PostType::TYPE );
		if ( is_string( $archive ) && '' !== $archive ) {
			$items[] = [ 'label' => __( 'Docs', 'core-blueprint-docs' ), 'url' => $archive ];
		}

		if ( is_tax( Taxonomies::CATEGORY ) ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$items = array_merge( $items, self::term_breadcrumbs( $term ) );
			}
		} elseif ( is_tax( Taxonomies::TAG ) ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$items[] = [ 'label' => $term->name, 'url' => '' ];
			}
		} elseif ( is_singular( PostType::TYPE ) ) {
			$post_id = get_queried_object_id();
			$term = self::breadcrumb_category( $post_id );
			if ( $term instanceof \WP_Term ) {
				$items = array_merge( $items, self::term_breadcrumbs( $term ) );
			}
			$items[] = [ 'label' => get_the_title( $post_id ), 'url' => '' ];
		}

		if ( empty( $items ) ) {
			return '';
		}

		$html = '<nav class="cb-docs-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumbs', 'core-blueprint-docs' ) . '"><ol>';
		$last = count( $items ) - 1;
		foreach ( $items as $index => $item ) {
			$html .= '<li>';
			if ( $index !== $last && '' !== (string) $item['url'] ) {
				$html .= '<a href="' . esc_url( (string) $item['url'] ) . '">' . esc_html( (string) $item['label'] ) . '</a>';
			} else {
				$html .= '<span' . ( $index === $last ? ' aria-current="page"' : '' ) . '>' . esc_html( (string) $item['label'] ) . '</span>';
			}
			$html .= '</li>';
		}
		return $html . '</ol></nav>';
	}

	public static function meta_shortcode( array|string $atts = [] ): string {
		$atts = shortcode_atts( [ 'id' => '0' ], (array) $atts, 'cb_docs_meta' );
		$post_id = absint( $atts['id'] );
		if ( 0 === $post_id && is_singular( PostType::TYPE ) ) {
			$post_id = get_queried_object_id();
		}
		if ( PostType::TYPE !== get_post_type( $post_id ) || ! DocumentAccess::can_read( $post_id ) ) {
			return '';
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$status = Meta::sanitize_status( get_post_meta( $post_id, Meta::STATUS, true ) ?: Meta::STATUS_CURRENT );
		$labels = Meta::status_labels();
		$version = (string) get_post_meta( $post_id, Meta::VERSION, true );
		$reviewed = (string) get_post_meta( $post_id, Meta::LAST_REVIEWED, true );
		$author = get_the_author_meta( 'display_name', (int) $post->post_author );

		$rows = [
			[ __( 'Author', 'core-blueprint-docs' ), (string) $author ],
			[ __( 'Published', 'core-blueprint-docs' ), get_the_date( '', $post ) ],
			[ __( 'Updated', 'core-blueprint-docs' ), get_the_modified_date( '', $post ) ],
			[ __( 'Status', 'core-blueprint-docs' ), $labels[ $status ] ?? $status ],
		];
		if ( '' !== $version ) {
			$rows[] = [ __( 'Version', 'core-blueprint-docs' ), $version ];
		}
		if ( '' !== $reviewed ) {
			$rows[] = [ __( 'Last reviewed', 'core-blueprint-docs' ), $reviewed ];
		}

		$html = '<dl class="cb-docs-meta">';
		foreach ( $rows as [ $label, $value ] ) {
			$html .= '<div class="cb-docs-meta__item"><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
		}
		return $html . '</dl>';
	}

	/**
	 * Build one bounded snapshot for the navigation tree.
	 *
	 * @return array{
	 *     terms_by_parent:array<int,array<int,\WP_Term>>,
	 *     docs_by_term:array<int,array<int,\WP_Post>>
	 * }
	 */
	private static function navigation_snapshot( int $parent ): array {
		$term_args = [
			'taxonomy'   => Taxonomies::CATEGORY,
			'hide_empty' => false,
			'number'     => self::NAVIGATION_MAX_TERMS,
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
			'posts_per_page' => self::NAVIGATION_MAX_DOCS,
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
		if ( $depth >= self::NAVIGATION_MAX_DEPTH || empty( $terms_by_parent[ $parent ] ) ) {
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

	/** @return array<int,array{label:string,url:string}> */
	private static function term_breadcrumbs( \WP_Term $term ): array {
		$items = [];
		$ancestors = array_reverse( get_ancestors( $term->term_id, Taxonomies::CATEGORY, 'taxonomy' ) );
		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, Taxonomies::CATEGORY );
			if ( $ancestor instanceof \WP_Term ) {
				$link = get_term_link( $ancestor );
				$items[] = [ 'label' => $ancestor->name, 'url' => is_wp_error( $link ) ? '' : $link ];
			}
		}
		$link = get_term_link( $term );
		$items[] = [ 'label' => $term->name, 'url' => is_wp_error( $link ) ? '' : $link ];
		return $items;
	}

	private static function breadcrumb_category( int $post_id ): ?\WP_Term {
		$terms = wp_get_post_terms( $post_id, Taxonomies::CATEGORY );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		$parents = [];
		$term_ids = [];
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$term_ids[] = (int) $term->term_id;
			$parents[ (int) $term->term_id ] = (int) $term->parent;
			foreach ( get_ancestors( $term->term_id, Taxonomies::CATEGORY, 'taxonomy' ) as $ancestor_id ) {
				$ancestor = get_term( (int) $ancestor_id, Taxonomies::CATEGORY );
				if ( $ancestor instanceof \WP_Term ) {
					$parents[ (int) $ancestor->term_id ] = (int) $ancestor->parent;
				}
			}
		}

		$resolved_id = StructuralCategory::resolve( $term_ids, $parents );
		if ( null === $resolved_id ) {
			return null;
		}

		$resolved = get_term( $resolved_id, Taxonomies::CATEGORY );
		return $resolved instanceof \WP_Term ? $resolved : null;
	}

	private static function state( string $state, string $message ): string {
		return '<div class="cb-docs-state" data-cb-docs-state="' . esc_attr( $state ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}
}
