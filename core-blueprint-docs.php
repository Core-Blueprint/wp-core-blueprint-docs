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
 *
 * @package CB_Docs
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'CB_DOCS_VERSION',      '1.0.0-rc1' );
define( 'CB_DOCS_REQUIRED_API', '1.0' );
define( 'CB_DOCS_FILE',         __FILE__ );
define( 'CB_DOCS_DIR',          plugin_dir_path( __FILE__ ) );
define( 'CB_DOCS_URL',          plugin_dir_url( __FILE__ ) );
define( 'CB_DOCS_BASENAME',     plugin_basename( __FILE__ ) );

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
	if ( 1 !== preg_match( '/^(\d+)\.(\d+)$/', $available, $available_match ) ) {
		return false;
	}
	if ( 1 !== preg_match( '/^(\d+)\.(\d+)$/', $required, $required_match ) ) {
		return false;
	}

	return (int) $available_match[1] === (int) $required_match[1]
		&& (int) $available_match[2] >= (int) $required_match[2];
}

function cb_docs_base_ready(): bool {
	if ( ! defined( 'CB_CORE_API_VERSION' ) ) {
		return false;
	}
	if ( ! cb_docs_api_compatible( (string) CB_CORE_API_VERSION, CB_DOCS_REQUIRED_API ) ) {
		return false;
	}

	return class_exists( '\CB\Core\ExtensionRegistry' )
		&& class_exists( '\CB\Core\Admin\PageRegistry' )
		&& interface_exists( '\CB\Core\Admin\Page' )
		&& class_exists( '\CB\Core\UI\Card' )
		&& class_exists( '\CB\Core\UI\Notice' )
		&& class_exists( '\CB\Core\UI\IntegrationGrid' )
		&& class_exists( '\CB\Core\Governance\EventRegistry' )
		&& class_exists( '\CB\Core\Governance\Audit' );
}

function cb_docs_dependency_message(): string {
	if ( ! defined( 'CB_CORE_API_VERSION' ) ) {
		return __( 'Core Blueprint Docs requires an active Core Blueprint Base plugin.', 'core-blueprint-docs' );
	}

	if ( ! cb_docs_api_compatible( (string) CB_CORE_API_VERSION, CB_DOCS_REQUIRED_API ) ) {
		return sprintf(
			/* translators: 1: required Core API version, 2: available Core API version. */
			__( 'Core Blueprint Docs requires Core API %1$s or a newer compatible minor version. This site provides %2$s.', 'core-blueprint-docs' ),
			CB_DOCS_REQUIRED_API,
			(string) CB_CORE_API_VERSION
		);
	}

	return __( 'Core Blueprint Docs cannot access the required public Base contracts.', 'core-blueprint-docs' );
}

function cb_docs_activate(): void {
	if ( ! cb_docs_base_ready() ) {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( CB_DOCS_BASENAME );
		wp_die(
			esc_html__( 'Core Blueprint Docs requires an active, Core API 1.x compatible Core Blueprint Base installation.', 'core-blueprint-docs' ),
			esc_html__( 'Core Blueprint dependency required', 'core-blueprint-docs' ),
			[ 'back_link' => true ]
		);
	}

	\CB\Docs\Install::activate();
}
register_activation_hook( __FILE__, 'cb_docs_activate' );
register_deactivation_hook( __FILE__, [ '\CB\Docs\Install', 'deactivate' ] );

add_action( 'plugins_loaded', static function (): void {
	if ( ! cb_docs_base_ready() ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', static function (): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}

				printf(
					'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
					esc_html__( 'Core Blueprint Docs:', 'core-blueprint-docs' ),
					esc_html( cb_docs_dependency_message() )
				);
			} );
		}
		return;
	}

	\CB\Docs\Plugin::boot();
}, 30 );
