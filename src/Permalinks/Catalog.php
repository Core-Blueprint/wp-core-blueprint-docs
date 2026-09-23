<?php
declare(strict_types=1);

namespace CB\Docs\Permalinks;

use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;
use CB\Docs\Structure\StructuralCategory;

defined( 'ABSPATH' ) || exit;

final class Catalog {
	/** @var array<string,mixed>|null */
	private static ?array $index = null;

	public static function init(): void {
		add_action( 'save_post_' . PostType::TYPE, [ __CLASS__, 'reset' ] );
		add_action( 'set_object_terms', [ __CLASS__, 'reset_on_terms' ], 10, 4 );
		add_action( 'created_' . Taxonomies::CATEGORY, [ __CLASS__, 'reset' ] );
		add_action( 'edited_' . Taxonomies::CATEGORY, [ __CLASS__, 'reset' ] );
		add_action( 'delete_' . Taxonomies::CATEGORY, [ __CLASS__, 'reset' ] );
	}

	/** @return array<string,mixed> */
	public static function index(): array {
		if ( null !== self::$index ) {
			return self::$index;
		}

		$terms = get_terms( [
			'taxonomy'   => Taxonomies::CATEGORY,
			'hide_empty' => false,
			'orderby'    => 'term_id',
			'order'      => 'ASC',
		] );
		$terms = is_wp_error( $terms ) ? [] : array_values( array_filter( $terms, static fn( $term ): bool => $term instanceof \WP_Term ) );

		$categories = [];
		$parent_map = [];
		foreach ( $terms as $term ) {
			$id = (int) $term->term_id;
			$categories[] = [
				'id'     => $id,
				'parent' => (int) $term->parent,
				'slug'   => (string) $term->slug,
			];
			$parent_map[ $id ] = (int) $term->parent;
		}

		$posts = get_posts( [
			'post_type'           => PostType::TYPE,
			'post_status'         => [ 'publish', 'private', 'future', 'draft', 'pending' ],
			'posts_per_page'      => -1,
			'orderby'             => 'ID',
			'order'               => 'ASC',
			'suppress_filters'    => false,
			'ignore_sticky_posts' => true,
		] );
		$posts = array_values( array_filter( $posts, static fn( $post ): bool => $post instanceof \WP_Post ) );
		$post_ids = array_map( static fn( \WP_Post $post ): int => (int) $post->ID, $posts );

		$term_ids_by_post = [];
		if ( ! empty( $post_ids ) ) {
			$relations = wp_get_object_terms(
				$post_ids,
				Taxonomies::CATEGORY,
				[ 'fields' => 'all_with_object_id' ]
			);
			if ( ! is_wp_error( $relations ) ) {
				foreach ( $relations as $relation ) {
					if ( ! $relation instanceof \WP_Term || ! isset( $relation->object_id ) ) {
						continue;
					}
					$post_id = (int) $relation->object_id;
					$term_id = (int) $relation->term_id;
					if ( $post_id > 0 && $term_id > 0 ) {
						$term_ids_by_post[ $post_id ][] = $term_id;
					}
				}
			}
		}

		$documents = [];
		foreach ( $posts as $post ) {
			$id = (int) $post->ID;
			$term_ids = array_values( array_unique( array_map( 'absint', $term_ids_by_post[ $id ] ?? [] ) ) );
			sort( $term_ids, SORT_NUMERIC );

			$structural_id = StructuralCategory::resolve( $term_ids, $parent_map );
			$state = 'resolved';
			if ( empty( $term_ids ) ) {
				$state = 'unassigned';
			} elseif ( null === $structural_id ) {
				$state = 'ambiguous';
			}

			$slug = (string) $post->post_name;
			if ( '' === $slug ) {
				$slug = sanitize_title( get_the_title( $post ) );
			}
			if ( '' === $slug ) {
				$slug = 'doc-' . $id;
			}

			$documents[] = [
				'id'                     => $id,
				'slug'                   => $slug,
				'structural_category_id' => $structural_id,
				'structure_state'        => $state,
				'public'                 => 'publish' === $post->post_status,
			];
		}

		self::$index = RouteIndex::build( $categories, $documents );
		self::$index['category_terms'] = $terms;
		self::$index['documents_projection'] = $documents;

		return self::$index;
	}

	public static function reset(): void {
		self::$index = null;
	}

	public static function reset_on_terms( int $object_id, mixed $terms, array $tt_ids, string $taxonomy ): void {
		if ( Taxonomies::CATEGORY === $taxonomy && PostType::TYPE === get_post_type( $object_id ) ) {
			self::reset();
		}
	}
}
