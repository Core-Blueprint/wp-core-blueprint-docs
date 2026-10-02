<?php
declare(strict_types=1);

namespace CB\Docs\Frontend\Components;

use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;
use CB\Docs\Structure\StructuralCategory;

defined( 'ABSPATH' ) || exit;

final class Breadcrumbs {
	public static function render(): string {
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
}
