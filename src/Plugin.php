<?php
declare(strict_types=1);

namespace CB\Docs;

use CB\Core\Admin\SettingsRegistry;
use CB\Docs\Admin\DocDetails;
use CB\Docs\Admin\ListFilters;
use CB\Docs\Admin\OrganizerPage;
use CB\Docs\Admin\OrganizerRest;
use CB\Docs\Admin\SettingsPage;
use CB\Docs\Content\Meta;
use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;
use CB\Docs\Frontend\Navigation\AdjacentDocuments;
use CB\Docs\Frontend\RestSearch;
use CB\Docs\Frontend\Shortcodes;
use CB\Docs\Governance\Events;
use CB\Docs\Integration\Builders\Bootstrap as BuildersBootstrap;
use CB\Docs\Integration\Suite;
use CB\Docs\Permalinks\Catalog;
use CB\Docs\Permalinks\ReservedSlugGuard;
use CB\Docs\Permalinks\Router;
use CB\Docs\Structure\CategoryOrder;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		Suite::init();
		Events::init();
		Settings::init();
		Catalog::init();
		ReservedSlugGuard::init();
		Router::init();
		AdjacentDocuments::init();
		BuildersBootstrap::init();
		RestSearch::init();
		OrganizerRest::init();

		add_action( 'init', [ PostType::class, 'register' ], 5 );
		add_action( 'init', [ Taxonomies::class, 'register' ], 6 );
		add_action( 'init', [ Meta::class, 'register' ], 7 );
		add_action( 'init', [ CategoryOrder::class, 'register' ], 8 );

		Shortcodes::init();

		if ( is_admin() ) {
			DocDetails::init();
			ListFilters::init();
			OrganizerPage::init();
			SettingsPage::init();
		}

		add_filter( 'plugin_action_links_' . CB_DOCS_BASENAME, [ __CLASS__, 'action_links' ] );
	}

	/** @param string[] $links @return string[] */
	public static function action_links( array $links ): array {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'edit.php?post_type=' . PostType::TYPE ) ),
			esc_html__( 'Docs', 'core-blueprint-docs' )
		);
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( SettingsRegistry::url( Suite::ID ) ),
			esc_html__( 'Settings', 'core-blueprint-docs' )
		);
		return $links;
	}
}
