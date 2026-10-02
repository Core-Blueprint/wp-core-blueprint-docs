<?php
declare(strict_types=1);

namespace CB\Docs\Integration\Builders;

use CB\Docs\Integration\Preferences;

defined( 'ABSPATH' ) || exit;

final class Bootstrap {
	private static bool $booted = false;

	public static function init(): void {
		if ( ! Preferences::bricks_enabled() ) {
			return;
		}

		add_action( 'init', [ \CB\Docs\Integration\Builders\Bricks\ElementRegistry::class, 'register' ], 11 );
		add_action( 'init', [ self::class, 'boot' ], 50 );
	}

	public static function boot(): void {
		if ( self::$booted || ! Preferences::bricks_enabled() ) {
			return;
		}

		if ( ! defined( 'BRICKS_VERSION' ) && ! class_exists( '\\Bricks\\Query' ) ) {
			return;
		}

		self::$booted = true;
		\CB\Docs\Integration\Builders\Bricks\Bootstrap::init();
	}
}
