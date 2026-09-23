<?php
declare(strict_types=1);

namespace CB\Docs\Structure;

use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Resolve documents to their canonical Organizer location.
 *
 * This service deliberately works from native taxonomy relationships and the
 * shared StructuralCategory resolver. It does not depend on permalink or
 * builder state.
 */
final class DocumentLocation {
	/** @var array<int,int>|null */
	private static ?array $parents = null;

	/** @var array<int,int|null> */
	private static array $locations = [];

	/** @var array<int,int[]> Structural category ID => canonical document IDs. */
	private static array $siblings = [];

	public static function structural_category_id( int $document_id ): ?int {
		if ( $document_id <= 0 || PostType::TYPE !== get_post_type( $document_id ) ) {
			return null;
		}

		if ( array_key_exists( $document_id, self::$locations ) ) {
			return self::$locations[ $document_id ];
		}

		$term_ids = wp_get_object_terms(
			$document_id,
			Taxonomies::CATEGORY,
			[ 'fields' => 'ids' ]
		);
		if ( is_wp_error( $term_ids ) ) {
			self::$locations[ $document_id ] = null;
			return null;
		}

		$resolved = StructuralCategory::resolve(
			array_values( array_unique( array_map( 'absint', $term_ids ) ) ),
			self::parents()
		);
		self::$locations[ $document_id ] = $resolved;
		return $resolved;
	}

	/**
	 * Return documents whose canonical structural location matches the current
	 * document. Unassigned or ambiguous documents intentionally return none.
	 *
	 * @return int[]
	 */
	public static function sibling_document_ids( int $document_id ): array {
		$structural_id = self::structural_category_id( $document_id );
		if ( null === $structural_id || $structural_id <= 0 ) {
			return [];
		}

		if ( isset( self::$siblings[ $structural_id ] ) ) {
			return self::$siblings[ $structural_id ];
		}

		$object_ids = get_objects_in_term( $structural_id, Taxonomies::CATEGORY );
		if ( is_wp_error( $object_ids ) ) {
			self::$siblings[ $structural_id ] = [];
			return [];
		}

		$object_ids = array_values( array_unique( array_filter( array_map( 'absint', $object_ids ) ) ) );
		if ( empty( $object_ids ) ) {
			self::$siblings[ $structural_id ] = [];
			return [];
		}

		$relations = wp_get_object_terms(
			$object_ids,
			Taxonomies::CATEGORY,
			[ 'fields' => 'all_with_object_id' ]
		);
		if ( is_wp_error( $relations ) ) {
			self::$siblings[ $structural_id ] = [];
			return [];
		}

		$term_ids_by_object = [];
		foreach ( $relations as $relation ) {
			if ( ! $relation instanceof \WP_Term || ! isset( $relation->object_id ) ) {
				continue;
			}
			$object_id = (int) $relation->object_id;
			$term_id   = (int) $relation->term_id;
			if ( $object_id > 0 && $term_id > 0 ) {
				$term_ids_by_object[ $object_id ][] = $term_id;
			}
		}

		$parents = self::parents();
		$matches = [];
		foreach ( $object_ids as $object_id ) {
			$term_ids = array_values( array_unique( array_map( 'absint', $term_ids_by_object[ $object_id ] ?? [] ) ) );
			if ( $structural_id === StructuralCategory::resolve( $term_ids, $parents ) ) {
				$matches[] = $object_id;
				self::$locations[ $object_id ] = $structural_id;
			}
		}

		sort( $matches, SORT_NUMERIC );
		self::$siblings[ $structural_id ] = $matches;
		return $matches;
	}

	/** @return array<int,int> */
	private static function parents(): array {
		if ( null !== self::$parents ) {
			return self::$parents;
		}

		$terms = get_terms(
			[
				'taxonomy'   => Taxonomies::CATEGORY,
				'hide_empty' => false,
			]
		);
		if ( is_wp_error( $terms ) ) {
			self::$parents = [];
			return self::$parents;
		}

		$parents = [];
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$parents[ (int) $term->term_id ] = max( 0, (int) $term->parent );
			}
		}

		self::$parents = $parents;
		return self::$parents;
	}
}
