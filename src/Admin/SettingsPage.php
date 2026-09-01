<?php
declare(strict_types=1);

namespace CB\Docs\Admin;

use CB\Core\Admin\Page as PageContract;
use CB\Core\Admin\PageRegistry;
use CB\Core\UI\Notice;
use CB\Docs\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage implements PageContract {
	public const SLUG = 'core-blueprint-docs-settings';

	public static function init(): void {
		add_action( 'cb_core_register_pages', [ __CLASS__, 'register' ] );
	}

	public static function register(): void {
		PageRegistry::register(
			new self(),
			[
				'components' => [
					'panels',
					'notices',
					'form-controls',
				],
			]
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
		</div>
		<?php
	}
}
