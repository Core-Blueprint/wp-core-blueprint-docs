<?php
declare(strict_types=1);

namespace CB\Docs\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical customer preferences for optional Docs consumers/adapters.
 *
 * Preferences decide whether an adapter may bootstrap. They never own
 * builder detection, rendering, storage, query policy or authorization.
 */
final class Preferences {
	public const OPTION = 'cb_docs_integration_preferences';

	public const BRICKS_AUTO     = 'auto';
	public const BRICKS_ENABLED  = 'enabled';
	public const BRICKS_DISABLED = 'disabled';

	/** @return array{gutenberg:bool,bricks:string} */
	public static function all(): array {
		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];

		return [
			'gutenberg' => self::sanitize_gutenberg( $stored['gutenberg'] ?? true ),
			'bricks'    => self::sanitize_bricks( $stored['bricks'] ?? self::BRICKS_AUTO ),
		];
	}

	public static function gutenberg_enabled(): bool {
		return self::all()['gutenberg'];
	}

	public static function bricks_mode(): string {
		return self::all()['bricks'];
	}

	public static function bricks_enabled(): bool {
		return self::BRICKS_DISABLED !== self::bricks_mode();
	}

	public static function sanitize_gutenberg( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_scalar( $value ) ) {
			$parsed = filter_var( (string) $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			if ( null !== $parsed ) {
				return $parsed;
			}
		}

		return true;
	}

	public static function sanitize_bricks( mixed $value ): string {
		$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

		return in_array( $value, [ self::BRICKS_AUTO, self::BRICKS_ENABLED, self::BRICKS_DISABLED ], true )
			? $value
			: self::BRICKS_AUTO;
	}

	/** @param array{gutenberg?:mixed,bricks?:mixed} $values */
	public static function update( array $values ): bool {
		$before = self::all();
		$after  = [
			'gutenberg' => self::sanitize_gutenberg( $values['gutenberg'] ?? true ),
			'bricks'    => self::sanitize_bricks( $values['bricks'] ?? self::BRICKS_AUTO ),
		];

		if ( $before === $after ) {
			return false;
		}

		return update_option( self::OPTION, $after, false );
	}
}
