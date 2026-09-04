<?php
declare(strict_types=1);

namespace CB\Docs\Admin;

use CB\Core\Admin\Page as PageContract;
use CB\Core\Admin\PageRegistry;
use CB\Core\UI\Card;
use CB\Core\UI\Notice;
use CB\Docs\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage implements PageContract {
	public const SLUG = 'core-blueprint-docs-settings';

	public static function init(): void {
		add_action( 'cb_core_register_pages', [ __CLASS__, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function register(): void {
		PageRegistry::register(
			new self(),
			[
				'foundations' => [
					'clipboard',
				],
				'components' => [
					'panels',
					'notices',
					'form-controls',
					'cards',
				],
			]
		);
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== PageRegistry::hook_suffix( self::SLUG ) ) {
			return;
		}

		wp_enqueue_script_module(
			'@cb-docs/admin-shortcodes',
			CB_DOCS_URL . 'assets/js/admin-shortcodes.js',
			[ '@cb-core/clipboard' ],
			CB_DOCS_VERSION
		);
	}

	public function slug(): string {
		return self::SLUG;
	}

	public function title(): string {
		return __( 'Docs Settings', 'core-blueprint-docs' );
	}

	public function menu_title(): string {
		return __( 'Docs', 'core-blueprint-docs' );
	}

	public function capability(): string {
		return 'manage_options';
	}

	public function position(): ?int {
		return null;
	}

	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'core-blueprint-docs' ) );
		}

		$rewrite_base = Settings::rewrite_base();
		$example      = home_url( '/' . $rewrite_base . '/example-doc/' );
		$updated      = isset( $_GET['cb_docs_updated'] )
			? sanitize_key( (string) wp_unslash( $_GET['cb_docs_updated'] ) )
			: ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only redirect state.
		?>
		<div class="wrap cb-core-wrap cb-docs-settings-wrap">
			<h1 class="cb-core-title"><?php esc_html_e( 'Core Blueprint Docs', 'core-blueprint-docs' ); ?></h1>
			<p class="cb-core-intro">
				<?php esc_html_e( 'Configure the small set of site-wide Docs settings. Documentation content and presentation remain native WordPress and builder-agnostic.', 'core-blueprint-docs' ); ?>
			</p>

			<?php if ( 'changed' === $updated ) : ?>
				<?php
				echo Notice::render( [
					'variant' => Notice::SUCCESS,
					'title'   => __( 'URL base updated', 'core-blueprint-docs' ),
					'message' => __( 'The new Docs URL base is active. WordPress rewrite rules were refreshed once after the new route was registered.', 'core-blueprint-docs' ),
				] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base renderer returns escaped component HTML.
				?>
			<?php elseif ( 'unchanged' === $updated ) : ?>
				<?php
				echo Notice::render( [
					'variant' => Notice::INFO,
					'title'   => __( 'No changes needed', 'core-blueprint-docs' ),
					'message' => __( 'The Docs URL base already had this value, so no rewrite refresh was necessary.', 'core-blueprint-docs' ),
				] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Base renderer returns escaped component HTML.
				?>
			<?php endif; ?>

			<section class="cb-core-panel">
				<h2><?php esc_html_e( 'Permalinks', 'core-blueprint-docs' ); ?></h2>
				<p><?php esc_html_e( 'Choose the URL base used by the Docs archive and every individual Doc. Changing this later changes public URLs, so existing external links may need redirects.', 'core-blueprint-docs' ); ?></p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cb_docs_save_settings">
					<?php wp_nonce_field( 'cb_docs_save_settings', 'cb_docs_settings_nonce' ); ?>

					<p>
						<label for="cb_docs_rewrite_base"><strong><?php esc_html_e( 'Docs URL base', 'core-blueprint-docs' ); ?></strong></label><br>
						<input class="regular-text" type="text" id="cb_docs_rewrite_base" name="rewrite_base" value="<?php echo esc_attr( $rewrite_base ); ?>" placeholder="docs" autocomplete="off">
					</p>
					<p class="description">
						<?php esc_html_e( 'Use a slug such as docs, documentation or handleiding. Nested paths such as knowledge/docs are also supported. Empty or invalid input falls back to docs.', 'core-blueprint-docs' ); ?>
					</p>
					<p class="description">
						<strong><?php esc_html_e( 'Example:', 'core-blueprint-docs' ); ?></strong>
						<code><?php echo esc_html( $example ); ?></code>
					</p>

					<?php submit_button( __( 'Save URL base', 'core-blueprint-docs' ) ); ?>
				</form>
			</section>

			<?php echo self::render_shortcodes_card(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- method escapes all consumer-owned content before passing HTML to Base Card. ?>
		</div>
		<?php
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
