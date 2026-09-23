<?php
declare(strict_types=1);

namespace CB\Docs\Structure;

defined( 'ABSPATH' ) || exit;

final class MutationLock {
	public const OPTION = 'cb_docs_structure_lock';
	private const STALE_SECONDS = 30;

	/** @return string|\WP_Error Owner token on success. */
	public static function acquire(): string|\WP_Error {
		$owner = wp_generate_password( 32, false, false );
		$payload = [
			'owner'       => $owner,
			'acquired_at' => time(),
		];

		if ( add_option( self::OPTION, $payload, '', false ) ) {
			return $owner;
		}

		global $wpdb;
		$raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				self::OPTION
			)
		);

		if ( ! is_string( $raw ) || '' === $raw ) {
			return new \WP_Error(
				'cb_docs_structure_locked',
				__( 'The documentation structure could not be saved.', 'core-blueprint-docs' ),
				[ 'status' => 423 ]
			);
		}

		$current = maybe_unserialize( $raw );
		$acquired_at = is_array( $current ) ? (int) ( $current['acquired_at'] ?? 0 ) : 0;
		if ( $acquired_at > 0 && ( time() - $acquired_at ) <= self::STALE_SECONDS ) {
			return new \WP_Error(
				'cb_docs_structure_locked',
				__( 'The documentation structure could not be saved.', 'core-blueprint-docs' ),
				[ 'status' => 423 ]
			);
		}

		$new_raw = maybe_serialize( $payload );
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				$new_raw,
				self::OPTION,
				$raw
			)
		);

		if ( 1 !== $updated ) {
			return new \WP_Error(
				'cb_docs_structure_locked',
				__( 'The documentation structure changed. Reload the Organizer before continuing.', 'core-blueprint-docs' ),
				[ 'status' => 409 ]
			);
		}

		wp_cache_delete( self::OPTION, 'options' );
		return $owner;
	}

	public static function release( string $owner ): void {
		if ( '' === $owner ) {
			return;
		}

		global $wpdb;
		$raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				self::OPTION
			)
		);
		if ( ! is_string( $raw ) || '' === $raw ) {
			return;
		}

		$current = maybe_unserialize( $raw );
		if ( ! is_array( $current ) || ! hash_equals( (string) ( $current['owner'] ?? '' ), $owner ) ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				self::OPTION,
				$raw
			)
		);
		wp_cache_delete( self::OPTION, 'options' );
	}
}
