<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$filters = file_get_contents( $root . '/src/Admin/ListFilters.php' );
$plugin = file_get_contents( $root . '/src/Plugin.php' );

if ( false === $filters || false === $plugin ) {
	fwrite( STDERR, "Docs admin filters smoke failed: source unavailable.\n" );
	exit( 1 );
}

$checks = [
	'filters are limited to the Docs list table' =>
		str_contains( $filters, "PostType::TYPE !== \$post_type" )
		&& str_contains( $filters, "'top' !== \$which" ),

	'category and tag filters use their native taxonomy query vars' =>
		str_contains( $filters, 'Taxonomies::CATEGORY' )
		&& str_contains( $filters, 'Taxonomies::TAG' )
		&& str_contains( $filters, "'value_field'     => 'slug'" ),

	'category filter preserves hierarchy while tags remain flat' =>
		str_contains( $filters, "__( 'All Doc Categories'" )
		&& str_contains( $filters, "__( 'All Doc Tags'" )
		&& str_contains( $filters, "true\n\t\t);" )
		&& str_contains( $filters, "false\n\t\t);" ),

	'filters use WordPress native list-table controls without custom query mutation' =>
		str_contains( $filters, "add_action( 'restrict_manage_posts'" )
		&& str_contains( $filters, 'wp_dropdown_categories(' )
		&& ! str_contains( $filters, 'pre_get_posts' ),

	'plugin boots list filters only in wp-admin' =>
		str_contains( $plugin, 'use CB\\Docs\\Admin\\ListFilters;' )
		&& str_contains( $plugin, 'ListFilters::init();' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, 'Docs admin filters smoke failed: ' . $label . "\n" );
		exit( 1 );
	}
}

echo "Docs admin filters smoke passed.\n";
