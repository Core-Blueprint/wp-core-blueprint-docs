<?php
declare(strict_types=1);

namespace CB\Docs\Frontend\Components;

use CB\Docs\Frontend\Assets;
use CB\Docs\Frontend\RestSearch;
use CB\Docs\Frontend\Search as SearchProvider;

defined( 'ABSPATH' ) || exit;

final class Search {
	private static int $instance = 0;

	/**
	 * Render the builder-neutral Docs search component.
	 *
	 * @param array{
	 *     placeholder?:mixed,
	 *     limit?:mixed,
	 *     min_chars?:mixed,
	 *     show_excerpt?:mixed,
	 *     show_category?:mixed,
	 *     category?:mixed,
	 *     tag?:mixed,
	 *     query?:mixed
	 * } $args
	 */
	public static function render( array $args = [] ): string {
		$args = wp_parse_args(
			$args,
			[
				'placeholder'   => __( 'Search documentation…', 'core-blueprint-docs' ),
				'limit'         => 20,
				'min_chars'     => 2,
				'show_excerpt'  => true,
				'show_category' => true,
				'category'      => '',
				'tag'           => '',
				'query'         => null,
			]
		);

		$placeholder   = sanitize_text_field( (string) $args['placeholder'] );
		$limit         = max( 1, min( 50, absint( $args['limit'] ) ?: 20 ) );
		$min_chars     = max( 1, min( 10, absint( $args['min_chars'] ) ?: 2 ) );
		$show_excerpt  = self::boolean( $args['show_excerpt'], true );
		$show_category = self::boolean( $args['show_category'], true );
		$category      = sanitize_title( (string) $args['category'] );
		$tag           = sanitize_title( (string) $args['tag'] );
		$query          = null === $args['query'] ? self::request_query() : sanitize_text_field( trim( (string) $args['query'] ) );

		Assets::enqueue_search();

		self::$instance++;
		$instance_id = 'cb-docs-search-' . self::$instance;
		$list_id     = $instance_id . '-list';
		$results_id  = $instance_id . '-results';

		$attrs = [
			'class'                    => 'cb-docs-search',
			'data-cb-docs-search'      => '',
			'data-endpoint'            => rest_url( RestSearch::NAMESPACE . RestSearch::ROUTE ),
			'data-limit'               => (string) $limit,
			'data-min-chars'           => (string) $min_chars,
			'data-show-excerpt'        => $show_excerpt ? '1' : '0',
			'data-show-category'       => $show_category ? '1' : '0',
			'data-category'            => $category,
			'data-tag'                 => $tag,
			'data-loading-label'       => __( 'Searching…', 'core-blueprint-docs' ),
			'data-no-results-label'    => __( 'No matching documentation found.', 'core-blueprint-docs' ),
			'data-error-label'         => __( 'Live search is temporarily unavailable. Submit the form to search.', 'core-blueprint-docs' ),
		];

		$html  = '<div' . self::attributes( $attrs ) . '>';
		$html .= '<form class="cb-docs-search__form" method="get" role="search">';
		$html .= '<label class="cb-docs-search__label" for="' . esc_attr( $instance_id ) . '">';
		$html .= '<span class="screen-reader-text">' . esc_html__( 'Search documentation', 'core-blueprint-docs' ) . '</span>';
		$html .= '<input class="cb-docs-search__input" id="' . esc_attr( $instance_id ) . '" type="search" name="cb_docs_q" value="' . esc_attr( $query ) . '" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="' . esc_attr( $list_id ) . '">';
		$html .= '</label>';
		$html .= '<button class="cb-docs-search__submit" type="submit">' . esc_html__( 'Search', 'core-blueprint-docs' ) . '</button>';
		$html .= '</form>';
		$html .= '<div class="cb-docs-search__results" id="' . esc_attr( $results_id ) . '" aria-live="polite"' . ( '' === $query ? ' hidden' : '' ) . '>';
		$html .= '<p class="cb-docs-search__status" data-cb-docs-search-status></p>';
		$html .= '<ul class="cb-docs-search__list" id="' . esc_attr( $list_id ) . '" role="listbox">';

		if ( '' !== $query ) {
			$result = SearchProvider::documents(
				$query,
				$limit,
				[
					'category' => $category,
					'tag'      => $tag,
				]
			);
			$html .= self::result_items( $result['items'], $show_excerpt, $show_category );
			if ( [] === $result['items'] ) {
				$html = str_replace(
					'<p class="cb-docs-search__status" data-cb-docs-search-status></p>',
					'<p class="cb-docs-search__status" data-cb-docs-search-status>' . esc_html__( 'No matching documentation found.', 'core-blueprint-docs' ) . '</p>',
					$html
				);
			}
		}

		return $html . '</ul></div></div>';
	}

	private static function request_query(): string {
		return isset( $_GET['cb_docs_q'] )
			? sanitize_text_field( trim( (string) wp_unslash( $_GET['cb_docs_q'] ) ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public search input.
			: '';
	}

	/** @param array<int,array<string,mixed>> $items */
	private static function result_items( array $items, bool $show_excerpt, bool $show_category ): string {
		$html = '';
		foreach ( $items as $index => $item ) {
			$title     = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			$permalink = esc_url( (string) ( $item['permalink'] ?? '' ) );
			if ( '' === $title || '' === $permalink ) {
				continue;
			}

			$option_id = 'cb-docs-search-option-' . self::$instance . '-' . ( $index + 1 );
			$html .= '<li class="cb-docs-search__option" id="' . esc_attr( $option_id ) . '" role="option" aria-selected="false">';
			$html .= '<a class="cb-docs-search__result" href="' . $permalink . '">';
			$html .= '<span class="cb-docs-search__title">' . esc_html( $title ) . '</span>';

			if ( $show_category ) {
				$categories = (array) ( $item['categories'] ?? [] );
				$first      = reset( $categories );
				if ( is_array( $first ) && '' !== (string) ( $first['name'] ?? '' ) ) {
					$html .= '<span class="cb-docs-search__category">' . esc_html( (string) $first['name'] ) . '</span>';
				}
			}

			if ( $show_excerpt ) {
				$excerpt = wp_strip_all_tags( (string) ( $item['excerpt'] ?? '' ), true );
				if ( '' !== trim( $excerpt ) ) {
					$html .= '<span class="cb-docs-search__excerpt">' . esc_html( $excerpt ) . '</span>';
				}
			}

			$html .= '</a></li>';
		}
		return $html;
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

	/** @param array<string,string> $attributes */
	private static function attributes( array $attributes ): string {
		$html = '';
		foreach ( $attributes as $name => $value ) {
			$html .= '' === $value
				? ' ' . esc_attr( $name )
				: ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}
		return $html;
	}
}
