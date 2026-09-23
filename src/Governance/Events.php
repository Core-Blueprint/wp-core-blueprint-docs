<?php
declare(strict_types=1);

namespace CB\Docs\Governance;

use CB\Core\Governance\Audit;
use CB\Core\Governance\EventRegistry;
use CB\Docs\Content\Meta;
use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

final class Events {
	public const CREATED          = 'docs.document.created';
	public const PUBLISHED        = 'docs.document.published';
	public const UPDATED          = 'docs.document.updated';
	public const TRASHED          = 'docs.document.trashed';
	public const RESTORED         = 'docs.document.restored';
	public const DELETED          = 'docs.document.deleted';
	public const SETTINGS_UPDATED = 'docs.settings.updated';
	public const STRUCTURE_UPDATED = 'docs.structure.updated';

	/** @var array<int,array{created:bool,fields:array<string,bool>}> */
	private static array $changes = [];
	/** @var array<int,bool> */
	private static array $lifecycle = [];
	private static int $suspend_changes = 0;

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register' ], 10 );
		add_action( 'post_updated', [ __CLASS__, 'capture_post_update' ], 10, 3 );
		add_action( 'wp_after_insert_post', [ __CLASS__, 'capture_insert' ], 100, 4 );
		add_action( 'added_post_meta', [ __CLASS__, 'capture_meta_change' ], 10, 4 );
		add_action( 'updated_post_meta', [ __CLASS__, 'capture_meta_change' ], 10, 4 );
		add_action( 'deleted_post_meta', [ __CLASS__, 'capture_meta_change' ], 10, 4 );
		add_action( 'set_object_terms', [ __CLASS__, 'capture_terms_change' ], 10, 6 );
		add_action( 'transition_post_status', [ __CLASS__, 'capture_status_transition' ], 10, 3 );
		add_action( 'trashed_post', [ __CLASS__, 'record_trashed' ], 10, 2 );
		add_action( 'untrashed_post', [ __CLASS__, 'record_restored' ], 10, 2 );
		add_action( 'before_delete_post', [ __CLASS__, 'record_deleted' ], 20, 2 );
		add_action( 'shutdown', [ __CLASS__, 'flush_changes' ], 5 );
	}

	public static function register(): void {
		EventRegistry::register( [ 'id' => self::CREATED, 'label' => __( 'Docs document created', 'core-blueprint-docs' ), 'retention_category' => 'general' ] );
		EventRegistry::register( [ 'id' => self::PUBLISHED, 'label' => __( 'Docs document published', 'core-blueprint-docs' ), 'retention_category' => 'general' ] );
		EventRegistry::register( [ 'id' => self::UPDATED, 'label' => __( 'Docs document updated', 'core-blueprint-docs' ), 'retention_category' => 'general' ] );
		EventRegistry::register( [ 'id' => self::TRASHED, 'label' => __( 'Docs document trashed', 'core-blueprint-docs' ), 'retention_category' => 'general' ] );
		EventRegistry::register( [ 'id' => self::RESTORED, 'label' => __( 'Docs document restored', 'core-blueprint-docs' ), 'retention_category' => 'general' ] );
		EventRegistry::register( [ 'id' => self::DELETED, 'label' => __( 'Docs document permanently deleted', 'core-blueprint-docs' ), 'retention_category' => 'general' ] );
		EventRegistry::register( [ 'id' => self::SETTINGS_UPDATED, 'label' => __( 'Docs settings updated', 'core-blueprint-docs' ), 'retention_category' => 'settings' ] );
		EventRegistry::register( [ 'id' => self::STRUCTURE_UPDATED, 'label' => __( 'Docs structure updated', 'core-blueprint-docs' ), 'retention_category' => 'general' ] );
	}

	public static function record_settings_updated( string $setting, string $before, string $after ): bool {
		$setting = sanitize_key( $setting );
		if ( '' === $setting || $before === $after ) {
			return false;
		}

		return Audit::record(
			self::SETTINGS_UPDATED,
			'notice',
			[
				'setting' => $setting,
				'before'  => sanitize_text_field( $before ),
				'after'   => sanitize_text_field( $after ),
			]
		);
	}


	/**
	 * Run one canonical structure mutation without emitting low-level document
	 * update noise. The caller records one semantic structure event afterwards.
	 */
	public static function without_change_capture( callable $callback ): mixed {
		++self::$suspend_changes;
		try {
			return $callback();
		} finally {
			self::$suspend_changes = max( 0, self::$suspend_changes - 1 );
		}
	}

	/** @param array<string,mixed> $context */
	public static function record_structure_updated( string $operation, array $context = [] ): bool {
		$operation = sanitize_key( $operation );
		if ( '' === $operation ) {
			return false;
		}

		$safe = [ 'operation' => $operation ];
		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || ! is_scalar( $value ) ) {
				continue;
			}
			$safe[ $key ] = is_int( $value ) ? $value : sanitize_text_field( (string) $value );
		}

		return Audit::record( self::STRUCTURE_UPDATED, 'notice', $safe );
	}

	public static function capture_post_update( int $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
		if ( self::$suspend_changes > 0 ) {
			return;
		}
		if ( ! self::is_doc( $post_after ) || self::is_noise( $post_id ) ) {
			return;
		}

		if ( 'auto-draft' === $post_before->post_status && 'auto-draft' !== $post_after->post_status ) {
			self::mark_created( $post_id );
		}

		$fields = [
			'post_title'        => 'title',
			'post_content'      => 'content',
			'post_excerpt'      => 'excerpt',
			'post_author'       => 'author',
			'post_name'         => 'slug',
			'post_date'         => 'publish_date',
			'post_password'     => 'password',
			'comment_status'    => 'comments',
			'menu_order'        => 'order',
		];
		foreach ( $fields as $property => $field ) {
			if ( $post_before->{$property} !== $post_after->{$property} ) {
				self::mark_field( $post_id, $field );
			}
		}

		if (
			$post_before->post_status !== $post_after->post_status
			&& 'publish' !== $post_after->post_status
			&& 'trash' !== $post_after->post_status
			&& 'trash' !== $post_before->post_status
		) {
			self::mark_field( $post_id, 'status' );
		}
	}

	public static function capture_insert( int $post_id, \WP_Post $post, bool $update, ?\WP_Post $post_before ): void {
		if ( self::$suspend_changes > 0 ) {
			return;
		}
		unset( $post_before );
		if ( $update || ! self::is_doc( $post ) || 'auto-draft' === $post->post_status || self::is_noise( $post_id ) ) {
			return;
		}
		self::mark_created( $post_id );
	}

	public static function capture_meta_change( mixed $meta_id, int $post_id, string $meta_key, mixed $meta_value ): void {
		if ( self::$suspend_changes > 0 ) {
			return;
		}
		unset( $meta_id, $meta_value );
		if ( PostType::TYPE !== get_post_type( $post_id ) || self::is_noise( $post_id ) ) {
			return;
		}

		if ( in_array( $meta_key, [ '_edit_lock', '_edit_last' ], true ) ) {
			return;
		}

		$field = match ( $meta_key ) {
			Meta::SUBTITLE      => 'subtitle',
			Meta::STATUS        => 'documentation_status',
			Meta::VERSION       => 'version',
			Meta::LAST_REVIEWED => 'last_reviewed',
			Meta::FEATURED      => 'featured',
			'_thumbnail_id'     => 'featured_image',
			default             => 'meta',
		};
		self::mark_field( $post_id, $field );
	}

	/** @param array<int|string,mixed>|string $terms @param int[] $tt_ids @param int[] $old_tt_ids */
	public static function capture_terms_change( int $object_id, mixed $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids ): void {
		if ( self::$suspend_changes > 0 ) {
			return;
		}
		unset( $terms, $tt_ids, $append, $old_tt_ids );
		if ( PostType::TYPE !== get_post_type( $object_id ) || self::is_noise( $object_id ) ) {
			return;
		}
		if ( Taxonomies::CATEGORY === $taxonomy ) {
			self::mark_field( $object_id, 'categories' );
		} elseif ( Taxonomies::TAG === $taxonomy ) {
			self::mark_field( $object_id, 'tags' );
		}
	}

	public static function capture_status_transition( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( ! self::is_doc( $post ) || self::is_noise( (int) $post->ID ) ) {
			return;
		}
		if ( 'publish' === $new_status && 'publish' !== $old_status && 'trash' !== $old_status ) {
			self::$lifecycle[ (int) $post->ID ] = true;
			Audit::record( self::PUBLISHED, 'notice', self::context( $post ) );
		}
	}

	public static function record_trashed( int $post_id, string $previous_status = '' ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! self::is_doc( $post ) ) {
			return;
		}
		self::$lifecycle[ $post_id ] = true;
		Audit::record( self::TRASHED, 'notice', self::context( $post ) + [ 'previous_status' => sanitize_key( $previous_status ) ] );
	}

	public static function record_restored( int $post_id, string $previous_status = '' ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! self::is_doc( $post ) ) {
			return;
		}
		self::$lifecycle[ $post_id ] = true;
		Audit::record( self::RESTORED, 'notice', self::context( $post ) + [ 'previous_status' => sanitize_key( $previous_status ) ] );
	}

	public static function record_deleted( int $post_id, \WP_Post $post ): void {
		if ( ! self::is_doc( $post ) ) {
			return;
		}
		unset( self::$changes[ $post_id ] );
		self::$lifecycle[ $post_id ] = true;
		Audit::record( self::DELETED, 'warning', self::context( $post ) );
	}

	public static function flush_changes(): void {
		foreach ( self::$changes as $post_id => $change ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post || ! self::is_doc( $post ) || 'auto-draft' === $post->post_status || 'trash' === $post->post_status ) {
				continue;
			}

			if ( $change['created'] ) {
				Audit::record( self::CREATED, 'notice', self::context( $post ) );
				continue;
			}
			if ( isset( self::$lifecycle[ $post_id ] ) ) {
				continue;
			}

			$fields = array_keys( array_filter( $change['fields'] ) );
			sort( $fields );
			if ( empty( $fields ) ) {
				continue;
			}

			Audit::record(
				self::UPDATED,
				'notice',
				self::context( $post ) + [ 'changed_fields' => $fields ]
			);
		}

		self::$changes = [];
		self::$lifecycle = [];
	}

	private static function mark_created( int $post_id ): void {
		self::$changes[ $post_id ] ??= [ 'created' => false, 'fields' => [] ];
		self::$changes[ $post_id ]['created'] = true;
	}

	private static function mark_field( int $post_id, string $field ): void {
		self::$changes[ $post_id ] ??= [ 'created' => false, 'fields' => [] ];
		self::$changes[ $post_id ]['fields'][ sanitize_key( $field ) ] = true;
	}

	private static function is_doc( \WP_Post $post ): bool {
		return PostType::TYPE === $post->post_type;
	}

	private static function is_noise( int $post_id ): bool {
		return (bool) wp_is_post_autosave( $post_id ) || (bool) wp_is_post_revision( $post_id );
	}

	/** @return array{document_id:int,status:string} */
	private static function context( \WP_Post $post ): array {
		return [
			'document_id' => (int) $post->ID,
			'status'      => sanitize_key( (string) $post->post_status ),
		];
	}
}
