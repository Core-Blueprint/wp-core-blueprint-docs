<?php
declare(strict_types=1);

$root = dirname( __DIR__ );
$failures = [];

/** @return string[] */
function cb_docs_files_with_extension( string $directory, string $extension ): array {
	if ( ! is_dir( $directory ) ) {
		return [];
	}

	$files = [];
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {
		if ( $file instanceof SplFileInfo && $file->isFile() && strtolower( $file->getExtension() ) === $extension ) {
			$files[] = $file->getPathname();
		}
	}
	sort( $files );
	return $files;
}

$expected = [
	'core-blueprint-docs.php',
	'src/Plugin.php',
	'src/Install.php',
	'src/Settings.php',
	'src/Content/PostType.php',
	'src/Content/Taxonomies.php',
	'src/Content/Meta.php',
	'src/Admin/DocDetails.php',
	'src/Admin/SettingsPage.php',
	'src/Admin/IntegrationReadiness.php',
	'src/Frontend/Queries.php',
	'src/Frontend/Shortcodes.php',
	'src/Frontend/DocumentAccess.php',
	'src/Frontend/Data/Document.php',
	'src/Frontend/Queries/Documents.php',
	'src/Frontend/Search.php',
	'src/Frontend/Conditions/Documents.php',
	'src/Governance/Events.php',
	'src/Integration/Suite.php',
	'src/Integration/Builders/Bootstrap.php',
	'src/Integration/Builders/Readiness.php',
	'src/Integration/Builders/Bricks/Bootstrap.php',
	'src/Integration/Builders/Bricks/DocumentContext.php',
	'src/Integration/Builders/Bricks/DynamicData.php',
	'src/Integration/Builders/Bricks/Queries.php',
	'src/Integration/Builders/Bricks/Conditions.php',
	'src/Integration/Builders/Bricks/GroupOrder.php',
	'docs/INTEGRATION-API.md',
	'docs/BRICKS.md',
	'assets/js/admin-shortcodes.js',
];
foreach ( $expected as $path ) {
	if ( ! is_file( $root . '/' . $path ) ) {
		$failures[] = 'Missing expected runtime file: ' . $path;
	}
}

$php_files = array_merge( [ $root . '/core-blueprint-docs.php' ], cb_docs_files_with_extension( $root . '/src', 'php' ) );
$forbidden = [
	'cb-core-css-'                         => 'private Base CSS handles are not public API',
	'cb_core_event_labels'                 => 'legacy event-label mutation is not the Governance contract',
	'CB\\Core\\Log\\AuditLog'            => 'extensions must write through Governance\\Audit',
	'CB\\Core\\Admin\\AdminAssetCatalog' => 'the Base asset catalog is private',
	'CB\\Core\\Admin\\PageBase'          => 'PageBase is internal and must not be consumed by extensions',
	'jquery'                               => 'Docs has no jQuery runtime',
];
foreach ( $php_files as $file ) {
	if ( ! is_file( $file ) ) {
		continue;
	}
	$content = (string) file_get_contents( $file );
	foreach ( $forbidden as $needle => $reason ) {
		if ( false !== stripos( $content, $needle ) ) {
			$failures[] = sprintf( '%s contains forbidden pattern "%s" (%s).', str_replace( $root . '/', '', $file ), $needle, $reason );
		}
	}
}

$bootstrap = (string) file_get_contents( $root . '/core-blueprint-docs.php' );
if ( ! str_contains( $bootstrap, 'Requires Plugins: core-blueprint' ) ) {
	$failures[] = 'Bootstrap is missing the canonical native Base dependency header.';
}
if ( str_contains( $bootstrap, 'function cb_docs_base_ready' ) ) {
	$failures[] = 'Bootstrap retains the obsolete pre-v1 readiness compatibility helper.';
}
foreach ( [
	"class_exists( '\\\\CB\\\\Core\\\\Admin\\\\SettingsRegistry' )",
	"class_exists( '\\\\CB\\\\Core\\\\UI\\\\Card' )",
	"class_exists( '\\\\CB\\\\Core\\\\UI\\\\Notice' )",
	"class_exists( '\\\\CB\\\\Core\\\\UI\\\\IntegrationGrid' )",
] as $required ) {
	if ( ! str_contains( $bootstrap, $required ) ) {
		$failures[] = 'Base dependency contract is missing ' . $required . '.';
	}
}
if ( str_contains( $bootstrap, 'CB\\Core\\Admin\\PageRegistry' ) || str_contains( $bootstrap, "interface_exists( '\\\\CB\\\\Core\\\\Admin\\\\Page' )" ) ) {
	$failures[] = 'Bootstrap retains the retired PageRegistry/Page settings routing contract.';
}

$post_type = (string) file_get_contents( $root . '/src/Content/PostType.php' );
foreach ( [ "'custom-fields'", "'comments'", "'revisions'", "'page-attributes'", "'show_in_rest'", 'Settings::rewrite_base()' ] as $required ) {
	if ( ! str_contains( $post_type, $required ) ) {
		$failures[] = 'Post type contract is missing ' . $required . '.';
	}
}

$settings = (string) file_get_contents( $root . '/src/Settings.php' );
foreach ( [ 'DEFAULT_REWRITE_BASE', 'REWRITE_DIRTY_OPTION', 'flush_rewrite_rules( false )', 'Events::record_settings_updated', 'SettingsRegistry::url(', 'Suite::ID', "'tab'             => 'general'" ] as $required ) {
	if ( ! str_contains( $settings, $required ) ) {
		$failures[] = 'Settings contract is missing ' . $required . '.';
	}
}
if ( str_contains( $settings, 'SettingsPage::SLUG' ) || str_contains( $settings, 'core-blueprint-docs-settings' ) ) {
	$failures[] = 'Settings save redirect retains the retired flat Docs settings route.';
}

$settings_page = (string) file_get_contents( $root . '/src/Admin/SettingsPage.php' );
foreach ( [
	"add_action( 'cb_core_register_settings'",
	'SettingsRegistry::register',
	'SettingsRegistry::GROUP_CONTENT_PUBLISHING',
	"SettingsRegistry::url( Suite::ID",
	"'form-controls'",
	"'cards'",
	"'clipboard'",
	"'integration-grid'",
	"'metric-tiles'",
	"'nav-tabs'",
	'TAB_OVERVIEW',
	'TAB_GENERAL',
	'TAB_INTEGRATIONS',
	'IntegrationGrid::render',
	'IntegrationReadiness::items',
	'Card::render',
	'cb_docs_save_settings',
	'data-cb-docs-shortcode-copy',
	'[cb_docs_list]',
	'[cb_docs_navigation]',
	'[cb_docs_search]',
	'[cb_docs_breadcrumbs]',
	'[cb_docs_meta]',
] as $required ) {
	if ( ! str_contains( $settings_page, $required ) ) {
		$failures[] = 'Settings page contract is missing ' . $required . '.';
	}
}
foreach ( [ 'cb_core_register_pages', 'PageRegistry::', 'implements PageContract', 'core-blueprint-docs-settings' ] as $retired_settings_route ) {
	if ( str_contains( $settings_page, $retired_settings_route ) ) {
		$failures[] = 'Settings page retains retired routing contract: ' . $retired_settings_route . '.';
	}
}

$integration_readiness = (string) file_get_contents( $root . '/src/Admin/IntegrationReadiness.php' );
foreach ( [
	'IntegrationGrid::READY',
	'IntegrationGrid::OPTIONAL',
	'BuilderReadiness::bricks_active()',
] as $required ) {
	if ( ! str_contains( $integration_readiness, $required ) ) {
		$failures[] = 'Integration readiness contract is missing ' . $required . '.';
	}
}
foreach ( [ 'BRICKS_VERSION', '\\Bricks\\' ] as $forbidden_admin_builder_reference ) {
	if ( str_contains( $integration_readiness, $forbidden_admin_builder_reference ) ) {
		$failures[] = 'Admin integration readiness must consume the builder-neutral readiness boundary, not Bricks directly: ' . $forbidden_admin_builder_reference . '.';
	}
}

$builder_readiness = (string) file_get_contents( $root . '/src/Integration/Builders/Readiness.php' );
foreach ( [
	'bricks_active',
	"defined( 'BRICKS_VERSION' )",
	"class_exists( '\\\\Bricks\\\\Query' )",
] as $required ) {
	if ( ! str_contains( $builder_readiness, $required ) ) {
		$failures[] = 'Builder readiness boundary is missing ' . $required . '.';
	}
}

$shortcode_adapter = (string) file_get_contents( $root . '/assets/js/admin-shortcodes.js' );
foreach ( [ "from '@cb-core/clipboard'", 'clipboard.enhance', 'data-cb-docs-shortcode-copy' ] as $required ) {
	if ( ! str_contains( $shortcode_adapter, $required ) ) {
		$failures[] = 'Shortcode clipboard adapter is missing ' . $required . '.';
	}
}
foreach ( [ 'navigator.clipboard', 'execCommand', 'document.createElement( \'textarea\'' ] as $forbidden_clipboard_logic ) {
	if ( str_contains( $shortcode_adapter, $forbidden_clipboard_logic ) ) {
		$failures[] = 'Docs must not implement clipboard mechanics itself: ' . $forbidden_clipboard_logic . '.';
	}
}

$events = (string) file_get_contents( $root . '/src/Governance/Events.php' );
foreach ( [ 'EventRegistry::register', 'Audit::record', 'docs.settings.updated' ] as $required ) {
	if ( ! str_contains( $events, $required ) ) {
		$failures[] = 'Governance contract is missing ' . $required . '.';
	}
}

$public_query = (string) file_get_contents( $root . '/src/Frontend/Queries/Documents.php' );
foreach ( [ "'post_status'         => 'publish'", "'perm'                => 'readable'", "'suppress_filters'    => false", 'MAX_PER_PAGE', "'include_ids'", "'category'", "'tag'", "'search'" ] as $required ) {
	if ( ! str_contains( $public_query, $required ) ) {
		$failures[] = 'Public Docs query contract is missing ' . $required . '.';
	}
}

$public_data = (string) file_get_contents( $root . '/src/Frontend/Data/Document.php' );
foreach ( [ 'DocumentAccess::can_read', "'content'", "'categories'", "'tags'", "'documentation_status'", "'last_reviewed'" ] as $required ) {
	if ( ! str_contains( $public_data, $required ) ) {
		$failures[] = 'Public Docs data contract is missing ' . $required . '.';
	}
}

$public_search = (string) file_get_contents( $root . '/src/Frontend/Search.php' );
foreach ( [ 'MAX_LIMIT', 'Documents::query', "'' === \$search" ] as $required ) {
	if ( ! str_contains( $public_search, $required ) ) {
		$failures[] = 'Public Docs search contract is missing ' . $required . '.';
	}
}

$public_conditions = (string) file_get_contents( $root . '/src/Frontend/Conditions/Documents.php' );
foreach ( [ 'is_current', 'in_category', 'has_tag', 'user_can_read', 'DocumentAccess::can_read' ] as $required ) {
	if ( ! str_contains( $public_conditions, $required ) ) {
		$failures[] = 'Public Docs conditions contract is missing ' . $required . '.';
	}
}

$plugin = (string) file_get_contents( $root . '/src/Plugin.php' );
foreach ( [ 'Integration\\Builders\\Bootstrap as BuildersBootstrap', 'BuildersBootstrap::init()', 'SettingsRegistry::url( Suite::ID )' ] as $required ) {
	if ( ! str_contains( $plugin, $required ) ) {
		$failures[] = 'Plugin contract is missing ' . $required . '.';
	}
}
if ( str_contains( $plugin, 'SettingsPage::SLUG' ) || str_contains( $plugin, 'core-blueprint-docs-settings' ) ) {
	$failures[] = 'Plugin action links retain the retired flat Docs settings route.';
}

$builder_bootstrap = (string) file_get_contents( $root . '/src/Integration/Builders/Bootstrap.php' );
foreach ( [ "defined( 'BRICKS_VERSION' )", "class_exists( '\\\\Bricks\\\\Query' )", 'Integration\\Builders\\Bricks\\Bootstrap::init()' ] as $required ) {
	if ( ! str_contains( $builder_bootstrap, $required ) ) {
		$failures[] = 'Optional builder bootstrap is missing ' . $required . '.';
	}
}

$bricks_bootstrap = (string) file_get_contents( $root . '/src/Integration/Builders/Bricks/Bootstrap.php' );
foreach ( [ 'DynamicData::init()', 'GroupOrder::init()', 'Queries::init()', 'Conditions::init()' ] as $required ) {
	if ( ! str_contains( $bricks_bootstrap, $required ) ) {
		$failures[] = 'Bricks bootstrap is missing ' . $required . '.';
	}
}

$dynamic_data = (string) file_get_contents( $root . '/src/Integration/Builders/Bricks/DynamicData.php' );
foreach ( [ 'bricks/dynamic_tags_list', 'bricks/dynamic_data/render_tag', 'bricks/dynamic_data/render_content', 'bricks/frontend/render_data', 'DocumentContext::value', 'Core Blueprint Docs' ] as $required ) {
	if ( ! str_contains( $dynamic_data, $required ) ) {
		$failures[] = 'Bricks Dynamic Data adapter is missing ' . $required . '.';
	}
}
foreach ( [ 'get_post_meta(', 'new \\WP_Query' ] as $forbidden_dynamic_logic ) {
	if ( str_contains( $dynamic_data, $forbidden_dynamic_logic ) ) {
		$failures[] = 'Bricks Dynamic Data must consume public Docs contracts, not domain/storage logic: ' . $forbidden_dynamic_logic . '.';
	}
}

$bricks_queries = (string) file_get_contents( $root . '/src/Integration/Builders/Bricks/Queries.php' );
foreach ( [ 'bricks/setup/control_options', 'bricks/query/run', 'Documents::query', 'Search::documents', 'cb_docs_documents', 'cb_docs_search_results' ] as $required ) {
	if ( ! str_contains( $bricks_queries, $required ) ) {
		$failures[] = 'Bricks query adapter is missing ' . $required . '.';
	}
}
foreach ( [ 'new \\WP_Query', 'get_posts(' ] as $forbidden_query_logic ) {
	if ( str_contains( $bricks_queries, $forbidden_query_logic ) ) {
		$failures[] = 'Bricks query adapter must delegate to public Docs providers: ' . $forbidden_query_logic . '.';
	}
}

$bricks_conditions = (string) file_get_contents( $root . '/src/Integration/Builders/Bricks/Conditions.php' );
foreach ( [ 'bricks/conditions/groups', 'bricks/conditions/options', 'bricks/conditions/result', 'Documents::user_can_read', 'Documents::in_category', 'Documents::has_tag' ] as $required ) {
	if ( ! str_contains( $bricks_conditions, $required ) ) {
		$failures[] = 'Bricks conditions adapter is missing ' . $required . '.';
	}
}

$group_order = (string) file_get_contents( $root . '/src/Integration/Builders/Bricks/GroupOrder.php' );
foreach ( [ "GROUP_PREFIX = 'Core Blueprint '", 'PRIORITY     = 9999', 'array_splice' ] as $required ) {
	if ( ! str_contains( $group_order, $required ) ) {
		$failures[] = 'Core Blueprint Bricks group-order contract is missing ' . $required . '.';
	}
}

foreach ( cb_docs_files_with_extension( $root . '/src', 'php' ) as $file ) {
	$relative = str_replace( $root . '/', '', $file );
	if ( str_starts_with( $relative, 'src/Integration/Builders/' ) ) {
		continue;
	}
	$content = (string) file_get_contents( $file );
	if ( str_contains( $content, 'bricks/' ) || str_contains( $content, '\\Bricks\\' ) || str_contains( $content, 'BRICKS_VERSION' ) ) {
		$failures[] = $relative . ' contains a Bricks reference outside the builder adapter/bootstrap boundary.';
	}
}

if ( ! empty( $failures ) ) {
	fwrite( STDERR, "Core Blueprint Docs conformance: FAIL\n\n" );
	foreach ( $failures as $failure ) {
		fwrite( STDERR, '- ' . $failure . "\n" );
	}
	exit( 1 );
}

fwrite( STDOUT, "Core Blueprint Docs conformance: PASS\n" );
exit( 0 );
