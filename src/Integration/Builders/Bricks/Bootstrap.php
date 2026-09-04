<?php
declare(strict_types=1);

namespace CB\Docs\Integration\Builders\Bricks;

defined( 'ABSPATH' ) || exit;

final class Bootstrap {
	private static bool $booted = false;

	public static function init(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		DynamicData::init();
		GroupOrder::init();
		Queries::init();
		Conditions::init();
	}
}
