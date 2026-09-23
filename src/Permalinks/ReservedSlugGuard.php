<?php
declare(strict_types=1);

namespace CB\Docs\Permalinks;

use CB\Docs\Content\Taxonomies;
use CB\Docs\Settings;

defined( 'ABSPATH' ) || exit;

final class ReservedSlugGuard {
	public static function init(): void {
		add_filter( 'pre_insert_term', [ __CLASS__, 'validate_new_term' ], 10, 3 );
		add_filter( 'wp_update_term_data', [ __CLASS__, 'protect_updated_term' ], 10, 4 );
	}

	/** @return string|\WP_Error */
	public static function validate_new_term( string $term, string $taxonomy, array $args ): string|\WP_Error {
		if ( ! Settings::hierarchy_enabled() || Taxonomies::CATEGORY !== $taxonomy ) {
			return $term;
		}

		$parent = max( 0, (int) ( $args['parent'] ?? 0 ) );
		$slug = isset( $args['slug'] ) && is_string( $args['slug'] ) && '' !== $args['slug']
			? sanitize_title( $args['slug'] )
			: sanitize_title( $term );

		if ( 0 === $parent && self::reserved( $slug ) ) {
			return new \WP_Error(
				'cb_docs_reserved_category_slug',
				__( 'This top-level Doc Category slug is reserved by the active Docs URL structure.', 'core-blueprint-docs' )
			);
		}

		return $term;
	}

	/**
	 * Fail closed for updates by retaining the previous valid structural values.
	 *
	 * @param array<string,mixed> $data
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public static function protect_updated_term( array $data, int $term_id, string $taxonomy, array $args ): array {
		if ( ! Settings::hierarchy_enabled() || Taxonomies::CATEGORY !== $taxonomy ) {
			return $data;
		}

		$parent = max( 0, (int) ( $data['parent'] ?? $args['parent'] ?? 0 ) );
		$slug = sanitize_title( (string) ( $data['slug'] ?? $args['slug'] ?? '' ) );
		if ( 0 !== $parent || ! self::reserved( $slug ) ) {
			return $data;
		}

		$current = get_term( $term_id, Taxonomies::CATEGORY );
		if ( ! $current instanceof \WP_Term ) {
			return $data;
		}

		$data['slug'] = (string) $current->slug;
		$data['parent'] = (int) $current->parent;
		return $data;
	}

	private static function reserved( string $slug ): bool {
		return in_array( $slug, RouteIndex::RESERVED_ROOTS, true );
	}
}
