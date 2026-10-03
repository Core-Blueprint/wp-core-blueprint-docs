<?php
declare(strict_types=1);

namespace CB\Docs\Admin;

use CoreBlueprint\Core\UI\IntegrationGrid;
use CB\Docs\Integration\Builders\Readiness as BuilderReadiness;
use CB\Docs\Integration\Preferences;

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
		return [
			self::gutenberg_item(),
			self::bricks_item(),
		];
	}

	/** @return array<string,mixed> */
	private static function gutenberg_item(): array {
		$enabled = Preferences::gutenberg_enabled();

		return [
			'name'         => __( 'Gutenberg blocks', 'core-blueprint-docs' ),
			'description'  => $enabled
				? __( 'Docs blocks are enabled for the native WordPress block editor. Blocks remain thin consumers of the builder-neutral Docs frontend contracts.', 'core-blueprint-docs' )
				: __( 'Docs blocks are disabled. Native Docs content, shortcodes and public frontend contracts remain available.', 'core-blueprint-docs' ),
			'status'       => $enabled ? IntegrationGrid::READY : IntegrationGrid::OPTIONAL,
			'status_label' => $enabled
				? __( 'Enabled', 'core-blueprint-docs' )
				: __( 'Disabled', 'core-blueprint-docs' ),
		];
	}

	/** @return array<string,mixed> */
	private static function bricks_item(): array {
		$active = BuilderReadiness::bricks_active();
		$mode   = Preferences::bricks_mode();

		if ( Preferences::BRICKS_DISABLED === $mode ) {
			return [
				'name'         => __( 'Bricks Builder', 'core-blueprint-docs' ),
				'description'  => __( 'The Docs Bricks adapter is disabled by preference. Docs continues to work through native WordPress content, shortcodes and builder-neutral frontend contracts.', 'core-blueprint-docs' ),
				'status'       => IntegrationGrid::OPTIONAL,
				'status_label' => __( 'Disabled', 'core-blueprint-docs' ),
			];
		}

		return [
			'name'         => __( 'Bricks Builder', 'core-blueprint-docs' ),
			'description'  => $active
				? __( 'Bricks is active. Docs dynamic data, queries and conditions are available through the optional adapter while Docs data and access logic remain builder-neutral.', 'core-blueprint-docs' )
				: __( 'Bricks is optional. Docs works without a builder through native WordPress content, shortcodes and builder-neutral frontend contracts.', 'core-blueprint-docs' ),
			'status'       => $active ? IntegrationGrid::READY : IntegrationGrid::OPTIONAL,
			'status_label' => $active
				? __( 'Ready', 'core-blueprint-docs' )
				: ( Preferences::BRICKS_ENABLED === $mode
					? __( 'Enabled', 'core-blueprint-docs' )
					: __( 'Not active', 'core-blueprint-docs' ) ),
		];
	}
}
