<?php
declare(strict_types=1);

$root        = dirname( __DIR__ );
$preferences = file_get_contents( $root . '/src/Integration/Preferences.php' );
$bootstrap   = file_get_contents( $root . '/src/Integration/Builders/Bootstrap.php' );

$checks = [
	'consumer preferences use a dedicated option' =>
		is_string( $preferences )
		&& str_contains( $preferences, "public const OPTION = 'cb_docs_integration_preferences'" ),
	'Gutenberg defaults enabled' =>
		is_string( $preferences )
		&& str_contains( $preferences, "'gutenberg' => self::sanitize_gutenberg( \$stored['gutenberg'] ?? true )" ),
	'Bricks defaults to auto' =>
		is_string( $preferences )
		&& str_contains( $preferences, "'bricks'    => self::sanitize_bricks( \$stored['bricks'] ?? self::BRICKS_AUTO )" ),
	'Bricks exposes auto enabled disabled modes' =>
		is_string( $preferences )
		&& str_contains( $preferences, "BRICKS_AUTO     = 'auto'" )
		&& str_contains( $preferences, "BRICKS_ENABLED  = 'enabled'" )
		&& str_contains( $preferences, "BRICKS_DISABLED = 'disabled'" ),
	'Bricks bootstrap fails closed when disabled' =>
		is_string( $bootstrap )
		&& substr_count( $bootstrap, 'Preferences::bricks_enabled()' ) >= 2
		&& str_contains( $bootstrap, "add_action( 'init', [ \\CB\\Docs\\Integration\\Builders\\Bricks\\ElementRegistry::class, 'register' ], 11 )" ),
];

$failed = array_keys( array_filter( $checks, static fn( bool $passed ): bool => ! $passed ) );
if ( [] !== $failed ) {
	fwrite( STDERR, "Docs integration preferences regression: FAIL\n- " . implode( "\n- ", $failed ) . "\n" );
	exit( 1 );
}

echo "Docs integration preferences regression: PASS\n";
