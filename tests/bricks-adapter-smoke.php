<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$builder = file_get_contents( $root . '/src/Integration/Builders/Bootstrap.php' );
$registry = file_get_contents( $root . '/src/Integration/Builders/Bricks/ElementRegistry.php' );
$dynamic = file_get_contents( $root . '/src/Integration/Builders/Bricks/DynamicData.php' );
$numbering = file_get_contents( $root . '/src/Structure/Numbering.php' );
$queries = file_get_contents( $root . '/src/Integration/Builders/Bricks/Queries.php' );
$conditions = file_get_contents( $root . '/src/Integration/Builders/Bricks/Conditions.php' );
$context = file_get_contents( $root . '/src/Integration/Builders/Bricks/DocumentContext.php' );
$element = file_get_contents( $root . '/src/Integration/Builders/Bricks/Elements/Search.php' );

foreach ( compact( 'builder', 'registry', 'dynamic', 'queries', 'conditions', 'context', 'element', 'numbering' ) as $name => $source ) {
	if ( false === $source ) {
		fwrite( STDERR, "FAIL: could not read {$name}.\n" );
		exit( 1 );
	}
}

$checks = [
	'Bricks remains optional and feature-detected' => str_contains( (string) $builder, 'BRICKS_VERSION' ) && str_contains( (string) $builder, 'class_exists' ) && str_contains( (string) $builder, 'Bricks' ),
	'custom element registration uses Bricks-supported init timing' => str_contains( (string) $builder, "ElementRegistry::class, 'register' ], 11" ),
	'custom Docs element category has a translatable Bricks builder label' => str_contains( (string) $registry, "public const CATEGORY = 'core-blueprint-docs';" ) && str_contains( (string) $registry, "add_filter( 'bricks/builder/i18n'" ) && str_contains( (string) $registry, "esc_html__( 'Core Blueprint Docs', 'core-blueprint-docs' )" ),
	'Docs Search is registered through the Bricks element API' => str_contains( (string) $registry, 'register_element(' ) && str_contains( (string) $registry, "'name'  => 'cb-docs-search'" ),
	'Dynamic Data exposes the Docs group and canonical render hooks' => str_contains( (string) $dynamic, "private const GROUP = 'Core Blueprint Docs';" ) && str_contains( (string) $dynamic, 'bricks/dynamic_data/render_tag' ) && str_contains( (string) $dynamic, 'bricks/frontend/render_data' ),
	'Dynamic Data exposes the canonical Organizer document number' => str_contains( (string) $dynamic, "private const NUMBER_TAG = 'cb_docs_number';" ) && str_contains( (string) $dynamic, "__( 'Doc number', 'core-blueprint-docs' )" ) && str_contains( (string) $dynamic, 'Numbering::document( $document_id )' ) && str_contains( (string) $numbering, 'DocumentLocation::sibling_document_ids' ) && str_contains( (string) $numbering, "'menu_order' => 'ASC'" ) && str_contains( (string) $numbering, "'title' => 'ASC'" ) && str_contains( (string) $numbering, "'ID' => 'ASC'" ),
	'custom query loops delegate to builder-neutral providers' => str_contains( (string) $queries, 'bricks/setup/control_options' ) && str_contains( (string) $queries, 'bricks/query/run' ) && str_contains( (string) $queries, 'Documents::query(' ) && str_contains( (string) $queries, 'Search::documents(' ),
	'conditions delegate to builder-neutral document conditions' => str_contains( (string) $conditions, 'bricks/conditions/result' ) && str_contains( (string) $conditions, 'Documents::user_can_read' ) && str_contains( (string) $conditions, 'Documents::in_category' ) && str_contains( (string) $conditions, 'Documents::has_tag' ),
	'DocumentContext resolves the Bricks loop object before current document fallback' => str_contains( (string) $context, 'get_loop_object()' ) && str_contains( (string) $context, 'Document::current()' ),
	'Docs Search element uses the shared category and canonical search renderer' => str_contains( (string) $element, 'public $category = ElementRegistry::CATEGORY;' ) && str_contains( (string) $element, 'SearchComponent::render( $args )' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, 'Docs Bricks adapter smoke failed: ' . $label . "\n" );
		exit( 1 );
	}
}

echo "Docs Bricks adapter smoke passed.\n";
