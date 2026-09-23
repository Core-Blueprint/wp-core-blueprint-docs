<?php
declare(strict_types=1);

namespace CB\Docs\Integration\Builders\Bricks;

use CB\Docs\Structure\Numbering;

defined( 'ABSPATH' ) || exit;

final class DynamicData {
	private const GROUP = 'Core Blueprint Docs';
	private const NUMBER_TAG = 'cb_docs_number';

	/** @var array<string,string> tag name => public document field */
	private const FIELDS = [
		'cb_docs_id'            => 'id',
		'cb_docs_title'         => 'title',
		'cb_docs_url'           => 'permalink',
		'cb_docs_excerpt'       => 'excerpt',
		'cb_docs_content'       => 'content',
		'cb_docs_categories'    => 'categories',
		'cb_docs_tags'          => 'tags',
		'cb_docs_subtitle'      => 'subtitle',
		'cb_docs_status'        => 'documentation_status',
		'cb_docs_version'       => 'version',
		'cb_docs_last_reviewed' => 'last_reviewed',
		'cb_docs_featured'      => 'featured',
	];

	public static function init(): void {
		add_filter( 'bricks/dynamic_tags_list', [ self::class, 'register_tags' ] );
		add_filter( 'bricks/dynamic_data/render_tag', [ self::class, 'render_tag' ], 20, 3 );
		add_filter( 'bricks/dynamic_data/render_content', [ self::class, 'render_content' ], 20, 3 );
		add_filter( 'bricks/frontend/render_data', [ self::class, 'render_content' ], 20, 3 );
	}

	/** @param array<int,array<string,mixed>> $tags @return array<int,array<string,mixed>> */
	public static function register_tags( array $tags ): array {
		foreach ( self::labels() as $name => $label ) {
			$tags[] = [
				'name'  => '{' . $name . '}',
				'label' => $label,
				'group' => self::GROUP,
			];
		}
		return $tags;
	}

	public static function render_tag( mixed $tag, mixed $post = null, string $context = 'text' ): mixed {
		unset( $post, $context );
		if ( ! is_string( $tag ) ) {
			return $tag;
		}

		$name = trim( $tag, '{}' );
		if ( ! self::supports( $name ) ) {
			return $tag;
		}

		$value = self::value( $name );
		return is_wp_error( $value ) ? '' : self::string_value( $value );
	}

	public static function render_content( mixed $content, mixed $post = null, string $context = 'text' ): mixed {
		unset( $post, $context );
		if ( ! is_string( $content ) || false === strpos( $content, '{cb_docs_' ) ) {
			return $content;
		}

		foreach ( self::names() as $name ) {
			$needle = '{' . $name . '}';
			if ( false === strpos( $content, $needle ) ) {
				continue;
			}

			$value   = self::value( $name );
			$content = str_replace( $needle, is_wp_error( $value ) ? '' : self::string_value( $value ), $content );
		}

		return $content;
	}

	/** @return array<string,string> */
	private static function labels(): array {
		return [
			'cb_docs_id'            => __( 'Doc ID', 'core-blueprint-docs' ),
			'cb_docs_number'        => __( 'Doc number', 'core-blueprint-docs' ),
			'cb_docs_title'         => __( 'Doc title', 'core-blueprint-docs' ),
			'cb_docs_url'           => __( 'Doc URL', 'core-blueprint-docs' ),
			'cb_docs_excerpt'       => __( 'Doc excerpt', 'core-blueprint-docs' ),
			'cb_docs_content'       => __( 'Doc content', 'core-blueprint-docs' ),
			'cb_docs_categories'    => __( 'Doc categories', 'core-blueprint-docs' ),
			'cb_docs_tags'          => __( 'Doc tags', 'core-blueprint-docs' ),
			'cb_docs_subtitle'      => __( 'Doc subtitle', 'core-blueprint-docs' ),
			'cb_docs_status'        => __( 'Documentation status', 'core-blueprint-docs' ),
			'cb_docs_version'       => __( 'Doc version', 'core-blueprint-docs' ),
			'cb_docs_last_reviewed' => __( 'Last reviewed', 'core-blueprint-docs' ),
			'cb_docs_featured'      => __( 'Featured Doc', 'core-blueprint-docs' ),
		];
	}

	/** @return string[] */
	private static function names(): array {
		return [ ...array_keys( self::FIELDS ), self::NUMBER_TAG ];
	}

	private static function supports( string $name ): bool {
		return self::NUMBER_TAG === $name || isset( self::FIELDS[ $name ] );
	}

	private static function value( string $name ): mixed {
		if ( self::NUMBER_TAG === $name ) {
			$document_id = DocumentContext::identifier();
			return null === $document_id ? null : Numbering::document( $document_id );
		}

		return isset( self::FIELDS[ $name ] )
			? DocumentContext::value( self::FIELDS[ $name ] )
			: null;
	}

	private static function string_value( mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}
		if ( is_array( $value ) ) {
			$labels = [];
			foreach ( $value as $item ) {
				if ( is_array( $item ) && isset( $item['name'] ) && is_scalar( $item['name'] ) ) {
					$labels[] = (string) $item['name'];
				}
			}
			return implode( ', ', $labels );
		}
		return '';
	}
}
