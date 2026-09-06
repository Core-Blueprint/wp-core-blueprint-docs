<?php
declare(strict_types=1);

namespace CB\Docs\Integration\Builders\Bricks\Elements;

use CB\Docs\Frontend\Assets;
use CB\Docs\Frontend\Components\Search as SearchComponent;

defined( 'ABSPATH' ) || exit;

final class Search extends \Bricks\Element {
	public $category = 'core-blueprint-docs';
	public $name     = 'cb-docs-search';
	public $icon     = 'ti-search';

	public function get_label(): string {
		return esc_html__( 'Docs Search', 'core-blueprint-docs' );
	}

	/** @return string[] */
	public function get_keywords(): array {
		return [ 'core blueprint', 'docs', 'documentation', 'search' ];
	}

	public function set_control_groups(): void {
		$this->control_groups['search'] = [
			'title' => esc_html__( 'Search', 'core-blueprint-docs' ),
			'tab'   => 'content',
		];
		$this->control_groups['input'] = [
			'title' => esc_html__( 'Input', 'core-blueprint-docs' ),
			'tab'   => 'style',
		];
		$this->control_groups['button'] = [
			'title' => esc_html__( 'Button', 'core-blueprint-docs' ),
			'tab'   => 'style',
		];
		$this->control_groups['results'] = [
			'title' => esc_html__( 'Results', 'core-blueprint-docs' ),
			'tab'   => 'style',
		];
	}

	public function set_controls(): void {
		$this->controls['usage'] = [
			'tab'     => 'content',
			'group'   => 'search',
			'type'    => 'info',
			'content' => esc_html__( 'Live search is powered by the builder-neutral Core Blueprint Docs search service. Bricks only controls presentation.', 'core-blueprint-docs' ),
		];
		$this->controls['placeholder'] = [
			'tab'     => 'content',
			'group'   => 'search',
			'label'   => esc_html__( 'Placeholder', 'core-blueprint-docs' ),
			'type'    => 'text',
			'default' => esc_html__( 'Search documentation…', 'core-blueprint-docs' ),
		];
		$this->controls['limit'] = [
			'tab'     => 'content',
			'group'   => 'search',
			'label'   => esc_html__( 'Result limit', 'core-blueprint-docs' ),
			'type'    => 'number',
			'default' => 20,
			'min'     => 1,
			'max'     => 50,
			'step'    => 1,
		];
		$this->controls['minChars'] = [
			'tab'         => 'content',
			'group'       => 'search',
			'label'       => esc_html__( 'Minimum characters', 'core-blueprint-docs' ),
			'type'        => 'number',
			'default'     => 2,
			'min'         => 1,
			'max'         => 10,
			'step'        => 1,
			'description' => esc_html__( 'Live search starts after this many characters.', 'core-blueprint-docs' ),
		];
		$this->controls['showExcerpt'] = [
			'tab'     => 'content',
			'group'   => 'search',
			'label'   => esc_html__( 'Show excerpts', 'core-blueprint-docs' ),
			'type'    => 'checkbox',
			'default' => true,
		];
		$this->controls['showCategory'] = [
			'tab'     => 'content',
			'group'   => 'search',
			'label'   => esc_html__( 'Show category', 'core-blueprint-docs' ),
			'type'    => 'checkbox',
			'default' => true,
		];
		$this->controls['category'] = [
			'tab'         => 'content',
			'group'       => 'search',
			'label'       => esc_html__( 'Category slug', 'core-blueprint-docs' ),
			'type'        => 'text',
			'description' => esc_html__( 'Optional. Limit search to one Docs category.', 'core-blueprint-docs' ),
		];
		$this->controls['tag'] = [
			'tab'         => 'content',
			'group'       => 'search',
			'label'       => esc_html__( 'Tag slug', 'core-blueprint-docs' ),
			'type'        => 'text',
			'description' => esc_html__( 'Optional. Limit search to one Docs tag.', 'core-blueprint-docs' ),
		];

		$this->controls['inputTypography'] = [
			'tab'   => 'style',
			'group' => 'input',
			'label' => esc_html__( 'Typography', 'core-blueprint-docs' ),
			'type'  => 'typography',
			'css'   => [ [ 'property' => 'typography', 'selector' => '.cb-docs-search__input' ] ],
		];
		$this->controls['inputBackground'] = [
			'tab'   => 'style',
			'group' => 'input',
			'label' => esc_html__( 'Background', 'core-blueprint-docs' ),
			'type'  => 'background',
			'css'   => [ [ 'property' => 'background', 'selector' => '.cb-docs-search__input' ] ],
		];
		$this->controls['inputBorder'] = [
			'tab'   => 'style',
			'group' => 'input',
			'label' => esc_html__( 'Border', 'core-blueprint-docs' ),
			'type'  => 'border',
			'css'   => [ [ 'property' => 'border', 'selector' => '.cb-docs-search__input' ] ],
		];
		$this->controls['inputPadding'] = [
			'tab'   => 'style',
			'group' => 'input',
			'label' => esc_html__( 'Padding', 'core-blueprint-docs' ),
			'type'  => 'dimensions',
			'css'   => [ [ 'property' => 'padding', 'selector' => '.cb-docs-search__input' ] ],
		];

		$this->controls['buttonTypography'] = [
			'tab'   => 'style',
			'group' => 'button',
			'label' => esc_html__( 'Typography', 'core-blueprint-docs' ),
			'type'  => 'typography',
			'css'   => [ [ 'property' => 'typography', 'selector' => '.cb-docs-search__submit' ] ],
		];
		$this->controls['buttonBackground'] = [
			'tab'   => 'style',
			'group' => 'button',
			'label' => esc_html__( 'Background', 'core-blueprint-docs' ),
			'type'  => 'background',
			'css'   => [ [ 'property' => 'background', 'selector' => '.cb-docs-search__submit' ] ],
		];
		$this->controls['buttonBorder'] = [
			'tab'   => 'style',
			'group' => 'button',
			'label' => esc_html__( 'Border', 'core-blueprint-docs' ),
			'type'  => 'border',
			'css'   => [ [ 'property' => 'border', 'selector' => '.cb-docs-search__submit' ] ],
		];
		$this->controls['buttonPadding'] = [
			'tab'   => 'style',
			'group' => 'button',
			'label' => esc_html__( 'Padding', 'core-blueprint-docs' ),
			'type'  => 'dimensions',
			'css'   => [ [ 'property' => 'padding', 'selector' => '.cb-docs-search__submit' ] ],
		];

		$this->controls['resultsBackground'] = [
			'tab'   => 'style',
			'group' => 'results',
			'label' => esc_html__( 'Background', 'core-blueprint-docs' ),
			'type'  => 'background',
			'css'   => [ [ 'property' => 'background', 'selector' => '.cb-docs-search__results' ] ],
		];
		$this->controls['resultsBorder'] = [
			'tab'   => 'style',
			'group' => 'results',
			'label' => esc_html__( 'Border', 'core-blueprint-docs' ),
			'type'  => 'border',
			'css'   => [ [ 'property' => 'border', 'selector' => '.cb-docs-search__results' ] ],
		];
		$this->controls['resultTitleTypography'] = [
			'tab'   => 'style',
			'group' => 'results',
			'label' => esc_html__( 'Title typography', 'core-blueprint-docs' ),
			'type'  => 'typography',
			'css'   => [ [ 'property' => 'typography', 'selector' => '.cb-docs-search__title' ] ],
		];
		$this->controls['resultCategoryTypography'] = [
			'tab'   => 'style',
			'group' => 'results',
			'label' => esc_html__( 'Category typography', 'core-blueprint-docs' ),
			'type'  => 'typography',
			'css'   => [ [ 'property' => 'typography', 'selector' => '.cb-docs-search__category' ] ],
		];
		$this->controls['resultExcerptTypography'] = [
			'tab'   => 'style',
			'group' => 'results',
			'label' => esc_html__( 'Excerpt typography', 'core-blueprint-docs' ),
			'type'  => 'typography',
			'css'   => [ [ 'property' => 'typography', 'selector' => '.cb-docs-search__excerpt' ] ],
		];
	}

	public function enqueue_scripts(): void {
		Assets::enqueue_search();
	}

	public function render(): void {
		$settings = $this->settings;
		$args = [
			'placeholder'   => (string) ( $settings['placeholder'] ?? __( 'Search documentation…', 'core-blueprint-docs' ) ),
			'limit'         => $settings['limit'] ?? 20,
			'min_chars'     => $settings['minChars'] ?? 2,
			'show_excerpt'  => self::checkbox( $settings, 'showExcerpt', true ),
			'show_category' => self::checkbox( $settings, 'showCategory', true ),
			'category'      => (string) ( $settings['category'] ?? '' ),
			'tag'           => (string) ( $settings['tag'] ?? '' ),
		];

		$this->set_attribute( '_root', 'class', 'cb-docs-bricks-search' );
		echo '<div ' . $this->render_attributes( '_root' ) . '>' . SearchComponent::render( $args ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted builder-neutral Docs renderer output.
	}

	/** @param array<string,mixed> $settings */
	private static function checkbox( array $settings, string $key, bool $default ): bool {
		if ( ! array_key_exists( $key, $settings ) ) {
			return $default;
		}
		return (bool) $settings[ $key ];
	}
}
