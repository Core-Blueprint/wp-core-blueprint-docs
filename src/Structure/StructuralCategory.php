<?php
declare(strict_types=1);

namespace CB\Docs\Structure;

defined( 'ABSPATH' ) || exit;

/**
 * Resolve Doc Category assignments to one structural location.
 *
 * Multiple assigned categories are structurally unambiguous when every
 * assignment lies on one ancestor-to-descendant path. The deepest assigned
 * term is then the canonical Organizer location. Assignments across separate
 * branches remain ambiguous and require explicit administrator review.
 */
final class StructuralCategory {

	/**
	 * @param array<int,mixed> $term_ids
	 * @param array<int,int>   $parents Term ID => parent term ID.
	 */
	public static function resolve( array $term_ids, array $parents ): ?int {
		$ids = self::normalize_ids( $term_ids );
		if ( empty( $ids ) ) {
			return null;
		}

		foreach ( $ids as $candidate ) {
			$lineage = self::lineage( $candidate, $parents );
			if ( null === $lineage ) {
				continue;
			}

			$assigned_on_lineage = array_intersect( $ids, $lineage );
			if ( count( $assigned_on_lineage ) === count( $ids ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Build the parent map from a Snapshot term projection.
	 *
	 * @param array<int,mixed> $terms
	 * @return array<int,int>
	 */
	public static function parent_map( array $terms ): array {
		$parents = [];
		foreach ( $terms as $term ) {
			if ( ! is_array( $term ) ) {
				continue;
			}
			$id = (int) ( $term['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$parents[ $id ] = max( 0, (int) ( $term['parent'] ?? 0 ) );
		}
		return $parents;
	}

	/**
	 * @param array<int,mixed> $term_ids
	 * @return int[]
	 */
	private static function normalize_ids( array $term_ids ): array {
		$ids = [];
		foreach ( $term_ids as $raw ) {
			$id = (int) $raw;
			if ( $id > 0 ) {
				$ids[ $id ] = $id;
			}
		}
		return array_values( $ids );
	}

	/**
	 * Return candidate + ancestors, or null when malformed cyclic hierarchy is detected.
	 *
	 * @param array<int,int> $parents
	 * @return int[]|null
	 */
	private static function lineage( int $term_id, array $parents ): ?array {
		$lineage = [];
		$seen = [];
		$current = $term_id;

		while ( $current > 0 ) {
			if ( isset( $seen[ $current ] ) ) {
				return null;
			}
			$seen[ $current ] = true;
			$lineage[] = $current;
			$current = max( 0, (int) ( $parents[ $current ] ?? 0 ) );
		}

		return $lineage;
	}
}
