<?php
declare(strict_types=1);

namespace CB\Docs\Structure;

use CB\Docs\Content\PostType;

defined( 'ABSPATH' ) || exit;

/**
 * Presentation-only document numbering.
 *
 * Numbers are derived from the canonical Organizer sibling order and are
 * never persisted in post titles or metadata.
 */
final class Numbering {
	private const STATUSES = [ 'publish', 'draft', 'pending', 'future', 'private' ];

	/** @var array<int,int> Document ID => one-based number. */
	private static array $numbers = [];

	/** @var array<int,bool> Structural category IDs already projected. */
	private static array $loaded_categories = [];

	public static function document( int $document_id ): ?int {
		if ( $document_id <= 0 || PostType::TYPE !== get_post_type( $document_id ) ) {
			return null;
		}

		if ( isset( self::$numbers[ $document_id ] ) ) {
			return self::$numbers[ $document_id ];
		}

		$category_id = DocumentLocation::structural_category_id( $document_id );
		if ( null === $category_id || $category_id <= 0 ) {
			return null;
		}

		self::load_category( $category_id, $document_id );
		return self::$numbers[ $document_id ] ?? null;
	}

	private static function load_category( int $category_id, int $document_id ): void {
		if ( isset( self::$loaded_categories[ $category_id ] ) ) {
			return;
		}

		$sibling_ids = DocumentLocation::sibling_document_ids( $document_id );
		if ( empty( $sibling_ids ) ) {
			self::$loaded_categories[ $category_id ] = true;
			return;
		}

		$posts = get_posts(
			[
				'post_type'           => PostType::TYPE,
				'post_status'         => self::STATUSES,
				'post__in'            => $sibling_ids,
				'posts_per_page'      => -1,
				'orderby'             => [
					'menu_order' => 'ASC',
					'title'      => 'ASC',
					'ID'         => 'ASC',
				],
				'suppress_filters'    => false,
				'ignore_sticky_posts' => true,
			]
		);

		$number = 0;
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post || PostType::TYPE !== $post->post_type ) {
				continue;
			}
			++$number;
			self::$numbers[ (int) $post->ID ] = $number;
		}

		self::$loaded_categories[ $category_id ] = true;
	}
}
