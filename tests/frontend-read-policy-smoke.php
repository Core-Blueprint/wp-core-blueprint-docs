<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$access = file_get_contents( $root . '/src/Frontend/DocumentAccess.php' );
$public_query = file_get_contents( $root . '/src/Frontend/Queries/Documents.php' );
$native_query = file_get_contents( $root . '/src/Frontend/Queries.php' );
$shortcodes = file_get_contents( $root . '/src/Frontend/Shortcodes.php' );
$search = file_get_contents( $root . '/src/Frontend/Search.php' );
$rest = file_get_contents( $root . '/src/Frontend/RestSearch.php' );

foreach ( compact( 'access', 'public_query', 'native_query', 'shortcodes', 'search', 'rest' ) as $name => $source ) {
	if ( false === $source ) {
		fwrite( STDERR, "FAIL: could not read {$name}.\n" );
		exit( 1 );
	}
}

$checks = [
	'exact reads are filtered and permission-aware' => str_contains( (string) $access, "'perm'" ) && str_contains( (string) $access, "'readable'" ) && str_contains( (string) $access, "'suppress_filters'" ) && str_contains( (string) $access, 'protected_content_allowed' ),
	'public collection query uses readable permission and WordPress filters' => str_contains( (string) $public_query, "'perm'" ) && str_contains( (string) $public_query, "'readable'" ) && str_contains( (string) $public_query, "'suppress_filters'" ) && str_contains( (string) $public_query, 'Document::project( $post )' ),
	'native shortcode query keeps the same readable/filter boundary' => str_contains( (string) $native_query, "'perm'" ) && str_contains( (string) $native_query, "'readable'" ) && str_contains( (string) $native_query, "'suppress_filters'" ),
	'list and navigation suppress protected document presentation' => substr_count( (string) $shortcodes, 'DocumentAccess::protected_content_allowed( $doc )' ) >= 3,
	'list returns the normal empty state when every matching document is protected' => str_contains( (string) $shortcodes, "return '' !== $items" ) && str_contains( (string) $shortcodes, "self::state( 'empty', __( 'No documentation found.'" ),
	'metadata shortcode authorizes exact reads' => str_contains( (string) $shortcodes, '! DocumentAccess::can_read( $post_id )' ),
	'empty search never becomes an unfiltered catalogue query' => str_contains( (string) $search, "if ( '' === $search )" ) && str_contains( (string) $search, "'items'       => []" ),
	'public REST search delegates to canonical search and disables shared caching' => str_contains( (string) $rest, 'Search::documents(' ) && str_contains( (string) $rest, 'no-store, no-cache, must-revalidate, max-age=0' ) && str_contains( (string) $rest, "'Vary', 'Cookie'" ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, 'Docs frontend read-policy smoke failed: ' . $label . "\n" );
		exit( 1 );
	}
}

echo "Docs frontend read-policy smoke passed.\n";
