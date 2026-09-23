<?php
declare(strict_types=1);

namespace CB\Docs\Permalinks;

use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;
use CB\Docs\Settings;

defined( 'ABSPATH' ) || exit;

final class Router {
	private const QUERY_ROUTE = 'cb_docs_route';
	private const QUERY_PATH  = 'cb_docs_route_path';

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_rules' ], 15 );
		add_filter( 'query_vars', [ __CLASS__, 'query_vars' ] );
		add_filter( 'request', [ __CLASS__, 'resolve_request' ] );
		add_filter( 'post_type_link', [ __CLASS__, 'document_link' ], 20, 2 );
		add_filter( 'term_link', [ __CLASS__, 'term_link' ], 20, 3 );
		add_filter( 'get_canonical_url', [ __CLASS__, 'canonical_url' ], 20, 2 );
		add_action( 'template_redirect', [ __CLASS__, 'redirect_legacy' ], 1 );
	}

	public static function register_rules(): void {
		if ( ! Settings::hierarchy_enabled() ) {
			return;
		}

		$base = preg_quote( Settings::rewrite_base(), '#' );

		add_rewrite_rule(
			'^docs-tag/([^/]+)/?$',
			'index.php?' . self::QUERY_ROUTE . '=legacy-tag&' . self::QUERY_PATH . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^docs-category/(.+?)/?$',
			'index.php?' . self::QUERY_ROUTE . '=legacy-category&' . self::QUERY_PATH . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^' . $base . '/(?:tag|document)/?
		add_rewrite_rule(
			'^' . $base . '/tag/([^/]+)/?$',
			'index.php?' . self::QUERY_ROUTE . '=tag&' . self::QUERY_PATH . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^' . $base . '/(?!(?:tag|document)(?:/|$))(.+?)/?$',
			'index.php?' . self::QUERY_ROUTE . '=dynamic&' . self::QUERY_PATH . '=$matches[1]',
			'top'
		);
	}

	/** @param string[] $vars @return string[] */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_ROUTE;
		$vars[] = self::QUERY_PATH;
		return array_values( array_unique( $vars ) );
	}

	/** @param array<string,mixed> $vars @return array<string,mixed> */
	public static function resolve_request( array $vars ): array {
		if ( ! Settings::hierarchy_enabled() ) {
			return $vars;
		}

		$route = sanitize_key( (string) ( $vars[ self::QUERY_ROUTE ] ?? '' ) );
		$path = self::sanitize_path( (string) ( $vars[ self::QUERY_PATH ] ?? '' ) );
		if ( '' === $route || '' === $path ) {
			return $vars;
		}

		switch ( $route ) {
			case 'tag':
				return self::taxonomy_request( $vars, Taxonomies::TAG, $path );

			case 'document':
				return self::document_request( $vars, $path, 'document' );

			case 'legacy-simple':
				return self::document_request( $vars, $path, 'legacy-simple' );

			case 'dynamic':
				return self::dynamic_request( $vars, $path );

			case 'legacy-category':
			case 'legacy-tag':
				return $vars;
		}

		return $vars;
	}

	public static function document_link( string $url, \WP_Post $post ): string {
		if ( ! Settings::hierarchy_enabled() || PostType::TYPE !== $post->post_type ) {
			return $url;
		}

		return CanonicalPath::document_url( (int) $post->ID ) ?? $url;
	}

	public static function term_link( string $url, \WP_Term $term, string $taxonomy ): string {
		if ( ! Settings::hierarchy_enabled() ) {
			return $url;
		}

		if ( Taxonomies::CATEGORY === $taxonomy ) {
			return CanonicalPath::category_url( (int) $term->term_id ) ?? $url;
		}
		if ( Taxonomies::TAG === $taxonomy ) {
			return CanonicalPath::tag_url( (string) $term->slug );
		}

		return $url;
	}

	public static function canonical_url( string|false $url, \WP_Post $post ): string|false {
		if ( ! Settings::hierarchy_enabled() || PostType::TYPE !== $post->post_type ) {
			return $url;
		}

		return CanonicalPath::document_url( (int) $post->ID ) ?? $url;
	}

	public static function redirect_legacy(): void {
		if ( ! Settings::hierarchy_enabled() ) {
			return;
		}

		$route = sanitize_key( (string) get_query_var( self::QUERY_ROUTE, '' ) );
		$path = self::sanitize_path( (string) get_query_var( self::QUERY_PATH, '' ) );
		if ( '' === $route || '' === $path ) {
			return;
		}

		if ( 'legacy-category' === $route ) {
			$index = Catalog::index();
			$term_id = (int) ( $index['category_by_path'][ $path ] ?? 0 );
			$url = $term_id > 0 ? CanonicalPath::category_url( $term_id ) : null;
			self::redirect_to( $url );
			return;
		}

		if ( 'legacy-tag' === $route ) {
			$term = get_term_by( 'slug', $path, Taxonomies::TAG );
			$url = $term instanceof \WP_Term ? CanonicalPath::tag_url( (string) $term->slug ) : null;
			self::redirect_to( $url );
			return;
		}

		if ( 'legacy-simple' === $route && is_singular( PostType::TYPE ) ) {
			$url = CanonicalPath::document_url( (int) get_queried_object_id() );
			self::redirect_to( $url );
			return;
		}

		if ( 'document' === $route && is_singular( PostType::TYPE ) ) {
			$post_id = (int) get_queried_object_id();
			$index = Catalog::index();
			$fallback = ! empty( $index['documents'][ $post_id ]['fallback'] );
			if ( ! $fallback ) {
				self::redirect_to( CanonicalPath::document_url( $post_id ) );
			}
		}
	}

	/** @param array<string,mixed> $vars @return array<string,mixed> */
	private static function dynamic_request( array $vars, string $path ): array {
		$index = Catalog::index();

		$term_id = (int) ( $index['category_by_path'][ $path ] ?? 0 );
		if ( $term_id > 0 ) {
			$vars['post_type'] = PostType::TYPE;
			$vars['tax_query'] = [
				[
					'taxonomy' => Taxonomies::CATEGORY,
					'field'    => 'term_id',
					'terms'    => [ $term_id ],
				],
			];
			$vars[ self::QUERY_ROUTE ] = 'category';
			return $vars;
		}

		$post_id = (int) ( $index['document_by_path'][ $path ] ?? 0 );
		if ( $post_id > 0 ) {
			$vars['post_type'] = PostType::TYPE;
			$vars['p'] = $post_id;
			$vars[ self::QUERY_ROUTE ] = 'document-canonical';
			return $vars;
		}

		if ( ! str_contains( $path, '/' ) ) {
			$post = get_page_by_path( $path, OBJECT, PostType::TYPE );
			if ( $post instanceof \WP_Post && ( 'publish' === $post->post_status || current_user_can( 'read_post', (int) $post->ID ) ) ) {
				$vars['post_type'] = PostType::TYPE;
				$vars['p'] = (int) $post->ID;
				$vars[ self::QUERY_ROUTE ] = 'legacy-simple';
			}
		}

		return $vars;
	}

	/** @param array<string,mixed> $vars @return array<string,mixed> */
	private static function document_request( array $vars, string $slug, string $route ): array {
		$post = get_page_by_path( $slug, OBJECT, PostType::TYPE );
		if ( ! $post instanceof \WP_Post ) {
			return $vars;
		}

		$vars['post_type'] = PostType::TYPE;
		$vars['p'] = (int) $post->ID;
		$vars[ self::QUERY_ROUTE ] = $route;
		return $vars;
	}

	/** @param array<string,mixed> $vars @return array<string,mixed> */
	private static function taxonomy_request( array $vars, string $taxonomy, string $slug ): array {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( ! $term instanceof \WP_Term ) {
			return $vars;
		}

		$vars['post_type'] = PostType::TYPE;
		$vars['tax_query'] = [
			[
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => [ (int) $term->term_id ],
			],
		];
		return $vars;
	}

	private static function sanitize_path( string $path ): string {
		$segments = array_values( array_filter( array_map(
			static fn( string $segment ): string => sanitize_title( rawurldecode( $segment ) ),
			explode( '/', trim( $path, '/' ) )
		) ) );

		return implode( '/', $segments );
	}

	private static function redirect_to( ?string $url ): void {
		if ( ! is_string( $url ) || '' === $url ) {
			return;
		}

		$current_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$current_path = (string) wp_parse_url( $current_path, PHP_URL_PATH );
		$target_path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( '' !== $current_path && untrailingslashit( $current_path ) === untrailingslashit( $target_path ) ) {
			return;
		}

		wp_safe_redirect( $url, 301, 'Core Blueprint Docs' );
		exit;
	}
}
,
			'index.php?' . self::QUERY_ROUTE . '=legacy-simple&' . self::QUERY_PATH . '=$matches[0]',
			'top'
		);
		add_rewrite_rule(
			'^' . $base . '/document/([^/]+)/?
		add_rewrite_rule(
			'^' . $base . '/tag/([^/]+)/?$',
			'index.php?' . self::QUERY_ROUTE . '=tag&' . self::QUERY_PATH . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^' . $base . '/(?!(?:tag|document)(?:/|$))(.+?)/?$',
			'index.php?' . self::QUERY_ROUTE . '=dynamic&' . self::QUERY_PATH . '=$matches[1]',
			'top'
		);
	}

	/** @param string[] $vars @return string[] */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_ROUTE;
		$vars[] = self::QUERY_PATH;
		return array_values( array_unique( $vars ) );
	}

	/** @param array<string,mixed> $vars @return array<string,mixed> */
	public static function resolve_request( array $vars ): array {
		if ( ! Settings::hierarchy_enabled() ) {
			return $vars;
		}

		$route = sanitize_key( (string) ( $vars[ self::QUERY_ROUTE ] ?? '' ) );
		$path = self::sanitize_path( (string) ( $vars[ self::QUERY_PATH ] ?? '' ) );
		if ( '' === $route || '' === $path ) {
			return $vars;
		}

		switch ( $route ) {
			case 'tag':
				return self::taxonomy_request( $vars, Taxonomies::TAG, $path );

			case 'document':
				return self::document_request( $vars, $path, 'document' );

			case 'dynamic':
				return self::dynamic_request( $vars, $path );

			case 'legacy-category':
			case 'legacy-tag':
				return $vars;
		}

		return $vars;
	}

	public static function document_link( string $url, \WP_Post $post ): string {
		if ( ! Settings::hierarchy_enabled() || PostType::TYPE !== $post->post_type ) {
			return $url;
		}

		return CanonicalPath::document_url( (int) $post->ID ) ?? $url;
	}

	public static function term_link( string $url, \WP_Term $term, string $taxonomy ): string {
		if ( ! Settings::hierarchy_enabled() ) {
			return $url;
		}

		if ( Taxonomies::CATEGORY === $taxonomy ) {
			return CanonicalPath::category_url( (int) $term->term_id ) ?? $url;
		}
		if ( Taxonomies::TAG === $taxonomy ) {
			return CanonicalPath::tag_url( (string) $term->slug );
		}

		return $url;
	}

	public static function canonical_url( string|false $url, \WP_Post $post ): string|false {
		if ( ! Settings::hierarchy_enabled() || PostType::TYPE !== $post->post_type ) {
			return $url;
		}

		return CanonicalPath::document_url( (int) $post->ID ) ?? $url;
	}

	public static function redirect_legacy(): void {
		if ( ! Settings::hierarchy_enabled() ) {
			return;
		}

		$route = sanitize_key( (string) get_query_var( self::QUERY_ROUTE, '' ) );
		$path = self::sanitize_path( (string) get_query_var( self::QUERY_PATH, '' ) );
		if ( '' === $route || '' === $path ) {
			return;
		}

		if ( 'legacy-category' === $route ) {
			$index = Catalog::index();
			$term_id = (int) ( $index['category_by_path'][ $path ] ?? 0 );
			$url = $term_id > 0 ? CanonicalPath::category_url( $term_id ) : null;
			self::redirect_to( $url );
			return;
		}

		if ( 'legacy-tag' === $route ) {
			$term = get_term_by( 'slug', $path, Taxonomies::TAG );
			$url = $term instanceof \WP_Term ? CanonicalPath::tag_url( (string) $term->slug ) : null;
			self::redirect_to( $url );
			return;
		}

		if ( 'legacy-simple' === $route && is_singular( PostType::TYPE ) ) {
			$url = CanonicalPath::document_url( (int) get_queried_object_id() );
			self::redirect_to( $url );
			return;
		}

		if ( 'document' === $route && is_singular( PostType::TYPE ) ) {
			$post_id = (int) get_queried_object_id();
			$index = Catalog::index();
			$fallback = ! empty( $index['documents'][ $post_id ]['fallback'] );
			if ( ! $fallback ) {
				self::redirect_to( CanonicalPath::document_url( $post_id ) );
			}
		}
	}

	/** @param array<string,mixed> $vars @return array<string,mixed> */
	private static function dynamic_request( array $vars, string $path ): array {
		$index = Catalog::index();

		$term_id = (int) ( $index['category_by_path'][ $path ] ?? 0 );
		if ( $term_id > 0 ) {
			$vars['post_type'] = PostType::TYPE;
			$vars['tax_query'] = [
				[
					'taxonomy' => Taxonomies::CATEGORY,
					'field'    => 'term_id',
					'terms'    => [ $term_id ],
				],
			];
			$vars[ self::QUERY_ROUTE ] = 'category';
			return $vars;
		}

		$post_id = (int) ( $index['document_by_path'][ $path ] ?? 0 );
		if ( $post_id > 0 ) {
			$vars['post_type'] = PostType::TYPE;
			$vars['p'] = $post_id;
			$vars[ self::QUERY_ROUTE ] = 'document-canonical';
			return $vars;
		}

		if ( ! str_contains( $path, '/' ) ) {
			$post = get_page_by_path( $path, OBJECT, PostType::TYPE );
			if ( $post instanceof \WP_Post && ( 'publish' === $post->post_status || current_user_can( 'read_post', (int) $post->ID ) ) ) {
				$vars['post_type'] = PostType::TYPE;
				$vars['p'] = (int) $post->ID;
				$vars[ self::QUERY_ROUTE ] = 'legacy-simple';
			}
		}

		return $vars;
	}

	/** @param array<string,mixed> $vars @return array<string,mixed> */
	private static function document_request( array $vars, string $slug, string $route ): array {
		$post = get_page_by_path( $slug, OBJECT, PostType::TYPE );
		if ( ! $post instanceof \WP_Post ) {
			return $vars;
		}

		$vars['post_type'] = PostType::TYPE;
		$vars['p'] = (int) $post->ID;
		$vars[ self::QUERY_ROUTE ] = $route;
		return $vars;
	}

	/** @param array<string,mixed> $vars @return array<string,mixed> */
	private static function taxonomy_request( array $vars, string $taxonomy, string $slug ): array {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( ! $term instanceof \WP_Term ) {
			return $vars;
		}

		$vars['post_type'] = PostType::TYPE;
		$vars['tax_query'] = [
			[
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => [ (int) $term->term_id ],
			],
		];
		return $vars;
	}

	private static function sanitize_path( string $path ): string {
		$segments = array_values( array_filter( array_map(
			static fn( string $segment ): string => sanitize_title( rawurldecode( $segment ) ),
			explode( '/', trim( $path, '/' ) )
		) ) );

		return implode( '/', $segments );
	}

	private static function redirect_to( ?string $url ): void {
		if ( ! is_string( $url ) || '' === $url ) {
			return;
		}

		wp_safe_redirect( $url, 301, 'Core Blueprint Docs' );
		exit;
	}
}
,
			'index.php?' . self::QUERY_ROUTE . '=document&' . self::QUERY_PATH . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^' . $base . '/tag/([^/]+)/?$',
			'index.php?' . self::QUERY_ROUTE . '=tag&' . self::QUERY_PATH . '=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^' . $base . '/(?!(?:tag|document)(?:/|$))(.+?)/?$',
			'index.php?' . self::QUERY_ROUTE . '=dynamic&' . self::QUERY_PATH . '=$matches[1]',
			'top'
		);
	}

	/** @param string[] $vars @return string[] */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY_ROUTE;
		$vars[] = self::QUERY_PATH;
		return array_values( array_unique( $vars ) );
	}

	/** @param array<string,mixed> $vars @return array<string,mixed> */
	public static function resolve_request( array $vars ): array {
		if ( ! Settings::hierarchy_enabled() ) {
			return $vars;
		}

		$route = sanitize_key( (string) ( $vars[ self::QUERY_ROUTE ] ?? '' ) );
		$path = self::sanitize_path( (string) ( $vars[ self::QUERY_PATH ] ?? '' ) );
		if ( '' === $route || '' === $path ) {
			return $vars;
		}

		switch ( $route ) {
			case 'tag':
				return self::taxonomy_request( $vars, Taxonomies::TAG, $path );

			case 'document':
				return self::document_request( $vars, $path, 'document' );

			case 'dynamic':
				return self::dynamic_request( $vars, $path );

			case 'legacy-category':
			case 'legacy-tag':
				return $vars;
		}

		return $vars;
	}

	public static function document_link( string $url, \WP_Post $post ): string {
		if ( ! Settings::hierarchy_enabled() || PostType::TYPE !== $post->post_type ) {
			return $url;
		}

		return CanonicalPath::document_url( (int) $post->ID ) ?? $url;
	}

	public static function term_link( string $url, \WP_Term $term, string $taxonomy ): string {
		if ( ! Settings::hierarchy_enabled() ) {
			return $url;
		}

		if ( Taxonomies::CATEGORY === $taxonomy ) {
			return CanonicalPath::category_url( (int) $term->term_id ) ?? $url;
		}
		if ( Taxonomies::TAG === $taxonomy ) {
			return CanonicalPath::tag_url( (string) $term->slug );
		}

		return $url;
	}

	public static function canonical_url( string|false $url, \WP_Post $post ): string|false {
		if ( ! Settings::hierarchy_enabled() || PostType::TYPE !== $post->post_type ) {
			return $url;
		}

		return CanonicalPath::document_url( (int) $post->ID ) ?? $url;
	}

	public static function redirect_legacy(): void {
		if ( ! Settings::hierarchy_enabled() ) {
			return;
		}

		$route = sanitize_key( (string) get_query_var( self::QUERY_ROUTE, '' ) );
		$path = self::sanitize_path( (string) get_query_var( self::QUERY_PATH, '' ) );
		if ( '' === $route || '' === $path ) {
			return;
		}

		if ( 'legacy-category' === $route ) {
			$index = Catalog::index();
			$term_id = (int) ( $index['category_by_path'][ $path ] ?? 0 );
			$url = $term_id > 0 ? CanonicalPath::category_url( $term_id ) : null;
			self::redirect_to( $url );
			return;
		}

		if ( 'legacy-tag' === $route ) {
			$term = get_term_by( 'slug', $path, Taxonomies::TAG );
			$url = $term instanceof \WP_Term ? CanonicalPath::tag_url( (string) $term->slug ) : null;
			self::redirect_to( $url );
			return;
		}

		if ( 'legacy-simple' === $route && is_singular( PostType::TYPE ) ) {
			$url = CanonicalPath::document_url( (int) get_queried_object_id() );
			self::redirect_to( $url );
			return;
		}

		if ( 'document' === $route && is_singular( PostType::TYPE ) ) {
			$post_id = (int) get_queried_object_id();
			$index = Catalog::index();
			$fallback = ! empty( $index['documents'][ $post_id ]['fallback'] );
			if ( ! $fallback ) {
				self::redirect_to( CanonicalPath::document_url( $post_id ) );
			}
		}
	}

	/** @param array<string,mixed> $vars @return array<string,mixed> */
	private static function dynamic_request( array $vars, string $path ): array {
		$index = Catalog::index();

		$term_id = (int) ( $index['category_by_path'][ $path ] ?? 0 );
		if ( $term_id > 0 ) {
			$vars['post_type'] = PostType::TYPE;
			$vars['tax_query'] = [
				[
					'taxonomy' => Taxonomies::CATEGORY,
					'field'    => 'term_id',
					'terms'    => [ $term_id ],
				],
			];
			$vars[ self::QUERY_ROUTE ] = 'category';
			return $vars;
		}

		$post_id = (int) ( $index['document_by_path'][ $path ] ?? 0 );
		if ( $post_id > 0 ) {
			$vars['post_type'] = PostType::TYPE;
			$vars['p'] = $post_id;
			$vars[ self::QUERY_ROUTE ] = 'document-canonical';
			return $vars;
		}

		if ( ! str_contains( $path, '/' ) ) {
			$post = get_page_by_path( $path, OBJECT, PostType::TYPE );
			if ( $post instanceof \WP_Post && ( 'publish' === $post->post_status || current_user_can( 'read_post', (int) $post->ID ) ) ) {
				$vars['post_type'] = PostType::TYPE;
				$vars['p'] = (int) $post->ID;
				$vars[ self::QUERY_ROUTE ] = 'legacy-simple';
			}
		}

		return $vars;
	}

	/** @param array<string,mixed> $vars @return array<string,mixed> */
	private static function document_request( array $vars, string $slug, string $route ): array {
		$post = get_page_by_path( $slug, OBJECT, PostType::TYPE );
		if ( ! $post instanceof \WP_Post ) {
			return $vars;
		}

		$vars['post_type'] = PostType::TYPE;
		$vars['p'] = (int) $post->ID;
		$vars[ self::QUERY_ROUTE ] = $route;
		return $vars;
	}

	/** @param array<string,mixed> $vars @return array<string,mixed> */
	private static function taxonomy_request( array $vars, string $taxonomy, string $slug ): array {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( ! $term instanceof \WP_Term ) {
			return $vars;
		}

		$vars['post_type'] = PostType::TYPE;
		$vars['tax_query'] = [
			[
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => [ (int) $term->term_id ],
			],
		];
		return $vars;
	}

	private static function sanitize_path( string $path ): string {
		$segments = array_values( array_filter( array_map(
			static fn( string $segment ): string => sanitize_title( rawurldecode( $segment ) ),
			explode( '/', trim( $path, '/' ) )
		) ) );

		return implode( '/', $segments );
	}

	private static function redirect_to( ?string $url ): void {
		if ( ! is_string( $url ) || '' === $url ) {
			return;
		}

		wp_safe_redirect( $url, 301, 'Core Blueprint Docs' );
		exit;
	}
}
