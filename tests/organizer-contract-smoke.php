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

$document_render_start = strpos( (string) $organizer, 'private static function render_document(' );
$document_render_end   = strpos( (string) $organizer, 'private static function render_attention_list(', false === $document_render_start ? 0 : $document_render_start );
$document_render       = false !== $document_render_start && false !== $document_render_end
	? substr( (string) $organizer, $document_render_start, $document_render_end - $document_render_start )
	: '';


$checks = [
	'Organizer uses public Reorder Foundation helper' => str_contains( (string) $organizer, 'Assets::enqueue_reorder' ),
	'Organizer module depends on public Reorder module ID' => str_contains( (string) $organizer, "'@cb-core/reorder'" ),
	'Organizer stays under native Docs content menu' => str_contains( (string) $organizer, "'edit.php?post_type=' . PostType::TYPE" ),
	'category order is native registered term metadata' => str_contains( (string) $category_order, 'register_term_meta' ) && str_contains( (string) $category_order, 'cb_docs_order' ),
	'category order metadata is not a public REST write surface' => str_contains( (string) $category_order, "'show_in_rest'      => false" ),
	'document ordering continues to use menu_order' => str_contains( (string) $mutation, "'menu_order'" ),
	'Organizer resolves one hierarchy path to its deepest assigned category' => str_contains( (string) $snapshot, 'StructuralCategory::resolve' ),
	'document moves use the same structural category resolver' => str_contains( (string) $mutation, 'StructuralCategory::resolve' ),
	'Organizer presentation bridges active Admin Theme tokens with WP-native fallbacks' => str_contains( (string) $organizer_css, 'html[data-cb-theme] body.cb-admin-theme .cb-docs-organizer-wrap' ) && str_contains( (string) $organizer_css, '--cb-docs-org-surface: var(--cb-surface-1);' ),
	'same-category reorder avoids taxonomy writes in commit and rollback paths' => substr_count( (string) $mutation, 'if ( ! $same_structural_category )' ) >= 2,
	'mutations require expected structure revision' => str_contains( (string) $mutation, 'expected_revision' ) && str_contains( (string) $mutation, 'stale' ),
	'mutations are serialized behind Docs-owned lock' => str_contains( (string) $mutation, 'MutationLock::acquire' ),
	'Organizer REST stays a private admin mutation boundary' => str_contains( (string) $rest, '/organizer/document' ) && str_contains( (string) $rest, '/organizer/term' ),
	'Organizer document route checks object-level edit authority' => str_contains( (string) $rest, 'can_move_document' ) && str_contains( (string) $rest, "current_user_can( 'edit_post', \$document_id )" ),
	'structure revision excludes ordinary document status' => ! str_contains( (string) $revision, "'status'" ),
	'Organizer runtime consumes public Reorder API' => str_contains( (string) $runtime, 'window.cbCore?.reorder' ) && str_contains( (string) $runtime, 'crossList: true' ),
	'Organizer category disclosure is accessible and locally persisted' => str_contains( (string) $organizer, 'data-cb-docs-toggle-term' ) && str_contains( (string) $organizer, 'aria-expanded' ) && str_contains( (string) $runtime, 'window.localStorage' ) && str_contains( (string) $runtime, 'data-cb-docs-term-id' ),
	'Organizer exposes bulk disclosure controls' => str_contains( (string) $organizer, 'data-cb-docs-expand-all' ) && str_contains( (string) $organizer, 'data-cb-docs-collapse-all' ),
	'Organizer assets use content-derived cache versions' => str_contains( (string) $organizer, "hash_file( 'sha256', \$path )" ) && str_contains( (string) $organizer, "asset_version( 'assets/css/admin-organizer.css' )" ),
	'Organizer resynchronizes Move-to controls after document moves' => str_contains( (string) $runtime, 'syncMoveToControl' ) && str_contains( (string) $runtime, 'option.disabled' ),
	'Organizer action controls use accessible Dashicon buttons' => str_contains( (string) $organizer, 'dashicons-arrow-up-alt2' ) && str_contains( (string) $organizer, 'dashicons-arrow-down-alt2' ) && str_contains( (string) $organizer, 'dashicons-edit' ) && str_contains( (string) $organizer, 'cb-docs-organizer__icon-button' ) && str_contains( (string) $organizer, 'aria-label=' ),
	'Organizer document view action opens the canonical frontend or preview URL in a new tab' => str_contains( (string) $organizer, 'get_permalink( $post )' ) && str_contains( (string) $organizer, 'get_preview_post_link( $post )' ) && str_contains( (string) $organizer, 'dashicons-visibility' ) && str_contains( (string) $organizer, 'target="_blank"' ) && str_contains( (string) $organizer, 'noopener noreferrer' ),
	'Organizer document actions keep Up Down Move-to Edit View order' => '' !== $document_render
		&& false !== ( $action_up = strpos( $document_render, 'data-cb-docs-move-up' ) )
		&& false !== ( $action_down = strpos( $document_render, 'data-cb-docs-move-down' ) )
		&& false !== ( $action_move = strpos( $document_render, 'data-cb-docs-move-to' ) )
		&& false !== ( $action_edit = strpos( $document_render, 'dashicons-edit' ) )
		&& false !== ( $action_view = strpos( $document_render, 'dashicons-visibility' ) )
		&& $action_up < $action_down
		&& $action_down < $action_move
		&& $action_move < $action_edit
		&& $action_edit < $action_view,
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
