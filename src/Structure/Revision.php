<?php
declare(strict_types=1);

namespace CB\Docs\Structure;

defined( 'ABSPATH' ) || exit;

final class Revision {
	/** @param array<string,mixed> $snapshot */
	public static function from_snapshot( array $snapshot ): string {
		$terms = [];
		foreach ( (array) ( $snapshot['terms'] ?? [] ) as $term ) {
			if ( ! is_array( $term ) ) {
				continue;
			}
			$terms[] = [
				'id'     => (int) ( $term['id'] ?? 0 ),
				'parent' => (int) ( $term['parent'] ?? 0 ),
				'order'  => (int) ( $term['order'] ?? 0 ),
			];
		}
		usort( $terms, static fn( array $a, array $b ): int => $a['id'] <=> $b['id'] );

		$documents = [];
		foreach ( (array) ( $snapshot['documents'] ?? [] ) as $document ) {
			if ( ! is_array( $document ) ) {
				continue;
			}
			$term_ids = array_values( array_unique( array_map( 'absint', (array) ( $document['term_ids'] ?? [] ) ) ) );
			sort( $term_ids, SORT_NUMERIC );
			$documents[] = [
				'id'         => (int) ( $document['id'] ?? 0 ),
				'menu_order' => (int) ( $document['menu_order'] ?? 0 ),
				'term_ids'   => $term_ids,
			];
		}
		usort( $documents, static fn( array $a, array $b ): int => $a['id'] <=> $b['id'] );

		$json = wp_json_encode(
			[ 'terms' => $terms, 'documents' => $documents ],
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		return hash( 'sha256', is_string( $json ) ? $json : '' );
	}

	public static function valid( string $revision ): bool {
		return 1 === preg_match( '/^[a-f0-9]{64}$/', $revision );
	}
}
