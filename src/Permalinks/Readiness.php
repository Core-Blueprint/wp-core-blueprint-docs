<?php
declare(strict_types=1);

namespace CB\Docs\Permalinks;

use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;
use CB\Docs\Settings;
use WP_Post;
use WP_Post_Type;
use WP_Taxonomy;

defined( 'ABSPATH' ) || exit;

final class Readiness {
	private const RESERVED_BASES = [ 'docs-category', 'docs-tag' ];

	private const RESERVED_ROOTS = [
		'wp-admin',
		'wp-json',
		'wp-content',
		'wp-includes',
	];

	/** @return array<string,int> */
	public static function analyze( ?string $base = null ): array {
		$index = Catalog::index();
		$readiness = isset( $index['readiness'] ) && is_array( $index['readiness'] )
			? $index['readiness']
			: [];

		$base = trim( $base ?? Settings::rewrite_base(), '/' );
		$reserved_base = in_array( $base, self::RESERVED_BASES, true ) ? 1 : 0;

		return [
			'blocking'                     => (int) ( $readiness['blocking'] ?? 0 ) + $reserved_base,
			'reserved_base'                => $reserved_base,
			'warnings'                     => (int) ( $readiness['warnings'] ?? 0 ),
			'reserved_categories'          => (int) ( $readiness['reserved_categories'] ?? 0 ),
			'duplicate_category_paths'     => (int) ( $readiness['duplicate_category_paths'] ?? 0 ),
			'invalid_category_paths'       => (int) ( $readiness['invalid_category_paths'] ?? 0 ),
			'document_category_collisions' => (int) ( $readiness['document_category_collisions'] ?? 0 ),
			'duplicate_document_paths'     => (int) ( $readiness['duplicate_document_paths'] ?? 0 ),
			'duplicate_legacy_document_slugs' => (int) ( $readiness['duplicate_legacy_document_slugs'] ?? 0 ),
			'legacy_simple_collisions'     => (int) ( $readiness['legacy_simple_collisions'] ?? 0 ),
			'unassigned'                   => (int) ( $readiness['unassigned'] ?? 0 ),
			'ambiguous'                    => (int) ( $readiness['ambiguous'] ?? 0 ),
		];
	}

	public static function ready( ?string $base = null ): bool {
		return self::namespace_ready( $base ) && 0 === self::analyze( $base )['blocking'];
	}

	/**
	 * Analyze collisions between the configured Docs namespace and existing
	 * public WordPress routes. This is the full save-time/admin readiness gate.
	 *
	 * @return array{
	 *   ready:bool,
	 *   base:string,
	 *   conflicts:array<int,array{type:string,label:string,url:string}>
	 * }
	 */
	public static function namespace_analyze( ?string $base = null ): array {
		$base = Settings::sanitize_rewrite_base( $base ?? Settings::rewrite_base() );
		$conflicts = [];

		$first_segment = explode( '/', $base )[0] ?? '';
		if ( in_array( $first_segment, self::RESERVED_ROOTS, true ) ) {
			$conflicts[] = [
				'type'  => 'reserved',
				'label' => __( 'This URL base is reserved by WordPress.', 'core-blueprint-docs' ),
				'url'   => '',
			];
		}

		$page = get_page_by_path( $base, OBJECT, 'page' );
		if ( $page instanceof WP_Post && 'trash' !== $page->post_status ) {
			$title = trim( get_the_title( $page ) );
			$conflicts[] = [
				'type'  => 'page',
				'label' => sprintf(
					/* translators: %s: WordPress page title. */
					__( 'The WordPress page "%s" already uses this path.', 'core-blueprint-docs' ),
					'' !== $title ? $title : __( '(Untitled)', 'core-blueprint-docs' )
				),
				'url'   => (string) ( get_edit_post_link( $page->ID, 'raw' ) ?: '' ),
			];
		}

		foreach ( get_post_types( [], 'objects' ) as $post_type ) {
			if ( ! $post_type instanceof WP_Post_Type || PostType::TYPE === $post_type->name || ! $post_type->public ) {
				continue;
			}

			$archive_base = self::post_type_archive_base( $post_type );
			if ( '' === $archive_base || $base !== $archive_base ) {
				continue;
			}

			$label = isset( $post_type->labels->name ) && is_string( $post_type->labels->name )
				? $post_type->labels->name
				: $post_type->name;
			$conflicts[] = [
				'type'  => 'post_type',
				'label' => sprintf(
					/* translators: %s: public post type label. */
					__( 'The %s archive already uses this path.', 'core-blueprint-docs' ),
					$label
				),
				'url'   => '',
			];
		}

		foreach ( get_taxonomies( [], 'objects' ) as $taxonomy ) {
			if (
				! $taxonomy instanceof WP_Taxonomy
				|| in_array( $taxonomy->name, [ Taxonomies::CATEGORY, Taxonomies::TAG ], true )
				|| ! $taxonomy->public
				|| ! is_array( $taxonomy->rewrite )
			) {
				continue;
			}

			$taxonomy_base = isset( $taxonomy->rewrite['slug'] )
				&& is_string( $taxonomy->rewrite['slug'] )
				&& '' !== trim( $taxonomy->rewrite['slug'], "/ \t\n\r\0\x0B" )
					? Settings::sanitize_rewrite_base( $taxonomy->rewrite['slug'] )
					: '';
			if ( '' === $taxonomy_base || $base !== $taxonomy_base ) {
				continue;
			}

			$label = isset( $taxonomy->labels->name ) && is_string( $taxonomy->labels->name )
				? $taxonomy->labels->name
				: $taxonomy->name;
			$conflicts[] = [
				'type'  => 'taxonomy',
				'label' => sprintf(
					/* translators: %s: public taxonomy label. */
					__( 'The %s taxonomy already uses this path.', 'core-blueprint-docs' ),
					$label
				),
				'url'   => '',
			];
		}

		return [
			'ready'     => [] === $conflicts,
			'base'      => $base,
			'conflicts' => $conflicts,
		];
	}

	public static function namespace_ready( ?string $base = null ): bool {
		return self::namespace_analyze( $base )['ready'];
	}

	/**
	 * Lightweight runtime guard available before all third-party post types and
	 * taxonomies are guaranteed to be registered. Existing Pages and WordPress
	 * reserved roots always retain priority over the Docs namespace.
	 */
	public static function runtime_ready( ?string $base = null ): bool {
		$base = Settings::sanitize_rewrite_base( $base ?? Settings::rewrite_base() );
		$first_segment = explode( '/', $base )[0] ?? '';
		if ( in_array( $first_segment, self::RESERVED_ROOTS, true ) ) {
			return false;
		}

		$page = get_page_by_path( $base, OBJECT, 'page' );
		return ! ( $page instanceof WP_Post && 'trash' !== $page->post_status );
	}

	private static function post_type_archive_base( WP_Post_Type $post_type ): string {
		if ( is_string( $post_type->has_archive ) && '' !== $post_type->has_archive ) {
			return Settings::sanitize_rewrite_base( $post_type->has_archive );
		}

		if ( true !== $post_type->has_archive || false === $post_type->rewrite ) {
			return '';
		}

		if (
			is_array( $post_type->rewrite )
			&& isset( $post_type->rewrite['slug'] )
			&& is_string( $post_type->rewrite['slug'] )
			&& '' !== $post_type->rewrite['slug']
		) {
			return Settings::sanitize_rewrite_base( $post_type->rewrite['slug'] );
		}

		return Settings::sanitize_rewrite_base( $post_type->name );
	}
}
