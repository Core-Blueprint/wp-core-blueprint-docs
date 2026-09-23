<?php
declare(strict_types=1);

namespace CB\Docs\Admin;

use CB\Docs\Content\Taxonomies;
use CB\Docs\Structure\Mutation;

defined( 'ABSPATH' ) || exit;

final class OrganizerRest {
	private const NAMESPACE = 'core-blueprint-docs/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/organizer/document',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'move_document' ],
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/organizer/term',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'reorder_term' ],
				'permission_callback' => [ __CLASS__, 'can_manage_terms' ],
			]
		);
	}


	public static function can_manage_terms(): bool {
		$taxonomy = get_taxonomy( Taxonomies::CATEGORY );
		$capability = $taxonomy && isset( $taxonomy->cap->manage_terms )
			? (string) $taxonomy->cap->manage_terms
			: 'manage_categories';
		return current_user_can( $capability );
	}

	public static function move_document( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$result = Mutation::move_document(
			absint( $request->get_param( 'document_id' ) ),
			absint( $request->get_param( 'target_term_id' ) ),
			(int) $request->get_param( 'target_index' ),
			sanitize_text_field( (string) $request->get_param( 'revision' ) )
		);

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public static function reorder_term( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$result = Mutation::reorder_term(
			absint( $request->get_param( 'term_id' ) ),
			(int) $request->get_param( 'target_index' ),
			sanitize_text_field( (string) $request->get_param( 'revision' ) )
		);

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
}
