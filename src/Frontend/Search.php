<?php
declare(strict_types=1);

namespace CB\Docs\Frontend;

use CB\Docs\Frontend\Queries\Documents;

defined( 'ABSPATH' ) || exit;

final class Search {
	private const DEFAULT_LIMIT = 20;
	private const MAX_LIMIT     = 50;

	/**
	 * Bounded authorization-aware Docs search for integrations and builders.
	 *
	 * @param array{category?:mixed,tag?:mixed,include_ids?:mixed} $args
	 * @return array{items:array<int,array<string,mixed>>,page:int,per_page:int,total:int,total_pages:int}
	 */
	public static function documents( string $search, int $limit = self::DEFAULT_LIMIT, array $args = [] ): array {
		$search = sanitize_text_field( trim( $search ) );
		$limit  = max( 1, min( self::MAX_LIMIT, $limit ) );
		if ( '' === $search ) {
			return [
				'items'       => [],
				'page'        => 1,
				'per_page'    => $limit,
				'total'       => 0,
				'total_pages' => 0,
			];
		}

		$args['search']   = $search;
		$args['page']     = 1;
		$args['per_page'] = $limit;
		return Documents::query( $args );
	}
}
