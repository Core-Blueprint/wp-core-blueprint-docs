<?php
declare(strict_types=1);

namespace CB\Docs\Content;

defined( 'ABSPATH' ) || exit;

final class Meta {
	public const SUBTITLE      = 'cb_docs_subtitle';
	public const STATUS        = 'cb_docs_status';
	public const VERSION       = 'cb_docs_version';
	public const LAST_REVIEWED = 'cb_docs_last_reviewed';
	public const FEATURED      = 'cb_docs_featured';

	public const STATUS_CURRENT    = 'current';
	public const STATUS_DEPRECATED = 'deprecated';

	public static function register(): void {
		register_post_meta(
			PostType::TYPE,
			self::SUBTITLE,
			self::string_args( 'sanitize_text_field' )
		);

		register_post_meta(
			PostType::TYPE,
			self::STATUS,
			[
				'single'            => true,
				'type'              => 'string',
				'default'           => self::STATUS_CURRENT,
				'show_in_rest'      => true,
				'sanitize_callback' => [ __CLASS__, 'sanitize_status' ],
				'auth_callback'     => [ __CLASS__, 'can_edit_meta' ],
			]
		);

		register_post_meta(
			PostType::TYPE,
			self::VERSION,
			self::string_args( 'sanitize_text_field' )
		);

		register_post_meta(
			PostType::TYPE,
			self::LAST_REVIEWED,
			[
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => [ __CLASS__, 'sanitize_date' ],
				'auth_callback'     => [ __CLASS__, 'can_edit_meta' ],
			]
		);

		register_post_meta(
			PostType::TYPE,
			self::FEATURED,
			[
				'single'            => true,
				'type'              => 'boolean',
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => [ __CLASS__, 'can_edit_meta' ],
			]
		);
	}

	/** @return array<string,mixed> */
	private static function string_args( callable|string $sanitize_callback ): array {
		return [
			'single'            => true,
			'type'              => 'string',
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => $sanitize_callback,
			'auth_callback'     => [ __CLASS__, 'can_edit_meta' ],
		];
	}

	public static function can_edit_meta( mixed $allowed, string $meta_key, int $post_id ): bool {
		unset( $allowed, $meta_key );
		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	public static function sanitize_status( mixed $value ): string {
		$status = sanitize_key( (string) $value );
		return in_array( $status, [ self::STATUS_CURRENT, self::STATUS_DEPRECATED ], true )
			? $status
			: self::STATUS_CURRENT;
	}

	public static function sanitize_date( mixed $value ): string {
		$date = sanitize_text_field( (string) $value );
		if ( '' === $date ) {
			return '';
		}
		if ( 1 !== preg_match( '/^(\\d{4})-(\\d{2})-(\\d{2})$/', $date, $matches ) ) {
			return '';
		}

		return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ? $date : '';
	}

	/** @return array<string,string> */
	public static function status_labels(): array {
		return [
			self::STATUS_CURRENT    => __( 'Current', 'core-blueprint-docs' ),
			self::STATUS_DEPRECATED => __( 'Deprecated', 'core-blueprint-docs' ),
		];
	}
}
