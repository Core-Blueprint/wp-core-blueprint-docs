<?php
declare(strict_types=1);

namespace CB\Docs\Frontend;

defined( 'ABSPATH' ) || exit;

final class Assets {
	private const SEARCH_HANDLE = 'cb-docs-search';

	public static function enqueue_search(): void {
		wp_enqueue_style(
			self::SEARCH_HANDLE,
			CB_DOCS_URL . 'assets/css/docs-search.css',
			[],
			CB_DOCS_VERSION
		);

		wp_enqueue_script(
			self::SEARCH_HANDLE,
			CB_DOCS_URL . 'assets/js/docs-search.js',
			[],
			CB_DOCS_VERSION,
			true
		);
	}
}
