<?php
declare(strict_types=1);

namespace CB\Docs\Integration\Builders;

defined( 'ABSPATH' ) || exit;

/** Builder availability boundary for non-adapter consumers such as admin UX. */
final class Readiness {
	public static function bricks_active(): bool {
		return defined( 'BRICKS_VERSION' ) || class_exists( '\\Bricks\\Query' );
	}
}
