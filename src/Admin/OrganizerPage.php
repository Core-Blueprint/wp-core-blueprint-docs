<?php
declare(strict_types=1);

namespace CB\Docs\Admin;

use CB\Core\UI\Assets;
use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;
use CB\Docs\Structure\Snapshot;

defined( 'ABSPATH' ) || exit;

final class OrganizerPage {
	private const SLUG = 'cb-docs-organizer';
	private static string $hook_suffix = '';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register' ], 20 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function register(): void {
		$hook = add_submenu_page(
			'edit.php?post_type=' . PostType::TYPE,
			__( 'Documentation Organizer', 'core-blueprint-docs' ),
			__( 'Organizer', 'core-blueprint-docs' ),
			'edit_posts',
			self::SLUG,
			[ __CLASS__, 'render' ],
			20
		);

		self::$hook_suffix = is_string( $hook ) ? $hook : '';
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( '' === self::$hook_suffix || self::$hook_suffix !== $hook_suffix ) {
			return;
		}

		Assets::enqueue_reorder();

		wp_enqueue_style(
			'cb-docs-organizer',
			CB_DOCS_URL . 'assets/css/admin-organizer.css',
			[],
			CB_DOCS_VERSION
		);

		wp_enqueue_script_module(
			'@cb-docs/organizer',
			CB_DOCS_URL . 'assets/js/admin-organizer.js',
			[ '@cb-core/reorder' ],
			CB_DOCS_VERSION
		);

		add_filter(
			'script_module_data_@cb-docs/organizer',
			static function ( array $existing ): array {
				return array_merge(
					$existing,
					[
						'documentEndpoint' => esc_url_raw( rest_url( 'core-blueprint-docs/v1/organizer/document' ) ),
						'termEndpoint'     => esc_url_raw( rest_url( 'core-blueprint-docs/v1/organizer/term' ) ),
						'nonce'            => wp_create_nonce( 'wp_rest' ),
						'i18n'             => [
							'saveFailed' => __( 'The documentation structure could not be saved.', 'core-blueprint-docs' ),
							'stale'      => __( 'The documentation structure changed. Reload the Organizer before continuing.', 'core-blueprint-docs' ),
							'network'    => __( 'The documentation structure could not be saved because the request failed.', 'core-blueprint-docs' ),
						],
					]
				);
			}
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to organize documentation.', 'core-blueprint-docs' ) );
		}

		$snapshot = Snapshot::build();
		if ( is_wp_error( $snapshot ) ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Documentation Organizer', 'core-blueprint-docs' ); ?></h1>
				<div class="notice notice-error inline"><p><?php echo esc_html( $snapshot->get_error_message() ); ?></p></div>
			</div>
			<?php
			return;
		}

		$terms = self::terms_by_parent( (array) $snapshot['terms'] );
		$all_terms = self::ordered_flat_terms( $terms );
		?>
		<div class="wrap cb-docs-organizer-wrap">
			<h1><?php esc_html_e( 'Documentation Organizer', 'core-blueprint-docs' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Drag documentation sets, sections and articles into their intended order. Category hierarchy remains managed from the normal Categories screen.', 'core-blueprint-docs' ); ?>
			</p>

			<div
				class="cb-docs-organizer"
				data-cb-docs-organizer
				data-cb-core-reorder
				data-revision="<?php echo esc_attr( (string) $snapshot['revision'] ); ?>"
			>
				<?php self::render_term_list( 0, $terms, (array) $snapshot['docs_by_term'], $all_terms ); ?>

				<div class="cb-docs-organizer__attention-grid">
					<?php self::render_attention_list( 'unassigned', __( 'Unassigned', 'core-blueprint-docs' ), __( 'These documents do not have a structural Doc Category yet.', 'core-blueprint-docs' ), (array) $snapshot['unassigned'], $all_terms ); ?>
					<?php self::render_attention_list( 'ambiguous', __( 'Needs review', 'core-blueprint-docs' ), __( 'These documents have more than one Doc Category. Move one to a single structural category to resolve it.', 'core-blueprint-docs' ), (array) $snapshot['ambiguous'], $all_terms ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param array<int,array<string,mixed>> $terms
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	private static function terms_by_parent( array $terms ): array {
		$by_parent = [];
		foreach ( $terms as $term ) {
			if ( ! is_array( $term ) || (int) ( $term['id'] ?? 0 ) <= 0 ) {
				continue;
			}
			$by_parent[ (int) ( $term['parent'] ?? 0 ) ][] = $term;
		}

		foreach ( $by_parent as &$siblings ) {
			usort(
				$siblings,
				static function ( array $a, array $b ): int {
					$a_order = (int) ( $a['order'] ?? 0 );
					$b_order = (int) ( $b['order'] ?? 0 );
					if ( $a_order > 0 || $b_order > 0 ) {
						if ( $a_order <= 0 ) {
							return 1;
						}
						if ( $b_order <= 0 ) {
							return -1;
						}
						if ( $a_order !== $b_order ) {
							return $a_order <=> $b_order;
						}
					}
					$name = strcasecmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
					return 0 !== $name ? $name : (int) $a['id'] <=> (int) $b['id'];
				}
			);
		}
		unset( $siblings );

		return $by_parent;
	}

	/**
	 * @param array<int,array<int,array<string,mixed>>> $terms
	 * @return array<int,array{id:int,label:string}>
	 */
	private static function ordered_flat_terms( array $terms, int $parent = 0, int $depth = 0 ): array {
		$flat = [];
		foreach ( $terms[ $parent ] ?? [] as $term ) {
			$id = (int) $term['id'];
			$flat[] = [
				'id'    => $id,
				'label' => str_repeat( '— ', max( 0, $depth ) ) . (string) $term['name'],
			];
			$flat = array_merge( $flat, self::ordered_flat_terms( $terms, $id, $depth + 1 ) );
		}
		return $flat;
	}

	/**
	 * @param array<int,array<int,array<string,mixed>>> $terms
	 * @param array<int,array<int,array<string,mixed>>> $docs_by_term
	 * @param array<int,array{id:int,label:string}> $all_terms
	 */
	private static function render_term_list( int $parent, array $terms, array $docs_by_term, array $all_terms ): void {
		$list_id = 'terms:' . $parent;
		$label = 0 === $parent ? __( 'Documentation sets', 'core-blueprint-docs' ) : __( 'Documentation sections', 'core-blueprint-docs' );
		?>
		<div
			class="cb-docs-organizer__term-list<?php echo 0 === $parent ? ' cb-docs-organizer__term-list--root' : ''; ?>"
			data-cb-core-reorder-list="<?php echo esc_attr( $list_id ); ?>"
			data-cb-core-reorder-list-label="<?php echo esc_attr( $label ); ?>"
			data-empty-label="<?php echo esc_attr__( 'No categories at this level.', 'core-blueprint-docs' ); ?>"
		>
			<?php foreach ( $terms[ $parent ] ?? [] as $term ) : ?>
				<?php self::render_term( $term, $terms, $docs_by_term, $all_terms ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed> $term
	 * @param array<int,array<int,array<string,mixed>>> $terms
	 * @param array<int,array<int,array<string,mixed>>> $docs_by_term
	 * @param array<int,array{id:int,label:string}> $all_terms
	 */
	private static function render_term( array $term, array $terms, array $docs_by_term, array $all_terms ): void {
		$term_id = (int) $term['id'];
		$parent_id = (int) $term['parent'];
		$can_manage = current_user_can( 'manage_categories' );
		$edit_link = get_edit_term_link( $term_id, Taxonomies::CATEGORY, PostType::TYPE );
		?>
		<section
			class="cb-docs-organizer__term"
			data-cb-core-reorder-item="term:<?php echo esc_attr( (string) $term_id ); ?>"
			data-cb-core-reorder-label="<?php echo esc_attr( (string) $term['name'] ); ?>"
			data-cb-docs-kind="term"
			data-parent-id="<?php echo esc_attr( (string) $parent_id ); ?>"
		>
			<header class="cb-docs-organizer__term-header">
				<div class="cb-docs-organizer__term-heading">
					<?php if ( $can_manage ) : ?>
						<button type="button" class="button-link cb-docs-organizer__handle" data-cb-core-reorder-handle aria-label="<?php echo esc_attr( sprintf( __( 'Reorder %s', 'core-blueprint-docs' ), (string) $term['name'] ) ); ?>"><span class="dashicons dashicons-move" aria-hidden="true"></span></button>
					<?php endif; ?>
					<strong><?php echo esc_html( (string) $term['name'] ); ?></strong>
				</div>
				<div class="cb-docs-organizer__actions">
					<?php if ( $can_manage ) : ?>
						<button type="button" class="button button-small" data-cb-docs-move-up><?php esc_html_e( 'Up', 'core-blueprint-docs' ); ?></button>
						<button type="button" class="button button-small" data-cb-docs-move-down><?php esc_html_e( 'Down', 'core-blueprint-docs' ); ?></button>
					<?php endif; ?>
					<?php if ( is_string( $edit_link ) && '' !== $edit_link ) : ?>
						<a class="button button-small" href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edit category', 'core-blueprint-docs' ); ?></a>
					<?php endif; ?>
				</div>
			</header>

			<?php self::render_document_list( $term_id, (array) ( $docs_by_term[ $term_id ] ?? [] ), $all_terms ); ?>
			<?php self::render_term_list( $term_id, $terms, $docs_by_term, $all_terms ); ?>
		</section>
		<?php
	}

	/**
	 * @param array<int,array<string,mixed>> $documents
	 * @param array<int,array{id:int,label:string}> $all_terms
	 */
	private static function render_document_list( int $term_id, array $documents, array $all_terms ): void {
		?>
		<div
			class="cb-docs-organizer__docs"
			data-cb-core-reorder-list="docs:<?php echo esc_attr( (string) $term_id ); ?>"
			data-cb-core-reorder-list-label="<?php echo esc_attr__( 'Documentation articles', 'core-blueprint-docs' ); ?>"
			data-empty-label="<?php echo esc_attr__( 'No documents in this category.', 'core-blueprint-docs' ); ?>"
		>
			<?php foreach ( $documents as $document ) : ?>
				<?php self::render_document( $document, $all_terms, $term_id, true ); ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * @param array<string,mixed> $document
	 * @param array<int,array{id:int,label:string}> $all_terms
	 */
	private static function render_document( array $document, array $all_terms, int $current_term_id, bool $ordered ): void {
		$document_id = (int) ( $document['id'] ?? 0 );
		if ( $document_id <= 0 ) {
			return;
		}
		$title = (string) ( $document['title'] ?? '' );
		$status = sanitize_key( (string) ( $document['status'] ?? 'draft' ) );
		$status_object = get_post_status_object( $status );
		$status_label = $status_object ? (string) $status_object->label : $status;
		$can_edit = current_user_can( 'edit_post', $document_id );
		$edit_link = get_edit_post_link( $document_id );
		?>
		<article
			class="cb-docs-organizer__doc"
			data-cb-core-reorder-item="doc:<?php echo esc_attr( (string) $document_id ); ?>"
			data-cb-core-reorder-label="<?php echo esc_attr( $title ); ?>"
			data-cb-docs-kind="doc"
			data-document-id="<?php echo esc_attr( (string) $document_id ); ?>"
			data-current-term="<?php echo esc_attr( (string) $current_term_id ); ?>"
		>
			<div class="cb-docs-organizer__doc-main">
				<?php if ( $can_edit ) : ?>
					<button type="button" class="button-link cb-docs-organizer__handle" data-cb-core-reorder-handle aria-label="<?php echo esc_attr( sprintf( __( 'Reorder %s', 'core-blueprint-docs' ), $title ) ); ?>"><span class="dashicons dashicons-move" aria-hidden="true"></span></button>
				<?php endif; ?>
				<span class="cb-docs-organizer__doc-title"><?php echo esc_html( $title ); ?></span>
				<span class="cb-docs-organizer__status"><?php echo esc_html( $status_label ); ?></span>
			</div>
			<?php if ( $can_edit ) : ?>
				<div class="cb-docs-organizer__actions">
					<?php if ( $ordered ) : ?>
						<button type="button" class="button button-small" data-cb-docs-move-up><?php esc_html_e( 'Up', 'core-blueprint-docs' ); ?></button>
						<button type="button" class="button button-small" data-cb-docs-move-down><?php esc_html_e( 'Down', 'core-blueprint-docs' ); ?></button>
					<?php endif; ?>
					<label class="screen-reader-text" for="cb-docs-move-<?php echo esc_attr( (string) $document_id ); ?>"><?php esc_html_e( 'Move to category', 'core-blueprint-docs' ); ?></label>
					<select id="cb-docs-move-<?php echo esc_attr( (string) $document_id ); ?>" data-cb-docs-move-to>
						<option value=""><?php esc_html_e( 'Move to…', 'core-blueprint-docs' ); ?></option>
						<?php foreach ( $all_terms as $option ) : ?>
							<option value="<?php echo esc_attr( (string) $option['id'] ); ?>" <?php disabled( $current_term_id, (int) $option['id'] ); ?>><?php echo esc_html( $option['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( is_string( $edit_link ) && '' !== $edit_link ) : ?>
						<a class="button button-small" href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Edit', 'core-blueprint-docs' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</article>
		<?php
	}

	/**
	 * @param array<int,array<string,mixed>> $documents
	 * @param array<int,array{id:int,label:string}> $all_terms
	 */
	private static function render_attention_list( string $key, string $title, string $description, array $documents, array $all_terms ): void {
		?>
		<section class="cb-docs-organizer__attention">
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<div
				class="cb-docs-organizer__docs"
				data-cb-core-reorder-list="attention:<?php echo esc_attr( $key ); ?>"
				data-cb-core-reorder-list-label="<?php echo esc_attr( $title ); ?>"
				data-empty-label="<?php echo esc_attr__( 'Nothing needs attention.', 'core-blueprint-docs' ); ?>"
			>
				<?php foreach ( $documents as $document ) : ?>
					<?php self::render_document( $document, $all_terms, 0, false ); ?>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}
}
