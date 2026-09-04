<?php
declare(strict_types=1);

namespace CB\Docs\Integration\Builders\Bricks;

use CB\Docs\Content\PostType;
use CB\Docs\Frontend\Data\Document;

defined( 'ABSPATH' ) || exit;

final class DocumentContext {
	/** @return array<string,mixed>|\WP_Error */
	public static function document(): array|\WP_Error {
		$loop_object = self::loop_object();
		if ( is_array( $loop_object ) && self::is_projection( $loop_object ) ) {
			return $loop_object;
		}

		if ( $loop_object instanceof \WP_Post && PostType::TYPE === $loop_object->post_type ) {
			return Document::get( (int) $loop_object->ID );
		}

		return Document::current();
	}

	public static function identifier(): ?int {
		$document = self::document();
		if ( is_wp_error( $document ) ) {
			return null;
		}

		$id = isset( $document['id'] ) ? absint( $document['id'] ) : 0;
		return $id > 0 ? $id : null;
	}

	public static function value( string $field ): mixed {
		$document = self::document();
		if ( is_wp_error( $document ) ) {
			return $document;
		}

		return array_key_exists( $field, $document ) ? $document[ $field ] : null;
	}

	private static function loop_object(): mixed {
		if ( ! class_exists( '\\Bricks\\Query' ) || ! method_exists( '\\Bricks\\Query', 'get_loop_object' ) ) {
			return null;
		}

		return \Bricks\Query::get_loop_object();
	}

	/** @param array<string,mixed> $value */
	private static function is_projection( array $value ): bool {
		return isset( $value['id'], $value['title'] )
			&& array_key_exists( 'permalink', $value )
			&& array_key_exists( 'documentation_status', $value );
	}
}
