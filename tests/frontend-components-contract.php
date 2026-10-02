<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$shortcodes  = file_get_contents( $root . '/src/Frontend/Shortcodes.php' );
$list        = file_get_contents( $root . '/src/Frontend/Components/DocumentList.php' );
$navigation  = file_get_contents( $root . '/src/Frontend/Components/Navigation.php' );
$search      = file_get_contents( $root . '/src/Frontend/Components/Search.php' );
$breadcrumbs = file_get_contents( $root . '/src/Frontend/Components/Breadcrumbs.php' );
$meta        = file_get_contents( $root . '/src/Frontend/Components/DocumentMeta.php' );

$checks = [
	'all placeable Docs surfaces have canonical components' =>
		is_string( $list ) && is_string( $navigation ) && is_string( $search ) && is_string( $breadcrumbs ) && is_string( $meta ),
	'shortcode layer contains no Docs query construction' =>
		is_string( $shortcodes )
		&& ! str_contains( $shortcodes, 'Queries::docs(' )
		&& ! str_contains( $shortcodes, 'get_terms(' )
		&& ! str_contains( $shortcodes, 'DocumentAccess::' ),
	'list canonicalizes bounds and taxonomy filters' =>
		is_string( $list )
		&& str_contains( $list, 'max( 1, min( 100' )
		&& str_contains( $list, 'Queries::taxonomy_filter(' )
		&& str_contains( $list, 'Queries::docs(' ),
	'navigation retains bounded traversal contracts' =>
		is_string( $navigation )
		&& str_contains( $navigation, 'private const MAX_TERMS = 250' )
		&& str_contains( $navigation, 'private const MAX_DOCS  = 500' )
		&& str_contains( $navigation, 'private const MAX_DEPTH = 20' ),
	'breadcrumbs retain structural category resolution' =>
		is_string( $breadcrumbs )
		&& str_contains( $breadcrumbs, 'StructuralCategory::resolve(' ),
	'metadata retains exact read authorization' =>
		is_string( $meta )
		&& str_contains( $meta, 'DocumentAccess::can_read( $post_id )' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, 'Docs canonical component contract failed: ' . $label . "\n" );
		exit( 1 );
	}
}

echo "Docs canonical component contract: PASS\n";
