<?php
declare(strict_types=1);

namespace CB\Docs\Integration\Editors\Gutenberg;

use CB\Docs\Content\PostType;
use CB\Docs\Frontend\Components\Breadcrumbs;
use CB\Docs\Frontend\Components\DocumentList;
use CB\Docs\Frontend\Components\DocumentMeta;
use CB\Docs\Frontend\Components\Navigation;
use CB\Docs\Frontend\Components\Search;
use CB\Docs\Integration\Preferences;

defined( 'ABSPATH' ) || exit;

final class Registry {
	private const EDITOR_HANDLE = 'cb-docs-block-editor';

	/** @var array<string,string> */
	private const BLOCKS = [
		'document-list' => 'render_document_list',
		'navigation'    => 'render_navigation',
		'search'        => 'render_search',
		'breadcrumbs'   => 'render_breadcrumbs',
		'document-meta' => 'render_document_meta',
	];

	public static function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		self::register_editor_script();

		foreach ( self::BLOCKS as $directory => $callback ) {
			$path = __DIR__ . '/blocks/' . $directory;
			$metadata_file = $path . '/block.json';
			if ( ! is_readable( $metadata_file ) ) {
				continue;
			}

			$metadata = json_decode( (string) file_get_contents( $metadata_file ), true );
			$supports = is_array( $metadata ) && isset( $metadata['supports'] ) && is_array( $metadata['supports'] )
				? $metadata['supports']
				: [];
			$supports['inserter'] = Preferences::gutenberg_enabled();

			register_block_type(
				$path,
				[
					'render_callback' => [ self::class, $callback ],
					'supports'        => $supports,
				]
			);
		}
	}

	/** @param array<string,mixed> $attributes */
	public static function render_document_list( array $attributes ): string {
		return self::wrap(
			'document-list',
			DocumentList::render( [
				'category'     => $attributes['category'] ?? '',
				'tag'          => $attributes['tag'] ?? '',
				'limit'        => $attributes['limit'] ?? 20,
				'show_excerpt' => $attributes['showExcerpt'] ?? true,
			] )
		);
	}

	/** @param array<string,mixed> $attributes */
	public static function render_navigation( array $attributes ): string {
		return self::wrap(
			'navigation',
			Navigation::render( [
				'category' => $attributes['category'] ?? '',
			] )
		);
	}

	/** @param array<string,mixed> $attributes */
	public static function render_search( array $attributes ): string {
		return self::wrap(
			'search',
			Search::render( [
				'placeholder'   => $attributes['placeholder'] ?? __( 'Search documentation…', 'core-blueprint-docs' ),
				'limit'         => $attributes['limit'] ?? 20,
				'min_chars'     => $attributes['minChars'] ?? 2,
				'show_excerpt'  => $attributes['showExcerpt'] ?? true,
				'show_category' => $attributes['showCategory'] ?? true,
				'category'      => $attributes['category'] ?? '',
				'tag'           => $attributes['tag'] ?? '',
			] )
		);
	}

	/** @param array<string,mixed> $attributes */
	public static function render_breadcrumbs( array $attributes, string $content, \WP_Block $block ): string {
		unset( $attributes, $content );

		return self::wrap(
			'breadcrumbs',
			Breadcrumbs::render( [
				'post_id' => self::docs_context_post_id( $block ),
			] )
		);
	}

	/** @param array<string,mixed> $attributes */
	public static function render_document_meta( array $attributes, string $content, \WP_Block $block ): string {
		unset( $content );
		$document_id = absint( $attributes['documentId'] ?? 0 );
		if ( 0 === $document_id ) {
			$document_id = self::docs_context_post_id( $block );
		}

		return self::wrap(
			'document-meta',
			DocumentMeta::render( [ 'id' => $document_id ] )
		);
	}

	private static function register_editor_script(): void {
		wp_register_script(
			self::EDITOR_HANDLE,
			CB_DOCS_URL . 'assets/js/gutenberg-blocks.js',
			[
				'wp-blocks',
				'wp-block-editor',
				'wp-components',
				'wp-element',
				'wp-server-side-render',
			],
			CB_DOCS_VERSION,
			true
		);

		wp_localize_script(
			self::EDITOR_HANDLE,
			'cbDocsBlocks',
			[
				'labels' => [
					'contentSettings' => __( 'Content settings', 'core-blueprint-docs' ),
					'categorySlug'    => __( 'Category slug', 'core-blueprint-docs' ),
					'tagSlug'         => __( 'Tag slug', 'core-blueprint-docs' ),
					'resultLimit'     => __( 'Result limit', 'core-blueprint-docs' ),
					'showExcerpt'     => __( 'Show excerpts', 'core-blueprint-docs' ),
					'placeholder'     => __( 'Placeholder', 'core-blueprint-docs' ),
					'minimumChars'    => __( 'Minimum characters', 'core-blueprint-docs' ),
					'showCategory'    => __( 'Show result category', 'core-blueprint-docs' ),
					'documentId'      => __( 'Document ID', 'core-blueprint-docs' ),
					'documentIdHelp'  => __( 'Leave at 0 to use the current Docs document.', 'core-blueprint-docs' ),
				],
			]
		);
	}

	private static function docs_context_post_id( \WP_Block $block ): int {
		$post_type = (string) ( $block->context['postType'] ?? '' );
		if ( PostType::TYPE !== $post_type ) {
			return 0;
		}

		return absint( $block->context['postId'] ?? 0 );
	}

	private static function wrap( string $component, string $content ): string {
		if ( '' === $content ) {
			return '';
		}

		$attributes = get_block_wrapper_attributes( [
			'class' => 'cb-docs-block cb-docs-block--' . sanitize_html_class( $component ),
		] );

		return '<div ' . $attributes . '>' . $content . '</div>';
	}
}
