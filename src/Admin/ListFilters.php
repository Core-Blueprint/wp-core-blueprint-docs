<?php
declare(strict_types=1);

namespace CB\Docs\Admin;

use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

final class ListFilters {
	public static function init(): void {
		add_action( 'restrict_manage_posts', [ __CLASS__, 'render' ], 10, 2 );
	}

	public static function render( string $post_type, string $which ): void {
		if ( PostType::TYPE !== $post_type || 'top' !== $which ) {
			return;
		}

		self::taxonomy_dropdown(
			Taxonomies::CATEGORY,
			__( 'All Doc Categories', 'core-blueprint-docs' ),
			true
		);
		self::taxonomy_dropdown(
			Taxonomies::TAG,
			__( 'All Doc Tags', 'core-blueprint-docs' ),
			false
		);
	}

	private static function taxonomy_dropdown( string $taxonomy, string $all_label, bool $hierarchical ): void {
		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_object instanceof \WP_Taxonomy ) {
			return;
		}

		$selected = isset( $_GET[ $taxonomy ] )
			? sanitize_title( (string) wp_unslash( $_GET[ $taxonomy ] ) )
			: '';

		wp_dropdown_categories(
			[
				'show_option_all' => $all_label,
				'taxonomy'        => $taxonomy,
				'name'            => $taxonomy,
				'orderby'         => 'name',
				'selected'        => $selected,
				'hierarchical'    => $hierarchical,
				'show_count'      => false,
				'hide_empty'      => false,
				'value_field'     => 'slug',
			]
		);
	}
}
