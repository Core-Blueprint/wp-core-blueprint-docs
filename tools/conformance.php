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
	'src/Content/PostType.php',
	'src/Content/Taxonomies.php',
	'src/Content/Meta.php',
	'src/Admin/DocDetails.php',
	'src/Frontend/Queries.php',
	'src/Frontend/Shortcodes.php',
	'src/Governance/Events.php',
	'src/Integration/Suite.php',
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
	'Requires Plugins:'                    => 'first-party extensions use the runtime Base dependency guard',
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

$post_type = (string) file_get_contents( $root . '/src/Content/PostType.php' );
foreach ( [ "'custom-fields'", "'comments'", "'revisions'", "'page-attributes'", "'show_in_rest'" ] as $required ) {
	if ( ! str_contains( $post_type, $required ) ) {
		$failures[] = 'Post type contract is missing ' . $required . '.';
	}
}

$events = (string) file_get_contents( $root . '/src/Governance/Events.php' );
foreach ( [ 'EventRegistry::register', 'Audit::record' ] as $required ) {
	if ( ! str_contains( $events, $required ) ) {
		$failures[] = 'Governance contract is missing ' . $required . '.';
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
