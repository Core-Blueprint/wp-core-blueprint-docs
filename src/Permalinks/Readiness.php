<?php
declare(strict_types=1);

namespace CB\Docs\Permalinks;

use CB\Docs\Settings;

defined( 'ABSPATH' ) || exit;

final class Readiness {
	private const RESERVED_BASES = [ 'docs-category', 'docs-tag' ];
	/** @return array<string,int> */
	public static function analyze( ?string $base = null ): array {
		$index = Catalog::index();
		$readiness = isset( $index['readiness'] ) && is_array( $index['readiness'] )
			? $index['readiness']
			: [];

		$base = trim( $base ?? Settings::rewrite_base(), '/' );
		$reserved_base = in_array( $base, self::RESERVED_BASES, true ) ? 1 : 0;

		return [
			'blocking'                     => (int) ( $readiness['blocking'] ?? 0 ) + $reserved_base,
			'reserved_base'                => $reserved_base,
			'warnings'                     => (int) ( $readiness['warnings'] ?? 0 ),
			'reserved_categories'          => (int) ( $readiness['reserved_categories'] ?? 0 ),
			'duplicate_category_paths'     => (int) ( $readiness['duplicate_category_paths'] ?? 0 ),
			'invalid_category_paths'       => (int) ( $readiness['invalid_category_paths'] ?? 0 ),
			'document_category_collisions' => (int) ( $readiness['document_category_collisions'] ?? 0 ),
			'duplicate_document_paths'     => (int) ( $readiness['duplicate_document_paths'] ?? 0 ),
			'legacy_simple_collisions'     => (int) ( $readiness['legacy_simple_collisions'] ?? 0 ),
			'unassigned'                   => (int) ( $readiness['unassigned'] ?? 0 ),
			'ambiguous'                    => (int) ( $readiness['ambiguous'] ?? 0 ),
		];
	}

	public static function ready( ?string $base = null ): bool {
		return 0 === self::analyze( $base )['blocking'];
	}
}
