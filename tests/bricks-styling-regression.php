<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$element = (string) file_get_contents( $root . '/src/Integration/Builders/Bricks/Elements/Search.php' );
$markup  = (string) file_get_contents( $root . '/src/Frontend/Components/Search.php' );
$css     = (string) file_get_contents( $root . '/assets/css/docs-search.css' );

function cb_docs_bricks_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

function cb_docs_control_block( string $content, string $name ): string {
	$marker = "\t\t\$this->controls['" . $name . "'] = [";
	$start  = strpos( $content, $marker );
	cb_docs_bricks_assert( false !== $start, 'Missing Bricks control: ' . $name );

	$next   = strpos( $content, "\n\t\t\$this->controls[", (int) $start + strlen( $marker ) );
	$render = strpos( $content, "\n\t}\n\n\tpublic function render", (int) $start + strlen( $marker ) );
	$end    = false !== $next ? $next : $render;
	cb_docs_bricks_assert( false !== $end, 'Could not delimit Bricks control: ' . $name );

	return substr( $content, (int) $start, (int) $end - (int) $start );
}

cb_docs_bricks_assert( ! str_contains( $element, "'tab'   => 'style'" ) && ! str_contains( $element, "'tab'      => 'style'" ), 'Docs-specific controls must remain under Content.' );
cb_docs_bricks_assert( ! preg_match( "/'type'\s*=>\s*'slider'/", $element ), 'Docs Search must not use slider controls for CSS lengths.' );
cb_docs_bricks_assert( str_contains( $element, "'property' => 'list-style-type'" ), 'Docs Search semantic result list must expose List marker.' );
cb_docs_bricks_assert( str_contains( $element, 'SearchComponent::render( $args )' ), 'Docs Search must delegate rendering to the canonical builder-neutral component.' );

preg_match_all( "/'selector'\s*=>\s*'([^']+)'/", $element, $selectors );
foreach ( $selectors[1] ?? [] as $selector ) {
	preg_match_all( '/\.([a-zA-Z0-9_-]+)/', (string) $selector, $classes );
	foreach ( $classes[1] ?? [] as $class ) {
		cb_docs_bricks_assert( str_contains( $markup, (string) $class ), 'Selector class .' . $class . ' is absent from canonical Docs Search markup.' );
	}
}

$background_count = preg_match_all( "/'type'\s*=>\s*'background'/", $element );
$video_excludes   = preg_match_all( "/'exclude'\s*=>\s*\[\s*'videoUrl',\s*'videoScale'\s*\]/", $element );
cb_docs_bricks_assert( $background_count === $video_excludes, 'Every Docs internal Background control must exclude video settings.' );

foreach ( [
	'inputMinHeight',
	'buttonMinHeight',
	'formColumnGap',
	'formRowGap',
	'formGridGap',
	'listColumnGap',
	'listRowGap',
	'listGridGap',
] as $name ) {
	$block = cb_docs_control_block( $element, $name );
	cb_docs_bricks_assert( (bool) preg_match( "/'type'\s*=>\s*'number'/", $block ), $name . ' must use native Bricks number control.' );
	cb_docs_bricks_assert( (bool) preg_match( "/'units'\s*=>\s*true/", $block ), $name . ' must expose native Bricks units.' );
}

foreach ( [
	[ 'form', 'formDisplay' ],
	[ 'list', 'listDisplay' ],
] as [ $prefix, $display ] ) {
	foreach ( [
		$prefix . 'FlexWrap'       => 'flex-wrap',
		$prefix . 'Direction'      => 'flex-direction',
		$prefix . 'JustifyContent' => 'justify-content',
		$prefix . 'AlignItems'     => 'align-items',
		$prefix . 'ColumnGap'      => 'column-gap',
		$prefix . 'RowGap'         => 'row-gap',
	] as $name => $property ) {
		$block = cb_docs_control_block( $element, $name );
		cb_docs_bricks_assert( str_contains( $block, "'property' => '" . $property . "'" ), $name . ' must target ' . $property . '.' );
		cb_docs_bricks_assert( str_contains( $block, "'" . $display . "'" ) && str_contains( $block, "'flex'" ), $name . ' must only appear for flex display.' );
	}
}

foreach ( [
	'formGridColumns' => 'formDisplay',
	'formGridGap'     => 'formDisplay',
	'listColumns'     => 'listDisplay',
	'listGridGap'     => 'listDisplay',
] as $name => $display ) {
	$block = cb_docs_control_block( $element, $name );
	cb_docs_bricks_assert( str_contains( $block, "'" . $display . "'" ) && str_contains( $block, "'grid'" ), $name . ' must only appear for grid display.' );
}

foreach ( [
	'resultCategoryTypography' => 'showCategory',
	'resultExcerptTypography'  => 'showExcerpt',
] as $name => $state ) {
	$block = cb_docs_control_block( $element, $name );
	cb_docs_bricks_assert( str_contains( $block, "'" . $state . "'" ) && str_contains( $block, 'true' ), $name . ' must only appear when its markup is enabled.' );
}

foreach ( [
	"'inputFocusBorder'",
	"'inputFocusShadow'",
	"'buttonHoverBackground'",
	"'buttonFocusBackground'",
	"'itemHoverBackground'",
	"'itemFocusBackground'",
	"'itemSelectedBackground'",
	"'resultTitleHoverColor'",
	"'resultTitleFocusColor'",
	"'resultTitleSelectedColor'",
] as $needle ) {
	cb_docs_bricks_assert( str_contains( $element, $needle ), 'Docs Search state styling contract missing ' . $needle . '.' );
}

cb_docs_bricks_assert( ! str_contains( $css, 'list-style: none' ), 'Docs Search baseline CSS must not defeat the List marker control.' );
cb_docs_bricks_assert( str_contains( $css, '.cb-docs-search__input' ) && str_contains( $css, 'min-width: 0' ), 'Docs Search baseline must remain flex/grid-safe.' );

fwrite( STDOUT, "Docs Bricks styling regression: PASS\n" );
