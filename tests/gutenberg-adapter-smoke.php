<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/src/Integration/Editors/Gutenberg/Bootstrap.php' );
$registry  = file_get_contents( $root . '/src/Integration/Editors/Gutenberg/Registry.php' );
$editor    = file_get_contents( $root . '/assets/js/gutenberg-blocks.js' );

$blocks = [ 'document-list', 'navigation', 'search', 'breadcrumbs', 'document-meta' ];

foreach ( compact( 'bootstrap', 'registry', 'editor' ) as $name => $source ) {
	if ( false === $source ) {
		fwrite( STDERR, "Docs Gutenberg adapter smoke failed: could not read {$name}.\n" );
		exit( 1 );
	}
}

foreach ( $blocks as $block ) {
	$metadata = $root . '/src/Integration/Editors/Gutenberg/blocks/' . $block . '/block.json';
	if ( ! is_file( $metadata ) ) {
		fwrite( STDERR, "Docs Gutenberg adapter smoke failed: missing {$block} block metadata.\n" );
		exit( 1 );
	}
	$data = json_decode( (string) file_get_contents( $metadata ), true );
	if ( ! is_array( $data ) || 3 !== (int) ( $data['apiVersion'] ?? 0 ) ) {
		fwrite( STDERR, "Docs Gutenberg adapter smoke failed: invalid {$block} block metadata.\n" );
		exit( 1 );
	}
	if ( 'core-blueprint' !== (string) ( $data['category'] ?? '' ) || 'cb-docs-block-editor' !== (string) ( $data['editorScript'] ?? '' ) ) {
		fwrite( STDERR, "Docs Gutenberg adapter smoke failed: {$block} is outside the canonical editor adapter contract.\n" );
		exit( 1 );
	}
	if ( false !== ( $data['supports']['html'] ?? null ) ) {
		fwrite( STDERR, "Docs Gutenberg adapter smoke failed: {$block} must disable raw HTML editing.\n" );
		exit( 1 );
	}
}

$checks = [
	'Gutenberg bootstrap obeys the integration preference before hooks register' =>
		str_contains( (string) $bootstrap, '! Preferences::gutenberg_enabled()' )
		&& str_contains( (string) $bootstrap, "add_action( 'init', [ Registry::class, 'register' ], 12 )" ),
	'Gutenberg category registration is idempotent' =>
		str_contains( (string) $bootstrap, "'core-blueprint' ===" )
		&& str_contains( (string) $bootstrap, "'title' => __( 'Core Blueprint'" ),
	'registry maps all five blocks to canonical render callbacks' =>
		str_contains( (string) $registry, 'DocumentList::render(' )
		&& str_contains( (string) $registry, 'Navigation::render(' )
		&& str_contains( (string) $registry, 'Search::render(' )
		&& str_contains( (string) $registry, 'Breadcrumbs::render(' )
		&& str_contains( (string) $registry, 'DocumentMeta::render(' ),
	'Gutenberg adapter contains no domain query or storage logic' =>
		! str_contains( (string) $registry, 'new \\WP_Query' )
		&& ! str_contains( (string) $registry, 'get_post_meta(' )
		&& ! str_contains( (string) $registry, 'StructuralCategory::' ),
	'editor uses native Inspector Controls and server-rendered canonical previews' =>
		str_contains( (string) $editor, 'InspectorControls' )
		&& str_contains( (string) $editor, 'ServerSideRender' )
		&& 5 === substr_count( (string) $editor, 'registerBlockType(' ),
	'editor implements no frontend query or rendering logic' =>
		! str_contains( (string) $editor, 'fetch(' )
		&& ! str_contains( (string) $editor, 'WP_Query' )
		&& ! str_contains( (string) $editor, 'innerHTML' ),
];

foreach ( $checks as $label => $passed ) {
	if ( ! $passed ) {
		fwrite( STDERR, 'Docs Gutenberg adapter smoke failed: ' . $label . "\n" );
		exit( 1 );
	}
}

echo "Docs Gutenberg adapter smoke: PASS\n";
