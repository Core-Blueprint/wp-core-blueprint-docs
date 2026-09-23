<?php
declare(strict_types=1);

namespace CB\Docs\Permalinks;

defined( 'ABSPATH' ) || exit;

/**
 * Pure route planner for the public Docs namespace.
 *
 * Input projections contain no WordPress objects. This keeps collision,
 * canonical-path and readiness semantics deterministic and directly testable.
 */
final class RouteIndex {
	public const RESERVED_ROOTS = [ 'tag', 'document' ];

	/**
	 * @param array<int,array{id:int,parent:int,slug:string}> $categories
	 * @param array<int,array{id:int,slug:string,structural_category_id:int|null,public?:bool}> $documents
	 * @return array{
	 *   categories:array<int,string>,
	 *   category_by_path:array<string,int>,
	 *   documents:array<int,array{path:string,fallback:bool,reason:string}>,
	 *   document_by_path:array<string,int>,
	 *   readiness:array{
	 *     blocking:int,
	 *     warnings:int,
	 *     reserved_categories:int,
	 *     duplicate_category_paths:int,
	 *     document_category_collisions:int,
	 *     duplicate_document_paths:int,
	 *     legacy_simple_collisions:int,
	 *     unassigned:int,
	 *     ambiguous:int
	 *   }
	 * }
	 */
	public static function build( array $categories, array $documents ): array {
		$category_rows = self::normalize_categories( $categories );
		$paths = [];
		$path_owner = [];
		$reserved_categories = 0;
		$duplicate_category_paths = 0;

		foreach ( array_keys( $category_rows ) as $term_id ) {
			$path = self::category_path( $term_id, $category_rows );
			if ( null === $path ) {
				continue;
			}

			$paths[ $term_id ] = $path;
			if ( isset( $path_owner[ $path ] ) && $path_owner[ $path ] !== $term_id ) {
				++$duplicate_category_paths;
			} else {
				$path_owner[ $path ] = $term_id;
			}

			$row = $category_rows[ $term_id ];
			if ( 0 === $row['parent'] && in_array( $row['slug'], self::RESERVED_ROOTS, true ) ) {
				++$reserved_categories;
			}
		}

		$document_routes = [];
		$document_by_path = [];
		$document_category_collisions = 0;
		$duplicate_document_paths = 0;
		$legacy_simple_collisions = 0;
		$unassigned = 0;
		$ambiguous = 0;

		foreach ( self::normalize_documents( $documents ) as $document ) {
			$id = $document['id'];
			$slug = $document['slug'];
			$structural_id = $document['structural_category_id'];
			$reason = '';
			$fallback = false;
			$path = '';

			if ( null === $structural_id || $structural_id <= 0 || ! isset( $paths[ $structural_id ] ) ) {
				$fallback = true;
				$reason = 'unresolved-structure';
				$path = 'document/' . $slug;
				if ( 'ambiguous' === (string) ( $document['structure_state'] ?? '' ) ) {
					++$ambiguous;
				} else {
					++$unassigned;
				}
			} else {
				$candidate = $paths[ $structural_id ] . '/' . $slug;
				if ( isset( $path_owner[ $candidate ] ) ) {
					$fallback = true;
					$reason = 'category-collision';
					$path = 'document/' . $slug;
					++$document_category_collisions;
				} else {
					$path = $candidate;
				}
			}

			$document_routes[ $id ] = [
				'path'     => $path,
				'fallback' => $fallback,
				'reason'   => $reason,
			];

			if ( ! empty( $document['public'] ) ) {
				if ( isset( $document_by_path[ $path ] ) && $document_by_path[ $path ] !== $id ) {
					++$duplicate_document_paths;
				} else {
					$document_by_path[ $path ] = $id;
				}

				if ( isset( $path_owner[ $slug ] ) ) {
					++$legacy_simple_collisions;
				}
			}
		}

		$blocking = $reserved_categories
			+ $duplicate_category_paths
			+ $duplicate_document_paths
			+ $legacy_simple_collisions;
		$warnings = $document_category_collisions + $unassigned + $ambiguous;

		return [
			'categories'       => $paths,
			'category_by_path' => $path_owner,
			'documents'        => $document_routes,
			'document_by_path' => $document_by_path,
			'readiness'        => [
				'blocking'                     => $blocking,
				'warnings'                     => $warnings,
				'reserved_categories'          => $reserved_categories,
				'duplicate_category_paths'     => $duplicate_category_paths,
				'document_category_collisions' => $document_category_collisions,
				'duplicate_document_paths'     => $duplicate_document_paths,
				'legacy_simple_collisions'     => $legacy_simple_collisions,
				'unassigned'                   => $unassigned,
				'ambiguous'                    => $ambiguous,
			],
		];
	}

	/**
	 * @param array<int,array{id:int,parent:int,slug:string}> $rows
	 */
	private static function category_path( int $term_id, array $rows ): ?string {
		$segments = [];
		$seen = [];
		$current = $term_id;

		while ( $current > 0 ) {
			if ( isset( $seen[ $current ] ) || ! isset( $rows[ $current ] ) ) {
				return null;
			}
			$seen[ $current ] = true;
			array_unshift( $segments, $rows[ $current ]['slug'] );
			$current = $rows[ $current ]['parent'];
		}

		return empty( $segments ) ? null : implode( '/', $segments );
	}

	/**
	 * @param array<int,mixed> $categories
	 * @return array<int,array{id:int,parent:int,slug:string}>
	 */
	private static function normalize_categories( array $categories ): array {
		$rows = [];
		foreach ( $categories as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = (int) ( $row['id'] ?? 0 );
			$slug = trim( (string) ( $row['slug'] ?? '' ), '/' );
			if ( $id <= 0 || '' === $slug ) {
				continue;
			}
			$rows[ $id ] = [
				'id'     => $id,
				'parent' => max( 0, (int) ( $row['parent'] ?? 0 ) ),
				'slug'   => $slug,
			];
		}
		return $rows;
	}

	/**
	 * @param array<int,mixed> $documents
	 * @return array<int,array{id:int,slug:string,structural_category_id:int|null,structure_state:string,public:bool}>
	 */
	private static function normalize_documents( array $documents ): array {
		$rows = [];
		foreach ( $documents as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = (int) ( $row['id'] ?? 0 );
			$slug = trim( (string) ( $row['slug'] ?? '' ), '/' );
			if ( $id <= 0 || '' === $slug ) {
				continue;
			}
			$structural = isset( $row['structural_category_id'] ) && null !== $row['structural_category_id']
				? (int) $row['structural_category_id']
				: null;
			$rows[] = [
				'id'                     => $id,
				'slug'                   => $slug,
				'structural_category_id' => $structural,
				'structure_state'        => sanitize_key( (string) ( $row['structure_state'] ?? '' ) ),
				'public'                 => ! array_key_exists( 'public', $row ) || (bool) $row['public'],
			];
		}
		return $rows;
	}
}
