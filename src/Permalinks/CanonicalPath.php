<?php
declare(strict_types=1);

namespace CB\Docs\Permalinks;

use CB\Docs\Content\PostType;
use CB\Docs\Settings;

defined( 'ABSPATH' ) || exit;

final class CanonicalPath {
	public static function archive_url(): string {
		return trailingslashit( home_url( '/' . Settings::rewrite_base() ) );
	}

	public static function category_url( int $term_id ): ?string {
		$index = Catalog::index();
		$path = isset( $index['categories'][ $term_id ] ) ? (string) $index['categories'][ $term_id ] : '';
		return '' === $path ? null : self::url( $path );
	}

	public static function tag_url( string $slug ): string {
		return self::url( 'tag/' . sanitize_title( $slug ) );
	}

	public static function document_url( int $post_id ): ?string {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || PostType::TYPE !== $post->post_type ) {
			return null;
		}

		$index = Catalog::index();
		$route = $index['documents'][ $post_id ] ?? null;
		if ( ! is_array( $route ) || empty( $route['path'] ) ) {
			return null;
		}

		return self::url( (string) $route['path'] );
	}

	private static function url( string $relative_path ): string {
		$path = trim( Settings::rewrite_base(), '/' ) . '/' . trim( $relative_path, '/' );
		return trailingslashit( home_url( '/' . $path ) );
	}
}
