<?php
declare(strict_types=1);

$root = dirname( __DIR__ );

$files = [
	'settings'   => 'src/Settings.php',
	'post_type'  => 'src/Content/PostType.php',
	'taxonomies' => 'src/Content/Taxonomies.php',
	'catalog'    => 'src/Permalinks/Catalog.php',
	'index'      => 'src/Permalinks/RouteIndex.php',
	'readiness'  => 'src/Permalinks/Readiness.php',
	'canonical'  => 'src/Permalinks/CanonicalPath.php',
	'router'     => 'src/Permalinks/Router.php',
	'guard'      => 'src/Permalinks/ReservedSlugGuard.php',
	'page'       => 'src/Admin/SettingsPage.php',
	'shortcodes' => 'src/Frontend/Shortcodes.php',
	'plugin'     => 'src/Plugin.php',
];

$sources = [];
foreach ( $files as $key => $relative ) {
	$source = file_get_contents( $root . '/' . $relative );
	if ( false === $source ) {
		fwrite( STDERR, "Docs permalink contract smoke failed: could not read {$relative}.\n" );
		exit( 1 );
	}
	$sources[ $key ] = $source;
}

$checks = [
	'Simple remains the backward-compatible default URL structure' =>
		str_contains( $sources['settings'], "URL_STRUCTURE_SIMPLE = 'simple'" )
		&& str_contains( $sources['settings'], 'DEFAULT_URL_STRUCTURE = self::URL_STRUCTURE_SIMPLE' ),

	'Hierarchy activation is readiness-gated before settings persistence' =>
		str_contains( $sources['settings'], 'URL_STRUCTURE_HIERARCHY' )
		&& str_contains( $sources['settings'], '! Readiness::ready()' )
		&& str_contains( $sources['settings'], "'cb_docs_updated' => 'blocked'" ),

	'Only base or URL structure changes mark rewrite rules dirty' =>
		str_contains( $sources['settings'], '$base_changed' )
		&& str_contains( $sources['settings'], '$structure_changed' )
		&& str_contains( $sources['settings'], 'REWRITE_DIRTY_OPTION' ),

	'Hierarchy singles use the reserved document namespace while the archive keeps the Docs base' =>
		str_contains( $sources['post_type'], "$base . '/document'" )
		&& str_contains( $sources['post_type'], "'has_archive'         => $base" ),

	'Hierarchy categories use the dynamic router and tags use the reserved tag namespace' =>
		str_contains( $sources['taxonomies'], "'rewrite'           => $hierarchy ? false" )
		&& str_contains( $sources['taxonomies'], "$base . '/tag'" ),

	'Route index reserves tag and document at the root' =>
		str_contains( $sources['index'], "RESERVED_ROOTS = [ 'tag', 'document' ]" )
		&& str_contains( $sources['index'], "'legacy_simple_collisions'" )
		&& str_contains( $sources['index'], "'invalid_category_paths'" ),

	'Catalog delegates structural truth to the Organizer resolver' =>
		str_contains( $sources['catalog'], 'StructuralCategory::resolve' )
		&& str_contains( $sources['catalog'], 'RouteIndex::build' ),

	'Canonical path service is the single public URL builder' =>
		str_contains( $sources['canonical'], 'Catalog::index()' )
		&& str_contains( $sources['canonical'], "'tag/'" )
		&& str_contains( $sources['canonical'], "Settings::rewrite_base()" ),

	'Router registers before the deferred rewrite flush' =>
		str_contains( $sources['router'], "add_action( 'init', [ __CLASS__, 'register_rules' ], 15 )" )
		&& str_contains( $sources['settings'], "add_action( 'init', [ __CLASS__, 'maybe_flush_rewrite_rules' ], 20 )" ),

	'Router protects reserved roots from the generic dynamic path rule' =>
		str_contains( $sources['router'], "(?!(?:tag|document)(?:/|$))" )
		&& str_contains( $sources['router'], "'=dynamic&'" ),

	'Router supports deterministic legacy category, tag and simple document redirects' =>
		str_contains( $sources['router'], 'legacy-category' )
		&& str_contains( $sources['router'], 'legacy-tag' )
		&& str_contains( $sources['router'], 'legacy-simple' )
		&& str_contains( $sources['router'], "wp_safe_redirect( $url, 301" ),

	'Canonical document and term links are filtered centrally' =>
		str_contains( $sources['router'], "add_filter( 'post_type_link'" )
		&& str_contains( $sources['router'], "add_filter( 'term_link'" )
		&& str_contains( $sources['router'], "add_filter( 'get_canonical_url'" ),

	'Reserved top-level categories fail closed after hierarchy activation' =>
		str_contains( $sources['guard'], "'pre_insert_term'" )
		&& str_contains( $sources['guard'], "'wp_update_term_data'" )
		&& str_contains( $sources['guard'], 'RouteIndex::RESERVED_ROOTS' ),

	'Settings UI exposes structure mode examples and readiness' =>
		str_contains( $sources['page'], 'Document URL structure' )
		&& str_contains( $sources['page'], 'Category hierarchy readiness' )
		&& str_contains( $sources['page'], 'Save permalinks' ),

	'Breadcrumbs consume the canonical structural category resolver' =>
		str_contains( $sources['shortcodes'], 'StructuralCategory::resolve' ),

	'Plugin boots Catalog, reserved slug protection and Router' =>
		str_contains( $sources['plugin'], 'Catalog::init()' )
		&& str_contains( $sources['plugin'], 'ReservedSlugGuard::init()' )
		&& str_contains( $sources['plugin'], 'Router::init()' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, 'Docs permalink contract smoke failed: ' . $label . "\n" );
		exit( 1 );
	}
}

foreach ( [ $sources['router'], $sources['canonical'], $sources['catalog'] ] as $source ) {
	if ( str_contains( $source, 'bricks/' ) || str_contains( $source, '\\Bricks\\' ) || str_contains( $source, 'BRICKS_VERSION' ) ) {
		fwrite( STDERR, "Docs permalink contract smoke failed: permalink domain must remain builder-agnostic.\n" );
		exit( 1 );
	}
}

echo "Docs permalink contract smoke passed.\n";
