<?php
declare(strict_types=1);

$root = dirname( __DIR__ );

$readiness = file_get_contents( $root . '/src/Permalinks/Readiness.php' );
$settings  = file_get_contents( $root . '/src/Settings.php' );
$post_type = file_get_contents( $root . '/src/Content/PostType.php' );
$taxonomy  = file_get_contents( $root . '/src/Content/Taxonomies.php' );
$router    = file_get_contents( $root . '/src/Permalinks/Router.php' );
$page      = file_get_contents( $root . '/src/Admin/SettingsPage.php' );
$install   = file_get_contents( $root . '/src/Install.php' );

foreach ( compact( 'readiness', 'settings', 'post_type', 'taxonomy', 'router', 'page', 'install' ) as $name => $source ) {
	if ( false === $source ) {
		fwrite( STDERR, "Docs namespace guard regression failed: could not read {$name}.\n" );
		exit( 1 );
	}
}

$checks = [
	'readiness detects reserved roots and existing Pages' =>
		str_contains( (string) $readiness, "private const RESERVED_ROOTS" )
		&& str_contains( (string) $readiness, "get_page_by_path( $base, OBJECT, 'page' )" ),
	'full readiness detects evident public archive and taxonomy collisions' =>
		str_contains( (string) $readiness, "get_post_types( [], 'objects' )" )
		&& str_contains( (string) $readiness, "get_taxonomies( [], 'objects' )" )
		&& str_contains( (string) $readiness, 'PostType::TYPE === $post_type->name' )
		&& str_contains( (string) $readiness, 'Taxonomies::CATEGORY, Taxonomies::TAG' ),
	'save blocks a colliding base without silently renaming it' =>
		str_contains( (string) $settings, "! Readiness::namespace_ready( $after['rewrite_base'] )" )
		&& str_contains( (string) $settings, "'cb_docs_candidate' => $after['rewrite_base']" )
		&& ! str_contains( (string) $settings, "'docs-2'" ),
	'runtime registration preserves existing Page priority' =>
		str_contains( (string) $post_type, 'Readiness::runtime_ready( $base )' )
		&& str_contains( (string) $post_type, "'has_archive'         => $routes_ready ? $base : false" )
		&& str_contains( (string) $post_type, "] : false" ),
	'hierarchy namespace routing and canonical projections fail closed while paused' =>
		substr_count( (string) $router, 'Readiness::runtime_ready( Settings::rewrite_base() )' ) >= 6
		&& str_contains( (string) $taxonomy, '$hierarchy && ! $routes_ready ? false' ),
	'namespace state transitions trigger controlled rewrite reconciliation' =>
		str_contains( (string) $settings, 'NAMESPACE_STATE_OPTION' )
		&& str_contains( (string) $settings, 'maybe_reconcile_namespace' )
		&& str_contains( (string) $settings, "update_option( self::REWRITE_DIRTY_OPTION, '1', false )" )
		&& str_contains( (string) $install, 'Settings::record_namespace_state()' )
		&& str_contains( (string) $install, 'Settings::clear_runtime_state()' ),
	'admin communicates paused and blocked namespace states' =>
		str_contains( (string) $page, "add_action( 'admin_notices', [ __CLASS__, 'namespace_notice' ] )" )
		&& str_contains( (string) $page, 'Public Docs URLs are paused' )
		&& str_contains( (string) $page, 'Public namespace readiness' )
		&& str_contains( (string) $page, 'Conflicts detected' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, 'Docs namespace guard regression failed: ' . $label . "\n" );
		exit( 1 );
	}
}

echo "Docs namespace guard regression: PASS\n";
