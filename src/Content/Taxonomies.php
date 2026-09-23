<?php
declare(strict_types=1);

namespace CB\Docs\Content;

use CB\Docs\Settings;

defined( 'ABSPATH' ) || exit;

final class Taxonomies {
	public const CATEGORY = 'cb_doc_category';
	public const TAG      = 'cb_doc_tag';

	public static function register(): void {
		$hierarchy = Settings::hierarchy_enabled();
		$base = Settings::rewrite_base();

		register_taxonomy(
			self::CATEGORY,
			[ PostType::TYPE ],
			[
				'labels' => [
					'name'              => __( 'Doc Categories', 'core-blueprint-docs' ),
					'singular_name'     => __( 'Doc Category', 'core-blueprint-docs' ),
					'search_items'      => __( 'Search Doc Categories', 'core-blueprint-docs' ),
					'all_items'         => __( 'All Doc Categories', 'core-blueprint-docs' ),
					'parent_item'       => __( 'Parent Doc Category', 'core-blueprint-docs' ),
					'parent_item_colon' => __( 'Parent Doc Category:', 'core-blueprint-docs' ),
					'edit_item'         => __( 'Edit Doc Category', 'core-blueprint-docs' ),
					'update_item'       => __( 'Update Doc Category', 'core-blueprint-docs' ),
					'add_new_item'      => __( 'Add New Doc Category', 'core-blueprint-docs' ),
					'new_item_name'     => __( 'New Doc Category Name', 'core-blueprint-docs' ),
					'menu_name'         => __( 'Categories', 'core-blueprint-docs' ),
				],
				'public'            => true,
				'publicly_queryable'=> true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
				'rewrite'           => $hierarchy ? false : [
					'slug'         => 'docs-category',
					'with_front'   => false,
					'hierarchical' => true,
				],
			]
		);

		register_taxonomy(
			self::TAG,
			[ PostType::TYPE ],
			[
				'labels' => [
					'name'                       => __( 'Doc Tags', 'core-blueprint-docs' ),
					'singular_name'              => __( 'Doc Tag', 'core-blueprint-docs' ),
					'search_items'               => __( 'Search Doc Tags', 'core-blueprint-docs' ),
					'popular_items'              => __( 'Popular Doc Tags', 'core-blueprint-docs' ),
					'all_items'                  => __( 'All Doc Tags', 'core-blueprint-docs' ),
					'edit_item'                  => __( 'Edit Doc Tag', 'core-blueprint-docs' ),
					'update_item'                => __( 'Update Doc Tag', 'core-blueprint-docs' ),
					'add_new_item'               => __( 'Add New Doc Tag', 'core-blueprint-docs' ),
					'new_item_name'              => __( 'New Doc Tag Name', 'core-blueprint-docs' ),
					'separate_items_with_commas' => __( 'Separate doc tags with commas', 'core-blueprint-docs' ),
					'add_or_remove_items'        => __( 'Add or remove doc tags', 'core-blueprint-docs' ),
					'choose_from_most_used'      => __( 'Choose from the most used doc tags', 'core-blueprint-docs' ),
					'menu_name'                  => __( 'Tags', 'core-blueprint-docs' ),
				],
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'hierarchical'       => false,
				'rewrite'            => [
					'slug'       => $hierarchy ? $base . '/tag' : 'docs-tag',
					'with_front' => false,
				],
			]
		);
	}
}
