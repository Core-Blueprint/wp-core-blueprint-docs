<?php
declare(strict_types=1);

namespace CB\Docs\Admin;

use CoreBlueprint\Core\Admin\SettingsRegistry;
use CoreBlueprint\Core\UI\Card;
use CoreBlueprint\Core\UI\ChoiceGroup;
use CoreBlueprint\Core\UI\Field;
use CoreBlueprint\Core\UI\IntegrationGrid;
use CoreBlueprint\Core\UI\Notice;
use CoreBlueprint\Core\UI\RadioCard;
use CoreBlueprint\Core\UI\RadioGroup;
use CB\Docs\Content\PostType;
use CB\Docs\Content\Taxonomies;
use CB\Docs\Governance\Events;
use CB\Docs\Integration\Preferences;
use CB\Docs\Integration\Suite;
use CB\Docs\Permalinks\Readiness;
use CB\Docs\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {
	private const TAB_OVERVIEW     = 'overview';
	private const TAB_GENERAL      = 'general';
	private const TAB_INTEGRATIONS = 'integrations';

	public static function init(): void {
		add_action( 'core_blueprint_register_settings', [ __CLASS__, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_notices', [ __CLASS__, 'namespace_notice' ] );
		add_action( 'admin_post_cb_docs_save_integrations', [ __CLASS__, 'save_integrations' ] );
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
						'choice-group',
						'clipboard',
					],
					'components' => [
						'actions',
						'cards',
						'fields',
						'form-controls',
						'integration-grid',
						'metric-tiles',
						'nav-tabs',
						'notices',
						'panels',
						'radio-cards',
						'status',
					],
				],
			]
		);
	}

	public static function namespace_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || Readiness::runtime_ready( Settings::rewrite_base() ) ) {
			return;
		}

		$url = SettingsRegistry::url( Suite::ID, [ 'tab' => self::TAB_GENERAL ] );
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Core Blueprint Docs:', 'core-blueprint-docs' ); ?></strong>
				<?php esc_html_e( 'Public Docs URLs are paused because the configured URL base is already in use.', 'core-blueprint-docs' ); ?>
				<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Review permalink settings', 'core-blueprint-docs' ); ?></a>
			</p>
		</div>
		<?php
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
		$stored_base = Settings::rewrite_base();
		$url_structure = Settings::url_structure();
		$updated = isset( $_GET['cb_docs_updated'] )
			? sanitize_key( (string) wp_unslash( $_GET['cb_docs_updated'] ) )
			: ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only redirect state.
		$candidate = isset( $_GET['cb_docs_candidate'] ) && is_string( $_GET['cb_docs_candidate'] )
			? Settings::sanitize_rewrite_base( wp_unslash( $_GET['cb_docs_candidate'] ) )
			: ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only redirect state.
		$display_base = 'blocked' === $updated && '' !== $candidate ? $candidate : $stored_base;
		$readiness = Readiness::analyze( $display_base );
		$namespace = Readiness::namespace_analyze( $display_base );
		$simple_example = home_url( '/' . $display_base . '/example-doc/' );
		$hierarchy_example = home_url( '/' . $display_base . '/wp-suite/core-blueprint-base/example-doc/' );
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
			<?php
			echo Notice::render( [
				'variant' => Notice::ERROR,
				'title'   => __( 'Permalink settings not changed', 'core-blueprint-docs' ),
				'message' => __( 'Resolve the blocking URL conflicts shown below. The previous Docs settings were kept.', 'core-blueprint-docs' ),
			] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base renderer returns escaped component HTML.
			?>
		<?php elseif ( ! Readiness::runtime_ready( $stored_base ) ) : ?>
			<?php
			echo Notice::render( [
				'variant' => Notice::WARNING,
				'title'   => __( 'Public Docs URLs are paused', 'core-blueprint-docs' ),
				'message' => __( 'The configured URL base conflicts with an existing public route or reserved WordPress path. Existing site content keeps priority until you choose an available Docs URL base.', 'core-blueprint-docs' ),
			] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base renderer returns escaped component HTML.
			?>
		<?php endif; ?>

		<section class="cb-core-panel">
			<h2><?php esc_html_e( 'Permalinks', 'core-blueprint-docs' ); ?></h2>
			<p><?php esc_html_e( 'Configure the public Docs namespace and choose whether document URLs stay simple or include the resolved Doc Category hierarchy.', 'core-blueprint-docs' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cb_docs_save_settings">
				<?php wp_nonce_field( 'cb_docs_save_settings', 'cb_docs_settings_nonce' ); ?>

				<p>
					<label for="cb_docs_rewrite_base"><strong><?php esc_html_e( 'Docs URL base', 'core-blueprint-docs' ); ?></strong></label><br>
					<input class="regular-text" type="text" id="cb_docs_rewrite_base" name="rewrite_base" value="<?php echo esc_attr( $display_base ); ?>" placeholder="docs" autocomplete="off">
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
					<h3><?php esc_html_e( 'Public namespace readiness', 'core-blueprint-docs' ); ?></h3>
					<?php if ( $namespace['ready'] ) : ?>
						<p><strong><?php esc_html_e( 'Available', 'core-blueprint-docs' ); ?></strong> <?php esc_html_e( 'No conflicting public namespace was detected.', 'core-blueprint-docs' ); ?></p>
					<?php else : ?>
						<p><strong><?php esc_html_e( 'Conflicts detected', 'core-blueprint-docs' ); ?></strong></p>
						<ul>
							<?php foreach ( $namespace['conflicts'] as $conflict ) : ?>
								<li>
									<?php echo esc_html( $conflict['label'] ); ?>
									<?php if ( '' !== $conflict['url'] ) : ?>
										<a href="<?php echo esc_url( $conflict['url'] ); ?>"><?php esc_html_e( 'Open item', 'core-blueprint-docs' ); ?></a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>

				<div class="cb-docs-readiness">
					<h3><?php esc_html_e( 'Category hierarchy readiness', 'core-blueprint-docs' ); ?></h3>
					<?php if ( 0 === $readiness['blocking'] ) : ?>
						<p><strong><?php esc_html_e( 'Ready', 'core-blueprint-docs' ); ?></strong> — <?php esc_html_e( 'No blocking URL conflicts were detected.', 'core-blueprint-docs' ); ?></p>
					<?php else : ?>
						<p><strong><?php esc_html_e( 'Action required', 'core-blueprint-docs' ); ?></strong> — <?php echo esc_html( sprintf( __( 'Blocking URL conflicts: %d', 'core-blueprint-docs' ), $readiness['blocking'] ) ); ?></p>
					<?php endif; ?>
					<ul>
						<li><?php echo esc_html( sprintf( __( 'Reserved Docs URL base conflicts: %d', 'core-blueprint-docs' ), $readiness['reserved_base'] ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'Reserved top-level category slugs: %d', 'core-blueprint-docs' ), $readiness['reserved_categories'] ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'Duplicate category paths: %d', 'core-blueprint-docs' ), $readiness['duplicate_category_paths'] ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'Invalid category hierarchy paths: %d', 'core-blueprint-docs' ), $readiness['invalid_category_paths'] ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'Duplicate document routes: %d', 'core-blueprint-docs' ), $readiness['duplicate_document_paths'] ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'Duplicate legacy document slugs: %d', 'core-blueprint-docs' ), $readiness['duplicate_legacy_document_slugs'] ) ); ?></li>
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
		$preferences = Preferences::all();
		$updated = isset( $_GET['cb_docs_integrations_updated'] )
			? sanitize_key( (string) wp_unslash( $_GET['cb_docs_integrations_updated'] ) )
			: ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only redirect state.

		if ( 'changed' === $updated ) {
			echo Notice::render( [
				'variant' => Notice::SUCCESS,
				'title'   => __( 'Integration settings updated', 'core-blueprint-docs' ),
				'message' => __( 'Your Docs editor and builder adapter preferences are active.', 'core-blueprint-docs' ),
			] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base renderer returns escaped component HTML.
		} elseif ( 'unchanged' === $updated ) {
			echo Notice::render( [
				'variant' => Notice::INFO,
				'title'   => __( 'No integration changes needed', 'core-blueprint-docs' ),
				'message' => __( 'The selected integration preferences were already active.', 'core-blueprint-docs' ),
			] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base renderer returns escaped component HTML.
		}

		echo IntegrationGrid::render( IntegrationReadiness::items() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base IntegrationGrid owns escaping and presentation.
		?>
		<section class="cb-core-panel">
			<h2><?php esc_html_e( 'Adapter preferences', 'core-blueprint-docs' ); ?></h2>
			<p><?php esc_html_e( 'Choose which optional presentation adapters Docs may register. Core Docs content and public contracts remain available regardless of these choices.', 'core-blueprint-docs' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cb_docs_save_integrations">
				<?php wp_nonce_field( 'cb_docs_save_integrations', 'cb_docs_integrations_nonce' ); ?>

				<div class="cb-core-form-scope">
					<?php
					$gutenberg_control = ChoiceGroup::render( [
						'type'       => ChoiceGroup::TYPE_CHECKBOX,
						'aria_label' => __( 'Gutenberg blocks', 'core-blueprint-docs' ),
						'options'    => [
							[
								'name'    => 'gutenberg',
								'value'   => '1',
								'label'   => __( 'Make Docs blocks available in the WordPress block editor.', 'core-blueprint-docs' ),
								'checked' => $preferences['gutenberg'],
							],
						],
					] );

					echo Field::render( [
						'variant' => Field::VARIANT_SEPARATED,
						'label'   => __( 'Gutenberg blocks', 'core-blueprint-docs' ),
						'control' => $gutenberg_control,
					] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base UI primitives own escaping.

					$bricks_control = RadioGroup::render( [
						'variant' => RadioCard::VARIANT_COMPACT,
						'name'    => 'bricks',
						'value'   => $preferences['bricks'],
						'layout'  => RadioGroup::LAYOUT_GRID,
						'columns' => 3,
						'options' => [
							[
								'value' => Preferences::BRICKS_AUTO,
								'label' => __( 'Auto', 'core-blueprint-docs' ),
								'desc'  => __( 'Use the Bricks adapter when Bricks is active.', 'core-blueprint-docs' ),
							],
							[
								'value' => Preferences::BRICKS_ENABLED,
								'label' => __( 'Enabled', 'core-blueprint-docs' ),
								'desc'  => __( 'Keep the Bricks adapter enabled when Bricks is available.', 'core-blueprint-docs' ),
							],
							[
								'value' => Preferences::BRICKS_DISABLED,
								'label' => __( 'Disabled', 'core-blueprint-docs' ),
								'desc'  => __( 'Do not register Docs Bricks elements, queries, dynamic data or conditions.', 'core-blueprint-docs' ),
							],
						],
					] );

					echo Field::render( [
						'variant' => Field::VARIANT_SEPARATED,
						'label'   => __( 'Bricks Builder', 'core-blueprint-docs' ),
						'control' => $bricks_control,
					] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base UI primitives own escaping.
					?>
				</div>

				<div class="cb-core-actions">
					<?php echo get_submit_button( __( 'Save integrations', 'core-blueprint-docs' ), 'primary', 'submit', false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress owns button escaping. ?>
				</div>
			</form>
		</section>
		<?php
	}

	public static function save_integrations(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change Docs integration settings.', 'core-blueprint-docs' ) );
		}

		check_admin_referer( 'cb_docs_save_integrations', 'cb_docs_integrations_nonce' );

		$before = Preferences::all();
		$raw_bricks = isset( $_POST['bricks'] ) && is_string( $_POST['bricks'] )
			? wp_unslash( $_POST['bricks'] )
			: Preferences::BRICKS_AUTO;
		$after = [
			'gutenberg' => isset( $_POST['gutenberg'] ) && '1' === (string) wp_unslash( $_POST['gutenberg'] ),
			'bricks'    => Preferences::sanitize_bricks( $raw_bricks ),
		];

		Preferences::update( $after );
		$stored  = Preferences::all();
		$changed = $stored !== $before;

		if ( $before['gutenberg'] !== $stored['gutenberg'] ) {
			Events::record_settings_updated(
				'integration_gutenberg',
				$before['gutenberg'] ? 'enabled' : 'disabled',
				$stored['gutenberg'] ? 'enabled' : 'disabled'
			);
		}
		if ( $before['bricks'] !== $stored['bricks'] ) {
			Events::record_settings_updated( 'integration_bricks', $before['bricks'], $stored['bricks'] );
		}

		wp_safe_redirect(
			SettingsRegistry::url(
				Suite::ID,
				[
					'tab'                          => self::TAB_INTEGRATIONS,
					'cb_docs_integrations_updated' => $changed ? 'changed' : 'unchanged',
				]
			)
		);
		exit;
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
