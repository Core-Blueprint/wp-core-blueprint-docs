<?php
declare(strict_types=1);

$root        = dirname( __DIR__ );
$preferences = file_get_contents( $root . '/src/Integration/Preferences.php' );
$bootstrap   = file_get_contents( $root . '/src/Integration/Builders/Bootstrap.php' );
$settings    = file_get_contents( $root . '/src/Admin/SettingsPage.php' );
$readiness   = file_get_contents( $root . '/src/Admin/IntegrationReadiness.php' );
$integration_start = is_string( $settings ) ? strpos( $settings, 'private static function render_integrations()' ) : false;
$integration_end   = is_string( $settings ) ? strpos( $settings, 'public static function save_integrations()', false === $integration_start ? 0 : $integration_start ) : false;
$integration_form  = false !== $integration_start && false !== $integration_end
	? substr( $settings, $integration_start, $integration_end - $integration_start )
	: '';

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
	'admin integration preferences use capability nonce and audit boundaries' =>
		is_string( $settings )
		&& str_contains( $settings, "admin_post_cb_docs_save_integrations" )
		&& str_contains( $settings, "current_user_can( 'manage_options' )" )
		&& str_contains( $settings, "check_admin_referer( 'cb_docs_save_integrations', 'cb_docs_integrations_nonce' )" )
		&& str_contains( $settings, "Events::record_settings_updated( 'integration_bricks'" ),
	'admin integration form consumes Base Golden form composition' =>
		'' !== $integration_form
		&& str_contains( $settings, "'choice-group'" )
		&& str_contains( $settings, "'fields'" )
		&& str_contains( $settings, "'radio-cards'" )
		&& str_contains( $settings, "'actions'" )
		&& substr_count( $integration_form, 'Field::VARIANT_SEPARATED' ) >= 2
		&& str_contains( $integration_form, 'ChoiceGroup::render(' )
		&& str_contains( $integration_form, 'RadioGroup::render(' )
		&& str_contains( $integration_form, 'RadioGroup::LAYOUT_GRID' )
		&& str_contains( $integration_form, 'cb-core-actions' )
		&& ! str_contains( $integration_form, '<fieldset>' )
		&& ! str_contains( $integration_form, 'style=' ),
	'Integration readiness exposes Gutenberg and preference-aware Bricks state' =>
		is_string( $readiness )
		&& str_contains( $readiness, 'self::gutenberg_item()' )
		&& str_contains( $readiness, 'Preferences::BRICKS_DISABLED === $mode' ),
];

$failed = array_keys( array_filter( $checks, static fn( bool $passed ): bool => ! $passed ) );
if ( [] !== $failed ) {
	fwrite( STDERR, "Docs integration preferences regression: FAIL\n- " . implode( "\n- ", $failed ) . "\n" );
	exit( 1 );
}

echo "Docs integration preferences regression: PASS\n";
