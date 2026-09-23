<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$location = file_get_contents( $root . '/src/Structure/DocumentLocation.php' );
$adjacent = file_get_contents( $root . '/src/Frontend/Navigation/AdjacentDocuments.php' );
$plugin = file_get_contents( $root . '/src/Plugin.php' );

foreach ( compact( 'location', 'adjacent', 'plugin' ) as $name => $source ) {
	if ( false === $source ) {
		fwrite( STDERR, "FAIL: could not read {$name}.\n" );
		exit( 1 );
	}
}

$checks = [
	'canonical sibling discovery uses the shared structural resolver' => str_contains( (string) $location, 'StructuralCategory::resolve' ) && str_contains( (string) $location, 'get_objects_in_term' ) && str_contains( (string) $location, "'all_with_object_id'" ),
	'unassigned or ambiguous structure fails closed' => str_contains( (string) $location, 'null === $structural_id' ) && str_contains( (string) $adjacent, "' AND 1=0'" ),
	'native previous and next WHERE hooks are registered' => str_contains( (string) $adjacent, "'get_previous_post_where'" ) && str_contains( (string) $adjacent, "'get_next_post_where'" ),
	'native previous and next sort hooks are registered' => str_contains( (string) $adjacent, "'get_previous_post_sort'" ) && str_contains( (string) $adjacent, "'get_next_post_sort'" ),
	'adjacent ordering is menu_order with deterministic ID fallback' => str_contains( (string) $adjacent, 'p.menu_order' ) && str_contains( (string) $adjacent, 'p.ID' ) && str_contains( (string) $adjacent, 'LIMIT 1' ),
	'native WordPress restrictions remain in the original WHERE suffix' => str_contains( (string) $adjacent, 'substr( $where, $marker_position )' ),
	'adjacent candidates are restricted to the canonical structural sibling set' => str_contains( (string) $adjacent, 'DocumentLocation::sibling_document_ids' ) && str_contains( (string) $adjacent, "' AND p.ID IN ('" ),
	'non-Docs post types are returned untouched' => substr_count( (string) $adjacent, 'PostType::TYPE !== $post->post_type' ) >= 2,
	'plugin boots the builder-neutral native adapter' => str_contains( (string) $plugin, 'AdjacentDocuments::init()' ),
	'no builder-specific dependency exists in the native adapter' => ! str_contains( (string) $adjacent, 'Bricks' ) && ! str_contains( (string) $adjacent, 'bricks/' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, 'Docs adjacent-navigation regression failed: ' . $label . "\n" );
		exit( 1 );
	}
}

echo "Docs adjacent-navigation regression passed.\n";
