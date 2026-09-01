<?php
declare(strict_types=1);

namespace CB\Docs;

use CB\Docs\Admin\SettingsPage;
use CB\Docs\Governance\Events;

defined( 'ABSPATH' ) || exit;

final class Settings {
	public const OPTION = 'cb_docs_settings';
	public const REWRITE_DIRTY_OPTION = 'cb_docs_rewrite_dirty';
	public const DEFAULT_REWRITE_BASE = 'docs';

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'maybe_flush_rewrite_rules' ], 20 );
		add_action( 'admin_post_cb_docs_save_settings', [ __CLASS__, 'save' ] );
	}

	/** @return array{rewrite_base:string} */
	public static function all(): array {
		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];

		return [
			'rewrite_base' => self::sanitize_rewrite_base( $stored['rewrite_base'] ?? self::DEFAULT_REWRITE_BASE ),
		];
	}

	public static function rewrite_base(): string {
		return self::all()['rewrite_base'];
	}

	public static function sanitize_rewrite_base( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			return self::DEFAULT_REWRITE_BASE;
		}

		$value = trim( (string) $value, "/ \t\n\r\0\x0B" );
		if ( '' === $value ) {
			return self::DEFAULT_REWRITE_BASE;
		}

		$segments = array_values( array_filter( array_map(
			static fn( string $segment ): string => sanitize_title( $segment ),
			explode( '/', $value )
		) ) );

		return empty( $segments ) ? self::DEFAULT_REWRITE_BASE : implode( '/', $segments );
	}

	public static function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change Docs settings.', 'core-blueprint-docs' ) );
		}

		check_admin_referer( 'cb_docs_save_settings', 'cb_docs_settings_nonce' );

		$before  = self::rewrite_base();
		$raw     = isset( $_POST['rewrite_base'] ) && is_string( $_POST['rewrite_base'] )
			? wp_unslash( $_POST['rewrite_base'] )
			: self::DEFAULT_REWRITE_BASE;
		$after   = self::sanitize_rewrite_base( $raw );
		$changed = $before !== $after;

		if ( $changed ) {
			update_option( self::OPTION, [ 'rewrite_base' => $after ], false );
			update_option( self::REWRITE_DIRTY_OPTION, '1', false );
			Events::record_settings_updated( 'rewrite_base', $before, $after );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'            => SettingsPage::SLUG,
					'cb_docs_updated' => $changed ? 'changed' : 'unchanged',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function maybe_flush_rewrite_rules(): void {
		if ( '1' !== (string) get_option( self::REWRITE_DIRTY_OPTION, '' ) ) {
			return;
		}

		flush_rewrite_rules( false );
		delete_option( self::REWRITE_DIRTY_OPTION );
	}
}
