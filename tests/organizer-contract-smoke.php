<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/src/Plugin.php' );
$release_builder = file_get_contents( $root . '/tools/build-release' );
$taxonomies = file_get_contents( $root . '/src/Content/Taxonomies.php' );
$shortcodes = file_get_contents( $root . '/src/Frontend/Shortcodes.php' );
$events = file_get_contents( $root . '/src/Governance/Events.php' );
$revision = file_get_contents( $root . '/src/Structure/Revision.php' );

foreach ( compact( 'plugin', 'taxonomies', 'shortcodes', 'events', 'revision', 'release_builder' ) as $name => $source ) {
	if ( false === $source ) {
		fwrite( STDERR, "FAIL: could not read {$name}.\n" );
		exit( 1 );
	}
}

$required_files = [
	'src/Structure/Order.php',
	'src/Structure/StructuralCategory.php',
	'src/Structure/CategoryOrder.php',
	'src/Structure/Snapshot.php',
	'src/Structure/Revision.php',
	'src/Structure/Mutation.php',
	'src/Structure/MutationLock.php',
	'src/Admin/OrganizerPage.php',
	'src/Admin/OrganizerRest.php',
	'assets/js/admin-organizer.js',
	'assets/css/admin-organizer.css',
];

foreach ( $required_files as $file ) {
	if ( ! is_file( $root . '/' . $file ) ) {
		fwrite( STDERR, "Docs Organizer contract failed: missing {$file}.\n" );
		exit( 1 );
	}
}

$organizer = file_get_contents( $root . '/src/Admin/OrganizerPage.php' );
$rest = file_get_contents( $root . '/src/Admin/OrganizerRest.php' );
$mutation = file_get_contents( $root . '/src/Structure/Mutation.php' );
$snapshot = file_get_contents( $root . '/src/Structure/Snapshot.php' );
$organizer_css = file_get_contents( $root . '/assets/css/admin-organizer.css' );
$category_order = file_get_contents( $root . '/src/Structure/CategoryOrder.php' );
$runtime = file_get_contents( $root . '/assets/js/admin-organizer.js' );

$checks = [
	'Organizer uses public Reorder Foundation helper' => str_contains( (string) $organizer, 'Assets::enqueue_reorder' ),
	'Organizer module depends on public Reorder module ID' => str_contains( (string) $organizer, "'@cb-core/reorder'" ),
	'Organizer stays under native Docs content menu' => str_contains( (string) $organizer, "'edit.php?post_type=' . PostType::TYPE" ),
	'category order is native registered term metadata' => str_contains( (string) $category_order, 'register_term_meta' ) && str_contains( (string) $category_order, 'cb_docs_order' ),
	'category order metadata is not a public REST write surface' => str_contains( (string) $category_order, "'show_in_rest'      => false" ),
	'document ordering continues to use menu_order' => str_contains( (string) $mutation, "'menu_order'" ),
	'Organizer resolves one hierarchy path to its deepest assigned category' => str_contains( (string) $snapshot, 'StructuralCategory::resolve' ),
	'document moves use the same structural category resolver' => str_contains( (string) $mutation, 'StructuralCategory::resolve' ),
	'Organizer presentation consumes Admin Theme tokens with WP-native fallbacks' => str_contains( (string) $organizer_css, 'var(--cb-surface-1, #fff)' ) && str_contains( (string) $organizer_css, 'var(--cb-border, #c3c4c7)' ) && ! str_contains( (string) $organizer_css, 'background: #fff;' ),
	'same-category reorder avoids taxonomy writes in commit and rollback paths' => substr_count( (string) $mutation, 'if ( ! $same_structural_category )' ) >= 2,
	'mutations require expected structure revision' => str_contains( (string) $mutation, 'expected_revision' ) && str_contains( (string) $mutation, 'stale' ),
	'mutations are serialized behind Docs-owned lock' => str_contains( (string) $mutation, 'MutationLock::acquire' ),
	'Organizer REST stays a private admin mutation boundary' => str_contains( (string) $rest, '/organizer/document' ) && str_contains( (string) $rest, '/organizer/term' ),
	'Organizer document route checks object-level edit authority' => str_contains( (string) $rest, 'can_move_document' ) && str_contains( (string) $rest, "current_user_can( 'edit_post', \$document_id )" ),
	'structure revision excludes ordinary document status' => ! str_contains( (string) $revision, "'status'" ),
	'Organizer runtime consumes public Reorder API' => str_contains( (string) $runtime, 'window.cbCore?.reorder' ) && str_contains( (string) $runtime, 'crossList: true' ),
	'Organizer resynchronizes Move-to controls after document moves' => str_contains( (string) $runtime, 'syncMoveToControl' ) && str_contains( (string) $runtime, 'option.disabled' ),
	'cross-category drag respects taxonomy assignment authority' => str_contains( (string) $runtime, "dataset?.canAssign === '1'" ),
	'frontend navigation delegates category ordering' => str_contains( (string) $shortcodes, 'CategoryOrder::sort_terms' ),
	'semantic structure audit exists' => str_contains( (string) $events, 'docs.structure.updated' ),
	'plugin boots Organizer structure and mutation surfaces' => str_contains( (string) $plugin, 'OrganizerRest::init' ) && str_contains( (string) $plugin, 'OrganizerPage::init' ),
	'Docs release builder requires Core API 1.1' => str_contains( (string) $release_builder, 'CB_DOCS_REQUIRED_API must remain 1.1' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, 'Docs Organizer contract failed: ' . $label . "\n" );
		exit( 1 );
	}
}

echo "Docs Organizer contract smoke passed.\n";
