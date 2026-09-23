<?php
declare(strict_types=1);

namespace CB\Docs\Admin;

use CB\Core\Admin\SettingsRegistry;
use CB\Core\UI\Card;
use CB\Core\UI\IntegrationGrid;
use CB\Core\UI\Notice;
use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;
use CB\Docs\Integration\Suite;
use CB\Docs\Permalinks\Readiness;
use CB\Docs\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {
	private const TAB_OVERVIEW     = 'overview';
	private const TAB_GENERAL      = 'general';
	private const TAB_INTEGRATIONS = 'integrations';

	public static function init(): void {
		add_action( 'cb_core_register_settings', [ __CLASS__, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function register(): void {
		$page = new self();

		SettingsRegistry::register(
			Suite::ID,
			[
				'label'        => $page->menu_title(),
				'description'  => __( 'Manage documentation health, URL behavior, frontend composition and optional integrations. Documentation content remains native WordPress and is managed from the Docs content screen.', 'core-blueprint-docs' ),
				'group'        => SettingsRegistry::GROUP_CONTENT_PUBLISHING,
				'capability'   => $page->capability(),
				'renderer'     => [ $page, 'render' ],
				'requirements' => [
					'foundations' => [
						'clipboard',
					],
					'components' => [
						'panels',
						'notices',
						'form-controls',
						'cards',
						'integration-grid',
						'metric-tiles',
						'nav-tabs',
						'status',
					],
				],
			]
		);
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( ! self::is_settings_screen() ) {
			return;
		}

		wp_enqueue_script_module(
			'@cb-docs/admin-shortcodes',
			CB_DOCS_URL . 'assets/js/admin-shortcodes.js',
			[ '@cb-core/clipboard' ],
			CB_DOCS_VERSION
		);
	}

	public function title(): string {
		return __( 'Docs', 'core-blueprint-docs' );
	}

	public function menu_title(): string {
		return __( 'Docs', 'core-blueprint-docs' );
	}

	public function capability(): string {
		return 'manage_options';
	}

	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'core-blueprint-docs' ) );
		}

		$tab = self::current_tab();
		?>
		<div class="wrap cb-core-wrap cb-core-page cb-docs-settings-wrap">
			<p class="cb-core-eyebrow"><?php esc_html_e( 'Core Blueprint', 'core-blueprint-docs' ); ?></p>
			<h1 class="cb-core-title"><?php esc_html_e( 'Docs', 'core-blueprint-docs' ); ?></h1>
			<p class="cb-core-intro">
				<?php esc_html_e( 'Manage documentation health, URL behavior, frontend composition and optional integrations. Documentation content remains native WordPress and is managed from the Docs content screen.', 'core-blueprint-docs' ); ?>
			</p>

			<?php self::render_tabs( $tab ); ?>

			<?php
			switch ( $tab ) {
				case self::TAB_GENERAL:
					self::render_general();
					break;
				case self::TAB_INTEGRATIONS:
					self::render_integrations();
					break;
				case self::TAB_OVERVIEW:
				default:
					self::render_overview();
					break;
			}
			?>
		</div>
		<?php
	}

	/** @return array<string,string> */
	private static function tabs(): array {
		return [
			self::TAB_OVERVIEW     => __( 'Overview', 'core-blueprint-docs' ),
			self::TAB_GENERAL      => __( 'General', 'core-blueprint-docs' ),
			self::TAB_INTEGRATIONS => __( 'Integrations', 'core-blueprint-docs' ),
		];
	}

	private static function current_tab(): string {
		$tab = isset( $_GET['tab'] )
			? sanitize_key( (string) wp_unslash( $_GET['tab'] ) )
			: self::TAB_OVERVIEW; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation-only state.

		return array_key_exists( $tab, self::tabs() ) ? $tab : self::TAB_OVERVIEW;
	}

	private static function tab_url( string $tab ): string {
		if ( ! array_key_exists( $tab, self::tabs() ) ) {
			$tab = self::TAB_OVERVIEW;
		}

		return SettingsRegistry::url( Suite::ID, [ 'tab' => $tab ] );
	}

	private static function is_settings_screen(): bool {
		$canonical_url = SettingsRegistry::url( Suite::ID );
		$query         = wp_parse_url( $canonical_url, PHP_URL_QUERY );
		$args          = [];

		if ( is_string( $query ) && '' !== $query ) {
			parse_str( $query, $args );
		}

		$settings_page = isset( $args['page'] ) ? sanitize_key( (string) $args['page'] ) : '';
		$current_page  = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin routing only.
		$extension_id  = isset( $_GET['extension'] ) ? sanitize_key( (string) wp_unslash( $_GET['extension'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin routing only.

		return '' !== $settings_page
			&& $settings_page === $current_page
			&& Suite::ID === $extension_id;
	}

	private static function render_tabs( string $active_tab ): void {
		?>
		<nav class="nav-tab-wrapper cb-core-tab-wrapper" aria-label="<?php echo esc_attr__( 'Docs sections', 'core-blueprint-docs' ); ?>">
			<?php foreach ( self::tabs() as $key => $label ) : ?>
				<a class="nav-tab <?php echo $active_tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( self::tab_url( $key ) ); ?>"<?php echo $active_tab === $key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	private static function render_overview(): void {
		$counts = wp_count_posts( PostType::TYPE );
		$published = (int) ( $counts->publish ?? 0 );
		$drafts    = (int) ( $counts->draft ?? 0 );
		$categories = wp_count_terms( [
			'taxonomy'   => Taxonomies::CATEGORY,
			'hide_empty' => false,
		] );
		$tags = wp_count_terms( [
			'taxonomy'   => Taxonomies::TAG,
			'hide_empty' => false,
		] );
		$categories = is_wp_error( $categories ) ? 0 : (int) $categories;
		$tags       = is_wp_error( $tags ) ? 0 : (int) $tags;
		?>
		<div class="cb-core-tiles" aria-label="<?php echo esc_attr__( 'Docs at a glance', 'core-blueprint-docs' ); ?>">
			<a class="cb-core-tile cb-core-tile--metric cb-core-tile--navigation cb-core-tile--neutral" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . PostType::TYPE ) ); ?>">
				<span class="cb-core-tile__label"><?php esc_html_e( 'Published Docs', 'core-blueprint-docs' ); ?></span>
				<strong class="cb-core-tile__value"><?php echo esc_html( number_format_i18n( $published ) ); ?></strong>
			</a>
			<a class="cb-core-tile cb-core-tile--metric cb-core-tile--navigation cb-core-tile--neutral" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . PostType::TYPE . '&post_status=draft' ) ); ?>">
				<span class="cb-core-tile__label"><?php esc_html_e( 'Drafts', 'core-blueprint-docs' ); ?></span>
				<strong class="cb-core-tile__value"><?php echo esc_html( number_format_i18n( $drafts ) ); ?></strong>
			</a>
			<a class="cb-core-tile cb-core-tile--metric cb-core-tile--navigation cb-core-tile--neutral" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . Taxonomies::CATEGORY . '&post_type=' . PostType::TYPE ) ); ?>">
				<span class="cb-core-tile__label"><?php esc_html_e( 'Categories', 'core-blueprint-docs' ); ?></span>
				<strong class="cb-core-tile__value"><?php echo esc_html( number_format_i18n( $categories ) ); ?></strong>
			</a>
			<a class="cb-core-tile cb-core-tile--metric cb-core-tile--navigation cb-core-tile--neutral" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . Taxonomies::TAG . '&post_type=' . PostType::TYPE ) ); ?>">
				<span class="cb-core-tile__label"><?php esc_html_e( 'Tags', 'core-blueprint-docs' ); ?></span>
				<strong class="cb-core-tile__value"><?php echo esc_html( number_format_i18n( $tags ) ); ?></strong>
			</a>
		</div>

		<?php echo self::render_shortcodes_card(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- method escapes consumer content before passing HTML to Base Card. ?>
		<?php
	}

	private static function render_general(): void {
		$rewrite_base = Settings::rewrite_base();
		$url_structure = Settings::url_structure();
		$readiness = Readiness::analyze();
		$simple_example = home_url( '/' . $rewrite_base . '/example-doc/' );
		$hierarchy_example = home_url( '/' . $rewrite_base . '/wp-suite/core-blueprint-base/example-doc/' );
		$updated = isset( $_GET['cb_docs_updated'] )
			? sanitize_key( (string) wp_unslash( $_GET['cb_docs_updated'] ) )
			: ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only redirect state.
		?>
		<?php if ( 'changed' === $updated ) : ?>
			<?php
			echo Notice::render( [
				'variant' => Notice::SUCCESS,
				'title'   => __( 'Permalink settings updated', 'core-blueprint-docs' ),
				'message' => __( 'The Docs URL settings are active. WordPress rewrite rules were refreshed once after the new routes were registered.', 'core-blueprint-docs' ),
			] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base renderer returns escaped component HTML.
			?>
		<?php elseif ( 'unchanged' === $updated ) : ?>
			<?php
			echo Notice::render( [
				'variant' => Notice::INFO,
				'title'   => __( 'No changes needed', 'core-blueprint-docs' ),
				'message' => __( 'The Docs permalink settings already had these values, so no rewrite refresh was necessary.', 'core-blueprint-docs' ),
			] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base renderer returns escaped component HTML.
			?>
		<?php elseif ( 'blocked' === $updated ) : ?>
			<div class="notice notice-error inline">
				<p><strong><?php esc_html_e( 'Category hierarchy was not enabled.', 'core-blueprint-docs' ); ?></strong></p>
				<p><?php esc_html_e( 'Resolve the blocking URL conflicts shown below and save the permalink settings again.', 'core-blueprint-docs' ); ?></p>
			</div>
		<?php endif; ?>

		<section class="cb-core-panel">
			<h2><?php esc_html_e( 'Permalinks', 'core-blueprint-docs' ); ?></h2>
			<p><?php esc_html_e( 'Configure the public Docs namespace and choose whether document URLs stay simple or include the resolved Doc Category hierarchy.', 'core-blueprint-docs' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cb_docs_save_settings">
				<?php wp_nonce_field( 'cb_docs_save_settings', 'cb_docs_settings_nonce' ); ?>

				<p>
					<label for="cb_docs_rewrite_base"><strong><?php esc_html_e( 'Docs URL base', 'core-blueprint-docs' ); ?></strong></label><br>
					<input class="regular-text" type="text" id="cb_docs_rewrite_base" name="rewrite_base" value="<?php echo esc_attr( $rewrite_base ); ?>" placeholder="docs" autocomplete="off">
				</p>
				<p class="description">
					<?php esc_html_e( 'Used for the Docs archive and, in Category hierarchy mode, category, tag and document paths. Nested bases such as knowledge/docs are supported.', 'core-blueprint-docs' ); ?>
				</p>

				<fieldset class="cb-docs-permalink-structure">
					<legend><strong><?php esc_html_e( 'Document URL structure', 'core-blueprint-docs' ); ?></strong></legend>
					<p>
						<label>
							<input type="radio" name="url_structure" value="<?php echo esc_attr( Settings::URL_STRUCTURE_SIMPLE ); ?>" <?php checked( Settings::URL_STRUCTURE_SIMPLE, $url_structure ); ?>>
							<strong><?php esc_html_e( 'Simple', 'core-blueprint-docs' ); ?></strong>
						</label><br>
						<code><?php echo esc_html( $simple_example ); ?></code>
					</p>
					<p>
						<label>
							<input type="radio" name="url_structure" value="<?php echo esc_attr( Settings::URL_STRUCTURE_HIERARCHY ); ?>" <?php checked( Settings::URL_STRUCTURE_HIERARCHY, $url_structure ); ?>>
							<strong><?php esc_html_e( 'Category hierarchy', 'core-blueprint-docs' ); ?></strong>
						</label><br>
						<code><?php echo esc_html( $hierarchy_example ); ?></code>
					</p>
					<p class="description">
						<?php esc_html_e( 'Category hierarchy uses the same structural category resolution as the Documentation Organizer. Safe parent categories are included automatically; unresolved or colliding documents use the reserved document/ fail-safe route.', 'core-blueprint-docs' ); ?>
					</p>
				</fieldset>

				<div class="cb-docs-readiness">
					<h3><?php esc_html_e( 'Category hierarchy readiness', 'core-blueprint-docs' ); ?></h3>
					<?php if ( 0 === $readiness['blocking'] ) : ?>
						<p><strong><?php esc_html_e( 'Ready', 'core-blueprint-docs' ); ?></strong> — <?php esc_html_e( 'No blocking URL conflicts were detected.', 'core-blueprint-docs' ); ?></p>
					<?php else : ?>
						<p><strong><?php esc_html_e( 'Action required', 'core-blueprint-docs' ); ?></strong> — <?php echo esc_html( sprintf( _n( '%d blocking URL conflict was detected.', '%d blocking URL conflicts were detected.', $readiness['blocking'], 'core-blueprint-docs' ), $readiness['blocking'] ) ); ?></p>
					<?php endif; ?>
					<ul>
						<li><?php echo esc_html( sprintf( __( 'Reserved top-level category slugs: %d', 'core-blueprint-docs' ), $readiness['reserved_categories'] ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'Duplicate category paths: %d', 'core-blueprint-docs' ), $readiness['duplicate_category_paths'] ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'Duplicate document routes: %d', 'core-blueprint-docs' ), $readiness['duplicate_document_paths'] ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'Legacy Simple URL collisions: %d', 'core-blueprint-docs' ), $readiness['legacy_simple_collisions'] ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'Documents using the fail-safe route because of a category collision: %d', 'core-blueprint-docs' ), $readiness['document_category_collisions'] ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'Unassigned documents using the fail-safe route: %d', 'core-blueprint-docs' ), $readiness['unassigned'] ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'Documents that need structural review: %d', 'core-blueprint-docs' ), $readiness['ambiguous'] ) ); ?></li>
					</ul>
				</div>

				<?php submit_button( __( 'Save permalinks', 'core-blueprint-docs' ) ); ?>
			</form>
		</section>
		<?php
	}

	private static function render_integrations(): void {
		echo IntegrationGrid::render( IntegrationReadiness::items() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base IntegrationGrid owns escaping and presentation.
	}

	private static function render_shortcodes_card(): string {
		$rows = '';
		foreach ( self::shortcodes() as $shortcode ) {
			$rows .= '<tr>';
			$rows .= '<td><code>' . esc_html( $shortcode['code'] ) . '</code></td>';
			$rows .= '<td>' . esc_html( $shortcode['description'] ) . '</td>';
			$rows .= '<td>' . ( '' !== $shortcode['attributes'] ? '<code>' . esc_html( $shortcode['attributes'] ) . '</code>' : '<span aria-hidden="true">—</span>' ) . '</td>';
			$rows .= '<td><button type="button" class="button cb-core-button cb-core-button--secondary cb-core-button--compact" data-cb-docs-shortcode-copy="' . esc_attr( $shortcode['code'] ) . '"><span class="cb-core-button__label">' . esc_html__( 'Copy', 'core-blueprint-docs' ) . '</span></button></td>';
			$rows .= '</tr>';
		}

		$body  = '<p class="cb-core-card__lead">' . esc_html__( 'Use these built-in shortcodes to place Docs content in WordPress content areas. Copy a shortcode and add optional attributes where needed.', 'core-blueprint-docs' ) . '</p>';
		$body .= '<table class="widefat"><thead><tr>';
		$body .= '<th scope="col">' . esc_html__( 'Shortcode', 'core-blueprint-docs' ) . '</th>';
		$body .= '<th scope="col">' . esc_html__( 'Purpose', 'core-blueprint-docs' ) . '</th>';
		$body .= '<th scope="col">' . esc_html__( 'Optional attributes', 'core-blueprint-docs' ) . '</th>';
		$body .= '<th scope="col">' . esc_html__( 'Action', 'core-blueprint-docs' ) . '</th>';
		$body .= '</tr></thead><tbody>' . $rows . '</tbody></table>';

		return Card::render( [
			'title' => __( 'Shortcodes', 'core-blueprint-docs' ),
			'body'  => $body,
		] );
	}

	/** @return array<int,array{code:string,description:string,attributes:string}> */
	private static function shortcodes(): array {
		return [
			[
				'code'        => '[cb_docs_list]',
				'description' => __( 'Lists published Docs ordered by menu order and title.', 'core-blueprint-docs' ),
				'attributes'  => 'category="slug" tag="slug" limit="20" excerpt="true"',
			],
			[
				'code'        => '[cb_docs_navigation]',
				'description' => __( 'Renders the hierarchical Doc Category tree with its published Docs.', 'core-blueprint-docs' ),
				'attributes'  => 'category="slug"',
			],
			[
				'code'        => '[cb_docs_search]',
				'description' => __( 'Renders a documentation search form with scoped Docs results.', 'core-blueprint-docs' ),
				'attributes'  => 'placeholder="…" limit="20"',
			],
			[
				'code'        => '[cb_docs_breadcrumbs]',
				'description' => __( 'Renders archive, category and single-document breadcrumbs in Docs contexts.', 'core-blueprint-docs' ),
				'attributes'  => '',
			],
			[
				'code'        => '[cb_docs_meta]',
				'description' => __( 'Renders documentation metadata for the current Doc or a specific Doc ID.', 'core-blueprint-docs' ),
				'attributes'  => 'id="123"',
			],
		];
	}
}
