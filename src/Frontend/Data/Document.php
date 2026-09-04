<?php
declare(strict_types=1);

namespace CB\Docs\Frontend\Data;

use CB\Docs\Content\Meta;
use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;
use CB\Docs\Frontend\DocumentAccess;

defined( 'ABSPATH' ) || exit;

final class Document {
	private const FIELDS = [
		'id',
		'title',
		'permalink',
		'excerpt',
		'content',
		'categories',
		'tags',
		'subtitle',
		'documentation_status',
		'version',
		'last_reviewed',
		'featured',
	];

	/** @return array<string,mixed>|\WP_Error */
	public static function current(): array|\WP_Error {
		$post_id = get_queried_object_id();
		if ( $post_id <= 0 ) {
			$post = get_post();
			$post_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		}

		return self::get( $post_id );
	}

	/** @return array<string,mixed>|\WP_Error */
	public static function get( int $document_id ): array|\WP_Error {
		$post = $document_id > 0 ? DocumentAccess::resolve( $document_id ) : null;
		if ( ! $post || PostType::TYPE !== $post->post_type || ! DocumentAccess::can_read( $post ) ) {
			return self::not_found();
		}

		return self::project( $post );
	}

	public static function value( string $field, ?int $document_id = null ): mixed {
		$field = sanitize_key( $field );
		if ( ! in_array( $field, self::FIELDS, true ) ) {
			return null;
		}

		$document = null === $document_id ? self::current() : self::get( $document_id );
		if ( is_wp_error( $document ) ) {
			return $document;
		}

		return $document[ $field ] ?? null;
	}

	/** @return string[] */
	public static function fields(): array {
		return self::FIELDS;
	}

	/**
	 * Project one document already obtained through an authorized Docs query.
	 *
	 * @internal Public consumers should call get()/current() or the query API.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function project( \WP_Post $post ): array|\WP_Error {
		if ( PostType::TYPE !== $post->post_type || ! DocumentAccess::protected_content_allowed( $post ) ) {
			return self::not_found();
		}

		$post_id = (int) $post->ID;
		$status  = get_post_meta( $post_id, Meta::STATUS, true );
		$status  = Meta::sanitize_status( '' !== (string) $status ? $status : Meta::STATUS_CURRENT );

		return [
			'id'                   => $post_id,
			'title'                => (string) get_the_title( $post ),
			'permalink'            => (string) ( get_permalink( $post ) ?: '' ),
			'excerpt'              => (string) get_the_excerpt( $post ),
			'content'              => (string) $post->post_content,
			'categories'           => self::terms( $post_id, Taxonomies::CATEGORY ),
			'tags'                 => self::terms( $post_id, Taxonomies::TAG ),
			'subtitle'             => sanitize_text_field( (string) get_post_meta( $post_id, Meta::SUBTITLE, true ) ),
			'documentation_status' => $status,
			'version'              => sanitize_text_field( (string) get_post_meta( $post_id, Meta::VERSION, true ) ),
			'last_reviewed'        => Meta::sanitize_date( get_post_meta( $post_id, Meta::LAST_REVIEWED, true ) ),
			'featured'             => (bool) get_post_meta( $post_id, Meta::FEATURED, true ),
		];
	}

	/** @return array<int,array{id:int,name:string,slug:string}> */
	private static function terms( int $post_id, string $taxonomy ): array {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		$result = [];
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$result[] = [
				'id'   => (int) $term->term_id,
				'name' => sanitize_text_field( (string) $term->name ),
				'slug' => sanitize_title( (string) $term->slug ),
			];
		}

		return $result;
	}

	private static function not_found(): \WP_Error {
		// Reuse an existing translated frontend string and intentionally keep
		// missing/unauthorized exact reads indistinguishable.
		return new \WP_Error( 'docs_document_not_found', __( 'No documentation found.', 'core-blueprint-docs' ) );
	}
}
