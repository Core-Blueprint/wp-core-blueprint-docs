<?php
declare(strict_types=1);

namespace CB\Docs\Frontend\Components;

use CB\Docs\Content\Meta;
use CB\Docs\Content\PostType;
use CB\Docs\Frontend\DocumentAccess;

defined( 'ABSPATH' ) || exit;

final class DocumentMeta {
	/** @param array{id?:mixed} $args */
	public static function render( array $args = [] ): string {
		$args = wp_parse_args( $args, [ 'id' => 0 ] );
		$post_id = absint( $args['id'] );

		if ( 0 === $post_id && is_singular( PostType::TYPE ) ) {
			$post_id = get_queried_object_id();
		}
		if ( PostType::TYPE !== get_post_type( $post_id ) || ! DocumentAccess::can_read( $post_id ) ) {
			return '';
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$status   = Meta::sanitize_status( get_post_meta( $post_id, Meta::STATUS, true ) ?: Meta::STATUS_CURRENT );
		$labels   = Meta::status_labels();
		$version  = (string) get_post_meta( $post_id, Meta::VERSION, true );
		$reviewed = (string) get_post_meta( $post_id, Meta::LAST_REVIEWED, true );
		$author   = get_the_author_meta( 'display_name', (int) $post->post_author );

		$rows = [
			[ __( 'Author', 'core-blueprint-docs' ), (string) $author ],
			[ __( 'Published', 'core-blueprint-docs' ), get_the_date( '', $post ) ],
			[ __( 'Updated', 'core-blueprint-docs' ), get_the_modified_date( '', $post ) ],
			[ __( 'Status', 'core-blueprint-docs' ), $labels[ $status ] ?? $status ],
		];

		if ( '' !== $version ) {
			$rows[] = [ __( 'Version', 'core-blueprint-docs' ), $version ];
		}
		if ( '' !== $reviewed ) {
			$rows[] = [ __( 'Last reviewed', 'core-blueprint-docs' ), $reviewed ];
		}

		$html = '<dl class="cb-docs-meta">';
		foreach ( $rows as [ $label, $value ] ) {
			$html .= '<div class="cb-docs-meta__item"><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
		}

		return $html . '</dl>';
	}
}
