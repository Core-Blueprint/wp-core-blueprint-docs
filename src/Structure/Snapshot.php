<?php
declare(strict_types=1);

namespace CB\Docs\Structure;

use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

final class Snapshot {
	public const MAX_TERMS = 250;
	public const MAX_DOCS  = 500;

	private const STATUSES = [ 'publish', 'draft', 'pending', 'future', 'private' ];

	/** @return array<string,mixed>|\WP_Error */
	public static function build(): array|\WP_Error {
		$terms = get_terms( [
			'taxonomy'   => Taxonomies::CATEGORY,
			'hide_empty' => false,
			'number'     => self::MAX_TERMS + 1,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		if ( count( $terms ) > self::MAX_TERMS ) {
			return new \WP_Error(
				'cb_docs_structure_too_many_terms',
				__( 'The documentation structure contains too many categories for the Organizer.', 'core-blueprint-docs' ),
				[ 'status' => 409 ]
			);
		}

		$terms = array_values( array_filter( $terms, static fn( $term ): bool => $term instanceof \WP_Term ) );
		if ( ! empty( $terms ) ) {
			update_meta_cache( 'term', array_map( static fn( \WP_Term $term ): int => (int) $term->term_id, $terms ) );
		}

		$posts = get_posts( [
			'post_type'           => PostType::TYPE,
			'post_status'         => self::STATUSES,
			'posts_per_page'      => self::MAX_DOCS + 1,
			'orderby'             => [ 'menu_order' => 'ASC', 'title' => 'ASC', 'ID' => 'ASC' ],
			'order'               => 'ASC',
			'suppress_filters'    => false,
			'ignore_sticky_posts' => true,
		] );
		if ( count( $posts ) > self::MAX_DOCS ) {
			return new \WP_Error(
				'cb_docs_structure_too_many_documents',
				__( 'The documentation structure contains too many documents for the Organizer.', 'core-blueprint-docs' ),
				[ 'status' => 409 ]
			);
		}

		$posts = array_values( array_filter( $posts, static fn( $post ): bool => $post instanceof \WP_Post ) );
		$post_ids = array_map( static fn( \WP_Post $post ): int => (int) $post->ID, $posts );
		$term_ids_by_post = self::term_ids_by_post( $post_ids );

		$term_projection = [];
		foreach ( $terms as $term ) {
			$term_projection[] = [
				'id'     => (int) $term->term_id,
				'parent' => (int) $term->parent,
				'name'   => (string) $term->name,
				'slug'   => (string) $term->slug,
				'order'  => CategoryOrder::value( (int) $term->term_id ),
			];
		}

		$documents = [];
		$docs_by_term = [];
		$unassigned = [];
		$ambiguous = [];

		foreach ( $posts as $post ) {
			$id = (int) $post->ID;
			$term_ids = array_values( array_unique( array_map( 'absint', $term_ids_by_post[ $id ] ?? [] ) ) );
			sort( $term_ids, SORT_NUMERIC );

			$document = [
				'id'         => $id,
				'title'      => get_the_title( $post ),
				'status'     => (string) $post->post_status,
				'menu_order' => (int) $post->menu_order,
				'term_ids'   => $term_ids,
			];
			$documents[] = $document;

			if ( 0 === count( $term_ids ) ) {
				$unassigned[] = $document;
			} elseif ( 1 === count( $term_ids ) ) {
				$docs_by_term[ $term_ids[0] ][] = $document;
			} else {
				$ambiguous[] = $document;
			}
		}

		foreach ( $docs_by_term as &$term_documents ) {
			self::sort_documents( $term_documents );
		}
		unset( $term_documents );
		self::sort_documents( $unassigned );
		self::sort_documents( $ambiguous );

		$snapshot = [
			'terms'        => $term_projection,
			'documents'    => $documents,
			'docs_by_term' => $docs_by_term,
			'unassigned'   => $unassigned,
			'ambiguous'    => $ambiguous,
		];
		$snapshot['revision'] = Revision::from_snapshot( $snapshot );

		return $snapshot;
	}

	/**
	 * @param int[] $post_ids
	 * @return array<int,int[]>
	 */
	private static function term_ids_by_post( array $post_ids ): array {
		if ( empty( $post_ids ) ) {
			return [];
		}

		$relations = wp_get_object_terms(
			$post_ids,
			Taxonomies::CATEGORY,
			[ 'fields' => 'all_with_object_id' ]
		);
		if ( is_wp_error( $relations ) ) {
			return [];
		}

		$by_post = [];
		foreach ( $relations as $relation ) {
			if ( ! $relation instanceof \WP_Term || ! isset( $relation->object_id ) ) {
				continue;
			}
			$post_id = (int) $relation->object_id;
			$term_id = (int) $relation->term_id;
			if ( $post_id > 0 && $term_id > 0 ) {
				$by_post[ $post_id ][] = $term_id;
			}
		}
		return $by_post;
	}

	/** @param array<int,array<string,mixed>> $documents */
	private static function sort_documents( array &$documents ): void {
		usort(
			$documents,
			static function ( array $a, array $b ): int {
				$order = (int) ( $a['menu_order'] ?? 0 ) <=> (int) ( $b['menu_order'] ?? 0 );
				if ( 0 !== $order ) {
					return $order;
				}
				$title = strcasecmp( (string) ( $a['title'] ?? '' ), (string) ( $b['title'] ?? '' ) );
				return 0 !== $title ? $title : (int) ( $a['id'] ?? 0 ) <=> (int) ( $b['id'] ?? 0 );
			}
		);
	}
}
