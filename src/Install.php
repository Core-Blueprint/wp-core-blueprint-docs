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
		if ( taxonomy_exists( Taxonomies::CATEGORY ) ) {
			unregister_taxonomy( Taxonomies::CATEGORY );
		}
		if ( taxonomy_exists( Taxonomies::TAG ) ) {
			unregister_taxonomy( Taxonomies::TAG );
		}
		if ( post_type_exists( PostType::TYPE ) ) {
			unregister_post_type( PostType::TYPE );
		}

		flush_rewrite_rules();
	}
}
