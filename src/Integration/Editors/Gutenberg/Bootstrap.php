<?php
declare(strict_types=1);

namespace CB\Docs\Integration\Editors\Gutenberg;

use CB\Docs\Integration\Preferences;

defined( 'ABSPATH' ) || exit;

final class Bootstrap {
	private static bool $initialized = false;

	public static function init(): void {
		if ( self::$initialized || ! Preferences::gutenberg_enabled() ) {
			return;
		}

		self::$initialized = true;
		add_filter( 'block_categories_all', [ self::class, 'categories' ] );
		add_action( 'init', [ Registry::class, 'register' ], 12 );
	}

	/**
	 * @param array<int,array<string,mixed>> $categories
	 * @return array<int,array<string,mixed>>
	 */
	public static function categories( array $categories ): array {
		foreach ( $categories as $category ) {
			if ( 'core-blueprint' === (string) ( $category['slug'] ?? '' ) ) {
				return $categories;
			}
		}

		$categories[] = [
			'slug'  => 'core-blueprint',
			'title' => __( 'Core Blueprint', 'core-blueprint-docs' ),
		];

		return $categories;
	}
}
