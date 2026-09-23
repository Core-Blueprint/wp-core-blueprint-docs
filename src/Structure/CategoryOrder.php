<?php
declare(strict_types=1);

namespace CB\Docs\Structure;

use CB\Docs\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

final class CategoryOrder {
	public const META_KEY = 'cb_docs_order';

	public static function register(): void {
		register_term_meta(
			Taxonomies::CATEGORY,
			self::META_KEY,
			[
				'single'            => true,
				'type'              => 'integer',
				'default'           => 0,
				'show_in_rest'      => false,
				'sanitize_callback' => 'absint',
			]
		);
	}

	public static function value( int $term_id ): int {
		return max( 0, (int) get_term_meta( $term_id, self::META_KEY, true ) );
	}

	/**
	 * @param \WP_Term[] $terms
	 * @return \WP_Term[]
	 */
	public static function sort_terms( array $terms ): array {
		usort(
			$terms,
			static function ( \WP_Term $a, \WP_Term $b ): int {
				$a_order = self::value( (int) $a->term_id );
				$b_order = self::value( (int) $b->term_id );

				if ( $a_order > 0 || $b_order > 0 ) {
					if ( $a_order <= 0 ) {
						return 1;
					}
					if ( $b_order <= 0 ) {
						return -1;
					}
					if ( $a_order !== $b_order ) {
						return $a_order <=> $b_order;
					}
				}

				$name = strcasecmp( (string) $a->name, (string) $b->name );
				return 0 !== $name ? $name : (int) $a->term_id <=> (int) $b->term_id;
			}
		);

		return $terms;
	}

	/** @return \WP_Term[]|\WP_Error */
	public static function siblings( int $parent_id ): array|\WP_Error {
		$terms = get_terms( [
			'taxonomy'   => Taxonomies::CATEGORY,
			'hide_empty' => false,
			'parent'     => max( 0, $parent_id ),
			'number'     => Snapshot::MAX_TERMS + 1,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		if ( count( $terms ) > Snapshot::MAX_TERMS ) {
			return new \WP_Error(
				'cb_docs_structure_too_many_terms',
				__( 'The documentation structure contains too many categories for the Organizer.', 'core-blueprint-docs' ),
				[ 'status' => 409 ]
			);
		}

		return self::sort_terms( array_values( array_filter( $terms, static fn( $term ): bool => $term instanceof \WP_Term ) ) );
	}

	/**
	 * Persist one complete sibling order.
	 *
	 * @param array<int,mixed> $term_ids
	 * @return true|\WP_Error
	 */
	public static function write( array $term_ids ): true|\WP_Error {
		try {
			$positions = Order::positions( $term_ids );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'cb_docs_invalid_term_order', $e->getMessage(), [ 'status' => 400 ] );
		}

		foreach ( $positions as $term_id => $position ) {
			$id = absint( $term_id );
			if ( $id <= 0 || ! term_exists( $id, Taxonomies::CATEGORY ) ) {
				return new \WP_Error(
					'cb_docs_invalid_term_order',
					__( 'A documentation category in the requested order no longer exists.', 'core-blueprint-docs' ),
					[ 'status' => 409 ]
				);
			}

			$current = self::value( $id );
			if ( $current === $position ) {
				continue;
			}

			$result = update_term_meta( $id, self::META_KEY, $position );
			if ( is_wp_error( $result ) || false === $result ) {
				return is_wp_error( $result )
					? $result
					: new \WP_Error(
						'cb_docs_term_order_write_failed',
						__( 'The documentation category order could not be saved.', 'core-blueprint-docs' ),
						[ 'status' => 500 ]
					);
			}
		}

		return true;
	}
}
