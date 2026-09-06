<?php
declare(strict_types=1);

namespace CB\Docs\Admin;

use CB\Core\UI\IntegrationGrid;

defined( 'ABSPATH' ) || exit;

/**
 * Docs-owned integration discovery and readiness descriptors.
 *
 * Base owns IntegrationGrid presentation. Docs owns only integration meaning,
 * readiness detection and customer-facing copy.
 */
final class IntegrationReadiness {
	/** @return array<int,array<string,mixed>> */
	public static function items(): array {
		return [ self::bricks_item() ];
	}

	/** @return array<string,mixed> */
	private static function bricks_item(): array {
		$active = defined( 'BRICKS_VERSION' ) || class_exists( '\\Bricks\\Query' );

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
