<?php
declare(strict_types=1);

namespace CB\Docs\Frontend;

use CB\Docs\Content\Meta;
use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

final class Shortcodes {
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
		$html = '<div class="cb-docs-list">';
		foreach ( $query->posts as $doc ) {
			if ( ! $doc instanceof \WP_Post ) {
				continue;
			}
			$html .= '<article class="cb-docs-list__item">';
			$html .= '<h3 class="cb-docs-list__title"><a href="' . esc_url( get_permalink( $doc ) ) . '">' . esc_html( get_the_title( $doc ) ) . '</a></h3>';
			if ( $show_excerpt ) {
				$excerpt = get_the_excerpt( $doc );
				if ( '' !== trim( $excerpt ) ) {
					$html .= '<p class="cb-docs-list__excerpt">' . esc_html( $excerpt ) . '</p>';
				}
			}
			$html .= '</article>';
		}
		wp_reset_postdata();
		return $html . '</div>';
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

		$content = self::render_category_level( $parent );
		return '' !== $content
			? '<nav class="cb-docs-navigation" aria-label="' . esc_attr__( 'Documentation navigation', 'core-blueprint-docs' ) . '">' . $content . '</nav>'
			: self::state( 'empty', __( 'No documentation navigation is available.', 'core-blueprint-docs' ) );
	}

	public static function search_shortcode( array|string $atts = [] ): string {
		$atts = shortcode_atts(
			[ 'placeholder' => __( 'Search documentation…', 'core-blueprint-docs' ), 'limit' => '20' ],
			(array) $atts,
			'cb_docs_search'
		);
		$query_string = isset( $_GET['cb_docs_q'] )
			? sanitize_text_field( (string) wp_unslash( $_GET['cb_docs_q'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search input.
			: '';

		$html  = '<div class="cb-docs-search">';
		$html .= '<form class="cb-docs-search__form" method="get" role="search">';
		$html .= '<label><span class="screen-reader-text">' . esc_html__( 'Search documentation', 'core-blueprint-docs' ) . '</span>';
		$html .= '<input type="search" name="cb_docs_q" value="' . esc_attr( $query_string ) . '" placeholder="' . esc_attr( (string) $atts['placeholder'] ) . '"></label>';
		$html .= '<button type="submit">' . esc_html__( 'Search', 'core-blueprint-docs' ) . '</button></form>';

		if ( '' === $query_string ) {
			return $html . '</div>';
		}

		$limit = max( 1, min( 100, absint( $atts['limit'] ) ?: 20 ) );
		$query = Queries::docs( [ 's' => $query_string, 'posts_per_page' => $limit ] );
		$html .= '<div class="cb-docs-search__results" aria-live="polite">';
		if ( ! $query->have_posts() ) {
			$html .= self::state( 'no-results', __( 'No matching documentation found.', 'core-blueprint-docs' ) );
		} else {
			$html .= '<ul class="cb-docs-search__list">';
			foreach ( $query->posts as $doc ) {
				if ( $doc instanceof \WP_Post ) {
					$html .= '<li><a href="' . esc_url( get_permalink( $doc ) ) . '">' . esc_html( get_the_title( $doc ) ) . '</a></li>';
				}
			}
			$html .= '</ul>';
		}
		wp_reset_postdata();
		return $html . '</div></div>';
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
		if ( PostType::TYPE !== get_post_type( $post_id ) ) {
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

	private static function render_category_level( int $parent ): string {
		$terms = get_terms( [
			'taxonomy'   => Taxonomies::CATEGORY,
			'hide_empty' => true,
			'parent'     => $parent,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		$html = '<ul class="cb-docs-navigation__categories">';
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$html .= '<li class="cb-docs-navigation__category">';
			$term_link = get_term_link( $term );
			$html .= is_wp_error( $term_link )
				? '<span>' . esc_html( $term->name ) . '</span>'
				: '<a href="' . esc_url( $term_link ) . '">' . esc_html( $term->name ) . '</a>';

			$query = Queries::docs( [
				'posts_per_page' => -1,
				'tax_query'      => [
					[
						'taxonomy'         => Taxonomies::CATEGORY,
						'field'            => 'term_id',
						'terms'            => [ $term->term_id ],
						'include_children' => false,
					],
				],
			] );
			if ( $query->have_posts() ) {
				$html .= '<ul class="cb-docs-navigation__docs">';
				foreach ( $query->posts as $doc ) {
					if ( $doc instanceof \WP_Post ) {
						$html .= '<li><a href="' . esc_url( get_permalink( $doc ) ) . '">' . esc_html( get_the_title( $doc ) ) . '</a></li>';
					}
				}
				$html .= '</ul>';
			}
			wp_reset_postdata();
			$html .= self::render_category_level( (int) $term->term_id );
			$html .= '</li>';
		}
		return $html . '</ul>';
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

		usort( $terms, static function ( \WP_Term $a, \WP_Term $b ): int {
			$a_depth = count( get_ancestors( $a->term_id, Taxonomies::CATEGORY, 'taxonomy' ) );
			$b_depth = count( get_ancestors( $b->term_id, Taxonomies::CATEGORY, 'taxonomy' ) );
			return $b_depth <=> $a_depth ?: $a->term_id <=> $b->term_id;
		} );
		return $terms[0] instanceof \WP_Term ? $terms[0] : null;
	}

	private static function state( string $state, string $message ): string {
		return '<div class="cb-docs-state" data-cb-docs-state="' . esc_attr( $state ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}
}
