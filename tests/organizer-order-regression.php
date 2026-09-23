<?php
declare(strict_types=1);

$root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

require_once $root . '/src/Structure/Order.php';

use CB\Docs\Structure\Order;

$assert = static function ( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "Docs Organizer order regression failed: {$message}\n" );
		exit( 1 );
	}
};

$assert( [ 'c', 'a', 'b', 'd' ] === Order::move( [ 'a', 'b', 'c', 'd' ], 'c', 0 ), 'backward final-index move' );
$assert( [ 'b', 'c', 'd', 'a' ] === Order::move( [ 'a', 'b', 'c', 'd' ], 'a', 3 ), 'forward final-index move' );
$assert( [ 'a', 'x', 'b' ] === Order::place( [ 'a', 'b' ], 'x', 1 ), 'place missing item' );
$assert( [ 'b', 'c', 'a' ] === Order::place( [ 'a', 'b', 'c' ], 'a', 2 ), 'place existing item' );
$assert(
	[ 'a' => 10, 'b' => 20, 'c' => 30 ] === Order::positions( [ 'a', 'b', 'c' ] ),
	'sparse canonical positions'
);

$duplicate_failed = false;
try {
	Order::normalize( [ 'a', 'a' ] );
} catch ( InvalidArgumentException ) {
	$duplicate_failed = true;
}
$assert( $duplicate_failed, 'duplicate identifiers fail closed' );

$bounds_failed = false;
try {
	Order::move( [ 'a', 'b' ], 'a', 2 );
} catch ( InvalidArgumentException ) {
	$bounds_failed = true;
}
$assert( $bounds_failed, 'move target bounds fail closed' );

echo "Docs Organizer order regression passed.\n";
