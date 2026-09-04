<?php
declare(strict_types=1);

namespace CB\Docs\Integration\Builders\Bricks;

use CB\Docs\Frontend\Queries\Documents;
use CB\Docs\Frontend\Search;

defined( 'ABSPATH' ) || exit;

final class Queries {
	public const DOCUMENTS      = 'cb_docs_documents';
	public const SEARCH_RESULTS = 'cb_docs_search_results';

	public static function init(): void {
		add_filter( 'bricks/setup/control_options', [ self::class, 'register_query_types' ] );
		add_filter( 'bricks/query/run', [ self::class, 'run' ], 10, 2 );
		add_filter( 'bricks/query/loop_object_id', [ self::class, 'loop_object_id' ], 10, 3 );
	}

	/** @param array<string,mixed> $options @return array<string,mixed> */
	public static function register_query_types( array $options ): array {
		if ( ! isset( $options['queryTypes'] ) || ! is_array( $options['queryTypes'] ) ) {
			$options['queryTypes'] = [];
		}

		$options['queryTypes'][ self::DOCUMENTS ]      = __( 'Docs: Documents', 'core-blueprint-docs' );
		$options['queryTypes'][ self::SEARCH_RESULTS ] = __( 'Docs: Search Results', 'core-blueprint-docs' );
		return $options;
	}

	/** @param array<int,mixed> $results @return array<int,mixed> */
	public static function run( array $results, mixed $query_obj ): array {
		$object_type = self::object_type( $query_obj );
		if ( self::DOCUMENTS === $object_type ) {
			$query = Documents::query( self::query_args( $query_obj ) );
			return $query['items'];
		}

		if ( self::SEARCH_RESULTS === $object_type ) {
			$search = isset( $_GET['cb_docs_q'] )
				? sanitize_text_field( (string) wp_unslash( $_GET['cb_docs_q'] ) )
				: ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public search input.
			$query = Search::documents( $search, min( 50, self::limit( $query_obj, 20 ) ) );
			return $query['items'];
		}

		return $results;
	}

	public static function loop_object_id( mixed $object_id, mixed $object, mixed $query_id ): mixed {
		if ( ! is_array( $object ) || ! isset( $object['id'] ) ) {
			return $object_id;
		}
		if ( ! class_exists( '\\Bricks\\Query' ) || ! method_exists( '\\Bricks\\Query', 'get_query_object_type' ) ) {
			return $object_id;
		}

		$type = (string) \Bricks\Query::get_query_object_type( $query_id );
		if ( ! in_array( $type, [ self::DOCUMENTS, self::SEARCH_RESULTS ], true ) ) {
			return $object_id;
		}

		$id = absint( $object['id'] );
		return $id > 0 ? $id : $object_id;
	}

	/** @return array<string,mixed> */
	private static function query_args( mixed $query_obj ): array {
		$settings = self::settings( $query_obj );
		return [
			'category'    => self::scalar( $settings['cb_docs_category'] ?? $settings['category'] ?? '' ),
			'tag'         => self::scalar( $settings['cb_docs_tag'] ?? $settings['tag'] ?? '' ),
			'search'      => self::scalar( $settings['cb_docs_search'] ?? $settings['search'] ?? $settings['s'] ?? '' ),
			'include_ids' => $settings['include_ids'] ?? $settings['post__in'] ?? [],
			'page'        => self::page( $query_obj ),
			'per_page'    => self::limit( $query_obj, 20 ),
		];
	}

	private static function object_type( mixed $query_obj ): string {
		return is_object( $query_obj ) && isset( $query_obj->object_type ) ? (string) $query_obj->object_type : '';
	}

	/** @return array<string,mixed> */
	private static function settings( mixed $query_obj ): array {
		return is_object( $query_obj ) && isset( $query_obj->settings ) && is_array( $query_obj->settings )
			? $query_obj->settings
			: [];
	}

	private static function limit( mixed $query_obj, int $default ): int {
		$settings = self::settings( $query_obj );
		$raw      = $settings['posts_per_page'] ?? $settings['count'] ?? $settings['per_page'] ?? $default;
		return max( 1, min( 100, (int) $raw ) );
	}

	private static function page( mixed $query_obj ): int {
		$settings = self::settings( $query_obj );
		$raw      = $settings['paged'] ?? $settings['page'] ?? 1;
		return max( 1, (int) $raw );
	}

	private static function scalar( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}
}
