<?php
declare(strict_types=1);

namespace CB\Docs\Frontend\Components;

use CB\Docs\Frontend\DocumentAccess;
use CB\Docs\Frontend\Queries;

defined( 'ABSPATH' ) || exit;

final class DocumentList {
	/**
	 * Render the builder-neutral Docs list component.
	 *
	 * @param array{
	 *     category?:mixed,
	 *     tag?:mixed,
	 *     limit?:mixed,
	 *     show_excerpt?:mixed
	 * } $args
	 */
	public static function render( array $args = [] ): string {
		$args = wp_parse_args(
			$args,
			[
				'category'     => '',
				'tag'          => '',
				'limit'        => 20,
				'show_excerpt' => true,
			]
		);

		$limit = max( 1, min( 100, absint( $args['limit'] ) ?: 20 ) );
		$query_args = [ 'posts_per_page' => $limit ];
		$tax_query = Queries::taxonomy_filter( (string) $args['category'], (string) $args['tag'] );
		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = $tax_query;
		}

		$query = Queries::docs( $query_args );
		if ( ! $query->have_posts() ) {
			return State::render( 'empty', __( 'No documentation found.', 'core-blueprint-docs' ) );
		}

		$show_excerpt = self::boolean( $args['show_excerpt'], true );
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
			: State::render( 'empty', __( 'No documentation found.', 'core-blueprint-docs' ) );
	}

	private static function boolean( mixed $value, bool $default ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_scalar( $value ) ) {
			$parsed = filter_var( (string) $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			return null === $parsed ? $default : $parsed;
		}
		return $default;
	}
}
