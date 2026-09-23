<?php
declare(strict_types=1);

namespace CB\Docs\Structure;

use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;
use CB\Docs\Governance\Events;

defined( 'ABSPATH' ) || exit;

final class Mutation {

	/** @return array{revision:string}|\WP_Error */
	public static function move_document(
		int $document_id,
		int $target_term_id,
		int $target_index,
		string $expected_revision
	): array|\WP_Error {
		$revision_error = self::validate_revision( $expected_revision );
		if ( is_wp_error( $revision_error ) ) {
			return $revision_error;
		}

		$owner = MutationLock::acquire();
		if ( is_wp_error( $owner ) ) {
			return $owner;
		}

		try {
			$snapshot = Snapshot::build();
			if ( is_wp_error( $snapshot ) ) {
				return $snapshot;
			}
			$stale = self::require_current_revision( $snapshot, $expected_revision );
			if ( is_wp_error( $stale ) ) {
				return $stale;
			}

			$post = get_post( $document_id );
			if ( ! $post instanceof \WP_Post || PostType::TYPE !== $post->post_type || 'trash' === $post->post_status ) {
				return new \WP_Error(
					'cb_docs_document_not_found',
					__( 'The documentation article no longer exists.', 'core-blueprint-docs' ),
					[ 'status' => 404 ]
				);
			}
			if ( ! current_user_can( 'edit_post', $document_id ) ) {
				return new \WP_Error(
					'cb_docs_document_forbidden',
					__( 'You do not have permission to move this documentation article.', 'core-blueprint-docs' ),
					[ 'status' => 403 ]
				);
			}

			$target_term = get_term( $target_term_id, Taxonomies::CATEGORY );
			if ( ! $target_term instanceof \WP_Term ) {
				return new \WP_Error(
					'cb_docs_category_not_found',
					__( 'The target documentation category no longer exists.', 'core-blueprint-docs' ),
					[ 'status' => 404 ]
				);
			}

			$old_term_ids = wp_get_object_terms( $document_id, Taxonomies::CATEGORY, [ 'fields' => 'ids' ] );
			if ( is_wp_error( $old_term_ids ) ) {
				return $old_term_ids;
			}
			$old_term_ids = array_values( array_unique( array_map( 'absint', $old_term_ids ) ) );
			$same_structural_category = 1 === count( $old_term_ids ) && (int) $old_term_ids[0] === $target_term_id;

			if ( ! $same_structural_category ) {
				$taxonomy = get_taxonomy( Taxonomies::CATEGORY );
				$assign_cap = $taxonomy && isset( $taxonomy->cap->assign_terms )
					? (string) $taxonomy->cap->assign_terms
					: 'edit_posts';
				if ( ! current_user_can( $assign_cap ) ) {
					return new \WP_Error(
						'cb_docs_category_assign_forbidden',
						__( 'You do not have permission to change documentation categories.', 'core-blueprint-docs' ),
						[ 'status' => 403 ]
					);
				}
			}

			$target_ids = self::document_ids_for_term( $snapshot, $target_term_id );
			try {
				$new_target_ids = Order::place( $target_ids, $document_id, $target_index );
			} catch ( \InvalidArgumentException $e ) {
				return new \WP_Error(
					'cb_docs_invalid_document_order',
					$e->getMessage(),
					[ 'status' => 400 ]
				);
			}

			$source_term_id = 1 === count( $old_term_ids ) ? (int) $old_term_ids[0] : 0;
			$new_source_ids = [];
			if ( $source_term_id > 0 && $source_term_id !== $target_term_id ) {
				$new_source_ids = array_values( array_filter(
					self::document_ids_for_term( $snapshot, $source_term_id ),
					static fn( int $id ): bool => $id !== $document_id
				) );
			}

			$affected_ids = array_values( array_unique( array_merge(
				[ $document_id ],
				$target_ids,
				$new_target_ids,
				$source_term_id > 0 ? self::document_ids_for_term( $snapshot, $source_term_id ) : []
			) ) );
			$old_orders = self::capture_document_orders( $affected_ids );

			$result = Events::without_change_capture(
				static function () use (
					$document_id,
					$target_term_id,
					$source_term_id,
					$new_source_ids,
					$new_target_ids,
					$same_structural_category
				): true|\WP_Error {
					if ( ! $same_structural_category ) {
						$assigned = wp_set_object_terms( $document_id, [ $target_term_id ], Taxonomies::CATEGORY, false );
						if ( is_wp_error( $assigned ) ) {
							return $assigned;
						}
					}

					if ( $source_term_id > 0 && $source_term_id !== $target_term_id ) {
						$source_write = self::write_document_order( $new_source_ids );
						if ( is_wp_error( $source_write ) ) {
							return $source_write;
						}
					}

					return self::write_document_order( $new_target_ids );
				}
			);

			if ( is_wp_error( $result ) ) {
				Events::without_change_capture(
					static function () use ( $document_id, $old_term_ids, $old_orders ): void {
						wp_set_object_terms( $document_id, $old_term_ids, Taxonomies::CATEGORY, false );
						self::restore_document_orders( $old_orders );
					}
				);
				return $result;
			}

			$after = Snapshot::build();
			if ( is_wp_error( $after ) ) {
				return $after;
			}

			Events::record_structure_updated(
				'document_move',
				[
					'document_id'      => $document_id,
					'from_category_id' => $source_term_id,
					'to_category_id'   => $target_term_id,
					'to_position'      => $target_index,
				]
			);

			return [ 'revision' => (string) $after['revision'] ];
		} finally {
			MutationLock::release( $owner );
		}
	}

	/** @return array{revision:string}|\WP_Error */
	public static function reorder_term(
		int $term_id,
		int $target_index,
		string $expected_revision
	): array|\WP_Error {
		$revision_error = self::validate_revision( $expected_revision );
		if ( is_wp_error( $revision_error ) ) {
			return $revision_error;
		}

		$owner = MutationLock::acquire();
		if ( is_wp_error( $owner ) ) {
			return $owner;
		}

		try {
			$snapshot = Snapshot::build();
			if ( is_wp_error( $snapshot ) ) {
				return $snapshot;
			}
			$stale = self::require_current_revision( $snapshot, $expected_revision );
			if ( is_wp_error( $stale ) ) {
				return $stale;
			}

			$term = get_term( $term_id, Taxonomies::CATEGORY );
			if ( ! $term instanceof \WP_Term ) {
				return new \WP_Error(
					'cb_docs_category_not_found',
					__( 'The documentation category no longer exists.', 'core-blueprint-docs' ),
					[ 'status' => 404 ]
				);
			}

			$taxonomy = get_taxonomy( Taxonomies::CATEGORY );
			$manage_cap = $taxonomy && isset( $taxonomy->cap->manage_terms )
				? (string) $taxonomy->cap->manage_terms
				: 'manage_categories';
			if ( ! current_user_can( $manage_cap ) ) {
				return new \WP_Error(
					'cb_docs_category_order_forbidden',
					__( 'You do not have permission to reorder documentation categories.', 'core-blueprint-docs' ),
					[ 'status' => 403 ]
				);
			}

			$siblings = CategoryOrder::siblings( (int) $term->parent );
			if ( is_wp_error( $siblings ) ) {
				return $siblings;
			}
			$sibling_ids = array_map( static fn( \WP_Term $item ): int => (int) $item->term_id, $siblings );

			try {
				$ordered_ids = Order::move( $sibling_ids, $term_id, $target_index );
			} catch ( \InvalidArgumentException $e ) {
				return new \WP_Error(
					'cb_docs_invalid_term_order',
					$e->getMessage(),
					[ 'status' => 400 ]
				);
			}

			$old_orders = [];
			foreach ( $sibling_ids as $sibling_id ) {
				$old_orders[ $sibling_id ] = CategoryOrder::value( $sibling_id );
			}

			$write = CategoryOrder::write( $ordered_ids );
			if ( is_wp_error( $write ) ) {
				foreach ( $old_orders as $sibling_id => $order ) {
					update_term_meta( $sibling_id, CategoryOrder::META_KEY, $order );
				}
				return $write;
			}

			$after = Snapshot::build();
			if ( is_wp_error( $after ) ) {
				return $after;
			}

			Events::record_structure_updated(
				'category_reorder',
				[
					'category_id' => $term_id,
					'parent_id'   => (int) $term->parent,
					'to_position' => $target_index,
				]
			);

			return [ 'revision' => (string) $after['revision'] ];
		} finally {
			MutationLock::release( $owner );
		}
	}

	/** @param array<string,mixed> $snapshot @return int[] */
	private static function document_ids_for_term( array $snapshot, int $term_id ): array {
		$documents = (array) ( $snapshot['docs_by_term'][ $term_id ] ?? [] );
		return array_values( array_filter( array_map(
			static fn( $document ): int => is_array( $document ) ? (int) ( $document['id'] ?? 0 ) : 0,
			$documents
		) ) );
	}

	/** @param int[] $document_ids @return array<int,int> */
	private static function capture_document_orders( array $document_ids ): array {
		$orders = [];
		foreach ( $document_ids as $document_id ) {
			$post = get_post( $document_id );
			if ( $post instanceof \WP_Post && PostType::TYPE === $post->post_type ) {
				$orders[ $document_id ] = (int) $post->menu_order;
			}
		}
		return $orders;
	}

	/** @param int[] $document_ids @return true|\WP_Error */
	private static function write_document_order( array $document_ids ): true|\WP_Error {
		try {
			$positions = Order::positions( $document_ids );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'cb_docs_invalid_document_order', $e->getMessage(), [ 'status' => 400 ] );
		}

		foreach ( $positions as $document_id => $position ) {
			$id = absint( $document_id );
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post || PostType::TYPE !== $post->post_type ) {
				return new \WP_Error(
					'cb_docs_document_order_write_failed',
					__( 'A documentation article in the requested order no longer exists.', 'core-blueprint-docs' ),
					[ 'status' => 409 ]
				);
			}
			if ( (int) $post->menu_order === $position ) {
				continue;
			}

			$updated = wp_update_post(
				[
					'ID'         => $id,
					'menu_order' => $position,
				],
				true
			);
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}
		return true;
	}

	/** @param array<int,int> $orders */
	private static function restore_document_orders( array $orders ): void {
		foreach ( $orders as $document_id => $menu_order ) {
			wp_update_post(
				[
					'ID'         => $document_id,
					'menu_order' => $menu_order,
				],
				true
			);
		}
	}

	/** @return true|\WP_Error */
	private static function validate_revision( string $expected_revision ): true|\WP_Error {
		if ( ! Revision::valid( $expected_revision ) ) {
			return new \WP_Error(
				'cb_docs_invalid_structure_revision',
				__( 'The documentation structure revision is invalid.', 'core-blueprint-docs' ),
				[ 'status' => 400 ]
			);
		}
		return true;
	}

	/** @param array<string,mixed> $snapshot @return true|\WP_Error */
	private static function require_current_revision( array $snapshot, string $expected_revision ): true|\WP_Error {
		$current = (string) ( $snapshot['revision'] ?? '' );
		if ( '' !== $current && hash_equals( $current, $expected_revision ) ) {
			return true;
		}

		return new \WP_Error(
			'cb_docs_structure_stale',
			__( 'The documentation structure changed. Reload the Organizer before continuing.', 'core-blueprint-docs' ),
			[
				'status'   => 409,
				'stale'    => true,
				'revision' => $current,
			]
		);
	}
}
