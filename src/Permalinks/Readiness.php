<?php
declare(strict_types=1);

namespace CB\Docs\Permalinks;

defined( 'ABSPATH' ) || exit;

final class Readiness {
	/** @return array<string,int> */
	public static function analyze(): array {
		$index = Catalog::index();
		$readiness = isset( $index['readiness'] ) && is_array( $index['readiness'] )
			? $index['readiness']
			: [];

		return [
			'blocking'                     => (int) ( $readiness['blocking'] ?? 0 ),
			'warnings'                     => (int) ( $readiness['warnings'] ?? 0 ),
			'reserved_categories'          => (int) ( $readiness['reserved_categories'] ?? 0 ),
			'duplicate_category_paths'     => (int) ( $readiness['duplicate_category_paths'] ?? 0 ),
			'document_category_collisions' => (int) ( $readiness['document_category_collisions'] ?? 0 ),
			'duplicate_document_paths'     => (int) ( $readiness['duplicate_document_paths'] ?? 0 ),
			'legacy_simple_collisions'     => (int) ( $readiness['legacy_simple_collisions'] ?? 0 ),
			'unassigned'                   => (int) ( $readiness['unassigned'] ?? 0 ),
			'ambiguous'                    => (int) ( $readiness['ambiguous'] ?? 0 ),
		];
	}

	public static function ready(): bool {
		return 0 === self::analyze()['blocking'];
	}
}
