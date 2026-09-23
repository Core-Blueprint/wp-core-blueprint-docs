<?php
declare(strict_types=1);

$root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

require_once $root . '/src/Structure/StructuralCategory.php';

use CB\Docs\Structure\StructuralCategory;

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "Docs Organizer structure regression failed: {$message}\n" );
		exit( 1 );
	}
};

$parents = [
	10 => 0,
	20 => 10,
	30 => 20,
	40 => 10,
	50 => 0,
];

$assert( 20 === StructuralCategory::resolve( [ 10, 20 ], $parents ), 'ancestor + child resolves to child' );
$assert( 30 === StructuralCategory::resolve( [ 10, 20, 30 ], $parents ), 'single assigned hierarchy resolves to deepest term' );
$assert( 20 === StructuralCategory::resolve( [ 20, 10, 20 ], $parents ), 'duplicate and unordered assignments normalize deterministically' );
$assert( null === StructuralCategory::resolve( [ 20, 40 ], $parents ), 'sibling branch assignments remain ambiguous' );
$assert( null === StructuralCategory::resolve( [ 20, 50 ], $parents ), 'separate root assignments remain ambiguous' );
$assert( 40 === StructuralCategory::resolve( [ 40 ], $parents ), 'single assignment resolves directly' );
$assert( null === StructuralCategory::resolve( [], $parents ), 'empty assignment remains unassigned' );

$cyclic = [ 60 => 70, 70 => 60 ];
$assert( null === StructuralCategory::resolve( [ 60, 70 ], $cyclic ), 'cyclic malformed hierarchy fails closed' );

$projection = [
	[ 'id' => 10, 'parent' => 0 ],
	[ 'id' => 20, 'parent' => 10 ],
];
$assert( [ 10 => 0, 20 => 10 ] === StructuralCategory::parent_map( $projection ), 'snapshot projection builds canonical parent map' );

echo "Docs Organizer structure regression passed.\n";
