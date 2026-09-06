<?php
declare(strict_types=1);

namespace CB\Docs\Admin;

use CB\Core\UI\IntegrationGrid;
use CB\Docs\Integration\Builders\Readiness as BuilderReadiness;

defined( 'ABSPATH' ) || exit;

/**
 * Docs-owned integration discovery and readiness descriptors.
 *
 * Base owns IntegrationGrid presentation. Docs owns only integration meaning,
 * readiness semantics and customer-facing copy. Builder detection stays inside
 * the builder integration boundary.
 */
final class IntegrationReadiness {
	/** @return array<int,array<string,mixed>> */
	public static function items(): array {
		return [ self::bricks_item() ];
	}

	/** @return array<string,mixed> */
	private static function bricks_item(): array {
		$active = BuilderReadiness::bricks_active();

		return [
			'name'         => __( 'Bricks Builder', 'core-blueprint-docs' ),
			'description'  => $active
				? __( 'Bricks is active. Docs dynamic data, queries and conditions are available through the optional adapter while Docs data and access logic remain builder-neutral.', 'core-blueprint-docs' )
				: __( 'Bricks is optional. Docs works without a builder through native WordPress content, shortcodes and builder-neutral frontend contracts.', 'core-blueprint-docs' ),
			'status'       => $active ? IntegrationGrid::READY : IntegrationGrid::OPTIONAL,
			'status_label' => $active
				? __( 'Ready', 'core-blueprint-docs' )
				: __( 'Not active', 'core-blueprint-docs' ),
		];
	}
}
