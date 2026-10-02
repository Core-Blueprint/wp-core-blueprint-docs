<?php
declare(strict_types=1);

namespace CB\Docs\Frontend;

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

		return Components\DocumentList::render( [
			'category'     => (string) $atts['category'],
			'tag'          => (string) $atts['tag'],
			'limit'        => $atts['limit'],
			'show_excerpt' => $atts['excerpt'],
		] );
	}

	public static function navigation_shortcode( array|string $atts = [] ): string {
		$atts = shortcode_atts( [ 'category' => '' ], (array) $atts, 'cb_docs_navigation' );

		return Components\Navigation::render( [
			'category' => (string) $atts['category'],
		] );
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
		return Components\Breadcrumbs::render();
	}

	public static function meta_shortcode( array|string $atts = [] ): string {
		$atts = shortcode_atts( [ 'id' => '0' ], (array) $atts, 'cb_docs_meta' );

		return Components\DocumentMeta::render( [
			'id' => $atts['id'],
		] );
	}
}
