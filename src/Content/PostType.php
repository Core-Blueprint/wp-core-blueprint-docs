<?php
declare(strict_types=1);

namespace CB\Docs\Content;

defined( 'ABSPATH' ) || exit;

final class PostType {
	public const TYPE = 'cb_doc';

	public static function register(): void {
		register_post_type(
			self::TYPE,
			[
				'labels' => [
					'name'                     => __( 'Docs', 'core-blueprint-docs' ),
					'singular_name'            => __( 'Doc', 'core-blueprint-docs' ),
					'menu_name'                => __( 'Docs', 'core-blueprint-docs' ),
					'name_admin_bar'           => __( 'Doc', 'core-blueprint-docs' ),
					'add_new'                  => __( 'Add New', 'core-blueprint-docs' ),
					'add_new_item'             => __( 'Add New Doc', 'core-blueprint-docs' ),
					'new_item'                 => __( 'New Doc', 'core-blueprint-docs' ),
					'edit_item'                => __( 'Edit Doc', 'core-blueprint-docs' ),
					'view_item'                => __( 'View Doc', 'core-blueprint-docs' ),
					'view_items'               => __( 'View Docs', 'core-blueprint-docs' ),
					'all_items'                => __( 'All Docs', 'core-blueprint-docs' ),
					'search_items'             => __( 'Search Docs', 'core-blueprint-docs' ),
					'parent_item_colon'        => __( 'Parent Doc:', 'core-blueprint-docs' ),
					'not_found'                => __( 'No docs found.', 'core-blueprint-docs' ),
					'not_found_in_trash'       => __( 'No docs found in Trash.', 'core-blueprint-docs' ),
					'archives'                 => __( 'Docs Archives', 'core-blueprint-docs' ),
					'attributes'               => __( 'Doc Attributes', 'core-blueprint-docs' ),
					'featured_image'           => __( 'Featured image', 'core-blueprint-docs' ),
					'set_featured_image'       => __( 'Set featured image', 'core-blueprint-docs' ),
					'remove_featured_image'    => __( 'Remove featured image', 'core-blueprint-docs' ),
					'use_featured_image'       => __( 'Use as featured image', 'core-blueprint-docs' ),
					'filter_items_list'        => __( 'Filter docs list', 'core-blueprint-docs' ),
					'items_list_navigation'    => __( 'Docs list navigation', 'core-blueprint-docs' ),
					'items_list'               => __( 'Docs list', 'core-blueprint-docs' ),
					'item_published'           => __( 'Doc published.', 'core-blueprint-docs' ),
					'item_published_privately' => __( 'Doc published privately.', 'core-blueprint-docs' ),
					'item_reverted_to_draft'   => __( 'Doc reverted to draft.', 'core-blueprint-docs' ),
					'item_scheduled'           => __( 'Doc scheduled.', 'core-blueprint-docs' ),
					'item_updated'             => __( 'Doc updated.', 'core-blueprint-docs' ),
				],
				'description'         => __( 'Documentation articles managed by Core Blueprint Docs.', 'core-blueprint-docs' ),
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => true,
				'show_in_rest'        => true,
				'rest_base'           => 'docs',
				'has_archive'         => true,
				'hierarchical'        => false,
				'exclude_from_search' => false,
				'menu_position'       => 26.4,
				'menu_icon'           => 'dashicons-media-document',
				'rewrite'             => [
					'slug'       => 'docs',
					'with_front' => false,
				],
				'supports' => [
					'title',
					'editor',
					'author',
					'thumbnail',
					'excerpt',
					'revisions',
					'custom-fields',
					'comments',
					'page-attributes',
				],
				'map_meta_cap'     => true,
				'capability_type'  => 'post',
				'delete_with_user' => false,
			]
		);
	}
}
