<?php
declare(strict_types=1);

$root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $value ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $value ) ?? '' );
	}
}

require_once $root . '/src/Permalinks/RouteIndex.php';

use CB\Docs\Permalinks\RouteIndex;

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "Docs permalink route regression failed: {$message}\n" );
		exit( 1 );
	}
};

$categories = [
	[ 'id' => 10, 'parent' => 0, 'slug' => 'wp-suite' ],
	[ 'id' => 20, 'parent' => 10, 'slug' => 'core-blueprint-base' ],
	[ 'id' => 30, 'parent' => 20, 'slug' => 'security' ],
];

$index = RouteIndex::build(
	$categories,
	[
		[ 'id' => 100, 'slug' => 'getting-started', 'structural_category_id' => 20, 'public' => true ],
		[ 'id' => 101, 'slug' => 'unassigned-doc', 'structural_category_id' => null, 'structure_state' => 'unassigned', 'public' => true ],
	]
);

$assert( 'wp-suite' === $index['categories'][10], 'top-level category path' );
$assert( 'wp-suite/core-blueprint-base' === $index['categories'][20], 'child category path' );
$assert( 'wp-suite/core-blueprint-base/getting-started' === $index['documents'][100]['path'], 'hierarchical document route' );
$assert( false === $index['documents'][100]['fallback'], 'safe hierarchy route is canonical' );
$assert( 'document/unassigned-doc' === $index['documents'][101]['path'], 'unassigned document gets fail-safe route' );
$assert( 1 === $index['readiness']['unassigned'], 'unassigned readiness warning' );
$assert( 0 === $index['readiness']['blocking'], 'safe projection has no blocking conflicts' );

$category_collision = RouteIndex::build(
	[
		[ 'id' => 10, 'parent' => 0, 'slug' => 'wp-suite' ],
		[ 'id' => 20, 'parent' => 10, 'slug' => 'base' ],
		[ 'id' => 30, 'parent' => 20, 'slug' => 'getting-started' ],
	],
	[
		[ 'id' => 200, 'slug' => 'getting-started', 'structural_category_id' => 20, 'public' => true ],
	]
);
$assert( 'document/getting-started' === $category_collision['documents'][200]['path'], 'category/document collision uses fail-safe document route' );
$assert( 1 === $category_collision['readiness']['document_category_collisions'], 'category/document collision is reported as warning' );

$reserved = RouteIndex::build(
	[
		[ 'id' => 1, 'parent' => 0, 'slug' => 'tag' ],
		[ 'id' => 2, 'parent' => 0, 'slug' => 'document' ],
	],
	[]
);
$assert( 2 === $reserved['readiness']['reserved_categories'], 'reserved root category slugs are blocking' );
$assert( 2 === $reserved['readiness']['blocking'], 'reserved root conflicts contribute to blocking total' );

$malformed = RouteIndex::build(
	[
		[ 'id' => 60, 'parent' => 70, 'slug' => 'cycle-a' ],
		[ 'id' => 70, 'parent' => 60, 'slug' => 'cycle-b' ],
	],
	[]
);
$assert( 2 === $malformed['readiness']['invalid_category_paths'], 'cyclic category graph is detected' );
$assert( 2 === $malformed['readiness']['blocking'], 'invalid category graph blocks hierarchy activation' );


$legacy_collision = RouteIndex::build(
	[
		[ 'id' => 1, 'parent' => 0, 'slug' => 'getting-started' ],
		[ 'id' => 2, 'parent' => 0, 'slug' => 'base' ],
	],
	[
		[ 'id' => 300, 'slug' => 'getting-started', 'structural_category_id' => 2, 'public' => true ],
	]
);
$assert( 1 === $legacy_collision['readiness']['legacy_simple_collisions'], 'legacy simple URL collision is blocking' );
$assert( 1 === $legacy_collision['readiness']['blocking'], 'legacy simple collision contributes to blocking total' );

$ambiguous = RouteIndex::build(
	$categories,
	[
		[ 'id' => 400, 'slug' => 'review-me', 'structural_category_id' => null, 'structure_state' => 'ambiguous', 'public' => true ],
	]
);
$assert( 'document/review-me' === $ambiguous['documents'][400]['path'], 'ambiguous document uses fail-safe route' );
$assert( 1 === $ambiguous['readiness']['ambiguous'], 'ambiguous assignment is reported' );

echo "Docs permalink route regression passed.\n";
