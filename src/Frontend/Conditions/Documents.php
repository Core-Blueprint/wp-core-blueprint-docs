<?php
declare(strict_types=1);

namespace CB\Docs\Frontend\Conditions;

use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;
use CB\Docs\Frontend\DocumentAccess;

defined( 'ABSPATH' ) || exit;

final class Documents {
	public static function is_current(): bool {
		$document_id = self::current_id();
		return $document_id > 0 && PostType::TYPE === get_post_type( $document_id );
	}

	public static function in_category( int|string $category, ?int $document_id = null ): bool {
		return self::has_term( $category, Taxonomies::CATEGORY, $document_id );
	}

	public static function has_tag( int|string $tag, ?int $document_id = null ): bool {
		return self::has_term( $tag, Taxonomies::TAG, $document_id );
	}

	public static function user_can_read( ?int $document_id = null ): bool {
		$document_id ??= self::current_id();
		return $document_id > 0 && DocumentAccess::can_read( $document_id );
	}

	private static function has_term( int|string $term, string $taxonomy, ?int $document_id ): bool {
		$document_id ??= self::current_id();
		if ( $document_id <= 0 || ! DocumentAccess::can_read( $document_id ) ) {
			return false;
		}

		if ( is_int( $term ) || ctype_digit( (string) $term ) ) {
			$term = absint( $term );
		} else {
			$term = sanitize_title( (string) $term );
		}
		if ( '' === (string) $term || 0 === $term ) {
			return false;
		}

		return has_term( $term, $taxonomy, $document_id );
	}

	private static function current_id(): int {
		$post_id = get_queried_object_id();
		if ( $post_id > 0 ) {
			return $post_id;
		}
		$post = get_post();
		return $post instanceof \WP_Post ? (int) $post->ID : 0;
	}
}
