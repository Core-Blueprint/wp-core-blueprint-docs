<?php
declare(strict_types=1);

namespace CB\Docs\Integration\Builders\Bricks;

defined( 'ABSPATH' ) || exit;

/** Registers Docs-specific Bricks elements without making Bricks a dependency. */
final class ElementRegistry {
	/** @var array<string,array{name:string,class:class-string}> */
	private const ELEMENTS = [
		'Elements/Search.php' => [
			'name'  => 'cb-docs-search',
			'class' => Elements\Search::class,
		],
	];

	public static function register(): void {
		if ( ! class_exists( '\\Bricks\\Elements' ) || ! class_exists( '\\Bricks\\Element' ) ) {
			return;
		}

		foreach ( self::ELEMENTS as $relative_file => $definition ) {
			$file = __DIR__ . '/' . $relative_file;
			if ( is_readable( $file ) ) {
				\Bricks\Elements::register_element( $file, $definition['name'], $definition['class'] );
			}
		}
	}
}
