<?php
declare(strict_types=1);

namespace CB\Docs;

use CB\Docs\Admin\DocDetails;
use CB\Docs\Admin\SettingsPage;
use CB\Docs\Content\Meta;
use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;
use CB\Docs\Frontend\Shortcodes;
use CB\Docs\Governance\Events;
use CB\Docs\Integration\Suite;

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

		add_action( 'init', [ PostType::class, 'register' ], 5 );
		add_action( 'init', [ Taxonomies::class, 'register' ], 6 );
		add_action( 'init', [ Meta::class, 'register' ], 7 );

		Shortcodes::init();

		if ( is_admin() ) {
			DocDetails::init();
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
			esc_url( admin_url( 'admin.php?page=' . SettingsPage::SLUG ) ),
			esc_html__( 'Settings', 'core-blueprint-docs' )
		);
		return $links;
	}
}
