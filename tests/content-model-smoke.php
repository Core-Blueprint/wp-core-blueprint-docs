<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$post_type = file_get_contents( $root . '/src/Content/PostType.php' );
$taxonomies = file_get_contents( $root . '/src/Content/Taxonomies.php' );
$meta = file_get_contents( $root . '/src/Content/Meta.php' );
$settings = file_get_contents( $root . '/src/Settings.php' );
$queries = file_get_contents( $root . '/src/Frontend/Queries.php' );

foreach ( compact( 'post_type', 'taxonomies', 'meta', 'settings', 'queries' ) as $name => $source ) {
	if ( false === $source ) {
		fwrite( STDERR, "FAIL: could not read {$name}.\n" );
		exit( 1 );
	}
}

$checks = [
	'Docs CPT is public, archived and REST-enabled' => str_contains( (string) $post_type, "public const TYPE = 'cb_doc';" ) && str_contains( (string) $post_type, "'has_archive'         => true" ) && str_contains( (string) $post_type, "'show_in_rest'        => true" ),
	'Doc hierarchy is intentionally category-based rather than parent-child posts' => 1 === preg_match( "/'hierarchical'\\s*=>\\s*false/", (string) $post_type ) && str_contains( (string) $post_type, "'page-attributes'" ),
	'Doc Categories and Doc Tags use separate native taxonomies' => str_contains( (string) $taxonomies, "public const CATEGORY = 'cb_doc_category';" ) && str_contains( (string) $taxonomies, "public const TAG      = 'cb_doc_tag';" ) && str_contains( (string) $taxonomies, "'docs-category'" ) && str_contains( (string) $taxonomies, "'docs-tag'" ),
	'native Docs metadata is registered for REST use' => str_contains( (string) $meta, 'SUBTITLE' ) && str_contains( (string) $meta, 'LAST_REVIEWED' ) && str_contains( (string) $meta, 'FEATURED' ) && str_contains( (string) $meta, "'show_in_rest'" ),
	'URL base supports safe nested slug segments and deferred rewrite refresh' => str_contains( (string) $settings, "explode( '/', \$value )" ) && str_contains( (string) $settings, 'sanitize_title( $segment )' ) && str_contains( (string) $settings, 'REWRITE_DIRTY_OPTION' ) && str_contains( (string) $settings, 'flush_rewrite_rules( false )' ),
	'native Docs ordering uses menu order then title' => str_contains( (string) $queries, "'menu_order' => 'ASC'" ) && str_contains( (string) $queries, "'title' => 'ASC'" ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, 'Docs content-model smoke failed: ' . $label . "\n" );
		exit( 1 );
	}
}

echo "Docs content-model smoke passed.\n";
