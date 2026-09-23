<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$entry = file_get_contents( $root . '/core-blueprint-docs.php' );
$requirements = file_get_contents( $root . '/src/Support/Requirements.php' );
$plugin = file_get_contents( $root . '/src/Plugin.php' );
$install = file_get_contents( $root . '/src/Install.php' );
$tools = file_get_contents( $root . '/tools/check' );

foreach ( compact( 'entry', 'requirements', 'plugin', 'install', 'tools' ) as $name => $source ) {
	if ( false === $source ) {
		fwrite( STDERR, "FAIL: could not read {$name}.\n" );
		exit( 1 );
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! defined( 'CB_DOCS_MIN_PHP' ) ) {
	define( 'CB_DOCS_MIN_PHP', '8.4' );
}
if ( ! defined( 'CB_DOCS_REQUIRED_API' ) ) {
	define( 'CB_DOCS_REQUIRED_API', '1.1' );
}
require_once $root . '/src/Support/Requirements.php';

$compatible = \CB\Docs\Support\Requirements::api_compatible( '1.1', '1.1' )
	&& \CB\Docs\Support\Requirements::api_compatible( '1.8', '1.2' )
	&& ! \CB\Docs\Support\Requirements::api_compatible( '2.0', '1.1' )
	&& ! \CB\Docs\Support\Requirements::api_compatible( '1.0', '1.1' )
	&& ! \CB\Docs\Support\Requirements::api_compatible( 'garbage', '1.1' );

$php_gate = strpos( (string) $entry, "version_compare( PHP_VERSION, CB_DOCS_MIN_PHP, '<' )" );
$autoload = strpos( (string) $entry, 'spl_autoload_register' );

$checks = [
	'native Base dependency header is present' => 1 === preg_match( '/^[ \t]*\*[ \t]*Requires Plugins:[ \t]*core-blueprint[ \t]*$/m', (string) $entry ),
	'API compatibility uses same-major sufficient-minor semantics' => $compatible,
	'PHP floor gate runs before the product autoloader' => false !== $php_gate && false !== $autoload && $php_gate < $autoload,
	'pre-v1 readiness alias is absent' => ! str_contains( (string) $entry, 'function cb_docs_base_ready' ),
	'Base product contracts are checked explicitly' => str_contains( (string) $entry, 'function cb_docs_base_contracts_ready(): bool' ) && str_contains( (string) $entry, "method_exists( '\\\\CB\\\\Core\\\\UI\\\\Assets', 'enqueue_reorder' )" ) && str_contains( (string) $entry, 'Governance' ) && str_contains( (string) $entry, 'Audit' ),
	'activation registers content before rewrite flush' => str_contains( (string) $install, 'PostType::register();' ) && str_contains( (string) $install, 'Taxonomies::register();' ) && str_contains( (string) $install, 'flush_rewrite_rules();' ),
	'plugin boot preserves builder-neutral ordering' => str_contains( (string) $plugin, 'BuildersBootstrap::init();' ) && str_contains( (string) $plugin, 'RestSearch::init();' ),
	'bootstrap smoke is wired into tools/check' => str_contains( (string) $tools, 'bootstrap-v1-smoke.php' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, 'Docs Bootstrap v1 smoke failed: ' . $label . "\n" );
		exit( 1 );
	}
}

echo "Docs Bootstrap v1 smoke passed.\n";
