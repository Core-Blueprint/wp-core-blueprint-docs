<?php
declare(strict_types=1);

namespace CB\Docs;

use CB\Docs\Content\Meta;
use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

final class Install {
	public static function activate(): void {
		PostType::register();
		Taxonomies::register();
		Meta::register();
		flush_rewrite_rules();
		delete_option( Settings::REWRITE_DIRTY_OPTION );
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
