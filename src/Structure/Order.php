<?php
declare(strict_types=1);

namespace CB\Docs\Structure;

defined( 'ABSPATH' ) || exit;

final class Order {
	/**
	 * Normalize an ordered opaque identifier list.
	 *
	 * @param array<int,mixed> $ids
	 * @return string[]
	 */
	public static function normalize( array $ids ): array {
		$normalized = [];
		$seen = [];

		foreach ( $ids as $raw ) {
			if ( ! is_scalar( $raw ) || is_bool( $raw ) ) {
				throw new \InvalidArgumentException( 'Ordered identifiers must be scalar strings or numbers.' );
			}
			$id = trim( (string) $raw );
			if ( '' === $id || strlen( $id ) > 191 ) {
				throw new \InvalidArgumentException( 'Ordered identifiers must be non-empty and at most 191 bytes.' );
			}
			if ( isset( $seen[ $id ] ) ) {
				throw new \InvalidArgumentException( 'Ordered identifiers must be unique.' );
			}
			$seen[ $id ] = true;
			$normalized[] = $id;
		}

		return $normalized;
	}

	/**
	 * Move an existing identifier to its requested final index.
	 *
	 * @param array<int,mixed> $ids
	 * @return string[]
	 */
	public static function move( array $ids, mixed $item_id, int $target_index ): array {
		$ids = self::normalize( $ids );
		$item_id = self::identifier( $item_id );
		$from = array_search( $item_id, $ids, true );

		if ( false === $from ) {
			throw new \InvalidArgumentException( 'Ordered item was not found.' );
		}
		if ( $target_index < 0 || $target_index >= count( $ids ) ) {
			throw new \InvalidArgumentException( 'Target index is outside the ordered list.' );
		}
		if ( $from === $target_index ) {
			return $ids;
		}

		array_splice( $ids, (int) $from, 1 );
		array_splice( $ids, $target_index, 0, [ $item_id ] );
		return array_values( $ids );
	}

	/**
	 * Place an identifier at a final index, whether or not it is already present.
	 *
	 * @param array<int,mixed> $ids
	 * @return string[]
	 */
	public static function place( array $ids, mixed $item_id, int $target_index ): array {
		$ids = self::normalize( $ids );
		$item_id = self::identifier( $item_id );
		$existing = array_search( $item_id, $ids, true );
		if ( false !== $existing ) {
			array_splice( $ids, (int) $existing, 1 );
		}

		if ( $target_index < 0 || $target_index > count( $ids ) ) {
			throw new \InvalidArgumentException( 'Target index is outside the ordered list.' );
		}

		array_splice( $ids, $target_index, 0, [ $item_id ] );
		return array_values( $ids );
	}

	/**
	 * Canonical sparse storage positions.
	 *
	 * @param array<int,mixed> $ids
	 * @return array<string,int>
	 */
	public static function positions( array $ids ): array {
		$positions = [];
		foreach ( self::normalize( $ids ) as $index => $id ) {
			$positions[ $id ] = ( $index + 1 ) * 10;
		}
		return $positions;
	}

	private static function identifier( mixed $value ): string {
		if ( ! is_scalar( $value ) || is_bool( $value ) ) {
			throw new \InvalidArgumentException( 'Ordered item identifier is invalid.' );
		}
		$id = trim( (string) $value );
		if ( '' === $id || strlen( $id ) > 191 ) {
			throw new \InvalidArgumentException( 'Ordered item identifier is invalid.' );
		}
		return $id;
	}
}
