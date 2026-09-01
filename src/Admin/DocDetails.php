<?php
declare(strict_types=1);

namespace CB\Docs\Admin;

use CB\Docs\Content\Meta;
use CB\Docs\Content\PostType;

defined( 'ABSPATH' ) || exit;

final class DocDetails {
	private const NONCE_ACTION = 'cb_docs_save_details';
	private const NONCE_FIELD  = 'cb_docs_details_nonce';

	public static function init(): void {
		add_action( 'add_meta_boxes_' . PostType::TYPE, [ __CLASS__, 'add_meta_box' ] );
		add_action( 'save_post_' . PostType::TYPE, [ __CLASS__, 'save' ], 10, 2 );
	}

	public static function add_meta_box(): void {
		add_meta_box(
			'cb-docs-details',
			__( 'Doc Details', 'core-blueprint-docs' ),
			[ __CLASS__, 'render' ],
			PostType::TYPE,
			'side',
			'default'
		);
	}

	public static function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$subtitle      = (string) get_post_meta( $post->ID, Meta::SUBTITLE, true );
		$status        = Meta::sanitize_status( get_post_meta( $post->ID, Meta::STATUS, true ) ?: Meta::STATUS_CURRENT );
		$version       = (string) get_post_meta( $post->ID, Meta::VERSION, true );
		$last_reviewed = (string) get_post_meta( $post->ID, Meta::LAST_REVIEWED, true );
		$featured      = (bool) get_post_meta( $post->ID, Meta::FEATURED, true );
		?>
		<p>
			<label for="cb_docs_subtitle"><strong><?php esc_html_e( 'Subtitle', 'core-blueprint-docs' ); ?></strong></label>
			<input class="widefat" type="text" id="cb_docs_subtitle" name="cb_docs_subtitle" value="<?php echo esc_attr( $subtitle ); ?>">
		</p>
		<p>
			<label for="cb_docs_status"><strong><?php esc_html_e( 'Documentation status', 'core-blueprint-docs' ); ?></strong></label>
			<select class="widefat" id="cb_docs_status" name="cb_docs_status">
				<?php foreach ( Meta::status_labels() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="cb_docs_version"><strong><?php esc_html_e( 'Version', 'core-blueprint-docs' ); ?></strong></label>
			<input class="widefat" type="text" id="cb_docs_version" name="cb_docs_version" value="<?php echo esc_attr( $version ); ?>" placeholder="1.0">
			<span class="description"><?php esc_html_e( 'Optional product or documentation version.', 'core-blueprint-docs' ); ?></span>
		</p>
		<p>
			<label for="cb_docs_last_reviewed"><strong><?php esc_html_e( 'Last reviewed', 'core-blueprint-docs' ); ?></strong></label>
			<input class="widefat" type="date" id="cb_docs_last_reviewed" name="cb_docs_last_reviewed" value="<?php echo esc_attr( $last_reviewed ); ?>">
		</p>
		<p>
			<label>
				<input type="checkbox" name="cb_docs_featured" value="1" <?php checked( $featured ); ?>>
				<?php esc_html_e( 'Featured doc', 'core-blueprint-docs' ); ?>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'These values are native WordPress post meta and can be used by builders, queries and the REST API.', 'core-blueprint-docs' ); ?></p>
		<?php
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( PostType::TYPE !== $post->post_type ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( (string) wp_unslash( $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$subtitle = isset( $_POST['cb_docs_subtitle'] )
			? sanitize_text_field( (string) wp_unslash( $_POST['cb_docs_subtitle'] ) )
			: '';
		$version = isset( $_POST['cb_docs_version'] )
			? sanitize_text_field( (string) wp_unslash( $_POST['cb_docs_version'] ) )
			: '';
		$status = isset( $_POST['cb_docs_status'] )
			? Meta::sanitize_status( wp_unslash( $_POST['cb_docs_status'] ) )
			: Meta::STATUS_CURRENT;
		$last_reviewed = isset( $_POST['cb_docs_last_reviewed'] )
			? Meta::sanitize_date( wp_unslash( $_POST['cb_docs_last_reviewed'] ) )
			: '';
		$featured = isset( $_POST['cb_docs_featured'] ) ? '1' : '0';

		self::store_optional( $post_id, Meta::SUBTITLE, $subtitle );
		self::store_optional( $post_id, Meta::VERSION, $version );
		update_post_meta( $post_id, Meta::STATUS, $status );
		self::store_optional( $post_id, Meta::LAST_REVIEWED, $last_reviewed );
		update_post_meta( $post_id, Meta::FEATURED, $featured );
	}

	private static function store_optional( int $post_id, string $key, string $value ): void {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, $value );
	}
}
