<?php
declare(strict_types=1);

namespace CB\Docs\Frontend;

use CB\Docs\Content\PostType;

defined( 'ABSPATH' ) || exit;

/**
 * Builder-neutral document read authorization boundary.
 *
 * Exact reads are resolved through a filtered WordPress query rather than a
 * raw get_post() permission guess. This keeps normal WordPress query policy and
 * compatible access-control filters in the decision path while remaining
 * standalone when no access extension is active.
 */
final class DocumentAccess {
	public static function can_read( int|\WP_Post $document ): bool {
		$post = self::resolve( $document );
		if ( ! $post || PostType::TYPE !== $post->post_type ) {
			return false;
		}

		$post_id = (int) $post->ID;
		if ( $post_id <= 0 || ! self::protected_content_allowed( $post ) ) {
			return false;
		}

		$query = new \WP_Query( [
			'post_type'           => PostType::TYPE,
			'post_status'         => current_user_can( 'edit_post', $post_id ) ? 'any' : 'publish',
			'p'                   => $post_id,
			'posts_per_page'      => 1,
			'fields'              => 'ids',
			'perm'                => 'readable',
			'suppress_filters'    => false,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		] );

		return in_array( $post_id, array_map( 'intval', (array) $query->posts ), true );
	}

	public static function protected_content_allowed( \WP_Post $post ): bool {
		if ( ! post_password_required( $post ) ) {
			return true;
		}

		return current_user_can( 'edit_post', (int) $post->ID );
	}

	public static function resolve( int|\WP_Post $document ): ?\WP_Post {
		$post = $document instanceof \WP_Post ? $document : get_post( $document );
		return $post instanceof \WP_Post ? $post : null;
	}
}
