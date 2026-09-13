<?php
/**
 * Plugin Name:       Core Blueprint Docs
 * Plugin URI:        https://coreblueprint.io
 * Description:       Lightweight builder-agnostic documentation with native WordPress content, taxonomies and metadata.
 * Version:           1.0.0-rc1
 * Author:            Core Blueprint
 * Author URI:        https://coreblueprint.io
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       core-blueprint-docs
 * Domain Path:       /languages
 * Requires at least: 7.0
 * Requires PHP:      8.4
 * Requires Plugins: core-blueprint
 *
 * @package CB_Docs
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( defined( 'CB_DOCS_FILE' ) ) {
	return;
}

define( 'CB_DOCS_NAME',         'Core Blueprint Docs' );
define( 'CB_DOCS_VERSION',      '1.0.0-rc1' );
define( 'CB_DOCS_MIN_PHP',      '8.4' );
define( 'CB_DOCS_REQUIRED_API', '1.0' );
define( 'CB_DOCS_FILE',         __FILE__ );
define( 'CB_DOCS_DIR',          plugin_dir_path( __FILE__ ) );
define( 'CB_DOCS_URL',          plugin_dir_url( __FILE__ ) );
define( 'CB_DOCS_BASENAME',     plugin_basename( __FILE__ ) );

/* Bootstrap v1 earliest-safe PHP boundary. */
if ( version_compare( PHP_VERSION, CB_DOCS_MIN_PHP, '<' ) ) {
	register_activation_hook( __FILE__, static function () {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		deactivate_plugins( CB_DOCS_BASENAME );
		wp_die(
			esc_html( sprintf( 'PHP %1$s or newer is required. This server runs PHP %2$s.', CB_DOCS_MIN_PHP, PHP_VERSION ) ),
			esc_html( 'Core Blueprint requirements not met' ),
			[
				'link_url'  => admin_url( 'plugins.php' ),
				'link_text' => __( 'Plugins' ),
			]
		);
	} );

	add_action( 'admin_notices', static function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
			esc_html( CB_DOCS_NAME . ':' ),
			esc_html( sprintf( 'PHP %1$s or newer is required. This server runs PHP %2$s.', CB_DOCS_MIN_PHP, PHP_VERSION ) )
		);
	} );
	return;
}

spl_autoload_register( static function ( string $class ): void {
	$prefix = 'CB\\Docs\\';
	$length = strlen( $prefix );

	if ( 0 !== strncmp( $class, $prefix, $length ) ) {
		return;
	}

	$relative = substr( $class, $length );
	$file     = CB_DOCS_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

	if ( is_file( $file ) ) {
		require_once $file;
	}
} );

add_action( 'init', static function (): void {
	load_plugin_textdomain(
		'core-blueprint-docs',
		false,
		dirname( CB_DOCS_BASENAME ) . '/languages'
	);
}, 1 );

function cb_docs_api_compatible( string $available, string $required ): bool {
	return \CB\Docs\Support\Requirements::api_compatible( $available, $required );
}

/** Product-specific public Base services consumed by Docs. */
function cb_docs_base_contracts_ready(): bool {
	return class_exists( '\\CB\\Core\\ExtensionRegistry' )
		&& class_exists( '\\CB\\Core\\Admin\\SettingsRegistry' )
		&& class_exists( '\\CB\\Core\\UI\\Card' )
		&& class_exists( '\\CB\\Core\\UI\\Notice' )
		&& class_exists( '\\CB\\Core\\UI\\IntegrationGrid' )
		&& class_exists( '\\CB\\Core\\Governance\\EventRegistry' )
		&& class_exists( '\\CB\\Core\\Governance\\Audit' );
}

/** Backward-compatible product readiness helper. */
function cb_docs_base_ready(): bool {
	return \CB\Docs\Support\Requirements::runtime_ready() && cb_docs_base_contracts_ready();
}

function cb_docs_dependency_message(): string {
	if ( ! \CB\Docs\Support\Requirements::runtime_ready() ) {
		return \CB\Docs\Support\Requirements::operator_message();
	}
	return __( 'Required Core Blueprint Base contracts are unavailable.', 'core-blueprint-docs' );
}

function cb_docs_fail_activation( string $message ): void {
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	deactivate_plugins( CB_DOCS_BASENAME );
	wp_die(
		esc_html( $message ),
		esc_html( 'Core Blueprint requirements not met' ),
		[
			'link_url'  => admin_url( 'plugins.php' ),
			'link_text' => __( 'Plugins' ),
		]
	);
}

function cb_docs_activate(): void {
	if ( ! \CB\Docs\Support\Requirements::runtime_ready() ) {
		cb_docs_fail_activation( \CB\Docs\Support\Requirements::activation_message() );
	}
	if ( ! cb_docs_base_contracts_ready() ) {
		cb_docs_fail_activation( 'Required Core Blueprint Base contracts are unavailable.' );
	}

	\CB\Docs\Install::activate();
}
register_activation_hook( __FILE__, 'cb_docs_activate' );
register_deactivation_hook( __FILE__, [ '\\CB\\Docs\\Install', 'deactivate' ] );

/* Docs retains its existing plugins_loaded:30 product boot timing. */
add_action( 'plugins_loaded', static function (): void {
	if ( ! \CB\Docs\Support\Requirements::runtime_ready() ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', static function (): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				printf(
					'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
					esc_html__( 'Core Blueprint Docs:', 'core-blueprint-docs' ),
					esc_html( \CB\Docs\Support\Requirements::operator_message() )
				);
			} );
		}
		return;
	}

	if ( ! cb_docs_base_contracts_ready() ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', static function (): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				printf(
					'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
					esc_html__( 'Core Blueprint Docs:', 'core-blueprint-docs' ),
					esc_html__( 'Required Core Blueprint Base contracts are unavailable.', 'core-blueprint-docs' )
				);
			} );
		}
		return;
	}

	\CB\Docs\Plugin::boot();
}, 30 );
