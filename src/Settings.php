<?php
declare(strict_types=1);

namespace CB\Docs;

use CB\Core\Admin\SettingsRegistry;
use CB\Docs\Governance\Events;
use CB\Docs\Integration\Suite;
use CB\Docs\Permalinks\Readiness;

defined( 'ABSPATH' ) || exit;

final class Settings {
	public const OPTION = 'cb_docs_settings';
	public const REWRITE_DIRTY_OPTION = 'cb_docs_rewrite_dirty';
	public const DEFAULT_REWRITE_BASE = 'docs';
	public const URL_STRUCTURE_SIMPLE = 'simple';
	public const URL_STRUCTURE_HIERARCHY = 'category_hierarchy';
	public const DEFAULT_URL_STRUCTURE = self::URL_STRUCTURE_SIMPLE;

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'maybe_flush_rewrite_rules' ], 20 );
		add_action( 'admin_post_cb_docs_save_settings', [ __CLASS__, 'save' ] );
	}

	/** @return array{rewrite_base:string,url_structure:string} */
	public static function all(): array {
		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];

		return [
			'rewrite_base'  => self::sanitize_rewrite_base( $stored['rewrite_base'] ?? self::DEFAULT_REWRITE_BASE ),
			'url_structure' => self::sanitize_url_structure( $stored['url_structure'] ?? self::DEFAULT_URL_STRUCTURE ),
		];
	}

	public static function rewrite_base(): string {
		return self::all()['rewrite_base'];
	}

	public static function url_structure(): string {
		return self::all()['url_structure'];
	}

	public static function hierarchy_enabled(): bool {
		return self::URL_STRUCTURE_HIERARCHY === self::url_structure();
	}

	public static function sanitize_url_structure( mixed $value ): string {
		$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
		return in_array( $value, [ self::URL_STRUCTURE_SIMPLE, self::URL_STRUCTURE_HIERARCHY ], true )
			? $value
			: self::DEFAULT_URL_STRUCTURE;
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

		$before = self::all();
		$raw_base = isset( $_POST['rewrite_base'] ) && is_string( $_POST['rewrite_base'] )
			? wp_unslash( $_POST['rewrite_base'] )
			: self::DEFAULT_REWRITE_BASE;
		$raw_structure = isset( $_POST['url_structure'] ) && is_string( $_POST['url_structure'] )
			? wp_unslash( $_POST['url_structure'] )
			: self::DEFAULT_URL_STRUCTURE;

		$after = [
			'rewrite_base'  => self::sanitize_rewrite_base( $raw_base ),
			'url_structure' => self::sanitize_url_structure( $raw_structure ),
		];

		if ( self::URL_STRUCTURE_HIERARCHY === $after['url_structure'] && ! Readiness::ready( $after['rewrite_base'] ) ) {
			wp_safe_redirect(
				SettingsRegistry::url(
					Suite::ID,
					[
						'tab'             => 'general',
						'cb_docs_updated' => 'blocked',
					]
				)
			);
			exit;
		}

		$base_changed = $before['rewrite_base'] !== $after['rewrite_base'];
		$structure_changed = $before['url_structure'] !== $after['url_structure'];
		$changed = $base_changed || $structure_changed;

		if ( $changed ) {
			update_option( self::OPTION, $after, false );
			update_option( self::REWRITE_DIRTY_OPTION, '1', false );

			if ( $base_changed ) {
				Events::record_settings_updated( 'rewrite_base', $before['rewrite_base'], $after['rewrite_base'] );
			}
			if ( $structure_changed ) {
				Events::record_settings_updated( 'url_structure', $before['url_structure'], $after['url_structure'] );
			}
		}

		wp_safe_redirect(
			SettingsRegistry::url(
				Suite::ID,
				[
					'tab'             => 'general',
					'cb_docs_updated' => $changed ? 'changed' : 'unchanged',
				]
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
